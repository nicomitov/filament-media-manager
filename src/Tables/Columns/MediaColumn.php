<?php

namespace Slimani\MediaManager\Tables\Columns;

use Filament\Tables\Columns\ImageColumn;
use Illuminate\Support\Collection;
use Slimani\MediaManager\Models\File;

class MediaColumn extends ImageColumn
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

    public function getImageUrl(?string $state = null, ?\Illuminate\Database\Eloquent\Model $relatedRecord = null): ?string
    {
        // $state ??= $this->getState();
        $state = $this->getState();

        if ($state instanceof Collection) {
            $state = $state->first();
        }

        return ($state instanceof File) ? $state->getUrl($this->getConversion()) : null;
    }
}
