<?php

namespace Tests\Feature\Reservation;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\LegalDocumentPublisher;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * La PIEL de la autorización del menor invitado, desde la T3 del sistema nuevo (`specs/fiesta-sistema-nuevo.md` §4.2;
 * antes, la de `celebracion-e-invitacion.md` §4.3 con sus grietas J-01…J-08, que esta guarda vigilaba en `site.css`).
 *
 * ▶ Lo que sigue vigilando, con la piel nueva: que «Firmar» sea la única acción y cierre el formulario (no una barra
 * pegada con párrafos dentro: medido en la T2, 401 px de 844); que el descargo se lea ENTERO y en el flujo
 * (`waiver-probatorio.md` §4.4); que cada desenlace tenga su tono y su título; que la hora sea la de la visita y no
 * el fin de la franja (`#426`); lo opcional marcado y nunca un asterisco; y que el anti-robot se declare como el tercero
 * que es. Y lo que decidió `#745`: el adulto en UNA casilla, el teléfono obligatorio, nacimiento y relación se quedan.
 *
 * ⚠️ La hoja se mira SIN comentarios: su prosa nombra a propósito lo que se retiró (`#553`).
 */
class GuardianSkinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->updateOrCreate(['key' => WaiverSettings::KEY_MODE], ['value' => WaiverSettings::MODE_INTERNAL]);
        Setting::flushMemo();

        app(LegalDocumentPublisher::class)->publish(WaiverSettings::SLUG, [
            'es' => ['title' => 'Exención de responsabilidad', 'body' => [
                ['h' => 'Riesgo asumido', 'p' => 'Saltar en camas elásticas implica riesgos.'],
            ]],
        ]);
    }

    public function test_signing_is_the_only_action_and_it_closes_the_form(): void
    {
        $html = $this->sheet();

        $this->assertSame(1, preg_match('#<form[^>]*data-firma[^>]*>(.*?)</form>#s', $html, $form), 'la firma es un formulario de verdad');
        $this->assertSame(1, preg_match('#<div style="display: grid;"><button type="submit"[^>]*data-firma-boton[^>]*>\s*Firmar\s*</button></div>\s*$#s', $form[1]), '«Firmar», a todo el ancho, cierra el formulario: nada después');
        $this->assertStringNotContainsString('gf-savebar', $html, 'volvió la barra pegada de la piel vieja');
        $this->assertSame(1, substr_count($form[1], 'type="submit"'), 'una sola acción');
    }

    public function test_the_waiver_is_read_whole_and_in_the_flow(): void
    {
        $html = $this->sheet();

        // El texto del descargo se PRESENTA en el flujo (`waiver-probatorio.md` §4.4), dentro del formulario y antes de
        // la casilla; «Leer el descargo» es un ancla a él, no un modal.
        $this->assertSame(1, preg_match('#<div class="aut-descargo" id="descargo"[^>]*>(.*?)</div>#s', $html, $bloque), 'el descargo no está en el flujo');
        $this->assertStringContainsString('Exención de responsabilidad', $bloque[1]);
        $this->assertStringContainsString('Saltar en camas elásticas implica riesgos.', $bloque[1]);
        $this->assertLessThan(strpos($html, 'name="accept_waiver"'), strpos($html, 'id="descargo"'), 'el descargo va ANTES de la casilla que lo acepta');
        $this->assertStringContainsString('href="#descargo"', $html, '«Leer el descargo» lleva al texto');

        $hoja = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(resource_path('js/fiesta/fiesta.css')));
        $this->assertSame(1, preg_match('#\.aut-descargo\s*\{([^}]*)\}#', $hoja, $regla), 'la regla del descargo vive en la hoja de la fiesta');
        $this->assertStringNotContainsString('max-height', $regla[1], 'el descargo se lee ENTERO: sin ventana');
        $this->assertStringNotContainsString('overflow', $regla[1], 'ni scroll dentro del scroll');
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function outcomes(): array
    {
        return [
            'ya estaba · información' => ['already', 'already', 'status'],
            'texto nuevo · plazo' => ['stale', 'stale', 'alert'],
            'anti-robot · error' => ['antibot', 'refused', 'alert'],
            'rechazo del dominio · error' => ['closed', 'refused', 'alert'],
        ];
    }

    #[DataProvider('outcomes')]
    public function test_each_outcome_has_its_own_tone_and_a_title(string $status, string $marca, string $role): void
    {
        $html = $this->sheet(session: ['guardian_status' => $status, 'guardian_minor' => 'Ana']);

        $this->assertMatchesRegularExpression(
            '#<aside role="'.$role.'" style="[^"]*"[^>]*data-guardian-outcome="'.$marca.'"[^>]*>.*?<strong [^>]*>[^<]+</strong>.*?</aside>#s',
            $html,
            'cada desenlace con su tono (el aviso del sistema) y su título delante',
        );
        $this->assertStringContainsString('data-firma', $html, 'tras un desenlace que no firma, el formulario sigue ahí');
    }

    public function test_signed_is_the_done_block_with_the_child_and_who_signed(): void
    {
        $html = $this->sheet(session: ['guardian_status' => 'signed', 'guardian_minor' => 'Ana Gómez', 'guardian_signer' => 'Marta Ruiz · 600111222']);

        $this->assertMatchesRegularExpression('#<div class="aut-fin" id="aut-listo" tabindex="-1" role="status" data-guardian-outcome="signed">#', $html);
        $this->assertStringContainsString('<p class="aut-quien">Ana Gómez<span>Marta Ruiz · 600111222</span></p>', $html);
        $this->assertStringNotContainsString('data-firma', $html, 'firmada, el formulario no se vuelve a pintar');
    }

    public function test_the_hour_is_the_effective_duration_and_not_the_end_of_the_slot(): void
    {
        // Fiesta de dos horas sobre la rejilla de 60 min: la franja acaba a las 18:00 y el niño sale a
        // las 19:00 (la trampa de `#426`). La línea del brief dice la hora de EMPEZAR.
        $html = $this->sheet(durationMin: 120);

        $this->assertMatchesRegularExpression('#data-guardian-what>[^<]*17:00[^<]*</p>#', $html);
        $this->assertStringNotContainsString('18:00', $html, 'la hoja pinta el fin de la FRANJA');
    }

    public function test_the_page_marks_what_is_optional_and_draws_the_system_controls(): void
    {
        $html = $this->sheet();

        $this->assertStringNotContainsString('pz-campo__req', $html, 'volvió el asterisco de lo obligatorio');
        $this->assertSame(1, substr_count($html, '<span class="pz-campo__opt">'), 'solo el correo es opcional: el teléfono es obligatorio (`#745`)');
        $this->assertStringContainsString('name="guardian_phone"', $html);
        $this->assertStringNotContainsString('name="guardian_surname"', $html, 'el adulto escribe nombre y apellidos en UNA casilla (`#745`)');
        $this->assertStringContainsString('name="minor_born_on"', $html, 'la fecha de nacimiento se queda (`#745`)');
        $this->assertStringContainsString('name="guardian_relationship"', $html, 'la relación se queda (`#745`)');
        $this->assertMatchesRegularExpression('#<a href="[^"]*privacidad[^"]*" class="pz-enlace[^"]*">#', $html, 'la política es un control propio');
        $this->assertStringContainsString('class="pz-casilla', $html, 'la casilla que firma es la del sistema');

        // El logotipo entra como FICHERO si la instalación lo trae; sin él, el nombre.
        $expected = file_exists(public_path('img/client-logo.svg')) ? 'class="inv-logo"' : 'class="inv-marca"';
        $this->assertStringContainsString($expected, $html);
    }

    public function test_the_antibot_is_declared_with_a_label_only_when_it_exists(): void
    {
        $url = $this->reservation()->guardianAuthorizationSignedUrl();

        $this->assertStringNotContainsString('inv-tercero', (string) $this->get($url)->assertOk()->getContent(), 'sin claves no hay caja de tercero que declarar');

        Setting::query()->updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key']);
        Setting::query()->updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key']);
        Setting::flushMemo();
        Turnstile::flushCache();

        $this->assertMatchesRegularExpression(
            '#<div class="inv-tercero"><span class="inv-tercero-rotulo">'.preg_quote(__('guardian.antibot_label'), '#').'</span><div class="cf-turnstile"#',
            (string) $this->get($url)->assertOk()->getContent(),
            'el anti-robot es de un tercero y se dice qué es',
        );
    }

    /** @param  array<string, string>  $session */
    private function sheet(int $durationMin = 60, array $session = []): string
    {
        return (string) $this->withSession($session)->get($this->reservation($durationMin)->guardianAuthorizationSignedUrl())->assertOk()->getContent();
    }

    private function reservation(int $durationMin = 60): OrderItem
    {
        $zone = Zone::firstOrCreate(['slug' => 'jump'], ['name' => ['es' => 'Jump'], 'position' => 1, 'is_active' => true]);
        $type = TicketType::create([
            'zone_id' => $zone->id, 'type' => TicketType::TYPE_ENTRY, 'name' => ['es' => 'Entrada'],
            'duration_min' => $durationMin, 'seats_per_unit' => 1, 'is_sellable' => true, 'is_active' => true, 'position' => 1,
        ]);
        $slot = Slot::create([
            'zone_id' => $zone->id, 'date' => now()->addMonth()->toDateString(),
            'start_time' => '17:00:00', 'end_time' => '18:00:00', 'capacity' => 200, 'online_capacity' => 200,
        ]);
        $user = User::factory()->create(['email_verified_at' => now(), 'name' => 'Responsable De La Reserva']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => 'R-'.strtoupper(substr(md5((string) mt_rand()), 0, 6)),
            'status' => Order::STATUS_PAID, 'subtotal' => 500, 'tax' => 0, 'total' => 500, 'currency' => 'EUR', 'paid_at' => now(),
        ]);
        /** @var OrderItem $item */
        $item = $order->items()->create(['ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'quantity' => 4, 'unit_price' => 500, 'seats' => 4]);

        return $item;
    }
}
