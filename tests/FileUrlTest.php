<?php

namespace Slimani\MediaManager\Tests;

use RuntimeException;
use Slimani\MediaManager\Models\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

uses(TestCase::class);

function makeTestFileWithMedia(?Media $defaultMedia, ?Media $fallbackMedia = null): File
{
    $file = new class extends File
    {
        public ?Media $defaultMedia = null;

        public ?Media $fallbackMedia = null;

        public function getFirstMedia(string $collectionName = 'default', $filters = []): ?Media
        {
            return $collectionName === 'default' ? $this->defaultMedia : $this->fallbackMedia;
        }
    };

    $file->defaultMedia = $defaultMedia;
    $file->fallbackMedia = $fallbackMedia;

    return $file;
}

function makeFakeMedia(string $disk, ?string $conversionsDisk = null): Media
{
    $media = new class extends Media
    {
        public array $requestedUrls = [];

        public array $requestedTemporaryUrls = [];

        public ?string $temporaryUrl = null;

        public ?\Throwable $temporaryUrlException = null;

        public function getUrl(string $conversionName = ''): string
        {
            $this->requestedUrls[] = $conversionName;

            return "https://cdn.test/{$this->disk}/".($conversionName !== '' ? "{$conversionName}.jpg" : 'original.jpg');
        }

        public function getTemporaryUrl(?\DateTimeInterface $expiration = null, string $conversionName = '', array $options = []): string
        {
            $this->requestedTemporaryUrls[] = $conversionName;

            if ($this->temporaryUrlException) {
                throw $this->temporaryUrlException;
            }

            return $this->temporaryUrl ?? "https://signed.test/{$this->disk}/".($conversionName !== '' ? "{$conversionName}.jpg" : 'original.jpg');
        }
    };

    $media->disk = $disk;
    $media->conversions_disk = $conversionsDisk;

    return $media;
}

it('returns a clean url for originals on a public disk', function () {
    config()->set('filesystems.disks.public.visibility', 'public');

    $media = makeFakeMedia('public');

    $file = makeTestFileWithMedia($media);

    expect($file->getUrl())->toBe('https://cdn.test/public/original.jpg')
        ->and($media->requestedUrls)->toBe([''])
        ->and($media->requestedTemporaryUrls)->toBe([]);
});

it('returns a temporary url for originals on a private disk', function () {
    config()->set('filesystems.disks.s3.visibility', 'private');

    $media = makeFakeMedia('s3');

    $file = makeTestFileWithMedia($media);

    expect($file->getUrl())->toBe('https://signed.test/s3/original.jpg')
        ->and($media->requestedTemporaryUrls)->toBe([''])
        ->and($media->requestedUrls)->toBe([]);
});

it('falls back to the standard url when temporary urls are unsupported', function () {
    config()->set('filesystems.disks.s3.visibility', 'private');

    $media = makeFakeMedia('s3');
    $media->temporaryUrlException = new RuntimeException('Temporary URLs are not supported.');

    $file = makeTestFileWithMedia($media);

    expect($file->getUrl())->toBe('https://cdn.test/s3/original.jpg')
        ->and($media->requestedTemporaryUrls)->toBe([''])
        ->and($media->requestedUrls)->toBe(['']);
});

it('uses the conversions disk visibility for named conversions', function () {
    config()->set('filesystems.disks.s3.visibility', 'private');
    config()->set('filesystems.disks.public.visibility', 'public');

    $media = makeFakeMedia('s3', 'public');

    $file = makeTestFileWithMedia($media);

    expect($file->getUrl('thumb'))->toBe('https://cdn.test/s3/thumb.jpg')
        ->and($media->requestedUrls)->toBe(['thumb'])
        ->and($media->requestedTemporaryUrls)->toBe([]);
});

it('falls back to the original disk when no conversions disk is set', function () {
    config()->set('filesystems.disks.public.visibility', 'public');

    $media = makeFakeMedia('public');

    $file = makeTestFileWithMedia($media);

    expect($file->getUrl('preview'))->toBe('https://cdn.test/public/preview.jpg')
        ->and($media->requestedUrls)->toBe(['preview'])
        ->and($media->requestedTemporaryUrls)->toBe([]);
});

it('returns null when the file has no media', function () {
    $file = makeTestFileWithMedia(null);

    expect($file->getUrl())->toBeNull();
});
