<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\EditRole;
use App\Filament\Resources\Roles\Pages\ListRoles;
use App\Filament\Resources\Roles\Schemas\RoleForm;
use App\Filament\Resources\Roles\Tables\RoleTable;
use App\Models\Role;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 7.11 — Gestión de roles y permisos. Hace editable desde el panel la matriz que
 * hasta ahora solo se sembraba en código (`RoleSeeder`/`PermissionSeeder`): qué permisos
 * tiene cada rol. El *enforcement* ya es data-driven (`User::hasPermission()` lee el pivote
 * `permission_role` en vivo), así que cambiar la matriz aquí surte efecto al instante.
 *
 * **Acceso solo admin** vía `access.manage` (permiso sembrado hasta ahora sin uso, que esta
 * entrega activa; en la práctica solo el admin lo tiene, vía `Gate::before`). Vive en el
 * cluster «Configuración».
 *
 * **Alcance:** listar + editar la matriz. SIN crear/borrar — los 3 roles base
 * (`admin`/`staff`/`customer`) son espina dorsal del código (`name` hardcodeado en Gate,
 * middleware, `canAccessPanel`, registro de clientes, badges, claves i18n) → inmutables y
 * no borrables. Crear roles personalizados queda para una iteración futura (exigiría
 * reescribir `canAccessPanel`). La asignación de roles A USUARIOS vive en la ficha del
 * usuario (`ViewUser`), no aquí.
 */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static ?string $slug = 'roles';

    // Tras Usuarios (100).
    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav_groups.sistema');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.access.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('admin.access.model_label_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.access.model_label_plural');
    }

    /** El conteo de permisos se muestra en la tabla → eager-load para evitar N+1. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('permissions');
    }

    public static function form(Schema $schema): Schema
    {
        return RoleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RoleTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }

    // ─── Autorización: solo admin (Gate::before) vía `access.manage` ──────────

    public static function canViewAny(): bool
    {
        return self::canManageAccess();
    }

    public static function canView($record): bool
    {
        return self::canManageAccess();
    }

    public static function canEdit($record): bool
    {
        return self::canManageAccess();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canManageAccess();
    }

    public static function canCreate(): bool
    {
        return false;  // los 3 roles base son constantes de código; sin alta en este alcance
    }

    public static function canDelete($record): bool
    {
        return false;  // roles base inmutables (name hardcodeado en ~10 sitios)
    }

    private static function canManageAccess(): bool
    {
        return auth()->user()?->hasPermission('access.manage') ?? false;
    }
}
