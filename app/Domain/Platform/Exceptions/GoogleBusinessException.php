<?php

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

/**
 * La conexión con la ficha de Google no se pudo completar
 * (`docs/specs/google-business-profile.md` §4.2).
 *
 * ⚠️ **A diferencia de `GoogleAuthException`, aquí el motivo SÍ se le enseña a quien lo ve**, y no es
 * una incoherencia: al visitante de la web no le sirve saber que un `aud` no cuadra, pero **quien está
 * al otro lado de esta pantalla es el admin del parque, y es quien tiene que arreglarlo**. «No has
 * concedido el permiso de la ficha» y «Google no ha devuelto token de refresco» se arreglan de formas
 * distintas, y callárselo le deja pulsando el mismo botón.
 *
 * ⚠️⚠️ **Lo que nunca sale es el material**: el mensaje nombra el motivo, jamás el token, el código ni
 * el cuerpo de la respuesta de Google (§4.2·6).
 */
final class GoogleBusinessException extends RuntimeException
{
    /** Faltan las credenciales del cliente central: no hay nada que canjear. */
    public const NOT_CONFIGURED = 'not_configured';

    /** Google contestó al canje con un error (código caducado, ya usado, `code_verifier` que no cuadra). */
    public const TOKEN_EXCHANGE_FAILED = 'token_exchange_failed';

    /**
     * El canje fue bien pero **no vino `refresh_token`**.
     *
     * ⚠️ Es el fallo MÁS probable de esta feature y el más silencioso: Google solo lo entrega con
     * `access_type=offline` **y** un consentimiento nuevo. Si la cuenta ya había autorizado antes, una
     * ida sin `prompt=consent` vuelve con un token de acceso y nada más — y una conexión sin token de
     * refresco es una conexión que muere en una hora. **No se guarda nada** (§4.2·2).
     */
    public const MISSING_REFRESH_TOKEN = 'missing_refresh_token';

    /**
     * El consentimiento de Google es **granular**: la persona puede desmarcar el permiso y seguir
     * adelante. Sin `business.manage` concedido no hay ficha que leer.
     */
    public const SCOPE_NOT_GRANTED = 'scope_not_granted';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $reason, string $detail = ''): self
    {
        return new self($reason, trim("Conexión con Google rechazada: {$reason}. {$detail}"));
    }
}
