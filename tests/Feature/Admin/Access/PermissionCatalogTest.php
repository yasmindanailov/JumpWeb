<?php

namespace Tests\Feature\Admin\Access;

use App\Domain\Identity\Services\PermissionCatalog;
use Database\Seeders\PermissionSeeder;
use Tests\TestCase;

/**
 * Fase 7.11 — `PermissionCatalog` es la fuente única de la ESTRUCTURA (grupos + asignables).
 * Estos tests amarran que no haya drift con `PermissionSeeder` (la fuente de EXISTENCIA en BD)
 * y que el invariante de seguridad (access.manage no asignable) se mantenga.
 */
class PermissionCatalogTest extends TestCase
{
    public function test_catalog_covers_exactly_the_seeded_permissions(): void
    {
        $catalog = PermissionCatalog::all();
        sort($catalog);

        $seeded = array_keys(PermissionSeeder::ALL_PERMISSIONS);
        sort($seeded);

        $this->assertSame($seeded, $catalog,
            'El catálogo de permisos debe cubrir exactamente los del seeder (sin drift).');
    }

    public function test_no_duplicate_permissions_across_groups(): void
    {
        $all = PermissionCatalog::all();

        $this->assertSame(count($all), count(array_unique($all)),
            'Ningún permiso debe aparecer en dos grupos.');
    }

    public function test_assignable_excludes_admin_only(): void
    {
        $assignable = PermissionCatalog::assignable();

        $this->assertNotContains('access.manage', $assignable,
            'access.manage NUNCA es asignable desde la matriz (admin-exclusivo).');
        $this->assertContains('access.manage', PermissionCatalog::all(),
            'pero sí está declarado en el catálogo (se muestra deshabilitado).');
        $this->assertSame(count(PermissionCatalog::all()) - 1, count($assignable));
    }

    public function test_i18n_key_replaces_dots(): void
    {
        $this->assertSame('orders_view', PermissionCatalog::i18nKey('orders.view'));
        $this->assertSame('access_manage', PermissionCatalog::i18nKey('access.manage'));
    }
}
