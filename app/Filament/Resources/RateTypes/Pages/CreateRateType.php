<?php

namespace App\Filament\Resources\RateTypes\Pages;

use App\Filament\Resources\RateTypes\Concerns\InteractsWithRateTypeForm;
use App\Filament\Resources\RateTypes\RateTypeResource;
use App\Models\RateType;
use App\Support\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fase 7.8 — Alta de una tarifa nueva.
 *
 * Lo específico de la creación:
 *  - La `key` SÍ se persiste (la elige el operador; en edición es de solo lectura). Se
 *    normaliza a minúsculas/sin espacios (la regex del form ya acota el formato).
 *  - **Defaults explícitos** de las columnas NOT NULL: en una Create page de Filament un
 *    Toggle sin tocar se dehidrata `false` y un campo vacío sobreescribe el default de BD,
 *    así que se fijan aquí además de en el form (defensa en profundidad).
 *  - Normalización común (etiqueta i18n, `weekdays` a enteros, prioridad) vía el trait.
 *  - Auditoría `prices.rate_created` + invalidación de la caché de precios del CTA.
 */
class CreateRateType extends CreateRecord
{
    use InteractsWithRateTypeForm;

    protected static string $resource = RateTypeResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.rate_types.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // `key`: normaliza a minúsculas y recorta (la regex del form la acota; esto es
        // defensa de integridad ante cualquier entrada tolerada).
        $data['key'] = strtolower(trim((string) ($data['key'] ?? '')));

        $data = $this->normalizeRateTypeData($data);

        // Defaults explícitos de columnas NOT NULL (ver cabecera): no se confía en lo que
        // dehidrate Filament para un campo sin tocar.
        $data['is_special'] = (bool) ($data['is_special'] ?? false);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['priority'] = max(0, (int) ($data['priority'] ?? 0));

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var RateType $record */
        $record = $this->record;

        AuditLogger::log('prices.rate_created', $record, [
            'key' => $record->key,
            'label_es' => (string) ($record->tr('label', 'es') ?? ''),
            'weekdays' => $record->weekdays ?? [],
            'priority' => (int) $record->priority,
            'is_active' => (bool) $record->is_active,
        ]);

        // Una tarifa nueva (días/prioridad) puede cambiar el precio resuelto y, con él, el
        // "desde X €" de la landing.
        $this->forgetPriceCache();
    }

    /** Tras crear, ir a la ficha de edición de la tarifa (para fijar precios desde el catálogo se va aparte). */
    protected function getRedirectUrl(): string
    {
        return RateTypeResource::getUrl('edit', ['record' => $this->record]);
    }
}
