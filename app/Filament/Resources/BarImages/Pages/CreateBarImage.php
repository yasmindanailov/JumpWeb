<?php

namespace App\Filament\Resources\BarImages\Pages;

use App\Domain\Content\Models\BarImage;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\BarImages\BarImageResource;
use App\Filament\Resources\BarImages\Concerns\InteractsWithBarImageForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateBarImage extends CreateRecord
{
    use InteractsWithBarImageForm;

    protected static string $resource = BarImageResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.bar_images.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareBarImageData($data);
    }

    protected function afterCreate(): void
    {
        /** @var BarImage $record */
        $record = $this->record;

        AuditLogger::log('content.bar_image_created', $record, [
            'kind' => (string) $record->kind,
            'alt' => $record->tr('alt'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return BarImageResource::getUrl('index');
    }
}
