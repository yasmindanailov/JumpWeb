<?php

namespace App\Filament\Resources\Zones\Pages;

use App\Filament\Resources\Zones\Concerns\InteractsWithZoneForm;
use App\Filament\Resources\Zones\ZoneResource;
use App\Models\Zone;
use App\Support\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateZone extends CreateRecord
{
    use InteractsWithZoneForm;

    protected static string $resource = ZoneResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.zones.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareZoneData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Zone $record */
        $record = $this->record;

        AuditLogger::log('content.zone_created', $record, [
            'slug' => $record->slug,
            'name' => $record->tr('name'),
            'max_per_slot' => $record->max_per_slot,
            'max_guests_per_slot' => $record->max_guests_per_slot,
            'is_active' => (bool) $record->is_active,
            'show_in_landing' => (bool) $record->show_in_landing,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return ZoneResource::getUrl('index');
    }
}
