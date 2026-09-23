<?php

namespace Tests\Feature;

use App\Domain\Content\Models\Faq;
use App\Domain\Content\Services\ContactTopics;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Honeypot;
use App\Domain\Platform\Services\Turnstile;
use App\Mail\ContactMessageMail;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * **`/contacto`: la CONDUCTA del producto** — lo que el controlador le pasa a la vista, lo que hace con un
 * envío y lo que manda por correo (`specs/paquete-de-instancia.md` §4.5.bis, `DECISIONES #649`).
 *
 * ❗❗ **Aquí no se lee el HTML, y ésa es toda la partición.** El contrato entre el producto y una instancia
 * son los DATOS que recibe la vista, no el marcado que produce: este fichero afirma sobre `answers`,
 * `topics`, la sesión y el correo, y por eso sobrevive el día que la vista se vaya a su instancia. Lo que
 * mira el marcado vive en `tests/Feature/Landing/ContactPageTest` y **se muda con la vista**.
 *
 * ⚠️ Medido el 19-09 al intentar la mudanza: con la vista fuera, los casos de marcado caían en cualquier
 * máquina sin el paquete y estos aguantaban. Es lo que trazó la línea.
 */
class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    // ── Lo que recibe la vista ────────────────────────────────────────────────

    /**
     * **El controlador pasa los temas (la lista cerrada) y los atajos**, y los atajos son DATO: salen del
     * inventario de destinos, no de tres `href` escritos en la plantilla. Que la lista sea EXACTAMENTE la
     * del contrato lo vigila `InstanceViewContractTest`; aquí, lo que llevan dentro.
     */
    public function test_the_view_receives_the_topics_and_the_shortcuts_as_data(): void
    {
        $respuesta = $this->get('/contacto')->assertOk();

        $respuesta->assertViewHas('topics', ContactTopics::ALL);
        $respuesta->assertViewHas('answers', function (array $answers): bool {
            $urls = array_column($answers, 'url');

            return in_array(url('/#faq'), $urls, true) && in_array(route('precios'), $urls, true);
        });
    }

    /**
     * **Los atajos siguen al inventario**: una página en mantenimiento deja de ofrecerse aquí también, y
     * el ancla de Dudas solo se ofrece si la portada pinta la sección (sin ninguna duda en el panel, no).
     * ⚠️ Afirmado sobre `answers`, no sobre los `href` del HTML: es la misma propiedad que
     * `Landing/ContactPageTest` mira en el marcado, y ésta es la que sobrevive a la mudanza.
     */
    public function test_the_shortcuts_follow_the_inventory(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.page.precios'], ['value' => '1', 'group' => 'maintenance']);
        Cache::flush();
        Setting::flushMemo();

        $this->get('/contacto')->assertOk()->assertViewHas(
            'answers',
            fn (array $answers): bool => ! in_array(route('precios'), array_column($answers, 'url'), true),
        );

        Faq::query()->delete();
        Cache::flush();

        $this->get('/contacto')->assertOk()->assertViewHas(
            'answers',
            fn (array $answers): bool => ! in_array(url('/#faq'), array_column($answers, 'url'), true),
        );
    }

    // ── Lo que hace con un envío ──────────────────────────────────────────────

    public function test_valid_submission_is_emailed(): void
    {
        // Fase 7.5 (#180): el formulario SOLO envía email al administrador; ya no
        // persiste en BD (la bandeja de contacto se retiró, tabla eliminada).
        Mail::fake();

        $response = $this->post('/contacto', [
            'name' => 'Ana',
            'email' => 'ana@example.com',
            'phone' => '600123123',
            'message' => 'Hola, quiero información sobre grupos.',
        ]);

        $response->assertRedirect(route('contacto'));
        // El Mailable es ShouldQueue (D, #243): se encola, no se manda síncrono → assertQueued.
        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail): bool => $mail->hasReplyTo('ana@example.com')
            && $mail->contact['name'] === 'Ana');
    }

    public function test_invalid_submission_shows_errors(): void
    {
        Mail::fake();

        $response = $this->from('/contacto')->post('/contacto', [
            'name' => '',
            'email' => 'no-es-un-email',
            'message' => 'corto',
        ]);

        $response->assertRedirect('/contacto');
        $response->assertSessionHasErrors(['name', 'email', 'message']);
        Mail::assertNothingOutgoing();
    }

    /** Un tema fuera de la lista se rechaza: el valor llega de un `<select>`, o sea del cliente. */
    public function test_an_unknown_topic_is_rejected(): void
    {
        Mail::fake();
        Cache::flush();

        $this->from('/contacto')->post('/contacto', [
            'name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'lo-que-sea',
            'message' => 'Mensaje válido de prueba.',
        ])->assertSessionHasErrors(['topic']);

        Mail::assertNothingOutgoing();
    }

    /**
     * **EL TEMA VIAJA AL CORREO Y AL ASUNTO**, que es lo que se lee en la bandeja antes de abrir nada
     * (la lección de `#506`). Sin tema, el asunto es el de siempre.
     */
    public function test_the_topic_travels_to_the_email_subject(): void
    {
        Mail::fake();
        Cache::flush();

        $this->post('/contacto', [
            'name' => 'Ana', 'email' => 'ana@example.com', 'topic' => 'groups',
            'message' => 'Somos un colegio y queremos venir.',
        ])->assertRedirect(route('contacto'));

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail): bool {
            return $mail->contact['topic'] === 'groups'
                && str_contains($mail->envelope()->subject, (string) __('site.contact_topics.groups', [], (string) config('app.locale')));
        });
    }

    public function test_honeypot_blocks_spam(): void
    {
        Mail::fake();

        $this->post('/contacto', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'spam spam spam spam',
            Honeypot::FIELD => 'http://spam.example', // el honeypot relleno = bot (`#654`: un solo nombre)
        ])->assertRedirect(route('contacto'));

        Mail::assertNothingOutgoing();
    }

    public function test_contact_is_rate_limited(): void
    {
        // Auditoría Fase 1 (A4): la ruta lleva throttle:5,1 → el 6.º envío en un minuto da 429, lo que
        // corta el mail-bombing al admin (antes solo había honeypot). Cache limpia: otros tests pudieron
        // golpear la ruta y el limitador es compartido por proceso.
        Mail::fake();
        Cache::flush();
        $payload = ['name' => 'Ana', 'email' => 'ana@example.com', 'message' => 'Mensaje válido de prueba.'];

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contacto', $payload)->assertRedirect(route('contacto'));
        }
        $this->post('/contacto', $payload)->assertStatus(429);
    }

    public function test_turnstile_blocks_submission_without_token_when_enabled(): void
    {
        // Auditoría Fase 1 (A4): con claves Turnstile configuradas, un envío SIN token se descarta
        // (no se envía email). Sin claves es no-op (cubierto por los demás tests). `verify('')`
        // cortocircuita sin llamada de red.
        Mail::fake();
        Cache::flush();
        Turnstile::flushCache();
        Setting::updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key', 'group' => 'security']);
        Setting::updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key', 'group' => 'security']);

        try {
            $this->post('/contacto', ['name' => 'Ana', 'email' => 'ana@example.com', 'message' => 'Mensaje válido aquí.'])
                ->assertRedirect(route('contacto'));
            Mail::assertNothingOutgoing();
        } finally {
            Turnstile::flushCache(); // no envenenar la memoización estática de tests posteriores
        }
    }
}
