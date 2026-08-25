<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;

/**
 * Fase 6 · waiver — verificación de la cadena de un titular (`specs/waiver-probatorio.md` §4.7,
 * §8.5): cada fila tiene que dar su propio hash, y cada fila salvo la primera tiene que enlazar con
 * la anterior. Es lo que un auditor pide («enséñame la cadena de esta persona») y lo que el
 * verificador de concurrencia comprueba tras N firmas simultáneas.
 *
 * ⚠️ El enlace de la PRIMERA fila que queda no se comprueba: cuando la poda por plazo se lleva la
 * firma más antigua, la siguiente sigue apuntando a un hash que ya no existe. Eso no rompe la
 * prueba de las que quedan —cada una sigue verificando su contenido—; solo mueve el inicio.
 */
final class WaiverChain
{
    /**
     * @return array{ok:bool, count:int, problems:list<string>}
     */
    public static function verify(User $user): array
    {
        $rows = WaiverSignature::query()
            ->where('user_id', $user->getKey())
            ->orderBy('id')
            ->get();

        $problems = [];
        $previous = null;
        foreach ($rows as $index => $row) {
            if ($index > 0 && $row->prev_hash !== $previous) {
                $problems[] = "#{$row->getKey()}: prev_hash no enlaza con la firma anterior";
            }
            if (! $row->verifyHash()) {
                $problems[] = "#{$row->getKey()}: el hash no coincide con el contenido";
            }
            $previous = $row->hash;
        }

        return ['ok' => $problems === [], 'count' => $rows->count(), 'problems' => $problems];
    }
}
