<?php

namespace App\Filament\Resources\Surveys\Schemas;

use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * **El formulario de una encuesta** (`docs/specs/encuestas.md` §4.1 y §4.4, T1): la clave, la clase, el encendido y
 * la ventana; el nombre y la frase de cabecera en tres idiomas; y las preguntas, con el molde de los esquemas de
 * campos del catálogo (`{key, type, required, label{es,en,fr}}`) y sus opciones cuando el tipo las pide.
 *
 * ⚠️ **Con respuestas guardadas, la clave, la clase y las claves y los tipos de las preguntas van BLOQUEADOS**
 * (los rótulos siguen editables): cambiar una pregunta a mitad mezcla lo medido, como los pesos de un experimento.
 * Un campo deshabilitado no viaja al guardar: el valor de la BD se queda tal cual. Las preguntas se normalizan al
 * guardar (`QuestionSchema::normalize()`, en las páginas): lo que entra en la BD es lo que la puerta y el correo leen.
 */
class SurveyForm
{
    public static function configure(Schema $schema): Schema
    {
        $locked = static fn (?Survey $record): bool => $record !== null && $record->hasResponses();

        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.surveys.section_survey'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('key')
                            ->label(__('admin.surveys.field_key'))
                            ->helperText(__('admin.surveys.field_key_hint'))
                            ->required()
                            ->maxLength(48)
                            ->regex(Survey::KEY_RE)
                            ->unique(ignoreRecord: true)
                            ->disabled($locked),
                        Select::make('kind')
                            ->label(__('admin.surveys.field_kind'))
                            ->options([
                                Survey::KIND_INTERNAL => __('admin.surveys.kind.internal'),
                                Survey::KIND_EXTERNAL => __('admin.surveys.kind.external'),
                            ])
                            ->default(Survey::KIND_INTERNAL)
                            ->required()
                            ->native(false)
                            ->selectablePlaceholder(false)
                            ->disabled($locked),
                        Toggle::make('active')
                            ->label(__('admin.surveys.field_active'))
                            ->helperText(__('admin.surveys.field_active_hint'))
                            ->default(false)
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at')
                            ->label(__('admin.surveys.field_starts_at'))
                            ->helperText(__('admin.surveys.field_window_hint'))
                            ->seconds(false)
                            ->native(false),
                        DateTimePicker::make('ends_at')
                            ->label(__('admin.surveys.field_ends_at'))
                            ->helperText(__('admin.surveys.field_window_hint'))
                            ->seconds(false)
                            ->native(false)
                            ->after('starts_at'),
                    ]),
                Section::make(__('admin.surveys.section_texts'))
                    ->schema([
                        Tabs::make('translations')->tabs([
                            self::translatableTab('es', __('admin.catalog.lang.es')),
                            self::translatableTab('en', __('admin.catalog.lang.en')),
                            self::translatableTab('fr', __('admin.catalog.lang.fr')),
                        ]),
                    ]),
                Section::make(__('admin.surveys.section_questions'))
                    ->description(__('admin.surveys.questions_hint'))
                    ->schema([
                        Repeater::make('questions')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(['default' => 1, 'sm' => 3])->schema([
                                    // ⚠️ Bloqueados PERO dehidratados: la clave y el tipo tienen que viajar con el guardado
                                    // para que `GuardsSurveyForm` empareje cada rótulo editado con su pregunta; lo que
                                    // llegue distinto de lo guardado se ignora allí (ocultar no es autorizar).
                                    TextInput::make('key')
                                        ->label(__('admin.surveys.field_question_key'))
                                        ->required()
                                        ->maxLength(40)
                                        ->regex(QuestionSchema::KEY_RE)
                                        ->distinct()
                                        ->disabled($locked)
                                        ->dehydrated(),
                                    Select::make('type')
                                        ->label(__('admin.surveys.field_question_type'))
                                        ->options(array_combine(QuestionSchema::TYPES, array_map(static fn (string $type): string => __('admin.surveys.type.'.$type), QuestionSchema::TYPES)))
                                        ->default(QuestionSchema::TYPE_CHOICE)
                                        ->required()
                                        ->native(false)
                                        ->selectablePlaceholder(false)
                                        ->live()
                                        ->disabled($locked)
                                        ->dehydrated(),
                                    Toggle::make('required')
                                        ->label(__('admin.surveys.field_required'))
                                        ->default(false)
                                        ->inline(false),
                                ]),
                                Grid::make(['default' => 1, 'sm' => 3])->schema([
                                    TextInput::make('label.es')->label(__('admin.surveys.field_label').' (ES)')->required()->maxLength(160),
                                    TextInput::make('label.en')->label(__('admin.surveys.field_label').' (EN)')->maxLength(160),
                                    TextInput::make('label.fr')->label(__('admin.surveys.field_label').' (FR)')->maxLength(160),
                                ]),
                                Repeater::make('options')
                                    ->label(__('admin.surveys.field_options'))
                                    ->visible(static fn (Get $get): bool => in_array($get('type'), QuestionSchema::WITH_OPTIONS, true))
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 4])->schema([
                                            TextInput::make('key')
                                                ->label(__('admin.surveys.field_option_key'))
                                                ->required()
                                                ->maxLength(40)
                                                ->regex(QuestionSchema::KEY_RE)
                                                ->distinct()
                                                ->disabled($locked)
                                                ->dehydrated(),
                                            TextInput::make('label.es')->label(__('admin.surveys.option_label').' (ES)')->required()->maxLength(120),
                                            TextInput::make('label.en')->label(__('admin.surveys.option_label').' (EN)')->maxLength(120),
                                            TextInput::make('label.fr')->label(__('admin.surveys.option_label').' (FR)')->maxLength(120),
                                        ]),
                                    ])
                                    ->itemLabel(static fn (array $state): ?string => $state['key'] ?? null)
                                    ->addActionLabel(__('admin.surveys.actions.add_option'))
                                    ->defaultItems(2)
                                    ->minItems(2)
                                    ->maxItems(12)
                                    ->reorderable(static fn (?Survey $record): bool => ! $locked($record))
                                    ->addable(static fn (?Survey $record): bool => ! $locked($record))
                                    ->deletable(static fn (?Survey $record): bool => ! $locked($record)),
                            ])
                            ->itemLabel(static fn (array $state): ?string => $state['key'] ?? null)
                            ->addActionLabel(__('admin.surveys.actions.add_question'))
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(20)
                            ->collapsible()
                            ->reorderable(static fn (?Survey $record): bool => ! $locked($record))
                            ->addable(static fn (?Survey $record): bool => ! $locked($record))
                            ->deletable(static fn (?Survey $record): bool => ! $locked($record)),
                    ]),
            ]);
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("name.{$locale}")
                ->label(__('admin.surveys.field_name'))
                // El español es obligatorio (es la base del respaldo de `Translated::pick()`); en/fr opcionales.
                ->required($locale === 'es')
                ->maxLength(120),
            Textarea::make("intro.{$locale}")
                ->label(__('admin.surveys.field_intro'))
                ->helperText(__('admin.surveys.field_intro_hint'))
                ->rows(2)
                ->maxLength(300),
        ]);
    }
}
