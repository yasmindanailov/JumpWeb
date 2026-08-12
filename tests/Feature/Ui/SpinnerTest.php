<?php

namespace Tests\Feature\Ui;

use App\Livewire\Auth\Login;
use App\Livewire\Tickets\Purchase;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sistema de feedback de carga (spinner). Ver docs/UI-SPINNER.md.
 *
 * Pruebas de RENDERIZADO: que el spinner / velo se inyecta donde toca, con el
 * objetivo (wire:target) correcto y de forma accesible. El comportamiento en vivo
 * de wire:loading lo aporta Livewire y se valida en pantalla.
 */
class SpinnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_spinner_component_is_accessible_by_default(): void
    {
        $html = Blade::render('<x-ui.spinner size="lg" />');

        $this->assertStringContainsString('jj-spinner', $html);
        $this->assertStringContainsString('jj-spinner--lg', $html);
        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('jj-spinner__sr', $html); // texto para lector de pantalla
    }

    public function test_decorative_spinner_is_hidden_from_screen_readers(): void
    {
        $html = Blade::render('<x-ui.spinner size="xs" :decorative="true" />');

        $this->assertStringContainsString('jj-spinner--xs', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
        $this->assertStringNotContainsString('role="status"', $html); // el contexto ya da el texto
    }

    public function test_loading_overlay_is_local_by_default_and_fullscreen_when_fixed(): void
    {
        $local = Blade::render('<x-ui.loading-overlay />');
        $this->assertStringContainsString('jj-loading', $local);
        $this->assertStringContainsString('jj-spinner', $local);
        $this->assertStringNotContainsString('jj-spinner-overlay', $local);

        $fixed = Blade::render('<x-ui.loading-overlay :fixed="true" />');
        $this->assertStringContainsString('jj-spinner-overlay', $fixed);
    }

    public function test_layout_links_the_spinner_stylesheet(): void
    {
        $this->seed(LandingContentSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('css/spinner.css', false);
    }

    public function test_login_button_shows_a_spinner_targeting_the_login_action(): void
    {
        Livewire::test(Login::class)
            ->assertSeeHtml('wire:target="login"')
            ->assertSeeHtml('jj-spinner');
    }

    public function test_purchase_panel_has_a_loading_veil_targeting_panel_actions(): void
    {
        $this->seed(LandingContentSeeder::class);

        Livewire::test(Purchase::class)
            ->assertSeeHtml('jj-loading')
            ->assertSeeHtml('addToCart'); // el velo cubre las acciones del panel (navegación + carrito)
    }

    public function test_lazy_placeholder_renders_the_brand_spinner(): void
    {
        $html = view('livewire.tickets.purchase-placeholder')->render();

        $this->assertStringContainsString('purchase-loading', $html);
        $this->assertStringContainsString('jj-spinner', $html);
    }
}
