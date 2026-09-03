<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\CalendarPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * `#461` — EL SHELL DEL PANEL: la marca por tema, el buscador, el idioma, la escala de acción y el
 * menú lateral estrecho.
 *
 * Cinco encargos del owner sobre el armazón, y cada uno tiene una forma concreta de regresar sin
 * que nada se ponga rojo. Esto es lo que vigila, en orden de daño:
 *
 *  1. **Que el logotipo no se quede invisible en modo oscuro.** El panel tiene tres modos de tema y
 *     por defecto sigue al sistema, así que la mitad de los operadores puede estar en oscuro sin
 *     haber elegido nada. Un `<img>` no se adapta: hacen falta las dos variantes. Y el modo de
 *     fallo que más cuesta ver es el CONTRARIO —que alguien «simplifique» a una sola imagen y en
 *     oscuro se pierda la marca—, porque en claro se sigue viendo perfecta.
 *  2. **Que el nombre accesible no se duplique ni se pierda.** Con dos imágenes, `display: none`
 *     saca el `alt` del árbol; por eso las dos van `aria-hidden` y el nombre lo pone un `sr-only`
 *     que está siempre. Esto no lo ve ninguna captura.
 *  3. **Que el rótulo del menú quepa.** El sidebar mide 6rem; un rótulo largo se parte por la mitad
 *     («Calend/ario») y eso no falla en ningún sitio, solo se lee mal. La regla es de LONGITUD y
 *     está medida: con el ítem en 81 px, «Calendario» ocupa 57 y quedan ~16 px de holgura, que a
 *     11 px son unos tres caracteres.
 *  4. **Que la escala de acción primaria siga siendo UNA.** El owner pidió explícitamente que no
 *     haya incoherencia entre tamaños; un token en un sitio es lo único que lo impide.
 *  5. **Que el idioma no vuelva al topbar** ni deje de ser un POST (cambiarlo muta estado y se
 *     audita: un `GET` sería un cambio de estado por navegación).
 */
class PanelShellTest extends TestCase
{
    use RefreshDatabase;

    /** Los sitios del menú plano que son CLASE (los mismos de `AdminNavigationTest`). */
    private const FLAT_MENU = [
        Dashboard::class,
        CalendarPage::class,
        OrderResource::class,
        UserResource::class,
    ];

    /**
     * Tope de caracteres del rótulo del menú lateral.
     *
     * ⚠️ **No es un número redondo, es una MEDIDA**: con el sidebar en 6rem y el carril a 0,5rem por
     * lado, el ítem tiene 81 px y su contenido 73. «Calendario» (10 caracteres) mide **57 px** a
     * 11 px de cuerpo → sobran 16, que son ~3 caracteres. Trece es el techo con holgura.
     */
    private const MAX_MENU_LABEL_CHARS = 13;

    private ?string $publicPath = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        if ($this->publicPath !== null) {
            File::deleteDirectory($this->publicPath);
        }

