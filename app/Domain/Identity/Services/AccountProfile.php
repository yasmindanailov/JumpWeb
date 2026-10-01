<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\EmailChangeOutcome;
use App\Domain\Identity\Contracts\ProfileUpdateResult;
use App\Domain\Identity\Contracts\Reconfirmation;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\LoginCode;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\SiteLocales;
use App\Notifications\EmailChangeCompleted;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyPendingEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * **El perfil del titular y el ciclo del cambio de correo** (`specs/area-cliente.md` §9, paso 7).
 *
 * Reúne lo que era dominio dentro de `Livewire\Account\UpdateProfile`, que es —con diferencia— la
 * gestión más grande: el patrón `pending_email` entero (pedir, cancelar, reenviar con su cooldown),
 * **dos** notificaciones, la doble comprobación de unicidad y el manejo de la carrera de UNIQUE.
 * Reescribir eso en la API habría sido garantizar que las dos versiones se separaran.
 *
 * ⚠️⚠️ **El correo NO se cambia al guardar: se SOLICITA.** El email vigente sigue intacto y lo nuevo
 * vive en `pending_email` hasta que el titular abre el enlace que se le manda al buzón nuevo. Esa es
 * la defensa de fondo de todo este ciclo: si alguien entra en una sesión ajena y cambia el correo, el
 * dueño **no pierde el acceso** —y además recibe el aviso al buzón viejo—.
 */
class AccountProfile
{
    /** Un solo reenvío por minuto. Es el cooldown que ya aplicaba la web. */
    private const RESEND_WINDOW = 60;

    /**
     * Cuánto vale el enlace de confirmación del correo nuevo.
     *
     * ⚠️ **Vivía en `Http\Controllers\Account\EmailChangeController` y baja aquí** porque es una
     * regla de DOMINIO, no de una superficie: la usan el controlador que valida el enlace, la página
     * que dice los minutos que quedan y ahora la API, que publica la caducidad para que el cliente no
     * tenga que recomponerla. Tres consumidores y una sola definición.
     */
    public const PENDING_EMAIL_HOLD_MINUTES = 60;

    /** Cuándo caduca el enlace pendiente de este titular, o `null` si no hay ninguno. */
    public static function pendingEmailExpiresAt(User $user): ?Carbon
    {
        if (! $user->pending_email || ! $user->pending_email_sent_at) {
            return null;
        }

        return $user->pending_email_sent_at->copy()->addMinutes(self::PENDING_EMAIL_HOLD_MINUTES);
    }

    /** Fallos seguidos al confirmar el código del correo nuevo, por (titular, IP), antes del bloqueo (A2b, `#856`). */
    public const MAX_CONFIRM_ATTEMPTS = 5;

    public function __construct(
        private readonly AccountCredentials $credentials,
        private readonly LoginCodes $codes,
    ) {}

