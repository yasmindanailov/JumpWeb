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
     * `#320` (`[DECIDIDO owner]`) — los permisos del rol `puerta`: **solo su puesto**.
     *
     * «Crear un rol solo para la puerta, y que solo entre a la página de la puerta con el mismo
     * login.» Es el operario que valida la entrada y nada más: ni pedidos, ni calendario, ni la hoja
     * de sala. Si un parque quiere que además imprima la hoja de reserva o el resumen del día, se le
     * conceden `orders.view` / `calendar.view` **desde la matriz de roles del panel** — que para eso
     * existe: esto es lo que trae el rol de fábrica, no lo que es posible.
     *
     * ⚠️ **Y por eso NO se tocó `staff`**: el empleado de mostrador sigue operando el panel con sus
     * trece permisos. Un rol nuevo no revisa la decisión de `#294` («en el parque, el operador ve las
     * edades y los precios y decide»); le pone al lado un puesto distinto.
     *
     * @var array<int,string>
     */
    public const PUERTA_DEFAULT_PERMISSIONS = [
        'registrations.validate',
        'puerta.profile',
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
        // La analítica (`specs/analitica.md` §4.5, `#735`): `reports.view` abre el cuadro de mando —llevaba
        // sembrado desde F7.11 sin consumidor— y el CSV tiene permiso PROPIO, auditado.
        'reports.export' => 'Exportar la analítica (CSV)',
        // La 360 del cliente (`specs/analitica.md` §4.6, T4a): la historia comercial y, en el régimen
        // identificado, la navegación de UNA persona; permiso propio, fuera del staff por defecto.
        'customers.insights' => 'Ver la 360 del cliente en su ficha',
        'audit.view' => 'Ver registro de auditoría',
    ];

    public function run(): void
    {
        // 1) Sembrar todos los permisos (idempotente).
        foreach (self::ALL_PERMISSIONS as $name => $label) {
            Permission::firstOrCreate(['name' => $name], ['label' => $label]);
        }

        // 2) Asignar a `staff` los de operativa diaria y a `puerta` los suyos. El admin no necesita
        //    asignación (Gate::before global). El customer no entra al panel.
        //
        //    `syncWithoutDetaching` y no `sync`: este seeder corre también sobre instalaciones vivas,
        //    y un `sync` borraría lo que el parque haya concedido a mano desde la matriz de roles.
        $defaults = [
            'staff' => self::STAFF_DEFAULT_PERMISSIONS,
            'puerta' => self::PUERTA_DEFAULT_PERMISSIONS,
        ];

        foreach ($defaults as $name => $permissions) {
            $role = Role::where('name', $name)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching(
                    Permission::whereIn('name', $permissions)->pluck('id'),
                );
            }
        }
    }
}
