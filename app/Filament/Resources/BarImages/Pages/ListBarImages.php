<?php

namespace App\Filament\Resources\BarImages\Pages;

use App\Filament\Resources\BarImages\BarImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBarImages extends ListRecords
{
    protected static string $resource = BarImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('admin.bar_images.actions.create')),
        ];
    }
}
