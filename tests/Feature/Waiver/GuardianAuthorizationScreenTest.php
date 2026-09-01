<?php

namespace Tests\Feature\Waiver;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Fase 6 · el JUSTIFICANTE de un menor invitado — **LA PANTALLA PÚBLICA**
 * (`docs/specs/waiver-por-reserva.md` §4.6, §4.7; tanda T2).
 *
 * Es la superficie más expuesta del producto: **pública, sin sesión y CREA PERSONAS**. Lo que este
 * fichero vigila, en este orden de importancia:
 *
 *  1. **Que no filtre nada.** Es una HOJA EN BLANCO: quien la abre no puede ver ni un dato de los
 *     justificantes que ya firmaron otros padres. Ésa es la razón por la que este enlace se puede
 *     repartir y el del post-form no.
 *  2. **Que la escalada de códigos sea 403 → 410 → 404 EN ESE ORDEN.** Invertirla deja que un
 *     desconocido deduzca por el código de estado si un pedido existe o está pagado.
 *  3. **Que las tres puertas del dominio manden sobre lo que pinta la pantalla**, porque entre pintar
 *     y enviar puede pasar la visita, cancelarse el pedido o llenarse el cupo.
 */
class GuardianAuthorizationScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La pantalla solo existe en modo INTERNO: fuera de él no hay texto que firmar aquí.
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();
    }

    private function version(): LegalDocumentVersion
    {
        return app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención de responsabilidad', 'body' => [
                ['h' => 'Riesgo asumido', 'p' => 'Saltar en camas elásticas implica riesgos.'],
            ]],
        ])->first();
    }

    private function responsible(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function orderFor(User $responsible, int $quantity = 4, ?string $date = null, string $status = Order::STATUS_PAID): Order
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id,
            'date' => $date ?? now()->addMonth()->toDateString(),
            'start_time' => '10:00:00', 'end_time' => '11:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);

        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => $status,
            'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR',
            'paid_at' => $status === Order::STATUS_PAID ? now() : null,
        ]);
        $order->items()->create([
            'ticket_type_id' => $type->id, 'slot_id' => $slot->id,
            'quantity' => $quantity, 'unit_price' => 500, 'seats' => $quantity,
        ]);

        return $order;
    }

    /** @return array<string, mixed> */
    private function payload(LegalDocumentVersion $version, array $overrides = []): array
    {
        return array_merge([
            'document_id' => $version->getKey(),
            'accept_waiver' => '1',
            'minor_name' => 'Ana',
            'minor_surname' => 'Gómez Ruiz',
            'minor_born_on' => now()->subYears(8)->toDateString(),
            'guardian_name' => 'Marta',
            'guardian_surname' => 'Ruiz Díaz',
            'guardian_relationship' => 'mother',
            'guardian_email' => 'marta@example.com',
            'guardian_phone' => '600111222',
        ], $overrides);
    }

    private function signedStoreUrl(Order $order): string
    {
        return URL::temporarySignedRoute(
            'reservation.authorization.store',
            now()->addDays(14),
            ['order' => $order],
        );
    }

    // ─── El camino feliz ──────────────────────────────────────────────────────

    public function test_a_parent_without_an_account_can_open_the_link_and_sign(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        $this->get($order->guardianAuthorizationSignedUrl())
            ->assertOk()
            ->assertSee('Exención de responsabilidad')
            ->assertSee('Saltar en camas elásticas implica riesgos.')
            ->assertSee($order->code);

        $this->post($this->signedStoreUrl($order), $this->payload($version))
            ->assertRedirect()
            ->assertSessionHas('guardian_status', 'signed');

        $this->assertSame(1, GuardianAuthorization::count());
        $signature = WaiverSignature::query()->where('subject_type', WaiverSignature::SUBJECT_GUEST_MINOR)->sole();
        $this->assertSame('Ana Gómez Ruiz', $signature->subject_name);
        $this->assertSame('Marta Ruiz Díaz', $signature->signer_name);
        $this->assertSame($responsible->id, $signature->user_id, 'el titular de la fila es el RESPONSABLE');
        $this->assertTrue($signature->fresh()->verifyHash());
    }

    // ─── 1 · CERO FUGA: es una hoja en blanco ─────────────────────────────────

    public function test_the_screen_shows_nothing_of_the_authorisations_already_signed(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $version = $this->version();

        // Dos padres ya han firmado.
        foreach ([['Luis', 'Pérez Soto', 'Carlos', 'Pérez Gil', 'carlos@example.com'],
            ['Nora', 'Blanco Díaz', 'Elena', 'Blanco Sanz', 'elena@example.com']] as $row) {
            app(GuardianAuthorizationSigner::class)->sign($responsible, (int) $order->id, $version, [
                'minor_name' => $row[0], 'minor_surname' => $row[1], 'minor_born_on' => '2016-01-01',
                'guardian_name' => $row[2], 'guardian_surname' => $row[3], 'guardian_relationship' => 'father',
                'guardian_email' => $row[4], 'guardian_phone' => '600000000',
            ], WaiverSignatureRequest::web('10.0.0.1', 'UA'));
        }

        $body = $this->get($order->guardianAuthorizationSignedUrl())->assertOk()->getContent();

        // Ni un byte de ninguno de los dos: ni menores, ni adultos, ni correos.
        foreach (['Luis', 'Pérez Soto', 'Carlos', 'carlos@example.com', 'Nora', 'Blanco Díaz', 'Elena', 'elena@example.com'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "la hoja en blanco filtró «{$leak}»");
        }
        // Tampoco el CONTADOR: cuántos han firmado es información del responsable, no de un padre.
        $this->assertStringNotContainsString('2 de 4', $body);
    }

    // ─── 2 · La escalada, y su ORDEN ──────────────────────────────────────────

    public function test_without_a_valid_signature_it_is_403_even_for_a_real_order(): void
    {
        $order = $this->orderFor($this->responsible());
        $this->version();

        $this->get(route('reservation.authorization', ['order' => $order]))->assertForbidden();
        $this->post(route('reservation.authorization.store', ['order' => $order]), [])->assertForbidden();
    }

    public function test_an_anonymised_holder_closes_the_channel_with_410(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $this->version();
        $url = $order->guardianAuthorizationSignedUrl();

        $responsible->anonymize();

        // 410 y no 404: el recurso existió y dejó de existir a propósito (art. 17).
        $this->get($url)->assertGone();
    }

    /**
     * ⚠️ El ORDEN es la propiedad, no los códigos sueltos. Con un pedido de un titular anonimizado
     * **y sin firma**, la respuesta tiene que ser **403** —no 410—: si autorizar fuera después de
     * comprobar la anonimización, un desconocido sabría por el código de estado que ese pedido existe
     * y que su titular ejerció la supresión.
     */
    public function test_authorisation_happens_before_the_gone_check(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $this->version();
        $responsible->anonymize();

        $this->get(route('reservation.authorization', ['order' => $order]))->assertForbidden();
    }

    public function test_outside_internal_mode_the_screen_does_not_exist(): void
    {
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_EXTERNAL]);
        Setting::flushMemo();

        $order = $this->orderFor($this->responsible());
        $this->version();

        // 404: no hay texto que firmar aquí. Y solo lo ve quien ya demostró acceso con la firma.
        $this->get($order->guardianAuthorizationSignedUrl())->assertNotFound();
    }

    public function test_without_a_published_version_there_is_nothing_to_sign(): void
    {
        $order = $this->orderFor($this->responsible());

        $this->get($order->guardianAuthorizationSignedUrl())->assertNotFound();
    }

    // ─── 3 · Las tres puertas ─────────────────────────────────────────────────

    public function test_an_unpaid_order_shows_why_and_does_not_write(): void
    {
        $order = $this->orderFor($this->responsible(), status: Order::STATUS_PENDING);
        $version = $this->version();

        $this->get($order->guardianAuthorizationSignedUrl())
            ->assertOk()
            ->assertSee(__('guardian.blocked.not_paid'))
            ->assertDontSee('name="minor_name"', false);

        // Y el POST forjado tampoco escribe: la puerta que manda está en el dominio.
        $this->post($this->signedStoreUrl($order), $this->payload($version))
            ->assertSessionHas('guardian_status', 'not_paid');
        $this->assertSame(0, GuardianAuthorization::count());
    }

    public function test_after_the_visit_the_form_closes_but_the_link_still_opens(): void
    {
        $order = $this->orderFor($this->responsible(), date: now()->subDays(2)->toDateString());
        $version = $this->version();

        $this->get($order->guardianAuthorizationSignedUrl())
            ->assertOk()
            ->assertSee(__('guardian.blocked.closed'));

        // Los DOS, porque un POST forjado desde una pestaña vieja es el caso real, no el hipotético.
        $this->post($this->signedStoreUrl($order), $this->payload($version))
            ->assertSessionHas('guardian_status', 'closed');
        $this->assertSame(0, GuardianAuthorization::count());
    }

    /**
     * ⚠️ Este caso lo pidió el ARNÉS, no el diseño: la mutación que quitaba el `isNotEmpty()` de
     * `visitFinished` **no mordía**, porque todos los demás pedidos de este fichero tienen franja.
     * Era una guarda defensiva sin sujeto.
     *
     * Lo que protege: `every()` sobre una colección VACÍA devuelve `true`, así que un pedido sin
     * ninguna línea fechada —una compra sin franja asignada todavía— se habría dado por «visita
     * terminada» y el formulario habría nacido **cerrado, en silencio**, el mismo día de crearse.
     */
    public function test_an_order_with_no_dated_line_is_not_treated_as_a_finished_visit(): void
    {
        $responsible = $this->responsible();
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::firstOrCreate(['zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY], [
            'name' => ['es' => 'Entrada'], 'duration_min' => 60, 'seats_per_unit' => 1,
            'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $order = Order::create([
            'user_id' => $responsible->id,
            'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID,
            'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        // SIN `slot_id`: la línea existe y está viva, pero no tiene día.
        $order->items()->create(['ticket_type_id' => $type->id, 'quantity' => 2, 'unit_price' => 500, 'seats' => 2]);
        $version = $this->version();

        $this->get($order->guardianAuthorizationSignedUrl())
            ->assertOk()
            ->assertDontSee(__('guardian.blocked.closed'))
            ->assertSee(__('guardian.booking.no_date'));

        $this->post($this->signedStoreUrl($order), $this->payload($version))
            ->assertSessionHas('guardian_status', 'signed');
        $this->assertSame(1, GuardianAuthorization::count());
    }

    public function test_the_cap_is_the_live_principal_lines_and_the_extra_one_is_refused(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible, quantity: 2);
        $version = $this->version();

        foreach (['Ana', 'Luis'] as $i => $name) {
            $this->post($this->signedStoreUrl($order), $this->payload($version, [
                'minor_name' => $name, 'minor_surname' => 'Apellido'.$i,
                'guardian_name' => 'Adulto'.$i, 'guardian_surname' => 'Apellido'.$i,
            ]))->assertSessionHas('guardian_status', 'signed');
        }

        $this->post($this->signedStoreUrl($order), $this->payload($version, [
            'minor_name' => 'Marta', 'minor_surname' => 'Tercera',
            'guardian_name' => 'Adulto3', 'guardian_surname' => 'Tercero',
        ]))->assertSessionHas('guardian_status', 'full');

        $this->assertSame(2, GuardianAuthorization::count());
    }

    // ─── Validación ───────────────────────────────────────────────────────────

    public function test_the_waiver_checkbox_is_required(): void
    {
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        $this->post($this->signedStoreUrl($order), $this->payload($version, ['accept_waiver' => null]))
            ->assertSessionHasErrors('accept_waiver');

        $this->assertSame(0, GuardianAuthorization::count());
    }

    public function test_an_adult_date_of_birth_is_refused_with_its_own_message(): void
    {
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        $this->post($this->signedStoreUrl($order), $this->payload($version, [
            'minor_born_on' => now()->subYears(25)->toDateString(),
        ]))->assertSessionHasErrors(['minor_born_on' => __('guardian.errors.born_on_adult')]);

        $this->assertSame(0, GuardianAuthorization::count());
    }

    public function test_a_future_date_of_birth_is_refused(): void
    {
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        $this->post($this->signedStoreUrl($order), $this->payload($version, [
            'minor_born_on' => now()->addDay()->toDateString(),
        ]))->assertSessionHasErrors(['minor_born_on' => __('guardian.errors.born_on_future')]);
    }

    public function test_a_relationship_outside_the_closed_list_is_refused(): void
    {
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        $this->post($this->signedStoreUrl($order), $this->payload($version, ['guardian_relationship' => 'vecino']))
            ->assertSessionHasErrors('guardian_relationship');
    }

    public function test_a_stale_document_id_is_refused_so_the_text_is_read_again(): void
    {
        $order = $this->orderFor($this->responsible());
        $v1 = $this->version();
        $this->version(); // se publica una v2: la v1 deja de ser firmable

        $this->post($this->signedStoreUrl($order), $this->payload($v1))
            ->assertSessionHas('guardian_status', 'stale');

        $this->assertSame(0, GuardianAuthorization::count());
    }

    // ─── Anti-abuso ───────────────────────────────────────────────────────────

    public function test_the_honeypot_swallows_a_bot_without_telling_it(): void
    {
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        // Se responde como si todo fuera bien: al bot no se le dice qué le delató. Aquí callar es
        // correcto porque un campo INVISIBLE relleno es señal de bot y de nada más.
        $this->post($this->signedStoreUrl($order), $this->payload($version, ['contact_ref' => 'http://spam.example']))
            ->assertSessionHas('guardian_status', 'signed');

        $this->assertSame(0, GuardianAuthorization::count(), 'el honeypot no puede escribir nada');
    }

    public function test_with_turnstile_configured_a_missing_token_writes_nothing(): void
    {
        Setting::query()->updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key']);
        Setting::query()->updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key']);
        Setting::flushMemo();
        Turnstile::flushCache();

        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        // ⚠️⚠️ **Y NO se le dice «Listo»**: éste es el defecto REAL que encontró la sonda de navegador
        // de esta tanda. Copiando el patrón de `/contacto`, un token ausente —widget bloqueado por una
        // extensión, red inestable, JS caído— **no escribía nada y anunciaba éxito**. Un mensaje de
        // contacto perdido es barato; un padre que cree tener firmada la autorización de su hijo se
        // entera en la puerta del parque. Turnstile falla a PERSONAS, así que su fallo se dice.
        $this->post($this->signedStoreUrl($order), $this->payload($version))
            ->assertSessionHas('guardian_status', 'antibot');

        $this->assertSame(0, GuardianAuthorization::count());
    }

    public function test_the_honeypot_stays_silent_but_turnstile_does_not(): void
    {
        // La ASIMETRÍA, en un solo caso, para que nadie la «unifique» al pasar por aquí.
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        Setting::query()->updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key']);
        Setting::query()->updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key']);
        Setting::flushMemo();
        Turnstile::flushCache();

        $this->post($this->signedStoreUrl($order), $this->payload($version, ['contact_ref' => 'x']))
            ->assertSessionHas('guardian_status', 'signed');
        $this->post($this->signedStoreUrl($order), $this->payload($version))
            ->assertSessionHas('guardian_status', 'antibot');

        $this->assertSame(0, GuardianAuthorization::count());
    }

    public function test_without_turnstile_keys_the_form_works(): void
    {
        // El CONTROL del caso anterior: sin claves el anti-bot es no-op y la web funciona igual.
        $order = $this->orderFor($this->responsible());
        $version = $this->version();

        $this->post($this->signedStoreUrl($order), $this->payload($version));

        $this->assertSame(1, GuardianAuthorization::count());
    }

    // ─── El responsable con sesión ────────────────────────────────────────────

    public function test_the_responsible_can_open_it_without_a_signature_and_finds_his_details(): void
    {
        $responsible = $this->responsible();
        $order = $this->orderFor($responsible);
        $this->version();

        $this->actingAs($responsible)
            ->get(route('reservation.authorization', ['order' => $order]))
            ->assertOk()
            ->assertSee($responsible->email, false);
    }

    public function test_another_logged_in_customer_gets_403(): void
    {
        $order = $this->orderFor($this->responsible());
        $this->version();
        $stranger = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($stranger)
            ->get(route('reservation.authorization', ['order' => $order]))
            ->assertForbidden();
    }

    // ─── Deber de información del art. 13 ────────────────────────────────────

    /**
     * Quien rellena esto es un TERCERO que no ha aceptado nada antes y está entregando datos de un
     * menor: la política de privacidad se enlaza, no se resume.
     *
     * ⚠️ El párrafo se pinta con `{!! !!}` porque lleva un `<a>` dentro de la frase, y eso es un sink
     * que `SEC-07` vigila. Es seguro por construcción y no por suerte: la frase es i18n **del
     * desarrollador** (no contenido de CMS) y **las dos interpolaciones pasan por `e()`** —la URL y el
     * rótulo—. Este caso existe para que quien mueva el enlace vea la condición.
     */
    public function test_the_public_form_links_the_privacy_policy(): void
    {
        $order = $this->orderFor($this->responsible());
        $this->version();

        $this->get($order->guardianAuthorizationSignedUrl())
            ->assertOk()
            ->assertSee(route('legal.privacidad'), false)
            ->assertSee(__('guardian.privacy_link'));
    }

    // ─── `no-store`: la pantalla lleva datos de un menor (`RGPD-04`) ──────────

    public function test_the_page_is_never_stored(): void
    {
        $order = $this->orderFor($this->responsible());
        $this->version();

        $response = $this->get($order->guardianAuthorizationSignedUrl())->assertOk();

        // ⚠️ Se asevera `no-store`, NO la cadena exacta: es la convención razonada en
        // `NoStoreWebResponsesTest`, porque el resto de la cabecera lo componen otras capas.
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }
}
