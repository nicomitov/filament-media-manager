<?php

namespace Slimani\MediaManager\Form\RichEditor\Nodes;

use Filament\Forms\Components\RichEditor\TipTapExtensions\ImageExtension as FilamentImageExtension;
use Slimani\MediaManager\MediaManagerPlugin;

class MediaImageNode extends FilamentImageExtension
{
    public static $name = 'image';

    public static $priority = 110;

    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),
            'id' => [
                'parseHTML' => fn ($DOMNode) => $DOMNode->getAttribute('data-id') ?? $DOMNode->getAttribute('id') ?: null,
                'renderHTML' => fn ($attributes) => [
                    'id' => $attributes->id ?? null,
                    'data-id' => $attributes->id ?? null,
                ],
            ],
        ];
    }

    public function renderHTML($node, $HTMLAttributes = []): array
    {
        if (filled($node->attrs->id ?? null)) {
            /** @var MediaManagerPlugin $plugin */
            $plugin = filament('media-manager');
            $fileModel = $plugin->getFileModel();
            $file = $fileModel::find($node->attrs->id);

            if ($file) {
                $HTMLAttributes['src'] = $file->getUrl();
            }
        }

        return parent::renderHTML($node, $HTMLAttributes);
    }
}
