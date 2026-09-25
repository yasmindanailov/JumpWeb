<?php

namespace App\Livewire\Admin\Puerta;

use App\Domain\Identity\Exceptions\WaiverDocumentStaleException;
use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Identity\Services\PuertaSettings;
use App\Domain\Identity\Services\WaiverCounterDeclaration;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Models\Survey;
use App\Domain\Platform\Models\SurveyResponse;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\PhoneNormalizer;
use App\Domain\Platform\Services\Surveys\QuestionSchema;
use App\Domain\Platform\Services\Surveys\SurveyResponses;
use Filament\Facades\Filament;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Fase 7.1a — Validar registro en puerta (decisiones #119 y #126) · **Fase 6 · subsistema A — la
 * PANTALLA DE PUERTA** (`docs/specs/identidad-qr-puerta.md` §4.6, §4.8, §9.2 A·5/A·6/A·7; `DECISIONES #208`).
 *
 * Dos capas, y la segunda solo con permiso:
 *
 *  1. **El SEMÁFORO de siempre** (`registrations.validate`): buscar por email o teléfono y devolver
 *     ÚNICAMENTE el estado del waiver —sin nombre, sin email/teléfono completo, sin historial—. Privacy
 *     by design (RGPD #19). Tres estados (`registered_with_waiver` · `registered_no_waiver` ·
 *     `not_registered`), o dos si la comprobación del waiver está desactivada (#216).
 *  2. **La FICHA** (`puerta.profile`, `[DECIDIDO owner]` §4.6): el CARNÉ escaneado por el mismo input
 *     —el lector es un *keyboard wedge*— o la búsqueda tecleada abren `$profile`: nombre del titular,
 *     waiver, reservas de HOY con lo pendiente de cobrar en puerta, la ventana ±N días, los menores a
 *     cargo como **edad y estado de la exención, JAMÁS el nombre**, y el botón «Registrar visita».
 *     La compone `Identity\Services\GateProfile` en una lectura con presupuesto (§4.11).
 *
 * Defensas (las de siempre, más las de §4.6):
 *  - Permiso re-autorizado en CADA petición (`SEC-04`): `mount()` corre una vez.
 *  - **Dos limitadores por empleado** (nunca por IP: comparten red): el de siempre por MINUTO para
 *    toda búsqueda (`puerta.validate_rate_limit_per_minute`), y **otro por HORA solo para la búsqueda
 *    TECLEADA que abre la ficha** (`puerta.lookup_rate_limit_per_hour`): escanear es alto volumen y
 *    legítimo; teclear cincuenta correos en una hora no es atender. El rechazo se audita UNA vez por
 *    ventana (`SEC-05`), y el del tecleo es acción CRÍTICA (aviso al operador).
 *  - Auditoría con hash: `registrations.validated` (el dato tecleado), `puerta.card_scanned` (el token);
 *    y la DIVULGACIÓN aparte: `puerta.profile_viewed` (§4.6·3).
 *  - **La ficha CADUCA EN EL SERVIDOR** (§4.8, A·6): `$profile['expires_at']` se comprueba en cada
 *    método público y en `render()` —`ensureFresh()`—; el velo y el cierre del navegador son Alpine y
 *    NO son la garantía. Recargar o restaurar una pestaña dormida no la resucita.
 *  - Coincidencia EXACTA por email y por teléfono normalizado; una sola URL sin parámetro; el input con
 *    `autocomplete="off"`: sin historial de a quién se ha mirado (el operador sí lo tiene: `audit_logs`).
 */
#[Layout('layouts.puerta')]
class ValidarRegistro extends Component
{
    public const STATUS_REGISTERED_WITH_WAIVER = 'registered_with_waiver';

    public const STATUS_REGISTERED_NO_WAIVER = 'registered_no_waiver';

    /** Estado 2-en-1 cuando la comprobación de waiver está DESACTIVADA (#216): solo «tiene cuenta». */
    public const STATUS_REGISTERED = 'registered';

    public const STATUS_NOT_REGISTERED = 'not_registered';

    public const STATUS_INVALID_INPUT = 'invalid_input';

    public const STATUS_RATE_LIMITED = 'rate_limited';

    /** Subsistema A: el carné escaneado existe pero está rotado o revocado → «busca por email» (§4.5). */
    public const STATUS_CARD_REVOKED = 'card_revoked';

    /** Subsistema A: tiene forma de carné (y pasa el control) pero no está en el sistema. */
    public const STATUS_CARD_UNKNOWN = 'card_unknown';

    /** Subsistema A: el limitador de la búsqueda TECLEADA que abre la ficha (por hora). */
    public const STATUS_LOOKUP_LIMITED = 'lookup_limited';

    public const INPUT_EMAIL = 'email';

    public const INPUT_PHONE = 'phone';

    public const INPUT_CARD = 'card';

    public string $input = '';

    /**
     * Última respuesta a renderizar (el semáforo). `null` antes de la primera búsqueda.
     * Forma: ['status' => string, 'query' => ?string, 'date' => ?string, 'outdated' => ?bool].
     *
     * @var array<string,mixed>|null
     */
    public ?array $result = null;

    /**
     * La FICHA (subsistema A), solo con `puerta.profile`: `GateProfileData::toArray()` más `via`
     * (`card` | `lookup`), `expires_at` (unix, servidor) y `ttl_minutes`. ⚠️ Viaja al navegador en el
     * snapshot de Livewire: por eso no puede llevar nada que §4.6 prohíba, y el DTO no tiene campo para
     * el nombre de un menor.
     *
     * @var array<string,mixed>|null
     */
    public ?array $profile = null;

    /**
     * ⚠️⚠️ **EL SUJETO Y EL RELOJ DE LA FICHA, BLOQUEADOS** (2026-08-28, revisión de `#217`).
     *
     * `$profile` es estado PÚBLICO de Livewire: viaja al navegador en el snapshot y **el navegador
     * puede devolverlo cambiado**. Hasta hoy, «a quién se le registra la visita» (`user_id`) y
     * «cuándo caduca la ficha» (`expires_at`) se leían de ahí, así que un cliente manipulado podía
     * acreditar la visita de OTRA persona —de donde saldrán los JumpPoints (§8.3)— o resucitar una
     * ficha ya vencida, que es justamente lo que `SEC-04` aplicado al tiempo existe para impedir.
     *
     * `#[Locked]` hace que Livewire **rechace cualquier cambio que venga del cliente**: solo las
     * escribe el servidor, en `openProfile()` y en `clear()`. Se quedan además dentro de `$profile`
     * para que la vista siga leyendo un solo objeto, pero **nadie decide nada con esa copia**.
     */
    #[Locked]
    public ?int $profileUserId = null;

    #[Locked]
    public ?int $profileExpiresAt = null;

    /**
     * **LA ENCUESTA INTERNA** (`specs/encuestas.md` §4.2, T2): la oferta y su estado dentro de ESTA ficha.
     * Forma: `['key', 'name', 'intro', 'count', 'state', 'questions']` con `state` ∈ `offer` (la tarjeta con
     * «Preguntar» y «No preguntar») · `open` (el formulario) · `answered` · `declined`. Las preguntas viajan
     * con sus rótulos en el idioma del PANEL: las lee el operador, que es quien pregunta y marca.
     *
     * ⚠️ Viaja en el snapshot como `$profile`: lleva la encuesta (dato del panel) y nunca respuestas de nadie.
     * Vive y muere con la ficha: `search()`, `clear()` y la caducidad la vacían.
     *
     * @var array<string,mixed>|null
     */
    public ?array $survey = null;

    /**
     * Lo que el operador marca con el dedo, `wire:model` DIFERIDO (cero idas y vueltas hasta «Guardar»). Llega
     * como cadenas y listas; el servidor lo tipa y lo valida contra las preguntas al guardar (`SEC-04`: el
     * navegador no decide qué vale).
     *
     * @var array<string,mixed>
     */
    public array $surveyAnswers = [];

    /** La encuesta ofrecida, bloqueada como el sujeto: el navegador no elige a qué encuesta responde. */
    #[Locked]
    public ?int $surveyId = null;

    /**
     * Esta página vive FUERA del shell de Filament (decisión #119) y por eso no pasa por el middleware
     * `SetUpPanel` del panel. Consecuencia MEDIDA en navegador (§9.7 C·5): sin panel «actual» y
     * booteado, `@filamentStyles` no emite `--gray-*`, `--primary-*`, `--success-*`… y el bundle del
     * panel —que las USA— pintaba texto negro puro donde pedía gris y un borde negro sólido donde
     * pedía un `ring` al 5 %.
     *
     * Va en `boot()` y no en `mount()` a propósito: `mount()` corre UNA vez, y los componentes de
     * Filament de la vista (`callout`, `section`, `badge`, `button`) resuelven color e iconos contra
     * el panel **en cada** render — también en las peticiones Livewire posteriores, que no vuelven a
     * pasar por el layout. `bootCurrentPanel()` se autoprotege de correr dos veces por petición.
     */
    public function boot(): void
    {
        Filament::setCurrentPanel(Filament::getDefaultPanel());
        Filament::bootCurrentPanel();
    }

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    /**
     * Re-valida el permiso en CADA petición Livewire (auditoría Fase 1, A8). `mount()` solo corre una
     * vez; sin esto, un staff degradado o con el permiso revocado EN VIVO seguiría invocando
     * `search()`/`clear()` con la pestaña abierta.
     */
    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->hasPermission('registrations.validate') ?? false, 403);
    }

    private function canViewProfile(): bool
    {
        return Auth::user()?->hasPermission('puerta.profile') ?? false;
    }

    public function search(): void
    {
        $this->authorizeAccess();
        $this->ensureFresh();
        $this->profile = null;
        $this->profileUserId = null;
        $this->profileExpiresAt = null;
        $this->forgetSurvey();

        $raw = trim($this->input);
        $type = self::detectInputType($raw);

        if ($type === null) {
            // En invalid_input devolvemos el `query` SOLO si hay algo escrito —
            // si está vacío, el mensaje genérico "Introduce un email…" basta.
            //
            // ⚠️ Y aquí el campo NO se vacía, al revés que abajo: si lo escrito está mal, el
            // empleado tiene que poder CORREGIRLO en vez de teclearlo entero otra vez.
            $this->result = ['status' => self::STATUS_INVALID_INPUT, 'query' => $raw];

            return;
        }

        // ▶ El campo se vacía y recupera el foco EN CUANTO la entrada es válida (`#234`,
        // `[DECIDIDO owner]`). En un mostrador, entre dos clientes no debe haber ningún gesto:
        // el lector de QR es un teclado, así que con el cursor ya dentro el siguiente escaneo
        // entra solo y su Enter dispara la búsqueda. Antes había que borrar lo anterior a mano.
        //
        // Se hace ANTES de los limitadores a propósito: `$raw` ya está capturado, y si la
        // búsqueda se rechaza por límite el empleado tampoco va a reintentarla con ese texto.
        //
        // El eco de a quién corresponde la respuesta NO se pierde al vaciar: viaja en
        // `$result['query']`, que es lo que pinta el callout.
        $this->input = '';
        $this->dispatch('gate-input-cleared');

        $userId = Auth::id();

        // Limitador A (decisión #126): TODA búsqueda, por minuto y por empleado. Es el freno
        // anti-enumeración si una sesión staff se compromete; el rechazo se audita una vez por ventana.
        if ($this->limited("puerta:validate:user:{$userId}", "puerta:validate:audit:user:{$userId}", PuertaSettings::validateRateLimit(), 60, 'registrations.validate_rate_limited')) {
            $this->result = ['status' => self::STATUS_RATE_LIMITED];

            return;
        }

        if ($type === self::INPUT_CARD) {
            $this->searchByCard($raw);

            return;
        }

        // Limitador B (§4.6·1): solo la búsqueda TECLEADA que va a abrir la FICHA, por hora. Mucho más
        // bajo que el A: teclear un correo debería ser raro («me he dejado el móvil»). Crítica.
        if ($this->canViewProfile() && $this->limited("puerta:lookup:user:{$userId}", "puerta:lookup:audit:user:{$userId}", PuertaSettings::lookupRateLimitPerHour(), 3600, 'puerta.lookup_rate_limited')) {
            $this->result = ['status' => self::STATUS_LOOKUP_LIMITED];

            return;
        }

        // Búsqueda EXACTA. Audit log con hash, NUNCA con el dato en claro.
        $user = $type === self::INPUT_EMAIL
            ? User::where('email', mb_strtolower($raw))->first()
            : self::findByPhone($raw);

        AuditLogger::logSensitive('registrations.validated', $raw, target: $user);

        // `query` se incluye en cada resultado para que el empleado SIEMPRE vea a qué persona
        // corresponde la respuesta. Eco del input introducido por el propio empleado, NO dato
        // extraído del User: cumple RGPD igual (no revela info que no supiera).
        if (! $user) {
            $this->result = ['status' => self::STATUS_NOT_REGISTERED, 'query' => $raw];

            return;
        }

        $this->result = $this->stateFor($user, $raw);
        $this->openProfile($user, 'lookup');
    }

    /**
     * El carné por el MISMO input (§9.2 A·5): el lector teclea el token y un Enter. El token se audita
     * como dato sensible (su sha256 es, a propósito, el mismo `token_hash` de la tabla) y un carné
     * REVOCADO no abre nada: «carné caducado — busca por email» es un camino que ya existe (§4.5).
     */
    private function searchByCard(string $raw): void
    {
        $normalized = CardToken::normalize($raw);
        $card = app(CustomerCards::class)->findByToken($normalized);
        AuditLogger::logSensitive('puerta.card_scanned', $normalized, target: $card?->user);

        if ($card === null) {
            $this->result = ['status' => self::STATUS_CARD_UNKNOWN];

            return;
        }
        if ($card->isRevoked() || $card->user === null) {
            $this->result = ['status' => self::STATUS_CARD_REVOKED];

            return;
        }

        $this->result = $this->stateFor($card->user, __('admin.puerta.validar.card_query'));

        // `#741` · **EL ESCANEO ACREDITA LA VISITA.** La persona está delante con su carné: no hay gesto más
        // claro de «ha venido», y entre dos clientes no puede haber ninguno (`#234`). Idempotente por (cliente,
        // día) —`GateVisits`—, ANTES de componer la ficha para que nazca con la visita puesta y la encuesta
        // interna se ofrezca en el mismo gesto (`specs/encuestas.md` §4.2). Con el mismo permiso que la ficha:
        // sin `puerta.profile` el escaneo sigue siendo solo el semáforo. La búsqueda TECLEADA no acredita
        // («me he dejado el móvil» puede ser una consulta): ahí sigue `registerVisit()`.
        if ($this->canViewProfile()) {
            app(GateVisits::class)->register($card->user, Auth::user(), DisplayTime::today());
        }

        $this->openProfile($card->user, 'card');
    }

    /**
     * El semáforo, calculado IGUAL para el tecleo y el escaneo. Fase 6 · waiver (§4.1, §4.8): el
     * estado sale de `WaiverStatus`, que sabe en qué MODO está la instalación — `desactivado` colapsa
     * a 2 estados (#216); `externo` lee el sello; `interno` lee el REGISTRO firmado, y si es de una
     * versión anterior lo SEÑALA pero deja pasar.
     *
     * @return array<string, mixed>
     */
    private function stateFor(User $user, string $query): array
    {
        $status = WaiverStatus::for($user);
        if (! $status->isEnabled()) {
            return ['status' => self::STATUS_REGISTERED, 'query' => $query];
        }
        if (! $status->signed) {
            return ['status' => self::STATUS_REGISTERED_NO_WAIVER, 'query' => $query];
        }

        return [
            'status' => self::STATUS_REGISTERED_WITH_WAIVER,
            'query' => $query,
            'date' => DisplayTime::format($status->acceptedAt, 'd/m/Y'),
            'outdated' => $status->isOutdated(),
        ];
    }

    /** La DIVULGACIÓN (§4.6·3): solo con permiso, compuesta por el servicio, auditada aparte y con caducidad de servidor. */
    private function openProfile(User $user, string $via): void
    {
        if (! $this->canViewProfile()) {
            return;
        }

        $this->profileUserId = (int) $user->getKey();
        $this->profileExpiresAt = now()->addMinutes(PuertaSettings::profileTtlMinutes())->timestamp;
        $this->profile = $this->composeProfile($user, $via);
        $this->refreshSurveyOffer($user);

        AuditLogger::log('puerta.profile_viewed', $user, ['via' => $via]);
    }

    /**
     * La ficha ENTERA: lo que compone el DOMINIO más las tres claves de PRESENTACIÓN que solo
     * existen en esta pantalla — `via` (de dónde salió la ficha: escaneo o búsqueda, y la vista lo
     * pinta como distintivo), `expires_at` y `ttl_minutes` (el velo de privacidad y el reloj que la
     * cierra sola).
     *
     * ⚠️⚠️ **Es fuente única a propósito, y nació de un defecto de producción** (2026-09-02). La
     * ficha se compone en DOS momentos —al abrirla y al recomponerla tras una acción del operador— y
     * el segundo lo hacía por su cuenta con un `toArray()` a secas: perdía las tres claves, y **una
     * clave ausente en Blade no falla, se pinta vacía**. El `ArgumentCountError` que se llevó el
     * operador tapaba ese segundo defecto, que no habría dado ningún error.
     *
     * ⚠️ `expires_at` es una COPIA para la vista; la decisión la toma `$profileExpiresAt`, que el
     * navegador no puede tocar (`SEC-04`). Quien llama fija la ventana antes —al abrir— o la reabre
     * después con {@see touchProfileWindow}.
     *
     * @return array<string, mixed>
     */
    private function composeProfile(User $user, string $via): array
    {
        return app(GateProfile::class)
            ->for($user, DisplayTime::today(), PuertaSettings::windowDays())
            ->toArray() + [
                'via' => $via,
                'expires_at' => $this->profileExpiresAt,
                'ttl_minutes' => PuertaSettings::profileTtlMinutes(),
            ];
    }

    /**
     * `SEC-04` aplicado al TIEMPO (§4.8): la ficha caduca en el servidor y se comprueba en cada ida y
     * vuelta. Un temporizador de navegador no es garantía —la pestaña se duerme, el JS se pausa— y el
     * dato ya está en el DOM; lo que lo hace real es que el servidor no la devuelva.
     */
    private function ensureFresh(): void
    {
        if ($this->profile !== null && (int) ($this->profileExpiresAt ?? 0) <= now()->timestamp) {
            $this->profile = null;
            $this->profileUserId = null;
            $this->profileExpiresAt = null;
            $this->result = null;
            // La encuesta abierta muere con la ficha (§4.2): sin guardar, y se vuelve a ofrecer en la siguiente.
            $this->forgetSurvey();
        }
    }

    /**
     * Acreditar la VISITA (§8.3): idempotente por (cliente, día). Requiere la ficha viva y el permiso; la
     * interacción reinicia el reloj.
     *
     * ▶ Desde `#741` el ESCANEO del carné acredita solo ({@see searchByCard}); este método queda para la ficha
     * abierta por BÚSQUEDA TECLEADA (sin botón en la vista desde `#234`: hoy solo lo llaman los tests y, el día
     * que vuelva un botón o llegue JumpPoints, ese botón).
     */
    public function registerVisit(): void
    {
        $this->authorizeAccess();
        abort_unless($this->canViewProfile(), 403);
        $this->ensureFresh();

        if ($this->profile === null) {
            return;
        }

        $customer = User::find((int) ($this->profileUserId ?? 0));
        if ($customer === null) {
            $this->clear();

            return;
        }

        app(GateVisits::class)->register($customer, Auth::user(), DisplayTime::today());
        $this->profile['visit_registered_today'] = true;
        $this->profileExpiresAt = now()->addMinutes(PuertaSettings::profileTtlMinutes())->timestamp;
        $this->profile['expires_at'] = $this->profileExpiresAt;
        // La visita acreditada es lo que abre la oferta de la encuesta interna (§4.2).
        $this->refreshSurveyOffer($customer);
    }

    // ─── La encuesta interna (`specs/encuestas.md` §4.2, T2) ───────────────────────────────────────

    /**
     * La OFERTA: solo con la visita de HOY acreditada, una encuesta interna viva y sin fila de este cliente para
     * ella (ni contestada ni declinada). Lo demás —a quién, cuándo, una por cliente— lo decide `SurveyResponses`.
     */
    private function refreshSurveyOffer(User $customer): void
    {
        $this->forgetSurvey();

        if (! (bool) ($this->profile['visit_registered_today'] ?? false)) {
            return;
        }

        $survey = app(SurveyResponses::class)->offerFor(Survey::KIND_INTERNAL, (int) $customer->getKey());
        if ($survey === null) {
            return;
        }

        $this->surveyId = (int) $survey->getKey();
        $this->survey = $this->surveyState($survey, 'offer');
    }

    private function forgetSurvey(): void
    {
        $this->survey = null;
        $this->surveyId = null;
        $this->surveyAnswers = [];
    }

    /**
     * Lo que la vista pinta de la encuesta, con los rótulos ya resueltos al idioma del panel (`QuestionSchema`
     * cae al español si falta): la vista no decide idiomas.
     *
     * @return array<string, mixed>
     */
    private function surveyState(Survey $survey, string $state): array
    {
        $locale = app()->getLocale();
        $questions = array_map(static fn (array $q): array => [
            'key' => $q['key'],
            'type' => $q['type'],
            'required' => $q['required'],
            'label' => QuestionSchema::label($q['label'], $locale),
            'options' => array_map(
                static fn (array $o): array => ['key' => $o['key'], 'label' => QuestionSchema::label($o['label'], $locale)],
                $q['options'],
            ),
        ], $survey->questionList());

        return [
            'key' => $survey->key,
            'name' => $survey->displayName($locale),
            'intro' => $survey->displayIntro($locale),
            'count' => count($questions),
            'state' => $state,
            'questions' => $questions,
        ];
    }

    /** «Preguntar»: abre el formulario. Sin escribir nada todavía. */
    public function openSurvey(): void
    {
        if ($this->surveySubject() === null) {
            return;
        }

        $this->surveyAnswers = [];
        $this->resetErrorBag();
        $this->survey['state'] = 'open';
        $this->touchProfileWindow();
    }

    /** «Ahora no»: vuelve a la oferta sin guardar (la tarjeta sigue ahí mientras viva la ficha). */
    public function cancelSurvey(): void
    {
        if ($this->surveySubject() === null) {
            return;
        }

        $this->surveyAnswers = [];
        $this->resetErrorBag();
        $this->survey['state'] = 'offer';
        $this->touchProfileWindow();
        $this->releaseReader();
    }

    /**
     * **El lector sigue libre.** Tocar un botón de la encuesta se lleva el foco, y al cerrarse el formulario ese
     * botón ya no existe: el foco caería al `body` y el siguiente escaneo no escribiría en ningún sitio. Es el
     * mismo aviso que emite `search()` (`#234`, `GateKioskTest`): la vista lo escucha y devuelve el cursor al campo.
     */
    private function releaseReader(): void
    {
        $this->dispatch('gate-input-cleared');
    }

    /**
     * «Guardar respuestas»: lo marcado se TIPA y se VALIDA contra las preguntas en el servidor; una obligatoria
     * sin contestar o un valor que la pregunta no acepta vuelven como error de campo y no se escribe nada. Con
     * todo en orden: la fila (`channel = internal`, el operador, la visita de hoy), el hecho y el rastro.
     */
    public function answerSurvey(): void
    {
        $subject = $this->surveySubject();
        if ($subject === null) {
            return;
        }
        [$customer, $survey] = $subject;

        // Cada intento se juzga de nuevo: los errores del anterior no sobreviven a una corrección.
        $this->resetErrorBag();
        $questions = $survey->questionList();
        $typed = QuestionSchema::fromForm($questions, $this->surveyAnswers);
        $errors = QuestionSchema::validate($questions, $typed);
        if ($errors !== []) {
            $messages = [];
            foreach ($errors as $key => $reason) {
                $messages['surveyAnswers.'.$key] = (string) __('admin.puerta.validar.profile.survey_error_'.$reason);
            }

            throw ValidationException::withMessages($messages);
        }

        $response = app(SurveyResponses::class)->answer(
            $survey,
            (int) $customer->getKey(),
            SurveyResponse::CHANNEL_INTERNAL,
            $typed,
            app()->getLocale(),
            DisplayTime::today()->toDateString(),
            Auth::id() === null ? null : (int) Auth::id(),
        );
        if ($response !== null) {
            AuditLogger::log('puerta.survey_answered', $customer, ['survey' => $survey->key, 'response_id' => (int) $response->getKey()]);
        }

        $this->survey = $this->surveyState($survey, 'answered');
        $this->surveyAnswers = [];
        $this->touchProfileWindow();
        $this->releaseReader();
    }

    /** «No preguntar»: también es una respuesta — deja fila, hecho y rastro, y no se vuelve a ofrecer. */
    public function declineSurvey(): void
    {
        $subject = $this->surveySubject();
        if ($subject === null) {
            return;
        }
        [$customer, $survey] = $subject;

        $response = app(SurveyResponses::class)->decline(
            $survey,
            (int) $customer->getKey(),
            SurveyResponse::CHANNEL_INTERNAL,
            DisplayTime::today()->toDateString(),
            Auth::id() === null ? null : (int) Auth::id(),
            app()->getLocale(),
        );
        if ($response !== null) {
            AuditLogger::log('puerta.survey_declined', $customer, ['survey' => $survey->key, 'response_id' => (int) $response->getKey()]);
        }

        $this->survey = $this->surveyState($survey, 'declined');
        $this->surveyAnswers = [];
        $this->touchProfileWindow();
        $this->releaseReader();
    }

    /**
     * El sujeto de la encuesta, re-autorizado (`SEC-04`): la ficha VIVA, el cliente y la encuesta BLOQUEADOS, y
     * la encuesta todavía en marcha. Si algo falta —la ficha caducó, la encuesta se apagó entre la oferta y el
     * guardado— no hay sujeto y no se escribe nada.
     *
     * @return array{0: User, 1: Survey}|null
     */
    private function surveySubject(): ?array
    {
        $this->authorizeAccess();
        abort_unless($this->canViewProfile(), 403);
        $this->ensureFresh();

        if ($this->profile === null || $this->survey === null || $this->surveyId === null) {
            return null;
        }

        $customer = User::find((int) ($this->profileUserId ?? 0));
        $survey = Survey::find($this->surveyId);
        if ($customer === null || $survey === null || ! $survey->isRunning()) {
            $this->forgetSurvey();

            return null;
        }

        return [$customer, $survey];
    }

    /**
     * **DAR POR FIRMADA la exención, con la persona delante** (`#336`, `[DECIDIDO owner]`).
     *
     * Solo aparece con **aceptación RETENIDA** —el cliente leyó el texto y marcó la casilla, y lo que
     * falta es que verifique su correo—, así que el operador confirma algo que EXISTE, no lo inventa.
     * Sin aceptación previa, el flujo sigue siendo el de siempre: pásale la tablet.
     *
     * ⚠️ **El permiso es el de validar** (`[DECIDIDO owner]`: quien valida en la puerta, da fe), y por
     * eso no hay comprobación nueva: `authorizeAccess()` ya lo exige para estar en esta pantalla.
     *
     * ⚠️⚠️ **NO se toca `email_verified_at`.** El operador acredita a la PERSONA, no al BUZÓN:
     * marcarlo verificado afirmaría sin prueba que esa dirección es suya, y de ahí cuelga la
     * recuperación de contraseña. El cliente sigue viendo su aviso de verificar, que es verdad.
     */
    public function declareWaiver(): void
    {
        $this->authorizeAccess();
        abort_unless($this->canViewProfile(), 403);
        $this->ensureFresh();

        if ($this->profile === null) {
            return;
        }

        $customer = User::find((int) ($this->profileUserId ?? 0));
        if ($customer === null) {
            $this->clear();

            return;
        }

        try {
            $firmada = app(WaiverCounterDeclaration::class)->declare($customer, Auth::user()) !== null;
        } catch (WaiverDocumentStaleException) {
            // El texto cambió entre la aceptación y hoy: NO se firma el viejo. Ahí sí toca la tablet.
            $this->profile['waiver_declare_stale'] = true;
            $this->touchProfileWindow();

            return;
        }

        if ($firmada) {
            // La ficha se recompone desde el dominio: componer el estado a mano aquí sería una
            // segunda opinión sobre lo que `WaiverStatus` ya sabe, y es como divergen.
            //
            // ⚠️ Por el MISMO compositor que al abrirla ({@see composeProfile}), y `via` se conserva:
            // la ficha no ha cambiado de origen por haber firmado. Recomponer aquí por libre fue el
            // defecto de `#336` — dos argumentos de menos (500 en la puerta, con la firma ya escrita)
            // y las tres claves de presentación perdidas.
            //
            // ⚠️⚠️ **Y el `refresh()` no es precaución, es necesario**: `WaiverCounterDeclaration`
            // limpia la aceptación retenida sobre una fila que carga y BLOQUEA aparte —tiene que
            // hacerlo, el lock es su punto de serialización—, así que este `$customer` conserva en
            // memoria el `waiver_pending_document_id` de antes. `GateProfile` lo lee del modelo, no
            // de la BD, de modo que sin esto la ficha recompuesta seguiría diciendo «tiene una
            // aceptación retenida» y la pantalla volvería a ofrecer el botón de declarar sobre algo
            // que acaba de firmarse. No falla nada: solo miente.
            $customer->refresh();

            $this->profile = $this->composeProfile($customer, (string) $this->profile['via']);
            $this->result = $this->stateFor($customer, (string) ($this->result['query'] ?? ''));
        }

        $this->touchProfileWindow();
    }

    /** Reabre la ventana de caducidad de la ficha tras una acción del operador (`SEC-04`: en servidor). */
    private function touchProfileWindow(): void
    {
        $this->profileExpiresAt = now()->addMinutes(PuertaSettings::profileTtlMinutes())->timestamp;
        if (is_array($this->profile)) {
            $this->profile['expires_at'] = $this->profileExpiresAt;
        }
    }

    public function clear(): void
    {
        $this->authorizeAccess();

        $this->input = '';
        $this->result = null;
        $this->profile = null;
        $this->profileUserId = null;
        $this->profileExpiresAt = null;
        $this->forgetSurvey();
        // «Nueva búsqueda» se pulsa con el dedo y el foco se quedaba en el botón (medido con la sonda, T2 de
        // las encuestas): el siguiente escaneo no escribía en el campo. El mismo aviso que tras buscar.
        $this->releaseReader();
    }

    public function render(): View
    {
        $this->ensureFresh();

        return view('livewire.admin.puerta.validar', [
            'canViewProfile' => $this->canViewProfile(),
            // El semáforo se resuelve FUERA de la plantilla (§9.7 C·5): la vista pinta un callout y
            // no decide colores. `null` mientras no hay búsqueda.
            'semaphore' => $this->result === null ? null : GateSemaphore::for($this->result),
        ]);
    }

    /**
     * `true` si el limitador RECHAZA; si no, consume un intento. El rechazo se audita SOLO la primera
     * vez por ventana (segundo limitador, `SEC-05`) para no inflar `audit_logs` ante martilleo, y sin
     * el dato buscado.
     */
    private function limited(string $key, string $auditKey, int $limit, int $decaySeconds, string $auditAction): bool
    {
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            if (! RateLimiter::tooManyAttempts($auditKey, 1)) {
                RateLimiter::hit($auditKey, $decaySeconds);
                AuditLogger::log($auditAction, payload: ['limit' => $limit]);
            }

            return true;
        }

        RateLimiter::hit($key, $decaySeconds);

        return false;
    }

    /**
     * Detecta el tipo del input. Devuelve `email`, `card`, `phone` o null si no es válido.
     *
     * Email: contiene `@` y es válido. Carné: 20 caracteres con el prefijo del carné tras normalizar
     * (§9.2 A·1) — se mira ANTES que el teléfono; un teléfono nunca lleva letras. Phone: solo dígitos,
     * espacios, +, -, (, ), . (formatos típicos).
     */
    public static function detectInputType(string $input): ?string
    {
        if ($input === '') {
            return null;
        }

        if (str_contains($input, '@')) {
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false ? self::INPUT_EMAIL : null;
        }

        if (CardToken::looksLike(CardToken::normalize($input))) {
            return self::INPUT_CARD;
        }

        return preg_match('/^[\d\s\+\-\(\)\.]+$/', $input) === 1 && self::normalizePhone($input) !== ''
            ? self::INPUT_PHONE
            : null;
    }

    /**
     * Búsqueda flexible por teléfono: empareja independiente del formato del input
     * y del valor guardado (espacios, guiones, paréntesis, puntos, prefijo `+`).
     */
    private static function findByPhone(string $rawPhone): ?User
    {
        $needle = self::normalizePhone($rawPhone);

        if ($needle === '') {
            return null;
        }

        // Normalización SQL espejo de `normalizePhone`: quita TODO lo no-dígito.
        // Funciona en MySQL y SQLite (REPLACE anidados, sin REGEXP que difieren entre DBs).
        return User::whereRaw(
            "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), '+', '') = ?",
            [$needle]
        )->first();
    }

    /**
     * Normaliza un teléfono a SOLO dígitos. Ej.: `+34 600-11.22 33` → `34600112233`.
     * Delega en {@see PhoneNormalizer::digits} — convención ÚNICA del proyecto,
     * compartida con el alta manual (`CustomerRegistrar`) para evitar divergencias (#264-audit).
     */
    public static function normalizePhone(string $phone): string
    {
        return PhoneNormalizer::digits($phone) ?? '';
    }
}
