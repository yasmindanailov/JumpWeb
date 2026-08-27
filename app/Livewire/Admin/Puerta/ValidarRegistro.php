<?php

namespace App\Livewire\Admin\Puerta;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\CardToken;
use App\Domain\Identity\Services\CustomerCards;
use App\Domain\Identity\Services\GateProfile;
use App\Domain\Identity\Services\GateVisits;
use App\Domain\Identity\Services\PuertaSettings;
use App\Domain\Identity\Services\WaiverStatus;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\PhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
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

        $raw = trim($this->input);
        $type = self::detectInputType($raw);

        if ($type === null) {
            // En invalid_input devolvemos el `query` SOLO si hay algo escrito —
            // si está vacío, el mensaje genérico "Introduce un email…" basta.
            $this->result = ['status' => self::STATUS_INVALID_INPUT, 'query' => $raw];

            return;
        }

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

        $ttl = PuertaSettings::profileTtlMinutes();
        $data = app(GateProfile::class)->for($user, DisplayTime::today(), PuertaSettings::windowDays());

        $this->profile = $data->toArray() + [
            'via' => $via,
            'expires_at' => now()->addMinutes($ttl)->timestamp,
            'ttl_minutes' => $ttl,
        ];
        AuditLogger::log('puerta.profile_viewed', $user, ['via' => $via]);
    }

    /**
     * `SEC-04` aplicado al TIEMPO (§4.8): la ficha caduca en el servidor y se comprueba en cada ida y
     * vuelta. Un temporizador de navegador no es garantía —la pestaña se duerme, el JS se pausa— y el
     * dato ya está en el DOM; lo que lo hace real es que el servidor no la devuelva.
     */
    private function ensureFresh(): void
    {
        if ($this->profile !== null && (int) ($this->profile['expires_at'] ?? 0) <= now()->timestamp) {
            $this->profile = null;
            $this->result = null;
        }
    }

    /**
     * Acreditar la VISITA (§8.3): un acto EXPLÍCITO e idempotente por (cliente, día), nunca un efecto
     * de abrir la ficha. Requiere la ficha viva y el permiso; la interacción reinicia el reloj.
     */
    public function registerVisit(): void
    {
        $this->authorizeAccess();
        abort_unless($this->canViewProfile(), 403);
        $this->ensureFresh();

        if ($this->profile === null) {
            return;
        }

        $customer = User::find((int) ($this->profile['user_id'] ?? 0));
        if ($customer === null) {
            $this->clear();

            return;
        }

        app(GateVisits::class)->register($customer, Auth::user(), DisplayTime::today());
        $this->profile['visit_registered_today'] = true;
        $this->profile['expires_at'] = now()->addMinutes(PuertaSettings::profileTtlMinutes())->timestamp;
    }

    public function clear(): void
    {
        $this->authorizeAccess();

        $this->input = '';
        $this->result = null;
        $this->profile = null;
    }

    public function render(): View
    {
        $this->ensureFresh();

        return view('livewire.admin.puerta.validar', [
            'canViewProfile' => $this->canViewProfile(),
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
