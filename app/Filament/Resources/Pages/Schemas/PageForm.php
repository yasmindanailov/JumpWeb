<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Domain\Content\Models\Page;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Fase 7.9 (iter. 2) — Formulario de edición de una página legal. El `slug` es de solo lectura
 * (identidad atada a la ruta). El cuerpo se edita por secciones (encabezado + párrafo) en un
 * Repeater por idioma; al persistir, `InteractsWithPageForm` lo recompone en la columna JSON
 * `body` con forma `{es:[{h,p}], en:[…], fr:[…]}` (la misma que consume el render público).
 */
class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.pages.section_settings'))
                    ->description(__('admin.pages.tokens_hint'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('slug')
                            ->label(__('admin.pages.field_slug'))
                            ->helperText(__('admin.pages.field_slug_hint'))
                            ->disabled()
                            ->dehydrated(false),
                        Toggle::make('is_active')
                            ->label(__('admin.pages.field_is_active'))
                            ->helperText(__('admin.pages.is_active_hint'))
                            ->default(true)
                            // Las páginas legales están enlazadas de forma OBLIGATORIA desde el pie, el
                            // sitemap, el banner de consentimiento (#219) y el registro → no se pueden
                            // desactivar (se fuerza activa al guardar; el toggle se deshabilita para que
                            // la UI sea coherente). Auditoría Fase 1 · Sistema 6 · W5 (antes solo `cookies`).
                            ->disabled(fn (?Page $record): bool => in_array($record?->slug, Page::PROTECTED_ACTIVE_SLUGS, true)),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.pages.lang.es')),
                    self::translatableTab('en', __('admin.pages.lang.en')),
                    self::translatableTab('fr', __('admin.pages.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("title.{$locale}")
                ->label(__('admin.pages.field_title'))
                ->required($locale === 'es')
                ->maxLength(160),

            Repeater::make("body_{$locale}")
                ->label(__('admin.pages.field_body'))
                ->schema([
                    TextInput::make('h')
                        ->label(__('admin.pages.field_h'))
                        ->maxLength(200),
                    Textarea::make('p')
                        ->label(__('admin.pages.field_p'))
                        ->rows(4)
                        ->maxLength(5000),
                ])
                ->itemLabel(fn (array $state): ?string => $state['h'] ?? null)
                ->addActionLabel(__('admin.pages.add_section'))
                ->reorderable()
                ->collapsible()
                ->defaultItems(0),
        ]);
    }
}
