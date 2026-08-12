<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\PermissionCatalog;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Fase 7.11 — Formulario de edición de un rol: identidad SOLO-LECTURA + matriz de permisos.
 *
 * El `name` es inmutable (espina dorsal del código) y la etiqueta legible se muestra desde
 * i18n (`admin.users.roles.<name>`, la misma que ya pintan los badges), por lo que no hay
 * campo editable de identidad.
 *
 * La matriz (un `CheckboxList` por área) solo es editable para roles distintos de
 * `admin`/`customer`: el admin lo puede todo (Gate::before) y el customer no accede al panel,
 * así que para ellos se muestra un aviso en vez de la matriz. `access.manage` aparece en el
 * grupo "Sistema" pero deshabilitado (admin-exclusivo, nunca asignable). La hidratación,
 * extracción y sync del pivote viven en `InteractsWithRoleForm`.
 */
class RoleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components(array_merge([
                self::identitySection(),
                self::adminNotice(),
                self::customerNotice(),
            ], self::matrixSections()));
    }

    private static function identitySection(): Section
    {
        return Section::make(__('admin.access.section_identity'))
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label(__('admin.access.field_name'))
                    ->helperText(__('admin.access.field_name_hint'))
                    ->disabled()
                    ->dehydrated(false),
                Placeholder::make('display_name')
                    ->label(__('admin.access.field_display'))
                    ->content(fn (?Role $record): string => $record !== null
                        ? __('admin.users.roles.'.$record->name)
                        : '—'),
            ]);
    }

    private static function adminNotice(): Section
    {
        return Section::make(__('admin.access.admin_notice_title'))
            ->visible(fn (?Role $record): bool => $record?->name === 'admin')
            ->schema([
                Placeholder::make('admin_notice')
                    ->hiddenLabel()
                    ->content(__('admin.access.admin_notice')),
            ]);
    }

    private static function customerNotice(): Section
    {
        return Section::make(__('admin.access.customer_notice_title'))
            ->visible(fn (?Role $record): bool => $record?->name === 'customer')
            ->schema([
                Placeholder::make('customer_notice')
                    ->hiddenLabel()
                    ->content(__('admin.access.customer_notice')),
            ]);
    }

    /**
     * Una Section con un CheckboxList por cada grupo de permisos. Solo visibles para roles
     * editables (ni admin ni customer).
     *
     * @return array<int, Section>
     */
    private static function matrixSections(): array
    {
        $sections = [];

        foreach (PermissionCatalog::groups() as $group => $names) {
            $sections[] = Section::make(PermissionCatalog::groupLabel($group))
                ->visible(fn (?Role $record): bool => $record !== null
                    && ! in_array($record->name, ['admin', 'customer'], true))
                ->schema([
                    CheckboxList::make('permissions_'.$group)
                        ->hiddenLabel()
                        ->options(self::optionsFor($names))
                        // `access.manage` se muestra pero nunca es marcable (admin-exclusivo).
                        ->disableOptionWhen(fn (string $value): bool => PermissionCatalog::isAdminOnly($value))
                        ->descriptions($group === 'sistema'
                            ? ['access.manage' => __('admin.access.access_manage_admin_only')]
                            : [])
                        ->columns(1)
                        ->bulkToggleable(false),
                ]);
        }

        return $sections;
    }

    /**
     * @param  array<int,string>  $names
     * @return array<string,string>
     */
    private static function optionsFor(array $names): array
    {
        $options = [];
        foreach ($names as $name) {
            $options[$name] = PermissionCatalog::label($name);
        }

        return $options;
    }
}
