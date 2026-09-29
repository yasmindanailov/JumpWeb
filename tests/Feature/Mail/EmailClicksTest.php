<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\EmailClick;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\Analytics\EmailClickMarks;
use App\Domain\Platform\Services\Analytics\EmailUtm;
use App\Domain\Platform\Services\Analytics\RouteNormalizer;
use App\Notifications\AccountAlreadyExists;
use App\Notifications\SurveyInvitation;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Los clics de cada correo** (`specs/correos-salientes.md` §4.8, la C2): la marca del envío (`jw_e`) viaja en cada enlace
 * de esta casa SOLO a una cuenta que no se opuso, con el interruptor encendido y nunca en la encuesta; al pulsar se apunta la
 * visita y la URL se limpia con un 302; el escáner se reconoce por el ritmo; la marca no llega jamás a la analítica anónima.
 */
class EmailClicksTest extends ApiTestCase
{
    // ─── La marca, al enviar ─────────────────────────────────────────────────────────────────────

    public function test_the_mark_of_the_send_travels_in_every_link_of_this_house_only_with_the_switch_on(): void
    {
        $this->switchOn();
        $user = User::factory()->create();

        $user->notify(new AccountAlreadyExists);

        $row = EmailSend::query()->sole();
        $enlaces = $this->ourLinks($this->lastSentEmail());
        $this->assertTrue($row->tracks_clicks);
        $this->assertGreaterThanOrEqual(6, count($enlaces), 'el botón, el logotipo y los cuatro del pie');
        foreach ($enlaces as $href) {
            $this->assertStringContainsString('&jw_e='.$row->send_key, $href, "un enlace de esta casa sin la marca: $href");
        }

        // El control: con el interruptor apagado, ni una marca y «no se mide».
        $this->switchOff();
        $user->notify(new AccountAlreadyExists);

        $this->assertStringNotContainsString('jw_e', (string) $this->lastSentEmail()->getHtmlBody());
        $this->assertFalse(EmailSend::query()->latest('id')->firstOrFail()->tracks_clicks);
    }

    /** Sin marca: quien se opuso a la analítica, quien no tiene cuenta y la encuesta, que es anónima (`#754`). */
    public function test_no_mark_for_who_objected_for_who_has_no_account_nor_in_the_survey(): void
    {
        $this->switchOn();

        User::factory()->create(['analytics_opt_out' => true])->notify(new AccountAlreadyExists);
        $this->assertStringNotContainsString('jw_e', (string) $this->lastSentEmail()->getHtmlBody(), 'se opuso');

        NotificationFacade::route('mail', 'sin.cuenta@example.test')->notify(new AccountAlreadyExists);
        $this->assertStringNotContainsString('jw_e', (string) $this->lastSentEmail()->getHtmlBody(), 'sin cuenta');

        User::factory()->create()->notify(new SurveyInvitation($this->survey(), str_repeat('a', 40)));
        $encuesta = (string) $this->lastSentEmail()->getHtmlBody();
        $this->assertStringContainsString('utm_medium=survey_invitation', $encuesta, 'la encuesta sí lleva su UTM, que no es de nadie');
        $this->assertStringNotContainsString('jw_e', $encuesta, 'la encuesta es anónima: nunca la marca de una persona');

        $this->assertSame(0, EmailSend::query()->where('tracks_clicks', true)->count());
    }

    /** Un `toMail()` a mano (una prueba, una vista previa) no es un envío: sin el evento del framework no hay marca. */
    public function test_a_to_mail_called_by_hand_carries_no_mark(): void
    {
        $this->switchOn();

        $html = (string) (new AccountAlreadyExists)->toMail(User::factory()->create())->render();

        $this->assertStringContainsString('utm_medium=account_already_exists', $html);
        $this->assertStringNotContainsString('jw_e', $html);
    }

    // ─── El clic ─────────────────────────────────────────────────────────────────────────────────

    /** Se apunta QUÉ enlace y la URL se limpia: el 302 va a la MISMA URL, byte a byte, sin la marca. */
    public function test_a_click_is_counted_and_the_mark_leaves_the_url(): void
    {
        [$row, $boton] = $this->sentWithMark();
        $this->travel(1)->minutes();

        $respuesta = $this->get($boton);

        $respuesta->assertRedirect();
        $this->assertSame(str_replace('&jw_e='.$row->send_key, '', $boton), $respuesta->headers->get('Location'));
        $this->get((string) $respuesta->headers->get('Location'))->assertSuccessful();

        $click = EmailClick::query()->sole();
        $this->assertNull($click->verdict, 'cuenta');
        $this->assertSame(RouteNormalizer::path((string) parse_url($boton, PHP_URL_PATH)), $click->route);
        $this->assertSame((int) $row->id, $click->email_send_id);
    }

