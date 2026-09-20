<?php

namespace App\Domain\Content\Exceptions;

use LogicException;

/**
 * **Se intentó guardar una imagen de un tercero donde solo caben rutas nuestras**
 * (`docs/specs/google-business-profile.md` §4.3·6 y §4.3·9; `DECISIONES #524`, `#727`).
 *
 * ❗❗ **Es un `LogicException` y no un error de validación**, y la diferencia importa: no es un dato
 * malo que llegue de fuera, es código de la casa haciendo lo que la tanda existe para impedir. La
 * sincronización descarga la foto y guarda su ruta; si un día alguien atajara guardando la URL de
 * Google, la portada volvería a pedirle la cara del autor al visitante —que es lo que `RGPD-05`
 * prohíbe sin consentimiento y lo que `SEC-01` acaba de sacar de `img-src`— **sin que se rompa nada
 * visible**: la foto se vería igual de bien.
 *
 * ⚠️ El mensaje nombra la COLUMNA y nunca el valor: una URL de Google lleva dentro el identificador
 * de la foto del autor, y un mensaje de excepción acaba en un log.
 */
final class ForeignImageUrlException extends LogicException
{
    private function __construct(public readonly string $column, string $message)
    {
        parent::__construct($message);
    }

    public static function in(string $column): self
    {
        return new self($column, "«{$column}» solo admite una ruta de nuestro disco o null: una imagen de la ficha se descarga y se sirve desde aquí (§4.3·6), nunca se enlaza a su host.");
    }
}
