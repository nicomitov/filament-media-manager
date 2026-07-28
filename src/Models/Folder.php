<?php

namespace Slimani\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;
use Slimani\MediaManager\Concerns\BelongsToTenant;
use Slimani\MediaManager\MediaManagerPlugin;

/**
 * @property int $id
 * @property string $name
 * @property int|null $parent_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Folder extends Model
{
    use BelongsToTenant;

    protected static function booted(): void
    {
        static::saving(function (self $folder): void {
            $plugin = static::resolveMediaManagerPlugin();

            if (
                $plugin?->isTenantAware()
                && $folder->parent_id !== null
                && ! $plugin->getFolderModel()::query()->whereKey($folder->parent_id)->exists()
            ) {
                throw new LogicException('A media folder cannot be assigned to a parent from a different tenant.');
            }
        });
    }

    protected $table = 'media_folders';

    protected $fillable = [
        'name',
        'parent_id',
    ];

    public function tags(): MorphToMany
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->morphToMany($plugin->getTagModel(), 'taggable', 'media_taggables');
    }

    public function parent(): BelongsTo
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->belongsTo($plugin->getFolderModel(), 'parent_id');
    }

    public function children(): HasMany
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->hasMany($plugin->getFolderModel(), 'parent_id');
    }

    public function files(): HasMany
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->hasMany($plugin->getFileModel(), 'folder_id');
    }

    /**
     * Fetch all child folder IDs beneath this folder using a high-performance Recursive CTE.
     * This executes a single raw SQL query to get infinite depth instead of N+1 PHP recursion.
     */
    public function getAllDescendantIds(): array
    {
        $folderTable = $this->getTable();
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');
        $tenantColumn = $plugin->getTenantColumn();
        $tenantKey = $plugin->getTenantKey();

        if ($plugin->isTenantAware()) {
            $query = "
                WITH RECURSIVE FolderHierarchy AS (
                    SELECT id, parent_id FROM {$folderTable}
                    WHERE id = ? AND {$tenantColumn} = ?
                    UNION ALL
                    SELECT f.id, f.parent_id FROM {$folderTable} f
                    INNER JOIN FolderHierarchy fh ON fh.id = f.parent_id
                    WHERE f.{$tenantColumn} = ?
                )
                SELECT id FROM FolderHierarchy WHERE id != ?
            ";

            $results = DB::select($query, [$this->id, $tenantKey, $tenantKey, $this->id]);

            return array_column($results, 'id');
        }

        $query = "
            WITH RECURSIVE FolderHierarchy AS (
                SELECT id, parent_id FROM {$folderTable} WHERE id = ?
                UNION ALL
                SELECT f.id, f.parent_id FROM {$folderTable} f
                INNER JOIN FolderHierarchy fh ON fh.id = f.parent_id
            )
            SELECT id FROM FolderHierarchy WHERE id != ?
        ";

        $results = DB::select($query, [$this->id, $this->id]);

        return array_column($results, 'id');
    }

    /**
     * Get recursive statistics for this folder (total size and files count across all levels).
     */
    public function getRecursiveStats(): array
    {
        $folderTable = $this->getTable();
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');
        $fileTable = (new ($plugin->getFileModel()))->getTable();
        $tenantColumn = $plugin->getTenantColumn();
        $tenantKey = $plugin->getTenantKey();

        if ($plugin->isTenantAware()) {
            $query = "
                WITH RECURSIVE FolderHierarchy AS (
                    SELECT id FROM {$folderTable}
                    WHERE id = ? AND {$tenantColumn} = ?
                    UNION ALL
                    SELECT f.id FROM {$folderTable} f
                    INNER JOIN FolderHierarchy fh ON fh.id = f.parent_id
                    WHERE f.{$tenantColumn} = ?
                )
                SELECT
                    COUNT(DISTINCT {$fileTable}.id) as files_count,
                    SUM({$fileTable}.size) as total_size,
                    (SELECT COUNT(*) FROM FolderHierarchy WHERE id != ?) as folders_count
                FROM FolderHierarchy
                LEFT JOIN {$fileTable}
                    ON {$fileTable}.folder_id = FolderHierarchy.id
                    AND {$fileTable}.{$tenantColumn} = ?
            ";

            $result = DB::selectOne($query, [
                $this->id,
                $tenantKey,
                $tenantKey,
                $this->id,
                $tenantKey,
            ]);

            return [
                'files_count' => (int) ($result->files_count ?? 0),
                'folders_count' => (int) ($result->folders_count ?? 0),
                'total_size' => (int) ($result->total_size ?? 0),
            ];
        }

        $query = "
            WITH RECURSIVE FolderHierarchy AS (
                SELECT id FROM {$folderTable} WHERE id = ?
                UNION ALL
                SELECT f.id FROM {$folderTable} f
                INNER JOIN FolderHierarchy fh ON fh.id = f.parent_id
            )
            SELECT 
                COUNT(DISTINCT {$fileTable}.id) as files_count,
                SUM({$fileTable}.size) as total_size,
                (SELECT COUNT(*) FROM FolderHierarchy WHERE id != ?) as folders_count
            FROM FolderHierarchy
            LEFT JOIN {$fileTable} ON {$fileTable}.folder_id = FolderHierarchy.id
        ";

        $result = DB::selectOne($query, [$this->id, $this->id]);

        return [
            'files_count' => (int) ($result->files_count ?? 0),
            'folders_count' => (int) ($result->folders_count ?? 0),
            'total_size' => (int) ($result->total_size ?? 0),
        ];
    }
}
