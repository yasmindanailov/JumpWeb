<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Attractions\AttractionResource;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\BarImages\BarImageResource;
use App\Filament\Resources\Catalog\CatalogResource;
use App\Filament\Resources\Experiments\ExperimentResource;
use App\Filament\Resources\Faqs\FaqResource;
use App\Filament\Resources\LandingServices\LandingServiceResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\ParkRules\ParkRuleResource;
use App\Filament\Resources\RateTypes\RateTypeResource;
use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Seasons\SeasonResource;
use App\Filament\Resources\Slots\SlotResource;
use App\Filament\Resources\SlotTemplates\SlotTemplateResource;
use App\Filament\Resources\SpecialDates\SpecialDateResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Zones\ZoneResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;

/**
 * «Ajustes» — la ÚNICA puerta a las pantallas de puesta en marcha (#223).
 *
 * ## Por qué existe
 * Medido el 2026-08-28: el menú del panel tenía **24 entradas en 6 grupos, todas
 * desplegadas**, y solo 4 eran del día a día (`PANEL-ADMIN.md` §2). El 79 % restante
 * es configuración que se toca una vez y no se vuelve a mirar. `[DECIDIDO owner]`: el
 * menú pasa a ser **plano y solo con los sitios del día a día**, y todo lo demás sale
 * del camino — se entra por el **menú del avatar**, no por la barra lateral.
 *
 * ## La invariante que sostiene esto
 * Esconder una pantalla del menú NO la protege (eso lo hace `canAccess()`/`canViewAny()`,
 * que ya existían y se verificaron una por una): la esconde. El riesgo real de esconder
 * es **dejar una pantalla huérfana** — fuera del menú y fuera de aquí — o sea inalcanzable
 * salvo tecleando la URL. Por eso:
 *
 *  - `AREAS` es la fuente ÚNICA de qué hay en esta página, y
 *  - `AdminNavigationTest` exige que **toda** página/recurso registrado en el panel esté
 *    en el menú plano, o en `AREAS`, o en la lista explícita de excepciones. Añadir un
 *    recurso nuevo sin colocarlo pone la suite en rojo.
 *
 * ## Autorización
 * No hay permiso propio: cada tarjeta pregunta a SU pantalla (`canAccess()`), que es la
 * misma puerta que protege la ruta. La página entera solo es accesible si al menos una
 * tarjeta lo es, así que a un empleado —cuyos 12 permisos no abren ninguna— no le sale
 * ni la entrada en el menú del avatar (`SEC`: sin superficie muerta que invite a probar).
 */
