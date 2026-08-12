<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\Consent;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\AuditLogger;
use App\Domain\Platform\Services\PhoneNormalizer;
use App\Notifications\CustomerAccountCreated;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Alta directa de un cliente desde el back-office (Fase 7.5, #181). Sustituye a la
 * invitación por enlace firmado: el operador crea la cuenta en el acto con el cliente
 * delante.
 *
 *  - Contraseña ALEATORIA (cast `hashed` en `User`); se envía en claro UNA vez por email.
 *  - Cuenta **ya verificada** (`email_verified_at`): alta presencial, sin email de verificación.
 *  - Rol `customer` + consent de **privacidad** (el operador confirma haberla explicado en persona).
 *  - Email de bienvenida con la contraseña temporal + recomendación de cambiarla.
 *
 * **Cliente SIN email (reserva de agenda, solo teléfono — #263):** la clienta da de alta reservas
 * escritas a mano de las que solo tiene el teléfono. En ese caso `email` se guarda como **`NULL`**
 * (nunca `''`: la cadena vacía colisionaría con el índice UNIQUE). La cuenta entonces:
 *   - NO recibe email de bienvenida (no hay a dónde) ni queda "verificada" (nada que verificar).
 *   - NO puede iniciar sesión ni usar autoservicio (recuperar contraseña, "Mis pedidos", RGPD): vive
 *     SOLO en el panel. Es el comportamiento esperado para un cliente de agenda.
 *   - Se deduplica por TELÉFONO en vez de por email (la decisión "reutilizar vs crear" la toma el
 *     operador en la página; este servicio solo CREA cuando se le llama). Ver `customersMatchingPhone`.
 *
 * Atómico (cuenta + rol + consent en una transacción); el email va FUERA de la txn (un
 * fallo de SMTP no debe revertir el alta). En español por defecto: el cliente aún no eligió
 * idioma y los emails al cliente viven en es/en/fr (el panel zh_CN es interno).
 */
class CustomerRegistrar
{
    /**
     * Crea la cuenta y (si hay email) envía la bienvenida.
     *
     *  - **Con email:** si ya existe una cuenta con ese email, NO crea nada ni envía: devuelve el
     *    usuario existente con `created => false` (el operador debe seleccionarlo, no duplicarlo).
     *  - **Sin email** (`null`/`''`): crea SIEMPRE una cuenta nueva sin email (la deduplicación por
     *    teléfono y la decisión de reutilizar la gobierna la página antes de llamar aquí). No envía
     *    correo y `email_sent => false`.
     *
     * @return array{user: User, created: bool, email_sent: bool}
     */
    public function register(string $name, ?string $email, ?string $phone): array
    {
        $name = trim($name);
        $email = self::normalizeEmail($email);
        $phone = $phone !== null && trim($phone) !== '' ? trim($phone) : null;

        if ($email !== null) {
            $existing = User::where('email', $email)->first();
            if ($existing !== null) {
                return ['user' => $existing, 'created' => false, 'email_sent' => true];
            }
        }

        // 10-12 caracteres letras+números (sin símbolos): segura pero fácil de teclear
        // desde el email. Se hashea al asignarla (cast `hashed`); solo viaja en claro al correo.
        // La cuenta SIN email también lleva contraseña (la columna es NOT NULL); es inservible
        // mientras no haya email, pero deja la cuenta lista si más tarde se le añade uno.
        $plainPassword = Str::password(12, letters: true, numbers: true, symbols: false, spaces: false);
        $now = now();
        $ip = request()?->ip();

        $user = DB::transaction(function () use ($name, $email, $phone, $plainPassword, $now, $ip): User {
            $user = User::create([
                'name' => $name,
                'email' => $email,                 // NULL para el cliente de agenda sin correo
                'phone' => $phone,
                'password' => $plainPassword,
                'locale' => 'es',
                'marketing_opt_in' => false,
                'privacy_accepted_at' => $now,
            ]);

            // Alta presencial CON email → verificada al instante (sin email de verificación).
            // SIN email no hay nada que verificar → `email_verified_at` queda null.
            // `email_verified_at` no es fillable: forceFill explícito.
            if ($email !== null) {
                $user->forceFill(['email_verified_at' => $now])->save();
            }

            if ($role = Role::where('name', 'customer')->first()) {
                $user->roles()->attach($role);
            }

            // Consent de privacidad: el operador confirma haber informado al cliente en persona.
            $user->consents()->create([
                'type' => 'privacy',
                'accepted_at' => $now,
                'ip' => $ip,
                'version' => Consent::CURRENT_VERSION,
            ]);

            return $user;
        });

        // Email de bienvenida SOLO si hay dirección. Sin email, no hay nada que enviar ni
        // contraseña que comunicar (la cuenta vive en el panel).
        $emailSent = false;
        if ($email !== null) {
            $emailSent = true;
            try {
                $user->notify((new CustomerAccountCreated($plainPassword))->locale('es'));
            } catch (\Throwable $e) {
                // Email NO bloqueante (#robustez): la cuenta ya está creada y comprometida en BD. Si el
                // transporte falla (SMTP caído, cuenta de envío sin activar, IP no autorizada…), NO debe
                // tirar el alta — se registra el fallo y el operador puede reenviar/resetear la contraseña.
                $emailSent = false;
                Log::warning('customer.welcome_email_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            }
        }

        // Auditoría con dato personal → solo sha256 del identificador (patrón de validar registro).
        // Con email, el identificador sensible es el email; sin email, el teléfono.
        AuditLogger::logSensitive('orders.customer_registered', $email ?? ($phone ?? ''), $user);

        return ['user' => $user, 'created' => true, 'email_sent' => $emailSent];
    }

    /**
     * Clientes (rol `customer`, no anonimizados) cuyo teléfono NORMALIZADO coincide con `$phone`.
     * Es la base del aviso de duplicados del alta sin email: el email era la clave de deduplicación;
     * sin él, el único identificador es el teléfono. La comparación se hace sobre el teléfono
     * normalizado (sin espacios ni símbolos) para que «600 11 22 33» y «600112233» casen.
     *
     * Carga los clientes con teléfono y filtra en PHP por igualdad normalizada (el teléfono no tiene
     * índice ni formato canónico en BD). A escala de parque (cientos de clientes) es asumible; si el
     * volumen creciera, normalizar la columna en BD sería la optimización.
     *
     * Los clientes ANONIMIZADOS (RGPD) quedan FUERA: `User::anonymize()` pone `phone = null`, así que
     * el `whereNotNull('phone')` los excluye (no hay columna `anonymized_at`; el marcador real es el
     * dominio sintético del email + el phone nulo).
     *
     * @return Collection<int, User>
     */
    public function customersMatchingPhone(?string $phone): Collection
    {
        $target = self::normalizePhone($phone);
        if ($target === null) {
            return new Collection;
        }

        return User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'customer'))
            ->whereNotNull('phone')
            ->get()
            ->filter(fn (User $u): bool => self::normalizePhone($u->phone) === $target)
            ->values();
    }

    /** Normaliza un email para almacenamiento/búsqueda: minúsculas + trim; cadena vacía → `null`. */
    public static function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }
        $email = Str::lower(trim($email));

        return $email === '' ? null : $email;
    }

    /**
     * Normaliza un teléfono para COMPARAR (no para mostrar). Delega en {@see PhoneNormalizer::digits}
     * (convención ÚNICA del proyecto = solo dígitos, sin `+`), la MISMA que usa la puerta
     * (`ValidarRegistro`) → sin divergencia entre subsistemas (#264-audit). `null`/vacío → `null`.
     * Nota: «con prefijo de país» y «sin él» (p. ej. «34600…» vs «600…») siguen siendo distintos.
     */
    public static function normalizePhone(?string $phone): ?string
    {
        return PhoneNormalizer::digits($phone);
    }
}
