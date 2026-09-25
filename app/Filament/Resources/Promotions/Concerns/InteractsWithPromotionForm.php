<?php

namespace App\Filament\Resources\Promotions\Concerns;

use App\Domain\Booking\Models\Promotion;

/**
 * Lo que crear y editar una promoción comparten al guardar:
 *  - El OBJETIVO (`target`), que no es columna: deja la clave que aplica y pone a `null` la otra.
 *  - El texto i18n sin idiomas vacíos (un idioma vacío es «sin traducir», no `''`).
 *  - Los valores por defecto de las columnas NOT NULL: el `Toggle` de Filament dehidrata `false`.
 */
trait InteractsWithPromotionForm
{
    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function preparePromotionData(array $data): array
    {
        $objetivo = $data['target'] ?? Promotion::TARGET_INSTALLATION;
        unset($data['target']);

        $data['zone_id'] = $objetivo === Promotion::TARGET_ZONE ? (int) ($data['zone_id'] ?? 0) ?: null : null;
        $data['ticket_type_id'] = $objetivo === Promotion::TARGET_PRODUCT ? (int) ($data['ticket_type_id'] ?? 0) ?: null : null;
        $data['text'] = $this->compactTranslations($data['text'] ?? []);
        $data['starts_on'] = ($data['starts_on'] ?? null) ?: null;
        $data['ends_on'] = ($data['ends_on'] ?? null) ?: null;
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['position'] = (int) ($data['position'] ?? 0);

        return $data;
    }

    /**
     * Lo que se audita de una promoción: qué dice (en español), su clase, a qué va y sus fechas.
     *
     * @return array<string,mixed>
     */
    protected function promotionAuditPayload(Promotion $promocion): array
    {
        return [
            'text' => $promocion->textIn('es'),
            'kind' => $promocion->kind,
            'target' => $promocion->target(),
            'zone_id' => $promocion->zone_id,
            'ticket_type_id' => $promocion->ticket_type_id,
            'starts_on' => $promocion->starts_on?->toDateString(),
            'ends_on' => $promocion->ends_on?->toDateString(),
            'is_active' => (bool) $promocion->is_active,
        ];
    }

    /**
     * @return array<string,string>
     */
    private function compactTranslations(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        $clean = [];
        foreach ($values as $locale => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $clean[$locale] = $text;
            }
        }

        return $clean;
    }
}
