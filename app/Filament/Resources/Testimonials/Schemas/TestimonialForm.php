<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

/**
 * Alta y edición de una opinión propia (`#490`). Molde de `FaqForm`.
 *
 * ⚠️ **La fecha es una FECHA, no un «hace 2 meses» tecleado.** La frase se deriva de ella al
 * pintar; escrita a mano sería verdad el día que se escribe y mentira dos meses después.
 * ⚠️ **La nota es opcional**: una opinión sin estrellas sigue siendo una opinión, y un valor por
 * defecto afirmaría una nota que nadie ha puesto.
 */
class TestimonialForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.testimonials.section_who'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('author')
                            ->label(__('admin.testimonials.field_author'))
                            ->helperText(__('admin.testimonials.field_author_hint'))
                            ->required()
                            ->maxLength(120),
                        Select::make('rating')
                            ->label(__('admin.testimonials.field_rating'))
                            ->helperText(__('admin.testimonials.field_rating_hint'))
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                            ->native(false),
                        DatePicker::make('published_at')
                            ->label(__('admin.testimonials.field_published_at'))
                            ->helperText(__('admin.testimonials.field_published_at_hint')),
                    ]),

                Section::make(__('admin.testimonials.section_classification'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('position')
                            ->label(__('admin.testimonials.field_position'))
                            ->helperText(__('admin.testimonials.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        Toggle::make('is_active')
                            ->label(__('admin.testimonials.field_is_active'))
                            ->helperText(__('admin.testimonials.field_is_active_hint'))
                            ->default(true),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.testimonials.lang.es')),
                    self::translatableTab('en', __('admin.testimonials.lang.en')),
                    self::translatableTab('fr', __('admin.testimonials.lang.fr')),
                ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Textarea::make("text.{$locale}")
                ->label(__('admin.testimonials.field_text'))
                // ⚠️ El tope no es capricho: la sección enseña la opinión ENTERA —no la recorta,
                // porque una opinión propia no tiene «la entera» en ningún otro sitio adonde
                // mandar—, así que una muy larga estira la tarjeta y descuadra el carril.
                ->helperText($locale === 'es' ? __('admin.testimonials.field_text_hint') : null)
                ->required($locale === 'es')
                ->rows(4)
                ->maxLength(400),
        ]);
    }
}
