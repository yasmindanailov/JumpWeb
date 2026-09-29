<?php

namespace Tests\Feature\Mail;

use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\EmailOpen;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Services\Analytics\EmailOpenMarks;
use App\Filament\Resources\EmailSends\Tables\EmailSendTable;
use App\Notifications\AccountAlreadyExists;
use App\Notifications\SurveyInvitation;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Tests\Feature\Api\ApiTestCase;

/**
 * **Las aperturas de cada correo** (`specs/correos-salientes.md` §4.12, `#797`, la C3): el píxel viaja SOLO con su interruptor
 * y el «sí» de «análisis» vivo de la cuenta (nunca a quien se opuso, sin cuenta ni en la encuesta); responde siempre el mismo
 * GIF sin dejar cookie ni sesión; cada apertura guarda su hora, su ORIGEN y si cuenta (Apple nunca); y la vista previa no la pide.
 */
class EmailOpensTest extends ApiTestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148';

    private const GMAIL = 'Mozilla/5.0 (Windows NT 5.1; rv:11.0) Gecko Firefox/11.0 (via ggpht.com GoogleImageProxy)';

    // ─── El píxel, al enviar ─────────────────────────────────────────────────────────────────────

    public function test_the_pixel_travels_only_with_the_switch_and_a_live_yes_to_analytics(): void
    {
        $this->switchOn();
        $conSi = $this->customer(analytics: true);

        $conSi->notify(new AccountAlreadyExists);

        $row = EmailSend::query()->sole();
        $this->assertTrue($row->tracks_opens);
        $this->assertSame(1, substr_count((string) $this->lastSentEmail()->getHtmlBody(), '/e/'.$row->send_key.'.gif'), 'UN píxel, con la clave de SU envío');

        // Los controles: nunca decidió, su ÚLTIMA decisión es «no» (aunque antes dijera sí), y el interruptor apagado.
        $this->customer(analytics: null)->notify(new AccountAlreadyExists);
        $this->assertNoPixel('nunca decidió con la sesión iniciada');

        $cambio = $this->customer(analytics: true);
        $this->consent($cambio, false, now()->addSecond());
        $cambio->notify(new AccountAlreadyExists);
        $this->assertNoPixel('su última decisión es «no»');

        $this->switchOff();
        $conSi->notify(new AccountAlreadyExists);
        $this->assertNoPixel('con el interruptor apagado');
        $this->assertSame(1, EmailSend::query()->where('tracks_opens', true)->count());
    }

    /** La regla de persona de los clics, también aquí: quien se opuso, quien no tiene cuenta y la encuesta, que es anónima. */
    public function test_no_pixel_for_who_objected_for_who_has_no_account_nor_in_the_survey(): void
    {
        $this->switchOn();

        $opuesto = $this->customer(analytics: true);
        $opuesto->forceFill(['analytics_opt_out' => true])->save();
        $opuesto->notify(new AccountAlreadyExists);
        $this->assertNoPixel('se opuso');

        NotificationFacade::route('mail', 'sin.cuenta@example.test')->notify(new AccountAlreadyExists);
        $this->assertNoPixel('sin cuenta');

        $this->customer(analytics: true)->notify(new SurveyInvitation($this->survey(), str_repeat('a', 40)));
        $this->assertNoPixel('la encuesta es anónima');
    }

    public function test_a_to_mail_called_by_hand_carries_no_pixel(): void
    {
        $this->switchOn();

        $html = (string) (new AccountAlreadyExists)->toMail($this->customer(analytics: true))->render();

        $this->assertStringNotContainsString('/e/', $html);
    }

    // ─── El píxel, al abrir ──────────────────────────────────────────────────────────────────────

    /** Siempre el mismo GIF, `no-store`, SIN cookie ni sesión: exista el envío o no. */
    public function test_the_pixel_answers_the_same_gif_and_leaves_no_cookie(): void
    {
        $row = $this->sentWithPixel();

        foreach ([$row->send_key, (string) Str::uuid()] as $clave) {
            $respuesta = $this->get(route('emails.open', ['send' => $clave]));

            $respuesta->assertOk();
            $this->assertSame('image/gif', $respuesta->headers->get('Content-Type'));
            $this->assertSame('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', base64_encode((string) $respuesta->getContent()));
            $this->assertStringContainsString('no-store', (string) $respuesta->headers->get('Cache-Control'));
            $this->assertSame([], $respuesta->headers->getCookies(), 'un píxel no deja nada en quien abre el correo');
        }
    }

    /** Cada apertura con su hora, su origen y si cuenta: la de Apple nunca, la misma lectura una vez, y la reapertura sí. */
    public function test_each_open_keeps_its_time_its_source_and_whether_it_counts(): void
    {
        $row = $this->sentWithPixel();
        $pixel = route('emails.open', ['send' => $row->send_key]);

        $this->travel(1)->minutes();
        $this->withHeader('User-Agent', self::IPHONE)->get($pixel)->assertOk();
        $this->travel(30)->seconds();
        $this->withHeader('User-Agent', self::IPHONE)->get($pixel)->assertOk();
        $this->travel(110)->seconds();
        $this->withHeader('User-Agent', self::GMAIL)->get($pixel)->assertOk();
        $this->withHeader('User-Agent', 'Mozilla/5.0')->get($pixel)->assertOk();

        $aperturas = EmailOpen::query()->orderBy('id')->get();
        $this->assertSame([EmailOpen::SOURCE_DIRECT, EmailOpen::SOURCE_DIRECT, EmailOpen::SOURCE_GMAIL, EmailOpen::SOURCE_APPLE], $aperturas->pluck('source')->all());
        $this->assertSame([null, EmailOpen::VERDICT_REPEAT, null, EmailOpen::VERDICT_APPLE], $aperturas->pluck('verdict')->all());
        $this->assertSame(['mobile', 'mobile', null, null], $aperturas->pluck('device')->all(), 'un proxy no dice nada del aparato');
        $this->assertSame(2, (int) EmailSend::query()->withOpenCounts()->sole()->opens_counted);
        $this->assertSame(1, (int) EmailSend::query()->withOpenCounts()->sole()->opens_automatic);
    }

    public function test_an_open_right_after_sending_is_a_machine(): void
    {
        $row = $this->sentWithPixel();

        $this->withHeader('User-Agent', self::IPHONE)->get(route('emails.open', ['send' => $row->send_key]))->assertOk();

        $this->assertSame([EmailOpen::VERDICT_EARLY], EmailOpen::query()->pluck('verdict')->all());
    }

    /** La regla se RE-COMPRUEBA al abrir: si retiró su «sí» después, o se apagó el interruptor, nada; el GIF sale igual. */
    public function test_the_rule_is_checked_again_when_opening(): void
    {
        $row = $this->sentWithPixel();
        $pixel = route('emails.open', ['send' => $row->send_key]);
        $this->travel(1)->minutes();

        $this->consent($row->user, false, now());
        $this->get($pixel)->assertOk();
        $this->assertSame(0, EmailOpen::query()->count(), 'retiró el consentimiento');

        $this->consent($row->user, true, now()->addSecond());
        $this->switchOff();
        $this->get($pixel)->assertOk();
        $this->assertSame(0, EmailOpen::query()->count(), 'con el interruptor apagado');

        $this->switchOn();
        $this->get($pixel)->assertOk();
        $this->assertSame(1, EmailOpen::query()->count(), 'el control: encendido y con su «sí», cuenta');
    }

    /** Un envío que salió SIN píxel no cuenta aperturas aunque llegue su clave: encender hoy no reescribe el pasado. */
    public function test_a_send_that_left_without_the_pixel_never_counts_an_open(): void
    {
        $this->customer(analytics: true)->notify(new AccountAlreadyExists);
        $row = EmailSend::query()->sole();
        $this->assertFalse($row->tracks_opens, 'salió con el interruptor apagado');
        $this->switchOn();
        $this->travel(1)->minutes();

        $this->get(route('emails.open', ['send' => $row->send_key]))->assertOk();

        $this->assertSame(0, EmailOpen::query()->count());
    }

    /** Sin limitador por IP (el proxy de Gmail), pero con tope por ENVÍO: lo de más en un minuto no se apunta. */
    public function test_a_flood_on_one_send_is_capped(): void
    {
        $row = $this->sentWithPixel();
        $this->travel(1)->minutes();
        DB::table('email_opens')->insert(array_fill(0, EmailOpen::FLOOD_PER_MINUTE, [
            'email_send_id' => $row->id, 'source' => EmailOpen::SOURCE_DIRECT, 'verdict' => EmailOpen::VERDICT_REPEAT, 'opened_at' => now(),
        ]));

        $this->get(route('emails.open', ['send' => $row->send_key]))->assertOk();

        $this->assertSame(EmailOpen::FLOOD_PER_MINUTE, EmailOpen::query()->count());
    }

    /** ⚠️⚠️ La vista previa del panel NO pide el píxel: pintarla contaría como una apertura del cliente. */
    public function test_the_preview_never_asks_for_the_pixel(): void
    {
        $row = $this->sentWithPixel();

        $pintada = EmailSendTable::inert((string) $row->html, (string) $row->send_key);

        $this->assertStringContainsString('/e/'.$row->send_key.'.gif', (string) $row->html, 'la copia guardada lo lleva, tal cual salió');
        $this->assertStringNotContainsString('/e/'.$row->send_key.'.gif', $pintada);
    }

    /** Apuntar NUNCA rompe el píxel: sin la tabla, el GIF sale igual y solo queda una línea en el registro. */
    public function test_a_broken_open_record_never_breaks_the_pixel(): void
    {
        $row = $this->sentWithPixel();
        $this->travel(1)->minutes();
        Schema::drop('email_opens');
        Log::spy();

        $this->get(route('emails.open', ['send' => $row->send_key]))->assertOk()->assertHeader('Content-Type', 'image/gif');

        Log::shouldHaveReceived('warning')->withArgs(static fn (string $message): bool => $message === 'email_opens.record_failed')->once();
    }

    // ─── El export y los plazos ──────────────────────────────────────────────────────────────────

    public function test_the_export_carries_the_opens_or_null_when_they_were_not_measured(): void
    {
        $user = User::factory()->create();
        $medido = $this->row($user->id, tracks: true);
        $this->open($medido, null);
        $this->open($medido, EmailOpen::VERDICT_APPLE);
        $this->row($user->id, tracks: false);

        $this->actingAs($user)->getJson(self::ROOT.'/me/export')
            ->assertOk()->assertValidResponse(200)
            ->assertJsonPath('emails.0.opens', 1)
            ->assertJsonPath('emails.1.opens', null);
    }

    public function test_pruning_a_send_takes_its_opens_with_it(): void
    {
        $viejo = $this->row(null, tracks: true, at: now()->subMonths(EmailSend::RETENTION_MONTHS + 1));
        $nuevo = $this->row(null, tracks: true);
        $this->open($viejo, null);
        $this->open($nuevo, null);

        $this->artisan('model:prune', ['--model' => [EmailSend::class]])->assertSuccessful();

        $this->assertSame([(int) $nuevo->id], EmailOpen::query()->pluck('email_send_id')->all());
        $this->assertEqualsCanonicalizing(['id', 'email_send_id', 'source', 'device', 'verdict', 'opened_at'], Schema::getColumnListing('email_opens'), 'ni agente ni IP');
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    private function switchOn(): void
    {
        Setting::query()->updateOrCreate(['key' => EmailOpenMarks::SETTING], ['value' => '1', 'group' => 'emails']);
    }

    private function switchOff(): void
    {
        Setting::query()->updateOrCreate(['key' => EmailOpenMarks::SETTING], ['value' => '0', 'group' => 'emails']);
    }

    /** Un cliente con su ÚLTIMA decisión de cookies: `true` acepta «análisis», `false` no, `null` nunca decidió. */
    private function customer(?bool $analytics): User
    {
        $user = User::factory()->create();
        if ($analytics !== null) {
            $this->consent($user, $analytics, now());
        }

        return $user;
    }

    private function consent(?User $user, bool $analytics, \DateTimeInterface $at): void
    {
        CookieConsentLog::query()->create([
            'user_id' => $user?->id, 'categories' => ['maps' => false, 'social' => false, 'analytics' => $analytics, 'marketing' => false],
            'version' => '2026-09-24', 'accepted_at' => $at,
        ]);
    }

    private function sentWithPixel(): EmailSend
    {
        $this->switchOn();
        $this->customer(analytics: true)->notify(new AccountAlreadyExists);

        $row = EmailSend::query()->sole();
        $this->assertTrue($row->tracks_opens);

        return $row;
    }

    private function assertNoPixel(string $why): void
    {
        $this->assertStringNotContainsString('/e/', (string) $this->lastSentEmail()->getHtmlBody(), $why);
        $this->assertFalse(EmailSend::query()->latest('id')->firstOrFail()->tracks_opens, $why);
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
            'tracks_opens' => $tracks, 'sent_at' => $at, 'created_at' => $at, 'updated_at' => $at,
        ]);

        return EmailSend::query()->findOrFail($id);
    }

    private function open(EmailSend $send, ?string $verdict): void
    {
        DB::table('email_opens')->insert(['email_send_id' => $send->id, 'source' => EmailOpen::SOURCE_DIRECT, 'verdict' => $verdict, 'opened_at' => now()]);
    }
}
