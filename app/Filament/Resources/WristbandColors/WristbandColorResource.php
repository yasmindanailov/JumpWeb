<?php

namespace App\Filament\Resources\WristbandColors;

use App\Domain\Booking\Models\WristbandColor;
use App\Domain\Booking\Services\WristbandWheel;
use App\Filament\Resources\WristbandColors\Pages\CreateWristbandColor;
use App\Filament\Resources\WristbandColors\Pages\EditWristbandColor;
use App\Filament\Resources\WristbandColors\Pages\ListWristbandColors;
use App\Filament\Resources\WristbandColors\Schemas\WristbandColorForm;
use App\Filament\Resources\WristbandColors\Tables\WristbandColorTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * **Los colores de las pulseras del parque** (`docs/specs/puerta-nueva.md` §4.4, la P2; D10).
 *
 * Una lista ORDENADA: cada color con la frase que lee el empleado en singular y en plural y su hex. Los que van «en la
 * rueda» se reparten las horas, en el orden de la lista, desde la hora del primero (dos ajustes de «Avanzado → Puerta»,
 * {@see WristbandWheel}); los demás, como color FIJO de un producto (la ilimitada, un
 * cumpleaños, en la sección «En la puerta» del catálogo).
 *
 * Con el permiso del catálogo (`catalog.manage`): quien da de alta los productos les pone su color. Fuera del menú, en
 * «Ajustes → Ventas», junto a las zonas.
 */
class WristbandColorResource extends Resource
{
    protected static ?string $model = WristbandColor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $slug = 'pulseras';

    public static function getNavigationLabel(): string
    {
        return __('admin.wristbands.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.wristbands.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.wristbands.model_label_plural');
    }

    public static function form(Schema $schema): Schema
    {
        return WristbandColorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WristbandColorTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWristbandColors::route('/'),
            'create' => CreateWristbandColor::route('/create'),
            'edit' => EditWristbandColor::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('catalog.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return self::canViewAny();
    }

    public static function canCreate(): bool
    {
        return self::canViewAny();
    }

    public static function canEdit($record): bool
    {
        return self::canViewAny();
    }

    /**
     * Un color que lleva FIJO algún producto no se borra: la FK es `nullOnDelete` y esos productos pasarían a la rueda sin
     * que nadie lo pidiera. Primero se le quita al producto.
     */
    public static function canDelete($record): bool
    {
        return $record instanceof WristbandColor
            && self::canViewAny()
            && ! $record->ticketTypes()->exists();
    }

    /** Fuera del menú lateral: es de puesta en marcha y se entra por «Ajustes» (`AdminSettingsHub`), como las zonas. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
