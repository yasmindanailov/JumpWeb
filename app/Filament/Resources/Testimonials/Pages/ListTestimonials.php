<?php

namespace App\Filament\Resources\Testimonials\Pages;

use App\Domain\Content\Services\CopiedRating;
use App\Domain\Platform\Services\AuditLogger;
use App\Filament\Resources\Testimonials\TestimonialResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListTestimonials extends ListRecords
{
    protected static string $resource = TestimonialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->notaDeGoogleAction(),
            CreateAction::make()->label(__('admin.testimonials.actions.create')),
        ];
    }

    /**
     * **La nota de la ficha de Google, a mano** (`#771`): la que enseñan las páginas mientras no haya Perfil de Empresa.
     * La guarda sola el importador al copiar; esto es para corregirla sin volver a copiar. Queda fechada hoy.
     */
    private function notaDeGoogleAction(): Action
    {
        return Action::make('googleRating')
            ->label(__('admin.testimonials.rating.action'))
            ->icon(Heroicon::OutlinedStar)
            ->color('gray')
            ->modalHeading(__('admin.testimonials.rating.heading'))
            ->modalDescription(__('admin.testimonials.rating.description'))
            ->fillForm(function (): array {
                $nota = app(CopiedRating::class)->get();

                return ['value' => $nota?->value, 'count' => $nota?->count, 'url' => $nota?->url];
            })
            ->schema([
                TextInput::make('value')->label(__('admin.testimonials.rating.value'))->numeric()->minValue(1)->maxValue(5)->step(0.1)->required(),
                TextInput::make('count')->label(__('admin.testimonials.rating.count'))->numeric()->minValue(1)->required(),
                TextInput::make('url')->label(__('admin.testimonials.rating.url'))->url()->maxLength(500),
            ])
            ->action(function (array $data): void {
                app(CopiedRating::class)->put((float) $data['value'], (int) $data['count'], ($data['url'] ?? null) ?: null, now());
                AuditLogger::log('content.testimonial_rating_updated', null, ['value' => (float) $data['value'], 'count' => (int) $data['count']]);
                Notification::make()->title(__('admin.testimonials.rating.saved'))->success()->send();
            });
    }
}
