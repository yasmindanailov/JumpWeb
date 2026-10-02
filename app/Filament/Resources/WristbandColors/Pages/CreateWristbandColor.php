<?php

namespace App\Filament\Resources\WristbandColors\Pages;

use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\WristbandColors\WristbandColorResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateWristbandColor extends CreateRecord
{
    protected static string $resource = WristbandColorResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.wristbands.create_title');
    }

    /**
     * Un color nuevo va al FINAL de la lista (y de la rueda): el orden lo cambia quien arrastra, no el alta.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['hex'] = strtolower((string) ($data['hex'] ?? ''));
        $data['in_wheel'] = (bool) ($data['in_wheel'] ?? true);
        $data['position'] = (int) WristbandColor::query()->max('position') + 1;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var WristbandColor $record */
        $record = $this->record;

        AuditLogger::log('catalog.wristband_saved', $record, self::trail($record) + ['created' => true]);
    }

    /** @return array<string, mixed> el rastro de un color, sin nada personal */
    public static function trail(WristbandColor $record): array
    {
        return [
            'name_one' => $record->name_one,
            'name_other' => $record->name_other,
            'hex' => $record->hex,
            'in_wheel' => (bool) $record->in_wheel,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return WristbandColorResource::getUrl('index');
    }
}
