<?php

namespace App\Domain\Identity\Services;

/**
 * Fase 7.11 — Fuente única de la ESTRUCTURA de los permisos del panel: cómo se agrupan
 * por área y cuáles son asignables desde la matriz. La EXISTENCIA en BD la siembra
 * `Database\Seeders\PermissionSeeder` (`ALL_PERMISSIONS`); este catálogo solo describe
 * la presentación y las reglas de asignación. Un test de paridad amarra que cubre
 * exactamente los mismos permisos que el seeder (sin drift).
 *
 * Las ETIQUETAS van por i18n (`admin.access.permissions.<name>`), NO por la columna
 * `permissions.label` (mono-idioma español): el panel se renderiza también en `zh_CN`
 * (#123) y la paridad es↔zh_CN es obligatoria.
 *
 * **Invariante de seguridad:** `access.manage` es admin-exclusivo — concederlo a un rol
 * no-admin permitiría auto-escalada (ese rol podría darse cualquier permiso desde aquí).
 * Por eso NO es asignable desde la matriz; en la práctica solo lo tiene el admin, vía el
 * `Gate::before` de `AppServiceProvider`.
 */
final class PermissionCatalog
{
    /**
     * Permisos que NUNCA se ofrecen como asignables en la matriz (admin-exclusivos).
     *
     * @var array<int,string>
     */
    public const ADMIN_ONLY = ['access.manage'];

    /**
     * Permisos agrupados por área, en orden de presentación. La unión de todos los grupos
     * debe coincidir con `PermissionSeeder::ALL_PERMISSIONS` (test de paridad).
     *
     * @var array<string,array<int,string>>
     */
    public const GROUPS = [
        // Operativa diaria (los que el staff trae por defecto).
        'operativa' => [
            'registrations.validate',
            'puerta.profile',
            'orders.view',
            'orders.create_manual',
            'orders.cancel',
            'orders.refund',
            'orders.edit_event_data',
            'orders.edit_guest_data',
            'orders.edit_item',
            'orders.edit_item_below_minimum',
            'orders.cancel_item',
            'orders.refund_item',
            'calendar.view',
            'users.search_minimal',
        ],
        // Gestión / configuración del parque.
        'gestion' => [
            'catalog.manage',
            'slots.manage',
            'prices.manage',
            'content.manage',
            'settings.manage',
            'users.manage',
            'users.anonymize',
            'consents.view',
            // Fase 6 · waiver (§4.6): el registro probatorio tiene permiso PROPIO y cada consulta se
            // audita. No va al staff por defecto: está fuera de toda superficie normal.
            'waiver.view',
            'reports.view',
            'reports.export',
            // La 360 del cliente en su ficha (`specs/analitica.md` §4.6, T4a): permiso PROPIO, porque junta la
            // historia comercial de una persona con su navegación; no va al staff por defecto.
            'customers.insights',
        ],
        // Sistema (incluye el admin-exclusivo `access.manage`, mostrado pero no asignable).
        'sistema' => [
            'audit.view',
            'access.manage',
        ],
    ];

    /**
     * @return array<string,array<int,string>>
     */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    /**
     * Todos los permisos declarados, en orden de grupo.
     *
     * @return array<int,string>
     */
    public static function all(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /**
     * Permisos asignables desde la matriz = todos menos los admin-exclusivos.
     *
     * @return array<int,string>
     */
    public static function assignable(): array
    {
        return array_values(array_diff(self::all(), self::ADMIN_ONLY));
    }

    /**
     * ¿Es un permiso admin-exclusivo (no asignable desde la matriz)?
     */
    public static function isAdminOnly(string $name): bool
    {
        return in_array($name, self::ADMIN_ONLY, true);
    }

    /**
     * Etiqueta i18n de un permiso (panel es/zh_CN). El nombre del permiso lleva puntos
     * (`orders.view`) y `__()` los interpretaría como anidación, así que la clave i18n usa
     * guion bajo (`orders_view`). Ver `i18nKey()`.
     */
    public static function label(string $name): string
    {
        return __('admin.access.permissions.'.self::i18nKey($name));
    }

    /**
     * Clave i18n plana del permiso (punto → guion bajo) para evitar la anidación de `__()`.
     */
    public static function i18nKey(string $name): string
    {
        return str_replace('.', '_', $name);
    }

    /**
     * Etiqueta i18n de un grupo (operativa / gestion / sistema).
     */
    public static function groupLabel(string $key): string
    {
        return __('admin.access.groups.'.$key);
    }
}
