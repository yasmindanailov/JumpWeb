<?php

namespace App\Filament\Resources\Experiments\Pages;

use App\Filament\Resources\Experiments\ExperimentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExperiments extends ListRecords
{
    protected static string $resource = ExperimentResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.experiments.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.experiments.actions.create')),
        ];
    }
}
