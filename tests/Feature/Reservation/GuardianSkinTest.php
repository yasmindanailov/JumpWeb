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
 * La PIEL del justificante del menor invitado (`docs/specs/celebracion-e-invitacion.md` §4.3, T3).
 *
 * ▶ Vigila las ocho grietas propias que el canvas midió (`doc/formulario.md`, J-01…J-08) y una que no
 * estaba en el canvas: **la barra de firmar**. La T2 hizo `.gf-savebar` pegada y en FILA para el
 * post-form, y esta hoja metía dentro dos párrafos legales y el anti-robot: medido a 390 × 844, la barra
 * ocupaba 401 px pegada abajo y el botón se salía 65 px de la pantalla, sin que fallara ningún test. Por
 * eso el primer caso mira QUÉ lleva la barra.
 *
 * ⚠️ Las reglas `.guardian__*` viven DENTRO del bloque de la hoja en `site.css`, así que las escalas de
 * radios, tallas y sombras las vigila `GuestFormSkinTest`; aquí va lo que es solo de esta pantalla. Y el
 * CSS se mira **sin comentarios**: su prosa nombra a propósito lo que se retiró (`#553`).
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

    public function test_the_sign_bar_carries_who_and_the_button_and_nothing_else(): void
    {
        $html = $this->sheet();

        $this->assertSame(1, preg_match('#<div class="gf-savebar">(.*?)</div>\s*</form>#s', $html, $bar), 'la barra de firmar cierra el formulario');
        $this->assertStringNotContainsString('<p', $bar[1], 'un párrafo dentro de la barra pegada la convierte en media pantalla (medido: 401 px de 844)');
        $this->assertStringNotContainsString('cf-turnstile', $bar[1], 'el anti-robot no viaja en la barra');
        $this->assertStringContainsString('data-guardian-who', $bar[1], 'la barra dice a QUIÉN se autoriza');
        $this->assertMatchesRegularExpression('#<button type="submit"[^>]*>\s*'.preg_quote(__('guardian.submit_short'), '#').'\s*</button>#', $bar[1]);
    }

    public function test_the_waiver_is_read_whole_and_the_guardian_rules_live_in_the_sheet_block(): void
    {
        $sheet = $this->sheetCss();

        $this->assertSame(1, preg_match('#\.guardian__waiver\s*\{([^}]*)\}#', $sheet, $rule), 'la regla del descargo vive en el bloque de la hoja');
        $this->assertStringNotContainsString('max-height', $rule[1], 'el descargo se lee ENTERO: sin ventana (J-01)');
        $this->assertStringNotContainsString('overflow', $rule[1], 'ni scroll dentro del scroll (J-01)');

        $this->assertStringNotContainsString('dashed', $sheet, 'el sistema no tiene bordes discontinuos (J-06)');
        // Los tonos que emite la vista tienen REGLA: una clase sin ella pinta el tono por defecto sin fallar.
        foreach (['ok' => '--ok', 'attn' => '--attn', 'err' => '--err'] as $tone => $token) {
            $this->assertMatchesRegularExpression('#\.gf-notice--'.$tone.'\s*\{\s*--gf-tone:\s*var\('.$token.'\)#', $sheet, "el tono «{$tone}» no tiene regla (J-02)");
        }
        $this->assertMatchesRegularExpression('#\.gf-page \.eventfields__error\s*\{[^}]*var\(--err-ink\)#', $sheet, 'el error de un campo se pinta en rojo de TEXTO');

        // Fuera del bloque no queda ninguna regla de esta pantalla: si vuelve una, las escalas de
        // `GuestFormSkinTest` dejan de verla.
        $all = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(public_path('css/site.css')));
        $this->assertSame(0, preg_match('#\.(guardian__|gf-group__num)#', str_replace($sheet, '', $all)), 'una regla del justificante fuera del bloque de la hoja');
    }

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function outcomes(): array
    {
        return [
            'firmada · éxito' => ['signed', 'gf-notice gf-notice--ok', 'status'],
            'ya estaba · información' => ['already', 'gf-notice', 'status'],
            'texto nuevo · plazo' => ['stale', 'gf-notice gf-notice--attn', 'alert'],
            'anti-robot · error' => ['antibot', 'gf-notice gf-notice--err', 'alert'],
            'rechazo del dominio · error' => ['closed', 'gf-notice gf-notice--err', 'alert'],
        ];
    }

    #[DataProvider('outcomes')]
    public function test_each_outcome_has_its_own_tone_and_a_title(string $status, string $classes, string $role): void
    {
        $html = $this->sheet(session: ['guardian_status' => $status, 'guardian_minor' => 'Ana']);

        $this->assertMatchesRegularExpression(
            '#<div class="'.preg_quote($classes, '#').'" role="'.$role.'" data-guardian-outcome="'.$status.'">\s*<p class="gf-notice__title">[^<]+</p>\s*<p class="gf-notice__text">[^<]+</p>#',
            $html,
            'cinco desenlaces, cuatro tonos, y cada uno con su título (J-02)',
        );
    }

    public function test_the_hour_is_the_effective_duration_and_not_the_end_of_the_slot(): void
    {
        // Fiesta de dos horas sobre la rejilla de 60 min: la franja acaba a las 18:00 y el niño sale a
        // las 19:00 (la trampa de `#426`).
        $html = $this->sheet(durationMin: 120);

        $this->assertStringContainsString('17:00–19:00', $html);
        $this->assertStringNotContainsString('18:00', $html, 'la hoja pinta el fin de la FRANJA, no el de la visita');
    }

    public function test_the_page_marks_what_is_optional_and_draws_the_system_controls(): void
    {
        $html = $this->sheet();

        $this->assertStringNotContainsString('eventfields__req', $html, 'volvió el asterisco de lo obligatorio');
        $this->assertSame(2, substr_count($html, '<span class="gf-opt">'), 'correo y teléfono se marcan como opcionales');
        $this->assertMatchesRegularExpression('#<a class="gf-legal" href="[^"]*privacidad[^"]*">#', $html, 'la política es un control propio (J-07)');
        $this->assertMatchesRegularExpression('#<input type="checkbox" class="guardian__check"#', $html, 'la casilla que firma es la del sistema (J-03)');
        $this->assertSame(3, substr_count($html, '<span class="gf-group__num">'), 'los tres pasos llevan su ordinal (J-04)');

        // El logotipo entra como FICHERO si la instalación lo trae (J-05); sin él, el nombre.
        $expected = file_exists(public_path('img/client-logo.svg')) ? 'class="gf-mark__logo"' : 'class="gf-mark__brand"';
        $this->assertStringContainsString($expected, $html);
    }

    public function test_the_antibot_is_declared_with_a_label_only_when_it_exists(): void
    {
        $url = $this->reservation()->guardianAuthorizationSignedUrl();

        $this->assertStringNotContainsString('guardian__third', (string) $this->get($url)->assertOk()->getContent(), 'sin claves no hay caja de tercero que declarar');

        Setting::query()->updateOrCreate(['key' => 'security.turnstile_site_key'], ['value' => 'site-key']);
        Setting::query()->updateOrCreate(['key' => 'security.turnstile_secret'], ['value' => 'secret-key']);
        Setting::flushMemo();
        Turnstile::flushCache();

        $this->assertMatchesRegularExpression(
            '#<div class="guardian__third">\s*<span class="guardian__third-label">'.preg_quote(__('guardian.antibot_label'), '#').'</span>\s*<div class="cf-turnstile"#',
            (string) $this->get($url)->assertOk()->getContent(),
            'el anti-robot es de un tercero y se dice qué es (J-08)',
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

    /** El bloque de la hoja en `site.css`, sin comentarios (el mismo recorte que `GuestFormSkinTest`). */
    private function sheetCss(): string
    {
        $css = (string) file_get_contents(public_path('css/site.css'));
        $title = strpos($css, 'Formulario post-reserva — HOJA ENFOCADA');
        $this->assertNotFalse($title, 'no se encuentra el bloque de la hoja en site.css');
        $start = strrpos(substr($css, 0, $title), '/* ====');
        $end = strpos($css, '/* ====', $title);
        $this->assertNotFalse($end, 'no se encuentra el final del bloque de la hoja');

        return (string) preg_replace('#/\*.*?\*/#s', '', substr($css, $start, $end - $start));
    }
}
