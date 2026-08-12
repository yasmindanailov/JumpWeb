<?php

namespace App\Filament\Resources\RateTypes\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Fase 7.8 — Lógica de formulario COMPARTIDA por crear (`CreateRateType`) y editar
 * (`EditRateType`) una tarifa. Fuente única para que ambas superficies traten los datos
 * igual (mismo patrón que `InteractsWithCatalogForm` del catálogo).
 *
 * Cubre las transformaciones independientes de la operación:
 *  - **Etiqueta i18n** `{es,en,fr}`: descarta idiomas vacíos.
 *  - **`weekdays`**: la lista del `CheckboxList` llega como strings (`['0','6']`); se
 *    normaliza a **enteros únicos y ordenados en 0..6**. Es CRÍTICO: `RateResolver`
 *    compara con `in_array($weekday, $rate->weekdays, true)` (estricto), y `$weekday`
 *    es un `int` de Carbon → un `'6'` (string) NUNCA casaría. Vacío → `null`.
 *  - **`priority`**: entero ≥ 0 (defensa sobre el `minValue` del form).
 *
 * El manejo de `key` es específico de cada página (se persiste al crear; es inmutable al
 * editar), por eso vive en cada una, no aquí.
 */
trait InteractsWithRateTypeForm
{
    /**
     * Deja `$data` listo para persistir (etiqueta, días y prioridad normalizados).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function normalizeRateTypeData(array $data): array
    {
        if (array_key_exists('label', $data)) {
            $data['label'] = $this->compactLabel($data['label']);
        }

        if (array_key_exists('weekdays', $data)) {
            $data['weekdays'] = $this->sanitizeWeekdays($data['weekdays']);
        }

        if (array_key_exists('priority', $data)) {
            $data['priority'] = max(0, (int) ($data['priority'] ?? 0));
        }

        if (array_key_exists('is_special', $data)) {
            $data['is_special'] = (bool) $data['is_special'];
        }

        if (array_key_exists('is_active', $data)) {
            $data['is_active'] = (bool) $data['is_active'];
        }

        return $data;
    }

    /**
     * Compacta la etiqueta i18n `{es,en,fr}`: descarta idiomas vacíos. La columna es
     * NOT NULL (`json`) y el `es` es obligatorio en el form, así que nunca queda vacía;
     * aun así, defensa: si llegara vacía del todo, se conserva como `{}`.
     *
     * @return array<string,string>
     */
    protected function compactLabel(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $clean = [];
        foreach ($value as $locale => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $clean[$locale] = $text;
            }
        }

        return $clean;
    }

    /**
     * Días de la semana → enteros únicos y ordenados en 0..6 (0=domingo, Carbon dayOfWeek).
     * Vacío → null (la tarifa no se activa por día de la semana; solo es fallback o vía
     * fechas especiales).
     *
     * @return array<int,int>|null
     */
    protected function sanitizeWeekdays(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $days = [];
        foreach ($value as $day) {
            $day = (int) $day;
            if ($day >= 0 && $day <= 6) {
                $days[$day] = $day; // clave = valor → dedup
            }
        }

        if ($days === []) {
            return null;
        }

        ksort($days);

        return array_values($days);
    }

    /**
     * Invalida la caché del CTA "desde X €" de la landing (única lectura de precios
     * cacheada, 15 min). Cambiar los `weekdays`/`priority`/`is_active` de una tarifa puede
     * alterar el precio mínimo resuelto, así que se invalida en crear/editar/borrar.
     */
    protected function forgetPriceCache(): void
    {
        Cache::forget('cta.min_price_cents');
    }
}
