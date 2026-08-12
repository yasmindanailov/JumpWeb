<?php

namespace App\Filament\Resources\LandingServices\Pages;

use App\Filament\Resources\LandingServices\LandingServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLandingServices extends ListRecords
{
    protected static string $resource = LandingServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.landing_services.actions.create')),
        ];
    }
}
