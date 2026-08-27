<?php

namespace App\Providers;

use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\ProductAddon;
use App\Domain\Booking\Models\RateType;
use App\Domain\Booking\Models\Room;
use App\Domain\Booking\Models\Season;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\SlotTemplate;
use App\Domain\Booking\Models\SpecialDate;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\LandingService;
use App\Domain\Content\Models\Offer;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Content\Services\MapsEmbed;
use App\Domain\Content\Services\SocialEmbed;
use App\Domain\Identity\Listeners\SignPendingWaiverOnVerification;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\Setting;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** Clave de memoización del payload del composer en `request()->attributes` (Sistema 6 · W1). */
    private const SHARED_VIEW_DATA_KEY = 'app.shared_view_data';

    public function register(): void
    {
        // Contexto de cuenta del cliente (#221): singleton para memoizar por petición — el nav
        // (puntito de aviso) y el sidebar lo piden por separado y comparten una única consulta.
        $this->app->singleton(CustomerAccountContext::class);

        // Las factories siguen VIVIENDO PLANAS en `database/factories/`, aunque los modelos se
        // repartan por módulos (Fase 2). Por defecto Laravel adivina el nombre a partir del
        // namespace COMPLETO, así que `App\Domain\Identity\Models\User` buscaría
        // `Database\Factories\Domain\Identity\Models\UserFactory` y reventaría media suite.
        // Resolvemos por NOMBRE CORTO del modelo: espejar el árbol de módulos dentro de
        // `database/factories/` no aporta nada y multiplicaría el churn de cada mudanza.
        // Lo cubre `FactoryResolutionTest`.
        Factory::guessFactoryNamesUsing(static function (string $model): string {
            $name = str_contains($model, '\\Models\\')
                ? Str::afterLast($model, '\\Models\\')
                : Str::after($model, 'App\\');

            return 'Database\\Factories\\'.$name.'Factory';
        });
    }

    /**
     * Comparte los datos del negocio (settings) con todas las vistas
     * como $site. Guardado por si la tabla aún no existe (migraciones/CI).
     */
    public function boot(): void
    {
        // Fase 6 · waiver (`#179`): la aceptación pendiente del alta se firma al VERIFICAR el correo. El
        // listener vive en Identity (el arch-test no deja dominio fuera de `app/Domain`) y se registra aquí.
        Event::listen(Verified::class, SignPendingWaiverOnVerification::class);

        // morphMap FORZADO (Fase 2, prerequisito de la modularización — DEUDA §Alta): las columnas
        // polimórficas (`payments.payable_type`, `prices.priceable_type`, `audit_logs.target_type`)
        // guardan ALIAS estables, no FQCN → renombrar/mover un modelo ya no rompe datos. `enforce`
        // hace que morfar un modelo SIN alias lance (ningún FQCN nuevo puede colarse en BD).
        // Los datos pre-existentes con FQCN los convirtió la migración 2026_08_12 (convert_morph_
        // types_to_aliases); el fallback de lectura de Laravel resuelve FQCN legacy igualmente.
        // ⚠️ Modelo NUEVO ⇒ añadir aquí su alias (lo exige `MorphMapTest`).
        Relation::enforceMorphMap([
            'attraction' => Attraction::class,
            'audit_log' => AuditLog::class,
            'consent' => Consent::class,
            'cookie_consent_log' => CookieConsentLog::class,
            'dependent' => Dependent::class,
            'faq' => Faq::class,
            'landing_service' => LandingService::class,
            'legal_document_version' => LegalDocumentVersion::class,
            'offer' => Offer::class,
            'opening_hour' => OpeningHour::class,
            'order' => Order::class,
            'order_adjustment' => OrderAdjustment::class,
            'order_item' => OrderItem::class,
            'page' => Page::class,
            'park_rule' => VenueRule::class,
            'payment' => Payment::class,
            'payment_refund' => PaymentRefund::class,
            'permission' => Permission::class,
            'price' => Price::class,
            'product_addon' => ProductAddon::class,
            'rate_type' => RateType::class,
            'role' => Role::class,
            'room' => Room::class,
            'season' => Season::class,
            'setting' => Setting::class,
            'slot' => Slot::class,
            'slot_template' => SlotTemplate::class,
            'special_date' => SpecialDate::class,
            'ticket' => Ticket::class,
            'ticket_type' => TicketType::class,
            'user' => User::class,
            'waiver_signature' => WaiverSignature::class,
            'zone' => Zone::class,
        ]);

        // Guard estricto de asignación masiva (recomendación B, 2026-06-15): fuera de producción,
        // cualquier `create()/update()/fill()` con una clave fuera del `$fillable` del modelo LANZA
        // en vez de descartarla en silencio. Caza allowlists incompletos en la suite (el mayor
        // riesgo de añadir `$fillable`) y futuras regresiones. En producción NO se activa: ante un
        // dato inesperado preferimos descartar a romper el flujo de pago.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Producción (Fase 4): generar SIEMPRE URLs https. Belt-and-suspenders sobre `trustProxies`
        // (que ya hace que Laravel detecte el esquema tras el proxy de Enhance): asegura que enlaces,
        // assets, callbacks de Redsys y enlaces de email salgan en https aunque alguna petición
        // interna no traiga las cabeceras `X-Forwarded-*`. Solo en producción → local/tests (http) intactos.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // `admin` = super-admin: pasa cualquier Gate. Para el resto se evalúan
        // los Gates/Policies normales (devolver null deja seguir la cadena).
        Gate::before(fn (User $user, string $ability) => $user->hasRole('admin') ? true : null);

        // El composer global corre una vez por CADA vista/subvista renderizada (la home monta ~79).
        // Memoizamos el payload en los atributos de la PETICIÓN (ámbito natural por request, a prueba
        // de fugas entre peticiones/tests) para no reconstruir `$site` ni reconsultar `settings` en
        // cada subvista: sin esto, un GET anónimo de la home dispara ~1.900 queries y ~4 s de BD
        // (auditoría Fase 1 · Sistema 6 · W1). Con la memoización, una sola lectura de `settings`/request.
        View::composer('*', function ($view): void {
            foreach ($this->sharedViewData() as $key => $value) {
                $view->with($key, $value);
            }
        });
    }

    /**
     * Datos compartidos con todas las vistas (`$site`, consentimiento de cookies, ancla de precio del
     * CTA), calculados UNA vez por petición y memoizados en `request()->attributes` (auditoría Fase 1 ·
     * Sistema 6 · W1: evita la amplificación del composer global a ~1.900 queries por GET de la home).
     *
     * @return array<string,mixed>
     */
    private function sharedViewData(): array
    {
        $request = request();
        $cached = $request->attributes->get(self::SHARED_VIEW_DATA_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        $data = $this->buildSharedViewData();
        $request->attributes->set(self::SHARED_VIEW_DATA_KEY, $data);

        return $data;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSharedViewData(): array
    {
        // Consentimiento de cookies (#219): gobierna el bloqueo previo de los iframes de tercero (mapa
        // de Google, feed social) y la visibilidad del banner. `state()` no necesita BD (lee la cookie);
        // se calcula también en la rama sin tabla `settings` (CI / instalación limpia).
        // Servicios del selector «Servicios» del nav (#256): data-driven (LandingService con
        // show_in_nav), memoizado con el resto del payload (1 query/petición, no por subvista).
        $data = [
            'cookieConsent' => CookieConsent::state(request()),
            'navServices' => $this->navServices(),
            // Ofertas activas para el widget «caja de regalo» (#270): site-wide, memoizado con el
            // resto del payload (1 query/petición). El widget solo se pinta si hay alguna.
            'offers' => $this->activeOffers(),
        ];

        if (! $this->tableExists('settings')) {
            return $data + [
                'site' => [],
                'ctaMinPriceCents' => null,
                'ctaMinPriceLabel' => null,
                'cookieBannerEnabled' => false,
            ];
        }

        // Una sola lectura de toda la tabla `settings` por petición (en vez de ~20 `Setting::value`,
        // cada uno 1 query, × cada vista) — auditoría Fase 1 · Sistema 6 · W1.
        $settings = Setting::query()->pluck('value', 'key');
        $get = fn (string $key, mixed $default = null): mixed => $settings[$key] ?? $default;

        // Textos de la landing editables POR IDIOMA (#215): si el ajuste del idioma actual está vacío,
        // cae al texto traducido por defecto (`lang/landing`). `seo_title` es null si no se ha fijado.
        $locale = app()->getLocale();

        $cents = $this->ctaMinPriceCents();
        // Teléfono marcable (solo dígitos/+) → queda VACÍO si el contacto es '[PENDIENTE]' o '' ⇒
        // `has_phone` apaga los CTA «Llamar» (Lote 11): nunca renderizar un `tel:` roto a un
        // placeholder, y unifica el saneo (antes cada plantilla usaba un patrón distinto).
        $phoneTel = preg_replace('/[^0-9+]/', '', (string) $get('contact.phone', ''));

        return $data + [
            'cookieBannerEnabled' => CookieConsent::bannerEnabled(),
            'site' => [
                'name' => $get('business.name', config('app.name')),
                'city' => $get('business.city', ''),
                'email' => $get('contact.email', ''),
                'phone' => $get('contact.phone', ''),
                'phone_tel' => $phoneTel,
                'has_phone' => $phoneTel !== '',
                'address1' => $get('address.line1', ''),
                'address2' => $get('address.line2', ''),
                'maps' => self::safeExternalUrl($get('address.maps_url')) ?? '#',
                'maps_embed' => MapsEmbed::clean($get('address.maps_embed_url')),
                // Defensa de RENDER uniforme (auditoría Fase 1, Sistema 5): igual que `maps` y
                // `registration_url`, las URLs de redes pasan por `safeExternalUrl` → el `href` no
                // puede ser otro esquema (`javascript:`/`data:`) aunque un valor se inyectara por BD
                // directa. El form del panel ya valida con `->url()`, pero el render no debe depender
                // SOLO de esa validación (paridad con las otras URLs externas del footer).
                'instagram' => self::safeExternalUrl($get('contact.instagram')) ?? '#',
                'tiktok' => self::safeExternalUrl($get('contact.tiktok')) ?? '#',
                'whatsapp' => preg_replace('/\D/', '', (string) $get('contact.whatsapp', '')),
                'social_feed' => SocialEmbed::clean($get('social.feed_embed_url')),
                // og_image pasa por `safeExternalUrl` (auditoría Fase 1 · Sistema 6 · W8): paridad de
                // defensa-en-profundidad con maps/instagram/tiktok/registration — solo http(s) en el
                // `<meta og:image>`; null → la meta se omite en el layout.
                'og_image' => self::safeExternalUrl($get('seo.og_image')),
                'tagline' => $get('landing.tagline.'.$locale) ?: __('landing.footer.tag'),
                'footer_rights' => $get('landing.footer_rights.'.$locale) ?: __('landing.footer.rights'),
                'seo_title' => $get('seo.title.'.$locale) ?: null,
                // Registro «del parque» (#216): URL del sistema externo + etiqueta/subtítulo por idioma
                // (fallback a los textos i18n del nav). URL vacía → el CTA cae al modal de registro interno.
                // Defensa: solo se acepta una URL http(s) (el form ya valida, pero esto blinda el
                // RENDER ante un valor inyectado por BD directa — el `href` no puede ser otro esquema).
                'registration_url' => self::safeExternalUrl($get('registration.url')),
                'registration_label' => $get('registration.label.'.$locale) ?: __('landing.nav.register'),
                // Subtítulo SIN fallback (#retoque): si el campo está vacío no se muestra nada (la
                // etiqueta sí tiene fallback porque el botón necesita texto; el subtítulo es opcional).
                'registration_subtitle' => $get('registration.subtitle.'.$locale) ?: null,
            ],
            'ctaMinPriceCents' => $cents,
            'ctaMinPriceLabel' => $cents !== null ? self::formatPriceLabel($cents) : null,
        ];
    }

    /**
     * Servicios visibles en el selector «Servicios» del nav (#256, modelo A). Data-driven:
     * `LandingService` con `show_in_nav`, ordenados. Solo las columnas que el nav necesita
     * (título + subtítulo + slug del anchor). Guardado por `hasTable` para CI/instalación limpia.
     *
     * @return Collection<int, LandingService>
     */
    private function navServices(): Collection
    {
        if (! $this->tableExists('landing_services')) {
            return collect();
        }

        // active() ADEMÁS de inNav(): un servicio oculto de /servicios (is_active=false) NO debe
        // salir en el menú (enlazaría a /servicios#slug a una sección que no se pinta = anchor roto).
        return LandingService::active()->inNav()->ordered()->get(['id', 'slug', 'title', 'nav_subtitle']);
    }

    /**
     * Ofertas activas para el widget flotante «caja de regalo» (#270, `docs/PLAN-OFERTAS-WIDGET.md`).
     * Data-driven, memoizado con el resto del payload (1 query/petición, no por subvista). Solo las
     * columnas que el widget necesita (id + título + imagen). Guardado por `tableExists` para
     * CI/instalación limpia. SIN Cache TTL: las ediciones del panel se reflejan al instante (igual
     * que `navServices`, a diferencia de `ctaMinPriceCents`).
     *
     * @return Collection<int, Offer>
     */
    private function activeOffers(): Collection
    {
        if (! $this->tableExists('offers')) {
            return collect();
        }

        // Resiliencia de DEPLOY (zero-downtime): en producción `tableExists` corta en corto (no
        // consulta information_schema), pero entre el rsync del código nuevo y `php artisan migrate`
        // la tabla `offers` aún no existe → la consulta lanzaría un 500 en TODAS las páginas (el
        // composer corre en cada vista). `rescue` devuelve una colección vacía (el widget no se pinta
        // esos segundos) en lugar de romper. En estado normal (tabla creada) no hay excepción ni coste.
        return rescue(
            fn (): Collection => Offer::active()->ordered()->get(['id', 'title', 'image']),
            collect(),
            false,
        );
    }

    /**
     * Precio mínimo de entrada (céntimos) para el anclaje "desde X €" de los CTAs
     * principales (nav + hero, mockup `design_mockup/jerarquia-ctas.html`). Considera
     * solo productos vendibles, activos y de tipo `entry` — packs y addons no entran
     * en "comprar entradas". Cacheado 15 min para no consultar BD en cada request.
     * Null si aún no hay catálogo (tabla nueva o entorno limpio) → la vista omite el subtítulo.
     */
    private function ctaMinPriceCents(): ?int
    {
        if (! $this->tableExists('ticket_types') || ! $this->tableExists('prices')) {
            return null;
        }

        return Cache::remember('cta.min_price_cents', now()->addMinutes(15), function (): ?int {
            $min = TicketType::query()
                ->where('is_sellable', true)
                ->where('is_active', true)
                ->where('type', TicketType::TYPE_ENTRY)
                // Misma comprabilidad que el catálogo de compra (Purchase/OrderCreator): NO anclar el
                // «desde X €» a una entrada de zona desactivada que el flujo no puede vender (Sistema 6 · W2).
                ->inOperationalZone()
                ->with('prices')
                ->get()
                ->flatMap(fn (TicketType $type) => $type->prices)
                ->where('amount_cents', '>', 0)
                ->min('amount_cents');

            return $min !== null ? (int) $min : null;
        });
    }

    /**
     * ¿Existe la tabla? En producción se ASUME que sí (las migraciones corren en cada deploy):
     * evitamos una consulta a `information_schema` por request y por tabla en el hot path del
     * composer global (4 lecturas/petición: settings, landing_services, ticket_types, prices).
     * Fuera de producción (CI / instalación limpia / durante `migrate`) sí se comprueba de verdad.
     */
    private function tableExists(string $table): bool
    {
        return $this->app->isProduction() || Schema::hasTable($table);
    }

    /**
     * Formato consistente de precio para los CTAs (nav + hero): coma decimal y `€` final,
     * convención europea. Se usa en los 3 idiomas (mercado primario español; coherente con
     * `<x-site.price-card>`). Si en el futuro hay que diferenciar por locale, pasa al composer.
     */
    public static function formatPriceLabel(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    /**
     * Devuelve la URL solo si es http(s) (defensa de render para enlaces externos editables: el
     * formulario ya valida, pero un valor inyectado por BD directa no debe poder colar un esquema
     * peligroso —`javascript:`, `data:`— en un `href`). Vacío/otro esquema → null.
     */
    public static function safeExternalUrl(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return ($value !== '' && preg_match('#^https?://#i', $value) === 1) ? $value : null;
    }
}
