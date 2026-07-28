<?php

namespace Slimani\MediaManager\Concerns;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait InteractsWithMediaFiles
{
    /**
     * Get all of the model's media attachments.
     */
    public function mediaAttachments(): MorphMany
    {
        return $this->morphMany(filament('media-manager')->getAttachmentModel(), 'attachable');
    }

    /**
     * Get all of the model's media files.
     */
    public function mediaFiles(?string $collection = null): MorphToMany
    {
        $fileModel = filament('media-manager')->getFileModel();
        $attachmentModel = new (filament('media-manager')->getAttachmentModel());

        $relation = $this->morphToMany($fileModel, 'attachable', $attachmentModel->getTable(), 'attachable_id', 'media_file_id')
            ->withPivot('collection', 'sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order');

        if ($collection) {
            $relation->wherePivot('collection', $collection);
        }

        return $relation;
    }

    /**
     * Define a single media relation via a foreign key.
     */
    public function mediaFile(string $column): BelongsTo
    {
        return $this->belongsTo(filament('media-manager')->getFileModel(), $column);
    }
}
