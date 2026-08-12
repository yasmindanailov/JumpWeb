<?php

namespace App\Filament\Resources\Catalog\Concerns;

use App\Models\RateType;
use App\Models\TicketType;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/**
 * Fase 7.6 — Lógica de formulario COMPARTIDA por crear (`CreateCatalog`) y editar
 * (`EditCatalog`) un producto del catálogo.
 *
 * Fuente única para que ambas superficies traten los datos igual (mismo patrón que
 * `AddonResolver` con el precio de complementos): así no divergen las dos páginas.
 *
 * Cubre lo NO específico de una u otra operación:
 *  - **i18n con listas**: las "ventajas" (`features`) se editan como texto (una por
 *    línea) y se guardan como `{es:[…],en:[…],fr:[…]}`.
 *  - **Limpieza i18n**: los textos traducibles vacíos no se persisten como `''`.
 *  - **Editor de `event_fields`**: saneo + claves únicas (bloquea el guardado si hay
 *    duplicados).
 *  - **Integridad del pack**: el máximo de invitados nunca por debajo del mínimo.
 *  - **Precios**: los campos `price_rate_{id}` (€) no son columnas del modelo → se
 *    extraen del form y se hace el upsert en la matriz `prices` (céntimos = única
 *    fuente de verdad). Vacío = sin precio para esa tarifa.
 *
 * Lo ESPECÍFICO de cada página (guardas de `type`/zona y diff de auditoría en edición;
 * normalización por tipo y orden al final en creación) vive en cada página.
 */
trait InteractsWithCatalogForm
{
    /** @var array<int,?string> Importes (€) por rate_type_id capturados del form para el upsert. */
    protected array $priceInputs = [];

    /**
     * Transformaciones comunes a crear y editar. Deja `$data` listo para persistir y
     * llena `$this->priceInputs` con los importes a aplicar tras guardar. Lanza `Halt`
     * si hay claves de evento duplicadas o el máximo de invitados es menor que el mínimo.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function applyCommonFormTransforms(array $data): array
    {
        // 1) Ventajas: texto (una por línea) → lista i18n; eliminar los campos virtuales.
        $features = [];
        foreach (['es', 'en', 'fr'] as $locale) {
            $features[$locale] = $this->linesToList($data["features_{$locale}"] ?? null);
            unset($data["features_{$locale}"]);
        }
        $data['features'] = array_filter($features, fn (array $list): bool => $list !== []);
        if ($data['features'] === []) {
            $data['features'] = null;
        }

        // 2) Limpieza de textos i18n simples: descartar idiomas vacíos; null si quedan todos vacíos.
        foreach (['name', 'description', 'period_label', 'badge'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->compactI18n($data[$field]);
            }
        }

        // 3) Esquema de datos del evento: saneo + claves únicas (bloquea si hay duplicados).
        if (array_key_exists('event_fields', $data)) {
            $data['event_fields'] = $this->sanitizeEventFields(is_array($data['event_fields']) ? $data['event_fields'] : []);
        }

        // 3.bis) Esquema de datos POR NIÑO del post-form (#217): mismo saneo + claves únicas.
        if (array_key_exists('guest_fields', $data)) {
            $data['guest_fields'] = $this->sanitizeGuestFields(is_array($data['guest_fields']) ? $data['guest_fields'] : []);
        }

        // 4) Integridad del pack (defensa en profundidad sobre el `->gte()` del form):
        // el máximo de invitados nunca por debajo del mínimo.
        if (array_key_exists('min_qty', $data) && array_key_exists('max_qty', $data)
            && $data['max_qty'] !== null && $data['min_qty'] !== null
            && (int) $data['max_qty'] < (int) $data['min_qty']) {
            Notification::make()
                ->title(__('admin.catalog.max_qty_below_min'))
                ->danger()
                ->send();

            throw new Halt;
        }

        // 4.bis) Columnas NOT NULL que el form podría enviar vacías (null al crear, o si se
        // vacían en edición) y romperían el insert/update. Las normalizamos a su default de
        // negocio: 0 en las de minutos/valor ("sin restricción"/"sin señal") y 'none' en el
        // tipo de señal. Defensa en profundidad sobre los ->default() del propio form.
        foreach (['available_after_open_min', 'available_before_close_min', 'prep_before_min', 'prep_after_min', 'deposit_value'] as $key) {
            if (array_key_exists($key, $data) && ($data[$key] === null || $data[$key] === '')) {
                $data[$key] = 0;
            }
        }
        if (array_key_exists('deposit_type', $data) && ($data['deposit_type'] === null || $data['deposit_type'] === '')) {
            $data['deposit_type'] = TicketType::DEPOSIT_NONE;
        }

        // 5) Precios: extraer los campos €/tarifa (no son columnas del modelo) para el upsert.
        // Vacío = sin precio para esa tarifa.
        $this->priceInputs = [];
        foreach (array_keys($data) as $key) {
            if (str_starts_with($key, 'price_rate_')) {
                $this->priceInputs[(int) substr($key, strlen('price_rate_'))] = $data[$key];
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Upsert de precios por tarifa a partir de `$this->priceInputs` (céntimos = única
     * fuente). Vacío → borra la fila (sin precio esa tarifa). No afecta a pedidos pasados
     * (cada `order_item` guarda su `unit_price`). Devuelve el diff por tarifa para auditar.
     *
     * @return array<int,array{from:?int,to:?int}>
     */
    protected function upsertPrices(TicketType $record): array
    {
        $priceChanges = [];

        foreach ($this->priceInputs as $rateId => $euros) {
            $existing = $record->prices()->where('rate_type_id', $rateId)->first();

            if ($euros === null || trim((string) $euros) === '') {
                if ($existing !== null) {
                    $priceChanges[$rateId] = ['from' => (int) $existing->amount_cents, 'to' => null];
                    $existing->delete();
                }

                continue;
            }

            // Normaliza coma decimal ('12,50') y nunca negativo (defensa sobre el minValue del form).
            $cents = max(0, (int) round((float) str_replace(',', '.', trim((string) $euros)) * 100));
            if ($existing === null || (int) $existing->amount_cents !== $cents) {
                $priceChanges[$rateId] = ['from' => $existing?->amount_cents !== null ? (int) $existing->amount_cents : null, 'to' => $cents];
                $record->prices()->updateOrCreate(['rate_type_id' => $rateId], ['amount_cents' => $cents, 'currency' => 'EUR']);
            }
        }

        return $priceChanges;
    }

