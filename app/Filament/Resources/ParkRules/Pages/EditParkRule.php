<?php

namespace App\Filament\Resources\ParkRules\Pages;

use App\Domain\Content\Models\VenueRule;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\ParkRules\Concerns\InteractsWithParkRuleForm;
use App\Filament\Resources\ParkRules\ParkRuleResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditParkRule extends EditRecord
{
    use InteractsWithParkRuleForm;

    protected static string $resource = ParkRuleResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var VenueRule $record */
        $record = $this->record;

        return __('admin.park_rules.edit_title', ['name' => (string) ($record->tr('name') ?? '')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteParkRuleAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareParkRuleData($data);
    }

    protected function afterSave(): void
    {
        /** @var VenueRule $record */
        $record = $this->record;

        AuditLogger::log('content.rule_updated', $record, [
            'name' => $record->tr('name'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    /**
     * Una norma no tiene dependientes (ninguna FK la referencia): se puede borrar libremente.
     */
    private function deleteParkRuleAction(): Action
    {
        return Action::make('deleteParkRule')
            ->label(__('admin.park_rules.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.park_rules.actions.delete.modal_heading'))
            ->modalDescription(__('admin.park_rules.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.park_rules.actions.delete.submit'))
            ->action(function (VenueRule $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.rule_deleted', $record, ['name' => $record->tr('name')]);
                $record->delete();

                Notification::make()->title(__('admin.park_rules.actions.delete.success'))->success()->send();

                $this->redirect(ParkRuleResource::getUrl('index'));
            });
    }
}
