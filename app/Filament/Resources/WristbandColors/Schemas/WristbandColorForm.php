<?php

namespace App\Filament\Resources\WristbandColors\Schemas;

use App\Domain\Booking\Models\WristbandColor;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * **Un color de pulsera** (`docs/specs/puerta-nueva.md` §4.4, la P2; D10): su frase en singular y en plural, tal como la
 * leerá el empleado en la Puerta, su color y si va en la rueda. El ORDEN de la rueda es el de la lista: se arrastra allí.
 */
class WristbandColorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('admin.wristbands.section'))
                    ->description(__('admin.wristbands.section_hint'))
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            TextInput::make('name_one')
                                ->label(__('admin.wristbands.field_name_one'))
                                ->helperText(__('admin.wristbands.field_name_one_hint'))
                                ->required()
                                ->maxLength(60),
                            TextInput::make('name_other')
                                ->label(__('admin.wristbands.field_name_other'))
                                ->helperText(__('admin.wristbands.field_name_other_hint'))
                                ->required()
                                ->maxLength(60),
                        ]),
                        Grid::make(['default' => 1, 'sm' => 2])->schema([
                            // El hex va en un `style` de la Puerta: solo `#rrggbb` (D15), y la Puerta lo vuelve a comprobar.
                            ColorPicker::make('hex')
                                ->label(__('admin.wristbands.field_hex'))
                                ->required()
                                ->regex(WristbandColor::HEX_RE),
                            Toggle::make('in_wheel')
                                ->label(__('admin.wristbands.field_in_wheel'))
                                ->helperText(__('admin.wristbands.field_in_wheel_hint'))
                                ->default(true)
                                ->inline(false),
                        ]),
                    ]),
            ]);
    }
}
