<?php

namespace Tests\Feature;

use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use App\Mail\ContactMessageMail;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LandingContentSeeder::class);
    }

    public function test_contact_form_is_shown(): void
    {
        $this->get('/contacto')
            ->assertOk()
            ->assertSee('name="message"', false);
    }

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

    public function test_honeypot_blocks_spam(): void
    {
        Mail::fake();

        $this->post('/contacto', [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'message' => 'spam spam spam spam',
            'website' => 'http://spam.example', // honeypot relleno = bot
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
        $this->resetTurnstileCache();
        Setting::updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key', 'group' => 'security']);
        Setting::updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key', 'group' => 'security']);

        try {
            $this->post('/contacto', ['name' => 'Ana', 'email' => 'ana@example.com', 'message' => 'Mensaje válido aquí.'])
                ->assertRedirect(route('contacto'));
            Mail::assertNothingOutgoing();
        } finally {
            $this->resetTurnstileCache(); // no envenenar la memoización estática de tests posteriores
        }
    }

    /** Resetea la memoización estática de Turnstile (persiste entre tests del mismo proceso). */
    private function resetTurnstileCache(): void
    {
        $ref = new \ReflectionProperty(Turnstile::class, 'cache');
        $ref->setAccessible(true);
        $ref->setValue(null, null);
    }
}
