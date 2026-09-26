<?php

namespace App\Filament\Pages;

use App\Domain\Booking\Services\AvailabilitySettings;
use App\Domain\Booking\Services\CatalogSettings;
use App\Domain\Booking\Services\GuestCountPolicy;
use App\Domain\Content\Services\MapsEmbed;
use App\Domain\Content\Services\ShellSettings;
use App\Domain\Content\Services\SocialEmbed;
use App\Domain\Identity\Services\DependentSettings;
use App\Domain\Identity\Services\PuertaSettings;
use App\Domain\Identity\Services\WaiverSettings;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Payments\Services\Redsys;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\Drivers;
use App\Domain\Platform\Services\Analytics\Pixels;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\Surveys\SurveySettings;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Fase 7.10 (iter. 1) — Configuración del negocio desde el panel.
 *
 * Edita los ajustes data-driven de la tabla `settings` (clave-valor), agrupados (Fase 3 · Plan B ·
 * L2) en **4 pestañas** de nivel superior — Tu negocio · Textos y aspecto web · Datos fiscales ·
 * Avanzado — para el empleado NO técnico (lo cotidiano primero, lo técnico colapsado al final).
 * Cumple el principio del proyecto: lo que se sembró en `LandingContentSeeder` se vuelve editable
 * sin tocar código. Las pestañas/secciones son puro layout: NO alteran el `statePath` de los
 * campos → un solo Guardar persiste todas las claves (atómico) y la validación cruzada Redsys-live
 * de `save()` se conserva.
 *
 * **Acceso solo admin** (`settings.manage`).
 *
 * **Fuera de alcance por seguridad (NUNCA editables aquí):** los SECRETOS
 * (`redsys_secret_key`, `security.turnstile_secret`) viven en el vault/`.env` (Fase 9), y el
 * **contador operativo** `redsys_next_gateway_order` lo gestiona el flujo de pago (editarlo
 * a mano colisionaría los pedidos). El setting muerto `sales.manual_hold_minutes` (#177)
 * tampoco se expone. La 2FA y el resto de seguridad quedan para sus sub-fases.
 *
 * **Defensa en profundidad:** la validación del formulario (tipos/rangos) replica los
 * límites de los helpers defensivos (`PaymentSettings`/`PuertaSettings`/`DisplayTime`), que
 * siguen siendo la última línea (leen con fallback no destructivo si el valor se corrompe).
 * Ningún setting de este alcance se cachea → no hay caché que invalidar (se leen en vivo).
 */
class Settings extends Page
{
    // Icono propio (no el engranaje del cluster «Configuración», que también usa Cog6Tooth).
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $slug = 'settings';

    // Primero dentro del cluster "Configuración".
    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.settings';

    /** Estado del formulario (claves de `settings` anidadas por el punto, p. ej. data.business.name). */
    public ?array $data = [];

    /** Ajustes booleanos (se guardan como '1'/'0'). */
    private const BOOL_KEYS = ['packs.prep_blocks_cupo', 'cookies.banner_enabled'];

    /**
     * Toggles cuyo DEFAULT de runtime es ON (sus helpers defensivos devuelven true sin fila). Si la
     * fila falta, `mount()` debe hidratar el toggle en ON para no mostrar OFF y, con un Save, apagar
     * el comportamiento por accidente (mismatch UI↔runtime). Coincide con `CookieConsent::bannerEnabled`
     * (#223). El interruptor del waiver dejó de ser un toggle en Fase 6: es el MODO `waiver.mode`,
     * que `mount()` hidrata con el valor EFECTIVO por la misma razón.
     */
    private const BOOL_DEFAULT_ON = ['cookies.banner_enabled'];

    /**
     * Claves gestionadas → su `group` en la tabla (fuente única para cargar y guardar).
     * El orden no importa; el formulario define la presentación.
     *
     * @var array<string,string>
     */
    private const MANAGED = [
        // Negocio + datos fiscales
        'business.name' => 'business',
        'business.city' => 'business',
        'business.legal_name' => 'business',
        'business.nif' => 'business',
        'business.address' => 'business',
        // Dominio del sitio para los textos legales (token `:site_domain`, #220). Vacío → se usa el
        // host de la petición (en producción, el dominio real automáticamente).
        'business.domain' => 'business',
        // Contacto + dirección + redes
        'contact.email' => 'contact',
        'contact.phone' => 'contact',
        // DESDE dónde salen los correos (`#500`). ⚠️ NO es `contact.email`: aquél es el buzón al
        // que escribe un cliente y éste es el remitente de los 23 correos automáticos — suelen ser
        // direcciones distintas, y ésta tiene que estar en el dominio que firma con SPF/DKIM.
        'mail.from_address' => 'contact',
        'address.line1' => 'contact',
        'address.line2' => 'contact',
        'address.maps_url' => 'contact',
        'address.maps_embed_url' => 'contact',
        'contact.instagram' => 'social',
        'contact.tiktok' => 'social',
        'contact.whatsapp' => 'contact',
        // Feed social de la sección «en directo» (#215): inserción (iframe) de un
        // widget de Instagram/TikTok que muestra las últimas publicaciones (SnapWidget/LightWidget).
        'social.feed_embed_url' => 'social',
        // Registro «del parque» (#216): el CTA público «Registro» del header lleva al sistema externo
        // de registro/waiver de la clienta. Etiqueta + subtítulo POR IDIOMA (fallback al texto i18n).
        'registration.url' => 'registration',
        'registration.label.es' => 'registration',
        'registration.label.en' => 'registration',
        'registration.label.fr' => 'registration',
        'registration.subtitle.es' => 'registration',
        'registration.subtitle.en' => 'registration',
        'registration.subtitle.fr' => 'registration',
        // Texto informativo del registro mostrado en la pantalla final de la compra (#223).
        'registration.description.es' => 'registration',
        'registration.description.en' => 'registration',
        'registration.description.fr' => 'registration',
        // Fiestas por edad (`specs/cumple-mixto.md` §22.4, `DECISIONES #284` D6): lo que lee el cliente
        // en el post-form cuando una edad no tiene producto en las condiciones de su reserva. Tres
        // casos POR IDIOMA; vacío → texto por defecto de `lang/*/guestform.php`.
        'mixed_party.no_product.below.es' => 'mixed_party',
        'mixed_party.no_product.below.en' => 'mixed_party',
        'mixed_party.no_product.below.fr' => 'mixed_party',
        'mixed_party.no_product.above.es' => 'mixed_party',
        'mixed_party.no_product.above.en' => 'mixed_party',
        'mixed_party.no_product.above.fr' => 'mixed_party',
        'mixed_party.no_product.gap.es' => 'mixed_party',
        'mixed_party.no_product.gap.en' => 'mixed_party',
        'mixed_party.no_product.gap.fr' => 'mixed_party',
        // SEO
        'seo.og_image' => 'seo',
        // LA FIESTA (`specs/fiesta-sistema-nuevo.md` F1c, `#743` §7·7): «Ver el parque» en la invitación, con el vídeo
        // de portada y su foto. Vacío → la invitación no ofrece el vídeo.
        'party.park_video' => 'party',
        'party.park_video_poster' => 'party',
        // Textos de la landing editables POR IDIOMA (#215): título web (SEO), eslogan del pie y la
        // coletilla del copyright. Vacío → cae al texto traducido por defecto (lang/landing).
        'seo.title.es' => 'seo',
        'seo.title.en' => 'seo',
        'seo.title.fr' => 'seo',
        'landing.tagline.es' => 'landing',
        'landing.tagline.en' => 'landing',
        'landing.tagline.fr' => 'landing',
        'landing.footer_rights.es' => 'landing',
        'landing.footer_rights.en' => 'landing',
        'landing.footer_rights.fr' => 'landing',
        'landing.zones_access.es' => 'landing',
        'landing.zones_access.en' => 'landing',
        'landing.zones_access.fr' => 'landing',
        /*
         * **`/bar`** (`#536`). ⚠️ Aquí van solo los TEXTOS; las imágenes viven en su propia pantalla
         * («Ajustes → El bar»), porque esta página **no sube ficheros** —cero `FileUpload` en las 992
         * líneas de su formulario— y la carta es una colección ordenable.
         * ⚠️ **Lo que NO está aquí y es a propósito**: «se pide en la barra» y la línea de alérgenos
         * son del PRODUCTO —ninguna instalación vende comida por la web—, así que viven en `site.php`
         * y no como texto editable. Lo que sí es de este cliente es su nombre, su promesa y su foto.
         */
        'bar.name.es' => 'bar',
        'bar.name.en' => 'bar',
        'bar.name.fr' => 'bar',
        'bar.lede.es' => 'bar',
        'bar.lede.en' => 'bar',
        'bar.lede.fr' => 'bar',
        'bar.photo_caption.es' => 'bar',
        'bar.photo_caption.en' => 'bar',
        'bar.photo_caption.fr' => 'bar',
        /*
         * ⚠️ **TRES estados y no un interruptor**: «sí se puede entrar solo», «no se puede» y **«no
         * lo hemos decidido»**, que es el estado de fábrica y no publica nada. Un toggle solo tiene
         * dos, y su `false` afirmaría «no se puede entrar» en toda instalación recién montada — una
         * afirmación de negocio que nadie ha hecho.
         */
        'bar.free_entry' => 'bar',
        // Operativa
        'sales.hold_minutes' => 'payment',
        'sales.purchase_horizon_months' => 'payment',
        'sales.order_prefix' => 'payment',
        'puerta.validate_rate_limit_per_minute' => 'puerta',
        // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §9.2 A·9): la ficha de puerta.
        'puerta.lookup_rate_limit_per_hour' => 'puerta',
        'puerta.profile_ttl_minutes' => 'puerta',
        'puerta.window_days' => 'puerta',
        // T3 de las encuestas (`specs/encuestas.md` §4.3): el plazo entre dos correos de encuesta a la misma persona.
        'surveys.cooldown_days' => 'puerta',
        // Fase 6 · waiver (`DECISIONES #142`): el MODO sustituye al interruptor de #216 —externo (el
        // sistema del parque; aquí solo el sello) · interno (se firma aquí) · desactivado— y el
        // plazo de conservación del registro firmado (vacío = no se poda; `[PENDIENTE: owner]`).
        // `puerta.waiver_check_enabled` ya no se edita: `save()` lo escribe como espejo del modo.
        'waiver.mode' => 'waiver',
        'waiver.retention_months' => 'waiver',
        'waiver.dependent_retention_months' => 'waiver',
        // Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §4.5): el tope de personas a cargo por
        // cuenta. Es un invariante de SERVIDOR (lo aplica `DependentRegistry`); vacío = 20.
        'dependents.max_per_account' => 'dependents',
        'payment.tax_rate' => 'payment',
        'packs.max_per_slot' => 'packs',
        'packs.max_guests_per_slot' => 'packs',
        'packs.prep_blocks_cupo' => 'packs',
        'packs.guest_count_cutoff_hours' => 'packs',
        // Pagos / Redsys (NO secreto)
        'redsys_environment' => 'payment',
        'redsys_merchant_code' => 'payment',
        'redsys_terminal' => 'payment',
        'redsys_currency' => 'payment',
        'redsys_merchant_name' => 'payment',
        'redsys_merchant_url' => 'payment',
        // Avisos de incidencia de cobro (recomendación C, 2026-06-15): email del operador al que
        // avisar de un cobro duplicado/huérfano o tras caducar. Vacío → se usa `contact.email`.
        'incidents.alert_email' => 'payment',
        // Tema (white-label, #7.10 iter.2): color de marca global. Los colores POR ZONA viven
        // en `zones.color` (#210) y se editan en cada zona, no aquí.
        'theme.brand' => 'theme',
        'theme.brand_secondary' => 'theme',
        // Color de ACCIÓN (`#209`): el relleno del botón que hace avanzar la compra. Vacío ⇒ el
        // botón sigue a la superficie (conducta histórica del producto); con valor ⇒ es el mismo
        // en los dos fondos. Ver `ThemeSettings::action()`.
        'theme.action' => 'theme',
        // Cookies (#223): mostrar el banner de consentimiento. Apagarlo NO desactiva el bloqueo
        // previo de los iframes de tercero (siguen gateados) — solo oculta el banner.
        'cookies.banner_enabled' => 'cookies',
        // Catálogo del sidebar de compra (#226): nº de productos a partir del cual aparece el
        // buscador. Vacío → default de `CatalogSettings` (12). El helper clampa el valor leído.
        'catalog.search_min_items' => 'catalog',
        // La CARCASA de la compra (`DECISIONES #682`, `specs/isla-y-landing-nueva.md` §4.10): el cajón lateral de
        // siempre o la isla del sistema nuevo. Sin fila es el cajón (`ShellSettings`).
        ShellSettings::KEY => 'theme',
        // Aviso de «casi llena» en el paso de hora del cajón (`#239`): plazas libres a partir de las
        // cuales la hora deja de anunciarse. Vacío → default de `AvailabilitySettings` (8); `0`
        // apaga el aviso. NO es una regla de aforo: no vende ni retiene una plaza (`AFORO-02`).
        'booking.low_availability_max' => 'booking',
        // La herramienta de análisis (`specs/analitica.md` §4.3, T3a·2): el driver y sus datos PÚBLICOS.
        // Las credenciales privadas del olvido van en `.env` (`services.posthog`, `services.matomo`).
        Drivers::KEY_DRIVER => 'analytics',
        Drivers::KEY_POSTHOG_PROJECT => 'analytics',
        Drivers::KEY_MATOMO_HOST => 'analytics',
        Drivers::KEY_MATOMO_SITE_ID => 'analytics',
        // Los píxeles de anuncios (T3b·1): ids PÚBLICOS; cargan solo con la categoría `marketing`. Los tokens de
        // las APIs de conversiones van en `.env` (`services.meta`, `services.tiktok`), nunca aquí.
        Pixels::KEY_GOOGLE_ADS_ID => 'marketing',
        Pixels::KEY_GOOGLE_ADS_LABEL => 'marketing',
        Pixels::KEY_META_PIXEL_ID => 'marketing',
        Pixels::KEY_TIKTOK_PIXEL_ID => 'marketing',
    ];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermission('settings.manage') ?? false;
    }

    /**
     * #223 — fuera del menú lateral: esta pantalla es de puesta en marcha, no del día a
     * día, y se entra por «Ajustes» (`AdminSettingsHub`, menú del avatar). Ocultar NO es
     * autorizar: quien decide el acceso sigue siendo `canAccess()`/`canViewAny()`.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.nav_label');
    }

    public function getTitle(): string
    {
        return __('admin.settings.title');
    }

    public function mount(): void
    {
        $values = [];
        foreach (array_keys(self::MANAGED) as $key) {
            // Los toggles con default ON se hidratan en ON si falta la fila (espejo de su helper).
            $raw = Setting::value($key, in_array($key, self::BOOL_DEFAULT_ON, true) ? '1' : null);
            // El modo del waiver se hidrata con el EFECTIVO (derivado del interruptor heredado si no
            // hay fila), por la misma razón que los toggles: que un Guardar no cambie la conducta.
            if ($key === WaiverSettings::KEY_MODE) {
                $raw = WaiverSettings::mode();
            }
            // El driver sin fila es «ninguno»: que el desplegable no arranque vacío ni un Guardar lo cambie.
            if ($key === Drivers::KEY_DRIVER && ! in_array($raw, Drivers::ALL, true)) {
                $raw = Drivers::NONE;
            }
            // La carcasa, igual: sin fila (o con un valor que no es ninguna) se hidrata con la EFECTIVA.
            if ($key === ShellSettings::KEY) {
                $raw = ShellSettings::shell();
            }
            $value = in_array($key, self::BOOL_KEYS, true)
                ? ((string) $raw === '1')
                : ($raw ?? '');
            // El punto de la clave crea estado anidado (data.business.name) → data_set.
            data_set($values, $key, $value);
        }

        $this->form->fill($values);
    }

    public function form(Schema $schema): Schema
    {
        // Fase 3 · Plan B · L2 — Reorganización para el empleado NO técnico: el muro de ~45 campos
        // en 10 secciones planas se agrupa en 4 PESTAÑAS de nivel superior (lo cotidiano primero,
        // lo técnico al final y colapsado). Las pestañas/secciones son PURO LAYOUT: no alteran el
        // `statePath` de los campos → el guardado sigue siendo ATÓMICO (un Guardar persiste las 45
        // claves) y la validación cruzada Redsys-live de `save()` queda intacta.
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('settings_tabs')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->tabs([
                        $this->businessTab(),
                        $this->webTab(),
                        $this->fiscalTab(),
                        $this->advancedTab(),
                    ]),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();   // valida tipos/rangos

        /** @var array<string,mixed> $flat */
        $flat = Arr::dot($state);

        // Guarda cruzada (T3a·2): un driver de análisis sin sus datos no se guarda. `Drivers::config()` lo
        // trataría como «ninguno» igualmente, pero el operador tiene que verlo aquí, no descubrirlo porque
        // la herramienta nunca aparece.
        $driver = (string) ($flat[Drivers::KEY_DRIVER] ?? Drivers::NONE);
        if ($driver === Drivers::POSTHOG && Drivers::posthogProject($flat[Drivers::KEY_POSTHOG_PROJECT] ?? null) === null) {
            Notification::make()->title(__('admin.settings.analytics_posthog_requires_token'))->danger()->persistent()->send();

            return;
        }
        if ($driver === Drivers::MATOMO && (Drivers::matomoHost($flat[Drivers::KEY_MATOMO_HOST] ?? null) === null || Drivers::matomoSiteId($flat[Drivers::KEY_MATOMO_SITE_ID] ?? null) === null)) {
            Notification::make()->title(__('admin.settings.analytics_matomo_requires_host'))->danger()->persistent()->send();

            return;
        }

        // Guarda cruzada: no permitir pasar Redsys a producción ('live') sin las credenciales
        // no-secretas mínimas (código de comercio + terminal). El default sandbox solo aplica
        // si la fila NO existe; un '' guardado dejaría la pasarela mal configurada en silencio.
        // Rechaza el guardado COMPLETO (atómico) con un aviso claro.
        if (($flat['redsys_environment'] ?? null) === 'live'
            && (trim((string) ($flat['redsys_merchant_code'] ?? '')) === ''
                || trim((string) ($flat['redsys_terminal'] ?? '')) === '')) {
            Notification::make()
                ->title(__('admin.settings.redsys_live_requires_credentials'))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        // Guarda cruzada (auditoría Fase 1 · runbook §5): NO permitir pasar a 'live' mientras la
        // CLAVE SECRETA efectiva no sea una clave real del banco. El secreto NO es un campo de este
        // formulario (vive en `REDSYS_SECRET_KEY` del vault, fallback a `settings`), así que se valida
        // el valor EFECTIVO que firmaría los cobros: `Redsys::config()['secret_key']`. Se rechaza si
        // sigue siendo la clave pública de sandbox o no mide 32 chars → evita un apagón de cobros en
        // producción (la pasarela rechazaría toda firma) por un go-live con el secreto sin desplegar.
        if (($flat['redsys_environment'] ?? null) === 'live') {
            $secret = (string) (new Redsys)->config()['secret_key'];
            if (mb_strlen($secret) !== Redsys::SECRET_KEY_LENGTH || $secret === Redsys::SANDBOX_SECRET_KEY) {
                Notification::make()
                    ->title(__('admin.settings.redsys_live_requires_secret'))
                    ->danger()
                    ->persistent()
                    ->send();

                return;
            }
        }

        $changes = [];
        $mapsEmbedRejected = false;
        $socialEmbedRejected = false;
        foreach (self::MANAGED as $key => $group) {
            $value = $flat[$key] ?? null;
            if (in_array($key, self::BOOL_KEYS, true)) {
                $value = $value ? '1' : '0';
            }
            $value = $value === null ? '' : trim((string) $value);

            // El mapa embebido: extraer la URL `src` limpia de lo que se pegue (iframe completo,
            // URL con atributos detrás, o URL sola). Si se pegó algo NO reconocible como inserción
            // de Google Maps, NO se sobreescribe el mapa actual en silencio: se preserva y se avisa.
            if ($key === 'address.maps_embed_url' && $value !== '') {
                $clean = MapsEmbed::clean($value);
                if ($clean === null) {
                    $mapsEmbedRejected = true;

                    continue;
                }
                $value = $clean;
            }

            // El feed social (sección «en directo»): mismo trato que el mapa — extraer
            // el `src` limpio del iframe del widget (SnapWidget/LightWidget). Si no se reconoce un
            // proveedor permitido, se preserva el valor actual y se avisa (no se rompe la sección).
            if ($key === 'social.feed_embed_url' && $value !== '') {
                $clean = SocialEmbed::clean($value);
                if ($clean === null) {
                    $socialEmbedRejected = true;

                    continue;
                }
                $value = $clean;
            }

            // WhatsApp: guardar SOLO dígitos (formato internacional sin '+'), para construir el
            // enlace `https://wa.me/{digits}` sin ambigüedad de formato (espacios, guiones, '+').
            if ($key === 'contact.whatsapp' && $value !== '') {
                $value = preg_replace('/\D/', '', $value) ?? '';
            }

            $old = (string) (Setting::value($key) ?? '');
            if ($old !== $value) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }

            Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        }

        // Espejo del interruptor heredado `puerta.waiver_check_enabled` (#216): ya no se edita, pero
        // sigue en BD y `WaiverSettings::mode()` lo usa como respaldo. Se mantiene coherente con el modo.
        Setting::updateOrCreate(
            ['key' => WaiverSettings::LEGACY_KEY_CHECK_ENABLED],
            ['value' => ($flat[WaiverSettings::KEY_MODE] ?? null) === WaiverSettings::MODE_OFF ? '0' : '1', 'group' => 'puerta'],
        );

        if ($changes !== []) {
            AuditLogger::log('settings.updated', null, ['changed' => $changes]);
        }

        Notification::make()
            ->title(__('admin.settings.saved'))
            ->success()
            ->send();

        if ($mapsEmbedRejected) {
            Notification::make()
                ->title(__('admin.settings.maps_embed_not_recognized'))
                ->warning()
                ->persistent()
                ->send();
        }

        if ($socialEmbedRejected) {
            Notification::make()
                ->title(__('admin.settings.social_feed_not_recognized'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    // ─── Pestañas de nivel superior (L2) ───────────────────────────────────────

    /**
     * Pestaña «Tu negocio»: lo cotidiano que el empleado toca a menudo (identidad, contacto,
     * dirección + mapa, redes y el registro externo). La sección polisémica original «Contacto,
     * dirección y redes» se rompe en tres dominios claros.
     */
    private function businessTab(): Tab
    {
        return Tab::make(__('admin.settings.tab_business'))
            ->icon(Heroicon::OutlinedBuildingStorefront)
            ->schema([
                $this->identitySection(),
                $this->contactSection(),
                $this->addressSection(),
                $this->socialSection(),
                $this->registrationSection(),
            ]);
    }

    /** Pestaña «Textos y aspecto web»: textos por idioma de la landing + opciones de aspecto. */
    private function webTab(): Tab
    {
        return Tab::make(__('admin.settings.tab_web'))
            ->icon(Heroicon::OutlinedPaintBrush)
            ->schema([
                $this->landingTextsSection(),
                $this->barSection(),
                $this->mixedPartySection(),
                $this->webAppearanceSection(),
            ]);
    }

    /** Pestaña «Datos fiscales»: identidad fiscal para facturación y textos legales. */
    private function fiscalTab(): Tab
    {
        return Tab::make(__('admin.settings.tab_fiscal'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->schema([
                $this->fiscalSection(),
            ]);
    }

    /**
     * Pestaña «Avanzado»: ajustes técnicos que afectan al comportamiento (ventas, puerta, aforo,
     * pasarela). Cada sección llega COLAPSADA y con un aviso de criticidad en su descripción; lo
     * habitual es no tocar nada aquí.
     */
    private function advancedTab(): Tab
    {
        return Tab::make(__('admin.settings.tab_advanced'))
            ->icon(Heroicon::OutlinedWrenchScrewdriver)
            ->schema([
                $this->salesSection(),
                $this->doorSection(),
                $this->capacitySection(),
                $this->redsysSection(),
                $this->analyticsSection(),
                $this->adsSection(),
            ]);
    }

    /**
     * La herramienta de análisis (`specs/analitica.md` §4.3, T3a·2): el driver y sus datos PÚBLICOS. Solo se
     * carga con la categoría `analytics` del banner; sus orígenes entran en la CSP con el driver activo.
     */
    private function analyticsSection(): Section
    {
        return Section::make(__('admin.settings.section_analytics'))
            ->description(__('admin.settings.section_analytics_hint'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                Select::make(Drivers::KEY_DRIVER)
                    ->label(__('admin.settings.analytics_driver'))
                    ->helperText(__('admin.settings.analytics_driver_hint'))
                    ->options([
                        Drivers::NONE => __('admin.settings.analytics_driver_none'),
                        Drivers::POSTHOG => __('admin.settings.analytics_driver_posthog'),
                        Drivers::MATOMO => __('admin.settings.analytics_driver_matomo'),
                    ])
                    ->default(Drivers::NONE)
                    ->selectablePlaceholder(false)
                    ->required(),
                TextInput::make(Drivers::KEY_POSTHOG_PROJECT)
                    ->label(__('admin.settings.analytics_posthog_project'))
                    ->helperText(__('admin.settings.analytics_posthog_project_hint'))
                    ->regex('/^$|^phc_[A-Za-z0-9]{20,}$/')
                    ->maxLength(80),
                TextInput::make(Drivers::KEY_MATOMO_HOST)
                    ->label(__('admin.settings.analytics_matomo_host'))
                    ->helperText(__('admin.settings.analytics_matomo_host_hint'))
                    ->regex('#^$|^https://[A-Za-z0-9.-]+(:\d+)?/?$#')
                    ->maxLength(255),
                TextInput::make(Drivers::KEY_MATOMO_SITE_ID)
                    ->label(__('admin.settings.analytics_matomo_site_id'))
                    ->regex('/^$|^[1-9]\d{0,8}$/')
                    ->maxLength(9),
            ]);
    }

    /**
     * Los píxeles de anuncios (`specs/analitica.md` §4.3, T3b·1): ids PÚBLICOS por plataforma. Solo cargan con la
     * categoría `marketing` del banner; sus orígenes entran en la CSP con el píxel configurado. Un id con otra
     * forma no pasa el campo (`Pixels::*_RE`), y vacío es «sin píxel».
     */
    private function adsSection(): Section
    {
        return Section::make(__('admin.settings.section_ads'))
            ->description(__('admin.settings.section_ads_hint'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                TextInput::make(Pixels::KEY_GOOGLE_ADS_ID)
                    ->label(__('admin.settings.ads_google_conversion_id'))
                    ->helperText(__('admin.settings.ads_google_conversion_id_hint'))
                    ->regex('/^$|^AW-\d{6,12}$/')
                    ->maxLength(16),
                TextInput::make(Pixels::KEY_GOOGLE_ADS_LABEL)
                    ->label(__('admin.settings.ads_google_conversion_label'))
                    ->helperText(__('admin.settings.ads_google_conversion_label_hint'))
                    ->regex('/^$|^[A-Za-z0-9_-]{6,40}$/')
                    ->maxLength(40),
                TextInput::make(Pixels::KEY_META_PIXEL_ID)
                    ->label(__('admin.settings.ads_meta_pixel_id'))
                    ->helperText(__('admin.settings.ads_meta_pixel_id_hint'))
                    ->regex('/^$|^\d{10,20}$/')
                    ->maxLength(20),
                TextInput::make(Pixels::KEY_TIKTOK_PIXEL_ID)
                    ->label(__('admin.settings.ads_tiktok_pixel_id'))
                    ->helperText(__('admin.settings.ads_tiktok_pixel_id_hint'))
                    ->regex('/^$|^[A-Z0-9]{16,24}$/')
                    ->maxLength(24),
            ]);
    }

    // ─── Secciones del formulario ──────────────────────────────────────────────

    /** Identidad pública del negocio (lo que el empleado reconoce a simple vista). */
    private function identitySection(): Section
    {
        return Section::make(__('admin.settings.section_identity'))
            ->description(__('admin.settings.section_identity_hint'))
            ->columns(2)
            ->schema([
                TextInput::make('business.name')
                    ->label(__('admin.settings.business_name'))
                    ->required()
                    ->maxLength(120),
                TextInput::make('business.city')
                    ->label(__('admin.settings.business_city'))
                    ->maxLength(120),
                TextInput::make('business.domain')
                    ->label(__('admin.settings.business_domain'))
                    ->helperText(__('admin.settings.business_domain_hint'))
                    ->maxLength(120),
            ]);
    }

    /** Contacto directo del negocio (email/teléfono/WhatsApp). */
    private function contactSection(): Section
    {
        return Section::make(__('admin.settings.section_contact_data'))
            ->description(__('admin.settings.section_contact_data_hint'))
            ->columns(2)
            ->schema([
                TextInput::make('contact.email')
                    ->label(__('admin.settings.contact_email'))
                    ->email()
                    ->maxLength(160),
                // El REMITENTE de los correos automáticos (`#500`). Vacío → se usa el del `.env`,
                // que en una instalación recién montada vale `hello@example.com`.
                TextInput::make('mail.from_address')
                    ->label(__('admin.settings.mail_from_address'))
                    ->helperText(__('admin.settings.mail_from_address_hint'))
                    // El campo vacío es un estado LEGÍTIMO (se usa el del servidor), así que el
                    // operador tiene que poder ver QUÉ sale hoy sin abrir el `.env`. Sin esto, un
                    // hueco vacío y un `hello@example.com` se ven exactamente igual.
                    ->placeholder(fn (): string => (string) config('mail.from.address'))
                    ->email()
                    ->maxLength(160),
                TextInput::make('contact.phone')
                    ->label(__('admin.settings.contact_phone'))
                    ->tel()
                    ->maxLength(40),
                TextInput::make('contact.whatsapp')
                    ->label(__('admin.settings.contact_whatsapp'))
                    ->helperText(__('admin.settings.contact_whatsapp_hint'))
                    ->tel()
                    ->maxLength(40),
            ]);
    }

    /** Dirección del parque + mapa embebido (Google Maps). */
    private function addressSection(): Section
    {
        return Section::make(__('admin.settings.section_address'))
            ->description(__('admin.settings.section_address_hint'))
            ->columns(2)
            ->schema([
                TextInput::make('address.line1')
                    ->label(__('admin.settings.address_line1'))
                    ->maxLength(160),
                TextInput::make('address.line2')
                    ->label(__('admin.settings.address_line2'))
                    ->maxLength(160),
                TextInput::make('address.maps_url')
                    ->label(__('admin.settings.address_maps_url'))
                    ->helperText(__('admin.settings.address_maps_url_hint'))
                    ->url()
                    ->maxLength(255),
                TextInput::make('address.maps_embed_url')
                    ->label(__('admin.settings.address_maps_embed_url'))
                    ->helperText(__('admin.settings.address_maps_embed_url_hint'))
                    // Acepta vacío, el <iframe> COMPLETO que da Google (Compartir → «Insertar un
                    // mapa») o la URL de inserción sola: `save()` extrae el `src` limpio con
                    // `MapsEmbed::clean`. Aquí solo se exige que CONTENGA una inserción de Google Maps.
                    ->regex('#^$|https://www\.google\.com/maps/embed#')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    /** Redes sociales + feed «en directo». */
    private function socialSection(): Section
    {
        return Section::make(__('admin.settings.section_social'))
            ->description(__('admin.settings.section_social_hint'))
            ->columns(2)
            ->schema([
                TextInput::make('contact.instagram')
                    ->label(__('admin.settings.contact_instagram'))
                    ->url()
                    ->maxLength(255),
                TextInput::make('contact.tiktok')
                    ->label(__('admin.settings.contact_tiktok'))
                    ->url()
                    ->maxLength(255),
                TextInput::make('social.feed_embed_url')
                    ->label(__('admin.settings.social_feed'))
                    ->helperText(__('admin.settings.social_feed_hint'))
                    // Acepta vacío, el <iframe> COMPLETO del widget (SnapWidget/LightWidget) o su
                    // URL de inserción sola: `save()` extrae el `src` limpio con `SocialEmbed::clean`.
                    // Aquí solo se exige que mencione un proveedor permitido.
                    ->regex('#^$|snapwidget\.com|lightwidget\.com#')
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Datos fiscales: razón social, NIF/CIF, IVA por defecto y domicilio fiscal. Alimentan
     * facturación y los textos legales; suelen rellenarse una vez (de ahí su pestaña propia, fuera
     * de lo cotidiano). El IVA vive aquí —y no en «Avanzado»— porque un empleado no técnico lo busca
     * junto a los datos de facturación; es informativo y no condiciona el cobro.
     */
    private function fiscalSection(): Section
    {
        return Section::make(__('admin.settings.section_fiscal'))
            ->description(__('admin.settings.section_fiscal_hint'))
            ->columns(2)
            ->schema([
                TextInput::make('business.legal_name')
                    ->label(__('admin.settings.business_legal_name'))
                    ->helperText(__('admin.settings.business_legal_name_hint'))
                    ->maxLength(160),
                TextInput::make('business.nif')
                    ->label(__('admin.settings.business_nif'))
                    ->maxLength(20),
                TextInput::make('payment.tax_rate')
                    ->label(__('admin.settings.tax_rate'))
                    ->helperText(__('admin.settings.tax_rate_hint'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100),
                Textarea::make('business.address')
                    ->label(__('admin.settings.business_address'))
                    ->helperText(__('admin.settings.business_address_hint'))
                    ->rows(2)
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Textos de la landing editables POR IDIOMA (#215): título web (SEO), eslogan del pie y la
     * coletilla del copyright. Cada uno con pestañas es/en/fr (la landing pública es trilingüe).
     * Si un idioma se deja vacío, el render cae al texto traducido por defecto (`lang/landing`).
     */
    private function landingTextsSection(): Section
    {
        return Section::make(__('admin.settings.section_landing_texts'))
            ->description(__('admin.settings.section_landing_texts_hint'))
            ->schema([
                Tabs::make('landing_texts')->tabs([
                    $this->landingTextTab('es', __('admin.settings.lang_es')),
                    $this->landingTextTab('en', __('admin.settings.lang_en')),
                    $this->landingTextTab('fr', __('admin.settings.lang_fr')),
                ]),
            ]);
    }

    /**
     * **`/bar` · los textos** (`DECISIONES #536`, artboard `Bar PJP`).
     *
     * ⚠️ **Sin nombre, la página no se publica.** No es un fallo: el titular de `/bar` es el nombre
     * del bar, y el propio canvas lo dejó como la pregunta que bloquea la página («el nombre real del
     * bar — ya estaba pendiente de 03 y aquí es el titular»). Publicarla con un genérico sería
     * inventarle nombre al local de un cliente.
     *
     * ⚠️ Las IMÁGENES no están aquí: se suben en «Ajustes → El bar», que es una colección ordenable
     * y esta página no sube ficheros.
     */
    private function barSection(): Section
    {
        return Section::make(__('admin.settings.section_bar'))
            ->description(__('admin.settings.section_bar_hint'))
            ->schema([
                Select::make('bar.free_entry')
                    ->label(__('admin.settings.bar_free_entry'))
                    ->helperText(__('admin.settings.bar_free_entry_hint'))
                    ->options([
                        '' => __('admin.settings.bar_free_entry_unset'),
                        'yes' => __('admin.settings.bar_free_entry_yes'),
                        'no' => __('admin.settings.bar_free_entry_no'),
                    ])
                    ->default('')
                    ->native(false),

                Tabs::make('bar_texts')->tabs([
                    $this->barTextTab('es', __('admin.settings.lang_es')),
                    $this->barTextTab('en', __('admin.settings.lang_en')),
                    $this->barTextTab('fr', __('admin.settings.lang_fr')),
                ]),
            ]);
    }

    private function barTextTab(string $loc, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("bar.name.{$loc}")
                ->label(__('admin.settings.bar_name'))
                ->helperText(__('admin.settings.bar_name_hint'))
                ->maxLength(80),
            TextInput::make("bar.lede.{$loc}")
                ->label(__('admin.settings.bar_lede'))
                ->helperText(__('admin.settings.bar_lede_hint'))
                ->maxLength(200),
            TextInput::make("bar.photo_caption.{$loc}")
                ->label(__('admin.settings.bar_photo_caption'))
                ->helperText(__('admin.settings.bar_photo_caption_hint'))
                ->maxLength(200),
        ]);
    }

    private function landingTextTab(string $loc, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("seo.title.{$loc}")
                ->label(__('admin.settings.seo_title'))
                ->helperText(__('admin.settings.seo_title_hint'))
                ->maxLength(180),
            TextInput::make("landing.tagline.{$loc}")
                ->label(__('admin.settings.landing_tagline'))
                ->helperText(__('admin.settings.landing_tagline_hint'))
                ->maxLength(180),
            TextInput::make("landing.footer_rights.{$loc}")
                ->label(__('admin.settings.landing_footer_rights'))
                ->helperText(__('admin.settings.landing_footer_rights_hint'))
                ->maxLength(120),
            Textarea::make("landing.zones_access.{$loc}")
                ->label(__('admin.settings.landing_zones_access'))
                ->helperText(__('admin.settings.landing_zones_access_hint'))
                ->rows(2)
                ->maxLength(300),
        ]);
    }

    /**
     * Registro «del parque» (#216): el CTA público «Registro» del header apunta al sistema externo
     * de la clienta (registro/waiver). URL única + etiqueta/subtítulo POR IDIOMA (pestañas es/en/fr;
     * vacío → texto i18n por defecto). Si la URL queda vacía, el CTA cae al modal de registro interno.
     */
    private function registrationSection(): Section
    {
        return Section::make(__('admin.settings.section_registration'))
            ->description(__('admin.settings.section_registration_hint'))
            ->schema([
                TextInput::make('registration.url')
                    ->label(__('admin.settings.registration_url'))
                    ->helperText(__('admin.settings.registration_url_hint'))
                    ->url()
                    ->maxLength(255),
                Tabs::make('registration_texts')->tabs([
                    $this->registrationTextTab('es', __('admin.settings.lang_es')),
                    $this->registrationTextTab('en', __('admin.settings.lang_en')),
                    $this->registrationTextTab('fr', __('admin.settings.lang_fr')),
                ]),
            ]);
    }

    /**
     * Fiestas por edad (`specs/cumple-mixto.md` §22.4, `DECISIONES #284` D6): los tres textos que lee
     * el cliente en el formulario de invitados cuando declara una edad para la que no hay producto
     * en las condiciones de su reserva — por debajo del tramo más bajo, por encima del más alto, en
     * un hueco entre dos—. Por idioma, con respaldo en el texto por defecto y el marcador `:phone`.
     */
    private function mixedPartySection(): Section
    {
        return Section::make(__('admin.settings.section_mixed_party'))
            ->description(__('admin.settings.section_mixed_party_hint'))
            ->collapsible()
            ->collapsed()
            ->schema([
                Tabs::make('mixed_party_texts')->tabs([
                    $this->mixedPartyTextTab('es', __('admin.settings.lang_es')),
                    $this->mixedPartyTextTab('en', __('admin.settings.lang_en')),
                    $this->mixedPartyTextTab('fr', __('admin.settings.lang_fr')),
                ]),
            ]);
    }

    private function mixedPartyTextTab(string $loc, string $label): Tab
    {
        return Tab::make($label)->schema([
            Textarea::make("mixed_party.no_product.below.{$loc}")
                ->label(__('admin.settings.mixed_party_below'))
                ->helperText(__('admin.settings.mixed_party_text_hint'))
                ->rows(2)
                ->maxLength(400),
            Textarea::make("mixed_party.no_product.above.{$loc}")
                ->label(__('admin.settings.mixed_party_above'))
                ->rows(2)
                ->maxLength(400),
            Textarea::make("mixed_party.no_product.gap.{$loc}")
                ->label(__('admin.settings.mixed_party_gap'))
                ->rows(2)
                ->maxLength(400),
        ]);
    }

    private function registrationTextTab(string $loc, string $label): Tab
    {
        return Tab::make($label)->schema([
            TextInput::make("registration.label.{$loc}")
                ->label(__('admin.settings.registration_label'))
                ->helperText(__('admin.settings.registration_label_hint'))
                ->maxLength(60),
            TextInput::make("registration.subtitle.{$loc}")
                ->label(__('admin.settings.registration_subtitle'))
                ->maxLength(120),
            Textarea::make("registration.description.{$loc}")
                ->label(__('admin.settings.registration_description'))
                ->helperText(__('admin.settings.registration_description_hint'))
                ->rows(3)
                ->maxLength(400),
        ]);
    }

    /**
     * Aspecto y opciones de la web: agrupa los 4 controles sueltos de un solo campo (color de
     * marca, imagen para compartir, umbral del buscador del catálogo y banner de cookies) en una
     * única sección a 2 columnas, en vez de 4 secciones de una línea. Cada campo conserva su label
     * y su ayuda, así que el significado se mantiene.
     *
     * - `theme.brand` (white-label, #7.10 iter.2): color de marca GLOBAL que consume la web, el
     *   primario del panel y el acento de los emails (vía `ThemeSettings`, que valida + hace
     *   fallback). Los colores POR ZONA se editan en cada zona (#210), no aquí.
     * - `seo.og_image`: imagen Open Graph al compartir el sitio.
     * - `catalog.search_min_items` (#226): umbral del buscador del catálogo (vacío → default 12 de
     *   `CatalogSettings`; la validación replica su rango).
     * - `booking.low_availability_max` (`#239`): plazas libres a partir de las cuales una hora se
     *   anuncia «casi llena» en el cajón (vacío → default 8 de `AvailabilitySettings`; `0` apaga el
     *   aviso). Es escaparate, no aforo.
     * - `cookies.banner_enabled` (#223): banner de consentimiento. Default ON; apagarlo solo oculta
     *   el banner — el bloqueo previo de iframes de tercero sigue activo.
     */
    private function webAppearanceSection(): Section
    {
        return Section::make(__('admin.settings.section_web_appearance'))
            ->description(__('admin.settings.section_web_appearance_hint'))
            ->columns(2)
            ->schema([
                ColorPicker::make('theme.brand')
                    ->label(__('admin.settings.theme_brand'))
                    ->helperText(__('admin.settings.theme_brand_hint'))
                    // Hex #RRGGBB estricto, o vacío (vacío → `ThemeSettings` usa el color por
                    // defecto). NO required: el helper garantiza siempre un color válido, así que
                    // dejarlo en blanco no rompe ninguna superficie.
                    ->regex('/^$|^#[0-9a-fA-F]{6}$/'),
                // Acento SECUNDARIO de marca (`DECISIONES #138`): las decoraciones que acompañan al
                // primario. Antes valía el amarillo de una zona del primer cliente, quemado en el CSS.
                ColorPicker::make('theme.brand_secondary')
                    ->label(__('admin.settings.theme_brand_secondary'))
                    ->helperText(__('admin.settings.theme_brand_secondary_hint'))
                    ->regex('/^$|^#[0-9a-fA-F]{6}$/'),
                // Color de ACCIÓN (`#209`, el quinto mecanismo del tema): el relleno del botón que
                // hace avanzar la compra —reservar, comprar, enviar—. NO es el color de marca: la
                // marca tiñe acentos y decoración y puede repetirse por zona; éste es un rol y hay
                // uno por pantalla.
                // ⚠️ **Vacío es una respuesta, no una falta.** Sin color de acción el botón sigue a
                // la superficie —oscuro sobre claro, claro sobre oscuro—, que es lo que el producto
                // hace desde siempre; ponerlo lo fija igual en los dos fondos. Por eso no hay
                // default ni `required`.
                ColorPicker::make('theme.action')
                    ->label(__('admin.settings.theme_action'))
                    ->helperText(__('admin.settings.theme_action_hint'))
                    ->regex('/^$|^#[0-9a-fA-F]{6}$/'),
                TextInput::make('seo.og_image')
                    ->label(__('admin.settings.seo_og_image'))
                    ->helperText(__('admin.settings.seo_og_image_hint'))
                    ->url()
                    ->maxLength(255),
                // «Ver el parque» en la invitación de una fiesta (`fiesta-sistema-nuevo.md` F1c, `#743`): el vídeo de
                // portada de la instalación y su foto. Una ruta bajo `public/` (como la sirve la landing) o una URL.
                TextInput::make('party.park_video')
                    ->label(__('admin.settings.party_park_video'))
                    ->helperText(__('admin.settings.party_park_video_hint'))
                    ->maxLength(255),
                TextInput::make('party.park_video_poster')
                    ->label(__('admin.settings.party_park_video_poster'))
                    ->helperText(__('admin.settings.party_park_video_poster_hint'))
                    ->maxLength(255),
                TextInput::make('catalog.search_min_items')
                    ->label(__('admin.settings.catalog_search_min_items'))
                    ->helperText(__('admin.settings.catalog_search_min_items_hint'))
                    ->integer()
                    ->minValue(CatalogSettings::SEARCH_MIN_ITEMS_MIN)
                    ->maxValue(CatalogSettings::SEARCH_MIN_ITEMS_MAX)
                    ->placeholder((string) CatalogSettings::SEARCH_MIN_ITEMS_DEFAULT),
                // `#239` — el aviso de «Casi llena» del paso de hora. Vive junto al umbral del
                // buscador porque es lo mismo: un número que decide qué ENSEÑA el cajón, no qué
                // vende. `0` apaga el aviso, así que NO lleva `required` ni default en el formulario:
                // el helper garantiza siempre un valor válido y dejarlo en blanco es legítimo.
                TextInput::make('booking.low_availability_max')
                    ->label(__('admin.settings.low_availability_max'))
                    ->helperText(__('admin.settings.low_availability_max_hint'))
                    ->integer()
                    ->minValue(AvailabilitySettings::LOW_MAX_MIN)
                    ->maxValue(AvailabilitySettings::LOW_MAX_MAX)
                    ->placeholder((string) AvailabilitySettings::LOW_MAX_DEFAULT),
                Toggle::make('cookies.banner_enabled')
                    ->label(__('admin.settings.cookies_banner_enabled'))
                    ->helperText(__('admin.settings.cookies_banner_enabled_hint')),
                // La CARCASA de la compra (`DECISIONES #682`): con la isla, la compra se abre en ella y la cuenta
                // sigue en el lateral hasta la T5. Es una elección de la instalación, y la ayuda dice su condición:
                // la isla se viste con las hojas de las páginas nuevas.
                Select::make(ShellSettings::KEY)
                    ->label(__('admin.settings.shell'))
                    ->helperText(__('admin.settings.shell_hint'))
                    ->options([
                        ShellSettings::CAJON => __('admin.settings.shell_options.cajon'),
                        ShellSettings::ISLA => __('admin.settings.shell_options.isla'),
                    ])
                    ->required()
                    ->selectablePlaceholder(false),
            ]);
    }

    /**
     * Ventas (técnico): retención de plaza durante el pago y horizonte de compra. Colapsada por
     * defecto — un valor fuera de rango se rechaza al guardar (defensa en profundidad). El IVA por
     * defecto se editó aquí históricamente, pero se movió a «Datos fiscales» (es informativo y un
     * no-técnico lo busca con la facturación).
     */
    private function salesSection(): Section
    {
        return Section::make(__('admin.settings.section_sales'))
            ->description(__('admin.settings.section_sales_hint'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                TextInput::make('sales.hold_minutes')
                    ->label(__('admin.settings.hold_minutes'))
                    ->helperText(__('admin.settings.hold_minutes_hint'))
                    ->integer()
                    ->minValue(PaymentSettings::HOLD_MINUTES_MIN)
                    ->maxValue(PaymentSettings::HOLD_MINUTES_MAX)
                    ->required(),
                TextInput::make('sales.purchase_horizon_months')
                    ->label(__('admin.settings.purchase_horizon_months'))
                    ->helperText(__('admin.settings.purchase_horizon_months_hint'))
                    ->integer()
                    ->minValue(PaymentSettings::PURCHASE_HORIZON_MONTHS_MIN)
                    ->maxValue(PaymentSettings::PURCHASE_HORIZON_MONTHS_MAX)
                    ->required(),
                TextInput::make('sales.order_prefix')
                    ->label(__('admin.settings.order_prefix'))
                    ->helperText(__('admin.settings.order_prefix_hint'))
                    ->maxLength(PaymentSettings::ORDER_PREFIX_MAX_LENGTH)
                    ->regex('/^[A-Za-z0-9-]+$/')
                    ->placeholder(PaymentSettings::ORDER_PREFIX_DEFAULT),
                TextInput::make('incidents.alert_email')
                    ->label(__('admin.settings.incidents_alert_email'))
                    ->helperText(__('admin.settings.incidents_alert_email_hint'))
                    ->email()
                    ->maxLength(160)
                    ->columnSpanFull(),
            ]);
    }

    /** Puerta (técnico): freno anti-abuso de validaciones, modo y conservación del waiver, tope de menores a cargo. Colapsada. */
    private function doorSection(): Section
    {
        return Section::make(__('admin.settings.section_door'))
            ->description(__('admin.settings.section_door_hint'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                TextInput::make('puerta.validate_rate_limit_per_minute')
                    ->label(__('admin.settings.puerta_rate_limit'))
                    ->helperText(__('admin.settings.puerta_rate_limit_hint'))
                    ->integer()
                    ->minValue(PuertaSettings::VALIDATE_RATE_LIMIT_MIN)
                    ->maxValue(PuertaSettings::VALIDATE_RATE_LIMIT_MAX)
                    ->required(),
                // Fase 6 · subsistema A (`specs/identidad-qr-puerta.md` §4.6, §4.8, §9.2 A·9): los tres
                // ajustes de la FICHA de puerta. Vacío = por defecto (`PuertaSettings`).
                TextInput::make('puerta.lookup_rate_limit_per_hour')
                    ->label(__('admin.settings.puerta_lookup_rate_limit'))
                    ->helperText(__('admin.settings.puerta_lookup_rate_limit_hint'))
                    ->integer()
                    ->minValue(PuertaSettings::LOOKUP_RATE_LIMIT_MIN)
                    ->maxValue(PuertaSettings::LOOKUP_RATE_LIMIT_MAX),
                TextInput::make('puerta.profile_ttl_minutes')
                    ->label(__('admin.settings.puerta_profile_ttl'))
                    ->helperText(__('admin.settings.puerta_profile_ttl_hint'))
                    ->integer()
                    ->minValue(PuertaSettings::PROFILE_TTL_MIN)
                    ->maxValue(PuertaSettings::PROFILE_TTL_MAX),
                TextInput::make('puerta.window_days')
                    ->label(__('admin.settings.puerta_window_days'))
                    ->helperText(__('admin.settings.puerta_window_days_hint'))
                    ->integer()
                    ->minValue(PuertaSettings::WINDOW_DAYS_MIN)
                    ->maxValue(PuertaSettings::WINDOW_DAYS_MAX),
                // T3 de las encuestas (`specs/encuestas.md` §4.3): la encuesta por correo nace de la visita
                // acreditada aquí, y este es el plazo entre dos correos a la misma persona. Vacío = 30.
                TextInput::make(SurveySettings::KEY_COOLDOWN_DAYS)
                    ->label(__('admin.settings.surveys_cooldown'))
                    ->helperText(__('admin.settings.surveys_cooldown_hint'))
                    ->integer()
                    ->minValue(SurveySettings::COOLDOWN_DAYS_MIN)
                    ->maxValue(SurveySettings::COOLDOWN_DAYS_MAX),
                // Fase 6 · waiver: los TRES modos (`DECISIONES #142`) en lugar del toggle de #216.
                Select::make(WaiverSettings::KEY_MODE)
                    ->label(__('admin.waiver.settings_mode'))
                    ->helperText(__('admin.waiver.settings_mode_hint'))
                    ->options([
                        WaiverSettings::MODE_EXTERNAL => __('admin.waiver.modes.externo'),
                        WaiverSettings::MODE_INTERNAL => __('admin.waiver.modes.interno'),
                        WaiverSettings::MODE_OFF => __('admin.waiver.modes.desactivado'),
                    ])
                    ->native(false)
                    ->required(),
                TextInput::make(WaiverSettings::KEY_RETENTION_MONTHS)
                    ->label(__('admin.waiver.settings_retention'))
                    ->helperText(__('admin.waiver.settings_retention_hint'))
                    ->integer()
                    ->minValue(WaiverSettings::RETENTION_MIN)
                    ->maxValue(WaiverSettings::RETENTION_MAX),
                // `DECISIONES #197`: la firma de un MENOR se conserva N meses tras su 18.º cumpleaños.
                TextInput::make(WaiverSettings::KEY_DEPENDENT_RETENTION_MONTHS)
                    ->label(__('admin.waiver.settings_dependent_retention'))
                    ->helperText(__('admin.waiver.settings_dependent_retention_hint'))
                    ->integer()
                    ->minValue(WaiverSettings::RETENTION_MIN)
                    ->maxValue(WaiverSettings::RETENTION_MAX),
                // Fase 6 · menores a cargo (`DECISIONES #142`): tope por cuenta, de servidor. Vacío = 20.
                TextInput::make(DependentSettings::KEY_MAX_PER_ACCOUNT)
                    ->label(__('admin.dependents.settings_max'))
                    ->helperText(__('admin.dependents.settings_max_hint'))
                    ->integer()
                    ->minValue(DependentSettings::MAX_PER_ACCOUNT_MIN)
                    ->maxValue(DependentSettings::MAX_PER_ACCOUNT_MAX),
            ]);
    }

    /** Aforo de cumpleaños (técnico): topes por franja y bloqueo de cupo por montaje. Colapsada. */
    private function capacitySection(): Section
    {
        return Section::make(__('admin.settings.section_capacity'))
            ->description(__('admin.settings.section_capacity_hint'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                TextInput::make('packs.max_per_slot')
                    ->label(__('admin.settings.packs_max_per_slot'))
                    ->helperText(__('admin.settings.packs_cap_hint'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(1000),
                TextInput::make('packs.max_guests_per_slot')
                    ->label(__('admin.settings.packs_max_guests_per_slot'))
                    ->helperText(__('admin.settings.packs_cap_hint'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100000),
                Toggle::make('packs.prep_blocks_cupo')
                    ->label(__('admin.settings.packs_prep_blocks_cupo'))
                    ->helperText(__('admin.settings.packs_prep_blocks_cupo_hint')),
                // El plazo para que el CLIENTE cambie sus invitados desde el post-form
                // (`specs/invitados-en-post-form.md` §4.5, `#444`). ⚠️ Va aquí y no en el enganche
                // porque esto no es un complemento: es una regla de la casa —lo que el parte de
                // celebración necesita saber con antelación—. Vacío = el suelo del producto (24 h).
                TextInput::make(GuestCountPolicy::SETTING_CUTOFF_HOURS)
                    ->label(__('admin.settings.packs_guest_count_cutoff_hours'))
                    ->helperText(__('admin.settings.packs_guest_count_cutoff_hours_hint'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(2160),
            ]);
    }

    private function redsysSection(): Section
    {
        return Section::make(__('admin.settings.section_redsys'))
            ->description(__('admin.settings.section_redsys_hint'))
            ->collapsible()
            ->collapsed()
            ->columns(2)
            ->schema([
                Select::make('redsys_environment')
                    ->label(__('admin.settings.redsys_environment'))
                    ->helperText(__('admin.settings.redsys_environment_hint'))
                    ->options([
                        'test' => __('admin.settings.redsys_env_test'),
                        'live' => __('admin.settings.redsys_env_live'),
                    ])
                    ->required(),
                TextInput::make('redsys_currency')
                    ->label(__('admin.settings.redsys_currency'))
                    ->helperText(__('admin.settings.redsys_currency_hint'))
                    // ISO-4217 numérico de 3 dígitos (978 = EUR); el helper PaymentSettings cae a 978.
                    ->regex('/^\d{3}$/')
                    ->required(),
                TextInput::make('redsys_merchant_code')
                    ->label(__('admin.settings.redsys_merchant_code'))
                    ->helperText(__('admin.settings.redsys_merchant_code_hint'))
                    ->maxLength(20),
                TextInput::make('redsys_terminal')
                    ->label(__('admin.settings.redsys_terminal'))
                    ->maxLength(10),
                TextInput::make('redsys_merchant_name')
                    ->label(__('admin.settings.redsys_merchant_name'))
                    ->maxLength(120),
                TextInput::make('redsys_merchant_url')
                    ->label(__('admin.settings.redsys_merchant_url'))
                    ->helperText(__('admin.settings.redsys_merchant_url_hint'))
                    ->url()
                    ->maxLength(255),
            ]);
    }
}
