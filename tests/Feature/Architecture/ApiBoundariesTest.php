<?php

namespace Tests\Feature\Architecture;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — **frontera de la capa de API** (spec §6.5).
 *
 * El criterio de éxito nº 4 de la fase dice «ninguna regla de negocio nace en un controlador de
 * API». La v1 del spec lo quería medir con un `git grep` → cero, que no es auditable: una regla
 * duplicada no es una cadena que se pueda buscar. Esta es su versión falsable.
 *
 * Y hacía falta una guarda PROPIA porque `ModuleBoundariesTest` **no** vigila esto: su lista
 * `DELIVERY` incluye `Http` y sale por `continue`. La v1 afirmaba lo contrario; la revisión lo
 * desmintió y §4.7 lo dejó escrito.
 *
 * La regla, dicha de una vez: **un controlador de `/api/v1` traduce HTTP ↔ dominio y nada más.**
 * Recibe una petición, llama a un servicio del dominio y serializa lo que le devuelva. Si necesita
 * abrir una transacción, escribir un modelo o contar intentos, es que la regla se está escribiendo
 * en el sitio equivocado: ese código pertenece a un servicio de Booking, Payments o Identity, donde
 * la web y el panel también puedan usarlo. Fase 2 rescató así `MAX_LINES_PER_CART`, y el spec §4.6
 * enumera las cinco extracciones que Fase 3 debe hacer por el mismo motivo.
 *
 * Se escanea con el TOKENIZADOR, no con expresiones regulares sobre el texto: los docblocks de
 * estos controladores citan `DB::transaction` y `OrderCreator` a propósito —para explicar por qué
 * NO están— y una regex los contaría como infracciones.
 */
class ApiBoundariesTest extends TestCase
{
    /**
     * Llamadas ESTÁTICAS prohibidas, por `Clase::método`. La clase se compara por nombre corto:
     * da igual si llega por `use` o con el FQCN escrito entero.
     *
     * @var array<string, list<string>>
     */
    private const FORBIDDEN_STATIC = [
        // Persistencia cruda. Una transacción en un controlador significa que la unidad de trabajo
        // —lo que debe ocurrir junto o no ocurrir— la está decidiendo la capa HTTP.
        'DB' => ['transaction', 'table', 'insert', 'update', 'delete', 'statement', 'unprepared'],
        // Contar intentos es política de seguridad (`SEC-06`), y vive en un limitador nombrado o en
        // un servicio de Identity, no repartida por controladores.
        'RateLimiter' => ['hit', 'attempt', 'increment', 'clear'],
        // Bloqueos: si un endpoint necesita un lock es que está orquestando concurrencia, que es
        // exactamente lo que `OrderCreator` ya hace de forma verificada (`INVARIANTES §6`).
        'Cache' => ['lock'],
    ];

    /**
     * Métodos de ESCRITURA de Eloquent, prohibidos venga como venga (`Modelo::create(...)` o
     * `$modelo->save()`). Es el corazón de la regla: el estado del dominio no cambia desde HTTP.
     *
     * `update` y `delete` cubren también el caso de la consulta encadenada
     * (`Order::where(...)->update(...)`), que es la forma en que una escritura se disfraza de
     * lectura.
     *
     * @var list<string>
     */
    private const FORBIDDEN_WRITES = [
        'create', 'createQuietly', 'forceCreate', 'firstOrCreate', 'updateOrCreate', 'createOrFirst',
        'save', 'saveQuietly', 'saveMany', 'push',
        'update', 'updateQuietly', 'upsert', 'insert', 'insertOrIgnore', 'insertGetId',
        'delete', 'deleteQuietly', 'forceDelete', 'destroy', 'truncate', 'restore',
        'increment', 'decrement',
    ];

    /**
     * Métodos de escritura cuyo nombre colisiona con operaciones inocentes de otras clases, y que
     * por eso se permiten sobre un receptor concreto. Excepciones CON NOMBRE, como las baselines de
     * `ModuleBoundariesTest`: la alternativa —quitarlos de la lista— dejaría pasar la escritura de
     * verdad.
     *
     * @var array<string, string>
     */
    private const ALLOWED_EXCEPTIONS = [
        // `Collection::push()` construye la respuesta en memoria; no toca la base de datos.
        'Illuminate\Support\Collection::push' => 'construir la respuesta en memoria',
    ];

