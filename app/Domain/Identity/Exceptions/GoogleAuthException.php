<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * El retorno de Google no se pudo convertir en una afirmación de confianza
 * (`docs/specs/auth-con-google.md` §6.3).
 *
 * ⚠️ **Una sola excepción con MOTIVO, y el motivo no se le enseña a nadie**: al visitante se le dice
 * «no hemos podido entrar con Google, inténtalo otra vez» y el motivo va al log. Distinguirlos en
 * pantalla no le sirve de nada a quien llega —no puede arreglar un `aud` que no cuadra— y sí le
 * serviría a quien esté probando por dónde se cuela.
 */
final class GoogleAuthException extends RuntimeException
{
    /** Google contestó al canje con un error (código caducado, ya usado, secreto que no cuadra). */
    public const TOKEN_EXCHANGE_FAILED = 'token_exchange_failed';

    /** La respuesta del canje no traía `id_token`. */
    public const MISSING_ID_TOKEN = 'missing_id_token';

    /** El `id_token` no tiene la forma de un JWT o su carga no es JSON. */
    public const MALFORMED_ID_TOKEN = 'malformed_id_token';

    /** El emisor no es Google. */
    public const BAD_ISSUER = 'bad_issuer';

    /** El token está emitido para OTRA aplicación: la defensa central contra un token fabricado. */
    public const BAD_AUDIENCE = 'bad_audience';

    /** Caducado, o emitido en el futuro más allá de la tolerancia de reloj. */
    public const EXPIRED = 'expired';

    /** El `nonce` no es el que esta sesión pidió: el token es de otra petición (replay). */
    public const BAD_NONCE = 'bad_nonce';

    /** Sin `sub` o sin correo no hay identidad que resolver. */
    public const INCOMPLETE_CLAIMS = 'incomplete_claims';

    /** La instalación no tiene claves configuradas: no hay nada que canjear. */
    public const NOT_CONFIGURED = 'not_configured';

    private function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public static function because(string $reason, string $detail = ''): self
    {
        return new self($reason, trim("Retorno de Google rechazado: {$reason}. {$detail}"));
    }
}