    /**
     * Las reglas de validación del perfil, **para las dos superficies**.
     *
     * ⚠️ **La doble comprobación de unicidad es la parte que no se puede perder**: se mira `email`
     * (ya en uso) **y** `pending_email` de otros (alguien lo está reclamando). Sin la segunda, dos
     * titulares podrían pedir el mismo correo y el segundo enlace que se confirmara chocaría contra
     * la UNIQUE de la base. El `ignore()` permite re-pedir el propio pendiente.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function rules(User $user): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            // TP·1 (`#792`): opcional y con `sometimes` —AUSENTE no cambia nada, `null` la borra—. La isla guarda Mi cuenta
            // sin este campo (`isla/cuenta/useAjustesCuenta.js`): con la regla contraria, cada guardado suyo la borraría.
            'born_on' => ['sometimes', ...BirthDatePolicy::rules()],
            'locale' => ['required', Rule::in(SiteLocales::SUPPORTED)],
            'email' => [
                'required', 'string', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
                Rule::unique('users', 'pending_email')->ignore($user->id),
            ],
        ];
    }

    /**
     * Guarda el perfil y, si el correo cambia, **solicita** el cambio.
     *
     * ⚠️ **Se llama `apply()` y no `update()`, y no es capricho**: `ApiBoundariesTest` prohíbe la
     * escritura de dominio en la capa HTTP buscando llamadas a `->update()`, y **no puede distinguir**
     * un modelo de Eloquent de un servicio con ese mismo nombre. Un método de servicio llamado
     * `update` provoca esa confusión cada vez que alguien lo llama desde un controlador; renombrarlo
     * cuesta una palabra y no debilita la guarda con una excepción.
     *
     * ⚠️ **La reconfirmación solo se pide si cambia el correo**, igual que en la web: obligar a
     * escribir la contraseña para corregir una errata en el teléfono no defiende nada y hace que el
     * titular acabe evitando la pantalla.
     *
     * ▶ Desde la A2a (`#855`) se reconfirma con la contraseña **o con un código `confirm`** al correo de la cuenta.
     *
     * @param  array{name: string, phone: string, born_on?: ?string, locale: string, email: string}  $data  sin `born_on`, la fecha no se toca
     */
    public function apply(User $user, array $data, ?Reconfirmation $with, string $ip): ProfileUpdateResult
    {
        $email = Str::lower(trim($data['email']));
        $emailChanged = $email !== $user->email;

        if ($emailChanged) {
            $verdict = $this->credentials->verify($user, $with ?? Reconfirmation::password(''), $ip);

            if ($verdict->failed()) {
                return match (true) {
                    $verdict->wasRateLimited() => ProfileUpdateResult::rateLimited($verdict->retryAfter),
                    $verdict->wasWrongCode() => ProfileUpdateResult::wrongCode(),
                    default => ProfileUpdateResult::wrongPassword(),
                };
            }
        }

        // Los campos NO sensibles se aplican siempre, cambie o no el correo.
        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->locale = $data['locale'];

        if (array_key_exists('born_on', $data)) {
            $user->fill(['born_on' => BirthDatePolicy::normalize($data['born_on'])]);
        }

        if ($emailChanged) {
            $user->pending_email = $email;
            $user->pending_email_sent_at = now();
        }

        try {
            $user->save();
        } catch (QueryException $e) {
            // ⚠️ **La carrera es real**: `Rule::unique` mira un instante anterior al guardado, así que
            // dos titulares pueden pedir el mismo correo a la vez. La base es quien decide, y quien
            // pierde recibe un veredicto que la interfaz puede enseñar — no un 500.
            if ($this->isUniqueConstraintViolation($e)) {
                return ProfileUpdateResult::emailTaken();
            }

            throw $e;
        }

        if ($emailChanged) {
            // ⚠️ **DOS avisos, y el segundo es la defensa.** El primero va al buzón NUEVO con el
            // enlace; el segundo al VIEJO, para que el dueño se entere si esto no lo ha pedido él. El
            // correo nuevo viaja **enmascarado** ahí: un aviso cruzado no puede regalar la dirección
            // completa de otro buzón a quien lea el primero.
            // ▶ Desde la A2b (`#856`) el primero lleva además un CÓDIGO y sale tras la respuesta (`CodeMail`): quien lo
            // pidió lo está esperando en la pantalla.
            $this->sendNewEmailCode($user, $ip);
            $user->notify(new EmailChangeRequested(self::maskEmail($email)));

            Log::info('account.email_change_requested', ['user_id' => $user->id]);

            return ProfileUpdateResult::saved(emailChangeRequested: true);
        }

        Log::info('account.profile_updated', ['user_id' => $user->id]);

        return ProfileUpdateResult::saved();
    }

