<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\Settings as SettingsPage;
use App\Mail\ContactMessageMail;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

/**
 * **El REMITENTE de todo correo sale del PANEL, no del `.env`** (`DECISIONES #500`, T2 de
 * `specs/correos-desde-canvas.md`).
 *
 * Medido antes de construirlo: `config/mail.php` resuelve `mail.from` con `env(...)`, **nadie lo
 * sobreescribe** en todo el repo y en una instalación recién montada vale `hello@example.com`. Los
 * 23 correos salían de `JumpWeb <hello@example.com>` — el nombre del PRODUCTO y el placeholder de
 * Laravel.
 *
 * ⚠️⚠️ **AQUÍ NO SE PUEDE USAR `Mail::fake()`**, y es la trampa de este fichero: el *fake* intercepta
 * antes de construir el mensaje, así que **`MessageSending` no se dispara** y el listener no corre —
 * el caso saldría VERDE con el mecanismo desconectado. La suite envía con el transporte `array`
 * (`phpunit.xml`), que sí recorre el camino entero y guarda lo enviado.
 */
class OutgoingSenderTest extends TestCase
{
    use RefreshDatabase;

    private function enviaUnCorreo(?callable $extra = null): Email
    {
        Mail::raw('cuerpo', function ($m) use ($extra) {
            $m->to('destinatario@jumpweb.test')->subject('prueba');
            if ($extra) {
                $extra($m);
            }
        });

        $enviados = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertNotEmpty($enviados, 'el transporte de pruebas no ha guardado ningún mensaje');

        /** @var Email $email */
        $email = $enviados->last()->getOriginalMessage();

        return $email;
    }

    private function conNegocio(string $nombre, ?string $remitente = null): void
    {
        Setting::create(['key' => 'business.name', 'value' => $nombre, 'group' => 'business']);
        if ($remitente !== null) {
            Setting::create(['key' => 'mail.from_address', 'value' => $remitente, 'group' => 'contact']);
        }
        Setting::flushMemo();
    }

    public function test_the_sender_comes_from_the_panel(): void
    {
        $this->conNegocio('Negocio Demo', 'reservas@negocio-demo.test');

        $from = $this->enviaUnCorreo()->getFrom()[0];

        $this->assertSame('reservas@negocio-demo.test', $from->getAddress());
        $this->assertSame('Negocio Demo', $from->getName());
    }

    /**
     * El campo vacío es un estado LEGÍTIMO: se usa la dirección del servidor. Pero **el nombre sí
     * cambia siempre**, porque ése no depende de ningún ajuste nuevo — `business.name` ya existía.
     */
    public function test_without_the_setting_the_address_falls_back_but_the_name_is_the_business(): void
    {
        $this->conNegocio('Negocio Demo');

        $from = $this->enviaUnCorreo()->getFrom()[0];

        $this->assertSame(config('mail.from.address'), $from->getAddress());
        $this->assertSame('Negocio Demo', $from->getName());
        $this->assertNotSame(config('app.name'), $from->getName(), 'el remitente no puede ser el nombre del PRODUCTO');
    }

    /**
     * ❗ **Un ajuste mal puesto no puede impedir que un correo salga.** El panel valida con `->email()`,
     * pero un valor metido por consola o por una migración se salta esa puerta: aquí el mecanismo
     * degrada a la dirección del servidor y **el correo se envía igual**.
     */
    public function test_an_invalid_address_degrades_instead_of_breaking_the_send(): void
    {
        $this->conNegocio('Negocio Demo', 'esto-no-es-un-email');

        $from = $this->enviaUnCorreo()->getFrom()[0];

        $this->assertSame(config('mail.from.address'), $from->getAddress());
        $this->assertSame('Negocio Demo', $from->getName());
    }

