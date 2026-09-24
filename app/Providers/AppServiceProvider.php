<?php

namespace App\Providers;

use App\Domain\Booking\Contracts\ReservationPlacesTaken;
use App\Domain\Booking\Contracts\SignedInvitationReplies;
use App\Domain\Booking\Models\InvitationReply;
use App\Domain\Booking\Models\OpeningHour;
use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderAdjustment;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Domain\Booking\Models\Price;
use App\Domain\Booking\Models\PriceTier;
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
use App\Domain\Content\Contracts\SocialProof;
use App\Domain\Content\Models\Attraction;
use App\Domain\Content\Models\BarImage;
use App\Domain\Content\Models\Faq;
use App\Domain\Content\Models\GoogleBusinessReview;
use App\Domain\Content\Models\GoogleBusinessReviewSummary;
use App\Domain\Content\Models\GoogleBusinessReviewSuppression;
use App\Domain\Content\Models\LandingService;
use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Testimonial;
use App\Domain\Content\Models\VenueRule;
use App\Domain\Content\Services\BusinessProfileSocialProof;
use App\Domain\Content\Services\CmsSocialProof;
use App\Domain\Content\Services\FallingBackSocialProof;
use App\Domain\Content\Services\GoogleSocialProof;
use App\Domain\Content\Services\HeroStatus;
use App\Domain\Content\Services\MapsEmbed;
use App\Domain\Content\Services\ScheduleDisplay;
use App\Domain\Content\Services\SocialEmbed;
use App\Domain\Identity\Listeners\RecordLoginFact;
use App\Domain\Identity\Listeners\SignPendingWaiverOnVerification;
use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\CookieConsentLog;
use App\Domain\Identity\Models\CustomerCard;
use App\Domain\Identity\Models\CustomerVisit;
use App\Domain\Identity\Models\Dependent;
use App\Domain\Identity\Models\DependentAssignment;
use App\Domain\Identity\Models\GuardianAuthorization;
use App\Domain\Identity\Models\LegalDocumentVersion;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\UserIdentity;
use App\Domain\Identity\Models\WaiverSignature;
use App\Domain\Identity\Services\CookieConsent;
use App\Domain\Identity\Services\CookieConsentLedger;
use App\Domain\Identity\Services\CustomerAccountContext;
use App\Domain\Identity\Services\GuardianPlaces;
use App\Domain\Payments\Models\Payment;
use App\Domain\Payments\Models\PaymentRefund;
use App\Domain\Payments\Services\PaymentSettings;
use App\Domain\Platform\Contracts\ConsentLedger;
use App\Domain\Platform\Listeners\ApplyBusinessSender;
use App\Domain\Platform\Listeners\RecordEmailSent;
use App\Domain\Platform\Models\AnalyticsEvent;
use App\Domain\Platform\Models\AnalyticsSession;
use App\Domain\Platform\Models\AuditLog;
use App\Domain\Platform\Models\GoogleBusinessConnection;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Analytics\AttributionContext;
use App\Domain\Platform\Services\Money;
use App\Domain\Platform\Services\QrLogo;
use App\Http\Instancia\InstanceViews;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Notifications\Events\NotificationSent;
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

    /** Clave de memoización de `ScheduleDisplay` en `request()->attributes` (`#662`). */
    private const SCHEDULE_DISPLAY_KEY = 'app.schedule_display';

    public function register(): void
    {
        // Contexto de cuenta del cliente (#221): singleton para memoizar por petición — el nav
        // (puntito de aviso) y el sidebar lo piden por separado y comparten una única consulta.
        $this->app->singleton(CustomerAccountContext::class);

        // El contexto de atribución del libro de eventos (`specs/analitica.md` §4.1, `#678`): UNO por
        // petición (`scoped`), lo rellena `ResolveVisitor` y lo copia el `creating` de `Order` en el
        // sello. El estado inicial es «sistema»: consola, cola y verificadores sellan sin navegante.
        $this->app->scoped(AttributionContext::class);

        /*
         * **Horario para mostrar: memoizado en la PETICIÓN, no en el contenedor** (`#662`).
         *
         * El servicio memoiza el horario semanal, las temporadas y la fecha especial de hoy **por
         * instancia**, y desde `#662` lo piden DOS sitios de la misma petición por separado: el
         * controlador, para la entradilla de «Visítanos», y `<x-site.visit>`, para la tabla y las
         * fechas próximas. Medido en la portada: **compartiendo instancia, 54 consultas; sin
         * compartirla, 58**. Antes lo compartía la VISTA, que resolvía el servicio y pasaba el objeto
         * al componente — y eso es justo lo que `#662` retira, porque esa vista se muda al paquete de
         * una instalación (`paquete-de-instancia.md` §4.7).
         *
         * ❗❗ **Y por eso NO es un `singleton`, que fue el primer intento y lo tumbaron cuatro casos
         * de `VisitSectionTest`.** Ni el Kernel HTTP ni el `TestCase` llaman a
         * `forgetScopedInstances()` —medido—, así que `singleton()` y `scoped()` se comportan igual:
         * la instancia **sobrevive entre las peticiones de una misma prueba**, y un caso que escriba
         * una `SpecialDate` entre dos `get()` lee el memoizado de la anterior. En producción no se
         * vería nunca (una petición, un contenedor), que es la peor clase de defecto.
         *
         * ▶ La salida es la que esta casa ya usa para el payload del composer
         * ({@see SHARED_VIEW_DATA_KEY}): memoizar en `request()->attributes`. El Kernel sustituye la
         * `request` del contenedor en cada petición, así que el límite de la memoización **es el
         * límite de la petición**, también en una prueba.
         */
        $this->app->bind(ScheduleDisplay::class, function ($app): ScheduleDisplay {
            $request = $app['request'];
            $memo = $request->attributes->get(self::SCHEDULE_DISPLAY_KEY);

            if ($memo instanceof ScheduleDisplay) {
                return $memo;
            }

            // ⚠️ `build()` y no `make()`: `make()` volvería a entrar por este mismo binding.
            $memo = $app->build(ScheduleDisplay::class);
            $request->attributes->set(self::SCHEDULE_DISPLAY_KEY, $memo);

            return $memo;
        });

        // ⚠️⚠️ **El binding vive AQUÍ y no en `BookingServiceProvider`, y es una consecuencia de la
        // frontera, no una preferencia** (`specs/invitados-en-post-form.md` §4.4, `#444`): Booking
        // pregunta cuántas plazas de una reserva ya tienen dueño —menores a cargo asignados y
        // justificantes firmados— y **Booking no puede mirar a Identity**
        // (`ModuleBoundariesTest`), así que su propio proveedor tampoco puede nombrar al
        // implementador. La capa de ENTREGA es el composition root y sí puede ver a los dos.
        $this->app->bind(ReservationPlacesTaken::class, GuardianPlaces::class);

        // Y por la MISMA frontera y con el mismo implementador (`#704`, T5·5): el recibo de la
        // invitación necesita saber si esa respuesta ya tiene justificante para no ofrecerle firmar a
        // quien acaba de firmar. La atadura la guarda Identity (`#576`) y el recibo es de Booking.
        $this->app->bind(SignedInvitationReplies::class, GuardianPlaces::class);

        // **El consentimiento vivo de un visitante** (`specs/analitica.md` §4.3, T3b·2): la cola relee la
        // última decisión de cookies antes de comunicar una compra a un anunciante. La prueba es de Identity
        // (`cookie_consent_logs`) y quien pregunta es Platform, que no ve a nadie: el contrato es de Platform,
        // lo implementa Identity y la atadura vive aquí, en el composition root.
        $this->app->bind(ConsentLedger::class, CookieConsentLedger::class);

        // **La prueba social de la landing** (`#490`; reescrito en `#732`, T2·6 de
        // `specs/google-business-profile.md` §4.3·9).
        //
        // ❗❗❗ **EL ORDEN DE ESTA LISTA ES LA POLÍTICA, y por eso vive aquí y no en el decorador.**
        // La cascada solo recorre; quién va delante lo decide el composition root:
        //
        //   1. **La ficha de Google** (Business Profile). Se sirve entera desde nuestro servidor
        //      —imagen incluida—, así que **no necesita consentimiento** y no le pide nada a Google.
        //   2. **Places**, lo que hay hoy en producción. Sigue enlazado **a propósito** (`[owner]`,
        //      21-09): retirarlo se valorará más adelante, y mientras tanto esto es lo que evita una
        //      REGRESIÓN — sin conexión con la ficha, la 1 responde vacío y la portada seguiría
        //      enseñando lo mismo que hoy en vez de caer a las opiniones propias.
        //   3. **Las opiniones propias**, que es lo que ve quien no acepta cookies de terceros.
        //
        // ⚠️⚠️ **Mientras la 2 siga en la lista, `img-src` NO puede dejar de nombrar a Google**
        // (§4.3·12): sus fotos de autor las carga el visitante desde `lh3.googleusercontent.com`. El
        // cambio de CSP va con la retirada de Places, no con esta tanda.
        //
        // ⚠️⚠️ **`scoped` y no `bind`** (§4.3·9): la portada le pregunta a esto por las opiniones,
        // por la selección y por el permiso. Con `bind` serían tres objetos y tres recorridos de la
        // cascada —con sus consultas— para pintar una sección (`PERF-02`). `scoped` dura la petición
        // y se reinicia entre peticiones, que es justo lo que hace falta: el consentimiento y las
        // reseñas cambian entre visitantes.
        $this->app->scoped(SocialProof::class, function (): SocialProof {
            return new FallingBackSocialProof(
                [
                    $this->app->make(BusinessProfileSocialProof::class),
                    $this->app->make(GoogleSocialProof::class),
                    $this->app->make(CmsSocialProof::class),
                ],
                // ⚠️⚠️ **El consentimiento se lee AQUÍ y no dentro del decorador**: `CookieConsent`
                // vive en Identity y **Content no puede mirar a Identity** (`ModuleBoundariesTest`).
                // La capa de ENTREGA es el composition root y sí ve a los dos — la misma salida que
                // `ReservationPlacesTaken` en `#444`.
                // ⚠️ Va como cierre para que se evalúe cuando hace falta y no al construir.
                static fn (): bool => (bool) (CookieConsent::state(request())['maps'] ?? false),
            );
        });

        // El icono que va DENTRO del QR del carné (`identidad-qr-puerta.md` §9.7 C·3): singleton
        // para que el rasterizado del SVG de la instalación se haga UNA vez por petición. El memo
        // vive en la instancia, no en una estática, justo para que el contenedor nuevo de cada test
        // lo reinicie solo (`SUITE-02`: un memo estático haría depender del ORDEN de los tests).
        $this->app->singleton(QrLogo::class);

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
        // **Las vistas de la INSTANCIA** (F5 · T2a, `specs/paquete-de-instancia.md` §4.1, `#647`): si
        // hay paquete configurado, su carpeta `web/` queda registrada como el namespace `instancia`.
        // ⚠️⚠️ Va aquí, en el arranque, y la ruta sale SOLO de configuración (`SEC-12`): es una ruta
        // desde la que el servidor ejecuta código, porque Blade compila a PHP. Sin paquete no se
        // registra nada y el producto sirve su anfitrión mínimo — que es el estado normal de una
        // instalación recién montada, no un error.
        $this->app->make(InstanceViews::class)->registrar();

        // Fase 6 · waiver (`#179`): la aceptación pendiente del alta se firma al VERIFICAR el correo. El
        // listener vive en Identity (el arch-test no deja dominio fuera de `app/Domain`) y se registra aquí.
        Event::listen(Verified::class, SignPendingWaiverOnVerification::class);

        // El libro de eventos (`specs/analitica.md` §4.1, `#678`): `user_logged_in` desde el `Login` del
        // framework, que disparan todas las puertas (contraseña, Google, verificación). El listener vive en
        // Identity por el mismo motivo que el de arriba.
        Event::listen(Login::class, RecordLoginFact::class);

        // Y `email_sent` desde el `NotificationSent` del framework (T1c): cada correo al cliente que sale, con
        // la misma clave que llevan sus enlaces (`EmailUtm`), también cuando lo manda el worker de la cola.
        Event::listen(NotificationSent::class, RecordEmailSent::class);

        // El REMITENTE de todo correo sale del PANEL y no del `.env` (`#500`, T2 de
        // `specs/correos-desde-canvas.md`). Va como listener y no como `Mail::alwaysFrom()` para no
        // consultar `settings` en peticiones que no envían nada: `MessageSending` solo se dispara
        // cuando hay un correo de verdad, y también desde el worker de la cola.
        Event::listen(MessageSending::class, ApplyBusinessSender::class);

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
            // El libro de eventos (`#678`): sin relaciones polimórficas hoy, pero todo modelo lleva alias.
            'analytics_session' => AnalyticsSession::class,
            'analytics_event' => AnalyticsEvent::class,
            // `#536`: las imágenes de `/bar` (la carta y la foto del local). Todo modelo necesita
            // alias de morfo — lo exige `MorphMapTest` y es lo que hace que el AUDIT guarde `bar_image`
            // y no el nombre de clase, que se rompe al mover el fichero de sitio.
            'bar_image' => BarImage::class,
            'consent' => Consent::class,
            'cookie_consent_log' => CookieConsentLog::class,
            'customer_card' => CustomerCard::class,
            'customer_visit' => CustomerVisit::class,
            'dependent' => Dependent::class,
            'dependent_assignment' => DependentAssignment::class,
            'faq' => Faq::class,
            // La conexión con la ficha de Google (`#524`). Alias como todo modelo nuevo: lo exige
            // `MorphMapTest`, y es lo que hace que el audit de «conectar»/«desconectar» guarde
            // `google_business_connection` y no un nombre de clase que se rompe al mover el fichero.
            'google_business_connection' => GoogleBusinessConnection::class,
            // Las reseñas de esa ficha y su resumen (`#727`). ⚠️ Que tengan alias NO las hace
            // cruzables con nada: §4.3·11 prohíbe relacionarlas con clientes o pedidos, y el alias
            // existe porque `MorphMapTest` lo exige de TODO modelo, también de los que no se
            // relacionan con ninguno.
            'google_business_review' => GoogleBusinessReview::class,
            'google_business_review_summary' => GoogleBusinessReviewSummary::class,
            // La lista de supresión (`#731`). ⚠️ Su alias SÍ se usa: es el `target` del rastro de
            // «Ocultar» en `audit_logs`, y por eso tiene que ser estable — el registro sobrevive a
            // la reseña que lo causó y a cualquier refactor que mueva la clase.
            'google_business_review_suppression' => GoogleBusinessReviewSuppression::class,
            'guardian_authorization' => GuardianAuthorization::class,
            // La INVITACIÓN DIGITAL y lo que contesta un padre (`#573`). Alias como todo modelo
            // nuevo: lo exige `MorphMapTest`, y es lo que hace que el audit guarde `invitation_reply`
            // en vez de un nombre de clase que se rompe al mover el fichero de sitio.
            'invitation_reply' => InvitationReply::class,
            'landing_service' => LandingService::class,
            'legal_document_version' => LegalDocumentVersion::class,
            // ⚠️ Aquí estaba `'offer'`, y se va con su modelo (`#668`). **Las filas de auditoría que
            // lo lleven siguen legibles**: `AuditLogTable` trata `target_type` como TEXTO
            // (`class_basename`) y nunca resuelve la clase — comprobado antes de retirarlo, porque
            // un alias sin modelo en una tabla que el panel sí resolviera sería un 500 en auditoría.
            'opening_hour' => OpeningHour::class,
            'order' => Order::class,
            'order_adjustment' => OrderAdjustment::class,
            'order_item' => OrderItem::class,
            'page' => Page::class,
            'park_rule' => VenueRule::class,
            'party_invitation' => PartyInvitation::class,
            'payment' => Payment::class,
            'payment_refund' => PaymentRefund::class,
            'permission' => Permission::class,
            'price' => Price::class,
            'price_tier' => PriceTier::class,
            'product_addon' => ProductAddon::class,
            'rate_type' => RateType::class,
            'role' => Role::class,
            'room' => Room::class,
            'season' => Season::class,
            'setting' => Setting::class,
            'slot' => Slot::class,
            'slot_template' => SlotTemplate::class,
            'special_date' => SpecialDate::class,
            // ⚠️ Lo exige `AuditLogger`: el alta, la edición y el borrado de una opinión escriben
            // en `audit_logs.target_type`, así que este modelo SÍ se morfa (`#490`).
            'testimonial' => Testimonial::class,
            'ticket' => Ticket::class,
            'ticket_type' => TicketType::class,
            'user' => User::class,
            'user_identity' => UserIdentity::class,
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
        // ⚠️ Aquí se resolvían `navServices` y `navZones`, las dos listas del menú viejo: **dos
        // consultas por petición en las doce vistas**. Se van con su consumidor (`#521`): los
        // destinos del menú son el inventario del canvas y los compone `SiteDestinations` sin BD.
        // ⚠️⚠️ **Aquí viajaba `offers`, y su retirada CRUZA LA FRONTERA** (`#668`, F5 · T3): era una
        // de las siete claves que este composer pone en TODA vista, así que estaba en el CONTRATO DE
        // VISTA de las nueve páginas de una instancia. Por eso `InstanceViews::CONTRATO` sube a 2 y
        // hay que avisar a cada instalación: su landing deja de recibir lo que hoy recibe.
        $data = [
            'cookieConsent' => CookieConsent::state(request()),
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
            // ⚠️ **Sube del `HomeController` al payload compartido en `#230`.** Lo pinta el MENÚ, y
            // el menú vive en las doce vistas: mientras solo lo pasara la home, el bloque de datos
            // del menú salía vacío en once de ellas — y desde que el hero se vació (`#226`) también
            // en la propia home, porque su único consumidor era el chip del hero.
            // ▶ Se calcula UNA vez por petición aquí, y el controlador deja de calcularlo: dos
            // llamadas al mismo servicio en la misma petición serían dos veces sus consultas.
            'heroStatus' => app(HeroStatus::class)->current(),
            'site' => [
                'name' => $get('business.name', config('app.name')),
                // Lanzamiento 2026-09-01: con la compra online CERRADA (`sales.online_enabled=0`) los
                // CTA de compra pasan a `tel:`; el suelo real es `EnsureOnlineSalesEnabled` en la API.
                'sales_online' => PaymentSettings::onlineSalesEnabled(),
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
                // La NOTA DE ACCESO de las zonas (`#587`, `[DECIDIDO owner]`): con quién entran los
                // pequeños y entre qué alturas. Es un dato del parque, así que SIN respaldo del
                // diccionario: vacía, la portada y `/atracciones` no pintan nada.
                'zones_access' => $get('landing.zones_access.'.$locale) ?: null,
                // EL RECUADRO DE LA OFERTA sobre el carril de tarifas (chapuza declarada, `[DECIDIDO
                // owner, 2026-09-18]`; ver `Setting::promoPercent()`): un dato de la instalación por
                // idioma y SIN respaldo del diccionario, como la nota de acceso. Vacío = no se pinta.
                'promo_banner' => $get('promo.banner.'.$locale) ?: null,
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
     * El precio del CTA de reservar («desde 8 €»), en registro de ESCAPARATE (`#589`): sin ceros a la
     * derecha, como lo escribe la sección de tarifas justo debajo (`Money::showcase()`, `#479`). Con dos
     * decimales la primera pantalla decía «8,00 €» y la sección siguiente «8 €» para la misma cifra.
     */
    public static function formatPriceLabel(int $cents): string
    {
        // ⚠️ `#661`: era la SEGUNDA copia de `WritesLandingValues::euros()`, letra por letra, y por eso
        // el CTA escribía «desde 8 €» partible mientras la sección de tarifas de debajo no lo era.
        return Money::showcaseWithSymbol($cents);
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
