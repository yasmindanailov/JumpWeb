<?php

namespace App\Filament\Resources\Catalog\Pages;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Catalog\Concerns\InteractsWithCatalogForm;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

/**
 * Fase 7.6 (iter. 2) — Alta de un producto nuevo del catálogo.
 *
 * Cierra el CRUD del catálogo (listar + editar + borrar ya existían; faltaba crear).
 * Desbloqueado por #189 (la edición de precios por tarifa ya existe): un producto nuevo
 * puede fijar sus precios en el alta con los mismos campos `price_rate_{id}`.
 *
 * Reutiliza el formulario (`CatalogForm`) y la lógica compartida (`InteractsWithCatalogForm`)
 * que ya usa la edición → las dos superficies tratan los datos igual.
 *
 * Concentra lo ESPECÍFICO de la creación:
 *  - El `type` SÍ se persiste (lo elige el operador; en edición es de solo lectura).
 *  - **Normalización por tipo**: limpia los campos que no aplican al tipo elegido
 *    (defensa de integridad, sin depender de qué campos dehidrata Filament al ocultar
 *    una sección): un complemento no lleva zona/aforo/pack; una entrada no lleva pack.
 *  - **Orden**: el producto nuevo se coloca al final del catálogo (`position`).
 *  - **Precios + auditoría** tras crear; aviso si queda vendible sin precio.
 *  - **Redirección a la ficha de edición** del nuevo producto: ahí se enganchan los
 *    complementos (el RelationManager necesita un registro ya persistido) y se revisa.
 */
class CreateCatalog extends CreateRecord
{
    use InteractsWithCatalogForm;

    protected static string $resource = CatalogResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.catalog.create_title');
    }

    /**
     * Transforma y normaliza los datos antes de crear.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // El tipo lo manda el servidor (el Select lo limita, pero no se confía en el cliente).
        $type = in_array($data['type'] ?? null, [
            TicketType::TYPE_ENTRY, TicketType::TYPE_PACK, TicketType::TYPE_ADDON,
        ], true) ? $data['type'] : TicketType::TYPE_ENTRY;
        $data['type'] = $type;

        // Transformaciones comunes (ventajas/i18n/event_fields/integridad pack + extracción
        // de precios a $this->priceInputs). Compartidas con la edición vía el trait.
        $data = $this->applyCommonFormTransforms($data);

        // Normalización por tipo: deja fuera los campos que no aplican (no se confía en que
        // Filament no dehidrate los campos de una sección oculta).
        $data = $this->normalizeByType($type, $data);

        // El producto nuevo se coloca al final del catálogo (el orden se reordena arrastrando).
        // `lockForUpdate` serializa altas concurrentes (estamos dentro de la transacción de
        // Filament) para que dos no lean el mismo máximo y empaten la posición.
        $data['position'] = (int) (TicketType::lockForUpdate()->max('position') ?? 0) + 1;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var TicketType $record */
        $record = $this->record;

        AuditLogger::log('catalog.created', $record, [
            'type' => $record->type,
            'name_es' => (string) ($record->tr('name', 'es') ?? ''),
        ]);

        // Precios: upsert por tarifa (lógica compartida con la edición).
        $priceChanges = $this->upsertPrices($record);
        if ($priceChanges !== []) {
            AuditLogger::log('catalog.prices_updated', $record, ['changed' => $priceChanges]);
            // Un producto nuevo con precio podría rebajar el "desde X €" del CTA de la landing.
            Cache::forget('cta.min_price_cents');
        }

        $this->warnIfSellableWithoutPrice($record);

        // Estado de borrador explícito: si el producto nace NO vendible, avisamos de que aún
        // no se vende (nace visible pero no comprable) para que el operador no crea que ya
        // está publicado. El caso vendible-sin-precio ya lo cubre el aviso anterior.
        if (! $record->is_sellable) {
            Notification::make()
                ->title(__('admin.catalog.created_draft_hint'))
                ->info()
                ->send();
        }
    }

    /** Tras crear, ir a la ficha de edición (para enganchar complementos y revisar). */
    protected function getRedirectUrl(): string
    {
        return CatalogResource::getUrl('edit', ['record' => $this->record]);
    }

    /**
     * Elimina del payload los campos que no corresponden al tipo elegido, para que la fila
     * nueva quede coherente independientemente de qué dehidrate Filament:
     *  - **addon**: sin zona, sin aforo/horario, sin bloque de pack ni señal.
     *  - **entry**: sin bloque de pack ni señal.
     *  - **pack**: conserva todo.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function normalizeByType(string $type, array $data): array
    {
        // Campos exclusivos del pack: nunca en entradas ni complementos.
        if ($type !== TicketType::TYPE_PACK) {
            foreach (['min_qty', 'max_qty', 'event_fields', 'guest_fields', 'prep_before_min', 'prep_after_min'] as $key) {
                unset($data[$key]);
            }
            // Sin señal fuera de los packs (la columna tiene default 'none'/0 en BD).
            $data['deposit_type'] = TicketType::DEPOSIT_NONE;
            $data['deposit_value'] = 0;
        }

        // Un complemento no consume aforo ni tiene zona/horario — SALVO el que OCUPA (la hora
        // extra, `specs/hora-extra.md` §4.9): ese conserva `duration_min`, que es CUÁNTO ocupa y la
        // columna que el aforo lee. Hasta `#410` este `unset` incondicional tapiaba la puerta de
        // entrada del dato: el diseño era coherente y aun así no se podía encender. La zona sigue
        // nula a propósito también para él (la hija hereda la de la franja que ocupa).
        if ($type === TicketType::TYPE_ADDON) {
            $data['zone_id'] = null;
            // ⚠️ **Los DOS interruptores conservan `duration_min`**, que es CUÁNTO ocupa o CUÁNTO
            // alarga — y para el extensor (§10.3.1) el olvido sería el mismo defecto que `#410`
            // arregló para el ocupante: el diseño coherente y la puerta de entrada tapiada.
            $keepsDuration = (bool) ($data['occupies_after_parent'] ?? false)
                || (bool) ($data['extends_parent_stay'] ?? false);
            foreach (['duration_min', 'available_after_open_min', 'available_before_close_min'] as $key) {
                if ($key === 'duration_min' && $keepsDuration) {
                    continue;
                }
                unset($data[$key]);
            }
        } else {
            // Solo un COMPLEMENTO puede ocupar detrás de un padre o alargar su estancia (regla 12;
            // los guards del modelo lo rechazarían con excepción — aquí se normaliza antes).
            $data['occupies_after_parent'] = false;
            $data['extends_parent_stay'] = false;
        }

        // Un pack es «1 niño = 1 plaza» (auditoría Fase 1 · L2): `seats_per_unit` SIEMPRE 1. Con >1
        // el checkout valida el cupo en UNIDADES pero lo consume en PLAZAS (qty×spu) → sobreventa del
        // cupo de niños. El campo está oculto para packs en el form; aquí se fuerza server-side (regla 12).
        if ($type === TicketType::TYPE_PACK) {
            $data['seats_per_unit'] = 1;
        }

        // Familia y tramo de edad (`specs/cumple-mixto.md` §9): normaliza, anula fuera del pack y
        // bloquea el guardado si el tramo pisa al de un hermano. Misma llamada en `EditCatalog`.
        $data = $this->normalizeCancellationCutoff($data, $type);
        // Lo que Mi cuenta dice de lo reservado (`#775`), solo donde tiene sentido. Misma llamada en `EditCatalog`.
        $data = $this->normalizeReservationWording($data, $type);

        return $this->normalizeGuestAgeFields($data, $type);
    }
}
