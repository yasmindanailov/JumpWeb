<?php

namespace App\Providers\Filament;

use App\Domain\Content\Services\ThemeSettings;
use App\Domain\Platform\Models\Setting;
use App\Filament\Pages\AdminSettingsHub;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Filament\Support\PanelGlobalSearchProvider;
use App\Http\Middleware\RequiresPanelRole;
use App\Http\Middleware\RestrictsPuertaRole;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel admin de Filament (Fase 7.0).
 *
 * Ver `docs/PLAN-FASE-7-PANEL.md` §1.2 y `docs/DECISIONES.md` #118.
 *
 * Aislamiento de la web pública:
 *  - Rutas auto-registradas bajo `/admin/*`. La web pública (`/`, `/entradas`,
 *    `/mi-cuenta`, etc.) no se toca.
 *  - Bundle de assets propio de Filament (`/css/filament/*`, `/js/filament/*`);
 *    `landing.css`, `site.css` y el `vite.config.js` de la web NO se modifican.
 *  - Login propio `/admin/login` separado del modal de auth de la web.
 *
 * Defense in depth en autorización:
 *  - `authMiddleware` ejecuta primero `Authenticate` (sesión) y luego
 *    `RequiresPanelRole` (rol). Ambos middleware deben pasar.
 *  - El gate canónico `User::canAccessPanel()` cierra a nivel Filament aunque
 *    se olvide el middleware en una ruta.
 */
