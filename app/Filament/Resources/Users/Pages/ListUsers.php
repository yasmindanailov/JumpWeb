<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    /**
     * Sin acción "Crear": los clientes se dan de alta por invitación firmada
     * (Fase 7.3), no desde aquí (`UserResource::canCreate()` = false).
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
