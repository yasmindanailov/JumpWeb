<?php

namespace Tests\Feature\Theme;

use Tests\Support\ReadsSiteStylesheets;
use Tests\TestCase;

/**
 * **EL CAJÓN: TEXTO, DEDO Y PIE** — la tanda A de su auditoría (`docs/specs/auditoria-cajon.md` §8,
 * `DECISIONES #450`).
 *
 * La auditoría del cajón (`#438`) midió, dentro del panel y en siete anchos, tres cosas que ninguna
 * guarda miraba porque el cajón estaba «aparcado»:
 *  - **C2** — el cian de zona pintaba TEXTO en todas las pantallas del embudo («Volver» y la línea de
 *    contexto a 2,21 : 1 sobre la banda; los enlaces a 2,45; «Incluido» en `--ok` a 2,95).
 *  - **C3** — la mitad de los controles no llegaba a 44 px (la × que cierra el diálogo medía 15 × 26).
 *  - **M3** — el CTA del pie partía en dos líneas a 320 cuando el total era ancho.
 *
 * Esta guarda fija lo que la tanda A dejó: en el cajón el texto que responde lee `--interactive`
 * (`#436`) y el color semántico como texto lee su par oscuro (`--ok-text` · `--err-text`, el mismo
 * trato que `--on-*` en `#434`); los controles enumerados leen `--tap-min`; el CTA no parte.
 *
 * ⚠️ Es estática: no mide píxeles. Que un control lleve `min-height: var(--tap-min)` no demuestra que
 * acabe midiendo 44 (un ancestro puede recortarlo). Eso lo mide la sonda del embudo
 * (`storage/app/audit-cajon-hallmark.mjs`); aquí se fija lo que la sonda no puede vigilar en cada push.
 */
class DrawerControlsTest extends TestCase
{
    use ReadsSiteStylesheets;

    /** Los prefijos de selector que son del cajón (los tres motores que ha tenido comparten esta hoja). */
    private const DRAWER_PREFIXES = [
        '.sidecart', '.purchase', '.bk-', '.cart', '.catalog', '.auth__', '.form__', '.check', '.acct',
        '.acc-tile', '.paydue', '.eventfields', '.addons__', '.cal', '.daystrip', '.timestrip', '.qtybox',
        '.entry__', '.whoblock', '.guardnote', '.state-badge', '.pwd-input',
    ];

    /**
     * Lo que SÍ lee `--zone-*` dentro del cajón y no es texto sobre papel: el cargador de marca (`#259`,
     * «Tres botes» hereda el color de la zona) y la letra del avatar de la cuenta, que va SOBRE TINTA
     * (6,85 con el paquete de PlayJump: es un relleno de marca con su texto, no texto de color sobre
     * papel). Solo encoge.
     */
    private const ZONE_ALLOWED = [
        '.jj-loading', '.jj-spinner-overlay', '.purchase-loading', '.acct__avatar',
    ];

    /** Los controles del cajón que crecen al dedo, con lo que medían antes (`auditoria-cajon.md` §3·C3). */
    private const TAP_44 = [
        '.sidecart__close' => 'la × que cierra el diálogo: 15 × 26',
        '.pwd-input__toggle' => 'el ojo de la contraseña: 32 × 32',
        '.addons__moreinfo' => '«Más info»: 47 × 20',
        '.auth__link' => '«¿Olvidaste tu contraseña?»: 151 × 17',
        '.auth__switch button' => 'el cambio entre entrar y crear cuenta del modal viejo',
        '.acct__qr' => '«Mi QR»: 73 × 28',
        '.acct__btn' => 'los botones del bloque de cuenta: × 41',
        '.purchase__add-more' => '«+ Añadir otra reserva»: × 42',
        '.cal__day' => 'las celdas del calendario: 35 × 35 a 320',
        '.check' => 'las casillas y su texto: × 21',
        '.entry__qty' => 'la cantidad, que se escribe: 34 × 18',
        '.purchase__authtabs .zone-tab' => 'las pestañas de entrar y crear cuenta: × 37',
        '.form__hint > summary' => '«Leer el texto completo» del descargo: × 16',
        '.sidecart__panel .btn' => 'todo botón de la familia dentro del cajón: × 42',
    ];

    public function test_the_scan_sees_the_corpus(): void
    {
        $this->assertGreaterThan(1500, count($this->siteRules()), 'el localizador de reglas se ha roto');
        $this->assertNotEmpty($this->drawerRules(), 'no se encuentra ninguna regla del cajón');
    }

