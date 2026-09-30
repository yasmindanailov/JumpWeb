<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Notifications\Support\MailPreviews;
use App\Notifications\Support\MailTextCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * LA VISTA PREVIA DE LOS TEXTOS (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): el correo DE VERDAD, con el caso real
 * más reciente y el borrador del panel, sin dejar nada. Lo que calla al romperse: un correo del catálogo sin constructor (el
 * panel diría «no se ha podido pintar» para siempre), un borrador que no llega o que se queda, y una fila que un `toMail()`
 * escribe y la vista previa no deshace.
 */
class MailPreviewsTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_every_mail_of_the_catalog_has_how_to_be_built(): void
    {
        $this->assertSame([], array_values(array_diff(array_keys(MailTextCatalog::CORREOS), array_keys(MailPreviews::constructores()))));
        $this->assertSame([], array_values(array_diff(array_keys(MailPreviews::constructores()), array_keys(MailTextCatalog::CORREOS))));
    }

    public function test_without_a_real_case_it_says_which_one_is_missing(): void
    {
        $yo = User::factory()->create();

        $this->assertSame(['motivo' => 'pedido'], MailPreviews::pintar('order_confirmation', 'es', [], $yo));
        $this->assertSame(['motivo' => 'fiesta'], MailPreviews::pintar('guest_form_request', 'es', [], $yo));
        $this->assertSame(['motivo' => 'firma'], MailPreviews::pintar('guardian_authorization_signed', 'es', [], $yo));
        $this->assertSame(['motivo' => 'encuesta'], MailPreviews::pintar('survey_invitation', 'es', [], $yo));
        // CONTROL: un correo que no necesita caso, sí.
        $this->assertArrayHasKey('html', MailPreviews::pintar('login_code', 'es', [], $yo));
    }

    public function test_every_mail_is_painted_with_a_real_case_and_leaves_nothing_behind(): void
    {
        ['host' => $host] = $this->mountParty();
        $antes = $this->filas();

        $sinCaso = [];
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            $r = MailPreviews::pintar($correo, 'es', [], $host);
            if (! isset($r['html'])) {
                $sinCaso[$correo] = $r['motivo'];

                continue;
            }
            $this->assertStringContainsString('<html', $r['html'], $correo);
        }

        // Sin firma ni encuesta en esta fiesta: solo esos dos dicen su motivo; ninguno revienta.
        $this->assertSame(['guardian_authorization_signed' => 'firma', 'survey_invitation' => 'encuesta'], $sinCaso);
        $this->assertSame($antes, $this->filas(), 'la vista previa deshace lo que un toMail() escribe');
    }

    public function test_the_draft_is_painted_in_its_language_and_dark_is_the_outlook_one(): void
    {
        ['host' => $host] = $this->mountParty();

        $es = MailPreviews::pintar('order_confirmation', 'es', ['emails.order_confirmation.intro' => 'BORRADOR {code}'], $host);
        $this->assertStringContainsString('BORRADOR R-', $es['html'] ?? '');
        // (El claro ya NOMBRA `data-ogsc` en sus reglas para Outlook.com: lo que cambia en oscuro es la etiqueta `<html>`.)
        $this->assertStringNotContainsString('<html data-ogsc', $es['html'] ?? '');

        // En otro idioma, el correo EN ese idioma con su borrador; el español no se cuela. Y la página sigue en el suyo.
        $idioma = app()->getLocale();
        $en = MailPreviews::pintar('order_confirmation', 'en', ['emails.order_confirmation.intro' => 'DRAFT {code}'], $host, oscuro: true);
        $this->assertNotSame('en', $idioma, 'el caso necesita otro idioma en la petición');
        $this->assertSame($idioma, app()->getLocale());
        $this->assertStringContainsString('DRAFT R-', $en['html'] ?? '');
        $this->assertStringNotContainsString('BORRADOR', $en['html'] ?? '');
        $this->assertStringContainsString('<html data-ogsc data-ogsb', $en['html'] ?? '');
        // Y tras pintar, el traductor vuelve a lo guardado (nada).
        $this->assertStringNotContainsString('BORRADOR', trans('emails.order_confirmation.intro', ['code' => 'R-1'], 'es'));
    }

    /** @return array<string, int> filas por tabla de lo que un correo podría escribir */
    private function filas(): array
    {
        $out = [];
        foreach (['party_invitations', 'email_sends', 'audit_logs', 'mail_texts', 'orders', 'order_items'] as $tabla) {
            $out[$tabla] = DB::table($tabla)->count();
        }

        return $out;
    }
}
