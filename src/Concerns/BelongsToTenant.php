<?php

namespace Slimani\MediaManager\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Slimani\MediaManager\MediaManagerPlugin;
use Throwable;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('media-manager-tenant', function (Builder $query): void {
            $plugin = static::resolveMediaManagerPlugin();

            if (! $plugin?->isTenantAware()) {
                return;
            }

            $tenantKey = $plugin->getTenantKey();

            if ($tenantKey === null) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->where(
                $query->qualifyColumn($plugin->getTenantColumn()),
                $tenantKey,
            );
        });

        static::creating(function (Model $model): void {
            $plugin = static::resolveMediaManagerPlugin();

            if (! $plugin?->isTenantAware()) {
                return;
            }

            $tenantKey = $plugin->getTenantKey();

            if ($tenantKey === null) {
                throw new LogicException('A tenant must be active before creating media records.');
            }

            $tenantColumn = $plugin->getTenantColumn();
            $modelTenantKey = $model->getAttribute($tenantColumn);

            if ($modelTenantKey !== null && (string) $modelTenantKey !== (string) $tenantKey) {
                throw new LogicException('A media record cannot be created for a different tenant.');
            }

            $model->setAttribute($tenantColumn, $tenantKey);
        });

        static::updating(function (Model $model): void {
            $plugin = static::resolveMediaManagerPlugin();

            if (! $plugin?->isTenantAware()) {
                return;
            }

            $tenantKey = $plugin->getTenantKey();

            if ($tenantKey === null) {
                throw new LogicException('A tenant must be active before updating media records.');
            }

            $tenantColumn = $plugin->getTenantColumn();

            if ((string) $model->getAttribute($tenantColumn) !== (string) $tenantKey) {
                throw new LogicException('A media record cannot be moved to a different tenant.');
            }
        });
    }

    protected static function resolveMediaManagerPlugin(): ?MediaManagerPlugin
    {
        try {
            $plugin = filament('media-manager');

            return $plugin instanceof MediaManagerPlugin ? $plugin : null;
        } catch (Throwable) {
            return null;
        }
    }
}
