<?php

namespace App\Filament\Resources\SpecialDates\Pages;

use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\SpecialDates\Concerns\InteractsWithSpecialDateForm;
use App\Filament\Resources\SpecialDates\SpecialDateResource;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fase 7.7 — Edición de una fecha especial.
 *
 * Normalización común vía el trait + **auditoría** `prices.special_date_updated` con el
 * diff de lo que cambió. **Borrado** siempre disponible (nada referencia a una fecha
 * especial): al borrarla, el día vuelve a regirse por el horario semanal.
 */
class EditSpecialDate extends EditRecord
{
    use InteractsWithSpecialDateForm;

    protected static string $resource = SpecialDateResource::class;

    /** Campos escalares cuyo cambio se audita con valor anterior/nuevo. */
    private const SCALAR_FIELDS = ['date', 'is_closed', 'open_time', 'close_time', 'rate_type_id'];

    /** @var array<string,mixed> Diff capturado en `mutateFormDataBeforeSave` para auditar en `afterSave`. */
    private array $auditPayload = [];

    public function getTitle(): string|Htmlable
    {
        /** @var SpecialDate $record */
        $record = $this->record;

        return __('admin.special_dates.edit_title', ['date' => (string) $record->date?->toDateString()]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteSpecialDateAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var SpecialDate $record */
        $record = $this->record;

        $data = $this->normalizeSpecialDateData($data);
        $this->auditPayload = $this->buildAuditDiff($record, $data);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var SpecialDate $record */
        $record = $this->record;

        if ($this->auditPayload !== []) {
            AuditLogger::log('prices.special_date_updated', $record, $this->auditPayload);
        }
    }

    private function deleteSpecialDateAction(): Action
    {
        return Action::make('deleteSpecialDate')
            ->label(__('admin.special_dates.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('prices.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.special_dates.actions.delete.modal_heading'))
            ->modalDescription(__('admin.special_dates.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.special_dates.actions.delete.submit'))
            ->action(function (SpecialDate $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return; // borrado concurrente: nada que hacer
                }

                // Auditar ANTES de borrar (el target aún existe).
                AuditLogger::log('prices.special_date_deleted', $record, [
                    'date' => $record->date?->toDateString(),
                    'is_closed' => (bool) $record->is_closed,
                ]);

                $record->delete();

                Notification::make()
                    ->title(__('admin.special_dates.actions.delete.success'))
                    ->success()
                    ->send();

                $this->redirect(SpecialDateResource::getUrl('index'));
            });
    }

    /**
     * Diff compacto para auditoría: valor anterior/nuevo de los campos escalares y si la
     * nota i18n cambió (sin volcar su contenido).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function buildAuditDiff(SpecialDate $record, array $data): array
    {
        $changed = [];
        foreach (self::SCALAR_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $old = $this->normalizeScalar($field, $record->getAttribute($field));
            $new = $this->normalizeScalar($field, $data[$field]);
            if ($old !== $new) {
                $changed[$field] = ['from' => $old, 'to' => $new];
            }
        }

        $textsChanged = [];
        if (array_key_exists('note', $data) && $record->getAttribute('note') != $data['note']) {
            $textsChanged[] = 'note';
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

    private function normalizeScalar(string $field, mixed $value): bool|int|string|null
    {
        if ($field === 'is_closed') {
            return (bool) $value;
        }
        if ($field === 'rate_type_id') {
            return $value === null || $value === '' ? null : (int) $value;
        }
        if ($value === null || $value === '') {
            return null;
        }
        // Horas → 'H:i' SIEMPRE: la columna `time` se lee como 'HH:MM:SS' pero el TimePicker
        // (`seconds(false)`) entrega 'HH:MM' → sin esta normalización, una edición que NO toca
        // la hora registraría un cambio FALSO ('10:00:00' → '10:00') y ensuciaría la auditoría.
        if ($field === 'open_time' || $field === 'close_time') {
            return $value instanceof CarbonInterface ? $value->format('H:i') : substr((string) $value, 0, 5);
        }
        // date → 'Y-m-d'.
        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) $value;
    }
}
