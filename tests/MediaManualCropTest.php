<?php

namespace Slimani\MediaManager\Tests;

use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use ReflectionMethod;
use Slimani\MediaManager\Livewire\MediaBrowser;
use Slimani\MediaManager\Models\File;
use Spatie\Image\Enums\Fit;

uses(TestCase::class);

it('lists app-registered conversions as croppable', function () {
    File::registerMediaConversionsUsing(function (File $file) {
        $file->addMediaConversion('slider')
            ->width(1920)
            ->height(1080)
            ->nonQueued();
    });

    $file = File::create(['name' => 'Slider Source']);
    $file->registerMediaConversions();

    $names = collect($file->mediaConversions)->map(fn ($c) => $c->getName())->toArray();

    expect($names)->toContain('slider', 'thumb', 'preview');

    File::registerMediaConversionsUsing(null);
});

it('persists a manual crop for a conversion and regenerates it', function () {
    $file = File::create(['name' => 'Crop Source']);
    $file->addMedia(UploadedFile::fake()->image('crop-source.jpg', 2000, 1500))
        ->toMediaCollection('default');

    Livewire::test(MediaBrowser::class)
        ->call('saveManualCrop', $file->id, 'thumb', [
            'x' => 100,
            'y' => 50,
            'width' => 900,
            'height' => 900,
        ])
        ->assertNotified();

    $media = $file->fresh()->getFirstMedia('default');

    expect($media->manipulations)->toHaveKey('thumb');
    expect($media->manipulations['thumb']['manualCrop'])->toBe([900, 900, 100, 50]);
});

it('resolves width, height and aspect ratio for fit()-based conversions', function () {
    File::registerMediaConversionsUsing(function (File $file) {
        $file->addMediaConversion('slider')
            ->fit(Fit::Crop, 1920, 1080)
            ->nonQueued();
    });

    $file = new File;
    $file->registerMediaConversions();

    $conversion = collect($file->mediaConversions)->firstWhere(fn ($c) => $c->getName() === 'slider');

    $method = new ReflectionMethod(MediaBrowser::class, 'resolveConversionDimensions');
    $method->setAccessible(true);

    [$width, $height] = $method->invoke(new MediaBrowser, $conversion);

    expect([$width, $height])->toBe([1920, 1080]);

    File::registerMediaConversionsUsing(null);
});

it('removes a manual crop and regenerates the conversion automatically', function () {
    $file = File::create(['name' => 'Removable Crop']);
    $file->addMedia(UploadedFile::fake()->image('removable-crop.jpg', 2000, 1500))
        ->toMediaCollection('default');

    Livewire::test(MediaBrowser::class)
        ->call('saveManualCrop', $file->id, 'thumb', [
            'x' => 10,
            'y' => 10,
            'width' => 200,
            'height' => 200,
        ]);

    expect($file->fresh()->getFirstMedia('default')->manipulations)->toHaveKey('thumb');

    Livewire::test(MediaBrowser::class)
        ->call('removeManualCrop', $file->id, 'thumb')
        ->assertNotified();

    expect($file->fresh()->getFirstMedia('default')->manipulations)->not->toHaveKey('thumb');
});

it('does nothing when the file has no media attached', function () {
    $file = File::create(['name' => 'No Media']);

    Livewire::test(MediaBrowser::class)
        ->call('saveManualCrop', $file->id, 'thumb', [
            'x' => 0,
            'y' => 0,
            'width' => 100,
            'height' => 100,
        ]);

    expect($file->fresh()->getFirstMedia('default'))->toBeNull();
});

it('only shows conversions with a manual crop set in the sidebar summary', function () {
    $file = File::create(['name' => 'Sidebar Source', 'mime_type' => 'image/jpeg']);
    $file->addMedia(UploadedFile::fake()->image('sidebar-source.jpg', 2000, 1500))
        ->toMediaCollection('default');

    $component = Livewire::test(MediaBrowser::class)
        ->call('selectFile', $file->id);

    $component->assertSee(__('media-manager::media-manager.messages.no_manual_crops'));

    $component->call('saveManualCrop', $file->id, 'preview', [
        'x' => 0,
        'y' => 0,
        'width' => 800,
        'height' => 800,
    ]);

    $component->assertDontSee(__('media-manager::media-manager.messages.no_manual_crops'));
});
