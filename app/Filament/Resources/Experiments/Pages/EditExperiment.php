<?php

namespace App\Filament\Resources\Experiments\Pages;

use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Experiments\ExperimentResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditExperiment extends EditRecord
{
    protected static string $resource = ExperimentResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Experiment $record */
        $record = $this->record;

        return __('admin.experiments.edit_title', ['name' => $record->name]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteExperimentAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return ExperimentResource::getUrl('index');
    }

    /** El rastro (`experiments.saved`): encender, apagar o mover la ventana queda con la clave y el estado. */
    protected function afterSave(): void
    {
        /** @var Experiment $record */
        $record = $this->record;

        AuditLogger::log('experiments.saved', $record, [
            'key' => $record->key,
            'name' => $record->name,
            'active' => $record->active,
            'variants' => $record->weightedVariants(),
            'created' => false,
        ]);
    }

    /**
     * Borrar deja de asignar al instante (el modelo olvida la caché de los vivos); las exposiciones ya contadas
     * siguen en el libro con su clave, así que el informe del periodo no cambia. Confirmación + rastro.
     */
    private function deleteExperimentAction(): Action
    {
        return Action::make('deleteExperiment')
            ->label(__('admin.experiments.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => ExperimentResource::canViewAny())
            ->requiresConfirmation()
            ->modalHeading(__('admin.experiments.actions.delete.modal_heading'))
            ->modalDescription(__('admin.experiments.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.experiments.actions.delete.submit'))
            ->action(function (Experiment $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('experiments.deleted', $record, ['key' => $record->key, 'name' => $record->name]);
                $record->delete();

                Notification::make()->title(__('admin.experiments.actions.delete.success'))->success()->send();

                $this->redirect(ExperimentResource::getUrl('index'));
            });
    }
}
