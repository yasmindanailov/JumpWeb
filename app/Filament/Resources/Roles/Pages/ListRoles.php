<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ListRecords;

class ListRoles extends ListRecords
{
    protected static string $resource = RoleResource::class;

    /** Sin "Crear": los roles base son constantes de código (ver `RoleResource`). */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