class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Topbar: la CTA "Crear pedido" (Fase 7.3, #120) + el selector de idioma (#123), los dos
        // en `USER_MENU_BEFORE` (antes del avatar). El ORDEN de registro = orden visual.
        // Gateados por permiso/vista, sin tocar la estructura del topbar de Filament.
        //
        // ⚠️ El botón "Calendario" SE RETIRÓ aquí (#223): con el menú plano el Calendario es una
        // entrada fija de la barra lateral, y tenerlo también arriba era el único enlace
        // duplicado del panel. "Crear pedido" se queda porque NO es un sitio sino una acción:
        // es lo único del panel que se hace desde cualquier pantalla.
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('filament.admin.create-order-topbar-button'),
        );

        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('filament.admin.locale-switcher'),
        );

        // Fase 7.4: bundle del calendario (FullCalendar + componente Alpine).
        // En el head para que persista entre navegaciones SPA y el componente
        // quede registrado antes de entrar en la página del calendario. La vista
        // se autogatea por `calendar.view` (no carga en login ni a quien no accede).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): View => view('filament.admin.calendar-assets'),
        );

        // Decisión #184: listener global que abre en pestaña nueva la URL de un
        // evento `open-url-new-tab` (lo usa "Imprimir resumen del día", cuya acción
        // lleva un formulario en modal y no puede ser un <a target="_blank">).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): View => view('filament.admin.open-url-listener'),
        );
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            // Avatar LOCAL (data-URI) en vez del ui-avatars.com externo, que la CSP bloquea.
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            // Mismo icono de marca que la web pública (favicon «J» sobre naranja).
            // Lanzamiento 2026-09-01 (`#325`): el icono de pestaña del panel es el de la INSTALACIÓN
            // cuando existe (`img/client-favicon.svg`, el mismo hueco que la web); el del producto
            // solo como suelo. Antes el panel enseñaba la «J» naranja del producto en cada cliente.
            ->favicon(file_exists(public_path('img/client-favicon.svg')) ? asset('img/client-favicon.svg') : asset('favicon.svg'))
            ->brandName(fn () => (string) (Setting::value('business.name') ?: config('app.name')))
            // «Panel de Control» bajo el wordmark (#215): el brand del sidebar pasa de texto plano
            // a una vista propia (nombre + subtítulo). Filament la oculta al colapsar el sidebar.
            ->brandLogo(fn (): View => view('filament.admin.brand'))
            // Colapsar el sidebar a iconos en escritorio: feature NATIVA de Filament
            // (`HasSidebar`), apagada por defecto. No hay CSS/JS a medida. Los grupos
            // de navegación ya son plegables por defecto (no se toca eso).
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                // primary = color de marca global (white-label, #7.10): botón del topbar, item
                // activo del dropdown locale, etc. Sigue `theme.brand` (editable en el panel).
                // `ThemeSettings` es defensivo (hex validado + fallback aunque la BD no esté lista
                // durante `migrate`), así que `Color::hex` nunca recibe un valor inválido.
                'primary' => Color::hex(ThemeSettings::panelPrimaryHex()),
                // Semánticos para resultados de la página de validar registro (#126):
                // verde "registrado + waiver", naranja "sin waiver", rojo "no registrado".
                // Distintos de `primary` para que no se confundan con la marca.
                'success' => Color::Green,
                'warning' => Color::Orange,
                'danger' => Color::Red,
            ])
            // #224 — BUSCADOR del panel, en la barra superior y con ⌘K / Ctrl+K.
            //
            // Es la otra mitad de #223: al esconder 19 pantallas detrás de «Ajustes», la forma
            // rápida de llegar a cualquier sitio deja de ser mirar el menú y pasa a ser escribir.
            // El proveedor propio añade una categoría que Filament no trae —PANTALLAS—, porque
            // de serie solo encuentra registros.
            //
            // Autorización: no hay que añadir nada. Filament exige `canAccess()` del recurso
            // antes de buscar en él (`Resource::canGloballySearch()`), y las pantallas salen de
            // fuentes ya filtradas por permiso. Un empleado, por tanto, encuentra PEDIDOS y sus
            // cuatro sitios, y ni un cliente (`[DECIDIDO owner, 2026-08-28]`).
            //
            // ⚠️ El atajo es `mod+k`, NO `['command+k', 'ctrl+k']`. Con los dos por separado el
            // sufijo que se pinta junto a la caja sale de `Arr::first()`, así que en Windows y
            // Linux anunciaba «META+K» —visto en el sondeo—. `mod` es el modificador que tanto
            // Mousetrap como el propio ayudante de Filament traducen por plataforma: ⌘K en Mac,
            // CTRL+K en el resto, y con UNA sola declaración.
            ->globalSearch(PanelGlobalSearchProvider::class)
            ->globalSearchKeyBindings(['mod+k'])
            ->globalSearchFieldKeyBindingSuffix()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // #223 — MENÚ PLANO. Antes había 5 grupos plegables con 24 entradas desplegadas a la
            // vez; medido contra `PANEL-ADMIN.md`, solo 4 de esas 24 eran del día a día (§2) y
            // 19 eran puesta en marcha (§3/§4). `[DECIDIDO owner, 2026-08-28]`: la barra lateral
            // se queda SOLO con los sitios del día a día, sin grupos y sin plegables —un grupo
            // plegable sigue ocupando sitio y sigue obligando a decidir dónde mirar—, y las 19
            // salen del camino a `AdminSettingsHub`, al que se entra por el menú del avatar.
            //
            // Ya no se declara `navigationGroups()`: cada recurso/página devuelve `null` en
            // `getNavigationGroup()` y su sitio lo fija `$navigationSort` (10 en 10).
            // Widgets del dashboard (7.4 iter2): se auto-descubren de
            // `app/Filament/Widgets` (DashboardStatsWidget + ReservationsWidget).
            // La card de saludo `AccountWidget` se retiró por decisión de la
            // clienta — el escritorio es puramente operativo (#179).
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationItems([
                // Fase 7.1a: atajo de «Puerta» para quien entra al panel sin recordar la URL directa.
                // La página dedicada vive fuera del shell de Filament (decisión #119).
                //
                // `#320` (`[DECIDIDO owner]`) — **al ADMIN ya no le sale**: «al rol de administrador
                // no se le muestra en el menú "puerta", es innecesario, él puede ver todos los
                // detalles de cualquier cliente directamente con el buscador». Sigue saliéndole al
                // empleado de mostrador, que sí atiende ahí. Y el rol `puerta` no necesita el enlace:
                // aterriza en esa pantalla y no navega el panel.
                //
                // ⚠️ Retirarlo del menú NO bastaba, y por eso además baja a `AdminSettingsHub`: el
                // buscador global saca sus pantallas de la propia navegación
                // (`PanelGlobalSearchProvider::screens()`), así que quitarlo de aquí lo quitaba
                // TAMBIÉN del buscador —justo la vía que el owner da por buena— y dejaba la puerta
                // alcanzable solo tecleando la URL. Sin que nada se pusiera rojo, encima: la guarda
                // de pantallas huérfanas solo mira las registradas en Filament, y ésta no lo está.
                NavigationItem::make('puerta-validar')
                    ->label(fn () => __('admin.puerta.nav_label'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn () => route('admin.puerta.validar'))
                    ->openUrlInNewTab(false)
                    ->visible(fn (): bool => ($u = auth()->user()) !== null
                        && ! $u->hasRole('admin')
                        && $u->hasPermission('registrations.validate'))
                    ->sort(50),
            ])
            // #223 — la puerta a las 19 pantallas de puesta en marcha: DENTRO del menú del
            // avatar (`[DECIDIDO owner]`), no en la barra lateral ni como icono suelto del
            // topbar. `visible()` delega en `AdminSettingsHub::canAccess()`, que a su vez
            // pregunta a cada pantalla: a un empleado no le aparece, porque no abriría nada.
            ->userMenuItems([
                'ajustes' => MenuItem::make()
                    ->label(fn (): string => __('admin.hub.nav_label'))
                    ->icon(Heroicon::OutlinedCog6Tooth)
                    ->url(fn (): string => AdminSettingsHub::getUrl())
                    ->visible(fn (): bool => AdminSettingsHub::canAccess())
                    ->sort(-1),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                // Cabeceras de seguridad TAMBIÉN en el panel (auditoría Fase 1, A6): el panel define su
                // propio stack y NO hereda el grupo `web`, así que sin esto `/admin` y `/admin/login`
                // —la superficie que gestiona PII, reembolsos y roles— iban sin X-Frame-Options/nosniff/
                // Referrer-Policy/Permissions-Policy/CSP. Es base (regla 9), no el endurecimiento de Fase 9.
                SecurityHeaders::class,
                // Locale del panel aislado del de la web pública (decisión #123).
                // Va en el stack general (no en `authMiddleware`) para aplicar
                // también a `/admin/login` y a usuarios sin `panel_locale` aún
                // (fallback al default `es` vía `SetAdminLocale::resolve(null)`).
                SetAdminLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                RequiresPanelRole::class,
                // `#320`: el rol `puerta` no navega el panel — cualquier ruta suya lo devuelve a su
                // pantalla. Va en `authMiddleware` (no en el stack general) porque `/admin/login`
                // tiene que seguir abierto: es por donde entra. La puerta es una ruta `web` fuera del
                // panel, así que la redirección no puede realimentarse.
                RestrictsPuertaRole::class,
            ]);
    }
}
