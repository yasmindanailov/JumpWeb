<?php

namespace App\Filament\Resources\WristbandColors\Pages;

use App\Filament\Resources\WristbandColors\WristbandColorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWristbandColors extends ListRecords
{
    protected static string $resource = WristbandColorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.wristbands.actions.create')),
        ];
    }
}
