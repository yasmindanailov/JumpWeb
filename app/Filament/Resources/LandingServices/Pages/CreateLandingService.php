<?php

namespace App\Filament\Resources\LandingServices\Pages;

use App\Domain\Content\Models\LandingService;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\LandingServices\Concerns\InteractsWithLandingServiceForm;
use App\Filament\Resources\LandingServices\LandingServiceResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateLandingService extends CreateRecord
{
    use InteractsWithLandingServiceForm;

    protected static string $resource = LandingServiceResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.landing_services.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareLandingServiceData($data);
    }

    protected function afterCreate(): void
    {
        /** @var LandingService $record */
        $record = $this->record;

        AuditLogger::log('content.landing_service_created', $record, [
            'slug' => (string) $record->slug,
            'title' => $record->tr('title'),
            'ticket_type_id' => $record->ticket_type_id ? (int) $record->ticket_type_id : null,
            'is_active' => (bool) $record->is_active,
            'show_in_nav' => (bool) $record->show_in_nav,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return LandingServiceResource::getUrl('index');
    }
}
