<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\BirthdayReminders;
use App\Domain\Platform\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * «AVÍSAME DE FECHAS», tanda A1 (`specs/avisame-de-fechas.md` §4.1, §4.2 y §4.4; `[DECIDIDO owner]` `#750`): la casilla
 * del recibo, la marca y la baja. El correo y la lista del panel son la A2.
 *
 * ⚠️ Lo que se vigila es lo que no se ve: que la casilla SOLO se ofrece con la prueba detrás (un «sí» firmado desde ese
 * recibo CON correo), que un envío de la casilla no toca «Su ficha» (la rama de la ficha leería su ausencia como
 * «vacíala»), y que la baja no escribe al abrirse (los escáneres de enlaces) y funciona sin caducidad.
 */
class AvisameDeFechasTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_checkbox_is_offered_only_after_a_yes_signed_from_the_receipt_with_an_email(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');

        // Sin firmar todavía: no hay prueba ni correo, no hay casilla.
        $this->assertStringNotContainsString('data-receipt-dates', $this->recibo($reply));

        $this->firmar($reply, ['guardian_email' => 'marta@example.com']);
        $html = $this->recibo($reply);
        $this->assertStringContainsString('data-receipt-dates', $html);
        $this->assertStringContainsString('Avísame de fechas para el cumple de mi hijo.', $html);
        // ⚠️ `\s+`: la pieza deja dos espacios entre `name` y `value` (la trampa de `@if … @endif`, spec §4.7).
        $this->assertMatchesRegularExpression('#type="checkbox"\s+name="dates"\s+value="1"(?![^>]*checked)#', $html, 'la casilla, SIN marcar');
        $this->assertLessThan(strpos($html, 'data-receipt-after'), strpos($html, 'data-receipt-dates'), 'antes de la línea de después');

        // CONTROL: el ajuste a 0 la apaga.
        Setting::query()->updateOrCreate(['key' => BirthdayReminders::WEEKS_KEY], ['value' => '0']);
        Setting::flushMemo();
        $this->assertStringNotContainsString('data-receipt-dates', $this->recibo($reply));
    }

    public function test_signed_without_an_email_there_is_nobody_to_write_to(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');
        $this->firmar($reply, ['guardian_email' => '']);

        $this->assertDatabaseHas('guardian_authorizations', ['invitation_reply_id' => $reply->getKey()]);
        $this->assertStringNotContainsString('data-receipt-dates', $this->recibo($reply));
        // Y a mano tampoco: el POST mira lo mismo que la casilla.
        $this->postJson(app(PartyInvitations::class)->receiptUrl($reply), ['dates' => '1'])->assertOk()->assertJson(['saved' => false]);
        $this->assertSame(0, BirthdayReminder::query()->count());
    }

    public function test_without_the_proof_behind_or_after_a_no_it_is_not_offered(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');
        $this->firmar($reply, ['guardian_email' => 'marta@example.com']);
        $url = app(PartyInvitations::class)->receiptUrl($reply);
        $this->assertStringContainsString('data-receipt-dates', $this->recibo($reply), 'CONTROL: firmada con correo, se ofrece');

        // La poda se lleva la firma antes que la fila: sin prueba detrás, ni casilla ni marca.
        DB::table('waiver_signatures')->where('subject_type', WaiverSignature::SUBJECT_GUEST_MINOR)->delete();
        $this->assertStringNotContainsString('data-receipt-dates', $this->recibo($reply));
        $this->postJson($url, ['dates' => '1'])->assertOk()->assertJson(['saved' => false]);

        // Un «no» no pide avisos, aunque su autorización siga firmada.
        $otra = $this->si($invitation->token, 'Lía Gil');
        $this->firmar($otra, ['guardian_email' => 'ana@example.com', 'minor_name' => 'Lía', 'minor_surname' => 'Gil']);
        $otra->forceFill(['attending' => false])->save();
        $this->postJson(app(PartyInvitations::class)->receiptUrl($otra), ['dates' => '1'])->assertOk()->assertJson(['saved' => false]);
        $this->assertSame(0, BirthdayReminder::query()->count());
    }

    public function test_ticking_creates_unticking_revokes_and_ticking_again_revives(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');
        $this->firmar($reply, ['guardian_email' => 'marta@example.com']);
        $url = app(PartyInvitations::class)->receiptUrl($reply);

        $this->postJson($url, ['dates' => '1'])->assertOk()->assertJson(['saved' => true]);
        $row = BirthdayReminder::query()->firstOrFail();
        $this->assertTrue($row->isLive());
        $this->assertSame('es', $row->locale);
        $this->assertMatchesRegularExpression('#type="checkbox"\s+name="dates"\s+value="1"\s+checked#', $this->recibo($reply), 'vuelve marcada');

        $this->postJson($url, ['dates' => '0'])->assertOk()->assertJson(['saved' => true]);
        $this->assertFalse($row->fresh()?->isLive());
        $this->assertMatchesRegularExpression('#type="checkbox"\s+name="dates"\s+value="1"(?![^>]*checked)#', $this->recibo($reply), 'de baja, vuelve SIN marcar');

        $this->travel(1)->hours();
        $this->post($url, ['dates' => '0', 'dates' => '1'])->assertRedirect();
        $vivo = $row->fresh();
        $this->assertTrue($vivo?->isLive(), 'marcar de nuevo la REVIVE');
        $this->assertSame(1, BirthdayReminder::query()->count(), 'una sola fila por autorización');
        $this->assertTrue($vivo->accepted_at->greaterThan($row->accepted_at), 'con su nueva fecha de aceptación');
    }

    public function test_a_checkbox_post_never_touches_the_sheet(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');
        $url = app(PartyInvitations::class)->receiptUrl($reply);
        $this->postJson($url, ['guest_data' => ['allergy' => 'Frutos secos']])->assertOk();
        $this->firmar($reply, ['guardian_email' => 'marta@example.com']);

        $this->postJson($url, ['dates' => '1'])->assertOk();

        $this->assertSame('Frutos secos', $reply->fresh()?->data['allergy'] ?? null, 'la casilla vació «Su ficha»');
    }

    public function test_a_reply_without_the_offer_cannot_create_the_mark(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');

        $this->postJson(app(PartyInvitations::class)->receiptUrl($reply), ['dates' => '1'])->assertOk()->assertJson(['saved' => false]);
        $this->assertSame(0, BirthdayReminder::query()->count());
    }

    public function test_the_unsubscribe_page_writes_only_with_its_button_and_never_expires(): void
    {
        ['invitation' => $invitation] = $this->mountParty();
        $reply = $this->si($invitation->token, 'Hugo Ruiz');
        $this->firmar($reply, ['guardian_email' => 'marta@example.com']);
        $this->postJson(app(PartyInvitations::class)->receiptUrl($reply), ['dates' => '1'])->assertOk();
        $row = BirthdayReminder::query()->firstOrFail();
        $baja = URL::signedRoute('birthday-reminder.unsubscribe', ['reminder' => $row->getKey()]);

        // Un año después, sigue funcionando; abrirla NO da de baja (los escáneres abren los GET).
        $this->travel(400)->days();
        $html = (string) $this->get($baja)->assertOk()->getContent();
        $this->assertStringContainsString('data-avisame-baja="ask"', $html);
        $this->assertStringContainsString('antes del cumple de Hugo', $html, 'el nombre de pila, y nada más');
        $this->assertStringNotContainsString('marta@example.com', $html);
        $this->assertTrue($row->fresh()?->isLive());

        $this->assertSame(1, preg_match('#<form method="post" action="([^"]+)"#', $html, $m));
        $this->post(html_entity_decode($m[1]))->assertRedirect();
        $this->assertFalse($row->fresh()?->isLive());
        $this->assertStringContainsString('data-avisame-baja="done"', (string) $this->get($baja)->assertOk()->getContent());

        // Sin su firma, no se abre ni se escribe.
        $this->get(route('birthday-reminder.unsubscribe', ['reminder' => $row->getKey()]))->assertForbidden();
        $this->post(route('birthday-reminder.unsubscribe.confirm', ['reminder' => $row->getKey()]))->assertForbidden();
    }

    public function test_the_next_birthday_and_the_weeks_setting(): void
    {
        $hoy = CarbonImmutable::create(2027, 3, 1);
        $this->assertSame('2028-02-29', BirthdayReminders::nextBirthday(CarbonImmutable::create(2020, 2, 29), CarbonImmutable::create(2027, 3, 1))->toDateString(), 'el 29-F, en bisiesto, el suyo');
        $this->assertSame('2027-02-28', BirthdayReminders::nextBirthday(CarbonImmutable::create(2020, 2, 29), CarbonImmutable::create(2027, 1, 10))->toDateString(), 'el 29-F, en año que no lo es, el 28');
        $this->assertSame('2027-03-01', BirthdayReminders::nextBirthday(CarbonImmutable::create(2019, 3, 1), $hoy)->toDateString(), 'hoy es su cumpleaños: hoy');
        $this->assertSame('2028-02-15', BirthdayReminders::nextBirthday(CarbonImmutable::create(2019, 2, 15), $hoy)->toDateString(), 'ya pasó: el del año que viene');

        $this->assertSame(6, BirthdayReminders::weeks(), 'seis, como el mockup');
        Setting::query()->updateOrCreate(['key' => BirthdayReminders::WEEKS_KEY], ['value' => '99']);
        Setting::flushMemo();
        $this->assertSame(BirthdayReminders::WEEKS_MAX, BirthdayReminders::weeks());
        Setting::query()->updateOrCreate(['key' => BirthdayReminders::WEEKS_KEY], ['value' => '0']);
        Setting::flushMemo();
        $this->assertFalse(BirthdayReminders::enabled());
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    private function si(string $token, string $nino): InvitationReply
    {
        $this->post(route('invitation.reply', ['token' => $token]), ['child_name' => $nino, 'attending' => '1'])->assertRedirect();

        return InvitationReply::query()->where('child_name', $nino)->latest('id')->firstOrFail();
    }

    private function recibo(InvitationReply $reply): string
    {
        return (string) $this->get(app(PartyInvitations::class)->receiptUrl($reply))->assertOk()->getContent();
    }

    /** Firma desde el recibo, con el formulario que el recibo pinta (su acción firmada, atada a la respuesta). */
    private function firmar(InvitationReply $reply, array $overrides): void
    {
        $this->assertSame(1, preg_match('#<form method="post" action="([^"]+)"[^>]*data-receipt-firma#', $this->recibo($reply), $m), 'el recibo no ofrece la firma');
        $this->post(html_entity_decode($m[1]), array_merge([
            'document_id' => LegalDocumentVersion::query()->orderByDesc('id')->firstOrFail()->getKey(),
            'accept_waiver' => '1',
            'invitation_reply_id' => (string) $reply->getKey(),
            'minor_name' => 'Hugo', 'minor_surname' => 'Ruiz', 'minor_born_on' => now()->subYears(7)->toDateString(),
            'guardian_name' => 'Marta Ruiz Díaz', 'guardian_relationship' => 'mother', 'guardian_phone' => '600111222',
        ], $overrides))->assertRedirect();
        $this->assertTrue(GuardianAuthorization::query()->where('invitation_reply_id', $reply->getKey())->exists(), 'no se firmó');
    }
}
