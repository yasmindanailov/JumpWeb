<?php

namespace Tests\Feature\Architecture;

use App\Console\Commands\VerifyPurchaseConcurrency;
use App\Domain\Booking\Services\PackAvailability;
use ReflectionClass;
use Tests\TestCase;

/**
 * **EL VERIFICADOR DE SOBREVENTA CUBRE LOS TRES AFOROS, NO UNO** (`DECISIONES #147`).
 *
 * ⚠️⚠️ Nació de un hueco MEDIDO, no de una simetría. Hasta el 2026-08-25 `purchase:verify-oversell`
 * sembraba **una entrada** con `online_capacity = 1` y forkaba N compras — y eso es **el único**
 * de los tres aforos del producto. Los cumpleaños se cuentan por otro camino entero
 * ({@see PackAvailability}, pool propio y **dos** topes por franja) y
 * **ningún verificador los ejecutaba**.
 *
 * ▶ Traducido a lo que significaba: **no había ninguna evidencia de que dos cumpleaños simultáneos
 * por la última plaza no se vendieran los dos.** La suite lo daba por bueno porque SQLite no
 * reproduce las carreras de InnoDB (`SUITE-04`), así que el silencio no era una prueba.
 *
 * Esta guarda no ejecuta la carrera —eso exige MySQL y `pcntl_fork`, y por eso el verificador es un
 * comando y no un test—. Vigila que **el instrumento no encoja**: es hermana de
 * `CriticalPathGateTest`, que vigila que el gate del `pre-push` no deje de cubrir lo que debe.
 */
class OversellVerifierCoversEveryQuotaTest extends TestCase
{
    /**
     * Los tres aforos del producto, con el nombre de su escenario en el comando.
     *
     * ⚠️ **Añadir un aforo nuevo al dominio y no añadirlo aquí es volver al estado de partida**: un
     * camino de aforo que ningún verificador ejercita y sobre el que no hay evidencia ninguna.
     *
     * @var array<string,string>
     */
    private const QUOTAS = [
        'entry' => 'asientos de la franja (SlotAvailability)',
        'pack' => 'cupo de FIESTAS por franja (zones.max_per_slot)',
        'pack-guests' => 'cupo de INVITADOS por franja (zones.max_guests_per_slot)',
        'pack-prep' => 'cupo de fiestas con la fiesta abarcando VARIAS franjas — montaje y limpieza '.
            'ACTIVOS, que es la configuración por defecto en producción',
        'mixed' => 'los DOS pools compitiendo a la vez en la misma zona y franja',
        'panel-edit' => 'el lock zona/día de las EDICIONES del panel (`AFORO-05`): N ediciones '.
            'concurrentes hacia dos destinos cuyas ventanas pisan una franja intermedia con una '.
            'plaza — el instrumento de la extracción 4 del desmontaje de `ViewOrder`, visto FALLAR '.
            'con el lock mutado a solo-la-fila-destino (2 comprometidos donde cabía 1)',
        'extra-hour' => 'la HORA EXTRA (`specs/hora-extra.md` §6·1): la última plaza de una franja '.
            'disputada entre entradas directas y la línea HIJA que ocupa de un complemento con '.
            '`occupies_after_parent` — visto FALLAR con la validación de la hija desactivada '.
            '(5 asientos escritos en una franja de 1, SIN necesitar carrera) y pasar con ella (1 de 8)',
        'stay-extension' => 'el cupo de SALA frente a una fiesta ALARGADA: la hora extra de un pack '.
            '(`extends_parent_stay`, `specs/hora-extra.md` §10) no añade un ocupante, alarga la '.
            'ventana de la fiesta — y el mapa de cupo tiene que verlo. Mide una AUSENCIA (nadie '.
            'compra la hora siguiente), así que su guarda del instrumento es a la vez su CONTROL: '.
            'la franja vende ANTES de alargar la fiesta y cierra DESPUÉS. Visto FALLAR con el cupo '.
            'ciego a `extra_minutes` (la segunda hora seguía ofreciendo 20)',

        // La hora extra COBRADA POR INVITADO (`specs/hora-extra.md` §11, `#443`). ⚠️⚠️ **No es una
        // variante del anterior: mide otra cosa y por otro camino.** Allí la extensión se SIEMBRA y
        // nadie debe ganar; aquí los 12 COMPRAN la misma sala con su hora extra en modo por-invitado
        // y **uno solo debe ganar**. Lo que lo hace un instrumento y no un adorno es su modo de
        // fallo: si los minutos volvieran a salir de la cantidad, cada compra pediría 8 × 60 = 480
        // min, la ventana no cabría en la rejilla y ganaría CERO — **visto FALLAR exactamente así**
        // (0 de 12 con `blocksFor()` devolviendo la cantidad).
        'stay-extension-per-guest' => 'la DERIVACIÓN de la ventana con la cantidad en PERSONAS: '.
            'el precio escala con los invitados y los minutos NO, comprobado a través del checkout '.
            'y bajo el lock, que es donde la suite es ciega (SQLite)',
    ];

