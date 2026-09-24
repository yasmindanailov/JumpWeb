<?php

namespace App\Filament\Resources\Experiments\Pages;

use App\Domain\Platform\Models\Experiment;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Experiments\ExperimentResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateExperiment extends CreateRecord
{
    protected static string $resource = ExperimentResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.experiments.create_title');
    }

    protected function getRedirectUrl(): string
    {
        return ExperimentResource::getUrl('index');
    }

    /** El rastro (`experiments.saved`): la clave, las variantes y el estado; nunca PII. */
    protected function afterCreate(): void
    {
        /** @var Experiment $record */
        $record = $this->record;

        AuditLogger::log('experiments.saved', $record, [
            'key' => $record->key,
            'name' => $record->name,
            'active' => $record->active,
            'variants' => $record->weightedVariants(),
            'created' => true,
        ]);
    }
}
