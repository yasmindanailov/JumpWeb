<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Resources\Pages\ListRecords;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * Sin acción "Crear" en el listado: los pedidos no se crean desde aquí. El pedido manual
     * (Fase 7.3) tiene su propia página, accesible desde el botón "Crear pedido" del topbar.
     */
    protected function getHeaderActions(): array
    {
        return [];
    }
}
