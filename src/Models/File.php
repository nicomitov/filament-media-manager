<?php

namespace Slimani\MediaManager\Models;

use Closure;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Carbon;
use LogicException;
use Slimani\MediaManager\Concerns\BelongsToTenant;
use Slimani\MediaManager\Database\Factories\FileFactory;
use Slimani\MediaManager\MediaManagerPlugin;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int|null $uploaded_by_user_id
 * @property int|null $folder_id
 * @property string $name
 * @property string|null $caption
 * @property string|null $alt_text
 * @property int $size
 * @property string $extension
 * @property string $mime_type
 * @property int|null $width
 * @property int|null $height
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class File extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;

    public static ?Closure $registerMediaConversionsUsing = null;

    protected static function booted(): void
    {
        static::saving(function (Model $file): void {
            $plugin = static::resolveMediaManagerPlugin();

            if (
                $plugin?->isTenantAware()
                && $file->folder_id !== null
                && ! $plugin->getFolderModel()::query()->whereKey($file->folder_id)->exists()
            ) {
                throw new LogicException('A media file cannot be assigned to a folder from a different tenant.');
            }
        });
    }

    public static function registerMediaConversionsUsing(?Closure $callback): void
    {
        static::$registerMediaConversionsUsing = $callback;
    }

    protected static function newFactory(): FileFactory
    {
        return FileFactory::new();
    }

    protected $table = 'media_files';

    protected $fillable = [
        'uploaded_by_user_id',
        'folder_id',
        'name',
        'caption',
        'alt_text',
        'size',
        'extension',
        'mime_type',
        'width',
        'height',
    ];

    public function tags(): MorphToMany
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->morphToMany($plugin->getTagModel(), 'taggable', 'media_taggables');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('default')
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $thumbConversion = $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10)
            ->nonQueued();

        $previewConversion = $this->addMediaConversion('preview')
            ->width(800)
            ->height(800)
            ->nonQueued();

        try {
            /** @var MediaManagerPlugin $plugin */
            $plugin = filament('media-manager');
            if ($plugin->getWithVideoThumbnails()) {
                $thumbConversion->extractVideoFrameAtSecond(1);
                $previewConversion->extractVideoFrameAtSecond(1);
            }
        } catch (\Throwable $th) {
            //
        }

        if (static::$registerMediaConversionsUsing) {
            app()->call(static::$registerMediaConversionsUsing, [
                'file' => $this,
                'media' => $media,
            ]);
        }
    }

    public function folder(): BelongsTo
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->belongsTo($plugin->getFolderModel(), 'folder_id');
    }

    public function uploader(): BelongsTo
    {
        $userModel = config('auth.providers.users.model') ?? 'App\Models\User';

        if (! class_exists($userModel)) {
            // Fallback for tests or environments where the model isn't available yet
            return $this->belongsTo(User::class, 'uploaded_by_user_id');
        }

        return $this->belongsTo($userModel, 'uploaded_by_user_id');
    }

    public function getUrl(string $conversion = '', ?string $collection = null): ?string
    {
        $media = $this->getFirstMedia($collection ?? 'default') ?? $this->getFirstMedia();

        if (! $media) {
            return null;
        }

        // Originals live on the media disk, while named conversions may live on a dedicated conversions disk.
        // We prefer clean URLs for explicitly public disks and signed temporary URLs for everything else.
        if ($this->diskIsPublic($media, $conversion)) {
            return $media->getUrl($conversion);
        }

        try {
            return $media->getTemporaryUrl(now()->addMinutes(20), $conversion);
        } catch (\Throwable $exception) {
            // Some drivers cannot generate temporary URLs, so fall back to the normal URL generator.
            return $media->getUrl($conversion);
        }
    }

    protected function diskIsPublic(Media $media, string $conversion = ''): bool
    {
        $diskName = $this->resolveDiskNameForUrl($media, $conversion);

        return config("filesystems.disks.{$diskName}.visibility") === 'public';
    }

    protected function resolveDiskNameForUrl(Media $media, string $conversion = ''): string
    {
        if ($conversion !== '') {
            return $media->conversions_disk ?: $media->disk ?: config('media-library.disk_name');
        }

        return $media->disk ?: config('media-library.disk_name');
    }
}