    /**
     * Aviso operativo: producto vendible pero sin precio en la tarifa base → no será
     * vendible hasta fijarlo. No bloquea el guardado.
     */
    protected function warnIfSellableWithoutPrice(TicketType $record): void
    {
        if ($record->is_sellable && ! $this->hasBaseRatePrice($record)) {
            Notification::make()
                ->title(__('admin.catalog.warn_sellable_no_price'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /**
     * Texto multilínea → lista de strings (recorta, descarta vacíos).
     *
     * @return array<int,string>
     */
    protected function linesToList(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $text) ?: []),
            fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * Compacta un valor i18n `{es,en,fr}`: descarta idiomas vacíos; null si todos lo están.
     *
     * @return array<string,string>|null
     */
    protected function compactI18n(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $clean = [];
        foreach ($value as $locale => $text) {
            $text = trim((string) $text);
            if ($text !== '') {
                $clean[$locale] = $text;
            }
        }

        return $clean === [] ? null : $clean;
    }

    /**
     * Sanea el esquema `event_fields` (datos básicos del evento): descarta filas sin clave,
     * normaliza el tipo, compacta las etiquetas i18n y exige claves ÚNICAS (bloquea el guardado).
     *
     * @param  array<int,mixed>  $rows
     * @return array<int,array<string,mixed>>
     */
    protected function sanitizeEventFields(array $rows): array
    {
        return $this->sanitizeFieldSchema($rows, 'admin.catalog.event_field_duplicate', true);
    }

    /**
     * Sanea el esquema `guest_fields` (datos por niño del post-form, #217): mismo saneo y misma
     * regla de claves únicas que `event_fields` (esquema gemelo en otra columna).
     *
     * @param  array<int,mixed>  $rows
     * @return array<int,array<string,mixed>>
     */
    protected function sanitizeGuestFields(array $rows): array
    {
        return $this->sanitizeFieldSchema($rows, 'admin.catalog.guest_field_duplicate');
    }

    /**
     * Saneo COMÚN de un esquema data-driven ({key,type,required,label}) — `event_fields` y
     * `guest_fields` son gemelos. Descarta filas sin clave, normaliza el tipo, compacta las
     * etiquetas i18n y exige claves ÚNICAS (bloquea el guardado con un aviso si hay duplicados).
     *
     * @param  array<int,mixed>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function sanitizeFieldSchema(array $rows, string $duplicateMessageKey, bool $withStage = false): array
    {
        $clean = [];
        $seen = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            if (isset($seen[$key])) {
                Notification::make()
                    ->title(__($duplicateMessageKey, ['key' => $key]))
                    ->danger()
                    ->send();

                throw new Halt;
            }
            $seen[$key] = true;

            $type = $row['type'] ?? 'text';
            $entry = [
                'key' => $key,
                'type' => in_array($type, ['text', 'number', 'textarea'], true) ? $type : 'text',
                'required' => (bool) ($row['required'] ?? false),
                'label' => $this->compactI18n($row['label'] ?? null) ?? ['es' => $key],
            ];

            // La fase (booking/postform) solo aplica a `event_fields` (#217); `guest_fields` no la usa.
            if ($withStage) {
                $stage = $row['stage'] ?? TicketType::EVENT_STAGE_BOOKING;
                $entry['stage'] = in_array($stage, [TicketType::EVENT_STAGE_BOOKING, TicketType::EVENT_STAGE_POSTFORM], true)
                    ? $stage
                    : TicketType::EVENT_STAGE_BOOKING;
            }

            $clean[] = $entry;
        }

        return $clean;
    }

    /** ¿El producto tiene precio en la tarifa base (`normal`)? Sin él no es vendible. */
    protected function hasBaseRatePrice(TicketType $record): bool
    {
        return $record->fresh()
            ->load('prices.rateType')
            ->prices
            ->contains(fn ($price): bool => optional($price->rateType)->key === RateType::KEY_NORMAL
                && $price->amount_cents !== null);
    }
}
