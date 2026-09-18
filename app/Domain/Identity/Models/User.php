<?php

namespace App\Domain\Identity\Models;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\Ticket;
use App\Domain\Platform\Services\AuditLogger;
use App\Notifications\PasswordReset;
use App\Notifications\VerifyEmailAddress;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
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
     * Los roles que hacen de alguien EQUIPO en vez de cliente (#223).
     *
     * Es la misma definición que usa `canAccessPanel()`: quien entra al panel es equipo, y
     * quien no, es cliente. Vive aquí como constante para que las pestañas «Clientes» y
     * «Equipo» del panel no puedan desviarse del gate de acceso: la lista estaba escrita a
     * mano en un solo sitio y una segunda copia habría envejecido en silencio.
     *
     * ▶ `#320` añade `puerta`, y conviene saber por qué entra AQUÍ si justamente no navega el panel:
     * esta lista responde a «¿puede autenticarse en `/admin/login`?», no a «¿puede navegar?». La
     * página de login de Filament comprueba `canAccessPanel()` DENTRO de `authenticate()` y, si es
     * falsa, hace `logout()` y falla la validación con «estas credenciales no coinciden» — o sea que
     * dejar el rol fuera de aquí le cierra el LOGIN y se queda sin poder entrar ni a su propia
     * pantalla. Quien lo saca del panel es el middleware `RestrictsPuertaRole`, un paso después — se
     * nombra en prosa y NO con `{@see}` porque una anotación resoluble sería una flecha de Identity
     * hacia la capa HTTP, y `ModuleBoundariesTest` la caza (lo hizo). Y como EQUIPO también es
     * correcto: quien valida en la entrada trabaja aquí.
     *
     * @var array<int, string>
     */
    public const PANEL_ROLES = ['admin', 'staff', 'puerta'];

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
     * ❗❗ LOS DOS CORREOS DEL FRAMEWORK, AL MOLDE DEL PRODUCTO (`DECISIONES #508`).
     *
     * Hasta esta tanda salían los de `Illuminate\Auth\Notifications` tal cual: vestidos —pasan por
     * el mismo layout— pero **sin cabecera y sin línea de adelanto**, así que en la bandeja se
     * anunciaban con «¡Hola!», que es el defecto que abrió este carril. **No estaban en el
     * inventario de 23** porque el artboard contó carpetas y éstos no viven en ninguna.
     *
     * ⚠️ Las subclases solo cambian `buildMailMessage($url)`: el token, la firma, la caducidad y la
     * ruta los sigue generando el framework. Aquí solo se dice **cuál** se manda.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordReset($token));
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailAddress);
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
    public function revokeAllAccess(string $cardReason = CustomerCard::REASON_REVOKED): void
    {
        $this->purgeSessions(exceptCurrent: false);
        $this->tokens()->delete();
        $this->revokeCards($cardReason);
        $this->purgeIdentities();
    }

    /**
     * Fase 6 · subsistema A — el CARNÉ QR es la siguiente credencial que llega después de Sanctum, y
     * entra aquí desde su primer commit (`specs/identidad-qr-puerta.md` §4.4, §9.2 A·2): es
     * literalmente el modo de fallo que `RGPD-06` existe para impedir (cuatro copias de la purga y
     * ninguna revocaba tokens). Revocar es escribir `revoked_at`, nunca borrar (historial); se audita
     * SOLO si había alguno activo, con el motivo y sin el token.
     *
     * ⚠️ `revokeOtherAccess()` NO lo llama a propósito: un cambio de contraseña no debe matar el carné
     * impreso en casa —escanear no autentica (§4.2)—; rotarlo es un acto explícito del titular.
     */
    private function revokeCards(string $reason): void
    {
        $count = $this->cards()->whereNull('revoked_at')->update([
            'revoked_at' => now(),
            'revoked_reason' => $reason,
        ]);

        if ($count > 0) {
            AuditLogger::log('cards.revoked', $this, ['count' => $count, 'reason' => $reason]);
        }
    }

    /**
     * Las IDENTIDADES EXTERNAS caen con `revokeAllAccess()` y **sobreviven** a `revokeOtherAccess()`
     * (`RGPD-06`, `specs/auth-con-google.md` §11): exactamente el mismo criterio que el carné.
     *
     * ⚠️⚠️ **No es que el vínculo sea una credencial** —con la fila no se entra a ninguna parte—: es
     * que `revokeAllAccess()` es la palanca de «me han entrado», y un vínculo plantado por quien te
     * tomó la cuenta sería una puerta trasera que **el restablecimiento de contraseña no cerraría**.
     * Quien se defiende tiene que echar a todo el mundo, no solo a quien tenga la contraseña.
     *
     * ⚠️ Un cambio VOLUNTARIO de contraseña no lo toca, por la misma razón que no toca el carné: no
     * es una defensa, es mantenimiento.
     *
     * ▶ **Consecuencia conocida y aceptada**: tras un reset, la siguiente entrada con Google
     * **vuelve a vincular sola** (la cuenta está verificada) y manda su aviso. Es ruido, no un
     * bloqueo — y es el precio de que la palanca de emergencia no deje nada abierto.
     */
    private function purgeIdentities(): void
    {
        $this->identities()->delete();
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
     * Revoca ÚNICAMENTE el token con el que se hace esta petición, si lo hay.
     *
     * Es el «cerrar sesión» de un cliente por Bearer: cierra su propia credencial y no toca las
     * demás. Vive aquí, y no en el controlador que lo usa, por la regla del paso 3a —invalidar
     * credenciales tiene un solo sitio—; `ApiBoundariesTest` lo señaló en cuanto se intentó lo
     * contrario, que es exactamente para lo que está esa guarda.
     *
     * Devuelve `false` cuando la petición no viene por token (sesión de navegador): ahí no hay
     * ninguno que revocar y quien llama decide qué hacer con la sesión.
     */
    public function revokeCurrentAccessToken(): bool
    {
        $token = $this->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            return false;
        }

        return (bool) $token->delete();
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
            // ⚠️ Fase 6 · waiver: el registro PROBATORIO (`waiver_signatures`) NO se toca, a propósito
            // —conservación con tratamiento restringido y plazo, art. 17.3.e + 18 (`RGPD-01`,
            // `specs/waiver-probatorio.md` §4.6, `WaiverRetentionTest`)—. Un documento sin titular
            // vinculado no probaría nada; el sello `waiver_accepted_at` de abajo sí se nulifica,
            // porque es presentación, no prueba.
            $this->consents()->delete();
            $this->roles()->detach();

            // Las IDENTIDADES EXTERNAS (`specs/auth-con-google.md` §11) las purga `revokeAllAccess()`
            // al final de esta misma transacción, igual que el carné: aquí no se repiten.
            //
            // ⚠️⚠️ Lo que hay que saber de ellas es que se **BORRAN**, no se redactan como el resto de
            // esta purga: una fila redactada dejaría el `sub` ocupado en `UNIQUE(provider, provider_id)`
            // y **esa persona no podría volver a registrarse con su Google nunca más** — el derecho de
            // supresión le habría cerrado la puerta de entrada en vez de devolverle sus datos.
            //
            // ⚠️ Y **no basta con el `cascadeOnDelete` de su FK**: esto no borra la fila de `users`, así
            // que ese cascade no se dispara nunca por esta vía.

            // Fase 6 · menores a cargo (`specs/menores-a-cargo.md` §5, `RGPD-01` ampliada): cada
            // persona a cargo sigue el régimen de su waiver. Con una firma detrás se CONSERVA vinculada
            // bajo el mismo tratamiento restringido que la firma (desvinculada: sale de toda superficie;
            // la poda la retira cuando su última firma vence). Sin firma ni referencias es el nombre y la
            // fecha de nacimiento de un menor sin nada que los justifique: se borra de verdad.
            // Tanda 4 (`specs/menores-a-cargo.md` §9.9.3 D6): las ENTRADAS ASIGNADAS a sus menores se
            // borran ANTES —son la misma clase de dato que `guest_data`, PII de un menor atada a una
            // visita— y así cada menor sigue después la regla de arriba con solo su firma como referencia.
            DependentAssignment::query()
                ->whereIn('dependent_id', $this->dependents()->select('id'))
                ->delete();

            $this->dependents()->get()->each(
                static fn (Dependent $dependent) => $dependent->hasReferences() ? $dependent->unlink() : $dependent->delete()
            );

            // RGPD art. 17 — PII de TERCEROS (auditoría Fase 1, A1): los datos de invitados de los
            // pedidos del titular (nombres y ALERGIAS de menores = datos de salud, art. 9) viven en
            // `order_items.guest_data`/`event_data`. El deber fiscal AEAT cubre la FACTURA (importes,
            // fechas, código), NO la lista de invitados → la vaciamos. Los pedidos se conservan.
            OrderItem::whereIn('order_id', $this->orders()->select('id'))
                ->where(fn ($q) => $q->whereNotNull('guest_data')->orWhereNotNull('event_data'))
                ->update(['guest_data' => null, 'event_data' => null]);

            // ⚠️ Y la INVITACIÓN DIGITAL de esas mismas reservas (`specs/celebracion-e-invitacion.md`
            // §4.4, `DECISIONES #577`). Es la MISMA clase de dato que acaba de vaciarse arriba:
            // `party_invitations.honoree_name` es el nombre de un menor, y cada fila de
            // `invitation_replies` lleva el nombre de un niño de OTRA familia, lo que su padre
            // contestó y —si el pack lo pedía— sus alergias (art. 9).
            // ❗ Sin estas dos líneas la supresión dejaba PII de menores **justo donde acababa de
            // vaciarla**: el titular borraba su cuenta y el nombre del homenajeado seguía en pie.
            //
            // ⚠️⚠️ **Por tabla y con las DOS consultas, sin apoyarse en la cascada**, por dos razones
            // independientes: `User` solo tiene permitido importar `Order`, `OrderItem` y `Ticket` de
            // Booking (`ModuleBoundariesTest`), y el art. 17 no puede depender de una acción de clave
            // foránea que por esta vía **nadie dispara** — la misma lección que `user_identities` dejó
            // escrita unas líneas más arriba.
            //
            // ▶ **Las respuestas van PRIMERO**, y ese orden es la propiedad: así el borrado no
            // necesita que la cascada de la invitación funcione. Al caer arrastran a `null` el
            // `invitation_reply_id` de los justificantes (`nullOnDelete`), que es lo correcto: la
            // firma **se conserva** —es la prueba de una visita que ocurrió, art. 17.3.e, `RGPD-01`—
            // y ese vínculo **no entra en su hash**, así que la cadena sigue verificando.
            $invitationItemIds = OrderItem::whereIn('order_id', $this->orders()->select('id'))->select('id');

            DB::table('invitation_replies')->whereIn('order_item_id', $invitationItemIds)->delete();
            DB::table('party_invitations')->whereIn('order_item_id', $invitationItemIds)->delete();

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
                'waiver_pending_document_id' => null,   // la aceptación pendiente es PII del alta (#179)
                'waiver_pending_channel' => null,
                'waiver_pending_ip' => null,
                'waiver_pending_user_agent' => null,
            ])->save();

            // A2: invalida TODAS las credenciales del titular —sesiones y tokens de API—. El cambio
            // de password NO basta (el guard web no monta AuthenticateSession), así que una
            // baja/baneo dejaría la sesión viva. Centralizado aquí → lo garantizan AMBAS vías
            // (panel `ViewUser` + self-service `DeleteAccount`). Los tokens entran en Fase 3 · paso
            // 3a: sin ellos, un Bearer sobreviviría a la supresión del art. 17. Y el carné QR en
            // Fase 6 · A (`RGPD-06` ampliada): aquí solo se le pone el motivo.
            $this->revokeAllAccess(CustomerCard::REASON_ANONYMIZED);
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
     * Fase 6 · waiver — el registro PROBATORIO de las firmas **DE ESTE TITULAR**: las suyas y las que
     * hizo en nombre de sus menores a cargo (`specs/waiver-probatorio.md` §4.3). Append-only y
     * **sobrevive a `anonymize()`** bajo régimen restringido: NO se lista en ninguna superficie normal
     * (bloque de cuenta, `GET /me/consents`, export del art. 20). Lo que el titular VE es `consents()`.
     *
     * ❗❗ **Acotada a `SUBJECTS_OF_HOLDER`, y eso EVITA UNA FUGA, no es una optimización.** Desde el
     * justificante de menor invitado (`specs/waiver-por-reserva.md` §4.5) hay filas con este
     * `user_id` que **no son de este titular**: él es el RESPONSABLE de la reserva, pero quien firma
     * es otro adulto y el sujeto es el hijo de otra familia. Sin este filtro, todo lo que signifique
     * «las firmas de este usuario» las publicaría — medido antes de acotarlo: `GET /me/waiver`
     * devolvía el nombre del menor con `dependent_id: 0` **y servía su PDF**, que lleva el nombre,
     * el teléfono y el correo del otro padre.
     *
     * ▶ **La relación es fail-closed a propósito**: lo ancho hay que pedirlo por su nombre
     * ({@see guardianSignatures()}), no al revés. Un consumidor nuevo que no sepa nada de esto
     * hereda la conducta segura.
     *
     * ⚠️ Quien necesita TODAS las filas ancladas a esta cuenta —`WaiverChain`, `WaiverSigner`— consulta
     * el modelo directamente, no esta relación.
     *
     * @return HasMany<WaiverSignature, $this>
     */
    public function waiverSignatures(): HasMany
    {
        return $this->hasMany(WaiverSignature::class)
            ->whereIn('subject_type', WaiverSignature::SUBJECTS_OF_HOLDER);
    }

    /**
     * Fase 6 · los JUSTIFICANTES que este titular ANCLA como responsable de sus reservas, y que **NO
     * son suyos** (`specs/waiver-por-reserva.md` §4.1, §4.5): cada uno lo firmó un adulto sin cuenta
     * por un menor invitado.
     *
     * ⚠️ **No es lo mismo que `waiverSignatures()` y no se pueden mezclar.** Lo que el responsable
     * puede ver de aquí está acotado por `[DECIDIDO owner]` §7·4: **el nombre del menor y si está
     * firmado — nunca los datos de contacto del adulto que firmó**. Su superficie es la del pedido
     * (T3), no la de «mis firmas».
     *
     * @return HasMany<WaiverSignature, $this>
     */
    public function guardianSignatures(): HasMany
    {
        return $this->hasMany(WaiverSignature::class)
            ->where('subject_type', WaiverSignature::SUBJECT_GUEST_MINOR);
    }

    /**
     * Fase 6 · menores a cargo — las PERSONAS A CARGO que este titular declaró
     * (`specs/menores-a-cargo.md` §4.1–§4.4): incluye las DESVINCULADAS (`removed_at`), que siguen
     * apuntando aquí porque hay un waiver firmado detrás. Lo que el titular VE es `->active()`.
     *
     * @return HasMany<Dependent, $this>
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(Dependent::class);
    }

    /**
     * Fase 6 · subsistema A — los CARNÉS QR del titular, activos y revocados (`specs/identidad-qr-puerta.md`
     * §4.4: historial de rotación). El activo lo da `CustomerCards::activeFor()`.
     *
     * @return HasMany<CustomerCard, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(CustomerCard::class);
    }

    /**
     * Fase 6 · subsistema A — las VISITAS acreditadas en la puerta (§8.3), una por día.
     *
     * @return HasMany<CustomerVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(CustomerVisit::class);
    }

    /**
     * Las IDENTIDADES EXTERNAS de esta cuenta (`specs/auth-con-google.md` §6.2): hoy, como mucho una
     * de Google.
     *
     * ⚠️ **No son credenciales** —con la fila no se entra a ninguna parte— y aun así **caen en
     * `revokeAllAccess()`**: ver el porqué en `purgeIdentities()`. Sobreviven a `revokeOtherAccess()`,
     * que es el mismo trato que recibe el carné (`RGPD-06`).
     *
     * @return HasMany<UserIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
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
     * Defense in depth: el middleware `RequiresPanelRole` ya cierra en `/admin/*`,
     * y este hook lo cierra a nivel de aplicación de Filament. Si por error de
     * configuración el middleware no se aplica a una ruta concreta, Filament sigue
     * negando el acceso aquí. Ver `docs/PLAN-FASE-7-PANEL.md` §1.3.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        foreach (self::PANEL_ROLES as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }

        return false;
    }

    /** Cuentas de EQUIPO: las que pueden entrar al panel. */
    public function scopeTeamMembers(Builder $query): Builder
    {
        return $query->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', self::PANEL_ROLES));
    }

    /** Cuentas de CLIENTE: todas las demás (incluidas las que aún no tienen rol). */
    public function scopeCustomers(Builder $query): Builder
    {
        return $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->whereIn('name', self::PANEL_ROLES));
    }
}
