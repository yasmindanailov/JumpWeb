<?php

namespace App\Filament\Resources\SpecialDates\Schemas;

use App\Models\RateType;
use App\Models\SpecialDate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * Fase 7.7 — Formulario de alta y edición de una fecha especial (compartido por
 * `CreateSpecialDate` y `EditSpecialDate`).
 *
 * Bloques:
 *  - **Día**: la fecha (única) y una nota interna i18n para identificarla.
 *  - **Estado del día**: cerrado o abierto. Si está **abierto**, opcionalmente una ventana
 *    horaria distinta de la semanal y una tarifa distinta de la del día de la semana. Si
 *    está **cerrado**, esos campos se ocultan (y el trait los anula al guardar).
 *
 * La normalización (nota i18n, limpiar ventana/tarifa de un día cerrado, validar
 * cierre>apertura) vive en el trait `InteractsWithSpecialDateForm`, no aquí.
 */
class SpecialDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::daySection(),
                self::stateSection(),
            ]);
    }

    /**
     * Gestionar el ESTADO operativo del día (cerrar el parque / horario especial) es competencia
     * de FRANJAS, no de PRECIOS → requiere `slots.manage` (auditoría Fase 1). Antes todo el recurso
     * estaba bajo `prices.manage`, así que un rol de solo-precios podía cerrar el parque.
     */
    private static function canManageSlots(): bool
    {
        return (bool) (auth()->user()?->hasPermission('slots.manage') ?? false);
    }

    private static function daySection(): Section
    {
        return Section::make(__('admin.special_dates.section_day'))
            ->schema([
                DatePicker::make('date')
                    ->label(__('admin.special_dates.field_date'))
                    ->helperText(__('admin.special_dates.date_hint'))
                    ->required()
                    ->native(false)
                    ->unique(ignoreRecord: true),

                self::noteTabs(),
            ]);
    }

    private static function noteTabs(): Tabs
    {
        return Tabs::make('note_translations')->tabs([
            self::noteTab('es', __('admin.special_dates.lang.es')),
            self::noteTab('en', __('admin.special_dates.lang.en')),
            self::noteTab('fr', __('admin.special_dates.lang.fr')),
        ]);
    }

    private static function noteTab(string $locale, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("note.{$locale}")
                ->label(__('admin.special_dates.field_note'))
                ->helperText($locale === 'es' ? __('admin.special_dates.note_hint') : null)
                ->maxLength(120),
        ]);
    }

    private static function stateSection(): Section
    {
        return Section::make(__('admin.special_dates.section_state'))
            ->schema([
                Toggle::make('is_closed')
                    ->label(__('admin.special_dates.field_is_closed'))
                    ->helperText(__('admin.special_dates.is_closed_hint'))
                    ->live()
                    ->default(false)
                    // disabled+dehydrated: un rol de solo-precios VE el valor pero no puede cerrar el
                    // parque, y al guardar se conserva (no se anula). Requiere `slots.manage`.
                    ->disabled(fn (): bool => ! self::canManageSlots())
                    ->dehydrated(),

                // Ventana y tarifa solo cuando el día está ABIERTO.
                TimePicker::make('open_time')
                    ->label(__('admin.special_dates.field_open_time'))
                    ->helperText(__('admin.special_dates.open_time_hint'))
                    ->seconds(false)
                    ->native(false)
                    ->disabled(fn (): bool => ! self::canManageSlots())
                    ->dehydrated()
                    ->visible(fn (Get $get): bool => ! $get('is_closed')),

                TimePicker::make('close_time')
                    ->label(__('admin.special_dates.field_close_time'))
                    ->helperText(__('admin.special_dates.close_time_hint'))
                    ->seconds(false)
                    ->native(false)
                    ->after('open_time')
                    ->disabled(fn (): bool => ! self::canManageSlots())
                    ->dehydrated()
                    ->visible(fn (Get $get): bool => ! $get('is_closed')),

                Select::make('rate_type_id')
                    ->label(__('admin.special_dates.field_rate_type'))
                    ->helperText(__('admin.special_dates.rate_type_hint'))
                    ->options(fn (?SpecialDate $record): array => self::rateOptions($record))
                    ->placeholder(__('admin.special_dates.rate_type_placeholder'))
                    ->visible(fn (Get $get): bool => ! $get('is_closed')),
            ]);
    }

    /**
     * Opciones del Select de tarifa: las activas. En edición, si la fecha ya referencia una
     * tarifa que entretanto se DESACTIVÓ (forma de retirarla en 7.8), se incluye igualmente
     * marcada "(inactiva)"; de lo contrario Filament añadiría un `in:` implícito y bloquearía
     * CUALQUIER guardado de esa fecha (no podrías ni editar su nota sin perder la tarifa).
     *
     * @return array<int,string>
     */
    private static function rateOptions(?SpecialDate $record): array
    {
        $rates = RateType::where('is_active', true)->orderByDesc('priority')->get();

        $currentId = $record?->rate_type_id;
        if ($currentId !== null && ! $rates->contains('id', $currentId)) {
            $current = RateType::find($currentId);
            if ($current !== null) {
                $rates->push($current);
            }
        }

        return $rates->mapWithKeys(function (RateType $rate): array {
            $label = (string) ($rate->tr('label') ?? $rate->key);
            if (! $rate->is_active) {
                $label .= ' '.__('admin.special_dates.rate_inactive_suffix');
            }

            return [$rate->id => $label];
        })->all();
    }
}
