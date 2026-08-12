<?php

namespace App\Filament\Resources\Attractions\Pages;

use App\Domain\Content\Models\Attraction;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Attractions\AttractionResource;
use App\Filament\Resources\Attractions\Concerns\InteractsWithAttractionForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateAttraction extends CreateRecord
{
    use InteractsWithAttractionForm;

    protected static string $resource = AttractionResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.attractions.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareAttractionData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Attraction $record */
        $record = $this->record;

        AuditLogger::log('content.attraction_created', $record, [
            'zone_id' => (int) $record->zone_id,
            'name' => $record->tr('name'),
            'is_active' => (bool) $record->is_active,
            'is_special' => (bool) $record->is_special,
            'ticket_type_id' => $record->ticket_type_id ? (int) $record->ticket_type_id : null,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return AttractionResource::getUrl('index');
    }
}
