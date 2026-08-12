<?php

namespace App\Livewire\Admin\Puerta;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Services\PuertaSettings;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\DisplayTime;
use App\Domain\Platform\Services\PhoneNormalizer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Fase 7.1a — Validar registro en puerta (decisiones #119 y #126).
 *
 * Búsqueda por email o teléfono que devuelve únicamente el estado del waiver del
 * cliente — sin nombre, sin email/phone completo, sin historial. Privacy-by-design
 * (RGPD #19): el empleado en puerta sabe si puede dejar saltar al cliente, no más.
 *
 * Tres estados posibles:
 *  - REGISTERED_WITH_WAIVER → puede saltar; el empleado le entrega pulsera.
 *  - REGISTERED_NO_WAIVER  → tiene cuenta, falta firmar; CTA "Solicitar firma".
 *  - NOT_REGISTERED        → CTA "Ofrecer alta por QR/tablet".
 *
 * Defensas:
 *  - Permiso `registrations.validate` (admin pasa por Gate::before).
 *  - Rate limit configurable vía `PuertaSettings::validateRateLimit()` (default 100/min)
 *    por `user_id` del staff (NO por IP — los empleados pueden compartir IP en el parque).
 *  - Audit log con `AuditLogger::logSensitive`: el identificador buscado se guarda
 *    como sha256, jamás en claro.
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

    public string $input = '';

    /**
     * Última respuesta a renderizar. `null` antes de la primera búsqueda.
     *
     * Forma: ['status' => string, 'date' => ?string].
     *
     * @var array<string,mixed>|null
     */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    /**
     * Re-valida el permiso en CADA petición Livewire (auditoría Fase 1, A8). `mount()` solo corre una
     * vez; sin esto, un staff degradado (staff→customer) o con el permiso `registrations.validate`
     * revocado EN VIVO seguiría invocando `search()`/`clear()` con la pestaña abierta (el enforcement
     * es data-driven y `hasPermission` lee el pivote al momento → aquí solo falta volver a comprobarlo).
     */
    private function authorizeAccess(): void
    {
        abort_unless(Auth::user()?->hasPermission('registrations.validate') ?? false, 403);
    }

    public function search(): void
    {
        $this->authorizeAccess();

        $raw = trim($this->input);
        $type = self::detectInputType($raw);

        if ($type === null) {
            // En invalid_input devolvemos el `query` SOLO si hay algo escrito —
            // si está vacío, el mensaje genérico "Introduce un email…" basta.
            $this->result = ['status' => self::STATUS_INVALID_INPUT, 'query' => $raw];

            return;
        }

        // Rate limit por usuario staff (configurable, decisión #126).
        $userId = Auth::id();
        $key = "puerta:validate:user:{$userId}";
        $limit = PuertaSettings::validateRateLimit();

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            // Audita el RECHAZO con razón estructurada (#127/#128, defense-in-depth): el rate-limit
            // es el freno anti-enumeración si una sesión staff se compromete (PuertaSettings) — el
            // evento que conviene dejar registrado. Solo la PRIMERA vez por ventana (2.º limitador)
            // para NO inflar `audit_logs` (NO Prunable) ante martilleo. Sin el input en claro.
            $auditKey = "puerta:validate:audit:user:{$userId}";
            if (! RateLimiter::tooManyAttempts($auditKey, 1)) {
                RateLimiter::hit($auditKey, 60);
                AuditLogger::log('registrations.validate_rate_limited', payload: ['limit' => $limit]);
            }

            $this->result = ['status' => self::STATUS_RATE_LIMITED];

            return;
        }

        RateLimiter::hit($key, 60);

        // Búsqueda. Audit log con hash, NUNCA con el dato en claro.
        $user = $type === 'email'
            ? User::where('email', mb_strtolower($raw))->first()
            : self::findByPhone($raw);

        AuditLogger::logSensitive('registrations.validated', $raw, target: $user);

        // `query` se incluye en cada resultado para que el empleado SIEMPRE vea
        // a qué persona corresponde la respuesta — UX crítica para evitar que un
        // resultado quede colgado mientras se prepara la siguiente búsqueda
        // (refinamiento operativo tras la entrega inicial de 7.1a).
        // Eco del input introducido por el propio empleado, NO dato extraído
        // del User: cumple RGPD igual (no revela info que no supiera).
        if (! $user) {
            $this->result = ['status' => self::STATUS_NOT_REGISTERED, 'query' => $raw];

            return;
        }

        // #216: si la comprobación de waiver está DESACTIVADA (lo gestiona el sistema externo),
        // colapsamos a 2 estados — solo importa si el cliente tiene cuenta o no.
        if (! PuertaSettings::waiverCheckEnabled()) {
            $this->result = ['status' => self::STATUS_REGISTERED, 'query' => $raw];

            return;
        }

        if ($user->waiver_accepted_at === null) {
            $this->result = ['status' => self::STATUS_REGISTERED_NO_WAIVER, 'query' => $raw];

            return;
        }

        $this->result = [
            'status' => self::STATUS_REGISTERED_WITH_WAIVER,
            'query' => $raw,
            'date' => DisplayTime::format($user->waiver_accepted_at, 'd/m/Y'),
        ];
    }

    public function clear(): void
    {
        $this->authorizeAccess();

        $this->input = '';
        $this->result = null;
    }

    public function render(): View
    {
        return view('livewire.admin.puerta.validar');
    }

    /**
     * Detecta el tipo del input. Devuelve 'email', 'phone' o null si no es válido.
     *
     * Email: contiene `@` y al menos un caracter a cada lado.
     * Phone: solo dígitos, espacios, +, -, (, ), . (formatos típicos).
     */
    public static function detectInputType(string $input): ?string
    {
        if ($input === '') {
            return null;
        }

        if (str_contains($input, '@')) {
            return filter_var($input, FILTER_VALIDATE_EMAIL) !== false ? 'email' : null;
        }

        return preg_match('/^[\d\s\+\-\(\)\.]+$/', $input) === 1 && self::normalizePhone($input) !== ''
            ? 'phone'
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
