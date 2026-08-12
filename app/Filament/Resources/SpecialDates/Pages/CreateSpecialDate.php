<?php

namespace App\Filament\Resources\SpecialDates\Pages;

use App\Filament\Resources\SpecialDates\Concerns\InteractsWithSpecialDateForm;
use App\Filament\Resources\SpecialDates\SpecialDateResource;
use App\Models\SpecialDate;
use App\Support\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fase 7.7 — Alta de una fecha especial.
 *
 * Lo específico de la creación: el default explícito de `is_closed` (el Toggle de Filament
 * se dehidrata `false` sin tocar, pero se fija aquí por robustez), la normalización común
 * (nota i18n + limpiar ventana/tarifa de un día cerrado + validar cierre>apertura) vía el
 * trait, y la auditoría. Tras crear, vuelve al listado.
 */
class CreateSpecialDate extends CreateRecord
{
    use InteractsWithSpecialDateForm;

    protected static string $resource = SpecialDateResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.special_dates.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->normalizeSpecialDateData($data);
    }

    protected function afterCreate(): void
    {
        /** @var SpecialDate $record */
        $record = $this->record;

        AuditLogger::log('prices.special_date_created', $record, [
            'date' => $record->date?->toDateString(),
            'is_closed' => (bool) $record->is_closed,
            'rate_type_id' => $record->rate_type_id,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return SpecialDateResource::getUrl('index');
    }
}