    /** Cancela el cambio pedido. Devuelve si había algo que cancelar. */
    public function cancelEmailChange(User $user): bool
    {
        if (! $user->pending_email) {
            return false;
        }

        $user->forceFill(['pending_email' => null, 'pending_email_sent_at' => null])->save();

        Log::info('account.email_change_cancelled', ['user_id' => $user->id]);

        return true;
    }

    /**
     * Reenvía la confirmación al buzón nuevo, con su cooldown.
     *
     * ⚠️ **Un reenvío por minuto**, y el limitador es aparte del de la contraseña: aquí no se está
     * adivinando nada, se está mandando correo — lo que se protege es el buzón del destinatario y el
     * coste del envío, no la cuenta.
     */
    public function resendPendingEmail(User $user, string $ip = ''): ResendResult
    {
        if (! $user->pending_email) {
            return ResendResult::nothingPending();
        }

        $key = 'pending-email-resend:'.$user->id;
        // ▶ Y por hora (A2b, `#856`): cada reenvío es ya un código nuevo al buzón que eligió quien pide el cambio —que puede
        // ser el de un tercero—; los números de los demás códigos.
        $hourKey = 'pending-email-resend-hour:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1) || RateLimiter::tooManyAttempts($hourKey, EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR)) {
            return ResendResult::throttled(EmailCodeLogin::secondsToWait([$key => 1, $hourKey => EmailCodeLogin::MAX_PER_EMAIL_PER_HOUR]));
        }

        RateLimiter::hit($key, self::RESEND_WINDOW);
        RateLimiter::hit($hourKey, 3600);

        // ⚠️ Se **resella** el envío: la ventana de validez del enlace cuenta desde el último, no
        // desde el primero. Sin esto, reenviar entregaría un enlace que caduca antes de llegar.
        $user->forceFill(['pending_email_sent_at' => now()])->save();
        $this->sendNewEmailCode($user, $ip);

        Log::info('account.email_change_resent', ['user_id' => $user->id]);

