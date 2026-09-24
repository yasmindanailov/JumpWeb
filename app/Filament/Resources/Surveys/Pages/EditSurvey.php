<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Surveys\Concerns\GuardsSurveyForm;
use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditSurvey extends EditRecord
{
    use GuardsSurveyForm;

    protected static string $resource = SurveyResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Survey $record */
        $record = $this->record;

        return __('admin.surveys.edit_title', ['name' => $record->displayName()]);
    }

    public function getSubheading(): string|Htmlable|null
    {
        /** @var Survey $record */
        $record = $this->record;

        return $record->hasResponses() ? __('admin.surveys.locked_hint') : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteSurveyAction(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return SurveyResource::getUrl('index');
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Survey $record */
        $record = $this->record;

        return $this->guard($data, $record);
    }

    protected function afterSave(): void
    {
        /** @var Survey $record */
        $record = $this->record;
        AuditLogger::log('surveys.saved', $record, $this->trail($record) + ['created' => false]);
    }

    /** Con respuestas no se borra (se apaga): la acción ni siquiera se ofrece (`SurveyResource::canDelete()`). */
    private function deleteSurveyAction(): Action
    {
        return Action::make('deleteSurvey')
            ->label(__('admin.surveys.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => SurveyResource::canDelete($this->record))
            ->requiresConfirmation()
            ->modalHeading(__('admin.surveys.actions.delete.modal_heading'))
            ->modalDescription(__('admin.surveys.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.surveys.actions.delete.submit'))
            ->action(function (Survey $record): void {
                $record = $record->fresh();
                if ($record === null || ! SurveyResource::canDelete($record)) {
                    return;
                }
                AuditLogger::log('surveys.deleted', $record, ['key' => $record->key, 'name' => $record->displayName('es'), 'kind' => $record->kind]);
                $record->delete();
                Notification::make()->title(__('admin.surveys.actions.delete.success'))->success()->send();
                $this->redirect(SurveyResource::getUrl('index'));
            });
    }
}
