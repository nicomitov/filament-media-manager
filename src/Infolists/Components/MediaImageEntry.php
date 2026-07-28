<?php

namespace Slimani\MediaManager\Infolists\Components;

use Filament\Infolists\Components\ImageEntry;
use Illuminate\Support\Collection;
use Slimani\MediaManager\MediaManagerPlugin;

class MediaImageEntry extends ImageEntry
{
    protected string|\Closure|null $conversion = null;

    public function conversion(string|\Closure|null $name): static
    {
        $this->conversion = $name;

        return $this;
    }

    public function getConversion(): ?string
    {
        return $this->evaluate($this->conversion) ?? 'thumb';
    }

    public function getImageUrl(mixed $state = null): ?string
    {
        if (blank($state)) {
            $state = $this->getState();
        }

        if ($state instanceof Collection) {
            $state = $state->first();
        }

        if (is_numeric($state)) {
            /** @var MediaManagerPlugin $plugin */
            $plugin = filament('media-manager');
            $fileModel = $plugin->getFileModel();
            $state = $fileModel::find($state);
        }

        return ($state instanceof ($this->getFileModel())) ? $state->getUrl($this->getConversion()) : parent::getImageUrl($state);
    }

    protected function getFileModel(): string
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $plugin->getFileModel();
    }
}
