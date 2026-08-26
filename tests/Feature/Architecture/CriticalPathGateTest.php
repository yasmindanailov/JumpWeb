<?php

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — el gate de concurrencia del `pre-push`, hecho FALSABLE (spec §9, aviso final).
 *
 * `INVARIANTES §6` dice que la suite (SQLite) es ciega a las carreras de InnoDB, así que tocar el
 * núcleo de dinero/aforo exige correr `redsys:verify-concurrency` y `purchase:verify-oversell`
 * sobre MySQL real. Quien lo impone es una expresión regular dentro de un script de shell
 * (`.githooks/pre-push`), y una regex en un script es justo la clase de guarda que se queda
 * desfasada en silencio: nadie la ejecuta hasta el día que hace falta, y ese día es tarde.
 *
 * Este test la trae al terreno donde sí se ejecuta. Comprueba tres cosas:
 *  1. que el patrón sigue cubriendo el núcleo actual —si alguien renombra `OrderCreator`, el gate
 *    dejaría de protegerlo y aquí se ve—;
 *  2. que **no** es un comodín que cubre medio repositorio, en cuyo caso todo el mundo empezaría a
 *    saltárselo con `VERIFY_CONC=1` por costumbre;
 *  3. que ningún controlador de la API toca el núcleo por debajo del radar del patrón. Hoy no hay
 *    ninguno; cuando el paso 4 traiga el checkout, o casa con el nombre o este test cae.
 */
class CriticalPathGateTest extends TestCase
{
    /**
     * Núcleo endurecido que el gate DEBE cubrir siempre (`INVARIANTES §6`). Si una de estas rutas
     * deja de existir, es que la clase se ha movido o renombrado: hay que actualizar las dos, la
     * lista y el patrón del hook.
     *
     * @var list<string>
     */
    private const CRITICAL_FILES = [
        'app/Domain/Booking/Services/OrderCreator.php',
        'app/Domain/Booking/Services/SlotGenerator.php',
        'app/Domain/Payments/Services/RedsysReturnHandler.php',
        // Fase 3 · paso 2: aquí vive ahora el código de `PAY-04`. La política extiende la retención
        // con un UPDATE atómico condicionado —partirlo en check+save resucita un hold vencido sin
        // recontar aforo (hallazgo L2)— y el initiator marca `SUPERSEDED` los intentos previos.
        'app/Domain/Booking/Services/ReservationAdmissionPolicy.php',
        'app/Domain/Payments/Services/PaymentInitiator.php',
        // Fase 3 · paso 4b: la fuente ÚNICA de oferta (`AFORO-02`). Entró tarde al patrón —este
        // mismo test ya la trataba como núcleo en `CRITICAL_SYMBOLS`, pero el fichero quedaba
        // fuera—, y sí puede mover lo que verifica `purchase:verify-oversell`: `OrderCreator` la
        // llama como backstop del corte intra-día (`passesIntradayFloor`).
        'app/Domain/Booking/Services/SlotOffer.php',
        // Cierre de Fase 3: aquí vive ahora la SECUENCIA «admitir → crear → abrir cobro», que antes
        // estaba escrita a mano en cinco puntos de la capa de entrega. No contiene reglas nuevas
        // —las tres piezas ya existían— pero sí el ORDEN, que es lo que ninguna guarda de
        // arquitectura sabe ver y lo que decide si se retiene aforo sin cobrarlo (`AFORO-10`) o se
        // reabre un cobro sobre un hold no validado (`PAY-04`).
        'app/Domain/Booking/Services/CheckoutOrchestrator.php',
        // Fase 4 · paso 4.0b·6. No orquesta el checkout ni escribe nada —solo pregunta a la oferta
        // si una línea cabe—, pero es superficie de API que DECIDE sobre aforo, y el gate ya trata
        // así a su hermano `AvailabilityController` por el mismo motivo. Dejarlo fuera habría sido
        // incoherente: los dos leen `SlotOffer` a través del mismo contrato, y la suite corre sobre
        // SQLite, que no ve carreras. El patrón se amplió con `Cart` en este paso.
        'app/Http/Controllers/Api/V1/CartLineController.php',
        // ⚠️⚠️ **LOS DOS CONTADORES DE AFORO**, dentro desde el 2026-08-25 y fuera desde el principio.
        // Son quienes deciden cuántas plazas quedan, y dependencias DIRECTAS del constructor de
        // `OrderCreator`. El docblock de `PackAvailability` describe literalmente el contrato que los
        // verificadores existen para comprobar —«pensado para correr bajo `lockForUpdate` en
        // `OrderCreator` (anti-sobreventa): el bloqueo de las franjas de la zona/día serializa las
        // compras concurrentes»— y aun así tocarlo no disparaba nada. Mismo modo de fallo que
        // `SlotOffer`: el gate vigilaba a quien LLAMA y no a quien CUENTA.
        //
        // ⚠️ **Y entran AQUÍ, no solo en el patrón del hook.** Añadirlos al `CRITICAL_RE` sin añadirlos
        // a esta lista deja el gate sin red: medido por mutación el 2026-08-25, quitar
        // `PackAvailability` del hook dejaba este fichero **en verde**. La lista es lo que impide que
        // el patrón encoja en silencio.
        'app/Domain/Booking/Services/SlotAvailability.php',
        'app/Domain/Booking/Services/PackAvailability.php',
        // No es dinero ni aforo, pero se protege igual (spec del waiver §8.5 y §10.5, `#169`): la
        // cadena de firmas por titular solo es lineal por el `lockForUpdate()` de este fichero, la
        // suite corre en SQLite —que NO emite `FOR UPDATE`— y el verificador es `waiver:verify-chain`.
        'app/Domain/Identity/Services/WaiverSigner.php',
    ];