    /**
     * ⚠️ Hoy **ningún correo define `from` propio** (medido en `app/Mail/` y `app/Notifications/`), así
     * que esta comprobación no cambia nada — existe para el día que alguien lo ponga, que es justo
     * cuando dejaría de ser evidente que se lo estaban pisando.
     */
    public function test_an_explicit_sender_is_never_overwritten(): void
    {
        $this->conNegocio('Negocio Demo', 'reservas@negocio-demo.test');

        $from = $this->enviaUnCorreo(fn ($m) => $m->from('propio@parque.test', 'Puesto a mano'))->getFrom()[0];

        $this->assertSame('propio@parque.test', $from->getAddress());
        $this->assertSame('Puesto a mano', $from->getName());
    }

    /**
     * ⚠️ **Con VARIOS remitentes no se toca nada, y el motivo es que `from()` REEMPLAZA.** Si el
     * mecanismo se conformara con mirar el primero, sustituirlo se llevaría por delante a los demás
     * **en silencio**. El RFC permite varios `From` (con su `Sender`), ningún correo del producto lo
     * usa hoy, y por eso la rama existe: es el caso en el que un fallo no se vería.
     *
     * ▶ Este caso lo pidió el ARNÉS, no el diseño: la mutación «deja de rendirse cuando hay varios
     * From» sobrevivía, o sea que esa rama no la vigilaba nadie.
     */
    public function test_several_senders_are_left_alone_because_replacing_would_drop_one(): void
    {
        $this->conNegocio('Negocio Demo', 'reservas@negocio-demo.test');

        $email = $this->enviaUnCorreo(function ($m): void {
            // El primero es el de config; el segundo lo añade quien envía, a propósito.
            $m->getSymfonyMessage()->addFrom(new Address('segundo@parque.test', 'Segundo'));
        });

        $this->assertCount(2, $email->getFrom(), 'no se ha perdido ningún remitente');
        $this->assertSame(config('mail.from.address'), $email->getFrom()[0]->getAddress());
        $this->assertSame('segundo@parque.test', $email->getFrom()[1]->getAddress());
    }

    /**
     * ❗❗ **Cambiar el remitente y quedarse con la respuesta son dos cosas distintas.**
     * `ContactMessageMail` pone `replyTo` con la dirección de quien escribe: si el mecanismo lo
     * tocara, el parque respondería al mensaje de un cliente **y le llegaría a sí mismo**.
     */
    public function test_the_reply_to_of_the_contact_form_is_left_alone(): void
    {
        $this->conNegocio('Negocio Demo', 'reservas@negocio-demo.test');

        Mail::to('parque@negocio-demo.test')->send(new ContactMessageMail([
            'name' => 'Quien escribe',
            'email' => 'cliente@ejemplo.test',
            'message' => 'hola',
        ]));

        /** @var Email $email */
        $email = Mail::mailer()->getSymfonyTransport()->messages()->last()->getOriginalMessage();

        $this->assertSame('reservas@negocio-demo.test', $email->getFrom()[0]->getAddress(), 'el remitente sí cambia');
        $this->assertSame('cliente@ejemplo.test', $email->getReplyTo()[0]->getAddress(), 'el Reply-To NO se toca');
    }

    /**
     * ⚠️⚠️ **La trampa de las tres listas blancas** (`#413`): un campo puede estar pintado en el
     * formulario y **no llegar a la fila** si falta en el mapa de claves gestionadas — y falla
     * callando, que es lo peor. Este caso guarda de verdad desde el panel y lee la BD.
     */
    public function test_the_field_actually_persists_from_the_panel(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@jumpweb.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('data.mail.from_address', 'reservas@negocio-demo.test')
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flushMemo();
        $this->assertSame('reservas@negocio-demo.test', Setting::value('mail.from_address'));
    }

    public function test_the_panel_rejects_something_that_is_not_an_address(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::where('email', 'admin@jumpweb.test')->firstOrFail();

        Livewire::actingAs($admin)
            ->test(SettingsPage::class)
            ->set('data.mail.from_address', 'esto-no-es-un-email')
            ->call('save')
            // ⚠️ Sin el prefijo `data.`: `assertHasFormErrors` ya lo añade él.
            ->assertHasFormErrors(['mail.from_address']);
    }
}