    public function test_a_hit_right_after_sending_is_a_machine(): void
    {
        [, $boton] = $this->sentWithMark();

        $this->get($boton)->assertRedirect();

        $this->assertSame([EmailClick::VERDICT_EARLY], EmailClick::query()->pluck('verdict')->all());
    }

    /** La ráfaga de un escáner (todos los enlaces a la vez) se lleva también las visitas de su ventana que parecían de alguien. */
    public function test_a_burst_over_the_whole_mail_is_a_scanner(): void
    {
        [$row] = $this->sentWithMark();
        $this->travel(1)->minutes();

        foreach (['/', '/login', route('legal.privacidad', [], false)] as $path) {
            $this->get($path.'?jw_e='.$row->send_key)->assertRedirect();
        }

        $this->assertSame(array_fill(0, 3, EmailClick::VERDICT_SWEEP), EmailClick::query()->orderBy('id')->pluck('verdict')->all());
        $this->assertSame(0, (int) EmailSend::query()->withClickCounts()->sole()->clicks_counted);
    }

    /** El mismo enlace dentro de la ventana es el mismo clic (un doble clic, el doble paso de Safe Links); pasada, otro. */
    public function test_the_same_link_again_within_the_window_is_the_same_click(): void
    {
        [$row] = $this->sentWithMark();
        $this->travel(1)->minutes();

        $this->get('/?jw_e='.$row->send_key)->assertRedirect();
        $this->get('/?jw_e='.$row->send_key)->assertRedirect();
        $this->travel(EmailClick::WINDOW_SECONDS + 1)->seconds();
        $this->get('/?jw_e='.$row->send_key)->assertRedirect();

        $this->assertSame([null, EmailClick::VERDICT_REPEAT, null], EmailClick::query()->orderBy('id')->pluck('verdict')->all());
        $this->assertSame(2, (int) EmailSend::query()->withClickCounts()->sole()->clicks_counted);
    }

    /** Desde qué se pulsó (`#796`): la CLASE del aparato; ni el agente ni la IP se guardan en ningún sitio. */
    public function test_the_device_is_kept_and_the_agent_is_not(): void
    {
        [$row, $boton] = $this->sentWithMark();
        $this->travel(1)->minutes();
        $iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

        $this->withHeader('User-Agent', $iphone)->get($boton)->assertRedirect();

        $this->assertSame('mobile', EmailClick::query()->sole()->device);
        $this->assertEqualsCanonicalizing(['id', 'email_send_id', 'route', 'device', 'verdict', 'clicked_at'], Schema::getColumnListing('email_clicks'), 'ni agente ni IP');
        $this->assertSame((int) $row->id, (int) EmailClick::query()->sole()->email_send_id);
    }

    /**
     * Un robot que DICE quién es (la vista previa de WhatsApp al pegar el enlace) no cuenta, y tampoco hace escáner al cliente
     * que pulsa después: no entra en la cuenta de la ráfaga.
     */
    public function test_a_robot_that_says_so_does_not_count_nor_makes_the_customer_a_scanner(): void
    {
        [$row] = $this->sentWithMark();
        $this->travel(1)->minutes();

        $this->withHeader('User-Agent', 'WhatsApp/2.24.19.86 A')->get('/?jw_e='.$row->send_key)->assertRedirect();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36')->get('/?jw_e='.$row->send_key)->assertRedirect();
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/128.0 Safari/537.36')->get('/login?jw_e='.$row->send_key)->assertRedirect();

        $this->assertSame([EmailClick::VERDICT_BOT, null, null], EmailClick::query()->orderBy('id')->pluck('verdict')->all());
        $this->assertSame(2, (int) EmailSend::query()->withClickCounts()->sole()->clicks_counted);
    }

