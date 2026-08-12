<?php

namespace App\Filament\Resources\Faqs\Pages;

use App\Filament\Resources\Faqs\Concerns\InteractsWithFaqForm;
use App\Filament\Resources\Faqs\FaqResource;
use App\Models\Faq;
use App\Support\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateFaq extends CreateRecord
{
    use InteractsWithFaqForm;

    protected static string $resource = FaqResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.faqs.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareFaqData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Faq $record */
        $record = $this->record;

        AuditLogger::log('content.faq_created', $record, [
            'question' => $record->tr('question'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return FaqResource::getUrl('index');
    }
}
