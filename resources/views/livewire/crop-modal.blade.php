@once
    <link rel="stylesheet" href="{{ \Filament\Support\Facades\FilamentAsset::getStyleHref('media-manager-cropper-styles', 'slimani/media-manager') }}" />
@endonce

<div
    x-data="{
        cropper: null,
        conversions: @js($conversions),
        activeConversion: @js($conversions->first()['name'] ?? null),
        cropperSrc: @js(\Filament\Support\Facades\FilamentAsset::getScriptSrc('media-manager-cropper', 'slimani/media-manager')),
        loadCropperScript() {
            return new Promise((resolve, reject) => {
                if (window.Cropper) {
                    resolve()

                    return
                }

                let script = document.querySelector('script[data-media-manager-cropper]')

                if (script) {
                    script.addEventListener('load', () => resolve())
                    script.addEventListener('error', reject)

                    return
                }

                script = document.createElement('script')
                script.src = this.cropperSrc
                script.dataset.mediaManagerCropper = true
                script.addEventListener('load', () => resolve())
                script.addEventListener('error', reject)
                document.head.appendChild(script)
            })
        },
        activeConversionData() {
            return this.conversions.find((conversion) => conversion.name === this.activeConversion)
        },
        applyConversionData() {
            const conversion = this.activeConversionData()

            this.cropper.setAspectRatio(conversion?.aspectRatio ?? NaN)

            if (conversion?.manualCrop) {
                const [width, height, x, y] = conversion.manualCrop
                this.cropper.setData({ x, y, width, height })
            }
        },
        async initCropper() {
            await this.loadCropperScript()

            this.cropper = new Cropper(this.$refs.cropImage, {
                aspectRatio: this.activeConversionData()?.aspectRatio ?? NaN,
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
                ready: () => this.applyConversionData(),
            })
        },
        switchConversion() {
            this.applyConversionData()
        },
        save() {
            const data = this.cropper.getData()
            const conversion = this.activeConversionData()

            $wire.saveManualCrop(@js($fileId), this.activeConversion, {
                x: data.x,
                y: data.y,
                width: data.width,
                height: data.height,
            })

            if (conversion) {
                conversion.manualCrop = [
                    Math.round(data.width),
                    Math.round(data.height),
                    Math.round(data.x),
                    Math.round(data.y),
                ]
            }
        },
        remove() {
            const conversion = this.activeConversionData()

            $wire.removeManualCrop(@js($fileId), this.activeConversion)

            if (conversion) {
                conversion.manualCrop = null
            }

            this.cropper.setAspectRatio(conversion?.aspectRatio ?? NaN)
            this.cropper.reset()
        },
    }"
    x-init="$nextTick(() => initCropper())"
    x-on:closed.window="cropper?.destroy()"
>
    <div class="mb-4">
        <label class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">
            {{ __('media-manager::media-manager.fields.conversion') }}
        </label>
        <select
            x-model="activeConversion"
            x-on:change="switchConversion()"
            class="fi-select-input mt-1 block w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        >
            <template x-for="conversion in conversions" :key="conversion.name">
                <option
                    :value="conversion.name"
                    x-text="conversion.name + (conversion.width && conversion.height ? ` (${conversion.width}×${conversion.height})` : '') + (conversion.manualCrop ? ' ✓' : '')"
                ></option>
            </template>
        </select>
    </div>

    <div class="max-h-[60vh] overflow-hidden" wire:ignore>
        <img x-ref="cropImage" src="{{ $imageUrl }}" class="max-w-full" alt="" />
    </div>

    <div class="mt-4 flex justify-end gap-2">
        <x-filament::button
            color="danger"
            x-show="activeConversionData()?.manualCrop"
            x-on:click="remove()"
        >
            {{ __('media-manager::media-manager.actions.remove_crop') }}
        </x-filament::button>

        <x-filament::button x-on:click="save()">
            {{ __('media-manager::media-manager.actions.save_crop') }}
        </x-filament::button>
    </div>
</div>
