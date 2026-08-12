<?php

namespace App\Filament\Resources\Slots\Pages;

use App\Filament\Concerns\RegeneratesSlots;
use App\Filament\Resources\Slots\SlotResource;
use Filament\Resources\Pages\ListRecords;

class ListSlots extends ListRecords
{
    use RegeneratesSlots;

    protected static string $resource = SlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->regenerateSlotsAction(),
        ];
    }
}
