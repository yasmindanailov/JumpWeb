<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Content\Services\MailTextRules;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Survey;
use App\Notifications\Support\MailPreviews;
use App\Notifications\Support\MailSituations;
use App\Notifications\Support\MailTextCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Tests\Support\AttachesPartyExtras;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * LA VISTA PREVIA DE LOS TEXTOS (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): el correo DE VERDAD, con el caso real
 * más reciente y el borrador del panel, sin dejar nada. Lo que calla al romperse: un correo del catálogo sin constructor (el
 * panel diría «no se ha podido pintar» para siempre), un borrador que no llega o que se queda, y una fila que un `toMail()`
 * escribe y la vista previa no deshace.
 */
class MailPreviewsTest extends TestCase
{
    use AttachesPartyExtras;
    use MountsAParty;
    use RefreshDatabase;

    /**
     * Lo que cada SITUACIÓN tiene que hacer salir (R1·T2, `#809`; `MailSituations`): es la promesa del desplegable. Sustituye a
     * la lista a mano de «condicionales del caso», que dependía del caso y ya no era cierta (02-10: 33 en la local frente a
     * 34 aquí, y no los mismos).
     *
     * @var array<string, array<string, list<string>>>
     */
    private const SITUACION_HACE_SALIR = [
        'order_confirmation' => [
            'entradas' => ['emails.order_confirmation.paid_confirmation'],
            'cumpleanos' => ['emails.order_confirmation.paid_confirmation_guest_form'],
            'sin_un_dia' => ['emails.order_confirmation.subject_no_date', 'emails.order_confirmation.headline_no_date'],
        ],
        'visit_eve_notice' => [
            'cumple_con_nombre' => ['emails.visit_eve.honoree'],
            'cumple_sin_nombre' => ['emails.visit_eve.honoree_unnamed'],
        ],
        'guest_form_request' => [
            'con_invitacion' => ['emails.guest_form.intro_invite', 'emails.guest_form.body_invite', 'emails.guest_form.action_invite', 'emails.guest_form.outro_invite'],
            'sin_invitacion' => ['emails.guest_form.intro', 'emails.guest_form.body', 'emails.guest_form.action', 'emails.guest_form.outro'],
            'con_extras' => ['emails.guest_form.notice_title', 'emails.guest_form.extras'],
            'reserva_sin_fecha' => ['emails.guest_form.subject_no_date'],
        ],
        'guardian_authorization_signed' => ['firma_de_reserva' => ['emails.guardian_authorization.booking']],
        'post_form_addons_changed' => [
            'extra_anadido' => ['emails.postform_addons.added', 'emails.postform_addons.delta_up'],
            'extra_cambiado' => ['emails.postform_addons.updated', 'emails.postform_addons.delta_up'],
            'extra_quitado' => ['emails.postform_addons.removed', 'emails.postform_addons.delta_down'],
        ],
        'mixed_party_surcharge_changed' => [
            'suplemento_nace' => ['emails.mixed_party_surcharge.intro', 'emails.mixed_party_surcharge.added', 'emails.mixed_party_surcharge.where_to_pay'],
            'suplemento_cambia' => ['emails.mixed_party_surcharge.updated'],
            'suplemento_se_quita' => ['emails.mixed_party_surcharge.removed'],
            'descuento_nace' => ['emails.mixed_party_surcharge.credit_added', 'emails.mixed_party_surcharge.where_discounted'],
            'descuento_cambia' => ['emails.mixed_party_surcharge.credit_updated'],
            'descuento_se_quita' => ['emails.mixed_party_surcharge.credit_removed'],
            'cambia_de_signo' => ['emails.mixed_party_surcharge.changed_direction'],
            'por_parque' => ['emails.mixed_party_surcharge.intro_by_park'],
        ],
        'birthday_coming_notice' => ['con_desde' => ['fiesta.cumple_mail.desde']],
        'order_item_modified' => [
            'cambio_dia' => ['emails.order_item_modified.slot_change'],
            'cambio_cantidad' => ['emails.order_item_modified.quantity_change'],
            'cambio_producto' => ['emails.order_item_modified.product_change'],
            'cambio_datos' => ['emails.order_item_modified.event_data_change'],
            'cambio_complementos' => ['emails.order_item_modified.addon_change'],
        ],
        'order_item_cancelled' => ['caen_complementos' => ['emails.order_item_cancelled.cascaded_addons']],
        'order_item_refunded' => [
            'tambien_cancelada' => ['emails.order_item_refunded.also_cancelled'],
            'devolucion_a_mano' => ['emails.order_item_refunded.when_manual'],
        ],
        'order_refunded' => [
            'tambien_cancelado' => ['emails.order_refunded.also_cancelled'],
            'devolucion_a_mano' => ['emails.order_refunded.when_manual'],
        ],
        'social_identity_linked' => ['google_verifico' => ['account.social_link_mail.promoted']],
        'survey_invitation' => ['encuesta_sin_entrada' => ['surveys.mail.line1']],
    ];

