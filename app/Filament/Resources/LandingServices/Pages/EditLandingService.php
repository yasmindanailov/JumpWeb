<?php

namespace App\Filament\Resources\LandingServices\Pages;

use App\Domain\Content\Models\LandingService;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\LandingServices\Concerns\InteractsWithLandingServiceForm;
use App\Filament\Resources\LandingServices\LandingServiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditLandingService extends EditRecord
{
    use InteractsWithLandingServiceForm;

    protected static string $resource = LandingServiceResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var LandingService $record */
        $record = $this->record;

        return __('admin.landing_services.edit_title', ['name' => (string) ($record->tr('title') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteLandingServiceAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareLandingServiceData($data);
    }

    protected function afterSave(): void
    {
        /** @var LandingService $record */
        $record = $this->record;

        AuditLogger::log('content.landing_service_updated', $record, [
            'slug' => (string) $record->slug,
            'title' => $record->tr('title'),
            'ticket_type_id' => $record->ticket_type_id ? (int) $record->ticket_type_id : null,
            'is_active' => (bool) $record->is_active,
            'show_in_nav' => (bool) $record->show_in_nav,
            'position' => (int) $record->position,
        ]);
    }

    /**
     * Un servicio no tiene dependientes (la FK `ticket_type_id` apunta hacia fuera, no al revés):
     * se puede borrar libremente. Borrarlo devuelve su pack (si tenía) a la sección Cumpleaños.
     */
    private function deleteLandingServiceAction(): Action
    {
        return Action::make('deleteLandingService')
            ->label(__('admin.landing_services.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.landing_services.actions.delete.modal_heading'))
            ->modalDescription(__('admin.landing_services.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.landing_services.actions.delete.submit'))
            ->action(function (LandingService $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.landing_service_deleted', $record, ['slug' => (string) $record->slug, 'title' => $record->tr('title')]);
                $record->delete();

                Notification::make()->title(__('admin.landing_services.actions.delete.success'))->success()->send();

                $this->redirect(LandingServiceResource::getUrl('index'));
            });
    }
}
