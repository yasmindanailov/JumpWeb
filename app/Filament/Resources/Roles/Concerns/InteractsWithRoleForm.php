<?php

namespace App\Filament\Resources\Roles\Concerns;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Services\PermissionCatalog;
use App\Domain\Platform\Services\AuditLogger;

/**
 * Fase 7.11 — Mecánica compartida de la matriz de permisos de un rol.
 *
 * Los campos `permissions_<grupo>` del form NO son columnas del modelo `Role` (la relación
 * es el pivote `permission_role`), así que:
 *  - al HIDRATAR (`mutateFormDataBeforeFill`) volcamos los permisos del rol a esos campos;
 *  - al GUARDAR (`mutateFormDataBeforeSave`) los EXTRAEMOS de `$data` (para que no lleguen al
 *    `update()` del modelo y revienten por columna inexistente) y los capturamos;
 *  - tras guardar (`afterSave`) sincronizamos el pivote con guardas + audit con diff.
 */
trait InteractsWithRoleForm
{
    /**
     * Vuelca los permisos del rol (pivote) a los campos `permissions_<grupo>` del form.
     * Solo se muestran los del propio grupo (intersección) → el estado casa con las opciones
     * del CheckboxList y no dispara "el campo seleccionado no es válido" (#156).
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function hydratePermissionMatrix(array $data): array
    {
        /** @var Role $role */
        $role = $this->record;
        $assigned = $role->permissions()->pluck('name')->all();

        foreach (PermissionCatalog::groups() as $group => $names) {
            $data['permissions_'.$group] = array_values(array_intersect($names, $assigned));
        }

        return $data;
    }

    /**
     * Saca las claves `permissions_<grupo>` de `$data` (no son columnas del modelo) y
     * devuelve [data sin esas claves, lista plana de names seleccionados y SANEADA].
     *
     * Robustez:
     *  - Cada grupo presente se intersecta con LOS NOMBRES DE SU PROPIO GRUPO → no se puede
     *    colar un permiso de otro grupo por un campo equivocado.
     *  - Si un grupo NO viene en el payload (petición parcial/forjada — el flujo normal de
     *    Livewire envía siempre los 3), se PRESERVA lo que el rol ya tenía en ese grupo en vez
     *    de borrarlo (el `sync()` reemplaza el conjunto entero).
     *  - El conjunto final se intersecta con `assignable()` → descarta `access.manage` y nombres
     *    inventados.
     *
     * @param  array<string,mixed>  $data
     * @return array{0: array<string,mixed>, 1: array<int,string>}
     */
    protected function extractPermissionMatrix(array $data): array
    {
        $currentAssigned = $this->record !== null
            ? $this->record->permissions()->pluck('name')->all()
            : [];

        $selected = [];

        foreach (PermissionCatalog::groups() as $group => $groupNames) {
            $key = 'permissions_'.$group;

            if (array_key_exists($key, $data)) {
                $submitted = is_array($data[$key]) ? array_map('strval', $data[$key]) : [];
                $selected = array_merge($selected, array_intersect($submitted, $groupNames));
            } else {
                // Grupo ausente del payload → preserva lo que el rol ya tenía en ese grupo.
                $selected = array_merge($selected, array_intersect($currentAssigned, $groupNames));
            }

            unset($data[$key]);
        }

        $selected = array_values(array_intersect($selected, PermissionCatalog::assignable()));

        return [$data, $selected];
    }

    /**
     * Sincroniza el pivote `permission_role` del rol con los names seleccionados (ya saneados)
     * y registra un audit con el diff, solo si hubo cambios.
     *
     * Los roles `admin` (super-admin vía Gate::before) y `customer` (no accede al panel) tienen
     * la matriz como informativa → no se tocan nunca.
     *
     * @param  array<int,string>  $selectedNames
     */
    protected function syncPermissionMatrix(array $selectedNames): void
    {
        // Re-lee el rol fresco: el name es inmutable desde la UI, pero si se hubiera renombrado
        // a admin/customer por otra vía entre la carga y el guardado, el no-op debe respetarse
        // contra el estado REAL de BD, no contra la instancia en memoria.
        $role = $this->record->fresh();

        if ($role === null || in_array($role->name, ['admin', 'customer'], true)) {
            return;
        }

        $before = $role->permissions()->pluck('name')->sort()->values()->all();

        $ids = Permission::whereIn('name', $selectedNames)->pluck('id');
        $role->permissions()->sync($ids);

        $after = $role->fresh()?->permissions()->pluck('name')->sort()->values()->all() ?? [];

        $added = array_values(array_diff($after, $before));
        $removed = array_values(array_diff($before, $after));

        if ($added !== [] || $removed !== []) {
            AuditLogger::log('access.role_permissions_updated', $role, [
                'role' => $role->name,
                'added' => $added,
                'removed' => $removed,
            ]);
        }
    }
}