    /** La regla se RE-COMPRUEBA al pulsar: si se opuso después, o se apagó el interruptor, nada; y la URL se limpia igual. */
    public function test_the_rule_is_checked_again_when_clicking(): void
    {
        [$row] = $this->sentWithMark();
        $this->travel(1)->minutes();

        $raiz = config('app.url').'/';

        $row->user?->forceFill(['analytics_opt_out' => true])->save();
        $this->assertSame($raiz, $this->get('/?jw_e='.$row->send_key)->headers->get('Location'));
        $this->assertSame(0, EmailClick::query()->count(), 'se opuso después de recibirlo');

        $row->user?->forceFill(['analytics_opt_out' => false])->save();
        $this->switchOff();
        $this->assertSame($raiz, $this->get('/?jw_e='.$row->send_key)->headers->get('Location'));
        $this->assertSame(0, EmailClick::query()->count(), 'con el interruptor apagado');

        $this->switchOn();
        $this->assertSame($raiz, $this->get('/?jw_e='.$row->send_key)->headers->get('Location'));
        $this->assertSame(1, EmailClick::query()->count(), 'el control: encendido y sin oposición, cuenta');
    }

    /** Un envío que salió SIN la marca no cuenta clics aunque alguien llegue con su clave: encender hoy no reescribe el pasado. */
    public function test_a_send_that_left_without_the_mark_never_counts_a_click(): void
    {
        User::factory()->create()->notify(new AccountAlreadyExists);
        $row = EmailSend::query()->sole();
        $this->assertFalse($row->tracks_clicks, 'salió con el interruptor apagado');
        $this->switchOn();
        $this->travel(1)->minutes();

        $this->get('/?jw_e='.$row->send_key)->assertRedirect();

        $this->assertSame(0, EmailClick::query()->count());
    }

    /** Una marca que no es de nadie se quita sin apuntar nada; lo demás de la query, intacto y en su orden. */
    public function test_an_unknown_or_malformed_mark_is_stripped_and_nothing_is_recorded(): void
    {
        $raiz = config('app.url').'/';

        $this->assertSame($raiz.'?b=2&a=1', $this->get('/?b=2&jw_e=no-es-un-uuid&a=1')->headers->get('Location'));
        $this->assertSame($raiz, $this->get('/?jw_e='.Str::uuid())->headers->get('Location'));
        $this->assertSame($raiz, $this->get('/?jw%5Fe='.Str::uuid())->headers->get('Location'), 'la clave codificada también se va');

        // PHP lee `jw.e` como `jw_e`, pero en la query cruda no se llama así: no se puede quitar, y redirigir sería un bucle.
        $this->get('/?jw.e='.Str::uuid())->assertOk();

        $this->assertSame(0, EmailClick::query()->count());
    }

