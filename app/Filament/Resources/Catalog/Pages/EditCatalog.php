<?php

namespace App\Filament\Resources\Catalog\Pages;

use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Catalog\Concerns\InteractsWithCatalogForm;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Fase 7.6 — Edición de un producto del catálogo.
 *
 * Concentra toda la lógica que el formulario (`CatalogForm`) no expresa de forma
 * declarativa:
 *  - **i18n con listas**: las "ventajas" (`features`) se editan como texto (una por
 *    línea) y se guardan como `{es:[…],en:[…],fr:[…]}`.
 *  - **Limpieza i18n**: los textos traducibles vacíos no se persisten como `''`.
 *  - **Editor de `event_fields`**: saneo + claves únicas (bloquea el guardado si hay
 *    duplicados).
 *  - **Guardas defense-in-depth**: `type` nunca se reescribe; la `zona` de un producto
 *    con ventas no se puede cambiar.
 *  - **Auditoría**: `catalog.updated` con el diff de lo que cambió; aviso si se deja
 *    el producto vendible sin precio.
 *  - **Borrado seguro**: solo si el producto NUNCA se ha vendido (limpia precios y
 *    pivote); con auditoría de éxito y de rechazo.
 */
class EditCatalog extends EditRecord
{
    use InteractsWithCatalogForm;

    protected static string $resource = CatalogResource::class;

    /** Campos escalares cuyo cambio se audita con valor anterior/nuevo. */
    private const SCALAR_FIELDS = [
        'zone_id', 'duration_min', 'seats_per_unit',
        'available_after_open_min', 'available_before_close_min',
        'min_qty', 'max_qty', 'deposit_type', 'deposit_value',
        'prep_before_min', 'prep_after_min',
        // La familia y el tramo de edad deciden quién paga un suplemento (`specs/cumple-mixto.md`
        // §9): moverlos cambia el veredicto de fiestas ya vendidas, así que se auditan como el
        // resto de la configuración con consecuencias económicas.
        'guest_age_family', 'guest_age_min', 'guest_age_max',
        'featured', 'is_active', 'is_sellable',
    ];

    private const BOOL_FIELDS = ['featured', 'is_active', 'is_sellable'];

    private const STRING_FIELDS = ['deposit_type', 'guest_age_family'];

    /** Campos i18n/JSON cuyo cambio se audita solo por nombre (no se vuelca el contenido). */
    private const TEXT_FIELDS = [
        'name', 'description', 'period_label', 'badge', 'features', 'event_fields', 'guest_fields',
    ];

    /** @var array<string,mixed> Diff capturado en `mutateFormDataBeforeSave` para auditar en `afterSave`. */
    private array $auditPayload = [];

