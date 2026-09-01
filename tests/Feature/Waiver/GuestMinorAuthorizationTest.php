<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Exceptions\GuardianAuthorizationExistsException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationHasSignaturesException;
use App\Domain\Identity\Exceptions\GuardianAuthorizationNotFoundException;
use App\Domain\Identity\Exceptions\WaiverEmailUnverifiedException;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverChain;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Identity\Services\WaiverSigner;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Fase 6 · el JUSTIFICANTE de un menor INVITADO a una reserva — «waiver offshore»
 * (`docs/specs/waiver-por-reserva.md`, tanda T1).
 *
 * ❗❗ **Los dos primeros casos son los BLOQUEANTES que encontró la revisión adversarial (§11.1), y se
 * escribieron para verlos FALLAR con el mecanismo anterior.** Los dos venían de lo mismo: la clave de
 * sujeto estaba cableada a `subject_id` y este sujeto no lo usa —
 *
 *  - `WaiverSigner` acotaba con `where('subject_id', $id)`, que con `null` Laravel convierte en
 *    `is null`: **todas** las autorizaciones de un responsable compartían búsqueda y la idempotencia
 *    por versión devolvía **la firma de otro menor**. El segundo padre veía «hecho», recibía su
 *    correo y su hijo se quedaba sin justificante, **sin fallo y sin aviso**.
 *  - `WaiverChain` agrupaba por `subject_type.':'.($subject_id ?? '')`, así que todos caían en
 *    `guest_minor:` y el verificador declaraba **ROTA una cadena sana**.
 *
 * ▶ La regla vive ahora en un solo sitio (`WaiverSignature::chainKey()` / `scopeInChain()`), y estos
 * casos son lo que impide que vuelva a escribirse a mano.
 */
class GuestMinorAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
        ])->first();
    }

    /** El RESPONSABLE: quien hizo la reserva. Su correo verificado por defecto (el caso normal). */
    private function responsible(bool $verified = true): User
    {
        return User::factory()->create(['email_verified_at' => $verified ? now() : null]);
    }

    /**
     * Un pedido PAGADO con una reserva REAL en el futuro.
     *
     * ⚠️ La línea no es decorado: desde la T2 el tope sale de las **líneas principales vivas**
     * (`AuthorizableOrdersReader`), así que un pedido sin ellas tiene capacidad **0** y no admite ni
     * un justificante. Los primeros fixtures de este fichero eran así — un pedido sin nada comprado
     * al que se le autorizan invitados es un mundo que no existe. **Se legalizan, no se excepciona
     * la regla.**
     */
    private function orderFor(User $responsible, int $quantity = 4): Order
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id,
            'date' => now()->addMonth()->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00',
            'capacity' => 200, 'online_capacity' => 200,
        ]);

        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 500, 'seats' => $quantity,
        ]);

        return $order;
    }

    /**
     * @param  array<string,mixed>  $overrides
     * @return array{authorization: GuardianAuthorization, signature: WaiverSignature, created: bool}
     */
    private function signFor(User $responsible, Order $order, LegalDocumentVersion $version, array $overrides = []): array
    {
        return app(GuardianAuthorizationSigner::class)->sign(
            $responsible,
            (int) $order->id,
            $version,
            array_merge([
                'minor_name' => 'Ana',
                'minor_surname' => 'Gómez Ruiz',
                'minor_born_on' => '2018-05-04',
                'guardian_name' => 'Marta',
                'guardian_surname' => 'Ruiz Díaz',
                'guardian_relationship' => 'mother',
                'guardian_email' => 'marta@example.com',
                'guardian_phone' => '600111222',
            ], $overrides),
            WaiverSignatureRequest::web('10.0.0.9', 'Mozilla/5.0 (padre)'),
        );
    }

    // ─── BLOQUEANTE 1 · la idempotencia NO se cruza entre autorizaciones ──────

    public function test_two_different_parents_of_the_same_order_each_get_their_own_signature(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $first = $this->signFor($responsible, $order, $version);
        $second = $this->signFor($responsible, $order, $version, [
            'minor_name' => 'Luis',
            'minor_surname' => 'Pérez Soto',
            'minor_born_on' => '2016-11-20',
            'guardian_name' => 'Carlos',
            'guardian_surname' => 'Pérez Gil',
            'guardian_relationship' => 'father',
            'guardian_email' => 'carlos@example.com',
            'guardian_phone' => '600333444',
        ]);

        // Lo que fallaba: la segunda firma era LA MISMA fila que la primera.
        $this->assertNotSame(
            $first['signature']->id,
            $second['signature']->id,
            'el segundo padre recibía la firma del primero y su hijo se quedaba sin justificante',
        );
        $this->assertNotSame($first['authorization']->id, $second['authorization']->id);
        $this->assertSame(2, WaiverSignature::where('subject_type', WaiverSignature::SUBJECT_GUEST_MINOR)->count());
        $this->assertSame(2, GuardianAuthorization::where('order_id', $order->id)->count());

        // Cada uno lleva SU menor y SU adulto, no los del otro.
        $this->assertSame('Ana Gómez Ruiz', $first['signature']->subject_name);
        $this->assertSame('Marta Ruiz Díaz', $first['signature']->signer_name);
        $this->assertSame('Luis Pérez Soto', $second['signature']->subject_name);
        $this->assertSame('Carlos Pérez Gil', $second['signature']->signer_name);
    }

    // ─── BLOQUEANTE 2 · cada autorización tiene su PROPIA cadena ──────────────

    public function test_each_authorisation_is_its_own_chain_and_the_verifier_stays_green(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $first = $this->signFor($responsible, $order, $version);
        $second = $this->signFor($responsible, $order, $version, [
            'minor_name' => 'Luis', 'minor_surname' => 'Pérez Soto', 'minor_born_on' => '2016-11-20',
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil', 'guardian_relationship' => 'father',
        ]);

        // Cada cadena empieza de cero: son sujetos distintos, no una cadena con dos eslabones.
        $this->assertNull($first['signature']->prev_hash);
        $this->assertNull($second['signature']->prev_hash);

        $verdict = WaiverChain::verify($responsible);
        $this->assertTrue($verdict['ok'], 'el verificador declaraba ROTA una cadena perfectamente sana');
        $this->assertSame(2, $verdict['count']);
        $this->assertSame(2, $verdict['chains'], 'las dos autorizaciones caían en la MISMA cadena');
        $this->assertSame([], $verdict['problems']);

        // Y la clave es distinta de verdad, no solo el veredicto.
        $this->assertNotSame($first['signature']->chainKey(), $second['signature']->chainKey());
    }

    public function test_the_chain_key_separates_the_three_kinds_of_subject(): void
    {
        $holder = WaiverSignature::chainKeyFor(WaiverSignature::SUBJECT_HOLDER, null, null);
        $dependent = WaiverSignature::chainKeyFor(WaiverSignature::SUBJECT_DEPENDENT, 7, null);
        $guestA = WaiverSignature::chainKeyFor(WaiverSignature::SUBJECT_GUEST_MINOR, null, 7);
        $guestB = WaiverSignature::chainKeyFor(WaiverSignature::SUBJECT_GUEST_MINOR, null, 8);

        $this->assertSame(4, collect([$holder, $dependent, $guestA, $guestB])->unique()->count());
        // El formato de los dos sujetos ANTIGUOS no cambia: la salida de los verificadores sigue
        // diciendo lo mismo que antes de esta tanda.
        $this->assertSame('holder:', $holder);
        $this->assertSame('dependent:7', $dependent);
        // Y un menor invitado NUNCA colisiona con un menor a cargo del mismo id.
        $this->assertNotSame(WaiverSignature::chainKeyFor(WaiverSignature::SUBJECT_DEPENDENT, 7, null), $guestA);
    }

    // ─── La prueba: las TRES personas viajan en la fila ───────────────────────

    public function test_the_signature_carries_the_three_identities_and_verifies(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        ['authorization' => $authorization, 'signature' => $signature, 'created' => $created] = $this->signFor($responsible, $order, $version);

        $this->assertTrue($created);
        $this->assertSame(WaiverSignature::SUBJECT_GUEST_MINOR, $signature->subject_type);
        $this->assertNull($signature->subject_id, 'el sujeto NO cuelga de `dependents` (FK dura)');
        $this->assertSame($authorization->id, $signature->subject_authorization_id);

        // 1 · el RESPONSABLE (quien reservó), 2 · el MENOR, 3 · quien FIRMA.
        $this->assertSame($responsible->id, $signature->user_id);
        $this->assertSame($responsible->name, $signature->holder_name);
        $this->assertSame($responsible->email, $signature->holder_email);
        $this->assertSame('Ana Gómez Ruiz', $signature->subject_name);
        $this->assertSame('2018-05-04', $signature->fresh()->subject_born_on->toDateString());
        $this->assertSame('Marta Ruiz Díaz', $signature->signer_name);
        $this->assertSame('marta@example.com', $signature->signer_email);
        $this->assertSame('600111222', $signature->signer_phone);
        $this->assertSame('mother', $signature->signer_relationship);

        $this->assertSame(4, $signature->fresh()->canonical_version);
        $this->assertTrue($signature->fresh()->verifyHash());
        $this->assertTrue($signature->isForGuestMinor());
        $this->assertFalse($signature->isForHolder());

        // NO es una firma del titular: ni consent visible ni sello. Su cuenta no ha aceptado nada.
        $this->assertSame(0, $responsible->consents()->count());
        $this->assertNull($responsible->fresh()->waiver_accepted_at);

        // El rastro dice el sujeto y el id, jamás el nombre del menor (`RGPD-02`).
        $log = AuditLog::where('action', 'waiver.signed')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(WaiverSignature::SUBJECT_GUEST_MINOR, $log->payload['subject_type']);
        $this->assertSame($authorization->id, $log->payload['authorization_id']);
        $this->assertStringNotContainsString('Ana', json_encode($log->payload, JSON_THROW_ON_ERROR));
    }

    // ─── «Un niño, un papel» (§7·9) e idempotencia ────────────────────────────

    public function test_the_same_parent_resending_the_form_writes_nothing_new(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $first = $this->signFor($responsible, $order, $version);
        // Mismo menor y mismo adulto, escritos de otra forma: es un reenvío, no otra persona.
        $second = $this->signFor($responsible, $order, $version, [
            'minor_name' => 'ANA', 'minor_surname' => 'gomez  ruiz',
            'guardian_name' => 'MARTA', 'guardian_surname' => 'ruiz diaz',
        ]);

        $this->assertSame($first['authorization']->id, $second['authorization']->id);
        $this->assertSame($first['signature']->id, $second['signature']->id);
        $this->assertFalse($second['created']);
        $this->assertSame(1, GuardianAuthorization::count());
        $this->assertSame(1, WaiverSignature::count());
    }

    public function test_a_second_parent_of_the_same_minor_is_told_it_is_already_signed(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $this->signFor($responsible, $order, $version);

        try {
            // El otro progenitor: mismo niño, adulto distinto.
            $this->signFor($responsible, $order, $version, [
                'guardian_name' => 'Javier', 'guardian_surname' => 'Gómez Lara', 'guardian_relationship' => 'father',
                'guardian_email' => 'javier@example.com', 'guardian_phone' => '600999888',
            ]);
            $this->fail('el segundo progenitor del mismo menor tiene que ver que ya está firmado');
        } catch (GuardianAuthorizationExistsException $e) {
            // El mensaje lleva el nombre del menor para que la pantalla sea útil…
            $this->assertSame('Ana Gómez Ruiz', $e->minorName);
        }

        // …y NO se escribe nada: ni segunda autorización, ni segunda firma, ni el adulto nuevo.
        $this->assertSame(1, GuardianAuthorization::count());
        $this->assertSame(1, WaiverSignature::count());
        $this->assertDatabaseMissing('waiver_signatures', ['signer_email' => 'javier@example.com']);
    }

    public function test_a_new_version_of_the_text_adds_a_link_to_the_same_authorisation(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);

        $v1 = $this->version();
        $first = $this->signFor($responsible, $order, $v1);
        $v2 = $this->version();
        $second = $this->signFor($responsible, $order, $v2);

        $this->assertSame($first['authorization']->id, $second['authorization']->id, 'la persona es la misma');
        $this->assertNotSame($first['signature']->id, $second['signature']->id);
        $this->assertSame($first['signature']->hash, $second['signature']->prev_hash, 'la cadena de ESA autorización crece');

        $verdict = WaiverChain::verify($responsible);
        $this->assertTrue($verdict['ok']);
        $this->assertSame(1, $verdict['chains']);
        $this->assertSame(1, GuardianAuthorization::count());
    }

    // ─── La clave del menor, determinista en los dos motores (§4.8) ───────────

    public function test_the_minor_key_ignores_case_accents_and_extra_spaces(): void
    {
        $canonical = GuardianAuthorization::keyFor('Ana', 'Pérez');

        $this->assertSame($canonical, GuardianAuthorization::keyFor('ana', 'perez'));
        $this->assertSame($canonical, GuardianAuthorization::keyFor('ANA', 'PÉREZ'));
        $this->assertSame($canonical, GuardianAuthorization::keyFor('  Ana  ', ' Pérez '));
        $this->assertSame($canonical, GuardianAuthorization::keyFor('Ana', 'Perez'));
        // …y sigue distinguiendo a dos personas distintas.
        $this->assertNotSame($canonical, GuardianAuthorization::keyFor('Ana', 'Pereza'));
    }

    /**
     * ⚠️ Sin el respaldo, `Str::ascii()` deja en `''` un nombre íntegramente en otro alfabeto y
     * **todos** esos menores colisionarían en la misma clave dentro de un pedido — o sea que el
     * segundo vería «ya tiene justificante» siendo otro niño.
     */
    public function test_two_names_in_a_non_latin_script_do_not_collapse_into_the_same_key(): void
    {
        $one = GuardianAuthorization::keyFor('李', '伟');
        $other = GuardianAuthorization::keyFor('王', '芳');

        $this->assertNotSame('', $one);
        $this->assertNotSame($one, $other);
    }

    // ─── §7·8 · esta rama NO exige el correo verificado del responsable ───────

    public function test_a_parent_can_sign_even_if_the_responsible_has_no_verified_email(): void
    {
        // El caso real: un colegio se da de alta POR TELÉFONO y `CustomerRegistrar` deja
        // `email_verified_at` en null a propósito. Con la puerta puesta, NINGÚN padre podría firmar.
        $responsible = $this->responsible(verified: false);
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $result = $this->signFor($responsible, $order, $version);

        $this->assertNotNull($result['signature']->id);
        $this->assertTrue($result['signature']->fresh()->verifyHash());
    }

    public function test_the_holder_still_needs_a_verified_email_for_his_own_signature(): void
    {
        // El control: la decisión del 2026-08-26 sigue en pie para la firma del PROPIO titular.
        $responsible = $this->responsible(verified: false);
        $version = $this->version();

        $this->expectException(WaiverEmailUnverifiedException::class);
        app(WaiverSigner::class)->sign($responsible, $version, WaiverSignatureRequest::web('10.0.0.9', 'UA'));
    }

    // ─── Guardas del firmador ─────────────────────────────────────────────────

    public function test_signing_a_guest_minor_without_an_authorisation_is_a_programming_error(): void
    {
        $responsible = $this->responsible();
        $version = $this->version();

        $this->expectException(InvalidArgumentException::class);
        app(WaiverSigner::class)->sign(
            $responsible,
            $version,
            new WaiverSignatureRequest(WaiverSignature::CHANNEL_WEB, '10.0.0.9', 'UA', WaiverSignature::SUBJECT_GUEST_MINOR),
        );
    }

    public function test_signing_for_an_authorisation_that_does_not_exist_is_refused(): void
    {
        $responsible = $this->responsible();
        $version = $this->version();

        $this->expectException(GuardianAuthorizationNotFoundException::class);
        app(WaiverSigner::class)->sign(
            $responsible,
            $version,
            WaiverSignatureRequest::web('10.0.0.9', 'UA')->forGuestMinor(999999),
        );
    }

    // ─── La fila no se borra mientras su prueba exista ────────────────────────

    public function test_an_authorisation_with_a_signature_behind_cannot_be_deleted(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        ['authorization' => $authorization] = $this->signFor($responsible, $order, $this->version());

        $this->expectException(GuardianAuthorizationHasSignaturesException::class);
        $authorization->delete();
    }

    // ─── Poda por plazo (§4.14) ───────────────────────────────────────────────

    public function test_the_prune_takes_the_signature_by_the_minor_period_and_then_the_orphan_row(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        // Un menor que cumplió 18 hace mucho: entra en el plazo de menor, que es el que le toca.
        ['authorization' => $authorization, 'signature' => $signature] = $this->signFor(
            $responsible, $order, $this->version(), ['minor_born_on' => now()->subYears(40)->toDateString()],
        );

        // Sin plazo NO se poda nada: es la conducta por defecto y sigue.
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class, GuardianAuthorization::class]])->assertOk();
        $this->assertDatabaseHas('waiver_signatures', ['id' => $signature->id]);
        $this->assertDatabaseHas('guardian_authorizations', ['id' => $authorization->id]);

        Setting::query()->updateOrCreate(
            ['key' => WaiverSettings::KEY_DEPENDENT_RETENTION_MONTHS],
            ['value' => '12'],
        );
        Setting::flushMemo();

        // ⚠️ El ORDEN importa y es el del scheduler: la firma primero (la FK es RESTRICT), la fila
        // huérfana después. Al revés, `model:prune` abortaría a mitad.
        $this->artisan('model:prune', ['--model' => [WaiverSignature::class, GuardianAuthorization::class]])->assertOk();

        $this->assertDatabaseMissing('waiver_signatures', ['id' => $signature->id]);
        $this->assertDatabaseMissing('guardian_authorizations', ['id' => $authorization->id]);
    }

    public function test_an_authorisation_that_still_has_its_signature_is_never_pruned(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        ['authorization' => $authorization] = $this->signFor(
            $responsible, $order, $this->version(), ['minor_born_on' => now()->subYears(6)->toDateString()],
        );

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_DEPENDENT_RETENTION_MONTHS], ['value' => '12']);
        Setting::flushMemo();

        $this->artisan('model:prune', ['--model' => [WaiverSignature::class, GuardianAuthorization::class]])->assertOk();

        $this->assertDatabaseHas('guardian_authorizations', ['id' => $authorization->id]);
    }

    // ─── RGPD · la prueba sobrevive a la supresión del RESPONSABLE ────────────

    public function test_anonymising_the_responsible_keeps_the_authorisation_and_its_signature(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        ['authorization' => $authorization, 'signature' => $signature] = $this->signFor($responsible, $order, $this->version());

        $responsible->anonymize();

        // La prueba no es del tutor para que él la borre: el parque se quedaría sin la prueba de una
        // visita que ocurrió, y esos datos no son suyos (art. 17.3.e).
        $this->assertDatabaseHas('waiver_signatures', ['id' => $signature->id]);
        $this->assertDatabaseHas('guardian_authorizations', ['id' => $authorization->id]);
        // Y sigue identificando a las tres personas, con el responsable ya anónimo en `users`.
        $fresh = $signature->fresh();
        $this->assertSame('Ana Gómez Ruiz', $fresh->subject_name);
        $this->assertSame('Marta Ruiz Díaz', $fresh->signer_name);
        $this->assertSame($responsible->fresh()->id, $fresh->user_id);
        $this->assertTrue($fresh->verifyHash(), 'la supresión no puede romper el hash de una prueba');
    }

    // ─── Un menor a cargo y uno invitado conviven sin estorbarse ──────────────

    public function test_a_dependent_and_a_guest_minor_of_the_same_holder_keep_separate_chains(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();
        $dependent = Dependent::create(['user_id' => $responsible->id, 'name' => 'Lucas', 'born_on' => '2017-03-12']);

        app(WaiverSigner::class)->sign($responsible, $version, WaiverSignatureRequest::web('10.0.0.7', 'UA')->forDependent($dependent->id));
        $this->signFor($responsible, $order, $version);
        app(WaiverSigner::class)->sign($responsible, $version, WaiverSignatureRequest::web('10.0.0.7', 'UA'));

        $verdict = WaiverChain::verify($responsible);
        $this->assertTrue($verdict['ok']);
        $this->assertSame(3, $verdict['count']);
        $this->assertSame(3, $verdict['chains'], 'titular, menor a cargo y menor invitado son TRES cadenas');
    }
}
