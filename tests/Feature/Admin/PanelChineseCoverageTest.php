<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

/**
 * **EL PANEL EN CHINO NO ENSEÑA CLAVES** (`#123`: el panel habla `es` y `zh_CN`). Una clave de `admin` que existe en español y
 * no en chino sale en el panel como CLAVE CRUDA —`admin.users.section_dependents`—, porque el respaldo (`fallback_locale`,
 * `en`) no tiene `admin.php`. Medido el 03-10: 354 de 3.840, repartidas por pedidos, catálogo, reseñas, ajustes, descargos…
 *
 * ▶ Un TRINQUETE, como la línea base de Larastan: la cifra solo BAJA. Una clave nueva sin su chino pone esto en rojo; al
 * traducir, se baja la constante (el caso lo exige, para que no quede holgura). Y la ficha del cliente, ENTERA desde el 03-10.
 */
class PanelChineseCoverageTest extends TestCase
{
    /** Las claves de `admin` que aún no tienen chino. ⚠️ Solo encoge. */
    private const SIN_TRADUCIR = 346;

    public function test_no_new_admin_text_ships_without_its_chinese(): void
    {
        $faltan = array_values(array_diff($this->claves('admin', 'es'), $this->claves('admin', 'zh_CN')));

        $this->assertLessThanOrEqual(self::SIN_TRADUCIR, count($faltan),
            'claves NUEVAS de admin sin chino (en el panel salen como clave): '.implode(', ', array_slice($faltan, -12)));
        $this->assertSame(self::SIN_TRADUCIR, count($faltan), 'han BAJADO: baja también SIN_TRADUCIR, o el trinquete se afloja');
    }

    /** La ficha del cliente, entera en chino (03-10): sus menores a cargo y «Renovar QR» salían como claves. */
    public function test_the_customer_card_is_whole_in_chinese(): void
    {
        $this->assertSame([], array_values(array_diff($this->claves('admin.users', 'es'), $this->claves('admin.users', 'zh_CN'))));
    }

    /** @return list<string> las claves de un grupo, aplanadas (`users.actions.rotate_card.label`) */
    private function claves(string $grupo, string $locale): array
    {
        $aplanar = static function (array $a, string $p = '') use (&$aplanar): array {
            $o = [];
            foreach ($a as $k => $v) {
                array_push($o, ...(is_array($v) ? $aplanar($v, $p.$k.'.') : [$p.$k]));
            }

            return $o;
        };
        $textos = trans($grupo, [], $locale);
        $this->assertIsArray($textos, "«{$grupo}» no existe en {$locale}");

        return $aplanar($textos);
    }
}
