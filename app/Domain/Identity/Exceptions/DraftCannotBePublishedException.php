<?php

namespace App\Domain\Identity\Exceptions;

use RuntimeException;

/**
 * Fase 6 · waiver — el bloqueante de la revisión (`specs/waiver-probatorio.md` §8.1) convertido en
 * MECANISMO: publicar es irreversible, así que un texto que todavía lleva un marcador de borrador
 * (`[PENDIENTE…]`, en cualquier caja) no se puede congelar en la cadena probatoria.
 */
class DraftCannotBePublishedException extends RuntimeException
{
    /**
     * @param  list<string>  $locales
     */
    public static function in(string $slug, array $locales): self
    {
        return new self(sprintf(
            'El texto «%s» lleva todavía un marcador [PENDIENTE] en %s: publicar es irreversible y grabaría un borrador en la cadena probatoria.',
            $slug,
            implode(', ', $locales),
        ));
    }
}
