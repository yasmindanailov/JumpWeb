<?php

namespace App\Filament\Resources\Roles\Pages;

use App\Domain\Identity\Models\Role;
use App\Filament\Resources\Roles\Concerns\InteractsWithRoleForm;
use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Fase 7.11 — Edición de la matriz de permisos de un rol.
 *
 * Los campos `permissions_<grupo>` no son columnas del modelo: se hidratan/extraen/sincronizan
 * en `InteractsWithRoleForm`. El `update()` del modelo queda como no-op (la identidad es
 * inmutable) y todo el trabajo real ocurre en `afterSave()` sobre el pivote.
 */
class EditRole extends EditRecord
{
    use InteractsWithRoleForm;

    protected static string $resource = RoleResource::class;

    /** Names de permisos seleccionados, capturados en `mutateFormDataBeforeSave` para `afterSave`. @var array<int,string> */
    protected array $capturedPermissionNames = [];

    public function getTitle(): string|Htmlable
    {
        /** @var Role $record */
        $record = $this->record;

        return __('admin.access.edit_title', ['role' => __('admin.users.roles.'.$record->name)]);
    }

    /** Sin borrar: roles base inmutables. */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->hydratePermissionMatrix($data);
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        [$data, $this->capturedPermissionNames] = $this->extractPermissionMatrix($data);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncPermissionMatrix($this->capturedPermissionNames);
    }
}
