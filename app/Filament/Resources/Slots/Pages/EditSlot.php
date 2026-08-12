<?php

namespace App\Filament\Resources\Slots\Pages;

use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Slots\Concerns\InteractsWithSlotForm;
use App\Filament\Resources\Slots\SlotResource;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditSlot extends EditRecord
{
    use InteractsWithSlotForm;

    protected static string $resource = SlotResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Slot $record */
        $record = $this->record;

        return __('admin.slots.edit_title', [
            'zone' => (string) ($record->zone?->tr('name') ?? ''),
            'date' => $record->date->format('d/m/Y'),
            'time' => substr((string) $record->start_time, 0, 5),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->resetCapacityAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareSlotData($data);
    }

    protected function afterSave(): void
    {
        /** @var Slot $record */
        $record = $this->record;

        AuditLogger::log('slots.slot_updated', $record, [
            'zone_id' => $record->zone_id,
            'date' => $record->date->toDateString(),
            'start_time' => substr((string) $record->start_time, 0, 5),
            'online_sales_open' => (bool) $record->online_sales_open,
            'online_capacity' => (int) $record->online_capacity,
            'capacity_overridden' => (bool) $record->capacity_overridden,
        ]);
    }

    /**
     * Devuelve una franja ajustada a mano al aforo de su plantilla (si existe) y quita la marca
     * de "ajustado", para que vuelva a gestionarla la regeneración. Solo visible si está ajustada
     * y la zona no es de cumpleaños.
     */
    private function resetCapacityAction(): Action
    {
        return Action::make('resetCapacity')
            ->label(__('admin.slots.actions.reset.label'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (): bool => (bool) ($this->record?->capacity_overridden)
                && ! SlotResource::isPackZone($this->record)
                && (auth()->user()?->hasPermission('slots.manage') ?? false))
            ->requiresConfirmation()
            ->modalHeading(__('admin.slots.actions.reset.modal_heading'))
            ->modalDescription(__('admin.slots.actions.reset.modal_description'))
            ->modalSubmitActionLabel(__('admin.slots.actions.reset.submit'))
            ->action(function (): void {
                // Mutar el MISMO objeto que usa el formulario ($this->record): si trabajáramos
                // sobre un ->fresh() aparte, fillForm() volvería a leer el record viejo (valor
                // stale) y un guardado posterior re-fijaría el override deshaciendo el reset.
                /** @var Slot $record */
                $record = $this->record;

                $template = SlotTemplate::where('zone_id', $record->zone_id)
                    ->where('weekday', Carbon::parse($record->date->toDateString())->dayOfWeek)
                    ->where('start_time', $record->start_time)
                    ->where('is_active', true)
                    ->first();

                // No restablecer por debajo de lo ya vendido (misma invariante que el editor).
                if ($template && $template->online_capacity < SlotResource::liveOccupancy($record)) {
                    Notification::make()
                        ->title(__('admin.slots.actions.reset.blocked_below_occupancy', [
                            'template' => $template->online_capacity,
                            'occupancy' => SlotResource::liveOccupancy($record),
                        ]))
                        ->danger()
                        ->send();

                    return;
                }

                if ($template) {
                    $record->capacity = $template->capacity;
                    $record->online_capacity = $template->online_capacity;
                }
                $record->capacity_overridden = false;
                $record->save();

                AuditLogger::log('slots.capacity_override_cleared', $record, [
                    'zone_id' => $record->zone_id,
                    'date' => $record->date->toDateString(),
                    'start_time' => substr((string) $record->start_time, 0, 5),
                    'online_capacity' => (int) $record->online_capacity,
                    'reset_from_template' => $template !== null,
                ]);

                Notification::make()->title(__('admin.slots.actions.reset.success'))->success()->send();

                $this->fillForm();
            });
    }
}
