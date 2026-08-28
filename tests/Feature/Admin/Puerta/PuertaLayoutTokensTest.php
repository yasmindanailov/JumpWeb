<?php

namespace Tests\Feature\Admin\Puerta;

use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 6 · subsistema A — el PRERREQUISITO de la pantalla de puerta
 * (`docs/specs/identidad-qr-puerta.md` §9.7 C·5).
 *
 * La página vive FUERA del shell de Filament (decisión #119) pero **reusa su bundle CSS**. Ese bundle
 * escribe `var(--gray-500)`, `var(--primary-600)`, `var(--success-600)`… y **no declara ninguna**:
 * quien las emite es `@filamentStyles`, y el layout de puerta no lo llamaba. MEDIDO en navegador antes
 * del arreglo: `--gray-500` vacía, `text-gray-500` calculando `rgb(0,0,0)` (negro puro, no gris), el
 * `<body>` en `rgba(0,0,0,0)` y el `ring` del formulario como un borde NEGRO SÓLIDO.
 *
 * Es un fallo que **no rompe ningún test de conducta**: la página respondía 200 y decía lo correcto,
 * solo se veía mal. Por eso la red tiene que mirar los TOKENS, no el texto.
 *
 * Dos cosas distintas, y cada una cae por su lado:
 *  - que `@filamentStyles` esté (si se quita, no hay `--gray-50:` en el HTML);
 *  - que el panel esté BOOTEADO (`ValidarRegistro::boot()`). Sin eso `@filamentStyles` sigue emitiendo
 *    tokens —los de fábrica de Filament— y la página parece arreglada, pero `primary` deja de seguir
 *    la marca del cliente (`theme.brand`) y pasa a ser el ámbar de Filament. Se comprueba contra el
 *    valor DERIVADO de la marca, no contra un literal.
 */
class PuertaLayoutTokensTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function staff(): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', 'staff')->value('id')]);

        return $u;
    }

    public function test_the_layout_emits_the_panel_design_tokens(): void
    {
        $html = $this->actingAs($this->staff())->get(route('admin.puerta.validar'))->assertOk()->getContent();

        // Los grises son el 84 % del problema medido: la vista los pide en cada texto secundario.
        foreach (['--gray-50:', '--gray-500:', '--gray-950:', '--success-600:', '--warning-600:', '--danger-600:'] as $token) {
            $this->assertStringContainsString($token, $html, "sin {$token} el bundle del panel pinta ese color como «nada»");
        }
    }

    /**
     * ⚠️ El primer test NO basta: `@filamentStyles` emite tokens **aunque el panel no esté booteado**
     * —los de fábrica de Filament— y la página parecería arreglada mientras `primary` deja de seguir
     * la marca del cliente. Aquí se separa una cosa de la otra.
     *
     * ⚠️ Y no se puede probar cambiando `theme.brand` a mitad de test: `AdminPanelProvider::colors()`
     * resuelve el hex al REGISTRAR el panel (arranque de la app), no al pintar. Se compara, pues,
     * contra el valor vivo… **y contra el ámbar de fábrica**, que es lo que saldría sin `boot()`.
     */
    public function test_primary_follows_the_client_brand_and_not_filaments_default(): void
    {
        $html = $this->actingAs($this->staff())->get(route('admin.puerta.validar'))->assertOk()->getContent();

        $brand = Color::hex(ThemeSettings::panelPrimaryHex())[600];

        $this->assertStringContainsString('--primary-600:'.$brand.';', $html, 'la marca del cliente no llega a la puerta: ¿está el panel booteado?');
        $this->assertNotSame(Color::Amber[600], $brand, 'este test solo discrimina si la marca NO es el ámbar de Filament');
        $this->assertStringNotContainsString('--primary-600:'.Color::Amber[600].';', $html, 'ámbar de fábrica = el panel no se booteó y `primary` no es del cliente');
    }

    /**
     * ⚠️ La spec (§9.7 C·5) daba por hecho que `@filamentStyles` «trae además la tipografía del
     * panel». MEDIDO en navegador: **no la trae**. La fuente la montan otras cuatro líneas del layout
     * del panel, y sin ellas el `<body>` calculaba la pila del sistema (`ui-sans-serif, system-ui…`)
     * y —peor— `--mono-font-family` quedaba sin definir, con lo que `var(--font-mono)` era un valor
     * INVÁLIDO y el código de pedido de cada reserva salía en sans en vez de en monoespaciada.
     */
    public function test_the_layout_also_wires_the_panel_typography(): void
    {
        $html = $this->actingAs($this->staff())->get(route('admin.puerta.validar'))->assertOk()->getContent();

        $this->assertStringContainsString('--font-family:', $html, 'sin esto el kiosco no usa la tipografía del panel');
        $this->assertStringContainsString('--mono-font-family:', $html, 'sin esto `var(--font-mono)` es inválido y el código de pedido sale en sans');
    }

    public function test_the_body_does_not_repaint_by_hand_what_fi_body_already_paints(): void
    {
        $html = $this->actingAs($this->staff())->get(route('admin.puerta.validar'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<body class="fi-body"/', $html);
        // `.fi-body` ya trae fondo, color de texto y `min-h-dvh` en claro y en oscuro. Repetirlo con
        // utilidades era lo que hacía que el fondo se viera transparente mientras faltaban los tokens.
        $this->assertDoesNotMatchRegularExpression('/<body[^>]*\bbg-gray-/', $html);
    }
}
