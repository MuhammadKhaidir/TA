<?php

namespace App\Filament\Concerns;

use Filament\Navigation\NavigationItem;

use function Filament\Support\original_request;

/**
 * Satu resource dapat tampil sebagai beberapa menu di grup berbeda
 * (misalnya Persyaratan TA dan Persyaratan KP), masing-masing membuka
 * daftar yang sudah terfilter lewat parameter tableFilters.
 */
trait PunyaMenuTerfilter
{
    protected static function itemTerfilter(string $label, string $grup, string $filter, string $nilai, int $urutan): NavigationItem
    {
        return NavigationItem::make($label)
            ->key(static::class.':'.$filter.':'.$nilai)
            ->group($grup)
            ->icon(static::getNavigationIcon())
            ->sort($urutan)
            ->url(static::getUrl('index', ['filters' => [$filter => ['value' => $nilai]]]))
            ->isActiveWhen(fn (): bool => original_request()->routeIs(static::getRouteBaseName().'.*')
                && data_get(original_request()->query('filters'), "{$filter}.value") === $nilai);
    }
}
