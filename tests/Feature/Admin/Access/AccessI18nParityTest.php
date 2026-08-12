<?php

namespace Tests\Feature\Admin\Access;

use App\Support\PermissionCatalog;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Fase 7.11 — Paridad i18n es↔zh_CN del subárbol `admin.access.*` (el panel se renderiza en
 * ambos idiomas, #123). Cubre las etiquetas de los 22 permisos, los 3 grupos y los textos de
 * la pantalla y de la acción de gestionar roles.
 */
class AccessI18nParityTest extends TestCase
{
    /** @return array<int,string> */
    private function requiredKeys(): array
    {
        $keys = [
            'admin.access.nav_label',
            'admin.access.model_label_singular',
            'admin.access.model_label_plural',
            'admin.access.edit_title',
            'admin.access.col_role',
            'admin.access.col_name',
            'admin.access.col_permissions',
            'admin.access.all_permissions',
            'admin.access.section_identity',
            'admin.access.field_name',
            'admin.access.field_name_hint',
            'admin.access.field_display',
            'admin.access.admin_notice_title',
            'admin.access.admin_notice',
            'admin.access.customer_notice_title',
            'admin.access.customer_notice',
            'admin.access.access_manage_admin_only',
            'admin.access.user_roles.label',
            'admin.access.user_roles.modal_heading',
            'admin.access.user_roles.modal_description',
            'admin.access.user_roles.field',
            'admin.access.user_roles.submit',
            'admin.access.user_roles.success',
            'admin.access.user_roles.blocked',
            'admin.access.user_roles.blocked_self',
            'admin.access.user_roles.blocked_last_admin',
        ];

        foreach (array_keys(PermissionCatalog::groups()) as $group) {
            $keys[] = 'admin.access.groups.'.$group;
        }

        foreach (PermissionCatalog::all() as $name) {
            $keys[] = 'admin.access.permissions.'.PermissionCatalog::i18nKey($name);
        }

        return $keys;
    }

    public function test_all_access_keys_exist_in_both_locales(): void
    {
        foreach (['es', 'zh_CN'] as $locale) {
            foreach ($this->requiredKeys() as $key) {
                $this->assertTrue(
                    Lang::has($key, $locale),
                    "Falta la clave i18n «{$key}» en el idioma «{$locale}».",
                );
            }
        }
    }
}
