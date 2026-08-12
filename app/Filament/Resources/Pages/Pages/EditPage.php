<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Pages\Concerns\InteractsWithPageForm;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;

class EditPage extends EditRecord
{
    use InteractsWithPageForm;

    protected static string $resource = PageResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Page $record */
        $record = $this->record;

        return __('admin.pages.edit_title', ['name' => (string) ($record->tr('title') ?? $record->slug)]);
    }

    protected function getHeaderActions(): array
    {
        /** @var Page $record */
        $record = $this->record;

        return [
            Action::make('viewOnWeb')
                ->label(__('admin.pages.actions.view'))
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->url(fn (): ?string => Route::has('legal.'.$record->slug) ? route('legal.'.$record->slug) : null)
                ->openUrlInNewTab()
                ->visible(fn (): bool => Route::has('legal.'.$record->slug)),
        ];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->splitBodyForForm($data);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->preparePageData($data);
    }

    protected function afterSave(): void
    {
        /** @var Page $record */
        $record = $this->record;

        AuditLogger::log('content.page_updated', $record, [
            'slug' => $record->slug,
            'title' => $record->tr('title'),
            'is_active' => (bool) $record->is_active,
        ]);
    }
}
