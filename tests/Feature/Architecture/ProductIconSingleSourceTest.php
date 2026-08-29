<?php

namespace Tests\Feature\Architecture;

use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Services\ProductIcon;
use Tests\TestCase;

/**
 * **EL ICONO DE UN PRODUCTO SE DECIDE EN UN SOLO SITIO** (`DECISIONES #140`).
 *
 * ⚠️⚠️ Lo decidía un booleano, escrito TRES veces: en `components/icons/product.blade.php` —que
 * además **no tenía ni un llamante**— y, con la geometría entera copiada dentro, en `CartStep.vue` y
 * en `SummaryLine.vue`. Con esa regla, un catálogo entero se reparte en **dos dibujos**: la tirolina,
 * la tarta y los calcetines son «no-pack», así que los tres salen como un ticket.
 *
 * Hermana de `LedgerSingleSourceTest` y `ZoneAccentIsNotAClassNameTest`: **prohíbe el MECANISMO**
 * —que una superficie derive el icono de `is_pack`— en vez de perseguir el síntoma.
 */
class ProductIconSingleSourceTest extends TestCase
{
    /**
     * ⚠️⚠️ **Aquí había una LISTA DE DOS FICHEROS escrita a mano, y por eso este caso no vio nada
     * durante meses** (`#259`). `CatalogStep.vue` —el catálogo, la superficie MÁS visible del
     * cajón— nunca estuvo en ella y siguió eligiendo su dibujo con `v-if="item.is_pack"`: el patrón
     * exacto que este fichero existe para prohibir, en la pantalla donde más se nota.
     *
     * ▶ El descubrimiento pasa a ser AUTOMÁTICO: todos los `.vue` del cajón, a cualquier
     * profundidad. Una lista que hay que acordarse de ampliar es una lista que envejece — es la
     * misma lección de `DECISIONES #113` y la que `SidebarIconParityTest` ya aprendió con su
     * `glob('**')` que no descendía.
     *
     * @return list<string>
     */
    private function surfaces(): array
    {
        $found = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(base_path('resources/js/sidebar'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->getExtension() === 'vue') {
                $found[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }

        sort($found);

        return $found;
    }

    /**
     * **Ninguna superficie elige el dibujo mirando `is_pack`.**
     *
     * Es el patrón exacto que había, y el que hace que elegir el icono de un producto exija tocar
     * Vue. La clave la manda el servidor (`line.icon`).
     */
    public function test_no_surface_picks_the_drawing_from_is_pack(): void
    {
        $superficies = $this->surfaces();

        // ⚠️ **Guarda de la guarda**: si el recorrido deja de descender, este caso pasaría en verde
        // mirando MENOS ficheros — que es exactamente cómo se le escapó el catálogo durante meses.
        $this->assertContains(
            'resources/js/sidebar/steps/CatalogStep.vue', $superficies,
            'el recorrido ha dejado de ver el catálogo, que es donde este patrón sobrevivió sin que nadie lo mirara',
        );
        $this->assertContains(
            'resources/js/sidebar/account/zones/OrdersZone.vue', $superficies,
            'el recorrido ha dejado de descender hasta las zonas del área de cliente',
        );

        $hallazgos = [];

        foreach ($superficies as $rel) {
            $ruta = base_path($rel);

            if (preg_match('/v-(?:if|else-if)="[^"]*is_pack[^"]*"[^>]*class="[^"]*(?:icon|tk)\b/', (string) file_get_contents($ruta), $m)) {
                $hallazgos[] = "$rel · «".trim($m[0]).'»';
            }
        }

        $this->assertSame([], $hallazgos, implode("\n", array_merge(
            ['Una superficie vuelve a elegir el icono a partir de `is_pack`:'],
            array_map(fn (string $h): string => '  · '.$h, $hallazgos),
            ['',
                '▶ Con esa regla un catálogo se reparte en DOS dibujos, y elegir otro exige tocar Vue.',
                '▶ La clave la manda el servidor (`line.icon`), resuelta por `Booking\Services\ProductIcon`.'],
        )));
    }

    /**
     * **La guarda de la guarda**: el patrón caza su propio ejemplo.
     *
     * ⚠️ Sin esto, una expresión mal escrita dejaría el test verde para siempre sin mirar nada — el
     * modo de fallo de toda guarda por `grep`, y uno que este repo ya ha pagado tres veces.
     */
    public function test_the_forbidden_pattern_actually_matches(): void
    {
        $this->assertMatchesRegularExpression(
            '/v-(?:if|else-if)="[^"]*is_pack[^"]*"[^>]*class="[^"]*(?:icon|tk)\b/',
            '<span v-if="line.is_pack" class="icon ic-b1 prod-ico" aria-hidden="true">',
            'el patrón prohibido ha dejado de cazar su propio ejemplo: la guarda es decorativa',
        );
    }

    /**
     * **Todas las claves ofrecidas EXISTEN en el sistema de diseño.**
     *
     * ⚠️ Una lista curada solo vale si lo que ofrece se puede dibujar: una clave sin componente es un
     * hueco mudo en la cesta, y el operador la habría elegido de un desplegable — o sea que el fallo
     * lo provocaría el propio panel.
     */
    public function test_every_offered_icon_exists_in_the_design_system(): void
    {
        foreach (ProductIcon::CHOICES as $clave) {
            $this->assertFileExists(
                resource_path("views/components/icons/{$clave}.blade.php"),
                "`{$clave}` se ofrece en el panel y no existe en el set de diseño",
            );
        }

        // Y los dos respaldos, que no se eligen pero se sirven.
        foreach ([ProductIcon::DEFAULT_PACK, ProductIcon::DEFAULT_OTHER] as $clave) {
            $this->assertContains($clave, ProductIcon::CHOICES, "el respaldo `{$clave}` no está en la lista ofrecida");
        }
    }

    /**
     * **Y el cajón sabe dibujar TODAS las que se ofrecen.**
     *
     * ⚠️ Ésta es la que de verdad protege, y no la cubre la paridad de iconos: aquélla comprueba que
     * lo que el cajón dibuja coincide con el set, **no que sepa dibujar todo lo que el panel deja
     * elegir**. Sin esto, añadir una opción al desplegable serviría un ticket genérico en la cesta
     * sin que nada fallara.
     */
    public function test_the_drawer_can_draw_every_offered_icon(): void
    {
        $registro = (string) file_get_contents(base_path('resources/js/sidebar/ProductIcon.vue'));

        foreach (ProductIcon::CHOICES as $clave) {
            $this->assertStringContainsString(
                "key === '{$clave}'", $registro,
                "el cajón no sabe dibujar `{$clave}`, que el panel SÍ deja elegir: serviría el respaldo genérico",
            );
        }
    }

    /** El producto resuelve su icono, y sin elegir nada conserva el aspecto que ya tenía. */
    public function test_a_product_without_a_choice_keeps_the_icon_of_its_type(): void
    {
        $pack = new TicketType(['type' => TicketType::TYPE_PACK]);
        $entrada = new TicketType(['type' => TicketType::TYPE_ENTRY]);

        $this->assertSame(ProductIcon::DEFAULT_PACK, $pack->iconKey());
        $this->assertSame(ProductIcon::DEFAULT_OTHER, $entrada->iconKey());

        // Y con elección, manda la elección — también en un producto que no es pack.
        $entrada->icon = 'socks';
        $this->assertSame('socks', $entrada->iconKey());

        // ⚠️ Defensivo: una clave corrupta —escrita saltándose el panel, o de un set anterior—
        // degrada al icono por tipo en vez de servir un `<svg>` vacío.
        $entrada->icon = 'no-existe';
        $this->assertSame(ProductIcon::DEFAULT_OTHER, $entrada->iconKey());
    }
}
