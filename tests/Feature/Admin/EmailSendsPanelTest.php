<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\EmailClick;
use App\Domain\Platform\Models\EmailOpen;
use App\Domain\Platform\Models\EmailSend;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\EmailClickMarks;
use App\Domain\Platform\Services\Analytics\EmailOpenMarks;
use App\Filament\Pages\Settings;
use App\Filament\Resources\EmailSends\EmailSendResource;
use App\Filament\Resources\EmailSends\Pages\ListEmailSends;
use App\Filament\Resources\EmailSends\Tables\EmailSendTable;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use Carbon\CarbonImmutable;
use Database\Seeders\LandingContentSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * **Los correos enviados, en el panel** (`specs/correos-salientes.md` §4.2, `DECISIONES #794`, la C1): la página con su permiso
 * PROPIO, la vista previa TAL CUAL en un `iframe` aislado que deja rastro sin el contenido, y los suyos en la ficha del cliente.
 */
class EmailSendsPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    public function test_the_page_needs_its_own_permission_and_staff_does_not_have_it(): void
    {
        $this->actingAs($this->withRole('staff'))->get(EmailSendResource::getUrl('index'))->assertForbidden();
        $this->actingAs($this->withRole('admin'))->get(EmailSendResource::getUrl('index'))->assertOk();
    }

    public function test_the_list_says_who_which_mail_and_whether_it_left(): void
    {
        $cliente = User::factory()->create(['name' => 'Lucía Martín']);
        $this->send($cliente->id, 'order_confirmation', sentAt: now());
        $this->send(null, 'guardian_authorization_signed', sentAt: null, failures: 3, recipient: 'padre@example.test');

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertSee('Lucía Martín')
            ->assertSee('Confirmación del pedido')
            ->assertSee('Enviado')
            ->assertSee('padre@example.test')
            ->assertSee('Justificante firmado')
            ->assertSee('No salió (3 intentos)');
    }

    /** La vista previa: el HTML exacto en un `iframe` con `sandbox` VACÍO, y el rastro con el envío y sin el contenido. */
    public function test_the_preview_shows_the_exact_copy_isolated_and_leaves_a_trace_without_content(): void
    {
        $send = $this->send(User::factory()->create()->id, 'order_confirmation', sentAt: now(), html: '<p>Tu pedido <strong>JW-1</strong> & más</p>');

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->mountAction(TestAction::make('preview')->table($send))
            ->assertActionMounted(TestAction::make('preview')->table($send));

        // ⚠️ La prueba de Livewire no pinta el CONTENIDO de un modal de Filament 4 (medido: la acción se monta y el HTML
        // no lo trae); se le pide a la acción de verdad —su `modalContent`— y el navegador lo ve entero en la sonda.
        $html = EmailSendTable::previewAction()->record($send)->getModalContent()?->render() ?? '';
        $this->assertStringContainsString('sandbox=""', $html, 'el iframe va AISLADO: ni scripts ni enlaces que salgan');
        $this->assertStringContainsString('srcdoc="&lt;base target=&quot;_blank&quot;&gt;&lt;p&gt;Tu pedido &lt;strong&gt;JW-1&lt;/strong&gt; &amp; más&lt;/p&gt;"', $html, 'la copia, entera y escapada UNA vez para el atributo, con los enlaces desactivados');

        $trace = AuditLog::query()->where('action', 'emails.previewed')->sole();
        $this->assertSame('email_send', $trace->target_type);
        $this->assertSame((int) $send->id, (int) $trace->target_id);
        $this->assertStringNotContainsString('JW-1', (string) json_encode($trace->payload), 'el rastro no lleva el contenido (RGPD-02)');
    }

    public function test_without_a_copy_there_is_no_preview(): void
    {
        $send = $this->send(User::factory()->create()->id, 'order_confirmation', sentAt: now(), html: null);

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertActionHidden(TestAction::make('preview')->table($send));
    }

    /** En la ficha: los suyos y «Ver todos» a la página filtrada; sin el permiso, la sección no está. */
    public function test_the_customer_card_lists_their_mails_only_with_the_permission(): void
    {
        $cliente = $this->withRole('customer');
        $this->send($cliente->id, 'survey_invitation', sentAt: now());

        Livewire::actingAs($this->withRole('admin'))->test(ViewUser::class, ['record' => $cliente->id])
            ->assertSee('Correos')
            ->assertSee('Encuesta')
            ->assertSee(EmailSendResource::urlForUser((int) $cliente->id), false);

        Livewire::actingAs($this->staffWhoSeesCustomers())->test(ViewUser::class, ['record' => $cliente->id])
            ->assertDontSee('Ver todos sus correos');
    }

    // ─── Los clics (la C2, §4.8) ─────────────────────────────────────────────────────────────────

    /** Los de una persona cuentan; los de un escáner, aparte; sin la marca, «No se mide» y no un cero que mentiría. */
    public function test_the_list_counts_the_clicks_apart_from_the_scanner_and_says_when_they_are_not_measured(): void
    {
        $cliente = User::factory()->create(['name' => 'Lucía Martín']);
        $medido = $this->send($cliente->id, 'order_confirmation', sentAt: now(), tracks: true);
        $this->click($medido, null);
        $this->click($medido, null);
        $this->click($medido, EmailClick::VERDICT_REPEAT);
        $this->click($medido, EmailClick::VERDICT_SWEEP);
        $this->click($medido, EmailClick::VERDICT_EARLY);
        $this->click($medido, EmailClick::VERDICT_BOT);
        $sinPulsar = $this->send($cliente->id, 'visit_reminder', sentAt: now(), tracks: true);
        $sinMedir = $this->send($cliente->id, 'survey_invitation', sentAt: now(), tracks: false);

        $lista = Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertSee('2 clics')
            ->assertSee('+3 automáticos')
            ->assertSee('Sin clics')
            ->assertSee('No se mide');

        // ⚠️ Por la columna, no por el texto de la página: desde la C3 la de aperturas también dice «No se mide».
        $this->assertSame('No se mide', EmailSendTable::clicks($sinMedir), 'sin la marca, un cero mentiría');
        $this->assertSame('Sin clics', EmailSendTable::clicks($sinPulsar));

        $lista->filterTable('clicks', EmailSendTable::CLICKED)->assertCanSeeTableRecords([$medido])->assertCanNotSeeTableRecords([$sinPulsar, $sinMedir]);
        $lista->filterTable('clicks', EmailSendTable::NOT_CLICKED)->assertCanSeeTableRecords([$sinPulsar])->assertCanNotSeeTableRecords([$medido, $sinMedir]);
        $lista->filterTable('clicks', EmailSendTable::NOT_MEASURED)->assertCanSeeTableRecords([$sinMedir])->assertCanNotSeeTableRecords([$medido, $sinPulsar]);

        Livewire::actingAs($this->withRole('admin'))->test(ViewUser::class, ['record' => $cliente->id])
            ->assertSee('2 clics')
            ->assertSee('No se mide');
    }

    /**
     * ⚠️⚠️ La vista previa NO puede pulsar por el cliente (`#796`): el `sandbox` vacío no impide que el marco navegue dentro de
     * sí mismo, así que el HTML llega con `<base target="_blank">` (bloqueado sin `allow-popups`) y sin la marca del envío.
     * Lo demás, igual: la UTM se queda.
     */
    public function test_the_preview_links_are_inert_and_carry_no_mark(): void
    {
        $send = $this->send(User::factory()->create()->id, 'account_already_exists', sentAt: now(), tracks: true);
        $key = (string) $send->send_key;
        $copia = '<html><head><title>x</title></head><body><a href="http://localhost:8081/login?utm_source=email&amp;utm_medium=account_already_exists&amp;jw_e='.$key.'">Entrar</a></body></html>';
        DB::table('email_sends')->where('id', $send->id)->update(['html' => $copia]);
        $send->refresh();

        $pintada = EmailSendTable::inert($copia, $key);

        $this->assertStringContainsString('<head><base target="_blank"><title>', $pintada);
        $this->assertStringNotContainsString('jw_e', $pintada);
        $this->assertStringContainsString('utm_medium=account_already_exists"', $pintada);
        $this->assertSame($copia, $send->fresh()?->html, 'la copia guardada no se toca');

        $html = EmailSendTable::previewAction()->record($send)->getModalContent()?->render() ?? '';
        $this->assertStringNotContainsString('jw_e', $html);
        $this->assertStringContainsString('&lt;base target=&quot;_blank&quot;&gt;', $html);
    }

    /** La línea de tiempo: cuándo salió y cada visita con su hora, cuánto después, qué enlace y desde qué; y deja rastro. */
    public function test_the_activity_shows_when_each_click_happened_and_leaves_a_trace(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 08:00:00', 'UTC'));
        $send = $this->send(User::factory()->create()->id, 'order_cancelled', sentAt: now(), tracks: true);
        DB::table('email_clicks')->insert([
            ['email_send_id' => $send->id, 'route' => '/login', 'device' => 'mobile', 'verdict' => null, 'clicked_at' => now()->addMinutes(13)],
            ['email_send_id' => $send->id, 'route' => '/', 'device' => 'desktop', 'verdict' => EmailClick::VERDICT_BOT, 'clicked_at' => now()->addMinutes(65)],
        ]);

        $eventos = EmailSendTable::activity($send);

        $this->assertSame(['sent', 'click', 'click'], array_column($eventos, 'kind'));
        $this->assertSame('29/09/2026 10:00', $eventos[0]['at'], 'en la hora del PARQUE (Madrid, UTC+2 en septiembre)');
        $this->assertSame('29/09/2026 10:13:00', $eventos[1]['at']);
        $this->assertSame('13 minutos después', $eventos[1]['after']);
        $this->assertSame(['/login', 'Móvil', true], [$eventos[1]['route'], $eventos[1]['device'], $eventos[1]['counts']]);
        $this->assertSame('1 hora 5 minutos después', $eventos[2]['after']);
        $this->assertFalse($eventos[2]['counts']);
        $this->assertSame('No cuenta: la vista previa de un chat o un robot', $eventos[2]['why']);

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->mountAction(TestAction::make('activity')->table($send))
            ->assertActionMounted(TestAction::make('activity')->table($send));

        $trace = AuditLog::query()->where('action', 'emails.activity_viewed')->sole();
        $this->assertSame((int) $send->id, (int) $trace->target_id);
        $this->assertStringNotContainsString('10:13', (string) json_encode($trace->payload), 'el rastro dice QUÉ envío se miró, no sus horas');

        $modal = EmailSendTable::activityAction()->record($send)->getModalContent()?->render() ?? '';
        $this->assertStringContainsString('/login', $modal);
        $this->assertStringContainsString('13 minutos después', $modal);
    }

    /** Las aperturas (la C3): las que cuentan, las automáticas aparte, «No se mide» sin píxel, el filtro y la ficha. */
    public function test_the_list_counts_the_opens_apart_from_the_automatic_ones(): void
    {
        $cliente = User::factory()->create();
        $abierto = $this->send($cliente->id, 'order_confirmation', sentAt: now(), opens: true);
        $this->open($abierto, EmailOpen::SOURCE_DIRECT, null);
        $this->open($abierto, EmailOpen::SOURCE_GMAIL, null);
        $this->open($abierto, EmailOpen::SOURCE_DIRECT, EmailOpen::VERDICT_REPEAT);
        $this->open($abierto, EmailOpen::SOURCE_APPLE, EmailOpen::VERDICT_APPLE);
        $sinAbrir = $this->send($cliente->id, 'order_cancelled', sentAt: now(), opens: true);
        $sinMedir = $this->send($cliente->id, 'survey_invitation', sentAt: now(), opens: false);

        $lista = Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertSee('Aperturas')
            ->assertSee('2 aperturas')
            ->assertSee('+1 automática')
            ->assertSee('Sin abrir');

        // ⚠️ Por la columna, no por el texto de la página: esa fila dice «No se mide» también en la de clics (lo cazó el arnés).
        $this->assertSame('No se mide', EmailSendTable::opens($sinMedir), 'sin píxel, un cero mentiría');
        $this->assertSame('Sin abrir', EmailSendTable::opens($sinAbrir));

        $lista->filterTable('opens', EmailSendTable::OPENED)->assertCanSeeTableRecords([$abierto])->assertCanNotSeeTableRecords([$sinAbrir, $sinMedir]);
        $lista->filterTable('opens', EmailSendTable::NOT_OPENED)->assertCanSeeTableRecords([$sinAbrir])->assertCanNotSeeTableRecords([$abierto, $sinMedir]);
        $lista->filterTable('opens', EmailSendTable::NOT_MEASURED)->assertCanSeeTableRecords([$sinMedir])->assertCanNotSeeTableRecords([$abierto, $sinAbrir]);

        Livewire::actingAs($this->withRole('admin'))->test(ViewUser::class, ['record' => $cliente->id])
            ->assertSee('2 aperturas');
    }

    /** En «Actividad», las aperturas con su origen y los clics, en el orden en que pasaron; la de Apple, con su porqué. */
    public function test_the_activity_puts_opens_and_clicks_in_the_order_they_happened(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-29 08:00:00', 'UTC'));
        $send = $this->send(User::factory()->create()->id, 'order_cancelled', sentAt: now(), tracks: true, opens: true);
        DB::table('email_opens')->insert([
            ['email_send_id' => $send->id, 'source' => EmailOpen::SOURCE_APPLE, 'device' => null, 'verdict' => EmailOpen::VERDICT_APPLE, 'opened_at' => now()->addSeconds(40)],
            ['email_send_id' => $send->id, 'source' => EmailOpen::SOURCE_GMAIL, 'device' => null, 'verdict' => null, 'opened_at' => now()->addHours(3)],
        ]);
        // Un clic ENTRE las dos aperturas: sin ordenar por hora, saldría detrás de las dos.
        DB::table('email_clicks')->insert(['email_send_id' => $send->id, 'route' => '/login', 'device' => 'mobile', 'verdict' => null, 'clicked_at' => now()->addHours(2)]);

        $eventos = EmailSendTable::activity($send);

        $this->assertSame(['sent', 'open', 'click', 'open'], array_column($eventos, 'kind'));
        $this->assertFalse($eventos[1]['counts']);
        $this->assertSame('No cuenta: Apple lo descarga al entregarlo, lo lea o no', $eventos[1]['why']);
        $this->assertSame(['Gmail', true, '29/09/2026 13:00:00'], [$eventos[3]['via'], $eventos[3]['counts'], $eventos[3]['at']]);
        $this->assertSame('3 horas después', $eventos[3]['after']);

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertActionVisible(TestAction::make('activity')->table($send));
    }

    /** El interruptor de las aperturas: APAGADO de fábrica y SEPARADO del de los clics (`#797`). */
    public function test_counting_opens_is_off_by_default_and_apart_from_the_clicks(): void
    {
        $this->seed(LandingContentSeeder::class);
        Setting::updateOrCreate(['key' => 'address.maps_url'], ['value' => '', 'group' => 'contact']);
        $this->assertFalse(EmailOpenMarks::enabled());

        Livewire::actingAs($this->withRole('admin'))->test(Settings::class)
            ->fillForm([EmailOpenMarks::SETTING => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(EmailOpenMarks::enabled());
        $this->assertFalse(EmailClickMarks::enabled(), 'encender las aperturas no enciende los clics');
    }

    public function test_without_the_mark_there_is_no_activity(): void
    {
        $send = $this->send(User::factory()->create()->id, 'order_confirmation', sentAt: now(), tracks: false);

        Livewire::actingAs($this->withRole('admin'))->test(ListEmailSends::class)
            ->assertActionHidden(TestAction::make('activity')->table($send));
    }

    /** El interruptor: APAGADO de fábrica (marca blanca, `[PENDIENTE: asesoría]`) y se enciende desde Ajustes. */
    public function test_counting_clicks_is_off_by_default_and_turned_on_from_the_settings(): void
    {
        // Los ajustes obligatorios de la página, como en `ThemeColorTest` (el `maps_url` del seeder no pasa su validación).
        $this->seed(LandingContentSeeder::class);
        Setting::updateOrCreate(['key' => 'address.maps_url'], ['value' => '', 'group' => 'contact']);
        $this->assertFalse(EmailClickMarks::enabled());

        Livewire::actingAs($this->withRole('admin'))->test(Settings::class)
            ->fillForm([EmailClickMarks::SETTING => true])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(EmailClickMarks::enabled());
        $this->assertSame('1', Setting::value(EmailClickMarks::SETTING));
    }

    public function test_the_customers_list_links_to_the_page_only_with_the_permission(): void
    {
        Livewire::actingAs($this->withRole('admin'))->test(ListUsers::class)
            ->assertSee(EmailSendResource::getUrl('index'), false);

        Livewire::actingAs($this->staffWhoSeesCustomers())->test(ListUsers::class)
            ->assertDontSee(EmailSendResource::getUrl('index'), false);
    }

    // ─── Andamios ────────────────────────────────────────────────────────────────────────────────

    /** Alguien del equipo que ve los clientes pero NO tiene `emails.view` (el control de las secciones). */
    private function staffWhoSeesCustomers(): User
    {
        $user = $this->withRole('staff');
        $user->roles()->first()?->permissions()->syncWithoutDetaching(
            Permission::query()->whereIn('name', ['users.view', 'users.manage'])->pluck('id')->all()
        );

        return $user->fresh() ?? $user;
    }

    private function withRole(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('name', $role)->value('id')]);

        return $user;
    }

    private function open(EmailSend $send, string $source, ?string $verdict): void
    {
        DB::table('email_opens')->insert(['email_send_id' => $send->id, 'source' => $source, 'verdict' => $verdict, 'opened_at' => now()]);
    }

    private function send(?int $userId, string $key, ?\DateTimeInterface $sentAt, int $failures = 0, ?string $recipient = 'cliente@example.test', ?string $html = '<p>Hola</p>', bool $tracks = false, bool $opens = false): EmailSend
    {
        $id = DB::table('email_sends')->insertGetId([
            'send_key' => (string) Str::uuid(), 'user_id' => $userId, 'recipient' => $recipient, 'mail_key' => $key,
            'subject' => 'Asunto', 'html' => $html, 'attachments' => '[]', 'failures' => $failures, 'tracks_clicks' => $tracks, 'tracks_opens' => $opens,
            'sent_at' => $sentAt, 'failed_at' => $failures > 0 ? now() : null, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return EmailSend::query()->findOrFail($id);
    }

    private function click(EmailSend $send, ?string $verdict): void
    {
        DB::table('email_clicks')->insert(['email_send_id' => $send->id, 'route' => '/', 'verdict' => $verdict, 'clicked_at' => now()]);
    }
}
