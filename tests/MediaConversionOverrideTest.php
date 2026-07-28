<?php

namespace Slimani\MediaManager\Tests;

use Filament\Facades\Filament;
use Filament\Panel;
use Slimani\MediaManager\MediaManagerPlugin;
use Slimani\MediaManager\Models\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(TestCase::class);

it('merges custom conversions with defaults and overrides by name', function () {
    File::registerMediaConversionsUsing(function (File $file, ?Media $media = null) {
        // Override 'thumb'
        $file->addMediaConversion('thumb')
            ->width(100)
            ->height(100)
            ->nonQueued();

        // Add a new one
        $file->addMediaConversion('extra')
            ->width(500)
            ->height(500)
            ->nonQueued();
    });

    $file = File::create(['name' => 'Merged File']);
    $file->registerMediaConversions();

    $conversions = $file->mediaConversions;

    // We expect 3 conversions: thumb (overridden), preview (default), extra (added)
    $names = collect($conversions)->map(fn ($c) => $c->getName())->toArray();
    // dd($names);
    expect($names)->toContain('thumb', 'preview', 'extra');

    // Reset for other tests
    File::registerMediaConversionsUsing(null);
});

it('uses exactly defaults when no callback is set', function () {
    File::registerMediaConversionsUsing(null);

    $file = File::create(['name' => 'Default File']);
    $file->registerMediaConversions();

    $conversions = $file->mediaConversions;

    expect($conversions)->toHaveCount(2);
    expect($conversions[0]->getName())->toBe('thumb');
    expect($conversions[1]->getName())->toBe('preview');
});

it('registers video thumbnails when enabled in plugin', function () {
    $plugin = MediaManagerPlugin::make()
        ->withVideoThumbnails(true);

    $panel = Panel::make('video_test')
        ->id('video_test')
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    $file = new File;
    $file->registerMediaConversions();

    $conversions = $file->mediaConversions;

    $thumb = collect($conversions)->first(fn ($c) => $c->getName() === 'thumb');
    $preview = collect($conversions)->first(fn ($c) => $c->getName() === 'preview');

    expect($thumb->getExtractVideoFrameAtSecond())->toEqual(1);
    expect($preview->getExtractVideoFrameAtSecond())->toEqual(1);
});

it('does not register video thumbnails by default', function () {
    $plugin = MediaManagerPlugin::make(); // Default is false

    $panel = Panel::make('default_test')
        ->id('default_test')
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    $file = new File;
    $file->registerMediaConversions();

    $conversions = $file->mediaConversions;

    $thumb = collect($conversions)->first(fn ($c) => $c->getName() === 'thumb');
    $preview = collect($conversions)->first(fn ($c) => $c->getName() === 'preview');

    expect($thumb->getExtractVideoFrameAtSecond())->toEqual(0);
    expect($preview->getExtractVideoFrameAtSecond())->toEqual(0);
});
