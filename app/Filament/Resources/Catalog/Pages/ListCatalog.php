<?php

namespace App\Filament\Resources\Catalog\Pages;

use App\Filament\Resources\Catalog\CatalogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCatalog extends ListRecords
{
    protected static string $resource = CatalogResource::class;

    /**
     * Acción "Crear producto" (7.6 iter. 2, #192). Filament solo la muestra si
     * `CatalogResource::canCreate()` es true (admin con `catalog.manage`).
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.catalog.actions.create')),
        ];
    }
}