    /**
     * Ficheros que NO deben disparar los verificadores. Son el control negativo: sin ellos, un
     * patrón accidentalmente laxo (`^app/.*\.php$`) pasaría los demás tests con nota.
     *
     * @var list<string>
     */
    private const NON_CRITICAL_FILES = [
        // ⚠️ El control negativo AFILADO: el vecino de al lado de los dos que acaban de entrar.
        // `ProductAvailability` es la tercera dependencia de aforo de `OrderCreator`, pero NO
        // cuenta plazas: solo pregunta si una hora cae dentro de la ventana del día
        // (`OperatingSchedule` → apertura/cierre/offsets). No hay nada que una carrera pueda
        // corromper ahí y la suite lo cubre entera sobre SQLite. Si algún día empieza a contar,
        // este test se pondrá rojo y obligará a decidirlo a conciencia en vez de por inercia.
        'app/Domain/Booking/Services/ProductAvailability.php',
        'app/Http/Controllers/Api/V1/MeController.php',
        'app/Http/Controllers/HomeController.php',
        'app/Domain/Identity/Models/User.php',
        'routes/api.php',
        // Control negativo del waiver: decide modo y vigencia, pero no escribe la cadena.
        'app/Domain/Identity/Services/WaiverAcceptance.php',
    ];

    /**
     * Clases del núcleo cuya presencia en un controlador de API lo convierte en crítico. Se
     * comparan por nombre corto: da igual cómo se importen.
     *
     * @var list<string>
     */
    private const CRITICAL_SYMBOLS = [
        'OrderCreator', 'SlotGenerator', 'RedsysReturnHandler', 'SlotOffer', 'PaymentInitiator',
        // Cierre de Fase 3, y es la entrada que más trabaja de la lista: desde que la secuencia vive
        // tras un contrato, un controlador de API llega al núcleo SIN nombrar ninguna de las clases
        // anteriores. Un endpoint futuro que inyecte `ReservationCheckout` y no case con el patrón
        // por su nombre quedaría fuera del gate, y su carrera no la ve la suite (SQLite).
        'ReservationCheckout',
    ];

    private function criticalPattern(): string
    {
        $hook = (string) file_get_contents(base_path('.githooks/pre-push'));

        $this->assertSame(
            1,
            preg_match("/^CRITICAL_RE='(.+)'$/m", $hook, $matches),
            'no encuentro `CRITICAL_RE` en .githooks/pre-push — ¿ha cambiado de nombre o de forma?'
        );

        return '#'.$matches[1].'#';
    }

    /** El núcleo de dinero/aforo sigue dentro del gate. */
    public function test_the_gate_still_covers_the_hardened_core(): void
    {
        $pattern = $this->criticalPattern();

        foreach (self::CRITICAL_FILES as $path) {
            $this->assertFileExists(
                base_path($path),
                "«{$path}» ya no existe: actualiza CRITICAL_FILES y el patrón del hook a la vez"
            );
            $this->assertSame(
                1, preg_match($pattern, $path),
                "El gate de pre-push ya NO cubre «{$path}»: un cambio ahí se empujaría sin correr ".
                'los verificadores de concurrencia (INVARIANTES §6).'
            );
        }
    }

    /** Y no cubre lo que no debe: un gate que salta siempre es un gate que se desactiva siempre. */
    public function test_the_gate_is_not_a_wildcard(): void
    {
        $pattern = $this->criticalPattern();

        foreach (self::NON_CRITICAL_FILES as $path) {
            $this->assertSame(
                0, preg_match($pattern, $path),
                "El gate exige los verificadores de concurrencia por tocar «{$path}», que no es ".
                'núcleo de dinero/aforo. Un gate que salta de más se acaba saltando a mano.'
            );
        }
    }

    /**
     * La guarda que mira hacia adelante: en cuanto el paso 4 escriba un controlador que orqueste el
     * checkout, o su nombre casa con el patrón o esto se pone rojo. Es la forma de que el aviso
     * del spec §9 —«los controladores de checkout de API no dispararían VERIFY_CONC»— no dependa
     * de que alguien lo recuerde.
     */
    public function test_no_api_controller_reaches_the_core_outside_the_gate(): void
    {
        $pattern = $this->criticalPattern();
        $uncovered = [];

        foreach ($this->apiControllerFiles() as $file) {
            $relative = mb_substr($file, mb_strlen(base_path()) + 1);

            if (preg_match($pattern, $relative) === 1) {
                continue;
            }

            foreach (self::CRITICAL_SYMBOLS as $symbol) {
                if ($this->referencesSymbol($file, $symbol)) {
                    $uncovered[] = "  {$relative}  →  {$symbol}";
                }
            }
        }

        $this->assertSame(
            [], $uncovered,
            "Controladores de API que tocan el núcleo de dinero/aforo SIN estar en el gate:\n".
            implode("\n", $uncovered)."\n\n".
            'Renómbralos para que casen con `CRITICAL_RE` (.githooks/pre-push) o amplía el patrón: '.
            'la suite SQLite no ve sus carreras (INVARIANTES §6).'
        );
    }

    /** Tokenizador, no regex sobre el texto: un docblock que cite `OrderCreator` no es una llamada. */
    private function referencesSymbol(string $file, string $symbol): bool
    {
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token)) {
                continue;
            }

            if (! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $name = ltrim($token[1], '\\');
            $short = str_contains($name, '\\') ? mb_substr($name, mb_strrpos($name, '\\') + 1) : $name;

            if ($short === $symbol) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function apiControllerFiles(): array
    {
        $base = app_path('Http/Controllers/Api');

        if (! is_dir($base)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
