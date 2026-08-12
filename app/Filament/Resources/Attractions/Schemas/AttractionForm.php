<?php

namespace App\Filament\Resources\Attractions\Schemas;

use App\Models\TicketType;
use App\Models\Zone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.9 (iter. 1) — Formulario de alta/edición de una atracción (compartido por crear/
 * editar). Identidad (zona, imagen, orden, activa, destacada, complemento) + textos i18n
 * (es/en/fr). La limpieza i18n, la normalización de la ruta de imagen y los defaults viven
 * en `InteractsWithAttractionForm`.
 *
 * La imagen es por ahora una RUTA relativa a `public/` (p. ej. `images/attractions/x.jpg`),
 * coherente con el dato sembrado; la subida de ficheros desde el panel llegará con la galería.
 *
 * Atracciones de pago (#228): `is_special` (resalte visual) + `ticket_type_id` (complemento
 * vendible vinculado → precio + CTA en la landing) son INDEPENDIENTES y opcionales.
 */
class AttractionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.attractions.section_identity'))
                    ->columns(2)
                    ->schema([
                        Select::make('zone_id')
                            ->label(__('admin.attractions.field_zone'))
                            ->helperText(__('admin.attractions.field_zone_hint'))
                            // Se marca la zona que NO se muestra en la landing: una atracción en
                            // una zona oculta (p. ej. cumpleaños) no aparece en la web, así que
                            // el operador lo ve antes de elegirla (coherencia añadir/quitar).
                            ->options(fn (): array => Zone::orderBy('position')->get()
                                ->mapWithKeys(fn (Zone $zone): array => [
                                    $zone->id => (string) $zone->tr('name')
                                        .($zone->show_in_landing ? '' : ' '.__('admin.attractions.zone_hidden_suffix')),
                                ])
                                ->all())
                            ->required()
                            ->native(false)
                            ->searchable()
                            ->live(),
                        TextInput::make('position')
                            ->label(__('admin.attractions.field_position'))
                            ->helperText(__('admin.attractions.field_position_hint'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0),
                        TextInput::make('image')
                            ->label(__('admin.attractions.field_image'))
                            ->helperText(__('admin.attractions.field_image_hint'))
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label(__('admin.attractions.field_is_active'))
                            ->helperText(__('admin.attractions.field_is_active_hint'))
                            ->default(true),
                        Toggle::make('is_special')
                            ->label(__('admin.attractions.field_is_special'))
                            ->helperText(__('admin.attractions.field_is_special_hint'))
                            ->default(false),
                        // Complemento vendible vinculado (#228): solo addons vendibles. Si se
                        // elige uno que NO está enganchado a una entrada vendible de la zona de
                        // la atracción, se avisa (en la landing degradaría a card informativa).
                        Select::make('ticket_type_id')
                            ->label(__('admin.attractions.field_complement'))
                            ->helperText(fn (Get $get, $state): string => self::complementWarning(
                                $get('zone_id') ? (int) $get('zone_id') : null,
                                $state ? (int) $state : null,
                            ) ?? __('admin.attractions.field_complement_hint'))
                            ->options(fn (): array => TicketType::query()
                                ->ofType(TicketType::TYPE_ADDON)
                                ->sellable()
                                ->orderBy('position')
                                ->get()
                                ->mapWithKeys(fn (TicketType $addon): array => [$addon->id => (string) $addon->tr('name')])
                                ->all())
                            ->nullable()
                            ->native(false)
                            ->searchable()
                            ->live()
                            ->columnSpanFull(),
                    ]),

                Tabs::make('translations')->tabs([
                    self::translatableTab('es', __('admin.attractions.lang.es')),
                    self::translatableTab('en', __('admin.attractions.lang.en')),
                    self::translatableTab('fr', __('admin.attractions.lang.fr')),
                ]),
            ]);
    }

    /**
     * Aviso si el complemento elegido NO será comprable en la landing de esta zona — porque no
     * tiene precio, o no está enganchado como complemento DE PAGO (`is_included=false`) a una
     * entrada vendible de esta zona (coherencia #226). En ese caso la landing lo mostraría solo
     * informativo. Estático porque `configure()` es estático (sin `$this`). null = todo coherente.
     */
    private static function complementWarning(?int $zoneId, ?int $addonId): ?string
    {
        if (! $addonId || ! $zoneId) {
            return null;
        }

        $addon = TicketType::find($addonId);
        if ($addon === null || ! $addon->prices()->exists()) {
            return __('admin.attractions.complement_not_attached_warning');
        }

        $purchasable = TicketType::query()
            ->ofType(TicketType::TYPE_ENTRY)
            ->sellable()
            ->where('zone_id', $zoneId)
            ->whereHas('addons', fn (Builder $q) => $q
                ->whereKey($addonId)
                ->where('product_addons.is_included', false))
            ->exists();

        return $purchasable ? null : __('admin.attractions.complement_not_attached_warning');
    }

    private static function translatableTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("name.{$locale}")
                ->label(__('admin.attractions.field_name'))
                ->required($locale === 'es')
                ->maxLength(120),
            Textarea::make("description.{$locale}")
                ->label(__('admin.attractions.field_description'))
                ->rows(3)
                ->maxLength(2000),
            TextInput::make("age.{$locale}")
                ->label(__('admin.attractions.field_age'))
                ->maxLength(60),
            TextInput::make("badge.{$locale}")
                ->label(__('admin.attractions.field_badge'))
                ->helperText($locale === 'es' ? __('admin.attractions.field_badge_hint') : null)
                ->maxLength(30),
        ]);
    }
}
