<?php

namespace App\Domain\Identity\Exceptions;

use LogicException;

/**
 * Fase 6 · waiver — un registro probatorio no se edita ni se borra (`specs/waiver-probatorio.md`
 * §4.2, §4.3). Lo lanzan los modelos inmutables desde sus eventos `updating`/`deleting`; la única
 * salida legítima es la poda por plazo (`WaiverSignature::pruning()`), que se autoriza fila a fila.
 */
class ImmutableRecordException extends LogicException
{
    public static function for(string $model, string $operation): self
    {
        return new self("{$model} es inmutable: no se permite «{$operation}». Una corrección es una fila NUEVA.");
    }
}
