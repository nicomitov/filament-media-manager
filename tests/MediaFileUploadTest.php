<?php

namespace Slimani\MediaManager\Tests;

use Livewire\Livewire;
use Slimani\MediaManager\Tests\Components\TestMediaFileUploadForm;
use Slimani\MediaManager\Tests\Models\User;

uses(TestCase::class);

it('can render media file upload field', function () {
    $user = User::create(['name' => 'Test User']);
    Livewire::actingAs($user)
        ->test(TestMediaFileUploadForm::class)
        ->assertOk();
});

it('has browse action', function () {
    $user = User::create(['name' => 'Test User']);
    Livewire::actingAs($user)
        ->test(TestMediaFileUploadForm::class)
        ->assertSee('Browse Media');
});

it('does not throw an exception when state contains non-stringable objects', function () {
    $user = User::create(['name' => 'Test User']);
    $nonStringable = new \stdClass;

    Livewire::actingAs($user)
        ->test(TestMediaFileUploadForm::class)
        ->set('data.avatar_id', [$nonStringable])
        ->mountFormComponentAction('avatar_id', 'browse_media')
        ->callMountedFormComponentAction()
        ->assertOk();
});
