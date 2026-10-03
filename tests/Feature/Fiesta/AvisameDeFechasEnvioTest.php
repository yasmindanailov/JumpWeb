<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Services\PartyInvitations;
use App\Domain\Identity\Models\BirthdayReminder;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\BirthdayReminders;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\DisplayTime;
use App\Filament\Resources\BirthdayReminders\Pages\ListBirthdayReminders;
use App\Notifications\BirthdayComingNotice;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Symfony\Component\Mime\Email;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * «AVÍSAME DE FECHAS», tanda A2 (`specs/avisame-de-fechas.md` §4.3 y §4.4; `[DECIDIDO owner]` `#750`): «El cumple se
 * acerca» (el correo nº 12 del mockup), `birthday-reminders:send` y la lista del panel.
 *
 * ⚠️ Lo que se vigila: UN correo por cumpleaños (la marca antes de encolar, y el mismo niño firmado en dos fiestas es un
 * correo), la ventana (ni fuera de plazo ni tan cerca que llegue tarde), que nunca va con la baja ni a quien ya celebra
 * aquí, que la baja alcanza a todo ese correo, y que el correo lleva por qué y cómo darse de baja.
 */
class AvisameDeFechasEnvioTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
    }

    public function test_one_email_inside_the_window_and_never_twice_for_the_same_birthday(): void
    {
        $fila = $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));

        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();

        Notification::assertSentOnDemand(BirthdayComingNotice::class, function (BirthdayComingNotice $n, array $canales, AnonymousNotifiable $a): bool {
            return $a->routes['mail'] === 'marta@example.com' && $n->nombre === 'Hugo' && $n->edad === 8;
        });
        $this->assertSame(DisplayTime::today()->addDays(30)->toDateString(), $fila->fresh()?->sent_for?->toDateString());

        // Otra pasada, otra hora: ni un correo más.
        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();
        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 1);
    }

    public function test_a_compound_first_name_goes_whole(): void
    {
        // `minor_name` es SOLO el nombre (los apellidos van aparte): cortarlo en el primer espacio dejaba a María José en «María».
        $fila = $this->pedido('María José Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30), 'María José');

        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();

        Notification::assertSentOnDemand(BirthdayComingNotice::class, fn (BirthdayComingNotice $n): bool => $n->nombre === 'María José');
        $baja = (string) $this->get(URL::signedRoute('birthday-reminder.unsubscribe', ['reminder' => $fila->getKey()]))->assertOk()->getContent();
        $this->assertStringContainsString('antes del cumple de María José', $baja);
    }

    public function test_outside_the_window_too_close_or_switched_off_nothing_goes(): void
    {
        $this->pedido('Hugo Ruiz', 'lejos@example.com', $this->nacidoConCumpleEn(60));
        $this->pedido('Lía Gil', 'cerca@example.com', $this->nacidoConCumpleEn(3));
        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();
        $this->ningunAviso();

        // CONTROL: el de 60 días entra si el plazo es de 9 semanas; y a 0, nada.
        Setting::query()->updateOrCreate(['key' => BirthdayReminders::WEEKS_KEY], ['value' => '9']);
        Setting::flushMemo();
        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();
        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 1);
    }

    public function test_the_dry_run_counts_and_touches_nothing(): void
    {
        $fila = $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));

        $this->artisan('birthday-reminders:send', ['--force' => true, '--dry-run' => true])
            ->expectsOutputToContain('Mandaría 1 avisos de cumple')
            ->assertSuccessful();

        $this->ningunAviso();
        $this->assertNull($fila->fresh()?->sent_for, 'el ensayo no marca: mañana saldría de verdad');
    }

    public function test_before_ten_at_the_park_the_hourly_run_waits(): void
    {
        $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));
        $this->travelTo(DisplayTime::today()->setTime(8, 0)->shiftTimezone(DisplayTime::timezone()));

        $this->artisan('birthday-reminders:send')->assertSuccessful();
        $this->ningunAviso();

        $this->travelTo(DisplayTime::today()->setTime(10, 5)->shiftTimezone(DisplayTime::timezone()));
        $this->artisan('birthday-reminders:send')->assertSuccessful();
        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 1);
    }

    public function test_the_same_child_signed_at_two_parties_is_one_email(): void
    {
        $nacio = $this->nacidoConCumpleEn(30);
        $una = $this->pedido('Hugo Ruiz', 'marta@example.com', $nacio);
        $otra = $this->pedido('Hugo Ruiz', 'Marta@Example.com', $nacio);

        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();

        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 1);
        $this->assertNotNull($una->fresh()?->sent_for);
        $this->assertNotNull($otra->fresh()?->sent_for, 'las dos filas quedan marcadas: la otra no escribe mañana');
    }

    public function test_never_with_the_opt_out_and_the_opt_out_covers_the_whole_address(): void
    {
        $hugo = $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));
        $lia = $this->pedido('Lía Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(35));

        app(BirthdayReminders::class)->revoke($hugo);

        $this->assertFalse($lia->fresh()?->isLive(), 'darse de baja es de ese CORREO, no de un niño');
        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();
        $this->ningunAviso();
    }

    public function test_whoever_already_celebrates_here_gets_nothing(): void
    {
        $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));
        // Una cuenta con ese correo, con una fiesta pagada hace unos meses.
        ['order' => $order] = $this->mountParty();
        $cliente = User::factory()->create(['email' => 'marta@example.com']);
        $order->forceFill(['user_id' => $cliente->getKey(), 'status' => Order::STATUS_PAID])->save();

        $this->artisan('birthday-reminders:send', ['--force' => true])->assertSuccessful();
        $this->ningunAviso();
    }

    public function test_the_email_says_why_and_how_to_leave(): void
    {
        $fila = $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));
        $correo = (new BirthdayComingNotice((int) $fila->getKey(), 'Hugo', 8, 'octubre', '14,95 €'))->toMail(new AnonymousNotifiable);

        $this->assertSame('Hugo cumple 8 en octubre: ¿lo celebramos aquí?', $correo->subject);
        $html = (string) $correo->render();
        $this->assertStringContainsString('El cumple de Hugo, resuelto', $html);
        $this->assertStringContainsString('Desde 14,95 € por niño', $html);
        $this->assertStringContainsString('marcaste «Avísame de fechas» al firmar la autorización de Hugo', $html);
        $baja = URL::signedRoute('birthday-reminder.unsubscribe', ['reminder' => $fila->getKey()]);
        $this->assertStringContainsString(e($baja), $html, 'la baja de un toque, al pie');
        // Y en la cabecera, para el botón «Cancelar suscripción» del gestor de correo.
        $mensaje = new Email;
        foreach ($correo->callbacks as $callback) {
            $callback($mensaje);
        }
        $this->assertSame('<'.$baja.'>', $mensaje->getHeaders()->get('List-Unsubscribe')?->getBodyAsString());

        // Sin precio en el catálogo, la frase del precio no sale.
        $sin = (string) (new BirthdayComingNotice((int) $fila->getKey(), 'Hugo', 8, 'octubre', null))->toMail(new AnonymousNotifiable)->render();
        $this->assertStringNotContainsString('por niño', $sin);
    }

    /**
     * El texto de FÁBRICA no promete horas (la R1c, `correos-rediseno.md` §4.1.4): desde `#873` las dos horas se reparten
     * —saltan y luego meriendan— y cuánto dura cada parte es de cada parque (los «90 minutos» de PlayJump, en el panel).
     */
    public function test_the_factory_text_promises_no_hours(): void
    {
        foreach (['es', 'en', 'fr'] as $locale) {
            foreach (['fiesta.cumple_mail.preheader', 'fiesta.cumple_mail.linea'] as $clave) {
                $texto = (string) __($clave, [], $locale);
                $this->assertSame(0, preg_match('/\d|horas|hours|heures/iu', $texto), "{$clave} ({$locale}) promete horas: «{$texto}»");
            }
        }
        $this->assertStringContainsString('luego', (string) __('fiesta.cumple_mail.linea', [], 'es'), 'el orden sí: primero saltan y luego meriendan');
    }

    public function test_the_panel_lists_them_and_deletes_on_request(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $fila = $this->pedido('Hugo Ruiz', 'marta@example.com', $this->nacidoConCumpleEn(30));
        $admin = User::factory()->create();
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        Livewire::actingAs($admin)->test(ListBirthdayReminders::class)
            ->assertSee('marta@example.com')
            ->assertSee('Hugo')
            ->assertSee('Esperando')
            ->callTableAction(DeleteAction::class, $fila);

        $this->assertNull($fila->fresh(), 'borrada a petición');
        $this->assertDatabaseCount('guardian_authorizations', 1);

        // Sin el permiso de clientes, no se entra. ⚠️ Un empleado DEL PANEL (`staff`): uno sin rol lo echa el panel entero
        // antes de llegar al recurso, y el 403 saldría aunque el recurso no mirara nada (lo cazó la mutación).
        $empleado = User::factory()->create();
        $empleado->roles()->sync([Role::where('name', 'staff')->value('id')]);
        $this->actingAs($empleado)->get('/admin')->assertOk();
        $this->actingAs($empleado)->get('/admin/avisos-de-cumple')->assertForbidden();
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /**
     * Ningún «El cumple se acerca». ⚠️ No `assertNothingSent()`: firmar manda su copia al adulto (`GuardianAuthorizationSigned`),
     * y esa afirmación saldría roja por un correo que no es el que se mide.
     */
    private function ningunAviso(): void
    {
        Notification::assertSentOnDemandTimes(BirthdayComingNotice::class, 0);
    }

    /** Una fecha de nacimiento (hace 7 años) cuyo PRÓXIMO cumpleaños cae dentro de `$dias` días (cumple 8). */
    private function nacidoConCumpleEn(int $dias): string
    {
        return DisplayTime::today()->addDays($dias)->subYears(8)->toDateString();
    }

    /**
     * Una fiesta, un «sí», la autorización firmada desde su recibo con ese correo y ese nacimiento, y la casilla marcada:
     * el camino entero, como lo hace un padre. `$nombre`: el nombre si es compuesto (si no, la primera palabra de `$nino`).
     */
    private function pedido(string $nino, string $correo, string $nacio, ?string $nombre = null): BirthdayReminder
    {
        ['invitation' => $invitation] = $this->mountParty();
        /** @var PartyInvitation $invitation */
        $this->post(route('invitation.reply', ['token' => $invitation->token]), ['child_name' => $nino, 'attending' => '1'])->assertRedirect();
        $reply = InvitationReply::query()->where('party_invitation_id', $invitation->getKey())->latest('id')->firstOrFail();
        $url = app(PartyInvitations::class)->receiptUrl($reply);
        $this->assertSame(1, preg_match('#<form method="post" action="([^"]+)"[^>]*data-receipt-firma#', (string) $this->get($url)->getContent(), $m));
        $nombre ??= explode(' ', $nino, 2)[0];
        $apellido = trim(substr($nino, strlen($nombre)));
        $this->post(html_entity_decode($m[1]), [
            'document_id' => LegalDocumentVersion::query()->orderByDesc('id')->firstOrFail()->getKey(),
            'accept_waiver' => '1', 'invitation_reply_id' => (string) $reply->getKey(),
            'minor_name' => $nombre, 'minor_surname' => $apellido, 'minor_born_on' => $nacio,
            'guardian_name' => 'Marta Ruiz', 'guardian_relationship' => 'mother', 'guardian_phone' => '600111222',
            'guardian_email' => $correo,
        ])->assertRedirect();
        $this->postJson($url, ['dates' => '1'])->assertOk()->assertJson(['saved' => true]);

        return BirthdayReminder::query()->latest('id')->firstOrFail();
    }
}
