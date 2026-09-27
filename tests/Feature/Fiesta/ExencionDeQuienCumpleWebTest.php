<?php

namespace Tests\Feature\Fiesta;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Identity\Contracts\HonoreeCoverage;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\DependentAssigner;
use App\Domain\Identity\Services\DependentRegistry;
use App\Domain\Identity\Services\GuardianAuthorizationSigner;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Identity\Services\WaiverSignatureRequest;
use App\Domain\Platform\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\Support\MountsAParty;
use Tests\TestCase;

/**
 * F7b de `specs/fiesta-sistema-nuevo.md` §4.13 (`[DECIDIDO owner]` `#752`): **EL DESCARGO DE QUIEN CUMPLE en la lista y en
 * su justificante**. La fila lee la cobertura (nunca el nombre), el panel sale solo cuando falta, el camino de la cuenta
 * exige la sesión del titular (`RGPD-03`: el enlace de la lista no la da y se reenvía), y su justificante —con `para=cumple`
 * DENTRO de la firma— no gasta plaza ni se rechaza con la fiesta llena.
 */
class ExencionDeQuienCumpleWebTest extends TestCase
{
    use MountsAParty;
    use RefreshDatabase;

    public function test_the_panel_shows_only_while_the_honoree_is_missing_and_only_in_internal_mode(): void
    {
        [$r, $host] = $this->fiesta(6);

        $html = $this->lista($r, $host);
        $this->assertStringContainsString('data-firma-cumple', $html, 'sin cubrir: el panel bajo su fila');
        // CONTROL de la búsqueda de abajo: en modo interno SÍ encuentra el «Falta» de su fila. ⚠️ Por PALABRA: con la subcadena
        // cazaba «Faltan…» de otros textos (visto al escribirlo).
        $this->assertMatchesRegularExpression('/\b'.preg_quote(__('fiesta.fila.missing'), '/').'\b/u', (string) preg_replace('/\s+/', ' ', strip_tags($html)));
        $this->assertStringContainsString('Falta su descargo · Firmarlo', $html);
        $this->assertStringContainsString('¿Firmas tú el descargo de Lucía?', $html);
        // En los textos del panel, «descargo» (`#339`). ⚠️ Sin el documento que se enseña dentro: el texto legal del parque
        // es suyo (el del montaje se titula «Exención…» y este caso lo cazaba como si fuera nuestro, visto al escribirlo).
        $panel = (string) preg_replace('#<div class="aut-descargo">.*?</div></details>#s', '', (string) strstr($html, 'data-firma-cumple'));
        $this->assertStringContainsString('Leer el descargo', $panel, 'CONTROL: el recorte deja el panel');
        $this->assertStringNotContainsString('exención', mb_strtolower(strip_tags($panel)), 'en pantalla, «descargo» (`#339`)');

        // Atado su menor a cargo con el descargo vigente: firmado, sin panel.
        $hijo = $this->hijo($host, 'Lucía');
        app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $hijo->id);
        $html = $this->lista($r, $host);
        $this->assertStringNotContainsString('data-firma-cumple', $html);
        $this->assertTrue($this->ficha0($r, $host)['firmada']);