    /** Directorio de los controladores de la API. */
    private function apiControllerPath(): string
    {
        return app_path('Http/Controllers/Api');
    }

    /**
     * El escaneo nunca puede pasar en vacío. Sin esta guarda, borrar la carpeta —o cambiarle el
     * nombre— dejaría un test verde que no mira nada, que es la peor clase de test verde.
     */
    public function test_the_scan_actually_sees_the_api_controllers(): void
    {
        $files = $this->apiControllerFiles();

        $this->assertNotEmpty(
            $files,
            'no se ha escaneado ningún controlador de API: ¿ha cambiado app/Http/Controllers/Api?'
        );
    }

    /** La guarda principal. */
    public function test_api_controllers_do_not_touch_the_domain_state(): void
    {
        $violations = [];

        foreach ($this->apiControllerFiles() as $file) {
            $relative = mb_substr($file, mb_strlen(base_path()) + 1);

            foreach ($this->violationsIn($file) as $violation) {
                $violations[] = "  {$relative} — {$violation}";
            }
        }

        $this->assertSame(
            [], $violations,
            "Controladores de /api/v1 haciendo trabajo que no es suyo:\n".implode("\n", $violations)."\n\n".
            'Un controlador de la API traduce HTTP ↔ dominio. Lo que abre transacciones, escribe '.
            'modelos o cuenta intentos vive en un servicio del módulo correspondiente, donde la web '.
            'y el panel también puedan usarlo (spec §4.6 y §6.5).'
        );
    }

    /**
     * Recorre los tokens del fichero y devuelve las infracciones legibles.
     *
     * @return list<string>
     */
    private function violationsIn(string $file): array
    {
        $tokens = array_values(array_filter(
            token_get_all((string) file_get_contents($file)),
            static fn (array|string $token): bool => ! is_array($token)
                || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));

        $violations = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || ($token[0] !== T_DOUBLE_COLON && $token[0] !== T_OBJECT_OPERATOR)) {
                continue;
            }

            $next = $tokens[$index + 1] ?? null;
            if (! is_array($next) || $next[0] !== T_STRING) {
                continue;
            }

            $method = $next[1];
            $receiver = $this->receiverBefore($tokens, $index);
            $line = $next[2];

            if ($token[0] === T_DOUBLE_COLON) {
                $shortClass = $receiver === null ? null : (str_contains($receiver, '\\')
                    ? mb_substr($receiver, mb_strrpos($receiver, '\\') + 1)
                    : $receiver);

                if ($shortClass !== null && in_array($method, self::FORBIDDEN_STATIC[$shortClass] ?? [], true)) {
                    $violations[] = "línea {$line}: {$shortClass}::{$method}() — persistencia o política fuera de su sitio";

                    continue;
                }
            }

            if (in_array($method, self::FORBIDDEN_WRITES, true)) {
                $key = ($receiver ?? '?').'::'.$method;
                if (isset(self::ALLOWED_EXCEPTIONS[$key])) {
                    continue;
                }

                $violations[] = "línea {$line}: ->{$method}() — escritura de dominio en la capa HTTP";
            }
        }

        return $violations;
    }

    /**
     * Nombre a la izquierda del `::` o del `->`, si es un símbolo estático reconocible. Devuelve
     * `null` cuando el receptor es una variable (`$user->save()`): ahí no hay clase que mirar, y la
     * prohibición del método basta.
     *
     * @param  list<array{0:int,1:string,2:int}|string>  $tokens
     */
    private function receiverBefore(array $tokens, int $index): ?string
    {
        $previous = $tokens[$index - 1] ?? null;

        if (is_array($previous) && in_array($previous[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
            return ltrim($previous[1], '\\');
        }

        return null;
    }

    /** @return list<string> */
    private function apiControllerFiles(): array
    {
        if (! is_dir($this->apiControllerPath())) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->apiControllerPath(), RecursiveDirectoryIterator::SKIP_DOTS)
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
