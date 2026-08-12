<?php

namespace App\Filament\Resources\Zones\Pages;

use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Zones\Concerns\InteractsWithZoneForm;
use App\Filament\Resources\Zones\ZoneResource;
use App\Models\Slot;
use App\Models\TicketType;
use App\Models\Zone;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;

class EditZone extends EditRecord
{
    use InteractsWithZoneForm;

    protected static string $resource = ZoneResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Zone $record */
        $record = $this->record;

        return __('admin.zones.edit_title', ['name' => (string) ($record->tr('name') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteZoneAction(),
        ];
    }

    /**
     * El modelo castea `prep_blocks_cupo` a boolean (o null), pero el Select usa claves string
     * ('' / '1' / '0'). Sin este mapeo al hidratar, el `false` del modelo no casa con la opción
     * '0', el Select cae al default '' y al guardar se degradaría a null (perdiendo el override).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $value = $data['prep_blocks_cupo'] ?? null;
        $data['prep_blocks_cupo'] = $value === null ? '' : ($value ? '1' : '0');

        return $data;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareZoneData($data);
    }

    protected function afterSave(): void
    {
        /** @var Zone $record */
        $record = $this->record;

        AuditLogger::log('content.zone_updated', $record, [
            'slug' => $record->slug,
            'name' => $record->tr('name'),
            'max_per_slot' => $record->max_per_slot,
            'max_guests_per_slot' => $record->max_guests_per_slot,
            'prep_blocks_cupo' => $record->prep_blocks_cupo,
            'is_active' => (bool) $record->is_active,
            'show_in_landing' => (bool) $record->show_in_landing,
        ]);

        // Desactivar/reactivar una zona cambia qué ENTRADAS son operativas (comprables) → mueve el
        // mínimo del CTA «desde X €» de la landing, que lo filtra por `inOperationalZone()` (Sistema 6 ·
        // W2/W3). Invalidar la caché para que el nav/hero no anuncien un precio de zona desactivada.
        Cache::forget('cta.min_price_cents');
    }

    /** Solo se puede borrar una zona SIN productos ni franjas (borrarla arrastraría franjas → ventas/tickets). */
    public static function isDeletable(Zone $zone): bool
    {
        return ! TicketType::where('zone_id', $zone->id)->exists()
            && ! Slot::where('zone_id', $zone->id)->exists();
    }

    private function deleteZoneAction(): Action
    {
        return Action::make('deleteZone')
            ->label(__('admin.zones.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.zones.actions.delete.modal_heading'))
            ->modalDescription(__('admin.zones.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.zones.actions.delete.submit'))
            ->action(function (Zone $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                // Defensa real (además de la UI): una zona con productos/franjas no se borra
                // — el cascade de `slots` destruiría order_items/tickets y los productos
                // quedarían huérfanos. Se desactiva en su lugar.
                if (! self::isDeletable($record)) {
                    AuditLogger::log('content.zone_delete_blocked', $record, ['slug' => $record->slug, 'reason' => 'has_products_or_slots']);
                    Notification::make()->title(__('admin.zones.actions.delete.blocked'))->danger()->send();

                    return;
                }

                AuditLogger::log('content.zone_deleted', $record, ['slug' => $record->slug]);
                $record->delete();

                Notification::make()->title(__('admin.zones.actions.delete.success'))->success()->send();

                $this->redirect(ZoneResource::getUrl('index'));
            });
    }
}