    public function test_every_mail_of_the_catalog_has_how_to_be_built(): void
    {
        $this->assertSame([], array_values(array_diff(array_keys(MailTextCatalog::CORREOS), array_keys(MailPreviews::constructores()))));
        $this->assertSame([], array_values(array_diff(array_keys(MailPreviews::constructores()), array_keys(MailTextCatalog::CORREOS))));
    }

    public function test_without_a_real_case_it_says_which_one_is_missing(): void
    {
        $yo = User::factory()->create();

        $this->assertSame(['motivo' => 'pedido'], MailPreviews::pintar('order_confirmation', 'es', [], $yo));
        $this->assertSame(['motivo' => 'fiesta'], MailPreviews::pintar('guest_form_request', 'es', [], $yo));
        $this->assertSame(['motivo' => 'firma'], MailPreviews::pintar('guardian_authorization_signed', 'es', [], $yo));
        $this->assertSame(['motivo' => 'encuesta'], MailPreviews::pintar('survey_invitation', 'es', [], $yo));
        // CONTROL: un correo que no necesita caso, sí.
        $this->assertArrayHasKey('html', MailPreviews::pintar('login_code', 'es', [], $yo));
    }

    public function test_every_mail_is_painted_with_a_real_case_and_leaves_nothing_behind(): void
    {
        ['host' => $host] = $this->mountParty();
        $antes = $this->filas();

        $sinCaso = [];
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            $r = MailPreviews::pintar($correo, 'es', [], $host);
            if (! isset($r['html'])) {
                $sinCaso[$correo] = $r['motivo'];

                continue;
            }
            $this->assertStringContainsString('<html', $r['html'], $correo);
        }

