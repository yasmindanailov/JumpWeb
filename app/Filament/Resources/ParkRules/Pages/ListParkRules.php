<?php

namespace App\Filament\Resources\ParkRules\Pages;

use App\Filament\Resources\ParkRules\ParkRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListParkRules extends ListRecords
{
    protected static string $resource = ParkRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.park_rules.actions.create')),
        ];
    }
}
