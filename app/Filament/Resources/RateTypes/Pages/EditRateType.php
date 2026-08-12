<?php

namespace App\Filament\Resources\RateTypes\Pages;

use App\Filament\Resources\RateTypes\Concerns\InteractsWithRateTypeForm;
use App\Filament\Resources\RateTypes\RateTypeResource;
use App\Models\RateType;
use App\Support\AuditLogger;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fase 7.8 — Edición de una tarifa.
 *
 * Concentra lo que el formulario no expresa de forma declarativa:
 *  - **`key` inmutable**: nunca se reescribe (cambiar la `key` de `normal` rompería el
 *    RateResolver y la web). El form la pone de solo lectura; aquí se elimina del payload
 *    como defensa en profundidad ante un tampering.
 *  - Normalización común (etiqueta i18n, `weekdays` a enteros, prioridad) vía el trait.
 *  - **Auditoría** `prices.rate_updated` con el diff de lo que cambió + invalidación de la
 *    caché de precios del CTA.
 *  - **Borrado seguro** (acción de cabecera): bloqueado si es la base `normal`, si tiene
 *    precios (FK `cascadeOnDelete`) o si la referencian fechas especiales; con auditoría
 *    de éxito y de rechazo.
 */
class EditRateType extends EditRecord
{
    use InteractsWithRateTypeForm;

    protected static string $resource = RateTypeResource::class;

    /** @var array<string,mixed> Diff capturado en `mutateFormDataBeforeSave` para auditar en `afterSave`. */
    private array $auditPayload = [];

    /** Campos cuyo cambio puede alterar el precio resuelto → obligan a invalidar la caché del CTA. */
    private const PRICE_AFFECTING_FIELDS = ['weekdays', 'priority', 'is_active'];

    /** ¿Ha cambiado algún campo que afecte al precio? (para invalidar la caché solo cuando toca). */
    private bool $priceAffectingChange = false;

    /** ¿Se está desactivando la tarifa base en este guardado? (para el aviso de afterSave). */
    private bool $deactivatingFallback = false;

    public function getTitle(): string|Htmlable
    {
        /** @var RateType $record */
        $record = $this->record;

        return __('admin.rate_types.edit_title', ['name' => (string) ($record->tr('label') ?? $record->key)]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteRateTypeAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var RateType $record */
        $record = $this->record;

        // `key` nunca se reescribe (defensa sobre el campo de solo lectura del form).
        unset($data['key']);

        $data = $this->normalizeRateTypeData($data);

        // Aviso si se desactiva la tarifa base: la resolución sigue funcionando (RateResolver
        // la encuentra por `key` aunque esté inactiva), pero es una operación inusual.
        $this->deactivatingFallback = $record->isFallback()
            && (bool) $record->is_active === true
            && array_key_exists('is_active', $data)
            && (bool) $data['is_active'] === false;

        $this->auditPayload = $this->buildAuditDiff($record, $data);

        // La caché del CTA solo se invalida si cambió un campo que afecta al precio resuelto
        // (días/prioridad/estado). Cambiar la etiqueta i18n o la marca informativa `is_special`
        // SÍ se audita, pero NO toca el precio → no malgasta la caché.
        $changedKeys = array_keys($this->auditPayload['changed'] ?? []);
        $this->priceAffectingChange = array_intersect(self::PRICE_AFFECTING_FIELDS, $changedKeys) !== [];

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var RateType $record */
        $record = $this->record;

        if ($this->auditPayload !== []) {
            AuditLogger::log('prices.rate_updated', $record, $this->auditPayload);
        }

        if ($this->priceAffectingChange) {
            $this->forgetPriceCache();
        }

        if ($this->deactivatingFallback) {
            Notification::make()
                ->title(__('admin.rate_types.warn_deactivated_fallback'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    private function deleteRateTypeAction(): Action
    {
        return Action::make('deleteRateType')
            ->label(__('admin.rate_types.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            // Visible solo con permiso Y si la tarifa se puede borrar sin riesgo. El handler
            // re-verifica con datos frescos (defensa en profundidad).
            ->visible(fn (RateType $record): bool => (auth()->user()?->hasPermission('prices.manage') ?? false)
                && $record->canBeDeleted())
            ->requiresConfirmation()
            ->modalHeading(__('admin.rate_types.actions.delete.modal_heading'))
            ->modalDescription(__('admin.rate_types.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.rate_types.actions.delete.submit'))
            ->action(function (RateType $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return; // borrado concurrente: nada que hacer
                }

                $reason = $record->deleteBlockedReason();
                if ($reason !== null) {
                    AuditLogger::log('prices.rate_delete_blocked', $record, ['reason' => $reason]);
                    Notification::make()
                        ->title(__('admin.rate_types.actions.delete.blocked.'.$reason))
                        ->danger()
                        ->send();

                    return;
                }

                // Auditar ANTES de borrar (el target aún existe).
                AuditLogger::log('prices.rate_deleted', $record, [
                    'key' => $record->key,
                    'label_es' => (string) ($record->tr('label', 'es') ?? ''),
                ]);

                $record->delete();
                $this->forgetPriceCache();

                Notification::make()
                    ->title(__('admin.rate_types.actions.delete.success'))
                    ->success()
                    ->send();

                $this->redirect(RateTypeResource::getUrl('index'));
            });
    }

    /**
     * Diff compacto para auditoría: valor anterior/nuevo de prioridad/flags y de `weekdays`;
     * la etiqueta i18n se audita solo por nombre (no se vuelca su contenido).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function buildAuditDiff(RateType $record, array $data): array
    {
        $changed = [];

        // Prioridad (entero).
        if (array_key_exists('priority', $data) && (int) $record->priority !== (int) $data['priority']) {
            $changed['priority'] = ['from' => (int) $record->priority, 'to' => (int) $data['priority']];
        }

        // Flags booleanos.
        foreach (['is_special', 'is_active'] as $flag) {
            if (array_key_exists($flag, $data) && (bool) $record->getAttribute($flag) !== (bool) $data[$flag]) {
                $changed[$flag] = ['from' => (bool) $record->getAttribute($flag), 'to' => (bool) $data[$flag]];
            }
        }

        // weekdays (lista de enteros; comparación normalizada por valor).
        if (array_key_exists('weekdays', $data)) {
            $old = $this->normalizedDays($record->weekdays);
            $new = $this->normalizedDays($data['weekdays']);
            if ($old !== $new) {
                $changed['weekdays'] = ['from' => $old, 'to' => $new];
            }
        }

        // Etiqueta i18n: solo se registra que cambió, no el contenido.
        $textsChanged = [];
        if (array_key_exists('label', $data) && $record->getAttribute('label') != $data['label']) {
            $textsChanged[] = 'label';
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

    /**
     * Normaliza una lista de días a enteros únicos y ordenados (para comparar el diff sin
     * falsos positivos por tipo string/int u orden).
     *
     * @return array<int,int>
     */
    private function normalizedDays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $days = array_values(array_unique(array_map('intval', $value)));
        sort($days);

        return $days;
    }
}
