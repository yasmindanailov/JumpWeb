<?php

namespace App\Filament\Resources\SlotTemplates\Pages;

use App\Filament\Concerns\GeneratesSlotTemplates;
use App\Filament\Resources\SlotTemplates\SlotTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotTemplates extends ListRecords
{
    use GeneratesSlotTemplates;

    protected static string $resource = SlotTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Generador en bloque (P15): crea todas las plantillas de una zona de una vez.
            $this->generateSlotTemplatesAction(),
            CreateAction::make()->label(__('admin.slot_templates.actions.create')),
        ];
    }
}
