<?php

namespace App\Http\Auth;

use App\Domain\Identity\Contracts\SocialProfile;

/**
 * **La custodia entre las dos peticiones** del retorno de Google (`docs/specs/auth-con-google.md`
 * §6.3·2 y §6.3·3).
 *
 * Entrar con Google son dos peticiones separadas por un viaje al navegador y a Google, y entre ellas
 * hay que recordar dos cosas: el **reto** que demuestra que la vuelta corresponde a nuestra ida, y
 * —cuando no hay cuenta todavía— el **perfil verificado** mientras la persona completa el alta.
 *
 * ⚠️⚠️ **Las dos viven en la sesión del SERVIDOR, jamás en campos del formulario.** Si el perfil
 * viajara por el navegador, cualquiera crearía una cuenta con la identidad verificada de otro — y a
 * esa cuenta se le firma un descargo probatorio. Es la misma doctrina que el `waiver_document_id`:
 * *el servidor solo emite la aceptación con el identificador que él mismo sirvió*.
 *
 * ⚠️ **Un solo uso y con caducidad.** El reto se borra SIEMPRE al comprobarlo, acierte o falle: un
 * reto que sobreviviera a un intento fallido sería un reto reutilizable, que es justo lo que el
 * `state` existe para impedir. Y las dos claves caducan por su cuenta, sin depender de que alguien
 * termine el flujo.
 *
 * ⚠️ Las horas se marcan con el reloj del FRAMEWORK (`now()`), nunca con `time()`: con `time()` las
 * dos caducidades quedan fuera del alcance de `travel()` y de la auditoría del reloj de la suite, o
 * sea **sin poder probarse**. Se descubrió al mutar: quitar la caducidad del reto dejaba la suite
 * verde porque no había ningún caso capaz de envejecerlo.
 */
final readonly class GoogleAuthSession
{
    /** El reto de la ida: `state`, `nonce` y a dónde volver. */
    private const CHALLENGE_KEY = 'auth.google.challenge';

    /** El perfil verificado que espera a la pantalla de alta. */
    private const PROFILE_KEY = 'auth.google.profile';

    /**
     * Lo que puede tardar una persona en elegir su cuenta en Google y aceptar. Quince minutos es
     * holgado para el caso normal y corto para cualquier otra cosa.
     */
    public const CHALLENGE_TTL_SECONDS = 900;

    /**
     * Lo que puede tardar en rellenar la pantalla de §7 (teléfono, condiciones, descargo). Media hora
     * porque hay que leer un texto legal; pasada, se vuelve a empezar por Google, que no cuesta nada.
     */
    public const PROFILE_TTL_SECONDS = 1800;

    /** Entrar o darse de alta: la intención por defecto, la que existía antes de `#347`. */
    public const INTENT_ENTER = 'enter';

    /**
     * **Vincular a la cuenta en la que ya se está** (`#347`, §21.3). Viaja en el reto y no en la URL
     * de vuelta por la misma razón que el `nonce`: lo que decide qué se hace al volver no puede ser
     * algo que ponga quien vuelve.
     */
    public const INTENT_LINK = 'link';

    /**
     * Abre el reto y devuelve lo que hay que mandarle a Google.
     *
     * `random_bytes` explícito y no un ayudante de cadenas: los dos valores son secretos de un solo
     * uso y quien los lea tiene que ver de dónde sale su aleatoriedad.
     *
     * ⚠️⚠️ **El `holder` es la mitad que hace segura la vinculación.** El reto anota QUIÉN la pidió, y
     * la vuelta comprueba que sigue siendo el mismo: entre las dos peticiones caben un `logout` y un
     * `login` con otra cuenta —en un dispositivo compartido es lo normal—, y sin esta anotación el
     * vínculo aterrizaría en la cuenta equivocada **sin que nada fallara**.
     *
     * @return array{state: string, nonce: string}
     */
    public static function startChallenge(string $intended, string $intent = self::INTENT_ENTER, ?int $holder = null): array
    {
        $challenge = [
            'state' => bin2hex(random_bytes(32)),
            'nonce' => bin2hex(random_bytes(32)),
            'intended' => $intended,
            'intent' => $intent,
            'holder' => $holder,
            'at' => now()->getTimestamp(),
        ];

        session([self::CHALLENGE_KEY => $challenge]);

        return ['state' => $challenge['state'], 'nonce' => $challenge['nonce']];
    }

    /**
     * Comprueba el `state` de la vuelta y **consume el reto pase lo que pase**.
     *
     * @return array{nonce: string, intended: string, intent: string, holder: int|null}|null
     *                                                                                       `null` = esta vuelta no corresponde a ninguna ida nuestra: no se hace nada.
     */
    public static function consumeChallenge(?string $state): ?array
    {
        $challenge = session(self::CHALLENGE_KEY);
        session()->forget(self::CHALLENGE_KEY);

        if (! is_array($challenge) || ! is_string($state) || $state === '') {
            return null;
        }

        $stored = $challenge['state'] ?? null;
        $nonce = $challenge['nonce'] ?? null;
        $at = $challenge['at'] ?? null;

        if (! is_string($stored) || ! is_string($nonce) || ! is_int($at)) {
            return null;
        }

        if ($at + self::CHALLENGE_TTL_SECONDS < now()->getTimestamp()) {
            return null;
        }

        if (! hash_equals($stored, $state)) {
            return null;
        }

        $intent = $challenge['intent'] ?? null;
        $holder = $challenge['holder'] ?? null;

        return [
            'nonce' => $nonce,
            'intended' => is_string($challenge['intended'] ?? null) ? $challenge['intended'] : '',
            // ⚠️ Una intención que no reconozcamos cae a ENTRAR, que es la conducta de siempre. Lo que
            // no puede pasar es que un reto viejo —de una sesión abierta antes del despliegue— llegue
            // con la clave ausente y el `match` del controlador se estrelle.
            'intent' => $intent === self::INTENT_LINK ? self::INTENT_LINK : self::INTENT_ENTER,
            'holder' => is_int($holder) ? $holder : null,
        ];
    }

    /** Guarda el perfil ya verificado mientras la persona completa su alta. */
    public static function rememberProfile(SocialProfile $profile): void
    {
        session([self::PROFILE_KEY => $profile->toSession() + ['at' => now()->getTimestamp()]]);
    }

    /**
     * El perfil que espera, **sin consumirlo**: es lo que necesita la pantalla para pintarse (y para
     * repintarse si la persona se deja un campo).
     */
    public static function peekProfile(): ?SocialProfile
    {
        $row = session(self::PROFILE_KEY);

        if (! is_array($row) || ! is_int($row['at'] ?? null)) {
            return null;
        }

        if (now()->getTimestamp() > $row['at'] + self::PROFILE_TTL_SECONDS) {
            self::forgetProfile();

            return null;
        }

        return SocialProfile::fromSession($row);
    }

    /**
     * El perfil, **y lo olvida**: lo llama el alta al crear la cuenta. Que el envío consuma la foto
     * es lo que impide que un segundo envío —dos pestañas, doble clic— cree una segunda cuenta.
     */
    public static function consumeProfile(): ?SocialProfile
    {
        $profile = self::peekProfile();
        self::forgetProfile();

        return $profile;
    }

    public static function forgetProfile(): void
    {
        session()->forget(self::PROFILE_KEY);
    }
}
