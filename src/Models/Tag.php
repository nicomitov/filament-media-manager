<?php

namespace Slimani\MediaManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;
use Slimani\MediaManager\Concerns\BelongsToTenant;
use Slimani\MediaManager\MediaManagerPlugin;

class Tag extends Model
{
    use BelongsToTenant;

    protected $table = 'media_tags';

    protected $fillable = [
        'name',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function files(): MorphToMany
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->morphedByMany($plugin->getFileModel(), 'taggable', 'media_taggables');
    }

    public function folders(): MorphToMany
    {
        /** @var MediaManagerPlugin $plugin */
        $plugin = filament('media-manager');

        return $this->morphedByMany($plugin->getFolderModel(), 'taggable', 'media_taggables');
    }
}