    public function test_the_verifier_declares_a_scenario_for_every_quota(): void
    {
        $declared = $this->scenarios();

        foreach (self::QUOTAS as $scenario => $what) {
            $this->assertContains(
                $scenario, $declared,
                "`purchase:verify-oversell` ya no declara el escenario «{$scenario}» ({$what}).\n".
                "▶ Ese aforo se quedaría SIN ninguna evidencia bajo concurrencia: la suite corre en\n".
                '  SQLite y no reproduce los locks de InnoDB (`SUITE-04`).',
            );
        }

        // Y al revés: un escenario que el comando declare y esta lista no conozca es un aforo que
        // alguien añadió sin decir cuál es — la lista dejaría de ser el inventario de lo cubierto.
        $this->assertSame(
            [], array_values(array_diff($declared, array_keys(self::QUOTAS))),
            'El comando declara escenarios que esta guarda no conoce: añádelos a `QUOTAS` con el '.
            'aforo que cubren, o la lista deja de ser un inventario.',
        );
    }

    /**
     * ⚠️⚠️ **Cada escenario mide un invariante DISTINTO, y eso es la mitad del valor.**
     *
     * Los tres podrían compartir la evaluación de asientos y los tres saldrían verdes sin haber
     * medido el cupo de packs: un pack **no consume asientos**, así que «asientos reservados == 1»
     * se cumple sola en el escenario de cumpleaños aunque se vendan ocho fiestas.
     *
     * ▶ Por eso se asevera que existen los dos contadores propios de packs. Si desaparecen, la
     * evaluación habrá caído al camino de entradas y el verde volvería a no significar nada.
     */
    public function test_each_scenario_measures_its_own_invariant(): void
    {
        $class = new ReflectionClass(VerifyPurchaseConcurrency::class);

        foreach ([
            'livePackLinesInSlot' => 'cuenta FIESTAS vivas en la franja',
            'livePackGuestsInSlot' => 'suma los INVITADOS vivos de la franja',
            'liveEntryLinesInSlot' => 'cuenta ENTRADAS vivas en la franja (escenario mixto)',
            'livePackLinesInZoneDay' => 'cuenta las fiestas de TODO EL DÍA, que es lo que exige el '.
                'escenario con montaje: dos fiestas se pisan sin compartir hora de inicio',
            'evaluateMixed' => 'evalúa el escenario mixto por TOPES y no por nº de ganadores',
        ] as $method => $what) {
            $this->assertTrue(
                $class->hasMethod($method),
                "Falta `{$method}()`, que {$what}.\n".
                '▶ Sin él la evaluación del escenario de packs cae al contador de ASIENTOS, y un pack '.
                'no consume asientos: el verificador saldría verde sin haber medido el cupo.',
            );
        }
    }

    /**
     * ⚠️⚠️ **La guarda del instrumento: sin ella, el verificador es verde por construcción.**
     *
     * Si el escenario está mal sembrado —el pack no cabe en la rejilla, falta el precio de la tarifa
     * del día, la hora quedó fuera del horario— los N compradores son rechazados por un motivo que
     * **no es la carrera**, y el resultado se leería como «nadie sobrevendió».
     *
     * ▶ No es hipotético: la primera ejecución de este comando en el repo falló exactamente así, por
     * un precio ausente en la tarifa del día. Allí lo delató el conteo; en los escenarios de pack hay
     * tramo, preparación y dos topes, así que la comprobación se hace **explícita y antes de forkar**.
     */
    public function test_the_verifier_checks_that_the_scenario_can_sell_at_least_once(): void
    {
        $class = new ReflectionClass(VerifyPurchaseConcurrency::class);

        $this->assertTrue(
            $class->hasMethod('probeSellsOnce'),
            "Ha desaparecido la comprobación previa de que el escenario permite vender UNA vez.\n".
            '▶ Sin ella, un escenario mal sembrado produce N rechazos que se leen como «no hubo '.
            'sobreventa»: el verificador pasa a ser verde por construcción.',
        );

        $source = (string) file_get_contents((string) $class->getFileName());
        $probeAt = strpos($source, '$this->probeSellsOnce(');
        $forkAt = strpos($source, '$this->forkWorkers(');

        $this->assertNotFalse($probeAt, 'la comprobación existe pero no se llama');
        $this->assertNotFalse($forkAt, 'no se ve la llamada que forka a los compradores');
        $this->assertLessThan(
            $forkAt, $probeAt,
            'La comprobación del escenario se hace DESPUÉS de forkar. Tiene que correr antes: '.
            'después ya no distingue un escenario mal sembrado de una carrera bien serializada.',
        );
    }

    /**
     * **La guarda de la guarda.**
     *
     * Todo lo de arriba se apoya en leer la constante del comando. Si un día deja de existir o
     * cambia de forma, los casos anteriores pasarían a comprobar una lista vacía y quedarían verdes
     * sin mirar nada — el modo de fallo que este repo ya ha pagado varias veces.
     */
    public function test_the_scenario_list_is_actually_readable(): void
    {
        $declared = $this->scenarios();

        $this->assertNotEmpty(
            $declared,
            'No se ha podido leer la lista de escenarios del comando: los demás casos de este '.
            'fichero estarían comprobando una lista vacía.',
        );

        $this->assertContains('entry', $declared, 'el escenario histórico debería seguir ahí');
    }

    /** @return list<string> */
    private function scenarios(): array
    {
        $class = new ReflectionClass(VerifyPurchaseConcurrency::class);

        if (! $class->hasConstant('SCENARIOS')) {
            return [];
        }

        /** @var list<string> $value */
        $value = $class->getConstant('SCENARIOS');

        return is_array($value) ? array_values($value) : [];
    }
}
