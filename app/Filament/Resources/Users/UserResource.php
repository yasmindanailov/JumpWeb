<?php

namespace App\Filament\Resources\Users;

use App\Domain\Identity\Models\User;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Users\Schemas\UserInfolist;
use App\Filament\Resources\Users\Tables\UsersTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Fase 7.5 — Gestión de usuarios (RGPD del día a día), decisión #180.
 *
 * Recurso de **solo lectura** (sin crear/editar/borrar): lista + ficha de detalle.
 * Toda la operativa va por acciones de la ficha (`ViewUser`):
 *  - enviar enlace de cambio de contraseña,
 *  - **anonimizar** (RGPD, `User::anonymize()`),
 * y la **vista de consentimientos** como sección de la ficha (gateada por
 * `consents.view`).
 *
 * Acceso **solo admin** (`users.manage`): el staff no ve este recurso ni el grupo
 * "Administración" del sidebar. Las acciones sensibles, además, solo aplican sobre
 * cuentas de cliente (no admin/staff, ni uno mismo) — ver `ViewUser`.
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 40;   // #223 · menú plano: 4.ª de cinco

    public static function getNavigationLabel(): string
    {
        return __('admin.users.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.users.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.model_label_plural');
    }

    /**
     * Eager-load de `roles`: lo usa el predicado de acciones sensibles de la ficha
     * (`isSensitiveActionAllowed`) y el badge de rol de la tabla → evita N+1 y que
     * `visible()` lance una query por render.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('roles');
    }

    public static function infolist(Schema $schema): Schema
    {
        return UserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'view' => ViewUser::route('/{record}'),
        ];
    }

    // ─── Autorización: solo admin (Gate::before) vía `users.manage` ──────────

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermission('users.manage') ?? false;
    }

    public static function canView($record): bool
    {
        return auth()->user()?->hasPermission('users.manage') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasPermission('users.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;  // alta de cliente = invitación firmada (7.3); no se crean aquí
    }

    public static function canEdit($record): bool
    {
        return false;  // recurso de solo lectura (decisión clienta #180)
    }

    public static function canDelete($record): bool
    {
        return false;  // borrado físico NO contemplado; baja RGPD = anonimizar
    }

    // ─── Buscador del panel (#224) ───────────────────────────────────────────

    /**
     * Nombre, correo y teléfono: las tres formas en que el negocio identifica a alguien.
     *
     * ⚠️ **Esto es PII y su puerta es `users.manage`**, o sea SOLO admin — Filament exige
     * `canAccess()` antes de buscar en un recurso, así que un empleado no obtiene ni un
     * resultado de aquí (`[DECIDIDO owner, 2026-08-28]`: el empleado busca pedidos, no
     * clientes). Y no hace falta el límite con auditoría de la puerta (`SEC-05`), que existe
     * porque allí busca un rol BAJO: un admin ya puede paginar la lista entera de clientes, de
     * modo que buscarla no le concede nada que no tuviera. Si algún día se le abre al empleado,
     * eso cambia y hay que traerse el tratamiento de la puerta entero.
     *
     * Las cuentas anonimizadas siguen apareciendo, a propósito: conservan sus pedidos por
     * obligación fiscal y hay que poder llegar a ellos. Ya no llevan PII (`RGPD-01`).
     *
     * @return array<int, string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return (string) $record->name;
    }

    /** @return array<string, string> */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            __('admin.users.col_email') => (string) $record->email,
            __('admin.users.col_phone') => (string) ($record->phone ?? ''),
        ]);
    }

    /**
     * Sin el `with('roles')` de `getEloquentQuery()`: ese eager-load existe para el badge de
     * rol de la TABLA y para el predicado de acciones sensibles de la ficha, y aquí solo se
     * pintan nombre, correo y teléfono. En una caja que consulta al teclear, cargar una
     * relación que nadie mira se paga en cada pulsación.
     */
    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return static::getModel()::query();
    }
}
