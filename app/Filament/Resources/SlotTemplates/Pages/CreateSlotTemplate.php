<?php

namespace App\Filament\Resources\SlotTemplates\Pages;

use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\SlotTemplates\Concerns\InteractsWithSlotTemplateForm;
use App\Filament\Resources\SlotTemplates\SlotTemplateResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateSlotTemplate extends CreateRecord
{
    use InteractsWithSlotTemplateForm;

    protected static string $resource = SlotTemplateResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.slot_templates.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareTemplateData($data);
    }

    protected function afterCreate(): void
    {
        /** @var SlotTemplate $record */
        $record = $this->record;

        AuditLogger::log('slots.template_created', $record, $this->auditPayload($record));
    }

    protected function getRedirectUrl(): string
    {
        return SlotTemplateResource::getUrl('index');
    }

    /** @return array<string,mixed> */
    private function auditPayload(SlotTemplate $record): array
    {
        return [
            'zone_id' => $record->zone_id,
            'weekday' => $record->weekday,
            'start_time' => substr((string) $record->start_time, 0, 5),
            'capacity' => $record->capacity,
            'online_capacity' => $record->online_capacity,
            'is_active' => (bool) $record->is_active,
        ];
    }
}
