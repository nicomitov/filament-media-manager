<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Slimani\MediaManager\MediaManagerPlugin;
use Slimani\MediaManager\Models\File;
use Slimani\MediaManager\Models\Folder;
use Slimani\MediaManager\Models\Tag;
use Slimani\MediaManager\Tests\Components\TestMediaPickerRelationshipForm;
use Slimani\MediaManager\Tests\Models\User;
use Slimani\MediaManager\Tests\TestCase;

uses(TestCase::class);

function useMediaTenant(string|int|null &$tenantKey): MediaManagerPlugin
{
    $plugin = MediaManagerPlugin::make()
        ->tenantAware(tenantResolver: function () use (&$tenantKey) {
            return $tenantKey;
        });

    $panel = Panel::make('tenant-test-'.str()->random())
        ->id('tenant-test-'.str()->random())
        ->plugin($plugin);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    return $plugin;
}

it('automatically assigns and scopes media records to the current tenant', function () {
    $tenantKey = 'tenant-one';
    useMediaTenant($tenantKey);

    $folderOne = Folder::create(['name' => 'Tenant One']);
    $fileOne = File::create(['name' => 'Tenant One File', 'folder_id' => $folderOne->id]);
    $tagOne = Tag::create(['name' => 'Shared', 'slug' => 'shared']);

    expect($folderOne->tenant_id)->toBe('tenant-one')
        ->and($fileOne->tenant_id)->toBe('tenant-one')
        ->and($tagOne->tenant_id)->toBe('tenant-one');

    $tenantKey = 'tenant-two';

    $folderTwo = Folder::create(['name' => 'Tenant Two']);
    $fileTwo = File::create(['name' => 'Tenant Two File', 'folder_id' => $folderTwo->id]);
    $tagTwo = Tag::create(['name' => 'Shared', 'slug' => 'shared']);

    expect(Folder::pluck('id')->all())->toBe([$folderTwo->id])
        ->and(File::pluck('id')->all())->toBe([$fileTwo->id])
        ->and(Tag::pluck('id')->all())->toBe([$tagTwo->id])
        ->and(Folder::find($folderOne->id))->toBeNull()
        ->and(File::find($fileOne->id))->toBeNull()
        ->and(Tag::find($tagOne->id))->toBeNull();
});

it('fails closed when tenancy is enabled without an active tenant', function () {
    $tenantKey = 'tenant-one';
    useMediaTenant($tenantKey);

    File::create(['name' => 'Tenant File']);

    $tenantKey = null;

    expect(File::query()->count())->toBe(0);

    File::create(['name' => 'Unowned File']);
})->throws(LogicException::class, 'A tenant must be active');

it('scopes recursive folder queries that bypass eloquent global scopes', function () {
    $tenantKey = 'tenant-one';
    useMediaTenant($tenantKey);

    $root = Folder::create(['name' => 'Root']);
    $child = Folder::create(['name' => 'Child', 'parent_id' => $root->id]);
    File::create(['name' => 'Owned', 'folder_id' => $child->id, 'size' => 100]);

    $tenantKey = 'tenant-two';

    DB::table('media_folders')->insert([
        'name' => 'Foreign Child',
        'parent_id' => $root->id,
        'tenant_id' => 'tenant-two',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('media_files')->insert([
        'name' => 'Foreign',
        'folder_id' => $child->id,
        'tenant_id' => 'tenant-two',
        'size' => 900,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $tenantKey = 'tenant-one';
    $root = Folder::findOrFail($root->id);

    expect($root->getAllDescendantIds())->toBe([$child->id])
        ->and($root->getRecursiveStats())->toBe([
            'files_count' => 1,
            'folders_count' => 1,
            'total_size' => 100,
        ]);
});

it('rejects cross-tenant folder relationships', function () {
    $tenantKey = 'tenant-one';
    useMediaTenant($tenantKey);

    $foreignFolder = Folder::create(['name' => 'Tenant One']);

    $tenantKey = 'tenant-two';

    File::create([
        'name' => 'Invalid File',
        'folder_id' => $foreignFolder->id,
    ]);
})->throws(LogicException::class, 'different tenant');

it('rejects a forged media picker file id from another tenant', function () {
    $tenantKey = 'tenant-one';
    useMediaTenant($tenantKey);

    $foreignFile = File::create(['name' => 'Tenant One File']);
    $user = User::create(['name' => 'Test User']);

    $tenantKey = 'tenant-two';

    Livewire::actingAs($user)
        ->test(TestMediaPickerRelationshipForm::class, ['user' => $user])
        ->fillForm(['avatar_id' => $foreignFile->id])
        ->call('submit')
        ->assertHasFormErrors(['avatar_id']);

    expect($user->fresh()->avatar_id)->toBeNull();
});
