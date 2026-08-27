<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Models\User;
use App\Domain\Identity\Models\WaiverSignature;

/**
 * Fase 6 · waiver — verificación de las cadenas de un titular (`specs/waiver-probatorio.md` §4.7,
 * §8.5; `DECISIONES #197`): hay UNA cadena por sujeto —la del titular y la de cada menor a su
 * cargo—, y en cada una toda fila tiene que dar su propio hash, enlazar con la anterior DE SU SUJETO
 * y apuntar al texto que dice apuntar. Es lo que un auditor pide («enséñame la cadena de esta
 * persona», «la de este menor») y lo que el verificador de concurrencia comprueba.
 *
 * ⚠️ El enlace de la PRIMERA fila que queda de cada cadena no se comprueba: cuando la poda por plazo
 * se lleva la firma más antigua de un sujeto, la siguiente sigue apuntando a un hash que ya no
 * existe. Eso no rompe la prueba de las que quedan —cada una sigue verificando su contenido—; solo
 * mueve el inicio. Y como las cadenas son por sujeto, podar las del titular nunca deja un agujero
 * en medio de la de un menor (NUC-3, `DEUDA.md`).
 */
final class WaiverChain
{
    /**
     * @return array{ok:bool, count:int, chains:int, problems:list<string>}
     */
    public static function verify(User $user): array
    {
        $rows = WaiverSignature::query()
            ->where('user_id', $user->getKey())
            ->with('version')
            ->orderBy('id')
            ->get();

        $problems = [];
        /** @var array<string, string> $heads la cabeza (último hash) de cada cadena, por sujeto */
        $heads = [];
        foreach ($rows as $row) {
            $subject = $row->subject_type.':'.($row->subject_id ?? '');
            if (array_key_exists($subject, $heads) && $row->prev_hash !== $heads[$subject]) {
                $problems[] = "#{$row->getKey()}: prev_hash no enlaza con la firma anterior de su sujeto ({$subject})";
            }
            if (! $row->verifyHash()) {
                $problems[] = "#{$row->getKey()}: el hash no coincide con el contenido";
            }
            // §10.6 (NUC-8): la cadena también cruza cada firma con su VERSIÓN — el texto que se firmó.
            // Hasta `#180` una versión alterada por debajo daba «cadena OK» y solo el PDF lo veía.
            if ($row->version === null || ! $row->version->verifyHash() || ! hash_equals((string) $row->version->body_hash, (string) $row->document_hash)) {
                $problems[] = "#{$row->getKey()}: el texto firmado no coincide con su versión";
            }
            $heads[$subject] = (string) $row->hash;
        }

        return ['ok' => $problems === [], 'count' => $rows->count(), 'chains' => count($heads), 'problems' => $problems];
    }
}
