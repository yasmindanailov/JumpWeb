<?php

namespace App\Domain\Identity\Models;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Ticket;
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

#[Fillable([
    'name', 'email', 'password', 'phone', 'locale', 'panel_locale', 'last_login_at',
    'marketing_opt_in', 'privacy_accepted_at', 'terms_accepted_at', 'waiver_accepted_at',
    'pending_email', 'pending_email_sent_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasLocalePreference, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

            // A2: invalida TODAS las sesiones activas del titular. El cambio de password NO basta (el
            // guard web no monta AuthenticateSession), así que una baja/baneo dejaría la sesión viva.
            // Centralizado aquí → lo garantizan AMBAS vías (panel `ViewUser` + self-service `DeleteAccount`).
            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table', 'sessions'))
                    ->where('user_id', $this->getKey())
                    ->delete();
            }
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