    /** C2 — dentro del cajón ningún TEXTO lee el color de una zona: lo que responde es `--interactive`. */
    public function test_no_drawer_text_reads_a_zone_colour(): void
    {
        $offenders = [];

        foreach ($this->drawerRules() as $rule) {
            if ($this->isAllowedZoneConsumer($rule['selector'])) {
                continue;
            }
            if (preg_match('/(?<![-\w])color\s*:\s*var\(--zone-/', $rule['body'])) {
                $offenders[] = $rule['selector'];
            }
        }

        $this->assertSame([], $offenders, "Texto del cajón pintado con `--zone-*` (la C2 de la auditoría, 2,21 : 1 medido):\n  ".implode("\n  ", $offenders));
    }

    /** C2 — el color semántico COMO TEXTO lee su par oscuro, no el relleno. */
    public function test_semantic_colour_as_text_reads_its_dark_pair(): void
    {
        $offenders = [];

        foreach ($this->drawerRules() as $rule) {
            if (preg_match('/(?<![-\w])color\s*:\s*var\(--(ok|err|warn)\)/', $rule['body'])) {
                $offenders[] = $rule['selector'];
            }
        }

        $this->assertSame([], $offenders, "Texto del cajón en `--ok`/`--err`/`--warn` a secas (2,95 medido en «Incluido»): usa `--ok-text`/`--err-text`.\n  ".implode("\n  ", $offenders));
    }

    /** C3 — cada control enumerado lee el mínimo táctil. La lista solo crece. */
    public function test_every_listed_control_reads_the_touch_minimum(): void
    {
        $rules = $this->rulesBySelector();

        foreach (self::TAP_44 as $selector => $why) {
            $this->assertArrayHasKey($selector, $rules, "`{$selector}` ya no existe: {$why}");
            $this->assertMatchesRegularExpression(
                '/(?<![-\w])(min-height|height)\s*:\s*var\(--tap-min\)/',
                $rules[$selector],
                "`{$selector}` ya no llega a 44 ({$why}): falta `min-height: var(--tap-min)`.",
            );
        }
    }

    /** M3 — el CTA del pie no parte en dos líneas: el total es el que encoge. */
    public function test_the_foot_cta_never_wraps_and_the_total_shrinks_first(): void
    {
        $rules = $this->rulesBySelector();

        $this->assertArrayHasKey('.bk-cta', $rules);
        $this->assertMatchesRegularExpression('/white-space\s*:\s*nowrap/', $rules['.bk-cta'], 'el CTA del pie vuelve a poder partir en dos líneas');
        $this->assertArrayHasKey('.bk-foot__v', $rules);
        $this->assertMatchesRegularExpression('/font-size\s*:\s*clamp\(/', $rules['.bk-foot__v'], 'el total del pie ya no encoge con la ventana: a 320 vuelve a empujar el botón a dos líneas');
    }

    /** m1 — «Septiembre De 2026»: solo la primera letra del mes va en mayúscula. */
    public function test_the_month_label_capitalises_only_its_first_letter(): void
    {
        $rules = $this->rulesBySelector();

        $this->assertArrayHasKey('.cal__month', $rules);
        $this->assertDoesNotMatchRegularExpression('/text-transform\s*:\s*capitalize/', $rules['.cal__month'], 'vuelve «Septiembre De 2026»');
        $this->assertArrayHasKey('.cal__month::first-letter', $rules, 'la primera letra del mes ya no se pone en mayúscula');
    }

    /** M10 — con el diálogo abierto, la página no anima detrás del velo. */
    public function test_the_page_pauses_its_loops_behind_the_open_drawer(): void
    {
        $paused = array_filter($this->siteRules(), fn (array $r) => str_contains($r['selector'], 'body:has(.sidecart.is-open)') && preg_match('/animation-play-state\s*:\s*paused/', $r['body']));

        $this->assertNotEmpty($paused, 'los bucles de la página vuelven a correr detrás del cajón abierto (M10)');
    }

    // ─────────────────────────────────────────────────────────────────────────────────

    /** @return list<array{selector: string, body: string, sheet: string}> */
    private function drawerRules(): array
    {
        $out = [];

        foreach ($this->siteRules() as $rule) {
            foreach (explode(',', $rule['selector']) as $single) {
                $single = trim($single);

                foreach (self::DRAWER_PREFIXES as $prefix) {
                    if (str_starts_with($single, $prefix)) {
                        $out[] = ['selector' => $single, 'body' => $rule['body'], 'sheet' => $rule['sheet']];

                        continue 3;
                    }
                }
            }
        }

        return $out;
    }

    private function isAllowedZoneConsumer(string $selector): bool
    {
        foreach (self::ZONE_ALLOWED as $allowed) {
            if (str_starts_with($selector, $allowed)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> selector exacto (espacios normalizados) => cuerpo, concatenando repeticiones */
    private function rulesBySelector(): array
    {
        $out = [];

        foreach ($this->siteRules() as $rule) {
            foreach (explode(',', $rule['selector']) as $single) {
                $single = trim((string) preg_replace('/\s+/', ' ', $single));
                $out[$single] = ($out[$single] ?? '').' '.$rule['body'];
            }
        }

        return $out;
    }
}
