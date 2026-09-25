<?php

namespace App\Filament\Resources\Testimonials\Schemas;

use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Testimonial;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Alta y edición de una opinión (`#490`): escrita en el panel o **copiada de la ficha de Google** del parque (`#771`,
 * la importa `php artisan reviews:import`). Molde de `FaqForm`.
 *
 * ⚠️ **La fecha es una FECHA, no un «hace 2 meses» tecleado.** La frase se deriva de ella al
 * pintar; escrita a mano sería verdad el día que se escribe y mentira dos meses después.
 * ⚠️ **La nota es opcional**: una opinión sin estrellas sigue siendo una opinión, y un valor por
 * defecto afirmaría una nota que nadie ha puesto.
 * ▶ **Las PÁGINAS** (`tags`) deciden dónde sale en las páginas nuevas: la de Kids pinta las etiquetadas «kids», y así.
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
                        TextInput::make('author_meta')
                            ->label(__('admin.testimonials.field_author_meta'))
                            ->helperText(__('admin.testimonials.field_author_meta_hint'))
                            ->maxLength(160),
                        Select::make('rating')
                            ->label(__('admin.testimonials.field_rating'))
                            ->helperText(__('admin.testimonials.field_rating_hint'))
                            ->options([1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'])
                            ->native(false),
                        DatePicker::make('published_at')
                            ->label(__('admin.testimonials.field_published_at'))
                            ->helperText(__('admin.testimonials.field_published_at_hint')),
                        FileUpload::make('avatar')
                            ->label(__('admin.testimonials.field_avatar'))
                            ->helperText(__('admin.testimonials.field_avatar_hint'))
                            ->disk(Testimonial::IMAGE_DISK)
                            ->directory('resenas')
                            ->visibility('public')
                            ->image()
                            ->avatar()
                            ->maxSize(1024)
                            ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png']),
                        FileUpload::make('photos')
                            ->label(__('admin.testimonials.field_photos'))
                            ->helperText(__('admin.testimonials.field_photos_hint'))
                            ->disk(Testimonial::IMAGE_DISK)
                            ->directory('resenas')
                            ->visibility('public')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->maxFiles(3)
                            ->maxSize(3072)
                            ->acceptedFileTypes(['image/webp', 'image/jpeg', 'image/png']),
                    ]),

                Section::make(__('admin.testimonials.section_origin'))
                    ->columns(2)
                    ->schema([
                        /*
                         * DE DÓNDE ES (`#771`). Una copiada de la ficha sale con la marca de Google y «Ver en Google»
                         * (su enlace); una escrita aquí, sin ninguno de los dos: no lleva la ropa de otro.
                         */
                        Select::make('origin')
                            ->label(__('admin.testimonials.field_origin'))
                            ->options([
                                Testimonial::ORIGIN_OWN => __('admin.testimonials.origins.own'),
                                Testimonial::ORIGIN_GOOGLE => __('admin.testimonials.origins.google'),
                            ])
                            ->default(Testimonial::ORIGIN_OWN)
                            ->required()
                            ->live()
                            ->native(false),
                        TextInput::make('source_url')
                            ->label(__('admin.testimonials.field_source_url'))
                            ->helperText(__('admin.testimonials.field_source_url_hint'))
                            ->url()
                            ->maxLength(500)
                            ->visible(fn (Get $get): bool => $get('origin') === Testimonial::ORIGIN_GOOGLE),
                    ]),

                Section::make(__('admin.testimonials.section_classification'))
                    ->columns(2)
                    ->schema([
                        TagsInput::make('tags')
                            ->label(__('admin.testimonials.field_tags'))
                            ->helperText(__('admin.testimonials.field_tags_hint'))
                            ->suggestions(fn (): array => self::paginas())
                            ->columnSpanFull(),
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

                Section::make(__('admin.testimonials.section_reply'))
                    ->schema([
                        Textarea::make('reply')
                            ->label(__('admin.testimonials.field_reply'))
                            ->helperText(__('admin.testimonials.field_reply_hint'))
                            ->rows(3)
                            ->maxLength(2000),
                    ]),
            ]);
    }

    /**
     * Las etiquetas que se sugieren: las zonas, las páginas habituales y las que ya se usan. Sugerencias, no una lista
     * cerrada: una página nueva estrena su etiqueta escribiéndola.
     *
     * @return list<string>
     */
    private static function paginas(): array
    {
        $usadas = Testimonial::query()->whereNotNull('tags')->pluck('tags')->flatten()->filter(fn ($t): bool => is_string($t))->all();

        return array_values(array_unique([...Zone::query()->pluck('slug')->all(), 'portada', 'cumpleanos', 'colegios', 'visitanos', ...$usadas]));
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            Textarea::make("text.{$locale}")
                ->label(__('admin.testimonials.field_text'))
                // Una reseña copiada va ENTERA (no se recorta nunca); el tope solo protege la columna.
                ->helperText($locale === 'es' ? __('admin.testimonials.field_text_hint') : null)
                ->required($locale === 'es')
                ->rows(4)
                ->maxLength(2000),
        ]);
    }
}
