<?php

namespace Tests\Feature\Mail;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Content\Services\MailTextRules;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Survey;
use App\Notifications\Support\MailPreviews;
use App\Notifications\Support\MailTextCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
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
    use MountsAParty;
    use RefreshDatabase;

    /**
     * Los bloques que el caso de estas pruebas NO pinta porque solo salen en otro caso (su condición al lado): el resto de
     * los bloques de cada correo tiene que salir. Uno que no sale y no está aquí es un bloque FANTASMA en el panel.
     *
     * @var list<string>
     */
    private const CONDICIONALES_DEL_CASO = [
        // Un pedido SIN fecha; y el de unas entradas, sin lista de invitados (el caso es una fiesta con fecha).
        'emails.order_confirmation.subject_no_date', 'emails.order_confirmation.headline_no_date',
        'emails.order_confirmation.paid_confirmation',
        // Quien cumple, cuando falta SU descargo y tiene nombre (sin él sale `honoree_unnamed`).
        'emails.visit_eve.honoree',
        // La fiesta SIN invitación (el caso la tiene: salen los `_invite`), sin fecha, y con extras que ofrecer.
        'emails.guest_form.subject_no_date', 'emails.guest_form.intro', 'emails.guest_form.body', 'emails.guest_form.action',
        'emails.guest_form.outro', 'emails.guest_form.notice_title', 'emails.guest_form.extras',
        // Un extra que cambia o se quita, y lo que baja (el caso AÑADE uno).
        'emails.postform_addons.updated', 'emails.postform_addons.removed', 'emails.postform_addons.delta_down',
        // El suplemento: lo cambia el parque, cambia, se quita, pasa a descuento o cruza de signo (el caso NACE, de 0 a 4 €).
        'emails.mixed_party_surcharge.intro_by_park', 'emails.mixed_party_surcharge.updated',
        'emails.mixed_party_surcharge.removed', 'emails.mixed_party_surcharge.credit_added',
        'emails.mixed_party_surcharge.credit_updated', 'emails.mixed_party_surcharge.credit_removed',
        'emails.mixed_party_surcharge.changed_direction', 'emails.mixed_party_surcharge.where_discounted',
        // «Avísame de fechas» con un precio «desde» (el caso no lo trae).
        'fiesta.cumple_mail.desde',
        // Lo que cambió en una reserva (el caso no trae cambios).
        'emails.order_item_modified.slot_change', 'emails.order_item_modified.quantity_change',
        'emails.order_item_modified.product_change', 'emails.order_item_modified.event_data_change',
        'emails.order_item_modified.addon_change',
        // Complementos que caen con la reserva; lo cancelado a la vez; la devolución hecha a mano.
        'emails.order_item_cancelled.cascaded_addons', 'emails.order_item_refunded.also_cancelled',
        'emails.order_item_refunded.when_manual', 'emails.order_refunded.also_cancelled', 'emails.order_refunded.when_manual',
        // Google verificó el correo de la cuenta.
        'account.social_link_mail.promoted',
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
    public function test_every_block_of_every_mail_is_painted_and_bold_goes_only_where_the_mold_paints_it(): void
    {
        ['host' => $host, 'reservation' => $fiesta, 'document' => $version] = $this->mountParty();
        $this->firma($host, $fiesta, $version);
        Survey::create([
            'key' => 'que-tal', 'name' => ['es' => '¿Qué tal?'], 'kind' => Survey::KIND_EXTERNAL, 'active' => true,
            'questions' => [['key' => 'ambiente', 'type' => 'scale', 'label' => ['es' => 'Ambiente']]],
        ]);

        $fantasmas = $asteriscos = [];
        $vistos = 0;
        foreach (array_keys(MailTextCatalog::CORREOS) as $correo) {
            $claves = MailTextCatalog::claves($correo);
            $borrador = [];
            foreach ($claves as $i => $clave) {
                $marca = 'QQ'.$i.'QQ';
                $borrador[$clave] = (MailTextRules::admiteNegrita($clave) ? '**'.$marca.'**' : $marca)
                    .' '.MailTextRules::aParque((string) MailTextCatalog::fabrica($clave, 'es'));
            }
            $pintado = MailPreviews::pintar($correo, 'es', $borrador, $host);
            $this->assertArrayHasKey('html', $pintado, "{$correo}: sin caso en la prueba");
            $todo = $pintado['asunto']."\n".$pintado['adelanto']."\n".$pintado['html'];
            foreach ($claves as $i => $clave) {
                $marca = 'QQ'.$i.'QQ';
                if (! str_contains($todo, $marca)) {
                    if (! in_array($clave, self::CONDICIONALES_DEL_CASO, true)) {
                        $fantasmas[] = $clave;
                    }

                    continue;
                }
                $vistos++;
                if (MailTextRules::admiteNegrita($clave) && preg_match('/<strong[^>]*>'.$marca.'<\/strong>/', $pintado['html']) !== 1) {
                    $asteriscos[] = $clave;
                }
            }
        }

        $this->assertGreaterThan(200, $vistos, 'CONTROL: la prueba mira de verdad los bloques');
        $this->assertSame([], $fantasmas, 'bloques que su correo no pinta en este caso: ¿condicionales (a la lista, con su condición) o de una versión que ya no sale (fuera del catálogo)?');
        $this->assertSame([], $asteriscos, 'bloques que admiten negrita y el molde no la pinta: el cliente leería los asteriscos (a MailTextRules::admiteNegrita)');
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
