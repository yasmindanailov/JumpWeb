<?php

namespace App\Domain\Identity\Models;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Ticket;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;

#[Fillable([
    'name', 'email', 'password', 'phone', 'locale', 'panel_locale', 'last_login_at',
    'marketing_opt_in', 'privacy_accepted_at', 'terms_accepted_at', 'waiver_accepted_at',
    'pending_email', 'pending_email_sent_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmail
{
    /**
     * `HasApiTokens` (Fase 3 · paso 0): habilita los tokens Bearer de Sanctum para el cliente
     * móvil de Fase 6. La SPA de Fase 4 NO los usa —va por cookie de sesión, spec §4.2— y hoy no
     * hay ningún emisor: `POST auth/tokens` llega en el paso 3, y con él la **revocación**, que es
     * la parte que la revisión del spec destapó como hueco (`RGPD-01`: `anonymize()` purga
     * `sessions` pero un Bearer sobreviviría al borrado). Hasta entonces la tabla está vacía.
     *
     * @use HasFactory<UserFactory>
     */
    use HasApiTokens, HasFactory, Notifiable;

    /** Dominio reservado para emails de cuentas anonimizadas (M3.3). No es enrutable. */
    public const ANONYMIZED_EMAIL_DOMAIN = 'deleted.local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'waiver_accepted_at' => 'datetime',
            'pending_email_sent_at' => 'datetime',
            'marketing_opt_in' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Nombre de pila (primer token del nombre completo) para saludos en la UI. Reutilizado por
     * el nav, el bloque de cuenta del sidebar y `CustomerAccountContext`.
     */
    public function firstName(): string
    {
        $trimmed = Str::of((string) $this->name)->trim();
        $first = $trimmed->before(' ')->toString();

        return $first !== '' ? $first : $trimmed->toString();
    }

    /**
     * ¿Esta cuenta está anonimizada (RGPD: derecho de supresión, M3.3)? Identificable por el
     * dominio sintético del email. Una cuenta anonimizada conserva sus pedidos (AEAT) pero no
     * puede iniciar sesión, no aparece en exports y su email original queda libre para reuso.
     */
    public function isAnonymized(): bool
    {
        return str_ends_with((string) $this->email, '@'.self::ANONYMIZED_EMAIL_DOMAIN);
    }

    /**
     * Cierra TODA credencial de acceso de este titular: sus sesiones y sus tokens de API.
     *
     * **Punto único de invalidación** (Fase 3 · paso 3a). Antes, cada sitio que necesitaba echar a
     * un usuario copiaba el borrado de la tabla `sessions` —cuatro copias— y ninguno tocaba los
     * tokens, porque cuando se escribieron no existían. Con un emisor de Bearer (Fase 6) eso
     * significaría que un token sobrevive al borrado RGPD, al cambio de contraseña, al reset y a
     * «cerrar otras sesiones»: cuatro puertas abiertas que nadie vería hasta que fuera tarde. Se
     * arregla ahora, con el sistema todavía sin tokens emitidos, porque después habría que
     * acordarse.
     *
     * Se usa cuando la credencial en curso TAMBIÉN debe morir: supresión RGPD (art. 17) y
     * restablecimiento de contraseña (quien resetea no está autenticado; si el atacante lo estaba,
     * tiene que caer). Para «todas menos la mía», {@see revokeOtherAccess()}.
     */
    public function revokeAllAccess(): void
    {
        $this->purgeSessions(exceptCurrent: false);
        $this->tokens()->delete();
    }

    /**
     * Igual que {@see revokeAllAccess()} pero conservando la credencial con la que se hace la
     * petición: la sesión actual si viene por navegador, el token actual si viene por API.
     *
     * Es el caso de «cambiar la contraseña por sospecha de robo» y el de «cerrar las demás
     * sesiones»: el titular legítimo no debe autoexpulsarse al defenderse.
     *
     * ⚠️ Cuando la petición llega por SESIÓN, `currentAccessToken()` no devuelve un token
     * persistido sino un `TransientToken`, así que **caen todos los tokens de API**. Es lo
     * correcto y no un efecto colateral: quien cambia su contraseña desde la web espera que
     * cualquier app que siguiera conectada deje de estarlo.
     */
    public function revokeOtherAccess(): void
    {
        $this->purgeSessions(exceptCurrent: true);

        $tokens = $this->tokens();
        $current = $this->currentAccessToken();

        if ($current instanceof PersonalAccessToken) {
            $tokens->whereKeyNot($current->getKey());
        }

        $tokens->delete();
    }

    /**
     * Borra las filas de `sessions` del titular. Solo aplica con el driver de base de datos: con
     * `array`/`file`/`redis` no hay tabla que purgar y el resto de la invalidación (rotación del
     * `remember_token`, `logoutOtherDevices`) sigue haciendo su trabajo.
     */
    private function purgeSessions(bool $exceptCurrent): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        $query = DB::connection(config('session.connection'))
            ->table((string) config('session.table', 'sessions'))
            ->where('user_id', $this->getAuthIdentifier());

        if ($exceptCurrent) {
            $query->where('id', '!=', session()->getId());
        }

        $query->delete();
    }

    /**
     * Anonimiza la cuenta (RGPD art. 17 + AEAT): sobrescribe datos personales con valores
     * neutros pero conserva la fila `users` (FK con `orders` para conservar las facturas
     * ≥4 años). El email original queda libre para re-uso por otra cuenta. La contraseña se
     * reemplaza por un hash aleatorio (login imposible). Borra consents y desvincula roles.
     *
     * Idempotente: si ya estaba anonimizada, no hace nada y devuelve false. Atómico.
     */
    public function anonymize(): bool
    {
        if ($this->isAnonymized()) {
            return false;
        }

        $originalEmail = (string) $this->email;

        DB::transaction(function () use ($originalEmail) {
            // Consentimientos: ya no son trazables a un titular real → se eliminan (RGPD).
            // Los roles: desvincular (la cuenta no debe tener permisos tras anonimizar).
            $this->consents()->delete();
            $this->roles()->detach();

            // RGPD art. 17 — PII de TERCEROS (auditoría Fase 1, A1): los datos de invitados de los
            // pedidos del titular (nombres y ALERGIAS de menores = datos de salud, art. 9) viven en
            // `order_items.guest_data`/`event_data`. El deber fiscal AEAT cubre la FACTURA (importes,
            // fechas, código), NO la lista de invitados → la vaciamos. Los pedidos se conservan.
            OrderItem::whereIn('order_id', $this->orders()->select('id'))
                ->where(fn ($q) => $q->whereNotNull('guest_data')->orWhereNotNull('event_data'))
                ->update(['guest_data' => null, 'event_data' => null]);

            // P2 (auditoría Fase 1): el audit de `order_items.event_data_updated` de pedidos LEGACY
            // pudo guardar el nombre del homenajeado (PII de menor) en `payload` (hoy se guardan solo
            // las CLAVES). Redacta esos payloads de los items del titular → la PII tampoco sobrevive
            // ahí a la supresión. No-op en instalaciones nuevas (payloads ya keys-only); el
            // `payload_hash` (sha256) se conserva.
            DB::table('audit_logs')
                ->where('action', 'order_items.event_data_updated')
                ->where('target_type', (new OrderItem)->getMorphClass())
                ->whereIn('target_id', OrderItem::whereIn('order_id', $this->orders()->select('id'))->select('id'))
                ->update(['payload' => null]);

            // Sistema 5 (auditoría Fase 1): el audit `orders.email_resent` de pedidos LEGACY pudo
            // guardar el EMAIL del titular en claro en `payload` (hoy ya no se persiste). Redacta
            // esos payloads de los pedidos del titular → el email tampoco sobrevive a la supresión
            // (art. 17). No-op en instalaciones nuevas (payloads ya sin email); `payload_hash` intacto.
            DB::table('audit_logs')
                ->where('action', 'orders.email_resent')
                ->where('target_type', (new Order)->getMorphClass())
                ->whereIn('target_id', $this->orders()->select('id'))
                ->update(['payload' => null]);

            // RGPD (recomendación A, 2026-06-15): las filas de `audit_logs` donde el titular fue el
            // ACTOR (`user_id`) guardan su IP y user-agent — dato personal (TJUE Breyer) que sobrevivía
            // a la supresión (art. 17). Las nulificamos (minimización en el borrado). Solo donde es el
            // ACTOR (no donde es el TARGET: ahí la IP es del operador, no suya). `user_id` —ya apuntando
            // a la cuenta anonimizada—, acción, target y `payload_hash` se conservan (integridad del rastro).
            DB::table('audit_logs')
                ->where('user_id', $this->getKey())
                ->where(fn ($q) => $q->whereNotNull('ip')->orWhereNotNull('user_agent'))
                ->update(['ip' => null, 'user_agent' => null]);

            // A10: el token de reset aún lleva el email ORIGINAL en claro → rastro identificable tras
            // la supresión. Se borra dentro de la misma transacción.
            DB::table('password_reset_tokens')->where('email', $originalEmail)->delete();

            $this->forceFill([
                'name' => 'Cliente eliminado',
                'email' => 'deleted_'.$this->getKey().'@'.self::ANONYMIZED_EMAIL_DOMAIN,
                'phone' => null,
                'locale' => 'es',
                'password' => Str::random(60),                  // hash aleatorio → login imposible
                'remember_token' => null,
                'email_verified_at' => null,
                'pending_email' => null,
                'pending_email_sent_at' => null,
                'last_login_at' => null,
                'marketing_opt_in' => false,
                'privacy_accepted_at' => null,
                'terms_accepted_at' => null,
                'waiver_accepted_at' => null,
            ])->save();

            // A2: invalida TODAS las credenciales del titular —sesiones y tokens de API—. El cambio
            // de password NO basta (el guard web no monta AuthenticateSession), así que una
            // baja/baneo dejaría la sesión viva. Centralizado aquí → lo garantizan AMBAS vías
            // (panel `ViewUser` + self-service `DeleteAccount`). Los tokens entran en Fase 3 · paso
            // 3a: sin ellos, un Bearer sobreviviría a la supresión del art. 17.
            $this->revokeAllAccess();
        });

        return true;
    }

    /**
     * Roles asignados a este usuario.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Consentimientos legales aceptados por este usuario.
     *
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * Pedidos de entradas de este usuario (Fase 5).
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Entradas emitidas a este usuario (a través de sus pedidos).
     *
     * @return HasManyThrough<Ticket, Order, $this>
     */
    public function tickets(): HasManyThrough
    {
        return $this->hasManyThrough(Ticket::class, Order::class);
    }

    /**
     * Idioma preferido del usuario: los correos (verificación, etc.) se envían
     * en este idioma (el que eligió al registrarse). Ver #40.
     */
    public function preferredLocale(): string
    {
        return $this->locale ?? config('app.locale');
    }

    /**
     * ¿Tiene el usuario el rol indicado (por nombre)?
     */
    public function hasRole(string $name): bool
    {
        return $this->roles()->where('name', $name)->exists();
    }

    /**
     * ¿Tiene el usuario el permiso indicado? El rol `admin` es super-admin
     * (puede todo); el resto solo lo que conceden los permisos de sus roles.
     */
    public function hasPermission(string $name): bool
    {
        if ($this->hasRole('admin')) {
            return true;
        }

        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $name))
            ->exists();
    }

    /**
     * Gate canónico de Filament: ¿puede este usuario entrar a este panel?
     *
     * Defense in depth: el middleware `RequiresStaffOrAdmin` ya cierra en `/admin/*`,
     * y este hook lo cierra a nivel de aplicación de Filament. Si por error de
     * configuración el middleware no se aplica a una ruta concreta, Filament sigue
     * negando el acceso aquí. Ver `docs/PLAN-FASE-7-PANEL.md` §1.3.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin') || $this->hasRole('staff');
    }
}
