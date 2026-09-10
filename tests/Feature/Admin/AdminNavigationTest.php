<?php

namespace Tests\Feature\Admin;

use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Pages\CalendarPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\RestrictsPuertaRole;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * #223 — La FORMA del panel: menú plano + «Ajustes» fuera del camino.
 *
 * ▶ `#320` — el menú del ADMIN pasa a CUATRO sitios: «Puerta» deja de salirle (no atiende por ahí, y
 * la tiene en «Ajustes → Sistema» y en el buscador). Al EMPLEADO le sigue saliendo, porque él sí
 * atiende. Y nace un tercer rol de equipo, `puerta`, que no navega el panel en absoluto: entra por el
 * mismo login y aterriza en su pantalla ({@see RestrictsPuertaRole}).
 *
 * Esta guarda no existía. Hasta el 2026-08-28 la navegación del panel —24 entradas en 6
 * grupos— no la miraba ni un solo test, y por eso se había desordenado sola: entradas
 * duplicadas (Calendario salía en el menú Y en la barra superior) y el 79 % del menú
 * ocupado por pantallas de puesta en marcha.
 *
 * Lo que fija, en orden de importancia:
 *
 *  1. **Que no haya pantallas huérfanas.** Es el riesgo REAL de esconder cosas: un recurso
 *     nuevo que no entra ni en el menú ni en Ajustes queda inalcanzable salvo tecleando su
 *     URL, y nada avisa. `test_every_registered_screen_is_reachable` obliga a colocar cada
 *     pantalla en uno de los tres sitios declarados.
 *  2. **Que el menú siga plano y en su orden**, para admin y para empleado.
 *  3. **Que esconder no conceda**: Ajustes no puede abrir nada que su usuario no pudiera
 *     abrir ya por la URL directa.
 *  4. **Que las dos pestañas de usuarios sean complementarias**: ninguna cuenta puede
 *     quedarse fuera de las dos ni salir en las dos.
 */
class AdminNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Los sitios del día a día que son CLASE, en su orden. «Puerta» no está aquí porque su página
     * vive fuera del shell de Filament (#119) y entra como `NavigationItem` suelto: se comprueba por
     * su rótulo, y desde `#320` solo le sale a quien atiende ahí — al admin no.
     *
     * @var array<int, class-string>
     */
    private const FLAT_MENU = [
        Dashboard::class,
        CalendarPage::class,
        OrderResource::class,
        UserResource::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
    }

    private function userWithRole(string $role): User
    {
        $u = User::factory()->create();
        $u->roles()->sync([Role::where('name', $role)->value('id')]);

        return $u;
    }

    /**
     * Los rótulos del menú lateral tal y como los vería este usuario, en su orden.
     *
     * ⚠️ Filament MEMOIZA la navegación por petición: construirla dos veces en el mismo
     * proceso para dos usuarios distintos devuelve la del primero. Medido el 2026-08-28,
     * y llegó a dar por bueno que un empleado veía las 24 entradas del admin. Por eso cada
     * caso resuelve UN solo usuario y `Filament::setCurrentPanel()` se rehace aquí dentro.
     *
     * @return array<int, string>
     */
    private function menuLabelsFor(User $user): array
    {
        $this->actingAs($user);

        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        $labels = [];

        foreach ($panel->getNavigation() as $group) {
            // El menú es PLANO: ningún grupo puede tener rótulo.
            $this->assertSame(
                '',
                (string) $group->getLabel(),
                'El menú del panel volvió a tener grupos: '.$group->getLabel(),
            );

            foreach ($group->getItems() as $item) {
                $labels[] = (string) $item->getLabel();
            }
        }

        return $labels;
    }

    public function test_admin_menu_is_flat_and_has_the_four_daily_places_in_order(): void
    {
        // `#320` (`[DECIDIDO owner]`): «Puerta» sale del menú — el gerente no atiende por ella, y la
        // conserva en «Ajustes → Sistema» (y por tanto en el buscador). El menú vuelve a CUATRO.
        $this->assertSame(
            ['Hoy', 'Calendario', 'Pedidos', 'Clientes'],
            $this->menuLabelsFor($this->userWithRole('admin')),
        );
    }

    public function test_staff_menu_drops_clients_because_it_needs_users_manage(): void
    {
        // El empleado tiene `calendar.view`, `orders.view` y `registrations.validate`, pero
        // NO `users.manage`: su menú son cuatro sitios, no cinco. No es una omisión.
        //
        // `#320`: conserva «Puerta» —él sí atiende ahí— y por eso su menú NO encoge con esta tanda.
        $this->assertSame(
            ['Hoy', 'Calendario', 'Pedidos', 'Puerta'],
            $this->menuLabelsFor($this->userWithRole('staff')),
        );
    }

    /**
     * `#320` (`[DECIDIDO owner]`) — el rol `puerta` NO navega el panel: cualquier ruta suya lo
     * devuelve a su pantalla ({@see RestrictsPuertaRole}). Es el puesto de la entrada, y con el mismo
     * login que el resto del equipo.
     */
    public function test_the_puerta_role_never_reaches_the_panel_and_lands_at_its_screen(): void
    {
        $puerta = $this->userWithRole('puerta');

        foreach ([Dashboard::getUrl(), CalendarPage::getUrl(), OrderResource::getUrl('index'), AdminSettingsHub::getUrl()] as $url) {
            $this->actingAs($puerta)
                ->get($url)
                ->assertRedirect(route('admin.puerta.validar'));
        }

        // Y su sitio SÍ le abre: lo que se le cierra es el panel, no su puesto de trabajo.
        $this->actingAs($puerta)->get(route('admin.puerta.validar'))->assertOk();
    }

    /**
     * `#320` — el rol de puerta trae SOLO sus dos permisos. Las dos hojas imprimibles de sala son de
     * `staff`, y aquí se comprueba que no se le regalan de rebote: `RequiresPanelRole` lo deja pasar
     * (es equipo) y quien lo frena es el permiso, que es donde vive la autorización.
     */
    public function test_the_puerta_role_does_not_get_the_printable_sheets(): void
    {
        $this->assertSame(
            ['registrations.validate', 'puerta.profile'],
            PermissionSeeder::PUERTA_DEFAULT_PERMISSIONS,
        );

        $puerta = $this->userWithRole('puerta');
        $this->assertFalse($puerta->hasPermission('orders.view'), 'la hoja de reserva imprimible no es suya');
        $this->assertFalse($puerta->hasPermission('calendar.view'), 'el resumen del día tampoco');
    }

    /** Ni al empleado ni al encargado los desvía nadie; y un rol de más alcance gana al acumularse. */
    public function test_staff_and_admin_are_not_sent_to_the_gate(): void
    {
        $this->actingAs($this->userWithRole('admin'))->get(Dashboard::getUrl())->assertOk();
        $this->actingAs($this->userWithRole('staff'))->get(Dashboard::getUrl())->assertOk();

        $both = $this->userWithRole('staff');
        $both->roles()->attach(Role::where('name', 'puerta')->value('id'));
        $this->actingAs($both)->get(Dashboard::getUrl())->assertOk();
    }

    /**
     * `#320` — la puerta se alcanza desde «Ajustes», que es lo que hace barato haberla sacado del
     * menú. ⚠️ Y no es un detalle de comodidad: el buscador global saca sus pantallas de la
     * navegación y de `visibleAreas()`, así que sin esta tarjeta la puerta desaparecía también del
     * buscador y quedaba accesible SOLO tecleando la URL.
     */
    public function test_the_gate_is_reachable_from_settings_for_the_admin(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $urls = [];
        foreach ((new AdminSettingsHub)->visibleAreas() as $area) {
            foreach ($area['items'] as $item) {
                $urls[] = $item['url'];
            }
        }

        $this->assertContains(route('admin.puerta.validar'), $urls);
    }

    /**
     * LA guarda del rediseño: toda pantalla registrada en el panel se alcanza por el menú,
     * por Ajustes, o está en la lista de excepciones declaradas — y nunca por accidente.
     */
    public function test_every_registered_screen_is_reachable(): void
    {
        $panel = Filament::getPanel('admin');

        $inHub = [];

        foreach (AdminSettingsHub::areas() as $entries) {
            foreach ($entries as $entry) {
                // `#320`: una entrada puede no ser de Filament (la puerta vive fuera del shell, y por
                // eso tampoco aparece entre las REGISTRADAS que esta guarda recorre).
                if (isset($entry['class'])) {
                    $inHub[] = $entry['class'];
                }
            }
        }

        $placed = array_merge(self::FLAT_MENU, $inHub, array_keys(AdminSettingsHub::OUTSIDE_HUB));

        $registered = array_merge(
            array_values($panel->getResources()),
            array_values($panel->getPages()),
        );

        foreach ($registered as $class) {
            $this->assertContains(
                $class,
                $placed,
                "{$class} no está ni en el menú plano, ni en «Ajustes», ni en la lista de "
                .'excepciones de AdminSettingsHub::OUTSIDE_HUB. Tal y como está, nadie puede '
                .'llegar a esa pantalla salvo tecleando su URL. Colócala en uno de los tres.',
            );
        }
    }

    /** Y al revés: una tarjeta de Ajustes que apunte a una clase que ya no se registra. */
    public function test_every_hub_card_points_to_a_registered_screen(): void
    {
        $panel = Filament::getPanel('admin');

        $registered = array_merge(
            array_values($panel->getResources()),
            array_values($panel->getPages()),
        );

        foreach (AdminSettingsHub::areas() as $area => $entries) {
            foreach ($entries as $entry) {
                // `#320`: la entrada que NO es de Filament se comprueba contra el enrutador — el
                // fallo equivalente es apuntar a una ruta que ya no existe.
                if (! isset($entry['class'])) {
                    $this->assertNotNull(
                        app('router')->getRoutes()->getByName($entry['route']),
                        "La tarjeta «{$entry['key']}» de «{$area}» apunta a la ruta «{$entry['route']}», que ya no existe.",
                    );

                    continue;
                }

                $this->assertContains(
                    $entry['class'],
                    $registered,
                    "La tarjeta «{$entry['key']}» de «{$area}» apunta a {$entry['class']}, que el panel ya no registra.",
                );
            }
        }
    }

    public function test_hub_is_out_of_the_sidebar_and_lives_in_the_avatar_menu(): void
    {
        $this->assertFalse(AdminSettingsHub::shouldRegisterNavigation());

        // La ENTRADA es tan crítica como la página: si el ítem del avatar desapareciera, las
        // 19 pantallas quedarían sin puerta y la página seguiría respondiendo 200 —o sea, la
        // suite seguiría verde con el panel roto. Se comprueba sobre el HTML de una pantalla
        // cualquiera, que es donde el usuario lo busca.
        $this->actingAs($this->userWithRole('admin'))
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee(AdminSettingsHub::getUrl(), escape: false)
            ->assertSeeText('Ajustes');

        // Y al empleado no le sale: no abriría ninguna de las 19.
        $this->actingAs($this->userWithRole('staff'))
            ->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee(AdminSettingsHub::getUrl(), escape: false);

        $this->actingAs($this->userWithRole('admin'))
            ->get(AdminSettingsHub::getUrl())
            ->assertOk()
            // Las cuatro áreas y una tarjeta de cada una.
            ->assertSeeText('Precios y productos')
            ->assertSeeText('Horarios y aforo')
            ->assertSeeText('Contenido web')
            ->assertSeeText('Sistema')
            ->assertSeeText('Plantillas de franja');
    }

    /**
     * Los colores de las tarjetas salen de los tokens del panel (`--primary-*`, `--gray-*`),
     * que NO están en la hoja compilada: los emite Filament en la respuesta. Esto se asevera
     * porque su ausencia no rompe nada visible en un test —la página responde 200 igual— y
     * ya costó una pantalla entera: en `#217` la de puerta salió en blanco y negro durante
     * días porque su layout no llamaba a `@filamentStyles` y las 84 utilidades `gray-*`
     * resolvían a nada.
     */
    public function test_hub_page_actually_receives_the_panel_color_tokens(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))
            ->get(AdminSettingsHub::getUrl())
            ->assertOk()
            ->getContent();

        foreach (['--primary-600', '--primary-500', '--gray-950', '--gray-500'] as $token) {
            $this->assertStringContainsString(
                $token.':',
                $html,
                "El panel no está declarando {$token}: las tarjetas de Ajustes saldrían sin color.",
            );
        }
    }

    /**
     * Esconder NO es autorizar. Ajustes no tiene permiso propio: pregunta a cada pantalla, así que no
     * puede abrir nada que su usuario no pudiera abrir ya por la URL directa.
     *
     * ⚠️ `#320` añadió al hub una tarjeta que NO es de Filament (la puerta) y estuvo a punto de
     * abrirle esta página al empleado: él tiene `registrations.validate`, así que la tarjeta le
     * respondía que sí y `canAccess()` pasaba a ser verdadera para alguien que no abriría ninguna de
     * las 19. No era una fuga —el enlace ya lo tenía en su menú— pero convertía «Ajustes» en una
     * página de una sola tarjeta redundante. Por eso esa tarjeta se acota al admin.
     */
    public function test_hub_grants_nothing_the_user_did_not_already_have(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff);
        $this->assertFalse(AdminSettingsHub::canAccess());

        $this->actingAs($staff)->get(AdminSettingsHub::getUrl())->assertForbidden();
        $this->actingAs($this->userWithRole('customer'))->get(AdminSettingsHub::getUrl())->assertForbidden();
    }

    /**
     * Caso aparte a propósito: `actingAs()` deja al usuario autenticado para el RESTO del
     * test, así que una petición «de invitado» escrita después de un `actingAs` no es de
     * un invitado — daba 403 en vez del redirect y parecía un fallo del código.
     */
    public function test_guest_is_redirected_to_login_instead_of_the_hub(): void
    {
        $this->get(AdminSettingsHub::getUrl())->assertRedirect('/admin/login');
    }

    public function test_hub_only_shows_cards_the_viewer_can_open(): void
    {
        $this->actingAs($this->userWithRole('admin'));

        $areas = (new AdminSettingsHub)->visibleAreas();

        $this->assertCount(4, $areas);
        $this->assertSame(
            21,
            array_sum(array_map(fn (array $a): int => count($a['items']), $areas)),
            'El admin debe ver las 21 tarjetas de Ajustes (19 + la puerta, que bajó del menú en `#320`, '.
            '+ «Opiniones propias», que entró con la sección 06 en `#490`).',
        );

        // La tarjeta «Equipo» lleva a la MISMA pantalla que «Clientes», en su otra pestaña.
        $team = collect($areas)->firstWhere('key', 'system')['items'];
        $this->assertStringContainsString(
            'tab='.ListUsers::TAB_TEAM,
            collect($team)->firstWhere('label', 'Equipo')['url'],
        );
    }

    /**
     * Las dos pestañas parten el censo en dos mitades exactas. Si alguien tocara el corte y
     * dejara de ser complementario, una cuenta podría no salir en NINGUNA de las dos y eso
     * no fallaría por sí solo — es el mismo modo de fallo que `mis-reservas-por-reserva.md`
     * §3.4 documentó para los dos ámbitos de reservas.
     */
    public function test_clients_and_team_tabs_split_every_account_exactly_once(): void
    {
        $this->userWithRole('admin');
        $this->userWithRole('staff');
        $this->userWithRole('customer');
        User::factory()->create();   // sin rol: es cliente

        $total = User::count();
        $clients = User::query()->customers()->count();
        $team = User::query()->teamMembers()->count();

        $this->assertSame($total, $clients + $team);
        $this->assertSame(2, $team);            // el admin y el staff creados aquí
        $this->assertSame($total - 2, $clients);
    }

    public function test_users_screen_defaults_to_clients_and_can_show_the_team(): void
    {
        $admin = $this->userWithRole('admin');
        $customer = $this->userWithRole('customer');

        // Por defecto (sin `?tab=`) manda el día a día: clientes.
        $this->actingAs($admin)->get(UserResource::getUrl())
            ->assertOk()
            ->assertSeeText($customer->name);

        $this->actingAs($admin)
            ->get(UserResource::getUrl('index', ['tab' => ListUsers::TAB_TEAM]))
            ->assertOk();
    }

    /**
     * El único enlace duplicado que tenía el panel. «Calendario» es un sitio (barra lateral);
     * «Crear pedido» es una acción y por eso SÍ sigue arriba.
     */
    public function test_calendar_is_not_duplicated_in_the_topbar(): void
    {
        $html = $this->actingAs($this->userWithRole('admin'))->get(Dashboard::getUrl())->getContent();

        $this->assertStringNotContainsString('fi-jj-calendar-btn', $html);
        $this->assertStringContainsString('fi-jj-create-order-btn', $html);
    }
}
