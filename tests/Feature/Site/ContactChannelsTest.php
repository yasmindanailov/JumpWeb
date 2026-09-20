<?php

namespace Tests\Feature\Site;

use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * **`<x-site.contact-channels>` — los canales de contacto, una tarjeta por cada uno que la instalación
 * tenga** (`DECISIONES #535`; re-alojado en F5 · T2b, `#654`).
 *
 * ❗❗ **Es un COMPONENTE del producto y se prueba como tal.** Hasta la mudanza de `/contacto` sus dos
 * reglas solo las vigilaba el HTML de la página de PlayJump (`Landing/ContactPageTest`), y esa página ya
 * no está en el producto. Un componente se renderiza solo, sin página que lo envuelva: así la guarda no
 * depende de ninguna landing —ni de la de un cliente ni del anfitrión mínimo—.
 *
 * ⚠️ `$site` lo reparte el composer global y lo MEMOIZA en `request()->attributes`: entre dos renders del
 * mismo caso hay que vaciarlos, o el segundo vería los ajustes del primero y el caso pasaría en verde
 * sin haber medido el cambio.
 */
class ContactChannelsTest extends TestCase
{
    use RefreshDatabase;

    private function ajuste(string $clave, string $valor): void
    {
        Setting::updateOrCreate(['key' => $clave], ['value' => $valor, 'group' => 'contact']);
    }

    private function render(): string
    {
        Cache::flush();
        Setting::flushMemo();
        request()->attributes->replace([]);

        return (string) $this->blade('<x-site.contact-channels />');
    }

    /** Un canal sin dato no se anuncia; sin ninguno, el bloque entero desaparece. */
    public function test_channels_come_from_the_panel_and_an_empty_one_is_not_painted(): void
    {
        $this->ajuste('contact.phone', '+34 968 47 12 30');
        $this->ajuste('contact.email', 'hola@ejemplo.es');

        $html = $this->render();
        $this->assertStringContainsString('+34 968 47 12 30', $html, 'el teléfono del panel no se pinta');
        $this->assertStringContainsString('href="tel:+34968471230"', $html, 'el `tel:` no va saneado a dígitos');
        $this->assertStringContainsString('hola@ejemplo.es', $html, 'el correo del panel no se pinta');

        $this->ajuste('contact.phone', '');
        $this->ajuste('contact.email', '');

        $this->assertStringNotContainsString('channels__title', $this->render(), 'el bloque de canales se pinta vacío: sin ningún dato no hay nada que ofrecer');
    }

    /**
     * ❗❗ **EL MISMO NÚMERO ES UNA TARJETA, NO DOS.** Es la respuesta que el dato da a la pregunta que el
     * canvas le hacía al owner: con dos tarjetas, la página repetiría el mismo número bajo dos rótulos
     * distintos, que es justo lo que hace creer que son dos números.
     *
     * ⚠️ El control es la otra mitad: con números DISTINTOS tienen que salir las dos.
     */
    public function test_one_card_when_phone_and_whatsapp_are_the_same_number(): void
    {
        $this->ajuste('contact.phone', '+34 968 47 12 30');
        $this->ajuste('contact.email', 'hola@ejemplo.es');
        $this->ajuste('contact.whatsapp', '+34 968 47 12 30');

        // Dos tarjetas: la del número compartido y la del correo — que también es un canal.
        $this->assertSame(
            2,
            substr_count($this->render(), 'class="channel__value"'),
            'el mismo número sale en dos tarjetas: teléfono y WhatsApp comparten número y deben compartir tarjeta',
        );

        // CONTROL: números distintos → dos tarjetas de número + la del correo.
        $this->ajuste('contact.whatsapp', '+34 600 11 22 33');

        $this->assertSame(
            3,
            substr_count($this->render(), 'class="channel__value"'),
            'con números distintos tienen que salir las dos tarjetas de número, más la del correo',
        );
    }
}