        return ResendResult::sent();
    }

    /**
     * **Confirma el correo nuevo con el CÓDIGO que llegó a ese buzón** (A2b de `specs/acceso-con-codigo.md` §4.9, `#856`):
     * la prueba de que el buzón es de quien pidió el cambio, escrita en el mismo dispositivo. Con un limitador propio por
     * (titular, IP) —este secreto no es el de reconfirmar— y los cinco intentos de cada código.
     */
    public function confirmPendingEmail(User $user, string $code, string $ip): EmailChangeOutcome
    {
        if (! $user->pending_email) {
            return EmailChangeOutcome::of(EmailChangeOutcome::NOTHING_PENDING);
        }

        $key = 'new-email-confirm:'.$user->id.'|'.$ip;
        if (RateLimiter::tooManyAttempts($key, self::MAX_CONFIRM_ATTEMPTS)) {
            return EmailChangeOutcome::rateLimited(RateLimiter::availableIn($key));
        }

        if (! $this->codes->consume((string) $user->pending_email, LoginCode::PURPOSE_NEW_EMAIL, $code)) {
            RateLimiter::hit($key, 60);

            return EmailChangeOutcome::of(EmailChangeOutcome::WRONG_CODE);
        }

        RateLimiter::clear($key);

        return $this->completeEmailChange($user);
    }

    /**
     * **Completa el cambio pedido**: el correo nuevo pasa a ser el de la cuenta, verificado. Lo usan el CÓDIGO (arriba) y el
     * ENLACE firmado (`EmailChangeController`), que antes lo hacía él mismo: bajó aquí TAL CUAL en la A2b (`#856`) para no
     * tener dos copias. Quien llama ya ha probado el buzón nuevo (el código, o la firma y el hash del enlace).
     */
    public function completeEmailChange(User $user): EmailChangeOutcome
    {
        if (! $user->pending_email || ! $user->pending_email_sent_at) {
            return EmailChangeOutcome::of(EmailChangeOutcome::NOTHING_PENDING);
        }

        // Caducidad de la solicitud (defensa adicional a la firma de la ruta). Por `pendingEmailExpiresAt()`, la MISMA cuenta
        // que publica la API: dos fórmulas de la ventana serían dos respuestas a «¿sigue valiendo?».
        $expiresAt = self::pendingEmailExpiresAt($user);
        if ($expiresAt === null || $expiresAt->lt(now())) {
            $user->forceFill(['pending_email' => null, 'pending_email_sent_at' => null])->save();

            return EmailChangeOutcome::of(EmailChangeOutcome::EXPIRED);
        }

        // Otro usuario pudo haber registrado ese email entre la solicitud y la confirmación.
        // UNIQUE de BD ya lo blindaría, pero damos un mensaje claro y limpiamos el pending.
        if (User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists()) {
            $user->forceFill(['pending_email' => null, 'pending_email_sent_at' => null])->save();

            return EmailChangeOutcome::of(EmailChangeOutcome::TAKEN);
        }

        $previousEmail = (string) $user->email;
        $newEmail = (string) $user->pending_email;

        $user->forceFill([
            'email' => $newEmail,
            'email_verified_at' => now(),       // implícitamente verificado (el cliente probó el buzón nuevo)
            'pending_email' => null,
            'pending_email_sent_at' => null,
        ])->save();

        // S-5 (`#181`): confirmar el correo NUEVO es verificarlo — y lo que espera a la verificación (la
        // aceptación pendiente del waiver) tiene que enterarse, como por el enlace del alta o por el cobro.
        event(new Verified($user));

        // Aviso al EMAIL VIEJO de que el cambio se consumó (cierre del loop anti-takeover, C-07).
        // Si la víctima ve este correo en su buzón original y no fue ella, sabe que la cuenta
        // fue tomada antes de que el atacante haga más daño. ⚠️ El correo viejo viaja DENTRO de la
        // notificación (va por la cola, y al volver de ella el titular ya tiene el nuevo, `#856`).
        $user->notify(new EmailChangeCompleted(self::maskEmail($newEmail), $previousEmail));

        Log::info('account.email_change_confirmed', ['user_id' => $user->id]);

        return EmailChangeOutcome::of(EmailChangeOutcome::CONFIRMED);
    }

    /** Un código nuevo al buzón NUEVO, y el correo con él tras la respuesta ({@see CodeMail}). Anula el anterior. */
    private function sendNewEmailCode(User $user, string $ip): void
    {
        $code = $this->codes->issue((string) $user->pending_email, LoginCode::PURPOSE_NEW_EMAIL, $ip);
        CodeMail::sendAfterResponse($user, new VerifyPendingEmail($code));
    }

    /**
     * `n****@dominio.com`: lo justo para que el dueño reconozca si fue él, sin publicar la dirección.
     *
     * ⚠️ **Es la implementación EXACTA que tenía `UpdateProfile`**, traída sin retocar. Al escribirla
     * de memoria salió otra —dejaba visible también la última letra— y habría cambiado el texto de un
     * correo que ya se está enviando en producción, sin que ningún test lo dijera: la notificación
     * recibe la cadena ya enmascarada y no comprueba su forma. Se copió mirándola, no recordándola.
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email, 2);

        if (count($parts) !== 2 || $parts[0] === '') {
            return $email;
        }

        return $parts[0][0].str_repeat('*', max(1, mb_strlen($parts[0]) - 1)).'@'.$parts[1];
    }

    /**
     * ¿Este fallo de la base es un choque de UNIQUE?
     *
     * ⚠️ Mira el código 1062 de MySQL **y** el texto de SQLite, porque la suite corre sobre SQLite en
     * memoria y producción sobre MySQL: comprobar solo uno dejaría la carrera sin cubrir justo en el
     * motor donde se prueba, o sin cubrir en el que importa.
     */
    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            || str_contains((string) $e->getMessage(), 'UNIQUE constraint failed');
    }
}
