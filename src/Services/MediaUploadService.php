<?php

namespace Slimani\MediaManager\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Slimani\MediaManager\MediaManagerPlugin;
use Slimani\MediaManager\Models\Folder;

class MediaUploadService
{
    public function upload(
        UploadedFile|TemporaryUploadedFile $file,
        ?Model $folder = null,
        ?int $userId = null,
        array $metadata = []
    ): Model {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');
        $fileModelClass = $plugin->getFileModel();
        /** @var Folder|null $folder */
        $fileModel = $fileModelClass::create([
            'folder_id' => $folder?->id,
            'uploaded_by_user_id' => $userId,
            'name' => $metadata['name'] ?? $file->getClientOriginalName(),
            'caption' => $metadata['caption'] ?? $file->getClientOriginalName(),
            'alt_text' => $metadata['alt_text'] ?? $file->getClientOriginalName(),
        ]);

        $fileModel->addMedia($file)
            ->toMediaCollection('default');

        return $fileModel;
    }
}
