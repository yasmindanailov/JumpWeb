<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Filament\Resources\Offers\Concerns\InteractsWithOfferForm;
use App\Filament\Resources\Offers\OfferResource;
use App\Models\Offer;
use App\Support\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateOffer extends CreateRecord
{
    use InteractsWithOfferForm;

    protected static string $resource = OfferResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.offers.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareOfferData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Offer $record */
        $record = $this->record;

        AuditLogger::log('content.offer_created', $record, [
            'title' => $record->tr('title'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return OfferResource::getUrl('index');
    }
}