    public function getTitle(): string|Htmlable
    {
        /** @var TicketType $record */
        $record = $this->record;

        return __('admin.catalog.edit_title', ['name' => (string) ($record->tr('name') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteProductAction(),
        ];
    }

    /**
     * Rellena los campos virtuales de "ventajas" (texto, una por línea) a partir de la
     * lista i18n almacenada.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $features = is_array($data['features'] ?? null) ? $data['features'] : [];

        foreach (['es', 'en', 'fr'] as $locale) {
            $list = is_array($features[$locale] ?? null) ? $features[$locale] : [];
            $data["features_{$locale}"] = implode("\n", array_map('strval', $list));
        }

        // Precios: un campo €/tarifa activa, leído de la matriz `prices` (céntimos → euros).
        foreach (RateType::where('is_active', true)->get() as $rate) {
            $cents = $this->record->prices->firstWhere('rate_type_id', $rate->id)?->amount_cents;
            $data["price_rate_{$rate->id}"] = $cents === null ? null : number_format($cents / 100, 2, '.', '');
        }

        return $data;
    }

    /**
     * Transforma y valida los datos antes de guardar (ver cabecera de la clase).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var TicketType $record */
        $record = $this->record;

        // 1) Guardas estructurales específicas de EDICIÓN (defensa sobre lo que ya hace el form).
        unset($data['type']);  // `type` nunca se reescribe (cambiar de tipo corrompe aforo/pedidos).

        if (CatalogResource::hasSales($record) && array_key_exists('zone_id', $data)
            && (int) $data['zone_id'] !== (int) $record->zone_id) {
            // El form desactiva la zona en productos vendidos; si llega un cambio (tampering),
            // lo revertimos y lo dejamos registrado.
            AuditLogger::log('catalog.update_blocked', $record, [
                'reason' => 'zone_change_forbidden_sold',
                'attempted_zone_id' => (int) $data['zone_id'],
            ]);
            $data['zone_id'] = $record->zone_id;
        }

        // 2) Transformaciones comunes (ventajas/i18n/event_fields/integridad pack + extracción
        // de precios a $this->priceInputs). Compartidas con la creación vía el trait.
        $data = $this->applyCommonFormTransforms($data);

        // 2.bis) Defensa en profundidad simétrica con `CreateCatalog::normalizeByType`: los esquemas
        // exclusivos de pack (datos del evento / datos por niño) NUNCA se persisten en un producto
        // que no es pack. La sección está oculta para no-packs y Filament no dehidrata lo oculto,
        // pero no se confía en ese comportamiento (regla 12) — un payload manipulado no cuela.
        if (! $record->isPack()) {
            unset($data['event_fields'], $data['guest_fields']);
        }

        // Un pack es «1 niño = 1 plaza» (auditoría Fase 1 · L2): `seats_per_unit` SIEMPRE 1 (con >1 el
        // checkout valida el cupo en UNIDADES y lo consume en PLAZAS → sobreventa). El campo está oculto
        // para packs; se fuerza server-side por si llega un payload manipulado (regla 12).
        if ($record->isPack()) {
            $data['seats_per_unit'] = 1;
        }

        // Familia y tramo de edad (`specs/cumple-mixto.md` §9): la MISMA puerta que en la creación
        // —normalizar, anular fuera del pack y bloquear tramos solapados—, no una copia.
        $data = $this->normalizeGuestAgeFields($data, $record->isPack());

        // 3) Capturar el diff para auditar tras guardar.
        $this->auditPayload = $this->buildAuditDiff($record, $data);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var TicketType $record */
        $record = $this->record;

        if ($this->auditPayload !== []) {
            AuditLogger::log('catalog.updated', $record, $this->auditPayload);
        }

        // Precios: upsert por tarifa (céntimos = única fuente; lógica compartida con la creación).
        $priceChanges = $this->upsertPrices($record);
        if ($priceChanges !== []) {
            AuditLogger::log('catalog.prices_updated', $record, ['changed' => $priceChanges]);
        }

        // Invalida la caché del CTA "desde X €" de la landing (única lectura de precios cacheada, 15 min;
        // el resto consulta BD en vivo) SIEMPRE que se edita un producto: no solo al cambiar el PRECIO,
        // también al marcar no vendible / desactivar la entrada más barata, que mueve el mínimo del CTA
        // (auditoría Fase 1 · Sistema 6 · W3 — antes la invalidación vivía dentro de `if ($priceChanges)`).
        Cache::forget('cta.min_price_cents');

        $this->warnIfSellableWithoutPrice($record);
    }

    private function deleteProductAction(): Action
    {
        return Action::make('deleteProduct')
            ->label(__('admin.catalog.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            // Visible solo con permiso Y si el producto NUNCA se ha vendido (si tiene ventas
            // se desactiva, no se borra). El handler re-verifica con datos frescos.
            ->visible(fn (TicketType $record): bool => (auth()->user()?->hasPermission('catalog.manage') ?? false)
                && ! CatalogResource::hasSales($record))
            ->requiresConfirmation()
            ->modalHeading(__('admin.catalog.actions.delete.modal_heading'))
            ->modalDescription(__('admin.catalog.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.catalog.actions.delete.submit'))
            ->action(function (TicketType $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;  // borrado concurrente: nada que hacer
                }

                if (CatalogResource::hasSales($record)) {
                    AuditLogger::log('catalog.delete_blocked', $record, ['reason' => 'has_sales']);
                    Notification::make()
                        ->title(__('admin.catalog.actions.delete.blocked'))
                        ->danger()
                        ->send();

                    return;
                }

                DB::transaction(function () use ($record): void {
                    // Auditar ANTES de borrar (el target aún existe).
                    AuditLogger::log('catalog.deleted', $record, [
                        'type' => $record->type,
                        'name_es' => (string) ($record->tr('name', 'es') ?? ''),
                    ]);

                    // `prices` es polimórfica (sin FK BD) → limpiar a mano. El pivote
                    // `product_addons` cascada por FK, lo borramos explícito por claridad.
                    $record->prices()->delete();
                    DB::table('product_addons')
                        ->where('product_id', $record->id)
                        ->orWhere('addon_id', $record->id)
                        ->delete();

                    $record->delete();

                    // Borrar la entrada más barata mueve el mínimo del CTA «desde X €» de la landing
                    // (auditoría Fase 1 · Sistema 6 · W3): invalidar la caché para no anunciar un
                    // precio de un producto que ya no existe hasta que expire el TTL (15 min).
                    Cache::forget('cta.min_price_cents');
                });

                Notification::make()
                    ->title(__('admin.catalog.actions.delete.success'))
                    ->success()
                    ->send();

                $this->redirect(CatalogResource::getUrl('index'));
            });
    }

    // ─── Helpers específicos de edición (diff de auditoría) ─────────────────────

    /**
     * Diff compacto para auditoría: valor anterior/nuevo de los campos escalares y la
     * lista de campos i18n/JSON que cambiaron (sin volcar su contenido).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function buildAuditDiff(TicketType $record, array $data): array
    {
        $changed = [];
        foreach (self::SCALAR_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $old = $record->getAttribute($field);
            $new = $data[$field];

            if ($this->scalarChanged($field, $old, $new)) {
                $changed[$field] = ['from' => $this->normalizeScalar($field, $old), 'to' => $this->normalizeScalar($field, $new)];
            }
        }

        $textsChanged = [];
        foreach (self::TEXT_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            // Comparación laxa de arrays/null (el orden de claves no importa para == en mapas).
            if ($record->getAttribute($field) != $data[$field]) {
                $textsChanged[] = $field;
            }
        }

        $payload = [];
        if ($changed !== []) {
            $payload['changed'] = $changed;
        }
        if ($textsChanged !== []) {
            $payload['texts_changed'] = $textsChanged;
        }

        return $payload;
    }

    private function scalarChanged(string $field, mixed $old, mixed $new): bool
    {
        return $this->normalizeScalar($field, $old) !== $this->normalizeScalar($field, $new);
    }

    private function normalizeScalar(string $field, mixed $value): bool|float|string|null
    {
        if (in_array($field, self::BOOL_FIELDS, true)) {
            return (bool) $value;
        }
        if (in_array($field, self::STRING_FIELDS, true)) {
            return $value === null ? null : trim((string) $value);
        }
        // Numérico (incluida zona): null se conserva como null para distinguir "sin valor".
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
