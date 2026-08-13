<?php

namespace Tests\Feature\Api;

use App\Domain\Booking\Exceptions\ReservationException;
use App\Http\Api\ApiErrorCode;
use App\Http\Api\ReservationErrorMap;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 3 · paso 4c — el mapa de rechazos de reserva es **exhaustivo**, y lo es por test (spec §4.3).
 *
 * `ReservationException` lleva como mensaje una clave de `lang/`, que no puede ser el contrato
 * público: renombrarla rompería a cualquier cliente ramificado sobre ella. `ReservationErrorMap` es
 * la indirección, y una indirección incompleta es peor que ninguna — un motivo de rechazo sin mapear
 * saldría con el código genérico y el cliente no podría distinguir «agotado» (refresca la
 * disponibilidad) de «fuera de horario» (elige otra hora).
 *
 * Por eso esto no se comprueba leyendo el mapa, sino **leyendo el dominio**: se recorre el código
 * buscando cada `ReservationException` que se lanza y se compara con lo mapeado. Una regla de
 * rechazo nueva no puede llegar a producción sin pasar por aquí.
 */
class ReservationErrorMapTest extends TestCase
{
    /** Las claves que el DOMINIO lanza hoy, leídas del código. */
    public function test_every_thrown_reservation_key_has_a_public_code(): void
    {
        $thrown = $this->thrownKeys();

        $this->assertNotEmpty(
            $thrown,
            'no se ha encontrado ninguna `ReservationException` en app/: ¿ha cambiado la forma de lanzarlas?'
        );

        $unmapped = array_values(array_diff($thrown, ReservationErrorMap::knownKeys()));

        $this->assertSame(
            [], $unmapped,
            "Motivos de rechazo que el dominio lanza y la API no sabe traducir:\n  ".
            implode("\n  ", $unmapped)."\n\n".
            'Añádelos a `ReservationErrorMap` con su `ApiErrorCode`, o el cliente recibirá un '.
            'código genérico que no le deja distinguir qué hacer (spec §4.3).'
        );
    }

    /**
     * Y al revés: una entrada del mapa que ya nadie lanza es una rama muerta en todo cliente que la
     * programó. Esta dirección es la que suele quedarse sin vigilar.
     */
    public function test_the_map_has_no_entries_nobody_throws(): void
    {
        $orphans = array_values(array_diff(ReservationErrorMap::knownKeys(), $this->thrownKeys()));

        $this->assertSame(
            [], $orphans,
            "El mapa traduce motivos que ya nadie lanza:\n  ".implode("\n  ", $orphans)
        );
    }

    /** Cada clave mapeada resuelve a su código, y el mensaje existe en el catálogo i18n. */
    public function test_every_mapped_code_has_a_readable_message(): void
    {
        foreach (ReservationErrorMap::knownKeys() as $key) {
            $code = ReservationErrorMap::codeFor(new ReservationException($key));

            $this->assertNotSame(
                $code->messageKey(), $code->message(),
                "El código «{$code->value}» no tiene mensaje en `lang/es/api.php`: el cliente que no ".
                'sepa qué hacer con el código mostraría la clave de traducción en crudo.'
            );
        }
    }

    /**
     * El `context` del dominio viaja como `params`. Sin él, un cliente pintaría «El producto — no
     * está disponible»: es la corrección que la v2 del spec añadió al sobre de error (§4.3).
     */
    public function test_the_context_of_the_domain_travels_as_params(): void
    {
        $exception = ReservationException::withContext('tickets.errors.sold_out_line', [
            'product' => 'Jump · 1 hora',
            'when' => '2026-08-14 10:00',
        ]);

        $this->assertSame(ApiErrorCode::LineSoldOut, ReservationErrorMap::codeFor($exception));
        $this->assertSame(
            ['product' => 'Jump · 1 hora', 'when' => '2026-08-14 10:00'],
            ReservationErrorMap::paramsFor($exception)
        );
    }

    /**
     * Claves de `ReservationException` que el código de `app/` lanza, leídas con el TOKENIZADOR: una
     * regex sobre el texto contaría también las que aparecen citadas en un docblock.
     *
     * @return list<string>
     */
    private function thrownKeys(): array
    {
        $keys = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $tokens = array_values(array_filter(
                token_get_all((string) file_get_contents($file)),
                static fn (array|string $token): bool => ! is_array($token)
                    || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ));

            foreach ($tokens as $index => $token) {
                if (! $this->opensAReservationException($tokens, $index)) {
                    continue;
                }

                // El primer literal de cadena tras el paréntesis de apertura es la clave.
                for ($ahead = $index; $ahead < count($tokens); $ahead++) {
                    $candidate = $tokens[$ahead];

                    if (is_array($candidate) && $candidate[0] === T_CONSTANT_ENCAPSED_STRING) {
                        $keys[] = trim($candidate[1], "'\"");

                        break;
                    }

                    if ($candidate === ')') {
                        break;
                    }
                }
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }

    /**
     * ¿Empieza aquí un `new ReservationException(` o un `ReservationException::withContext(`?
     *
     * @param  list<array{0:int,1:string,2:int}|string>  $tokens
     */
    private function opensAReservationException(array $tokens, int $index): bool
    {
        $token = $tokens[$index];

        if (! is_array($token) || $token[0] !== T_STRING) {
            return false;
        }

        if (! str_ends_with($token[1], 'ReservationException')) {
            return false;
        }

        $next = $tokens[$index + 1] ?? null;

        // `new ReservationException(` o `ReservationException::withContext(`
        return $next === '(' || (is_array($next) && $next[0] === T_DOUBLE_COLON);
    }

    /** @return list<string> */
    private function phpFiles(string $base): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
