<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Surveys\Concerns\GuardsSurveyForm;
use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateSurvey extends CreateRecord
{
    use GuardsSurveyForm;

    protected static string $resource = SurveyResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.surveys.create_title');
    }

    protected function getRedirectUrl(): string
    {
        return SurveyResource::getUrl('index');
    }

    /** @param  array<string, mixed>  $data */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $this->guard($data, null);
    }

    protected function afterCreate(): void
    {
        /** @var Survey $record */
        $record = $this->record;
        AuditLogger::log('surveys.saved', $record, $this->trail($record) + ['created' => true]);
    }
}
