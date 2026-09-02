<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\GuardianRoster;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverProof;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use App\Notifications\GuardianAuthorizationSigned;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — **LAS SUPERFICIES DE DENTRO** (tanda T3,
 * `docs/specs/waiver-por-reserva.md` §4.10–§4.15).
 *
 * Lo que vigila, por orden de lo que puede doler:
 *
 *  1. **Que el RESPONSABLE no vea a los otros padres.** `[DECIDIDO owner]` §7·4: ve los nombres de
 *     los menores y quién falta, **nunca** correo, teléfono ni relación de otro adulto. La regla la
 *     impone el TIPO (`GuardianRoster` tiene dos formas), no la disciplina de cada plantilla.
 *  2. **Que el PDF diga que nada está verificado**, y que solo lo alcancen el operador con permiso y
 *     quien firmó. Es el documento que se enseñaría si alguien reclama.
 *  3. **Que la puerta no pague un N+1**: la ficha se compone con un número constante de consultas y
 *     ya cazó uno en `#294`.
 */
class GuestMinorSurfacesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ❗❗ **El reloj va CONGELADO, y lo pidió la auditoría** (`scripts/audit-clock.sh`, `TESTING.md`
     * §2). Este fichero siembra una franja de **HOY** —la puerta solo enseña las reservas del día— que
     * termina a las 23:00, y **cerca de medianoche esa visita ya ha pasado**: el dominio se niega a
     * autorizar sobre una visita terminada y los DIEZ casos se ponían rojos. Medido: verdes a
     * cualquier hora normal y rojos a las 21:59:30 de Madrid y a las 23:59:30 UTC.
     *
     * ▶ *Un test que solo falla ciertas noches está rojo y aún no lo sabes.* La hora elegida —09:00
     * UTC, mediodía en Madrid— deja la franja abierta con catorce horas de margen y cae en el mismo
     * día natural en las dos zonas, que es la otra frontera que la auditoría barre.
     */
    private const FROZEN_NOW = '2026-06-15 09:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::FROZEN_NOW);

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    private function orderFor(User $responsible, ?string $date = null): Order
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => $date ?? now()->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '23:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => 10, 'unit_price' => 500, 'seats' => 10,
        ]);

        return $order;
    }

    /** @return array{authorization: GuardianAuthorization, signature: WaiverSignature, created: bool} */
    private function authorize(User $responsible, Order $order, string $minor, string $guardian, ?string $email = 'carlos@example.com'): array
    {
        return app(GuardianAuthorizationSigner::class)->sign(
            $responsible,
            // ⚠️ El sujeto es la RESERVA desde `#401`, no el pedido: un pedido puede tener dos
            // visitas y el padre autoriza una.
            (int) $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail()->id,
            LegalDocumentVersion::query()->latest('id')->first() ?? $this->version(),
            [
                'minor_name' => $minor, 'minor_surname' => 'Pérez Soto',
                'minor_born_on' => now()->subYears(9)->toDateString(),
                'guardian_name' => $guardian, 'guardian_surname' => 'Pérez Gil',
                'guardian_relationship' => 'father',
                'guardian_email' => $email, 'guardian_phone' => '600333444',
            ],
            WaiverSignatureRequest::web('10.0.0.1', 'UA'),
        );
    }

    // ─── 1 · Las DOS formas del roster ────────────────────────────────────────

    public function test_the_responsible_form_carries_no_data_of_the_other_parents(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $this->version();
        $this->authorize($responsible, $order, 'Luis', 'Carlos');

        $mine = app(GuardianRoster::class)->forResponsible((int) $order->id);
        $operator = app(GuardianRoster::class)->forOperator((int) $order->id);

        // Lo que SÍ ve: el menor y su estado (control — sin esto, un roster vacío pasaría igual).
        $this->assertCount(1, $mine);
        $this->assertSame('Luis Pérez Soto', $mine[0]['minor']);
        $this->assertSame('current', $mine[0]['waiver']);

        // Y lo que NO: la forma no tiene esas claves, así que ninguna plantilla puede pintarlas
        // «por error». La regla la impone el TIPO.
        foreach (['guardian', 'relationship', 'born_on', 'signed_on'] as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $mine[0], "la forma del responsable no puede llevar «{$forbidden}»");
        }

        // El CONTROL de que la otra forma sí lo lleva: si no, este test pasaría con las dos vacías.
        $this->assertSame('Carlos Pérez Gil', $operator[0]['guardian']);
        $this->assertSame('father', $operator[0]['relationship']);
    }

    public function test_neither_form_carries_the_contact_details_of_the_guardian(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $this->version();
        $this->authorize($responsible, $order, 'Luis', 'Carlos');

        // El correo y el teléfono están en la FILA y en la prueba, y ahí se quedan: la puerta no los
        // necesita para dejar pasar y el panel tampoco para recibir a un niño.
        foreach ([app(GuardianRoster::class)->forOperator((int) $order->id)[0],
            app(GuardianRoster::class)->forResponsible((int) $order->id)[0]] as $row) {
            $this->assertStringNotContainsString('carlos@example.com', json_encode($row, JSON_THROW_ON_ERROR));
            $this->assertStringNotContainsString('600333444', json_encode($row, JSON_THROW_ON_ERROR));
        }
    }

    // ─── 2 · La puerta ────────────────────────────────────────────────────────

    public function test_the_gate_lists_the_guest_minors_of_todays_orders_with_their_first_name(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $this->version();
        $this->authorize($responsible, $order, 'Luis', 'Carlos');

        $profile = app(GateProfile::class)->for($responsible, now(), 7)->toArray();

        $this->assertCount(1, $profile['guest_minors']);
        $this->assertSame('Luis', $profile['guest_minors'][0]['name']);
        $this->assertSame($order->code, $profile['guest_minors'][0]['order_code']);
        $this->assertSame(9, $profile['guest_minors'][0]['age']);
        $this->assertSame('current', $profile['guest_minors'][0]['waiver']);
        // ⚠️ Los APELLIDOS no tienen campo aquí y es estructural (`#236`): distinguir a un niño de
        // otro en un mostrador no los necesita.
        $this->assertArrayNotHasKey('surname', $profile['guest_minors'][0]);
        $this->assertStringNotContainsString('Pérez Soto', json_encode($profile['guest_minors'], JSON_THROW_ON_ERROR));
    }

    public function test_a_guest_minor_of_another_day_does_not_reach_the_gate(): void
    {
        // El CONTROL de la acotación a HOY: la ventana de ±N días es contexto, y un justificante
        // sirve para dejar entrar a alguien que está delante.
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible, date: now()->addDays(3)->toDateString());
        $this->version();
        $this->authorize($responsible, $order, 'Luis', 'Carlos');

        $profile = app(GateProfile::class)->for($responsible, now(), 7)->toArray();

        $this->assertSame([], $profile['guest_minors']);
        $this->assertNotSame([], $profile['window'], 'la reserva sí está en la ventana: lo acotado son los justificantes');
    }

    /**
     * ⚠️ El presupuesto de la puerta es una restricción de DISEÑO, no una comprobación posterior:
     * `GateProfileTest` fija el techo en 28 y ya cazó un N+1 en `#294`. Aquí se mide que **no crece
     * con el número de menores invitados**, que es la forma en que este bloque podría meterlo.
     */
    public function test_the_gate_does_not_pay_a_query_per_guest_minor(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $this->version();
        $this->authorize($responsible, $order, 'Uno', 'Adulto1');

        $one = $this->countQueries(fn () => app(GateProfile::class)->for($responsible->fresh(), now(), 7));

        foreach (['Dos', 'Tres', 'Cuatro', 'Cinco', 'Seis'] as $i => $name) {
            $this->authorize($responsible, $order, $name, 'Adulto'.($i + 2));
        }

        $six = $this->countQueries(fn () => app(GateProfile::class)->for($responsible->fresh(), now(), 7));

        $this->assertSame($one, $six, "con 6 menores invitados se hacen {$six} consultas y con 1 se hacen {$one}");
    }

    private function countQueries(callable $run): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $run();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // ─── 3 · El PDF ───────────────────────────────────────────────────────────

    public function test_the_proof_names_the_signer_the_minor_and_the_responsible_and_says_nothing_is_verified(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);

        $responsible = User::factory()->create(['email_verified_at' => now(), 'name' => 'Colegio Ejemplo']);
        $order = $this->orderFor($responsible);
        $this->version();
        ['signature' => $signature] = $this->authorize($responsible, $order, 'Luis', 'Carlos');

        $admin = User::factory()->create(['email_verified_at' => now()]);
        $admin->roles()->sync([Role::where('name', 'admin')->value('id')]);

        $html = view('pdf.waiver-proof', ['proof' => WaiverProof::make($signature)])->render();

        // Las TRES personas, con su papel dicho.
        $this->assertStringContainsString('Luis Pérez Soto', $html);
        $this->assertStringContainsString('Carlos Pérez Gil', $html);
        $this->assertStringContainsString('Colegio Ejemplo', $html);
        $this->assertStringContainsString($order->code, $html);
        // Y la nota propia: aquí lo declara un DESCONOCIDO, no alguien con cuenta y correo verificado.
        $this->assertStringContainsString(__('waiver.proof.subject_guest_minor_note'), $html);
        // El CONTROL: no se cuela la nota del menor a cargo, que dice otra cosa.
        $this->assertStringNotContainsString(__('waiver.proof.subject_dependent_note'), $html);
    }

    public function test_the_responsible_cannot_reach_the_proof_of_a_guest_minor(): void
    {
        // Ya lo fija `MeWaiverGuestMinorTest` para la API; aquí se deja escrito el porqué: la prueba
        // lleva el nombre, el correo y el teléfono de un adulto que NO es el titular de la cuenta.
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $this->version();
        ['signature' => $signature] = $this->authorize($responsible, $order, 'Luis', 'Carlos');

        $this->assertSame(0, $responsible->waiverSignatures()->count());
        $this->assertSame(1, $responsible->guardianSignatures()->count());
        $this->assertSame($signature->id, $responsible->guardianSignatures()->first()->id);
    }

    // ─── 4 · El correo de copia ───────────────────────────────────────────────

    public function test_signing_sends_the_copy_to_the_email_the_parent_declared(): void
    {
        Notification::fake();

        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $this->post(URL::temporarySignedRoute('reservation.authorization.store', now()->addDays(14), ['reservation' => $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail()]), [
            'document_id' => $version->getKey(), 'accept_waiver' => '1',
            'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto',
            'minor_born_on' => now()->subYears(9)->toDateString(),
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil',
            'guardian_relationship' => 'father', 'guardian_email' => 'carlos@example.com',
        ])->assertSessionHas('guardian_status', 'signed');

        Notification::assertSentOnDemand(
            GuardianAuthorizationSigned::class,
            fn ($notification, array $channels, $notifiable): bool => $notifiable->routes['mail'] === 'carlos@example.com',
        );
    }

    public function test_without_an_email_nothing_is_sent_and_the_signature_stands(): void
    {
        Notification::fake();

        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $this->post(URL::temporarySignedRoute('reservation.authorization.store', now()->addDays(14), ['reservation' => $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail()]), [
            'document_id' => $version->getKey(), 'accept_waiver' => '1',
            'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto',
            'minor_born_on' => now()->subYears(9)->toDateString(),
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil',
            'guardian_relationship' => 'father',
        ])->assertSessionHas('guardian_status', 'signed');

        // ⚠️ El correo es una CORTESÍA: la prueba existe igual. Que no haya buzón no puede impedir
        // que un niño quede autorizado.
        Notification::assertNothingSent();
        $this->assertSame(1, GuardianAuthorization::count());
    }

    /**
     * ❗❗ **El ASUNTO no puede nombrar al menor** (`DECISIONES #406`, encontrado por la revisión
     * adversarial del subsistema).
     *
     * `guardian_email` es un campo TECLEADO por un adulto sin cuenta, `nullable` y validado solo con
     * `email:filter`: nadie comprueba que ese buzón sea suyo. Una errata —`@gmial.com`— manda el
     * correo a un desconocido.
     *
     * ⚠️⚠️ **Y el asunto es la peor mitad**: se replica en la previsualización de la bandeja, en la
     * pantalla de bloqueo del móvil, en los logs del servidor de correo y en los REBOTES, que citan
     * asunto y cabeceras — sitios a los que el adjunto no llega. El cuerpo y el PDF los lee quien
     * abre el mensaje; el asunto lo ve cualquiera que mire la pantalla.
     *
     * ▶ Por eso el nombre del menor sale del asunto y se queda en el cuerpo, que es donde el
     * destinatario legítimo necesita saber por quién firmó.
     */
    public function test_the_subject_never_names_the_minor(): void
    {
        Notification::fake();

        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $this->post(URL::temporarySignedRoute('reservation.authorization.store', now()->addDays(14), ['reservation' => $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail()]), [
            'document_id' => $version->getKey(), 'accept_waiver' => '1',
            'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto',
            'minor_born_on' => now()->subYears(9)->toDateString(),
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil',
            'guardian_relationship' => 'father', 'guardian_email' => 'carlos@example.com',
        ])->assertSessionHas('guardian_status', 'signed');

        Notification::assertSentOnDemand(
            GuardianAuthorizationSigned::class,
            function ($notification, array $channels, $notifiable): bool {
                $mail = $notification->toMail($notifiable);

                // El nombre del menor NO, en ninguna de sus dos mitades ni completo.
                $this->assertStringNotContainsString('Luis', (string) $mail->subject);
                $this->assertStringNotContainsString('Pérez Soto', (string) $mail->subject);

                // ⚠️ **CONTROL de que la sonda mira donde cree**: el cuerpo SÍ lo nombra, así que un
                // asunto vacío o un `toMail()` que no compusiera nada pasarían el bloque de arriba sin
                // decir nada. Si esto se rompe, el instrumento dejó de leer el correo de verdad.
                $this->assertStringContainsString('Luis Pérez Soto', implode(' ', $mail->introLines));
                $this->assertNotSame('', (string) $mail->subject);

                return true;
            },
        );
    }

    public function test_a_resend_by_the_same_parent_does_not_send_a_second_copy(): void
    {
        Notification::fake();

        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $version = $this->version();
        $payload = [
            'document_id' => $version->getKey(), 'accept_waiver' => '1',
            'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto',
            'minor_born_on' => now()->subYears(9)->toDateString(),
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil',
            'guardian_relationship' => 'father', 'guardian_email' => 'carlos@example.com',
        ];
        $url = fn (): string => URL::temporarySignedRoute('reservation.authorization.store', now()->addDays(14), ['reservation' => $order->items()->whereNull('parent_item_id')->orderBy('id')->firstOrFail()]);

        $this->post($url(), $payload);
        $this->post($url(), $payload);

        Notification::assertSentOnDemandTimes(GuardianAuthorizationSigned::class, 1);
        $this->assertSame(1, GuardianAuthorization::count());
    }

    // ─── 5 · Un menor a cargo y uno invitado no se mezclan en la puerta ───────

    public function test_dependents_and_guest_minors_are_separate_lists_at_the_gate(): void
    {
        $responsible = User::factory()->create(['email_verified_at' => now()]);
        $order = $this->orderFor($responsible);
        $this->version();
        Dependent::create(['user_id' => $responsible->id, 'name' => 'Marta', 'born_on' => now()->subYears(7)->toDateString()]);
        $this->authorize($responsible, $order, 'Luis', 'Carlos');

        $profile = app(GateProfile::class)->for($responsible, now(), 7)->toArray();

        // Son personas distintas con un régimen distinto: mezclarlas haría creer al operador que
        // este adulto responde por todas.
        $this->assertSame(['Marta'], array_column($profile['dependents'], 'name'));
        $this->assertSame(['Luis'], array_column($profile['guest_minors'], 'name'));
    }
}
