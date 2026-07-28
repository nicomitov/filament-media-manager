<?php

namespace Slimani\MediaManager\Tests\Components;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Livewire\Component;
use Slimani\MediaManager\Form\MediaPicker;

class TestMediaFileUploadForm extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public $data;

    public function mount()
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                MediaPicker::make('avatar_id'),
            ])
            ->statePath('data');
    }

    public function render()
    {
        return '<div>{{ $this->form }}</div>';
    }
}
