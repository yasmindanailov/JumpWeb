<?php

namespace App\Domain\Content\Services;

use App\Domain\Content\Models\Attraction;
use Illuminate\Support\Collection;

/**
 * **EL MOSAICO DE LA SECCIÓN «QUÉ HAY DENTRO»** (`docs/specs/rediseno-desde-canvas.md` §5.4 · T2d ·
 * `DECISIONES #482`).
 *
 * La portada enseña **cinco** atracciones de las que haya y delega el resto en `/atracciones`: tres
 * con nombre y dos **veladas**, que se disuelven hacia el papel y dicen «hay más» sin ser un
 * destino. Este servicio decide **cuáles cinco y en qué papel**.
 *
 * ❗❗ **EL REPARTO NO ES «LAS CINCO PRIMERAS», Y EL ARTBOARD ESCRIBE POR QUÉ**: de las tres que se
 * leen, **una es de la primera zona y dos de la segunda**. Su propia nota lo justifica —*«antes se
 * nombraban dos de Jump y una de Kids, así que la madre de un niño de 4 años veía un solo juego de
 * su zona con nombre»*—, y las dos veladas vuelven a la primera zona. Con las cinco primeras por
 * orden global, en esta instalación **las cinco saldrían de Jump** y Kids no aparecería en la
 * sección que presenta lo que hay dentro.
 *
 * ⚠️⚠️ **QUIÉN ES «LA PRIMERA ZONA» LO DICE EL PANEL** (`zones.position`), no este código.
 * `[DECIDIDO owner]`: las cinco se cambian **reordenando en el panel**, sin campo nuevo. Medido con
 * los datos de esta instalación, el reparto reproduce el mosaico del mockup: «Saltos libres» grande,
 * «Piscina de bolas» y «Toboganes» con nombre, y «Salto a la nube» y «Tirolina» veladas.
 * ▶ Se descartó un campo `featured` por atracción con su precedente delante: `ticket_types.icon`
 * es exactamente esa forma y estuvo en `NULL` en los catorce enganches hasta que alguien lo miró.
 *
 * ❗❗❗ **EL VELO SON DOS CELDAS O NINGUNA, Y NO ES SIMETRÍA: ES SU SIGNIFICADO.** El artboard lo
 * escribe al descartar su propia opción 4a — *«el degradado es una BANDA que disuelve el borde
 * inferior de la sección, y con solo dos de tres veladas deja de decir "la sección se acaba" y dice
 * "estas fotos están borrosas"»*—, así que las veladas tienen que ocupar **la fila entera**.
 * ▶ De ahí sale la regla de degradación: con **cinco atracciones o menos** el velo desaparece y la
 * sección enseña las tres con nombre. Velar la última diría «hay más» sobre un catálogo que ya cabe
 * entero en la página de al lado. *Vacío es una respuesta.*
 */
final class RideMosaic
{
    /** Las que se leen: llevan nombre, zona y enlace. */
    public const CON_NOMBRE = 3;

    /** Las que se disuelven: sin nombre, sin enlace y ocultas al lector de pantalla. */
    public const VELADAS = 2;

    /**
     * @param  Collection<int, object>  $zones  zonas de la landing, ordenadas por `position`, con sus
     *                                          atracciones activas ya cargadas
     * @return list<array{ride: Attraction, zone: object, papel: string}>
     */
    public static function compose(Collection $zones): array
    {
        $conJuegos = $zones->filter(fn ($zona): bool => $zona->attractions->isNotEmpty())->values();
        $total = $conJuegos->sum(fn ($zona): int => $zona->attractions->count());

        if ($total === 0) {
            return [];
        }

        // El velo solo entra cuando de verdad queda algo detrás de él.
        $veladas = $total > self::CON_NOMBRE + self::VELADAS ? self::VELADAS : 0;
        $cuantas = min($total, self::CON_NOMBRE + $veladas);

        /*
         * El reparto del artboard, en pares (zona, atracción): la grande de la primera zona, las dos
         * que se leen de la segunda, y las veladas otra vez de la primera. Con una sola zona no hay
         * reparto que hacer y salen sus primeras por orden.
         */
        $deseado = $conJuegos->count() > 1
            ? [[0, 0], [1, 0], [1, 1], [0, 1], [0, 2]]
            : [[0, 0], [0, 1], [0, 2], [0, 3], [0, 4]];

        $elegidas = [];
        $usadas = [];

        foreach ($deseado as [$iZona, $iRide]) {
            if (count($elegidas) >= $cuantas) {
                break;
            }

            $ride = $conJuegos->get($iZona)?->attractions->get($iRide);

            if ($ride === null || isset($usadas[$ride->id])) {
                continue;
            }

            $usadas[$ride->id] = true;
            $elegidas[] = ['ride' => $ride, 'zone' => $conJuegos->get($iZona)];
        }

        /*
         * ⚠️ **El relleno no es un adorno defensivo**: una zona con una sola atracción deja huecos en
         * el reparto, y sin esto la portada enseñaría tres fotos donde el parque tiene ocho. Se
         * recorre en el orden del panel, saltando las ya elegidas.
         */
        foreach ($conJuegos as $zona) {
            foreach ($zona->attractions as $ride) {
                if (count($elegidas) >= $cuantas) {
                    break 2;
                }

                if (! isset($usadas[$ride->id])) {
                    $usadas[$ride->id] = true;
                    $elegidas[] = ['ride' => $ride, 'zone' => $zona];
                }
            }
        }

        return array_values(array_map(
            static fn (array $celda, int $i): array => $celda + [
                'papel' => match (true) {
                    $i === 0 => 'grande',
                    $i < self::CON_NOMBRE => 'chica',
                    default => 'velada',
                },
            ],
            $elegidas,
            array_keys($elegidas),
        ));
    }
}
