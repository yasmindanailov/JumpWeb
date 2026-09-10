<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Domain\Content\Models\Testimonial;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Testimonials\Concerns\InteractsWithTestimonialForm;
use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class EditTestimonial extends EditRecord
{
    use InteractsWithTestimonialForm;

    protected static string $resource = TestimonialResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Testimonial $record */
        $record = $this->record;

        return __('admin.testimonials.edit_title', ['name' => (string) $record->author]);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->deleteTestimonialAction(),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareTestimonialData($data);
    }

    protected function afterSave(): void
    {
        /** @var Testimonial $record */
        $record = $this->record;

        AuditLogger::log('content.testimonial_updated', $record, [
            'author' => (string) $record->author,
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    /** Una opinión no tiene dependientes: ninguna FK la referencia. Confirmación + audit. */
    private function deleteTestimonialAction(): Action
    {
        return Action::make('deleteTestimonial')
            ->label(__('admin.testimonials.actions.delete.label'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => auth()->user()?->hasPermission('content.manage') ?? false)
            ->requiresConfirmation()
            ->modalHeading(__('admin.testimonials.actions.delete.modal_heading'))
            ->modalDescription(__('admin.testimonials.actions.delete.modal_description'))
            ->modalSubmitActionLabel(__('admin.testimonials.actions.delete.submit'))
            ->action(function (Testimonial $record): void {
                $record = $record->fresh();

                if ($record === null) {
                    return;
                }

                AuditLogger::log('content.testimonial_deleted', $record, ['author' => (string) $record->author]);
                $record->delete();

                Notification::make()->title(__('admin.testimonials.actions.delete.success'))->success()->send();

                $this->redirect(TestimonialResource::getUrl('index'));
            });
    }
}
