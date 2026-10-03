<?php

namespace Tests\Feature\Mail;

use App\Notifications\BirthdayComingNotice;
use App\Notifications\Support\BrandedMailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Tests\TestCase;

/**
 * **EL PIE COMERCIAL** (la C1 de `specs/correos-rediseno.md` §4.4, `#920`; LSSI art. 22.1): un correo comercial dice en su
 * pie por qué lo recibe y cómo darse de baja —bajo el filete, ANTES de los enlaces de siempre, como el `pie({comercial})` del
 * diseño—, en el HTML y en su versión de texto, y la baja va también en `List-Unsubscribe`. La URL, tal cual: sin la UTM que
 * llevan los demás enlaces del correo. Y una frase sin su baja no se acepta.
 */
class CommercialFooterTest extends TestCase
{
    use RefreshDatabase;

    private const BAJA = 'http://localhost/novedades/7/baja?signature=abc123';

    public function test_the_footer_says_why_and_how_to_leave_in_html_text_and_header(): void
    {
        $mail = $this->comercial()->commercial('Recibes este correo porque sí. Si no quieres más, [toca aquí](baja).', self::BAJA);
        $html = (string) $mail->render();

        $this->assertSame(1, preg_match('#<p class="pjm-muted" data-pie="comercial"[^>]*>(.*?)</p>#s', $html, $pie), 'el pie comercial');
        $this->assertStringContainsString('Recibes este correo porque sí.', $pie[1]);
        $this->assertStringContainsString('href="'.e(self::BAJA).'"', $pie[1], 'la baja, tal cual: sin UTM ni marca del envío');
        $this->assertStringContainsString('>toca aquí</a>', $pie[1]);
        // El resto del correo SÍ lleva su UTM (el control de que «sin UTM» es una decisión y no un correo sin clave).
        $this->assertStringContainsString('utm_source=email', $html);
        // En el pie, bajo su filete y antes de los enlaces de siempre.
        $this->assertLessThan(strpos($html, 'data-pie="comercial"'), strpos($html, 'data-bloque="pie"'));
        $this->assertLessThan(strpos($html, e(route('legal.privacidad'))), strpos($html, 'data-pie="comercial"'));

        $texto = $this->texto($mail);
        $this->assertStringContainsString('Si no quieres más, toca aquí ('.self::BAJA.').', $texto, 'en texto, con su dirección');
        $this->assertLessThan(strpos($texto, route('legal.privacidad')), strpos($texto, 'Recibes este correo porque sí.'));

        $this->assertSame('<'.self::BAJA.'>', $this->cabeceras($mail)->get('List-Unsubscribe')?->getBodyAsString());
    }

    /** CONTROL: un correo que no es comercial no lleva ni el pie comercial ni la cabecera. */
    public function test_a_mail_that_is_not_commercial_carries_neither(): void
    {
        $mail = $this->comercial();

        $this->assertStringNotContainsString('data-pie="comercial"', (string) $mail->render());
        $this->assertNull($this->cabeceras($mail)->get('List-Unsubscribe'));
    }

    public function test_a_footer_that_does_not_name_its_unsubscribe_is_refused(): void
    {
        $this->expectException(\LogicException::class);
        $this->comercial()->commercial('Recibes este correo porque sí. Si no quieres más, [toca aquí](otra).', self::BAJA);
    }

    /** Un correo de cuerpo en orden con su clave (la del 12): sus enlaces llevan UTM. */
    private function comercial(): BrandedMailMessage
    {
        return (new BrandedMailMessage(new BirthdayComingNotice(1, 'Vera', 8, 'noviembre', null)))
            ->hero('fiesta.cumple_mail', 'info', [], ['nombre' => 'Vera'])
            ->paragraphs('Uno.')
            ->button('Ver días libres', route('cumpleanos'));
    }

    private function texto(MailMessage $mail): string
    {
        return (string) view(BrandedMailMessage::VISTAS['text'], $mail->data())->render();
    }

    private function cabeceras(MailMessage $mail): Headers
    {
        $mensaje = new Email;
        foreach ($mail->callbacks as $callback) {
            $callback($mensaje);
        }

        return $mensaje->getHeaders();
    }
}