class AdminSettingsHub extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $slug = 'ajustes';

    protected string $view = 'filament.pages.admin-settings-hub';

    /**
     * Pantallas que están FUERA del menú plano a propósito y que NO viven aquí, con el
     * porqué. Lista cerrada: la guarda de navegación la usa como única excepción.
     *
     * @var array<class-string, string>
     */
    public const OUTSIDE_HUB = [
        // Es una ACCIÓN, no un sitio: vive como CTA de la barra superior (#120).
        CreateManualOrderPage::class => 'topbar',
        // Esta misma página.
        self::class => 'self',
    ];

    /**
     * Las áreas de la página y su contenido, en orden de aparición.
     *
     * Cada entrada declara:
     *  - `key`   → sufijo de los rótulos (`admin.hub.items.<key>.{label,description}`),
     *  - `class` → la pantalla, que es también quien decide si se ve (`canAccess()`),
     *  - `page`  → (opcional, solo recursos) página del recurso a la que apunta,
     *  - `query` → (opcional) parámetros de la URL, p. ej. la pestaña activa.
     *
     * ▶ `#320` — y una entrada puede NO ser de Filament. La puerta es una página Livewire que vive
     * fuera del shell (`#119`), así que no tiene `canAccess()` ni `getUrl()` que preguntar: se
     * declara con `route` + `permission` + `icon`. Hizo falta porque el ítem «Puerta» salió del menú
     * lateral y **el buscador global saca sus pantallas de la navegación** ({@see
     * \App\Filament\Support\PanelGlobalSearchProvider}): sin colocarla aquí, retirarla del menú la
     * dejaba alcanzable solo tecleando la URL, y sin que ninguna guarda lo notara —la de pantallas
     * huérfanas solo mira las REGISTRADAS en Filament, y ésta no lo está—.
     *
     * @return array<string, array<int, array{key: string, class?: class-string, page?: string, query?: array<string, string>, route?: string, permission?: string, icon?: string|BackedEnum}>>
     */
    public static function areas(): array
    {
        return [
            'sales' => [
                ['key' => 'catalog', 'class' => CatalogResource::class],
                ['key' => 'rate_types', 'class' => RateTypeResource::class],
                ['key' => 'zones', 'class' => ZoneResource::class],
            ],
            'schedule' => [
                ['key' => 'weekly_schedule', 'class' => WeeklySchedule::class],
                ['key' => 'seasons', 'class' => SeasonResource::class],
                ['key' => 'special_dates', 'class' => SpecialDateResource::class],
                ['key' => 'slots', 'class' => SlotResource::class],
                ['key' => 'slot_templates', 'class' => SlotTemplateResource::class],
            ],
            'web' => [
                ['key' => 'attractions', 'class' => AttractionResource::class],
                ['key' => 'landing_services', 'class' => LandingServiceResource::class],
                ['key' => 'faqs', 'class' => FaqResource::class],
                ['key' => 'testimonials', 'class' => TestimonialResource::class],
                // ⚠️ Aquí estaba «Ofertas» (`#270`) y se retira con su recurso en `#668` (F5 · T3),
                // cumpliendo `#631`: «oferta» es un hecho de PRECIO —el «antes» tachado del
                // catálogo— y no un CMS de imágenes en un icono flotante.
                // `#536`: las imágenes de `/bar` — las caras de la CARTA y la foto del local. Van
                // aquí, junto al resto del CMS, y no en Ajustes: la página de ajustes no sube
                // ficheros (cero `FileUpload` en ella) y esto es una colección ordenable.
                ['key' => 'bar_images', 'class' => BarImageResource::class],
                ['key' => 'park_rules', 'class' => ParkRuleResource::class],
                ['key' => 'pages', 'class' => PageResource::class],
                // La ficha de Google (`#524`, `specs/google-business-profile.md` §4.2·1). Va en
                // «Contenido web» y no en «Sistema» porque de ella sale lo que se PUBLICA en la
                // portada —las reseñas—, aunque por dentro sea una conexión.
                ['key' => 'google_business', 'class' => GoogleBusinessProfilePage::class],
            ],
            'system' => [
                ['key' => 'settings', 'class' => Settings::class],
                // Los experimentos (`specs/analitica.md` §4.4, T5b): alta, encendido y cierre de las pruebas A/B.
                // Van en «Sistema» con `settings.manage`: es configuración del producto; los resultados se miran en
                // «Analítica → Conversión».
                ['key' => 'experiments', 'class' => ExperimentResource::class],
                // `#320`: la PUERTA, y esta tarjeta es el ESPEJO EXACTO del ítem de menú. Al admin se
                // le retiró del menú (`[DECIDIDO owner]`: no atiende por ahí, atiende por el buscador)
                // y aparece aquí; a quien sí lo tiene en el menú —el empleado— no se le repite, o
                // «Ajustes» pasaría a enseñarle una sola tarjeta con un enlace que ya tiene, y esta
                // página dejaría de significar «puesta en marcha». Entre las dos reglas, todo el que
                // tenga el permiso llega por exactamente UN camino.
                [
                    'key' => 'puerta',
                    'route' => 'admin.puerta.validar',
                    'permission' => 'registrations.validate',
                    'roles' => ['admin'],
                    'icon' => Heroicon::OutlinedShieldCheck,
                ],
                // Misma pantalla que «Clientes» del menú, otra pestaña: el equipo se
                // gestiona aquí (se hace una vez), los clientes en el día a día.
                ['key' => 'team', 'class' => UserResource::class, 'query' => ['tab' => ListUsers::TAB_TEAM]],
                ['key' => 'roles', 'class' => RoleResource::class],
                ['key' => 'audit', 'class' => AuditLogResource::class],
                ['key' => 'maintenance', 'class' => Maintenance::class],
            ],
        ];
    }

    /**
     * Las áreas ya resueltas para la vista: solo lo que este usuario puede abrir, y las
     * áreas que se quedan sin tarjetas no se pintan.
     *
     * @return array<int, array{key: string, label: string, items: array<int, array{label: string, description: string, url: string, icon: string|BackedEnum|null}>}>
     */
    public function visibleAreas(): array
    {
        $areas = [];

        foreach (static::areas() as $area => $entries) {
            $items = [];

            foreach ($entries as $entry) {
                if (! static::entryIsVisible($entry)) {
                    continue;
                }

                $items[] = [
                    'label' => __('admin.hub.items.'.$entry['key'].'.label'),
                    'description' => __('admin.hub.items.'.$entry['key'].'.description'),
                    'url' => static::urlFor($entry),
                    'icon' => static::iconFor($entry),
                ];
            }

            if ($items === []) {
                continue;
            }

            $areas[] = [
                'key' => $area,
                'label' => __('admin.hub.areas.'.$area),
                'items' => $items,
            ];
        }

        return $areas;
    }

    /**
     * ¿Este usuario puede abrir la entrada? La autoridad sigue siendo de la pantalla: una de
     * Filament responde por su `canAccess()`; una de fuera (`route`) por su permiso declarado, que es
     * el MISMO que exige su ruta — si divergieran, esta página ofrecería un enlace a un 403.
     *
     * `roles` acota además a una lista de roles, para la tarjeta que solo existe porque a ESE rol le
     * falta el enlace en otro sitio. Es un filtro que solo puede QUITAR: nunca concede nada que el
     * permiso no diera ya.
     *
     * @param  array{class?: class-string, permission?: string, roles?: array<int, string>}  $entry
     */
    private static function entryIsVisible(array $entry): bool
    {
        if (isset($entry['class'])) {
            return $entry['class']::canAccess();
        }

        $user = auth()->user();
        if ($user === null || ! $user->hasPermission($entry['permission'])) {
            return false;
        }

        foreach ($entry['roles'] ?? [] as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return ! isset($entry['roles']);
    }

    /** @param array{class?: class-string, icon?: string|BackedEnum} $entry */
    private static function iconFor(array $entry): string|BackedEnum|null
    {
        return isset($entry['class']) ? $entry['class']::getNavigationIcon() : ($entry['icon'] ?? null);
    }

    /**
     * URL de una entrada. `Resource::getUrl()` recibe el nombre de la página como primer
     * argumento y `Page::getUrl()` recibe directamente los parámetros: son firmas
     * distintas, y confundirlas pasa el análisis estático pero rompe en ejecución.
     *
     * @param  array{key: string, class?: class-string, page?: string, query?: array<string, string>, route?: string}  $entry
     */
    private static function urlFor(array $entry): string
    {
        if (! isset($entry['class'])) {
            return route($entry['route']);
        }

        /** @var class-string $class */
        $class = $entry['class'];
        $query = $entry['query'] ?? [];

        if (is_subclass_of($class, Resource::class)) {
            return $class::getUrl($entry['page'] ?? 'index', $query);
        }

        return $class::getUrl($query);
    }

    /** Fuera del menú lateral: se entra por el menú del avatar (`AdminPanelProvider`). */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * Accesible si alguna tarjeta lo es. Sin permiso propio: la autoridad sigue siendo
     * la de cada pantalla, así que esta página no puede conceder nada que no se tenga ya.
     */
    public static function canAccess(): bool
    {
        foreach (static::areas() as $entries) {
            foreach ($entries as $entry) {
                if (static::entryIsVisible($entry)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getTitle(): string
    {
        return __('admin.hub.title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.hub.subheading');
    }
}