        // Fuera del modo interno no hay firma que pedir ni que enseñar: ni panel, ni leyenda, ni «Falta».
        [$r2, $host2] = $this->fiesta(6);
        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_EXTERNAL]);
        Setting::flushMemo();
        $html = $this->lista($r2, $host2);
        $this->assertStringNotContainsString('data-firma-cumple', $html);
        $this->assertStringNotContainsString('pli-leyenda', $html);
        $texto = (string) preg_replace('/\s+/', ' ', strip_tags($html));
        preg_match_all('/.{0,50}\b'.preg_quote(__('fiesta.fila.missing'), '/').'\b.{0,50}/u', $texto, $donde);
        $this->assertSame([], $donde[0], 'ninguna fila dice «Falta» fuera del modo interno');
    }

    public function test_a_guest_named_like_the_honoree_is_not_signed_by_the_honoree_authorization(): void
    {
        [$r, $host] = $this->fiesta(6);
        // La invitada, apuntada SOLO con su nombre de pila (la lista pegada de la clase): «Lucía» empareja por nombre con el
        // «Lucía Pérez» del justificante de quien cumple. ⚠️ Con «Lucía Gil» el caso no medía nada (no empareja de todos
        // modos): lo cazó la mutación.
        $r->forceFill(['guest_data' => [['name' => 'Lucía'], ['name' => 'Lucía']]])->save();
        $this->firmar($r, $host, 'Lucía', 'Pérez', true);

        $ninos = $this->ninos($r, $host);
        $this->assertTrue($ninos[0]['firmada'], 'quien cumple, por su justificante atado');
        $this->assertFalse($ninos[1]['firmada'], 'la invitada «Lucía» no puede salir firmada por el justificante de quien cumple');

        // CONTROL: con su propio justificante, sí.
        $this->firmar($r, $host, 'Lucía', 'Gil', false, 'Ana Gil');
        $this->assertTrue($this->ninos($r, $host)[1]['firmada']);
    }

    public function test_without_a_session_the_account_path_offers_to_sign_in_and_cannot_write(): void
    {
        [$r, $host] = $this->fiesta(6);
        $firmada = $r->guestFormSignedUrl();

        $html = (string) $this->get($firmada)->assertOk()->getContent();
        $this->assertStringContainsString('data-cumple-entrar', $html, 'sin sesión: «Entrar y firmarlo en mi cuenta»');
        $this->assertSame(1, preg_match('#href="([^"]+)"[^>]*data-cumple-aqui#', $html, $aqui));
        $this->assertStringContainsString('para=cumple', html_entity_decode($aqui[1]), '«Firmarlo aquí»: su justificante');
        $this->assertStringNotContainsString('id="fiesta-cumple"', $html, 'sin sesión no se pinta el formulario de la cuenta');

        // El POST de la cuenta, sin sesión: la puerta de la lista le cierra el paso y no se escribe nada.
        $this->post(route('reservation.honoree.store', ['reservation' => $r]), $this->datosHijo('nuevo'))->assertForbidden();
        // ⚠️ Y aunque trajera una FIRMA válida (la puerta de la lista la aceptaría), sin la sesión del titular el camino de la
        // cuenta no escribe: una firma a nombre del titular no puede hacerla quien tenga un enlace (`RGPD-03`). Sin este
        // caso, quitar esa comprobación del controlador no lo cazaba nada: la puerta respondía antes.
        $firmado = URL::temporarySignedRoute('reservation.honoree.store', now()->addDay(), ['reservation' => $r, 'v' => $r->guestFormLinkVersion()]);
        $this->post($firmado, $this->datosHijo('nuevo'))->assertRedirect()->assertSessionHas('status', 'cumple-entrar');
        // Y con la sesión de OTRA cuenta y el enlace firmado: tampoco. Sin la comprobación del titular, el hijo se creaba en la
        // cuenta de ese otro antes de que el dominio se negara a atarlo (lo cazó la mutación).
        $intruso = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($intruso)->post($firmado, $this->datosHijo('nuevo'))->assertSessionHas('status', 'cumple-entrar');
        $this->assertSame(0, Dependent::query()->count());
        auth()->logout();
        // Con la sesión de OTRO, tampoco.
        $otro = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($otro)->post(route('reservation.honoree.store', ['reservation' => $r]), $this->datosHijo('nuevo'))->assertForbidden();
        $this->assertSame(0, Dependent::query()->count());
    }

    public function test_the_host_signs_an_existing_child_from_the_row(): void
    {
        [$r, $host] = $this->fiesta(6);
        $lucia = $this->hijo($host, 'Lucía');

        $html = $this->lista($r, $host);
        $this->assertStringContainsString('id="fiesta-cumple"', $html, 'con la sesión del titular: su formulario');
        $this->assertMatchesRegularExpression('#<option value="'.$lucia->id.'"\s+selected#', $html, 'el hijo que se llama como quien cumple, preseleccionado');

        $this->actingAs($host)->post(route('reservation.honoree.store', ['reservation' => $r]), $this->datosHijo((string) $lucia->id))
            ->assertRedirect()->assertSessionHas('status', 'cumple-firmado');

        $cov = app(GuardianPlaces::class)->honoreeCoverage($r->id);
        $this->assertSame([HonoreeCoverage::DEPENDENT, $lucia->id], [$cov?->via, $cov?->dependentId]);
        $this->assertStringContainsString('El descargo de Lucía está firmado.', $this->lista($r, $host, 'cumple-firmado'));
    }

    public function test_a_child_without_a_current_waiver_is_signed_in_the_same_step(): void
    {
        [$r, $host] = $this->fiesta(6);
        // Un hijo de antes, sin su descargo (como los de la cuenta de sondas: la sonda lo vio con Noa).
        $sinFirma = Dependent::query()->create(['user_id' => $host->id, 'name' => 'Lucía', 'surname' => 'Pérez', 'born_on' => now()->subYears(8)->toDateString(), 'relationship' => 'mother']);

        $this->actingAs($host)->post(route('reservation.honoree.store', ['reservation' => $r]), $this->datosHijo((string) $sinFirma->id))
            ->assertSessionHas('status', 'cumple-firmado');

        $cov = app(GuardianPlaces::class)->honoreeCoverage($r->id);
        $this->assertTrue($cov?->signed(), 'firmado su descargo con la casilla del panel, y atado');
        $this->assertSame($sinFirma->id, $cov->dependentId);
    }

    public function test_the_host_adds_and_signs_a_new_child_in_one_step(): void
    {
        [$r, $host] = $this->fiesta(6);

        $this->actingAs($host)->post(route('reservation.honoree.store', ['reservation' => $r]), $this->datosHijo('nuevo'))
            ->assertSessionHas('status', 'cumple-firmado');

        $hijo = Dependent::query()->where('user_id', $host->id)->firstOrFail();
        $this->assertSame(['Lucía', 'Pérez Ruiz'], [$hijo->name, $hijo->surname]);
        $cov = app(GuardianPlaces::class)->honoreeCoverage($r->id);
        $this->assertTrue($cov?->signed(), 'añadido, firmado y atado en el mismo paso');
        $this->assertSame($hijo->id, $cov->dependentId);
    }

    public function test_the_account_path_refuses_without_the_checkbox_with_a_stale_text_or_an_unverified_email(): void
    {
        [$r, $host] = $this->fiesta(6);
        $url = route('reservation.honoree.store', ['reservation' => $r]);

        $this->actingAs($host)->post($url, ['accept_waiver' => null] + $this->datosHijo('nuevo'))
            ->assertSessionHas('status', 'cumple-rechazo')->assertSessionHas('honoree_reason', __('fiesta.firma.err_casilla'));
        $this->actingAs($host)->post($url, ['document_id' => 999999] + $this->datosHijo('nuevo'))
            ->assertSessionHas('status', 'cumple-stale');
        $this->assertSame(0, Dependent::query()->count(), 'nada escrito');

        $host->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($host)->post($url, $this->datosHijo('nuevo'))
            ->assertSessionHas('honoree_reason', __('fiesta.lista.cumple_firma.verifica'));
        $this->assertSame(0, Dependent::query()->count(), 'sin correo verificado, ni medio paso');
    }

    public function test_the_honoree_authorization_page_signs_even_with_the_party_full(): void
    {
        [$r, $host] = $this->fiesta(1);

        // CONTROL: la autorización de siempre, con la fiesta llena, cerrada.
        $normal = (string) $this->get($r->guardianAuthorizationSignedUrl())->assertOk()->getContent();
        $this->assertStringContainsString(__('guardian.blocked.full'), $normal);

        $pagina = (string) $this->get($r->guardianAuthorizationSignedUrl(['para' => 'cumple']))->assertOk()->getContent();
        $this->assertStringContainsString('El descargo de Lucía', $pagina);
        $this->assertStringContainsString('data-para-cumple', $pagina);
        $this->assertStringContainsString(__('fiesta.firma.casilla_cumple'), $pagina);
        $this->assertStringNotContainsString(__('guardian.blocked.full'), $pagina, 'su plaza es suya: «llena» no le cierra la puerta');

        $this->assertSame(1, preg_match('#<form[^>]*method="post"[^>]*action="([^"]*autorizacion[^"]*)"#i', $pagina, $m));
        $accion = html_entity_decode($m[1]);
        $this->assertStringContainsString('para=cumple', $accion, 'la marca viaja DENTRO de la firma del envío');
        $this->post($accion, [
            'document_id' => $this->documento()->id, 'accept_waiver' => '1',
            'minor_name' => 'Lucía', 'minor_surname' => 'Pérez', 'minor_born_on' => now()->subYears(8)->toDateString(),
            'guardian_name' => 'Pedro Pérez', 'guardian_relationship' => 'father', 'guardian_phone' => '600222333',
        ])->assertRedirect();

        $this->assertTrue(GuardianAuthorization::query()->where('order_item_id', $r->id)->where('honoree', true)->exists());
        $this->assertSame(HonoreeCoverage::AUTHORIZATION, app(GuardianPlaces::class)->honoreeCoverage($r->id)?->via);
    }

    public function test_the_honoree_link_cannot_be_forged_and_says_when_it_is_already_covered(): void
    {
        [$r, $host] = $this->fiesta(6);

        // Un padre invitado con el enlace de siempre no puede convertirlo en el de quien cumple.
        $this->get($r->guardianAuthorizationSignedUrl().'&para=cumple')->assertForbidden();

        app(DependentAssigner::class)->assignHonoree($host, $r->order_id, $r->id, $this->hijo($host, 'Lucía')->id);
        $pagina = (string) $this->get($r->guardianAuthorizationSignedUrl(['para' => 'cumple']))->assertOk()->getContent();
        $this->assertStringContainsString(__('guardian.blocked.honoree_covered'), $pagina);
        $this->assertStringNotContainsString('name="accept_waiver"', $pagina, 'cubierto: no se ofrece firmar otra vez');
    }

    // ── Montaje ─────────────────────────────────────────────────────────────────────────────────────

    /** @return array{0: OrderItem, 1: User} */
    private function fiesta(int $quantity): array
    {
        ['reservation' => $r, 'host' => $host] = $this->mountParty();
        $r->forceFill(['quantity' => $quantity, 'seats' => $quantity, 'honoree_row' => true])->save();

        return [$r->fresh() ?? $r, $host];
    }

    private function lista(OrderItem $r, User $host, ?string $status = null): string
    {
        return (string) $this->actingAs($host)->withSession($status === null ? [] : ['status' => $status])
            ->get(route('reservation.guests', ['reservation' => $r]))->assertOk()->getContent();
    }

    /** @return list<array<string, mixed>> */
    private function ninos(OrderItem $r, User $host): array
    {
        return $this->actingAs($host)->get(route('reservation.guests', ['reservation' => $r]))->assertOk()->viewData('m')['ninos'];
    }

    /** @return array<string, mixed> */
    private function ficha0(OrderItem $r, User $host): array
    {
        return $this->ninos($r, $host)[0];
    }

    private function documento(): LegalDocumentVersion
    {
        return LegalDocumentVersion::query()->where('slug', WaiverSettings::SLUG)->orderByDesc('id')->firstOrFail();
    }

    private function hijo(User $host, string $nombre): Dependent
    {
        return app(DependentRegistry::class)->add($host, $nombre, now()->subYears(8)->toDateString(), 'Pérez', 'mother', $this->documento(), WaiverSignatureRequest::web('127.0.0.1', 'test'));
    }

    private function firmar(OrderItem $r, User $host, string $nombre, string $apellidos, bool $cumple, string $adulto = 'Marta Pérez'): void
    {
        app(GuardianAuthorizationSigner::class)->sign($host, $r->id, $this->documento(), [
            'minor_name' => $nombre, 'minor_surname' => $apellidos, 'minor_born_on' => now()->subYears(8)->toDateString(),
            'guardian_name' => $adulto, 'guardian_surname' => '', 'guardian_relationship' => 'mother', 'guardian_email' => null, 'guardian_phone' => '600111222',
        ], WaiverSignatureRequest::web('127.0.0.1', 'test'), null, $cumple);
    }

    /** @return array<string, mixed> */
    private function datosHijo(string $hijo): array
    {
        return [
            'hijo' => $hijo, 'document_id' => $this->documento()->id, 'accept_waiver' => '1',
            'minor_name' => 'Lucía', 'minor_surname' => 'Pérez Ruiz', 'minor_born_on' => now()->subYears(8)->toDateString(), 'relationship' => 'mother',
        ];
    }
}
