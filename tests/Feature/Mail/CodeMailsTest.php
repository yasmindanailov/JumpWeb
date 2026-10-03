<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Notifications\ConfirmationCode;
use App\Notifications\LoginCode;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Tests\TestCase;

/**
 * **EL CÓDIGO, EN SU BLOQUE** (la R1c de `specs/correos-rediseno.md` §4.1.4; el 8 del zip (6)): los tres correos de código
 * dicen el hecho en el titular —«Tu código para entrar»— y el código va debajo, grande y en mono, con su etiqueta y la nota
 * de su caducidad; ni chapa ni botón. Antes el código ERA el titular.
 *
 * Lo que fija cada caso, con su control: el código está en el bloque y NO en el titular; la etiqueta dice las cifras; la
 * nota, los minutos; la versión de texto lo lleva («etiqueta: código»); y lo de antes del código (para qué es) va antes.
 */
class CodeMailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sign_in_code_goes_in_its_block_under_the_headline(): void
    {
        $user = User::factory()->create();
        $mail = (new LoginCode('482913'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame(1, preg_match('#<tr data-bloque="codigo">(.*?)</tr>\s*</table>#s', $html, $bloque), 'el bloque del código');
        $this->assertStringContainsString('482-913', $bloque[1], 'el código, en dos grupos con guion');
        $this->assertStringContainsString('Código de 6 cifras', $bloque[1]);
        $this->assertStringContainsString('Vale 10 minutos', $bloque[1], 'la nota dice cuánto vale');
        $this->assertStringContainsString('ui-monospace', $bloque[1], 'en la familia mono');

        $this->assertSame(1, preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $h1));
        $this->assertSame('Tu código para entrar', trim($h1[1]), 'el titular dice el hecho; el código ya no es el titular');
        $this->assertStringNotContainsString('data-bloque="boton"', $html, 'se escribe donde se pidió: sin botón');
        // ⚠️ La CLASE en un elemento, no el nombre suelto: la hoja del oscuro (`<style>`) trae la regla de todos los tonos.
        $this->assertStringNotContainsString('class="pjm-tono-info-t"', $html, 'sin chapa, como su diseño');

        $texto = $this->texto($mail);
        $this->assertStringContainsString('Código de 6 cifras: 482-913', $texto);
        $this->assertLessThan(strpos($texto, 'Si no lo has pedido'), strpos($texto, '482-913'), 'el código, antes de «si no fuiste tú»');
    }

    public function test_the_confirmation_code_says_what_it_is_for_before_the_code(): void
    {
        $user = User::factory()->create();
        $mail = (new ConfirmationCode('104733', 'delete_account'))->toMail($user);
        $html = (string) $mail->render();

        $para = strpos($html, 'Es para borrar tu cuenta.');
        $codigo = strpos($html, 'data-bloque="codigo"');
        $this->assertNotFalse($para);
        $this->assertNotFalse($codigo);
        $this->assertLessThan($codigo, $para, 'quien lo recibe sin haberlo pedido lee primero PARA QUÉ es');
        $this->assertSame(1, preg_match('#<tr data-bloque="codigo">(.*?)</tr>\s*</table>#s', $html, $bloque));
        $this->assertStringContainsString('104-733', $bloque[1]);
    }

    public function test_the_new_email_code_goes_in_its_block_too(): void
    {
        $user = User::factory()->create(['pending_email' => 'nuevo@example.com']);
        $mail = (new VerifyPendingEmail('660021'))->toMail($user);
        $html = (string) $mail->render();

        $this->assertSame(1, preg_match('#<tr data-bloque="codigo">(.*?)</tr>\s*</table>#s', $html, $bloque));
        $this->assertStringContainsString('660-021', $bloque[1]);
        $this->assertSame(1, preg_match('#<h1[^>]*>(.*?)</h1>#s', $html, $h1));
        $this->assertSame('Confirma tu nuevo email', trim($h1[1]));
    }

    /** CONTROL: un correo SIN código no pinta el bloque (el bloque nace solo de `code()`). */
    public function test_a_mail_without_a_code_paints_no_code_block(): void
    {
        // Un correo CON chapa (la cancelación; el 5 la perdió con la R2e, como su diseño).
        $html = (string) (new BrandedMailMessage)->hero('emails.order_cancelled', 'err')->line('Uno.')->render();

        $this->assertStringNotContainsString('data-bloque="codigo"', $html);
        $this->assertStringContainsString('class="pjm-tono-error-t"', $html, 'y los que llevan chapa la siguen llevando');
    }

    private function texto(MailMessage $mail): string
    {
        return (string) view(BrandedMailMessage::VISTAS['text'], $mail->data())->render();
    }
}
