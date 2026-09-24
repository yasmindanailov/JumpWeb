<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSurveys extends ListRecords
{
    protected static string $resource = SurveyResource::class;

    public function getSubheading(): ?string
    {
        return __('admin.surveys.subheading');
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.surveys.actions.create')),
        ];
    }
}
