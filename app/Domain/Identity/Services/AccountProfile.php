<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\ProfileUpdateResult;
use App\Domain\Identity\Contracts\ResendResult;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\SiteLocales;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyPendingEmail;
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

    public function __construct(private readonly AccountCredentials $credentials) {}

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
     * @param  array{name: string, phone: string, locale: string, email: string}  $data
     */
    public function apply(User $user, array $data, ?string $currentPassword, string $ip): ProfileUpdateResult
    {
        $email = Str::lower(trim($data['email']));
        $emailChanged = $email !== $user->email;

        if ($emailChanged) {
            $verdict = $this->credentials->verify($user, (string) $currentPassword, $ip);

            if ($verdict->failed()) {
                return $verdict->wasRateLimited()
                    ? ProfileUpdateResult::rateLimited($verdict->retryAfter)
                    : ProfileUpdateResult::wrongPassword();
            }
        }

        // Los campos NO sensibles se aplican siempre, cambie o no el correo.
        $user->name = $data['name'];
        $user->phone = $data['phone'];
        $user->locale = $data['locale'];

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
            $user->notify(new VerifyPendingEmail);
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
    public function resendPendingEmail(User $user): ResendResult
    {
        if (! $user->pending_email) {
            return ResendResult::nothingPending();
        }

        $key = 'pending-email-resend:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return ResendResult::throttled(RateLimiter::availableIn($key));
        }

        RateLimiter::hit($key, self::RESEND_WINDOW);

        // ⚠️ Se **resella** el envío: la ventana de validez del enlace cuenta desde el último, no
        // desde el primero. Sin esto, reenviar entregaría un enlace que caduca antes de llegar.
        $user->forceFill(['pending_email_sent_at' => now()])->save();
        $user->notify(new VerifyPendingEmail);

        Log::info('account.email_change_resent', ['user_id' => $user->id]);

        return ResendResult::sent();
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
