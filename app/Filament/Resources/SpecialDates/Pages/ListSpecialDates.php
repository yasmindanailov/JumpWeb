<?php

namespace App\Filament\Resources\SpecialDates\Pages;

use App\Filament\Resources\SpecialDates\SpecialDateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSpecialDates extends ListRecords
{
    protected static string $resource = SpecialDateResource::class;

    /** "Añadir fecha". Filament solo la muestra si `canCreate()` (admin con `prices.manage`). */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.special_dates.actions.create')),
        ];
    }
}
