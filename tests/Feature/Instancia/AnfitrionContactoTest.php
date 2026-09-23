<?php

namespace Tests\Feature\Instancia;

use App\Domain\Content\Services\ContactTopics;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Honeypot;
use App\Http\Instancia\InstanceViews;
use Database\Seeders\LandingContentSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * **El ANFITRIÓN MÍNIMO de `/contacto`** — lo que el producto sirve sin paquete de instancia (F5 · T2b,
 * `specs/paquete-de-instancia.md` §4.1 y §4.4, `DECISIONES #654`).
 *
 * ❗❗ **Es marcado del PRODUCTO, y por eso aquí SÍ se mira el HTML** (`#649`): la landing de un cliente
 * no se afirma desde el producto, pero su respaldo es del producto y tiene que FUNCIONAR —una instalación
 * recién montada sirve esto, y desde esta página se le escribe al parque el primer día—.
 *
 * ⚠️ La suite corre SIN paquete (`phpunit.xml` fija `INSTANCIA_RUTA` vacía), así que `/contacto` es esta
 * página en cualquier máquina: el gate no puede salir verde aquí y rojo allí (§4.5).
 */
class AnfitrionContactoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LandingContentSeeder::class);
        foreach ([
            'contact.phone' => '+34 968 47 12 30',
            'address.line1' => 'Ctra. de Prueba, 1',
            'address.line2' => '30000 Ciudad',
        ] as $clave => $valor) {
            Setting::updateOrCreate(['key' => $clave], ['value' => $valor, 'group' => 'contact']);
        }
        Cache::flush();
    }

    public function test_without_a_package_the_product_serves_its_own_working_contact_page(): void
    {
        config(['instancia.ruta' => null]);

        $respuesta = $this->get('/contacto')->assertOk()->assertViewIs('anfitrion.contacto');
        $html = (string) $respuesta->getContent();
        $x = $this->xpath($html);

        // El formulario ENTERO: los cinco campos que valida el controlador, el CSRF y el honeypot.
        $this->assertSame(1, $x->query('//form[@class="form contact-form"]')->length, 'no hay formulario de contacto');
        foreach (['name', 'email', 'phone', 'topic', 'message'] as $campo) {
            $this->assertSame(1, $x->query("//form//*[@name='{$campo}']")->length, "falta el campo `{$campo}`");
        }
        $this->assertSame(1, $x->query('//form//input[@name="_token"]')->length, 'el formulario no lleva CSRF');
        $this->assertSame(
            1,
            $x->query('//form//input[@name="'.Honeypot::FIELD.'"]')->length,
            'el anfitrión mínimo perdió el honeypot: el correo del parque recibiría a los bots',
        );

        // El tema no viene elegido (`#535`): la primera opción no afirma nada, y las claves son las
        // del controlador.
        $opciones = $x->query('//select[@name="topic"]/option');
        $this->assertSame(count(ContactTopics::ALL) + 1, $opciones->length);
        $this->assertSame('', (string) $opciones->item(0)?->getAttribute('value'));

        // Los DATOS: los canales del panel, los atajos del inventario y la dirección ya escrita.
        $this->assertSame(1, $x->query('//*[contains(concat(" ", normalize-space(@class), " "), " channels ")]')->length, 'sin los canales del panel');
        $this->assertGreaterThan(0, $x->query('//a[contains(concat(" ", normalize-space(@class), " "), " answers__link ")]')->length, 'sin los atajos del inventario');
        $this->assertStringContainsString('Ctra. de Prueba, 1, 30000 Ciudad', $html, 'la dirección no va escrita por `VenueAddress`');
    }

    /**
     * Un paquete que no trae `contacto` cae igual al anfitrión mínimo: la instancia viste lo que quiere
     * y lo demás sigue en pie, sin que nadie tenga que elegirlo.
     */
    public function test_a_package_without_the_page_still_falls_back_to_the_host(): void
    {
        $paquete = sys_get_temp_dir().'/instancia-sin-contacto-'.getmypid();
        File::ensureDirectoryExists($paquete.'/'.InstanceViews::SUBCARPETA);
        File::put($paquete.'/'.InstanceViews::SUBCARPETA.'/portada.blade.php', 'la portada de la instancia');

        try {
            config(['instancia.ruta' => $paquete]);
            $this->assertNotNull(app(InstanceViews::class)->registrar(), 'el paquete de prueba no se registró');

            $this->get('/contacto')->assertOk()->assertViewIs('anfitrion.contacto');
        } finally {
            File::deleteDirectory($paquete);
        }
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument;
        libxml_use_internal_errors(true);
        $doc->loadHTML($html);
        libxml_clear_errors();

        return new DOMXPath($doc);
    }
}