        parent::tearDown();
    }

    /**
     * Apunta `public_path()` a un directorio TEMPORAL para las pruebas de la MARCA, que dependen de
     * qué ficheros hay en `public/img/`. Escribirlos en el `public/` del repo dejaría artefactos si
     * el test muere a medias (el recurso del kit falso de `#302`).
     *
     * ⚠️⚠️ **Solo en las pruebas que renderizan la VISTA suelta, nunca en las que piden una PÁGINA**:
     * el manifiesto de Vite se busca bajo `public_path()`, así que con el directorio temporal
     * cualquier petición HTTP al panel devuelve 500 —«Vite manifest not found»—. Lo cazó esta misma
     * suite: dos casos en rojo por un `setUp` demasiado ancho.
     */
    private function useTemporaryPublicPath(): void
    {
        $this->publicPath = storage_path('framework/testing/public-'.uniqid());
        File::ensureDirectoryExists($this->publicPath.'/img');
        $this->app->usePublicPath($this->publicPath);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    private function putLogo(string $file): void
    {
        File::put($this->publicPath.'/img/'.$file, '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1 1"></svg>');
    }

    private function brand(): string
    {
        return view('filament.admin.brand')->render();
    }

    // ─── 1 · La marca ────────────────────────────────────────────────────────────────────────

    public function test_brand_serves_both_variants_when_the_installation_provides_them(): void
    {
        $this->useTemporaryPublicPath();
        $this->putLogo('client-logo.svg');
        $this->putLogo('client-logo-ink.svg');

        $html = $this->brand();

        // La clara se esconde en oscuro y la de tinta aparece: si alguien quita una de las dos
        // clases, el panel en oscuro enseña el logotipo equivocado (o los dos).
        $this->assertStringContainsString('client-logo.svg', $html);
        $this->assertStringContainsString('client-logo-ink.svg', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*dark:hidden[^"]*"[^>]*src="[^"]*client-logo\.svg/', $html);
        $this->assertMatchesRegularExpression('/class="[^"]*dark:block[^"]*"[^>]*src="[^"]*client-logo-ink\.svg/', $html);
    }

    public function test_brand_with_two_images_keeps_exactly_one_accessible_name(): void
    {
        $this->useTemporaryPublicPath();
        $this->putLogo('client-logo.svg');
        $this->putLogo('client-logo-ink.svg');

        $html = $this->brand();

        // Ninguna imagen aporta nombre (si lo hiciera, en cada tema se anunciaría una y en el otro
        // ninguna), y el nombre vive en un `sr-only` que está en las dos superficies.
        $this->assertSame(2, substr_count($html, 'aria-hidden="true"'), 'Las DOS imágenes tienen que ir ocultas al lector.');
        $this->assertSame(0, preg_match('/<img[^>]*alt="[^"]+"/', $html), 'Con dos variantes ninguna imagen puede llevar `alt` con texto.');
        $this->assertStringContainsString('sr-only', $html);
    }

    public function test_brand_falls_back_to_the_light_logo_when_there_is_no_ink_variant(): void
    {
        $this->useTemporaryPublicPath();
        $this->putLogo('client-logo.svg');

        $html = $this->brand();

        // Sin variante de tinta se sigue viendo el logotipo en los DOS temas: el hueco falla hacia
        // visible. Y como hay una sola imagen, recupera su `alt` (no hace falta el `sr-only`).
        $this->assertStringContainsString('client-logo.svg', $html);
        $this->assertStringNotContainsString('dark:hidden', $html);
        $this->assertMatchesRegularExpression('/<img[^>]*alt="[^"]+"/', $html);
    }

    public function test_brand_falls_back_to_the_wordmark_without_any_logo(): void
    {
        $this->useTemporaryPublicPath();
        $html = $this->brand();

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('fi-jj-brand-wordmark', $html);
    }

    public function test_brand_subtitle_says_administracion(): void
    {
        $this->useTemporaryPublicPath();

        $this->assertSame('Administración', __('admin.panel_subtitle'));
        $this->assertStringContainsString('Administración', $this->brand());
    }

    // ─── 2 · El menú lateral ─────────────────────────────────────────────────────────────────

    public function test_sidebar_is_narrow_and_has_no_collapse_toggle(): void
    {
        $panel = Filament::getPanel('admin');

        // La flecha la pinta Filament SOLO si el sidebar es colapsable: retirarlo es lo que la quita.
        $this->assertFalse($panel->isSidebarCollapsibleOnDesktop(), 'El menú va SIEMPRE plegado: sin flecha de contraer.');
        $this->assertSame('6rem', $panel->getSidebarWidth());
    }

    public function test_flat_menu_labels_fit_the_narrow_sidebar(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        foreach (self::FLAT_MENU as $screen) {
            $label = (string) $screen::getNavigationLabel();

            $this->assertLessThanOrEqual(
                self::MAX_MENU_LABEL_CHARS,
                mb_strlen($label),
                "«{$label}» no cabe en el menú de 6rem: con más de ".self::MAX_MENU_LABEL_CHARS
                .' caracteres el rótulo se parte y se lee mal. Acorta el rótulo o ensancha el sidebar.'
            );
        }
    }

    // ─── 3 · El idioma ───────────────────────────────────────────────────────────────────────

    public function test_language_lives_in_the_user_menu_and_posts(): void
    {
        // ⚠️ Filament compone el menú CON el usuario (`getUserName()`): sin sesión lanza `TypeError`.
        $this->actingAs($this->userWithRole('admin'));

        $items = Filament::getPanel('admin')->getUserMenuItems();

        foreach (['locale_es', 'locale_zh_CN'] as $key) {
            $this->assertArrayHasKey($key, $items, "Falta la entrada de idioma «{$key}» en el menú del avatar.");

            $item = $items[$key];
            $this->assertInstanceOf(Action::class, $item);
            // Cambiar de idioma escribe en `users.panel_locale` y deja auditoría: tiene que ser POST.
            $this->assertTrue($item->shouldPostToUrl(), 'El cambio de idioma MUTA estado: no puede ser un enlace GET.');
            $this->assertStringContainsString('/admin/lang/', (string) $item->getUrl());
        }
    }

    public function test_the_active_language_is_marked_and_the_other_is_not(): void
    {
        // Sin esto el menú ofrece dos idiomas y no dice en cuál estás. Es lo que se perdió al
        // retirar el disparador del topbar, que llevaba el idioma actual en su `aria-label`.
        $user = $this->userWithRole('admin');
        $user->forceFill(['panel_locale' => 'zh_CN'])->save();
        $this->actingAs($user);

        $items = Filament::getPanel('admin')->getUserMenuItems();

        $this->assertNotSame(
            $items['locale_es']->getIcon(),
            $items['locale_zh_CN']->getIcon(),
            'El idioma activo y el inactivo no pueden pintarse igual.'
        );
        $this->assertSame('primary', $items['locale_zh_CN']->getColor());
        $this->assertSame('gray', $items['locale_es']->getColor());
    }

    public function test_language_switcher_is_gone_from_the_topbar(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        // La vista del selector se retiró con su único consumidor; que su clase no vuelva al topbar
        // es lo que impide que alguien lo «recupere» y acabe en los dos sitios.
        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee('fi-jj-locale-switcher');
    }

    // ─── 4 · El buscador y la escala de acción ───────────────────────────────────────────────

    public function test_global_search_placeholder_says_what_it_finds(): void
    {
        $placeholder = __('filament-panels::global-search.field.placeholder');

        $this->assertSame('Busca cliente, pedido, reserva…', $placeholder);

        // ⚠️ El override de vendor es PARCIAL a propósito: si alguien copia el fichero entero, las
        // demás claves quedan congeladas en la versión de hoy. Esta comprueba que siguen viniendo
        // del paquete.
        $this->assertSame('Búsqueda global', __('filament-panels::global-search.field.label'));
    }

    public function test_primary_action_scale_is_declared_once_and_used_by_the_cta(): void
    {
        $css = File::get(resource_path('css/filament/admin/theme.css'));

        $this->assertMatchesRegularExpression('/--jj-action-h:\s*2\.75rem/', $css, 'La escala de acción primaria vive en UN token.');
        $this->assertMatchesRegularExpression('/\.fi-jj-action\s*\{[^}]*min-height:\s*var\(--jj-action-h\)/s', $css);

        // Y el CTA la usa por CLASE, no con un tamaño escrito a mano: es lo que evita que el
        // siguiente botón importante estrene su propio número.
        $blade = File::get(resource_path('views/filament/admin/create-order-topbar-button.blade.php'));
        $this->assertStringContainsString('fi-jj-action', $blade);
    }
}
