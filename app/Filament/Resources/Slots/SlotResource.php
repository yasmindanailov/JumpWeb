<?php

namespace App\Filament\Resources\Slots;

use App\Filament\Resources\Slots\Pages\EditSlot;
use App\Filament\Resources\Slots\Pages\ListSlots;
use App\Filament\Resources\Slots\Schemas\SlotForm;
use App\Filament\Resources\Slots\Tables\SlotTable;
use App\Models\Slot;
use App\Models\TicketType;
use App\Support\PackAvailability;
use App\Support\SlotAvailability;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.7 iter.3 — Gestión fina de franjas concretas (`slots`): abrir/cerrar la venta online
 * de una franja y ajustar su aforo online de forma puntual. Las franjas las crea el generador
 * (`slots:generate` / botón «Regenerar franjas»), así que aquí NO se crean ni se borran a mano:
 * solo se LISTAN y se EDITAN (toggle de venta + excepción de aforo). La limpieza de franjas
 * obsoletas se hace con «Regenerar franjas».
 *
 * **Acceso solo admin** (`slots.manage`, igual que el horario semanal y las temporadas).
 *
 * Aforo de la zona de cumpleaños: va por CUPO ({@see PackAvailability}/ajustes), no por la
 * columna `online_capacity` de la franja → en esas franjas el aforo NO es editable (solo el
 * toggle de venta). La detección es data-driven (zona que aloja packs), no por slug.
 */
class SlotResource extends Resource
{
    protected static ?string $model = Slot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedViewColumns;

    protected static ?string $slug = 'slots';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.programacion');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.slots.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.slots.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.slots.model_label_plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('zone');
    }

    public static function form(Schema $schema): Schema
    {
        return SlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlotTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlots::route('/'),
            'edit' => EditSlot::route('/{record}/edit'),
        ];
    }

    /**
     * Ids de las zonas cuyo aforo va por CUPO (alojan packs) — el aforo de sus franjas no se
     * edita por columna. Data-driven (sin slug quemado). Consulta barata (≈1 zona); se evita
     * caché estática para no filtrar entre tests.
     *
     * @return array<int,int>
     */
    public static function packZoneIds(): array
    {
        return TicketType::query()
            ->where('type', TicketType::TYPE_PACK)
            ->whereNotNull('zone_id')
            ->distinct()
            ->pluck('zone_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public static function isPackZone(Slot $slot): bool
    {
        return in_array((int) $slot->zone_id, self::packZoneIds(), true);
    }

    /**
     * Ocupación VIVA de la franja para mostrar/validar (NUNCA `seats_taken`, que está muerta):
     * para entradas, plazas ocupadas en esa franja; para packs, invitados reservados.
     */
    public static function liveOccupancy(Slot $slot): int
    {
        $date = $slot->date->toDateString();

        if (self::isPackZone($slot)) {
            [, $guests] = (new PackAvailability)->occupancyMaps($slot->zone_id, $date, [], null, $slot->zone);

            return (int) ($guests[$slot->start_time] ?? 0);
        }

        $map = (new SlotAvailability)->occupancyMap($slot->zone_id, $date);

        return (int) ($map[$slot->start_time] ?? 0);
    }

    // ─── Autorización: solo admin (Gate::before) vía `slots.manage` ─────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false; // las franjas las crea el generador, no se dan de alta a mano
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return false; // borrar a mano arriesga el cascade de order_items; se usa «Regenerar franjas»
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('slots.manage') ?? false;
    }
}
