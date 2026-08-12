<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * Listado de «Incidencias» (recomendación C). Sin acción de crear: `audit_logs` es append-only,
 * solo lo escribe `AuditLogger`.
 */
class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
