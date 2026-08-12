<?php

namespace App\Filament\Resources\RateTypes\Pages;

use App\Filament\Resources\RateTypes\RateTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRateTypes extends ListRecords
{
    protected static string $resource = RateTypeResource::class;

    /**
     * "Crear tarifa". Filament solo la muestra si `RateTypeResource::canCreate()` es true
     * (admin con `prices.manage`).
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.rate_types.actions.create')),
        ];
    }
}
