<?php

namespace Tests\Feature\Account;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\User;
use App\Notifications\Support\MarketingMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **LA BAJA DE «NOVEDADES»** (la C1a de `specs/correos-rediseno.md` §4.4, `[DECIDIDO owner]` `#920`; LSSI art. 22.1): el
 * enlace del pie de cada correo comercial abre una página con UN botón, y el botón retira «Quiero recibir novedades» con su
 * prueba, como desde Mi cuenta (`AccountPrivacy::setMarketing()`).
 *
 * ⚠️ Lo que se vigila: abrirla NO da de baja (los escáneres de enlaces abren los GET); el botón sí, y sella la aceptación sin
 * borrarla; funciona siempre (sin caducidad); sin su firma no se abre ni se escribe, y la de una cuenta no vale para otra; no
 * dice el correo ni el nombre; y habla el idioma de la cuenta.
 */
class MarketingUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_writes_only_with_its_button_leaves_the_proof_and_never_expires(): void
    {
        $ana = User::factory()->create(['email' => 'ana@example.com', 'name' => 'Ana Gil Ruiz', 'marketing_opt_in' => true]);
        $aceptacion = $ana->consents()->create([
            'type' => Consent::TYPE_MARKETING, 'accepted_at' => now()->subMonth(), 'ip' => '10.0.0.1', 'version' => Consent::CURRENT_VERSION,
        ]);
        $baja = MarketingMail::unsubscribeUrl($ana);

        // Un año después, sigue funcionando; abrirla NO da de baja.
        $this->travel(400)->days();
        $html = (string) $this->get($baja)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->getContent();
        $this->assertStringContainsString('data-novedades-baja="ask"', $html);
        $this->assertStringNotContainsString('ana@example.com', $html, 'ni el correo…');
        $this->assertStringNotContainsString('Ana Gil', $html, '…ni el nombre: quien la abre puede no ser el titular');
        $this->assertTrue((bool) $ana->fresh()?->marketing_opt_in, 'abrirla no escribe');

        $this->assertSame(1, preg_match('#<form method="post" action="([^"]+)"#', $html, $m));
        $this->post(html_entity_decode($m[1]))->assertRedirect($baja);
        $this->assertFalse((bool) $ana->fresh()?->marketing_opt_in, 'el botón da de baja');
        $this->assertNotNull($aceptacion->fresh()?->revoked_at, 'con su prueba: la aceptación se SELLA…');
        $this->assertNotNull($aceptacion->fresh()?->accepted_at, '…y no se borra (justifica lo que se envió antes)');
        $this->assertStringContainsString('data-novedades-baja="done"', (string) $this->get($baja)->assertOk()->getContent());
    }

    public function test_without_its_signature_nothing_opens_and_one_account_signature_is_not_another(): void
    {
        $ana = User::factory()->create(['marketing_opt_in' => true]);
        $bea = User::factory()->create(['marketing_opt_in' => true]);

        $this->get(route('marketing.unsubscribe', ['user' => $ana->getKey()]))->assertForbidden();
        $this->post(route('marketing.unsubscribe.confirm', ['user' => $ana->getKey()]))->assertForbidden();
        // La firma de Ana sobre la dirección de Bea.
        $deAna = MarketingMail::unsubscribeUrl($ana);
        $this->get(str_replace('/novedades/'.$ana->getKey().'/', '/novedades/'.$bea->getKey().'/', $deAna))->assertForbidden();
        $this->assertTrue((bool) $bea->fresh()?->marketing_opt_in);
        $this->assertTrue((bool) $ana->fresh()?->marketing_opt_in);

        // CONTROL: con su firma, la de Bea se abre.
        $this->get(MarketingMail::unsubscribeUrl($bea))->assertOk();
    }

    public function test_the_page_speaks_the_language_of_the_account(): void
    {
        $tom = User::factory()->create(['marketing_opt_in' => true, 'locale' => 'en']);
        $lea = User::factory()->create(['marketing_opt_in' => true, 'locale' => 'fr']);
        $ana = User::factory()->create(['marketing_opt_in' => true, 'locale' => 'es']);

        $this->get(MarketingMail::unsubscribeUrl($tom))->assertOk()->assertSee('News from the park');
        $this->get(MarketingMail::unsubscribeUrl($lea))->assertOk()->assertSee('Les nouveautés du parc');
        $this->get(MarketingMail::unsubscribeUrl($ana))->assertOk()->assertSee('Las novedades del parque');
    }
}
