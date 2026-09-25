<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Domain\Booking\Models\Promotion;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Promotions\Concerns\InteractsWithPromotionForm;
use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreatePromotion extends CreateRecord
{
    use InteractsWithPromotionForm;

    protected static string $resource = PromotionResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.promotions.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->preparePromotionData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Promotion $record */
        $record = $this->record;

        AuditLogger::log('catalog.promotion_created', $record, $this->promotionAuditPayload($record));
    }

    protected function getRedirectUrl(): string
    {
        return PromotionResource::getUrl('index');
    }
}
