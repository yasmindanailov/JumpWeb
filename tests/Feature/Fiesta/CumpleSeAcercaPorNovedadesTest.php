<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\AccountPrivacy;
use App\Domain\Identity\Services\BirthdayReminders;
use App\Domain\Platform\Services\DisplayTime;
use App\Notifications\BirthdayComingNotice;
use App\Notifications\Support\MarketingMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\Email;
use Tests\Support\MountsAParty;
use Tests\Support\SendsThroughTheQueue;
use Tests\Support\SignsABirthdayReminder;
use Tests\TestCase;

/**
 * **EL 12 AMPLIADO** (la C1a de `specs/correos-rediseno.md` §4.4, `[DECIDIDO owner]` `#920`): «El cumple se acerca» sale
 * también a quien declaró a un menor en su cuenta y marcó «novedades», en la misma ventana que el de «Avísame de fechas».
 *
 * ⚠️ Lo que se vigila, cada cosa con su control: SOLO con «novedades», con correo y sin anonimizar; la ventana, el menor
 * activo y que no cumpla los 18; nunca a quien ya celebra aquí; UNA vez por cumpleaños (la marca antes de encolar); NUNCA
 * DOS VECES EL MISMO CUMPLE por los dos caminos, venga primero el que venga; a quien se dio de baja de «Avísame de fechas»,
 * tampoco por «novedades»; el pie de «novedades» con su baja; y que el consentimiento se relee cuando el correo SALE.
 */
class CumpleSeAcercaPorNovedadesTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;
    use SendsThroughTheQueue;
    use SignsABirthdayReminder;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_a_declared_minor_of_an_account_with_news_gets_one_mail_in_its_window_and_never_twice(): void
    {
        $ana = $this->cuenta('ana@example.com');
        $vera = $this->menor($ana, 'Vera', $this->nacidoConCumpleEn(30));
        $cumple = DisplayTime::today()->addDays(30);

        $this->enviar();

        Notification::assertSentTo($ana, BirthdayComingNotice::class, fn (BirthdayComingNotice $n): bool => $n->reminderId === null
            && $n->nombre === 'Vera' && $n->edad === 8 && $n->mes === $cumple->locale('es')->isoFormat('MMMM'));
        $this->assertSame($cumple->toDateString(), $vera->fresh()?->birthday_mail_for?->toDateString(), 'la marca, ANTES de encolar');

        // Otra pasada, otra hora: ni un correo más.
        $this->enviar();
        Notification::assertSentToTimes($ana, BirthdayComingNotice::class, 1);
    }

    public function test_only_with_news_with_an_address_and_not_anonymized(): void
    {
        $sin = $this->cuenta('sin@example.com', novedades: false);
        $vera = $this->menor($sin, 'Vera', $this->nacidoConCumpleEn(30));
        // Una cuenta suprimida tiene la casilla apagada y sus menores fuera (`RGPD-01`); aquí, la casilla encendida A PROPÓSITO:
        // lo que se mide es que el público no la toma aunque la tuviera.
        $borrada = $this->cuenta('deleted_77@'.User::ANONYMIZED_EMAIL_DOMAIN);
        $lia = $this->menor($borrada, 'Lía', $this->nacidoConCumpleEn(30));
        $sinCorreo = User::factory()->withoutEmail()->create(['marketing_opt_in' => true]);
        $leo = $this->menor($sinCorreo, 'Leo', $this->nacidoConCumpleEn(30));

        $this->enviar();
        Notification::assertNothingSent();
        // ⚠️ Y ni siquiera se ELIGIERON: sin marca. Lo que no sale lo frena también `shouldSend()` al salir, y esa segunda
        // capa taparía un público mal elegido; la marca es la prueba de la primera.
        foreach ([$vera, $lia, $leo] as $menor) {
            $this->assertNull($menor->fresh()?->birthday_mail_for, "{$menor->name}: fuera del público, sin marca");
        }

        // CONTROL: con la casilla, la de la primera sale (el menor y la ventana valían).
        $sin->forceFill(['marketing_opt_in' => true])->save();
        $this->enviar();
        Notification::assertSentToTimes($sin, BirthdayComingNotice::class, 1);
        Notification::assertNotSentTo($borrada, BirthdayComingNotice::class);
        Notification::assertNotSentTo($sinCorreo, BirthdayComingNotice::class);
    }

    public function test_outside_the_window_too_close_a_removed_minor_or_one_turning_adult_get_nothing(): void
    {
        $ana = $this->cuenta('ana@example.com');
        $this->menor($ana, 'Lejos', $this->nacidoConCumpleEn(60));
        $this->menor($ana, 'Cerca', $this->nacidoConCumpleEn(3));
        $this->menor($ana, 'Mayor', $this->nacidoConCumpleEn(30, 18));
        $this->menor($ana, 'Quitado', $this->nacidoConCumpleEn(30))->unlink();
        // CONTROL, en la misma pasada: con 17 sigue siendo el cumple de un niño.
        $this->menor($ana, 'Vera', $this->nacidoConCumpleEn(30, 17));

        $this->enviar();

        Notification::assertSentToTimes($ana, BirthdayComingNotice::class, 1);
        Notification::assertSentTo($ana, BirthdayComingNotice::class, fn (BirthdayComingNotice $n): bool => $n->nombre === 'Vera' && $n->edad === 17);
    }

    public function test_whoever_already_celebrates_here_gets_nothing(): void
    {
        // Una fiesta pagada de una cuenta, dentro del año (`MountsAParty`: dentro de unos días).
        ['order' => $order] = $this->mountParty();
        $bea = $this->cuenta('bea@example.com');
        $order->forceFill(['user_id' => $bea->getKey()])->save();
        $this->menor($bea, 'Lía', $this->nacidoConCumpleEn(30));
        // CONTROL: la cuenta de al lado, sin fiesta.
        $ana = $this->cuenta('ana@example.com');
        $this->menor($ana, 'Vera', $this->nacidoConCumpleEn(30));

        $this->enviar();

        Notification::assertNotSentTo($bea, BirthdayComingNotice::class);
        Notification::assertSentToTimes($ana, BirthdayComingNotice::class, 1);
    }

    public function test_the_same_birthday_by_the_two_ways_at_once_is_one_mail(): void
    {
        $nacio = $this->nacidoConCumpleEn(30);
        $this->pedirAviso('Vera Gil', 'ana@example.com', $nacio);
        $ana = $this->cuenta('Ana@Example.com');
        $vera = $this->menor($ana, 'Vera', $nacio);

        $this->enviar();

        // Sale el de la casilla (va primero) y la cuenta no recibe otro: el mismo correo, la misma fecha de nacimiento.
        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 1);
        Notification::assertNotSentTo($ana, BirthdayComingNotice::class);
        $this->assertNull($vera->fresh()?->birthday_mail_for, 'por la cuenta no salió nada');
    }

    public function test_a_reminder_asked_after_the_account_mail_is_not_a_second_one(): void
    {
        $nacio = $this->nacidoConCumpleEn(30);
        $ana = $this->cuenta('ana@example.com');
        $this->menor($ana, 'Vera', $nacio);
        $this->enviar();
        Notification::assertSentToTimes($ana, BirthdayComingNotice::class, 1);

        // Después, la misma persona marca «Avísame de fechas» para la misma niña, y para otra, con otra fecha.
        $vera = $this->pedirAviso('Vera Gil', 'Ana@Example.com', $nacio);
        $this->pedirAviso('Lía Gil', 'ana@example.com', $this->nacidoConCumpleEn(35));
        $this->enviar();

        // CONTROL: la de la otra fecha sale; la de Vera, no —y queda marcada para no mirarla cada hora—.
        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 1);
        Notification::assertSentOnDemand(BirthdayComingNotice::class, fn (BirthdayComingNotice $n): bool => $n->nombre === 'Lía');
        $this->assertSame(DisplayTime::today()->addDays(30)->toDateString(), $vera->fresh()?->sent_for?->toDateString());
    }

    public function test_whoever_left_the_birthday_mails_is_not_written_by_news_either(): void
    {
        // Se dio de baja desde un 12 de «Avísame de fechas» («Si no quieres más correos como este»); la baja es del correo.
        $aviso = $this->pedirAviso('Leo Gil', 'ana@example.com', $this->nacidoConCumpleEn(200));
        app(BirthdayReminders::class)->revoke($aviso);
        $ana = $this->cuenta('ana@example.com');
        $this->menor($ana, 'Vera', $this->nacidoConCumpleEn(30));
        // CONTROL: otra cuenta, sin esa baja.
        $bea = $this->cuenta('bea@example.com');
        $this->menor($bea, 'Lía', $this->nacidoConCumpleEn(30));

        $this->enviar();

        Notification::assertNotSentTo($ana, BirthdayComingNotice::class);
        Notification::assertSentToTimes($bea, BirthdayComingNotice::class, 1);
    }

    public function test_the_dry_run_counts_it_and_marks_nothing(): void
    {
        $ana = $this->cuenta('ana@example.com');
        $vera = $this->menor($ana, 'Vera', $this->nacidoConCumpleEn(30));

        $this->artisan('birthday-reminders:send', ['--force' => true, '--dry-run' => true])
            ->expectsOutputToContain('Mandaría 1 avisos de cumple (1 por «novedades»)')
            ->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($vera->fresh()?->birthday_mail_for, 'el ensayo no marca: mañana saldría de verdad');
    }

    public function test_the_mail_to_an_account_says_news_and_leaves_news(): void
    {
        $ana = $this->cuenta('ana@example.com');
        $correo = (new BirthdayComingNotice(null, 'Vera', 8, 'noviembre', '14,95 €'))->toMail($ana);
        $html = (string) $correo->render();
        $baja = MarketingMail::unsubscribeUrl($ana);

        $this->assertSame('Vera cumple 8 en noviembre: ¿lo celebramos aquí?', $correo->subject);
        $this->assertSame(1, preg_match('#<p class="pjm-muted" data-pie="comercial"[^>]*>(.*?)</p>#s', $html, $pie), 'el pie comercial');
        $this->assertStringContainsString('porque marcaste la casilla de novedades', $pie[1]);
        $this->assertStringContainsString('href="'.e($baja).'"', $pie[1], 'la baja de «novedades» de ESTA cuenta, tal cual');
        $this->assertStringNotContainsString('Avísame de fechas', $html, 'no es el porqué de la casilla de la autorización');
        $mensaje = new Email;
        foreach ($correo->callbacks as $callback) {
            $callback($mensaje);
        }
        $this->assertSame('<'.$baja.'>', $mensaje->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());

        // Como su diseño: sin chapa, el precio en negrita y «mira los días libres» enlazado a los días.
        // ⚠️ La CLASE en un elemento, no el nombre suelto: la hoja del oscuro (`<style>`) trae la regla de todos los tonos.
        $this->assertStringNotContainsString('class="pjm-tono-info-t"', $html);
        $this->assertMatchesRegularExpression('#<strong[^>]*>Desde 14,95 € por niño</strong>#u', $html);
        $this->assertMatchesRegularExpression('#<a class="pjm-link" href="'.preg_quote(e(route('cumpleanos')), '#').'\?utm_source=email[^"]*"[^>]*>mira los días libres</a>#u', $html);

        // Y nunca a una ruta suelta: su baja es la de una cuenta.
        $this->expectException(\LogicException::class);
        (new BirthdayComingNotice(null, 'Vera', 8, 'noviembre', null))->toMail(new AnonymousNotifiable);
    }

    public function test_the_consent_is_read_again_when_the_mail_leaves_the_queue(): void
    {
        $ana = $this->cuenta('ana@example.com');
        $bea = $this->cuenta('bea@example.com');
        $correo = static fn (): BirthdayComingNotice => new BirthdayComingNotice(null, 'Vera', 8, 'noviembre', null);

        $this->assertSame(0, $this->salidasDeLaCola($ana, $correo(), static fn () => app(AccountPrivacy::class)->setMarketing($ana, false, '127.0.0.1')),
            'se dio de baja entre el comando y la cola: no sale');
        $this->assertSame(1, $this->salidasDeLaCola($bea, $correo(), static fn () => null), 'CONTROL: con la casilla, sale');
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    private function enviar(): void
    {
        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();
    }

    private function cuenta(string $correo, bool $novedades = true): User
    {
        return User::factory()->create(['email' => $correo, 'marketing_opt_in' => $novedades]);
    }

    private function menor(User $titular, string $nombre, string $nacio): Dependent
    {
        return Dependent::query()->create([
            'user_id' => $titular->getKey(), 'name' => $nombre, 'surname' => 'Gil', 'relationship' => 'mother', 'born_on' => $nacio,
        ]);
    }
}
