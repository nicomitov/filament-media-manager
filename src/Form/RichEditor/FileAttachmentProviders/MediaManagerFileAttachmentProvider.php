<?php

namespace Slimani\MediaManager\Form\RichEditor\FileAttachmentProviders;

use Closure;
use Filament\Forms\Components\RichEditor\FileAttachmentProviders\Contracts\FileAttachmentProvider;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Filament\Support\Concerns\EvaluatesClosures;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Slimani\MediaManager\MediaManagerPlugin;

class MediaManagerFileAttachmentProvider implements FileAttachmentProvider
{
    use EvaluatesClosures;

    protected RichContentAttribute $attribute;

    protected string|Closure|null $collection = null;

    protected string|Closure|null $directory = null;

    public static function make(): static
    {
        return app(static::class);
    }

    public function collection(string|Closure|null $collection): static
    {
        $this->collection = $collection;

        return $this;
    }

    public function directory(string|Closure|null $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function attribute(RichContentAttribute $attribute): static
    {
        $this->attribute = $attribute;

        return $this;
    }

    public function getFileAttachmentUrl(mixed $file): ?string
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');
        $fileModel = $plugin->getFileModel();
        $fileRecord = $fileModel::find($file);

        if (! $fileRecord) {
            return null;
        }

        return $fileRecord->getUrl(collection: $this->getCollection());
    }

    public function saveUploadedFileAttachment(TemporaryUploadedFile $file): string
    {
        $folderId = null;
        $directory = $this->getDirectory();

        if ($directory) {
            $segments = explode('/', trim($directory, '/'));
            $parentId = null;

            foreach ($segments as $segment) {
                /** @var MediaManagerPlugin $plugin */
                $plugin = filament('media-manager');
                $folderModel = $plugin->getFolderModel();
                $folder = $folderModel::firstOrCreate([
                    'name' => $segment,
                    'parent_id' => $parentId,
                ]);
                $parentId = $folder->id;
            }
            $folderId = $parentId;
        }

        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');
        $fileModelClass = $plugin->getFileModel();
        $fileModel = $fileModelClass::create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'uploaded_by_user_id' => auth()->id(),
            'folder_id' => $folderId,
        ]);

        $media = $fileModel->addMediaFromString($file->get())
            ->usingFileName($file->getClientOriginalName())
            ->toMediaCollection($this->getCollection() ?? 'default');

        $fileModel->update([
            'name' => $media->file_name,
            'size' => $media->size,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'width' => $media->getCustomProperty('width'),
            'height' => $media->getCustomProperty('height'),
        ]);

        return (string) $fileModel->id;
    }

    public function cleanUpFileAttachments(array $exceptIds): void
    {
        // For now, we don't implement full cleanup to avoid accidental deletion of shared media.
        // In a real scenario, we might want to delete Files that are only used in this collection/record.
    }

    public function getDefaultFileAttachmentVisibility(): ?string
    {
        return 'public';
    }

    public function isExistingRecordRequiredToSaveNewFileAttachments(): bool
    {
        return false;
    }

    public function getCollection(): ?string
    {
        return $this->evaluate($this->collection);
    }

    public function getDirectory(): ?string
    {
        return $this->evaluate($this->directory);
    }
}