    /**
     * ⚠️⚠️ **Un enlace FIRMADO sigue abriendo con la marca**: el 302 lo deja exactamente como se firmó (más la UTM, ignorada),
     * y la marca también la ignoran los accesos por firma (`IGNORED_QUERY`). Una clave ajena sigue dando 403.
     */
    public function test_a_signed_link_still_opens_with_the_mark(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);
        $firmada = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
        ]);
        $conMarca = EmailUtm::tag($firmada, 'verify_email_address', (string) Str::uuid());

        $this->assertTrue(URL::hasValidSignature(Request::create($conMarca), true, EmailUtm::IGNORED_QUERY), 'un acceso por firma la ignora');

        $respuesta = $this->get($conMarca);
        $this->assertSame(EmailUtm::tag($firmada, 'verify_email_address'), $respuesta->headers->get('Location'));
        $this->assertNotSame(403, $this->get((string) $respuesta->headers->get('Location'))->status());
        $this->assertTrue($user->fresh()?->hasVerifiedEmail(), 'el enlace abrió de verdad');

        $this->get($firmada.'&foo=1')->assertForbidden();
    }

    /** ⚠️⚠️ La marca ata una visita a una persona: la ignora la firma y NUNCA la admite la analítica anónima (`#793`). */
    public function test_the_mark_never_reaches_the_anonymous_analytics(): void
    {
        $this->assertContains(EmailUtm::MARK, EmailUtm::IGNORED_QUERY);
        $this->assertNotContains(EmailUtm::MARK, RouteNormalizer::QUERY_ALLOWLIST);
        $this->assertSame(['utm_source' => 'email'], RouteNormalizer::query(['utm_source' => 'email', EmailUtm::MARK => (string) Str::uuid()]));
    }

    /** Apuntar NUNCA impide abrir la página: sin la tabla, el 302 sale igual y solo queda una línea en el registro. */
    public function test_a_broken_click_record_never_blocks_the_page(): void
    {
        [$row, $boton] = $this->sentWithMark();
        $this->travel(1)->minutes();
        Schema::drop('email_clicks');
        Log::spy();

        $respuesta = $this->get($boton);

        $this->assertSame(str_replace('&jw_e='.$row->send_key, '', $boton), $respuesta->headers->get('Location'));
        Log::shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => $message === 'email_clicks.record_failed')->once();
    }

    // ─── El export y los plazos ──────────────────────────────────────────────────────────────────

    /** El export del art. 20 lleva sus clics de persona (sin los del escáner), o `null` si ese correo no los contaba. */
    public function test_the_export_carries_the_clicks_or_null_when_they_were_not_counted(): void
    {
        $user = User::factory()->create();
        $medido = $this->row($user->id, tracks: true);
        $this->click($medido, null);
        $this->click($medido, null);
        $this->click($medido, EmailClick::VERDICT_SWEEP);
        $this->row($user->id, tracks: false);

        $this->actingAs($user)->getJson(self::ROOT.'/me/export')
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('emails.0.clicks', 2)
            ->assertJsonPath('emails.1.clicks', null);
    }

    /** Los clics se van con su envío: la poda de los 24 meses es un borrado en bloque y los arrastra la clave foránea. */
    public function test_pruning_a_send_takes_its_clicks_with_it(): void
    {
        $viejo = $this->row(null, tracks: true, at: now()->subMonths(EmailSend::RETENTION_MONTHS + 1));
        $nuevo = $this->row(null, tracks: true);
        $this->click($viejo, null);
        $this->click($nuevo, null);

        $this->artisan('model:prune', ['--model' => [EmailSend::class]])->assertSuccessful();

        $this->assertSame([(int) $nuevo->id], EmailClick::query()->pluck('email_send_id')->all());
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    private function switchOn(): void
    {
        Setting::query()->updateOrCreate(['key' => EmailClickMarks::SETTING], ['value' => '1', 'group' => 'emails']);
    }

    /** Como lo apaga el panel: guardando el modelo, que vacía la memoria de `Setting` (un borrado en bloque no la vacía). */
    private function switchOff(): void
    {
        Setting::query()->updateOrCreate(['key' => EmailClickMarks::SETTING], ['value' => '0', 'group' => 'emails']);
    }

    /** @return array{0: EmailSend, 1: string} el envío y la URL de su botón, con la marca */
    private function sentWithMark(): array
    {
        $this->switchOn();
        User::factory()->create()->notify(new AccountAlreadyExists);

        $row = EmailSend::query()->sole();
        $boton = collect($this->ourLinks($this->lastSentEmail()))->first(static fn (string $h): bool => str_contains($h, '/login?'));
        $this->assertIsString($boton, 'el botón de «Ya tienes cuenta» lleva a entrar');

        return [$row, $boton];
    }

    /** @return list<string> los `href` a esta casa del correo, decodificados */
    private function ourLinks(Email $email): array
    {
        preg_match_all('/href="([^"]+)"/', (string) $email->getHtmlBody(), $m);

        return array_values(array_filter(
            array_map(static fn (string $h): string => html_entity_decode($h), $m[1]),
            static fn (string $h): bool => str_starts_with($h, (string) config('app.url')),
        ));
    }

    private function lastSentEmail(): Email
    {
        /** @var TransportInterface&ArrayTransport $transport */
        $transport = app('mailer')->getSymfonyTransport();
        $email = $transport->messages()->last()?->getOriginalMessage();
        $this->assertInstanceOf(Email::class, $email);

        return $email;
    }

    private function survey(): Survey
    {
        return Survey::create([
            'key' => 'visita', 'name' => ['es' => 'Tu visita'], 'kind' => Survey::KIND_EXTERNAL, 'active' => true,
            'questions' => [['key' => 'ambiente', 'type' => 'scale', 'label' => ['es' => 'Ambiente']]],
        ]);
    }

    private function row(?int $userId, bool $tracks, ?\DateTimeInterface $at = null): EmailSend
    {
        $at ??= now()->subDay();
        $id = DB::table('email_sends')->insertGetId([
            'send_key' => (string) Str::uuid(), 'user_id' => $userId, 'recipient' => 'alguien@example.test',
            'mail_key' => 'account_already_exists', 'subject' => 'Asunto', 'html' => '<p>Copia</p>', 'attachments' => '[]',
            'tracks_clicks' => $tracks, 'sent_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);

        return EmailSend::query()->findOrFail($id);
    }

    private function click(EmailSend $send, ?string $verdict): void
    {
        DB::table('email_clicks')->insert(['email_send_id' => $send->id, 'route' => '/', 'verdict' => $verdict, 'clicked_at' => now()]);
    }
}
