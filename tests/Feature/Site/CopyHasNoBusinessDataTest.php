<?php

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * **El copy del producto no lleva datos de negocio** (auditoría M5, `[DECIDIDO owner]`; re-alojado en
 * F5 · T2b, `#654`). «Parking gratis 2h» era un dato del parque escrito en `lang/`, y la FAQ del panel ya
 * lo dice: lo que es de una instalación va en su panel o en su instancia, nunca en el diccionario del
 * producto. Hasta la mudanza de `/contacto` lo vigilaba `Landing/ContactPageTest` junto con el HTML de la
 * página; el HTML se fue con la vista y esta mitad, que es del producto, se queda.
 */
class CopyHasNoBusinessDataTest extends TestCase
{
    public function test_the_landing_copy_carries_no_parking_key(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            $copy = (string) file_get_contents(base_path("lang/{$locale}/landing.php"));

            $this->assertDoesNotMatchRegularExpression(
                "/'parking'\s*=>/",
                $copy,
                "`lang/{$locale}/landing.php` vuelve a llevar la clave `parking`: es un dato de negocio y va en el panel o en la instancia.",
            );
        }
    }
}
