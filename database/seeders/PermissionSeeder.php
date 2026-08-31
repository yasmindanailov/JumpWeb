<?php

namespace Database\Seeders;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Fase 7.0 — Matriz de permisos finos del panel admin.
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §2.4 y `docs/DECISIONES.md` #118.
 *
 * El rol `admin` NO necesita lista de permisos: el `Gate::before` en
 * `AppServiceProvider` le concede todo. `staff` recibe los permisos de operativa
 * diaria; el resto (catálogo, aforo, tarifas, contenido, settings, usuarios)
 * queda solo para admin. Cambiable desde el panel cuando se entregue la
 * sub-fase de gestión de roles (7.11 en `PLAN-FASE-7-PANEL.md` §4).
 */
class PermissionSeeder extends Seeder
{
    /**
     * Permisos asignados al rol `staff` por defecto (operativa diaria).
     *
     * @var array<int,string>
     */
    public const STAFF_DEFAULT_PERMISSIONS = [
        'registrations.validate',
        'puerta.profile',
        'orders.view',
        'orders.create_manual',
        'orders.cancel',
        'orders.refund',
        'orders.edit_event_data',
        // T3 de reservas mixtas (`specs/cumple-mixto.md` §23.5, `[DECIDIDO owner]` Q1·a): los dos
        // en `staff` por defecto — «en el parque, el operador ve las edades y los precios y
        // decide» —, revocables por rol desde la matriz.
        'orders.edit_guest_data',
        'orders.edit_item',
        'orders.edit_item_below_minimum',
        'orders.cancel_item',
        'orders.refund_item',
        'calendar.view',
        'users.search_minimal',
    ];

    /**
     * Matriz completa de permisos del panel (etiquetas para el panel cuando se
     * entregue la gestión de roles). El admin pasa por encima de todos.
     *
     * @var array<string,string>
     */
    public const ALL_PERMISSIONS = [
        // Operativa diaria — staff por defecto.
        'registrations.validate' => 'Validar registro/waiver en puerta',
        // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.6): la FICHA completa es otra cosa
        // que «¿está registrado?». Staff por defecto: es la operativa del mostrador.
        'puerta.profile' => 'Ver la ficha de puerta del cliente y registrar su visita',
        'orders.view' => 'Ver pedidos',
        'orders.create_manual' => 'Crear pedido manual (back-office)',
        'orders.cancel' => 'Cancelar pedido',
        'orders.refund' => 'Reembolsar pedido',
        'orders.edit_event_data' => 'Editar datos del evento del pedido (homenajeado, edad, notas)',
        // T3 de reservas mixtas (§23.5): permisos PROPIOS —y no dentro de `edit_item`— para poder
        // revocarlos por rol sin quitarle al rol la edición normal de reservas.
        'orders.edit_guest_data' => 'Editar los datos por invitado desde el panel',
        'orders.edit_item' => 'Editar item del pedido (fecha, cantidad, producto, datos, complementos)',
        'orders.edit_item_below_minimum' => 'Bajar un pack por debajo de su mínimo de invitados, con rastro',
        'orders.cancel_item' => 'Cancelar item suelto del pedido (con reembolso automático)',
        'orders.refund_item' => 'Reembolsar item suelto del pedido',
        'calendar.view' => 'Ver calendario unificado',
        'users.search_minimal' => 'Búsqueda mínima de usuario (RGPD-safe)',

        // Gestión — solo admin.
        'catalog.manage' => 'Gestionar catálogo (entradas, packs, complementos)',
        'slots.manage' => 'Gestionar franjas y aforo',
        'prices.manage' => 'Gestionar tarifas y precios',
        'content.manage' => 'Gestionar contenido (zonas, atracciones, FAQ, normas, páginas)',
        'settings.manage' => 'Gestionar configuración y datos fiscales',
        'users.manage' => 'Crear/editar/borrar usuarios',
        'users.anonymize' => 'Anonimizar usuario (RGPD)',
        'consents.view' => 'Ver consentimientos de usuario',
        // Fase 6 · waiver (§4.6): el registro probatorio, con permiso PROPIO y consulta auditada.
        'waiver.view' => 'Ver el registro probatorio del waiver (firmas y PDF)',
        'access.manage' => 'Gestionar roles y permisos',
        'reports.view' => 'Ver informes y exportaciones',
        'audit.view' => 'Ver registro de auditoría',
    ];

    public function run(): void
    {
        // 1) Sembrar todos los permisos (idempotente).
        foreach (self::ALL_PERMISSIONS as $name => $label) {
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        // 2) Asignar a `staff` los de operativa diaria. El admin no necesita asignación
        //    (Gate::before global). El customer no entra al panel.
        $staff = Role::where('name', 'staff')->first();
        if ($staff) {
            $ids = Permission::whereIn('name', self::STAFF_DEFAULT_PERMISSIONS)->pluck('id');
            $staff->permissions()->syncWithoutDetaching($ids);
        }
    }
}
