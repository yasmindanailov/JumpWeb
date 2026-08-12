<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Filament\Resources\Pages\PageResource;
use Filament\Resources\Pages\ListRecords;

class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    // Sin acción de crear: las páginas legales están atadas a rutas fijas (edit-only).
    protected function getHeaderActions(): array
    {
        return [];
    }
}
