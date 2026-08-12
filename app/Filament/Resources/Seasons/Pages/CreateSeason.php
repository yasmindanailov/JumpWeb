<?php

namespace App\Filament\Resources\Seasons\Pages;

use App\Filament\Resources\Seasons\Concerns\InteractsWithSeasonForm;
use App\Filament\Resources\Seasons\SeasonResource;
use App\Models\Season;
use App\Support\AuditLogger;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateSeason extends CreateRecord
{
    use InteractsWithSeasonForm;

    protected static string $resource = SeasonResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.seasons.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? true);

        return $this->validateSeasonData($data);
    }

    protected function afterCreate(): void
    {
        /** @var Season $record */
        $record = $this->record;

        AuditLogger::log('slots.season_created', $record, [
            'name' => $record->name,
            'range' => $record->start_date?->toDateString().'…'.$record->end_date?->toDateString(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return SeasonResource::getUrl('index');
    }
}
