<?php

namespace Slimani\MediaManager;

use BackedEnum;
use Closure;
use Filament\Contracts\Plugin;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Support\Concerns\EvaluatesClosures;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\Folder;
use Slimani\MediaManager\Models\MediaAttachment;
use Slimani\MediaManager\Models\Tag;
use Slimani\MediaManager\Pages\MediaManager;

class MediaManagerPlugin implements Plugin
{
    use EvaluatesClosures;

    protected string|Closure $page = MediaManager::class;

    protected string|Closure $fileModel = File::class;

    protected string|Closure $folderModel = Folder::class;

    protected string|Closure $tagModel = Tag::class;

    protected string|Closure $attachmentModel = MediaAttachment::class;

    protected string|Closure|null $disk = null;

    protected bool|Closure $tenantAware = false;

    protected string|Closure $tenantColumn = 'tenant_id';

    protected ?Closure $tenantResolver = null;

    protected string|Closure|null $navigationGroup = null;

    protected string|Closure|null $navigationLabel = null;

    protected string|Closure|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected int|Closure|null $navigationSort = null;

    protected bool|Closure $shouldRegisterNavigation = true;

    protected bool|Closure $withVideoThumbnails = false;

    protected array|Closure $headerWidgets = [];

    protected array|Closure $footerWidgets = [];

    protected View|Closure|null $header = null;

    protected View|Closure|null $footer = null;

    public function getId(): string
    {
        return 'media-manager';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            $this->getPage(),
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return app(static::class);
    }

    /*
     * Customize the Media Manager page or replace it entirely.
     *
     * @param  string|Closure  $page
     */
    public function mediaManagerPage(string|Closure $page): static
    {
        $this->page = $page;

        return $this;
    }

    public function getPage(): string
    {
        return (string) $this->evaluate($this->page);
    }

    public function fileModel(string|Closure $model): static
    {
        $this->fileModel = $model;

        return $this;
    }

    public function getFileModel(): string
    {
        return (string) $this->evaluate($this->fileModel);
    }

    public function folderModel(string|Closure $model): static
    {
        $this->folderModel = $model;

        return $this;
    }

    public function getFolderModel(): string
    {
        return (string) $this->evaluate($this->folderModel);
    }

    public function tagModel(string|Closure $model): static
    {
        $this->tagModel = $model;

        return $this;
    }

    public function getTagModel(): string
    {
        return (string) $this->evaluate($this->tagModel);
    }

    public function attachmentModel(string|Closure $model): static
    {
        $this->attachmentModel = $model;

        return $this;
    }

    public function getAttachmentModel(): string
    {
        return (string) $this->evaluate($this->attachmentModel);
    }

    public function disk(string|Closure $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): string
    {
        return $this->evaluate($this->disk) ?? config('media-library.disk_name', 'media');
    }

    public function tenantAware(
        bool|Closure $condition = true,
        ?Closure $tenantResolver = null,
        string|Closure $tenantColumn = 'tenant_id',
    ): static {
        $this->tenantAware = $condition;
        $this->tenantResolver = $tenantResolver;
        $this->tenantColumn = $tenantColumn;

        return $this;
    }

    public function isTenantAware(): bool
    {
        return (bool) $this->evaluate($this->tenantAware);
    }

    public function getTenantColumn(): string
    {
        $column = (string) $this->evaluate($this->tenantColumn);

        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
            throw new InvalidArgumentException("Invalid tenant column [{$column}].");
        }

        return $column;
    }

    public function getTenantKey(): string|int|null
    {
        $tenant = $this->tenantResolver
            ? $this->evaluate($this->tenantResolver)
            : Filament::getTenant();

        if ($tenant instanceof Model) {
            $tenant = $tenant->getKey();
        }

        if ($tenant instanceof BackedEnum) {
            $tenant = $tenant->value;
        }

        if ($tenant === null || is_string($tenant) || is_int($tenant)) {
            return $tenant;
        }

        throw new InvalidArgumentException('The media tenant resolver must return a model, backed enum, string, integer, or null.');
    }

    public function navigationGroup(string|Closure|null $group): static
    {
        $this->navigationGroup = $group;

        return $this;
    }

    public function getNavigationGroup(): string
    {
        return $this->evaluate($this->navigationGroup)
            ?? __('media-manager::media-manager.navigation.group');
    }

    public function navigationLabel(string|Closure|null $label): static
    {
        $this->navigationLabel = $label;

        return $this;
    }

    public function getNavigationLabel(): string
    {
        return $this->evaluate($this->navigationLabel)
            ?? __('media-manager::media-manager.navigation.label');
    }

    public function navigationIcon(string|Closure|BackedEnum|null $icon): static
    {
        $this->navigationIcon = $icon;

        return $this;
    }

    public function getNavigationIcon(): string|BackedEnum|null
    {
        return $this->evaluate($this->navigationIcon);
    }

    public function navigationSort(int|Closure|null $sort): static
    {
        $this->navigationSort = $sort;

        return $this;
    }

    public function getNavigationSort(): ?int
    {
        return $this->evaluate($this->navigationSort);
    }

    public function shouldRegisterNavigation(bool|Closure $condition = true): static
    {
        $this->shouldRegisterNavigation = $condition;

        return $this;
    }

    public function getShouldRegisterNavigation(): bool
    {
        return (bool) $this->evaluate($this->shouldRegisterNavigation);
    }

    public function withVideoThumbnails(bool|Closure $condition = true): static
    {
        $this->withVideoThumbnails = $condition;

        return $this;
    }

    public function videoThumbnails(bool|Closure $condition = true): static
    {
        return $this->withVideoThumbnails($condition);
    }

    public function getWithVideoThumbnails(): bool
    {
        return (bool) $this->evaluate($this->withVideoThumbnails);
    }

    public function headerWidgets(array|Closure $widgets): static
    {
        $this->headerWidgets = $widgets;

        return $this;
    }

    public function getHeaderWidgets(): array
    {
        return $this->evaluate($this->headerWidgets);
    }

    public function footerWidgets(array|Closure $widgets): static
    {
        $this->footerWidgets = $widgets;

        return $this;
    }

    public function getFooterWidgets(): array
    {
        return $this->evaluate($this->footerWidgets);
    }

    public function header(View|Closure|null $header): static
    {
        $this->header = $header;

        return $this;
    }

    public function getHeader(): ?View
    {
        return $this->evaluate($this->header);
    }

    public function footer(View|Closure|null $footer): static
    {
        $this->footer = $footer;

        return $this;
    }

    public function getFooter(): ?View
    {
        return $this->evaluate($this->footer);
    }
}
