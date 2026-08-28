<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    /**
     * Claves de las pestañas. Viajan en la URL como `?tab=` (Filament las publica con
     * `#[Url(as: 'tab')]`), así que son parte del contrato: «Ajustes → Equipo» enlaza
     * a `?tab=team` y `AdminNavigationTest` lo fija.
     */
    public const TAB_CLIENTS = 'clients';

    public const TAB_TEAM = 'team';

    protected static string $resource = UserResource::class;

    /**
     * Sin acción "Crear": los clientes se dan de alta por invitación firmada
     * (Fase 7.3), no desde aquí (`UserResource::canCreate()` = false).
     */
    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Dos pestañas sobre UNA sola pantalla (#223): «Clientes» es día a día y entra por el
     * menú; «Equipo» es puesta en marcha y entra por Ajustes. Se parten aquí y no en dos
     * recursos porque la ficha, las acciones sensibles y su patrón de defensa (`ViewUser`)
     * son los mismos para las dos: duplicar el recurso duplicaría esa autorización, que es
     * justo lo que no se debe copiar.
     *
     * El corte lo dan los scopes del modelo, que leen `User::PANEL_ROLES` — la MISMA lista
     * que decide `canAccessPanel()`. Los dos lados son complementarios por construcción:
     * ninguna cuenta puede quedarse fuera de las dos pestañas ni salir en ambas.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            self::TAB_CLIENTS => Tab::make()
                ->label(__('admin.users.tabs.clients'))
                ->icon(Heroicon::OutlinedUsers)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->customers()),

            self::TAB_TEAM => Tab::make()
                ->label(__('admin.users.tabs.team'))
                ->icon(Heroicon::OutlinedShieldCheck)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->teamMembers()),
        ];
    }

    /** El día a día manda: se entra en «Clientes» salvo que la URL pida otra cosa. */
    public function getDefaultActiveTab(): string
    {
        return self::TAB_CLIENTS;
    }

    public function getTitle(): string
    {
        return $this->activeTab === self::TAB_TEAM
            ? __('admin.users.title_team')
            : __('admin.users.title_clients');
    }

    /**
     * Sin migas. La heredada decía «Usuarios › Listado» encima de un título que dice
     * «Clientes»: dos nombres para la misma pantalla, que es justo la incoherencia que
     * este rediseño va a quitar. En un índice tampoco navegan a ningún sitio — el menú
     * lateral ya marca dónde estás.
     */
    public function getBreadcrumbs(): array
    {
        return [];
    }
}
