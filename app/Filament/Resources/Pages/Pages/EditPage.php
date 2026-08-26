<?php

namespace App\Filament\Resources\Pages\Pages;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Services\LegalIdentity;
use App\Domain\Identity\Exceptions\DraftCannotBePublishedException;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\LegalDocuments;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Pages\Concerns\InteractsWithPageForm;
use App\Filament\Resources\Pages\PageResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;

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

            // Fase 6 · waiver (`specs/waiver-probatorio.md` §4.2): PUBLICAR es un acto con fecha, no un
            // guardado. Congela el texto GUARDADO como versión firmable, inmutable. Solo en la página
            // del waiver y solo para quien gestiona contenido.
            Action::make('publishVersion')
                ->label(__('admin.waiver.publish.label'))
                ->icon(Heroicon::OutlinedDocumentCheck)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => __('admin.waiver.publish.heading', ['next' => $this->nextVersionNumber()]))
                // El aviso de «borrador» (revisión `#169` §10.4): los marcadores bloquean, las palabras
                // avisan — un texto definitivo puede mencionarlas, pero quien publica tiene que verlo.
                ->modalDescription(function (): HtmlString {
                    $texts = $this->publishableTexts();
                    $description = __('admin.waiver.publish.description', [
                        'next' => $this->nextVersionNumber(),
                        // F-07 (`#181`): los idiomas que se ANUNCIAN son los que `publish()` publicará (con cuerpo).
                        'locales' => implode(', ', LegalDocumentPublisher::publishableLocales($texts)) ?: '—',
                    ]);
                    $drafty = LegalDocumentPublisher::mentionsDraftWords($texts);

                    // F-02 (`#181`): Filament pinta la descripción escapada en un `<p>`; un `\n\n` no separa nada.
                    return new HtmlString($drafty === []
                        ? e($description)
                        : e($description).'<br><br><strong>'.e(__('admin.waiver.publish.draft_words', ['locales' => implode(', ', $drafty)])).'</strong>');
                })
                ->modalSubmitActionLabel(fn (): string => __('admin.waiver.publish.confirm', ['next' => $this->nextVersionNumber()]))
                ->visible(fn (): bool => $record->slug === WaiverSettings::SLUG
                    && (auth()->user()?->hasPermission('content.manage') ?? false))
                ->action(fn () => $this->publishVersion()),
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

    private function publishVersion(): void
    {
        /** @var Page $record */
        $record = $this->record;

        try {
            $rows = app(LegalDocumentPublisher::class)->publish($record->slug, $this->publishableTexts(), auth()->user());
        } catch (DraftCannotBePublishedException $e) {
            // El bloqueante de la revisión (§8.1) como mecanismo: un borrador no se congela.
            Notification::make()
                ->title(__('admin.waiver.publish.refused_draft'))
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        } catch (InvalidArgumentException) {
            Notification::make()
                ->title(__('admin.waiver.publish.nothing'))
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('admin.waiver.publish.done', [
                'version' => $rows->first()?->version,
                'locales' => $rows->pluck('locale')->implode(', '),
            ]))
            ->success()
            ->send();
    }

    private function nextVersionNumber(): int
    {
        return (LegalDocuments::latestVersionNumber((string) $this->record->slug) ?? 0) + 1;
    }

    /**
     * El texto tal y como se ENSEÑA: título y secciones por idioma, con los tokens fiscales YA
     * resueltos (`LegalIdentity::interpolate`) — el snapshot es lo que vio la persona, no la
     * plantilla. Se lee de lo PERSISTIDO, no del formulario: publicar lo que aún no se ha guardado
     * sería congelar un texto que la web no está sirviendo.
     *
     * @return array<string, array{title:string, body:list<array{h:string,p:string}>}>
     */
    private function publishableTexts(): array
    {
        /** @var Page $record */
        $record = $this->record->fresh() ?? $this->record;
        $bodies = is_array($record->body) ? $record->body : [];

        $texts = [];
        foreach ($bodies as $locale => $sections) {
            $body = [];
            foreach (is_array($sections) ? $sections : [] as $section) {
                if (! is_array($section)) {
                    continue;
                }
                $body[] = [
                    'h' => LegalIdentity::interpolate((string) ($section['h'] ?? '')),
                    'p' => LegalIdentity::interpolate((string) ($section['p'] ?? '')),
                ];
            }
            $texts[(string) $locale] = [
                'title' => LegalIdentity::interpolate((string) ($record->tr('title', (string) $locale) ?? '')),
                'body' => $body,
            ];
        }

        return $texts;
    }
}