        // Sin firma ni encuesta en esta fiesta: solo esos dos dicen su motivo; ninguno revienta.
        $this->assertSame(['guardian_authorization_signed' => 'firma', 'survey_invitation' => 'encuesta'], $sinCaso);
        $this->assertSame($antes, $this->filas(), 'la vista previa deshace lo que un toMail() escribe');
    }

    public function test_the_draft_is_painted_in_its_language_and_dark_is_the_outlook_one(): void
    {
        ['host' => $host] = $this->mountParty();

        $es = MailPreviews::pintar('order_confirmation', 'es', ['emails.order_confirmation.intro' => 'BORRADOR {code}'], $host);
        $this->assertStringContainsString('BORRADOR R-', $es['html'] ?? '');
        // (El claro ya NOMBRA `data-ogsc` en sus reglas para Outlook.com: lo que cambia en oscuro es la etiqueta `<html>`.)
        $this->assertStringNotContainsString('<html data-ogsc', $es['html'] ?? '');

        // En otro idioma, el correo EN ese idioma con su borrador; el español no se cuela. Y la página sigue en el suyo.
        $idioma = app()->getLocale();
        $en = MailPreviews::pintar('order_confirmation', 'en', ['emails.order_confirmation.intro' => 'DRAFT {code}'], $host, oscuro: true);
        $this->assertNotSame('en', $idioma, 'el caso necesita otro idioma en la petición');
        $this->assertSame($idioma, app()->getLocale());
        $this->assertStringContainsString('DRAFT R-', $en['html'] ?? '');
        $this->assertStringNotContainsString('BORRADOR', $en['html'] ?? '');
        $this->assertStringContainsString('<html data-ogsc data-ogsb', $en['html'] ?? '');
        // Y tras pintar, el traductor vuelve a lo guardado (nada).
        $this->assertStringNotContainsString('BORRADOR', trans('emails.order_confirmation.intro', ['code' => 'R-1'], 'es'));
    }

    /**
     * LA GUARDA DE LOS BLOQUES CONTRA EL MOLDE DE VERDAD (30-09): cada correo pintado con una MARCA en cada bloque editable
     * —en negrita donde las reglas la admiten—. Un bloque que su correo no pinta sería un bloque fantasma en el panel (el del
     * correo nuevo ofrecía seis de una versión que ya no sale), y uno que admite negrita tiene que pintarla en `<strong>`: si
     * no, el cliente leería los asteriscos (141 de 278 textos, antes de `MailTextRules::admiteNegrita`).
     */
    /**
     * LA GUARDA DE LOS BLOQUES CONTRA EL MOLDE, EN CADA SITUACIÓN (R1·T2, `#809`): cada correo pintado en su situación de
     * siempre y en cada una de `MailSituations`, con una MARCA en cada bloque editable. Cuatro promesas: cada situación hace
     * salir sus textos (`SITUACION_HACE_SALIR`); ningún bloque es un FANTASMA (sale en alguna); todo bloque que falta en alguna
     * lleva su aviso «Solo sale si…» (ninguno condicional en silencio); y la negrita, solo donde el molde la pinta. El caso
     * tiene de TODO lo que el catálogo decide (una fiesta con extras abiertos, otra sin ellos, unas entradas, una firma de una
     * reserva y una encuesta), y nada de lo pintado se queda escrito.
     */
    public function test_every_situation_paints_its_texts_no_block_is_a_phantom_and_every_conditional_one_says_when(): void
    {
        ['host' => $host, 'reservation' => $conExtras, 'document' => $version] = $this->mountParty();
        $this->extra($conExtras->ticketType, 'Cubo de refrescos', 1200);
        $this->firma($host, $conExtras, $version);
        ['reservation' => $fiesta] = $this->mountParty(); // la ÚLTIMA fiesta, sin extras: «con extras» tiene que buscar la otra
        $this->entradas($host, $fiesta); // el ÚLTIMO pedido, sin lista: «un cumpleaños» tiene que buscar uno con ella
        Survey::create([
            'key' => 'que-tal', 'name' => ['es' => '¿Qué tal?'], 'kind' => Survey::KIND_EXTERNAL, 'active' => true,
            'intro' => ['es' => 'Una entrada propia.'],
            'questions' => [['key' => 'ambiente', 'type' => 'scale', 'label' => ['es' => 'Ambiente']]],
        ]);
        $antes = $this->filas();

        $fantasmas = $sinAviso = $noSalen = $asteriscos = [];
        $vistos = 0;
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            $claves = MailTextCatalog::claves($correo);
            $borrador = [];
            foreach ($claves as $i => $clave) {
                $marca = 'QQ'.$i.'QQ';
                $borrador[$clave] = (MailTextRules::admiteNegrita($clave) ? '**'.$marca.'**' : $marca)
                    .' '.MailTextRules::aParque((string) MailTextCatalog::fabrica($clave, 'es'));
            }
            $situaciones = MailSituations::de($correo) ?: [null];
            $salen = []; // clave → en cuántas situaciones sale
            foreach ($situaciones as $situacion) {
                $pintado = MailPreviews::pintar($correo, 'es', $borrador, $host, false, $situacion);
                $this->assertArrayHasKey('html', $pintado, "{$correo} · {$situacion}: sin caso en la prueba (".($pintado['motivo'] ?? '').')');
                $todo = $pintado['asunto']."\n".$pintado['adelanto']."\n".$pintado['html'];
                foreach ($claves as $i => $clave) {
                    $marca = 'QQ'.$i.'QQ';
                    if (! str_contains($todo, $marca)) {
                        continue;
                    }
                    $salen[$clave] = ($salen[$clave] ?? 0) + 1;
                    $vistos++;
                    if (MailTextRules::admiteNegrita($clave) && preg_match('/<strong[^>]*>'.$marca.'<\/strong>/', $pintado['html']) !== 1) {
                        $asteriscos[$clave] = true;
                    }
                }
                foreach (self::SITUACION_HACE_SALIR[$correo][$situacion] ?? [] as $clave) {
                    $i = array_search($clave, $claves, true);
                    if ($i === false || ! str_contains($todo, 'QQ'.$i.'QQ')) {
                        $noSalen[] = "{$correo} · {$situacion}: {$clave}";
                    }
                }
            }
            foreach ($claves as $clave) {
                $veces = $salen[$clave] ?? 0;
                if ($veces === 0) {
                    $fantasmas[] = $clave;
                } elseif ($veces < count($situaciones) && MailSituations::condicion($clave) === null) {
                    $sinAviso[] = $clave;
                }
            }
        }

        $this->assertGreaterThan(400, $vistos, 'CONTROL: la prueba mira de verdad los bloques, en cada situación');
        $this->assertSame([], $noSalen, 'situaciones que no hacen salir lo que prometen (MailPreviews::constructores)');
        $this->assertSame([], $fantasmas, 'bloques que su correo no pinta en ninguna situación: ¿falta una (MailSituations) o es de una versión que ya no sale (fuera del catálogo)?');
        $this->assertSame([], $sinAviso, 'bloques que solo salen en algunas situaciones y no dicen cuándo (MailSituations::CONDICIONES)');
        $this->assertSame([], array_keys($asteriscos), 'bloques que admiten negrita y el molde no la pinta: el cliente leería los asteriscos (a MailTextRules::admiteNegrita)');
        $this->assertSame($antes, $this->filas(), 'ninguna situación deja nada escrito');
    }

    /** Lo que decide el CATÁLOGO del parque no se finge: sin un caso real de su clase, la situación dice qué falta. */
    public function test_a_situation_decided_by_the_catalogue_says_what_is_missing_without_its_case(): void
    {
        ['host' => $host] = $this->mountParty();

        $this->assertSame(['motivo' => 'pedido_entradas'], MailPreviews::pintar('order_confirmation', 'es', [], $host, false, 'entradas'));
        $this->assertSame(['motivo' => 'fiesta_con_extras'], MailPreviews::pintar('guest_form_request', 'es', [], $host, false, 'con_extras'));
        $this->assertSame(['motivo' => 'firma_de_reserva'], MailPreviews::pintar('guardian_authorization_signed', 'es', [], $host, false, 'firma_de_reserva'));
        // CONTROL: la de siempre y una que es un cambio de ejemplo, sí.
        $this->assertArrayHasKey('html', MailPreviews::pintar('order_confirmation', 'es', [], $host, false, 'cumpleanos'));
        $this->assertArrayHasKey('html', MailPreviews::pintar('guest_form_request', 'es', [], $host, false, 'sin_invitacion'));
    }

    /**
     * «La firma de una reserva» es la última que LO SEA, no la última sin más: si la más reciente ya no es de ninguna (su
     * reserva se canceló: `AuthorizableReservations` no la da), la situación busca una anterior que sí, y su línea de la
     * reserva sale. (El arnés vio que, con una sola firma de una reserva en el caso, las dos cosas eran lo mismo.)
     */
    public function test_the_signature_of_a_booking_is_the_last_one_that_has_one(): void
    {
        ['host' => $host, 'reservation' => $fiesta, 'order' => $pedido, 'document' => $version] = $this->mountParty();
        $this->firma($host, $fiesta, $version);
        // (Montar otra fiesta publica otra versión del descargo: la segunda firma, con la suya.)
        ['host' => $otro, 'reservation' => $cancelada, 'order' => $otroPedido, 'document' => $otraVersion] = $this->mountParty();
        $this->firma($otro, $cancelada, $otraVersion, 'Lena', 'lena.parent@example.com');
        DB::table('order_items')->where('id', $cancelada->id)->update(['cancelled_at' => now()]);

        $ultima = MailPreviews::pintar('guardian_authorization_signed', 'es', [], $host);
        $deReserva = MailPreviews::pintar('guardian_authorization_signed', 'es', [], $host, false, 'firma_de_reserva');

        $this->assertStringNotContainsString($otroPedido->code, $ultima['html'] ?? '', 'CONTROL: la última ya no es de ninguna reserva');
        $this->assertStringNotContainsString($pedido->code, $ultima['html'] ?? '');
        $this->assertStringContainsString($pedido->code, $deReserva['html'] ?? '');
    }

    /** Una situación que no es del correo no se pinta: sale la primera (llega del navegador). */
    public function test_a_situation_of_another_mail_falls_back_to_the_first(): void
    {
        ['host' => $host] = $this->mountParty();

        $ajena = MailPreviews::pintar('mixed_party_surcharge_changed', 'es', [], $host, false, 'cambio_cantidad');
        $primera = MailPreviews::pintar('mixed_party_surcharge_changed', 'es', [], $host);
        $this->assertArrayHasKey('html', $primera);
        $this->assertSame($primera['html'], $ajena['html'] ?? null);
        // Y la reserva modificada nunca sale SIN un cambio (`#809`): su primera es «cambio de día u hora».
        $this->assertSame('cambio_dia', MailSituations::elegida('order_item_modified', null));
    }

    /** Las dos listas de `MailSituations` hablan del catálogo y tienen su texto en los dos idiomas del panel. */
    public function test_every_condition_and_situation_is_of_the_catalogue_and_has_its_words(): void
    {
        foreach (MailSituations::CONDICIONES as $clave => $condicion) {
            $this->assertTrue(MailTextCatalog::esEditable($clave), "«{$clave}» no es un texto editable del catálogo");
            $this->assertNotSame([], MailSituations::de((string) MailTextCatalog::correoDe($clave)), "«{$clave}»: su correo no tiene situaciones para verlo");
            foreach (['es', 'zh_CN'] as $idioma) {
                $this->assertTrue(Lang::has('admin.mail_texts.solo_si.'.$condicion, $idioma, false), "falta admin.mail_texts.solo_si.{$condicion} en {$idioma}");
            }
        }
        foreach (MailSituations::SITUACIONES as $correo => $situaciones) {
            $this->assertTrue(MailTextCatalog::existe($correo), "«{$correo}» no es un correo del catálogo");
            foreach ($situaciones as $situacion) {
                foreach (['es', 'zh_CN'] as $idioma) {
                    $this->assertTrue(Lang::has('admin.mail_texts.situaciones.'.$situacion, $idioma, false), "falta admin.mail_texts.situaciones.{$situacion} en {$idioma}");
                }
            }
        }
        foreach (MailPreviews::MOTIVOS as $motivo) {
            $this->assertTrue(Lang::has('admin.mail_texts.sin_caso.'.$motivo, 'zh_CN', false), "falta el motivo {$motivo} en zh_CN");
        }
    }

    public function test_the_new_email_is_previewed_as_it_goes_out_with_its_code(): void
    {
        $pintado = MailPreviews::pintar('verify_pending_email', 'es', [], User::factory()->create());

        // El que sale lleva el código (`#856`): en el asunto y como titular. La versión sin él ya no se envía.
        $this->assertStringContainsString('482-913', $pintado['asunto'] ?? '');
        $this->assertStringContainsString('482-913', $pintado['html'] ?? '');
    }

    public function test_the_signed_copy_is_previewed_in_the_language_of_the_tab(): void
    {
        ['host' => $host, 'reservation' => $fiesta] = $this->mountParty();
        $versiones = app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención', 'body' => [['h' => 'Riesgo', 'p' => 'Saltar implica riesgos.']]],
            'en' => ['title' => 'Waiver', 'body' => [['h' => 'Risk', 'p' => 'Jumping has risks.']]],
        ]);
        $this->firma($host, $fiesta, $versiones->firstWhere('locale', 'es'));
        $borrador = ['emails.guardian_authorization.intro' => 'DRAFT {name}'];

        // La copia sale en el idioma de la versión FIRMADA: sin una firma en inglés, la pestaña inglesa no tiene caso (antes
        // pintaba la española y lo escrito en inglés no se veía)…
        $this->assertSame(['motivo' => 'firma'], MailPreviews::pintar('guardian_authorization_signed', 'en', $borrador, $host));
        // CONTROL: la española, sí.
        $this->assertArrayHasKey('html', MailPreviews::pintar('guardian_authorization_signed', 'es', [], $host));

        // …y con una, sale en inglés y con lo escrito en la pestaña.
        $this->firma($host, $fiesta, $versiones->firstWhere('locale', 'en'), 'Lena', 'lena.parent@example.com');
        $en = MailPreviews::pintar('guardian_authorization_signed', 'en', $borrador, $host);
        $this->assertSame('Waiver signed', $en['asunto'] ?? null);
        $this->assertStringContainsString('DRAFT Lena', $en['html'] ?? '');
    }

    /** Una autorización firmada por el padre de un invitado (su copia es uno de los correos del catálogo). */
    private function firma(User $responsable, OrderItem $fiesta, ?LegalDocumentVersion $version, string $menor = 'Noa', string $correo = 'carlos@example.com'): void
    {
        $this->assertNotNull($version);
        Notification::fake(); // firmar manda la copia: aquí solo hace falta la firma
        app(GuardianAuthorizationSigner::class)->sign($responsable, (int) $fiesta->getKey(), $version, [
            'minor_name' => $menor, 'minor_surname' => 'Pérez Soto', 'minor_born_on' => now()->subYears(9)->toDateString(),
            'guardian_name' => 'Carlos', 'guardian_surname' => 'Pérez Gil', 'guardian_relationship' => 'father',
            'guardian_email' => $correo, 'guardian_phone' => '600333444',
        ], WaiverSignatureRequest::web('10.0.0.1', 'UA'));
    }

    /** Un pedido PAGADO de unas entradas (sin lista de invitados), en la franja de una fiesta: el último pedido del caso. */
    private function entradas(User $cliente, OrderItem $fiesta): Order
    {
        $tipo = TicketType::create([
            'zone_id' => $fiesta->slot->zone_id, 'type' => TicketType::TYPE_ENTRY, 'name' => ['es' => 'Entrada 1 hora'],
            'duration_min' => 60, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 9,
        ]);
        $pedido = Order::create([
            'user_id' => $cliente->id, 'code' => 'R-ENTRAD', 'status' => Order::STATUS_PAID,
            'subtotal' => 1600, 'tax' => 0, 'total' => 1600, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        $pedido->items()->create(['ticket_type_id' => $tipo->id, 'slot_id' => $fiesta->slot_id, 'quantity' => 2, 'unit_price' => 800, 'seats' => 2]);

        return $pedido;
    }

    /** @return array<string, int> filas por tabla de lo que un correo podría escribir */
    private function filas(): array
    {
        $out = [];
        foreach (['party_invitations', 'email_sends', 'audit_logs', 'mail_texts', 'orders', 'order_items'] as $tabla) {
            $out[$tabla] = DB::table($tabla)->count();
        }

        return $out;
    }
}
