<?php

namespace Tests\Feature\Mail;

use App\Mail\ContactMessageMail;
use App\Mail\PaymentIncidentMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LOS DOS AVISOS AL EQUIPO, CON LA PLANTILLA** (la R1c de `specs/correos-rediseno.md` §4.1.4): el mensaje de contacto y la
 * incidencia de cobro dejan su HTML suelto —colores a mano, sin oscuro ni versión de texto— y pintan la plantilla del diseño
 * como todos, sin cambiar cómo se envían (sus pruebas de envío, `ContactPageTest` y `RedsysReturnHandlerTest`, intactas).
 *
 * Lo que fijan estos casos: los datos llegan a su sitio (cabecera, resguardo, aviso) en el idioma del PARQUE aunque quien
 * escribe navegara en otro, cada párrafo del mensaje es un párrafo, sin UTM (el equipo no es audiencia), con su versión de
 * texto, y los dos tipos de incidencia dicen lo suyo.
 */
class StaffMailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // El idioma del PARQUE, explícito: el de la instalación, no el de la petición.
        config(['app.locale' => 'es']);
    }

    public function test_the_contact_message_paints_the_template_in_the_language_of_the_park(): void
    {
        // Quien escribe navegaba en francés: el correo se CONSTRUYE en su petición, donde `SetLocale` pisa `app.locale`…
        app()->setLocale('fr');
        $mail = new ContactMessageMail([
            'name' => 'Ana Ruiz', 'email' => 'ana@example.com', 'phone' => '600 11 22 33', 'topic' => 'groups',
            'message' => "Somos un colegio.\nQueremos venir en mayo.", 'locale' => 'fr',
        ]);
        $this->assertNull($mail->locale, 'nada del idioma se decide al construirlo (medido: guardaba el del visitante)');

        // …y se PINTA en la cola, con el de la instalación: lo lee el operador.
        app()->setLocale('es');
        $html = $mail->render();

        $this->assertStringContainsString('data-bloque="cabecera"', $html);
        $this->assertStringContainsString('Te escribe Ana Ruiz', $html);
        $this->assertSame(1, preg_match('#<tr data-bloque="resguardo">(.*?)</tr>\s*</table>#s', $html, $resguardo));
        foreach (['Nombre', 'Ana Ruiz', 'Correo', 'ana@example.com', 'Teléfono', '600 11 22 33', 'Tema', 'Grupos y colegios'] as $dato) {
            $this->assertStringContainsString($dato, $resguardo[1], "el resguardo lleva «{$dato}»");
        }
        $this->assertSame(2, preg_match_all('#<p[^>]*>(Somos un colegio\.|Queremos venir en mayo\.)</p>#', $html), 'un párrafo por línea');
        $this->assertStringContainsString('idioma: francés', $html);
        $this->assertStringNotContainsString('utm_', $html, 'el equipo no es audiencia: sin UTM');

        $mail->assertSeeInText('Queremos venir en mayo.');
        $mail->assertSeeInText('Correo: ana@example.com');
        $this->assertSame('Grupos y colegios — Ana Ruiz', $mail->envelope()->subject, 'el tema delante (`#535`), en el idioma del parque');
        $this->assertTrue($mail->hasReplyTo('ana@example.com'));
    }

    /** CONTROL: sin teléfono ni tema, sus filas no salen —una ausencia no inventa un dato—, y el asunto es el de siempre. */
    public function test_a_contact_without_phone_nor_topic_leaves_their_rows_out(): void
    {
        $mail = new ContactMessageMail([
            'name' => 'Luis', 'email' => 'luis@example.com', 'phone' => null, 'topic' => null,
            'message' => 'Hola.', 'locale' => 'es',
        ]);

        $html = $mail->render();

        $this->assertSame(1, preg_match('#<tr data-bloque="resguardo">(.*?)</tr>\s*</table>#s', $html, $resguardo));
        $this->assertStringNotContainsString('Teléfono', $resguardo[1]);
        $this->assertStringNotContainsString('Tema', $resguardo[1]);
        $this->assertSame('Nuevo mensaje de contacto — Luis', $mail->envelope()->subject);
    }

    public function test_the_payment_incident_says_each_kind_in_the_template(): void
    {
        $datos = [
            'kind' => 'duplicate', 'action' => 'refund', 'order_id' => 7, 'order_code' => 'R-7K2P4',
            'order_status' => 'paid', 'payment_id' => 31, 'gateway_order' => '0000600031', 'source' => 'notification',
        ];

        $duplicado = new PaymentIncidentMail($datos);
        $html = $duplicado->render();
        $this->assertStringContainsString('Cobro duplicado o huérfano', $html);
        $this->assertStringContainsString('data-bloque="aviso"', $html);
        $this->assertStringContainsString('Procede una devolución manual', $html);
        $this->assertSame(1, preg_match('#<tr data-bloque="resguardo">(.*?)</tr>\s*</table>#s', $html, $resguardo));
        foreach (['R-7K2P4', 'paid', '0000600031', '31', 'notification'] as $dato) {
            $this->assertStringContainsString($dato, $resguardo[1]);
        }
        $this->assertStringNotContainsString('#C83912', $html, 'el rojo a mano de antes');
        $this->assertSame('⚠️ Incidencia de cobro (cobro duplicado/huérfano) — pedido R-7K2P4', $duplicado->envelope()->subject);
        $duplicado->assertSeeInText('devolución manual');

        // CONTROL: el otro tipo dice lo suyo y no lo del primero.
        $tarde = new PaymentIncidentMail(['kind' => 'overbooked'] + $datos);
        $html = $tarde->render();
        $this->assertStringContainsString('Cobro llegado tras caducar la reserva', $html);
        $this->assertStringContainsString('Hay que contactar al cliente', $html);
        $this->assertStringNotContainsString('Procede una devolución manual', $html);
        $this->assertSame('⚠️ Incidencia de cobro (cobro tras caducar) — pedido R-7K2P4', $tarde->envelope()->subject);
    }
}
