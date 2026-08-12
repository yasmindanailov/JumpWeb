<?php

namespace App\Filament\Resources\ParkRules\Pages;

use App\Domain\Content\Models\VenueRule;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\ParkRules\Concerns\InteractsWithParkRuleForm;
use App\Filament\Resources\ParkRules\ParkRuleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateParkRule extends CreateRecord
{
    use InteractsWithParkRuleForm;

    protected static string $resource = ParkRuleResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('admin.park_rules.create_title');
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->prepareParkRuleData($data);
    }

    protected function afterCreate(): void
    {
        /** @var VenueRule $record */
        $record = $this->record;

        AuditLogger::log('content.rule_created', $record, [
            'name' => $record->tr('name'),
            'is_active' => (bool) $record->is_active,
            'position' => (int) $record->position,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return ParkRuleResource::getUrl('index');
    }
}
