<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Support\InitialsAvatarProvider;
use App\Http\Middleware\RequiresStaffOrAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetAdminLocale;
use App\Support\ThemeSettings;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
 *    `RequiresStaffOrAdmin` (rol). Ambos middleware deben pasar.
 *  - El gate canónico `User::canAccessPanel()` cierra a nivel Filament aunque
 *    se olvide el middleware en una ruta.
 */
class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        // Topbar: accesos rápidos "Calendario" + "Crear pedido" (Fase 7.3, #120) + selector de
        // idioma (#123), todos en `USER_MENU_BEFORE` (antes del avatar). El ORDEN de registro =
        // orden visual: primero "Calendario", luego "Crear pedido", luego el selector de idioma →
        // quedan a la IZQUIERDA del avatar en ese orden. Gateados por permiso/vista, sin tocar la
        // estructura del topbar de Filament.
        FilamentView::registerRenderHook(
            PanelsRenderHook::USER_MENU_BEFORE,
            fn (): View => view('filament.admin.calendar-topbar-button'),
        );

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
            ->favicon(asset('favicon.svg'))
            ->brandName('Jumpingjump')
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
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            // Plan B · L1: navegación reorganizada en 5 grupos para el empleado no técnico
            // (el cluster monolítico «Configuración» se retiró). Labels como CLOSURE → se
            // resuelven POR PETICIÓN, así casan con `getNavigationGroup()` de cada recurso en
            // el idioma del panel (es / zh_CN, #123). Orden = el del array; lo delicado al final.
            ->navigationGroups([
                NavigationGroup::make(fn (): string => __('admin.nav_groups.operativa'))
                    ->icon(Heroicon::OutlinedBriefcase)->collapsible(),
                NavigationGroup::make(fn (): string => __('admin.nav_groups.programacion'))
                    ->icon(Heroicon::OutlinedCalendarDays)->collapsible(),
                NavigationGroup::make(fn (): string => __('admin.nav_groups.catalogo'))
                    ->icon(Heroicon::OutlinedBanknotes)->collapsible(),
                NavigationGroup::make(fn (): string => __('admin.nav_groups.contenido'))
                    ->icon(Heroicon::OutlinedGlobeAlt)->collapsible(),
                NavigationGroup::make(fn (): string => __('admin.nav_groups.sistema'))
                    ->icon(Heroicon::OutlinedCog6Tooth)->collapsible(),
            ])
            // Widgets del dashboard (7.4 iter2): se auto-descubren de
            // `app/Filament/Widgets` (DashboardStatsWidget + ReservationsWidget).
            // La card de saludo `AccountWidget` se retiró por decisión de la
            // clienta — el escritorio es puramente operativo (#179).
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->navigationItems([
                // Fase 7.1a: atajo de "Validar registro" en la sidebar del panel rico
                // para el admin/staff que entra al panel sin recordar la URL directa.
                // La página dedicada vive fuera del shell Filament (decisión #119).
                NavigationItem::make('puerta-validar')
                    // Plan B · L1: el grupo de 1 ítem «Puerta» se fusiona en «Operativa».
                    ->group(fn () => __('admin.nav_groups.operativa'))
                    ->label(fn () => __('admin.puerta.validar.title'))
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn () => route('admin.puerta.validar'))
                    ->openUrlInNewTab(false)
                    ->visible(fn () => auth()->user()?->hasPermission('registrations.validate') ?? false)
                    ->sort(30),
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
                RequiresStaffOrAdmin::class,
            ]);
    }
}
