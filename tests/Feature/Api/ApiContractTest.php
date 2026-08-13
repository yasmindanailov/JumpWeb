<?php

namespace Tests\Feature\Api;

use App\Http\Api\ApiErrorCode;
use App\Http\Api\ApiSurface;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — **el contrato manda** (`DECISIONES #21`, spec §2 criterio 2).
 *
 * `openapi/v1.yaml` no se genera desde el código: se escribe a mano, y estas guardas impiden que
 * el código y el documento se separen sin que nadie se entere. Reparten el trabajo con los tests
 * de cada endpoint:
 *
 *  - **allí** (vía `ApiTestCase`) se valida la RESPUESTA REAL contra el esquema, que es lo que la
 *    v1 del spec no hacía y por lo que su criterio de éxito era inauditable;
 *  - **aquí** se valida que las dos listas —rutas registradas y `paths` del documento— son la
 *    misma, y que el esquema es lo bastante ESTRICTO como para que esa validación muerda.
 *
 * Ese último punto es el que sostiene la prueba por mutación del estándar de Fase 2: renombrar un
 * campo de `UserResource` deja el test del endpoint en rojo *solo si* el esquema declara
 * `additionalProperties: false` y lista el campo en `required`. Si alguien relajara el esquema, la
 * mutación dejaría de morder y nadie lo notaría — salvo por
 * `test_response_schemas_are_strict_enough_for_a_rename_to_fail`.
 */
class ApiContractTest extends TestCase
{
    /**
     * Campos OPCIONALES a propósito, con su porqué. Exención con nombre, no laxitud: la alternativa
     * —permitir que cualquier esquema declare menos `required` que `properties`— convertiría la
     * guarda en decorativa, que es justo lo que `ModuleBoundariesTest` evita con sus baselines.
     *
     * Cada entrada debe seguir existiendo en el esquema: si el campo desaparece, el test cae y hay
     * que borrar la entrada. La lista solo encoge.
     *
     * @var array<string, list<string>>
     */
    private const OPTIONAL_BY_DESIGN = [
        // El sobre de error omite estos dos cuando están vacíos (spec §4.3): un `"fields": {}` en
        // cada 500 sería ruido que todo cliente tendría que aprender a ignorar.
        'Error.error' => ['params', 'fields'],
        // Los campos de paginación solo existen donde hay paginación. La alternativa —emitir
        // `current_page: 1, last_page: 1` en una lista que no pagina— sería fingir una paginación
        // que el endpoint ignora: `?page=2` no haría nada. Que estén o no ES la señal de si se
        // puede paginar; `total` va siempre, para que leerlo no exija saberlo.
        'ListMeta' => ['current_page', 'last_page', 'per_page'],
        // Esquema de PETICIÓN, no de respuesta: un cuerpo sí puede tener campos legítimamente
        // opcionales. `remember` por defecto es `false`, y exigirlo obligaría a todo cliente a
        // enviarlo. Lo que sigue mordiendo aquí es `additionalProperties: false`, que es lo que
        // impide colar un campo que el servidor ignoraría en silencio.
        'LoginRequest' => ['remember'],
        // Mismo caso: cuerpo de PETICIÓN. `marketing` es un consentimiento opcional por definición
        // (exigirlo sería pedir una respuesta a algo que puede no contestarse), `context` tiene
        // valor por defecto, y los dos anti-bot solo los envía quien los tiene: el señuelo
        // `website` lo rellenan los bots y `turnstile_token` solo existe si la instalación
        // configuró claves. Obligarlos convertiría en 422 a un cliente correcto.
        'RegisterRequest' => ['marketing', 'context', 'website', 'turnstile_token'],
        // Cuerpo de PETICIÓN otra vez, y por el mismo motivo. Una línea de cesta sin complementos y
        // sin datos de evento es lo normal —una entrada suelta—, así que exigir los dos campos
        // convertiría en 422 la petición más frecuente de todas. `additionalProperties: false`
        // sigue impidiendo colar un campo que el servidor ignoraría en silencio.
        'QuoteRequestItem' => ['event_data', 'addons'],
    ];

    /** @var array<string, mixed>|null */
    private static ?array $contract = null;

    /** @return array<string, mixed> */
    private function contract(): array
    {
        return self::$contract ??= Yaml::parseFile(
            base_path((string) config('api.openapi.directory').'/'.(string) config('api.openapi.file'))
        );
    }

    /** El documento tiene que existir y parsear: sin esto, todo lo demás pasaría en vacío. */
    public function test_the_contract_document_parses_and_declares_paths(): void
    {
        $contract = $this->contract();

        $this->assertSame('3.0.3', $contract['openapi'] ?? null, 'Spectator valida contra OpenAPI 3.0');
        $this->assertNotEmpty($contract['paths'] ?? [], 'el documento no declara ningún path');
    }

    /** La versión vive en la URL (spec §4.1) y el documento debe decir la misma que el router. */
    public function test_the_contract_server_matches_the_route_prefix(): void
    {
        $this->assertSame(
            '/'.ApiSurface::PREFIX,
            $this->contract()['servers'][0]['url'] ?? null,
            'el `servers.url` del documento no coincide con `ApiSurface::PREFIX`'
        );
    }

    /** Ruta publicada que el documento no describe = contrato incompleto. */
    public function test_every_registered_route_is_declared_in_the_contract(): void
    {
        $missing = array_diff($this->registeredOperations(), $this->contractOperations());

        $this->assertSame(
            [], array_values($missing),
            "Rutas de la API sin describir en openapi/v1.yaml:\n  ".implode("\n  ", $missing)
        );
    }

    /**
     * Y al revés: una operación documentada sin ruta es una promesa que el cliente móvil se
     * creería. Esta dirección es la que suele quedarse sin vigilar.
     */
    public function test_every_contract_operation_is_a_registered_route(): void
    {
        $missing = array_diff($this->contractOperations(), $this->registeredOperations());

        $this->assertSame(
            [], array_values($missing),
            "Operaciones documentadas que ninguna ruta sirve:\n  ".implode("\n  ", $missing)
        );
    }

    /**
     * Los `code` son el contrato que el cliente PROGRAMA (spec §4.3). El documento y el enum tienen
     * que enumerar exactamente los mismos: uno nuevo en PHP sin documentar es un código que el
     * cliente no espera; uno documentado sin emisor es una rama muerta en el cliente.
     */
    public function test_the_error_codes_of_the_contract_match_the_enum(): void
    {
        $documented = $this->contract()['components']['schemas']['Error']['properties']['error']['properties']['code']['enum'] ?? [];
        $implemented = array_map(static fn (ApiErrorCode $case): string => $case->value, ApiErrorCode::cases());

        sort($documented);
        sort($implemented);

        $this->assertSame($implemented, $documented, 'ApiErrorCode y el enum del documento han divergido');
    }

    /**
     * La estrictez del esquema ES la prueba por mutación: sin `additionalProperties: false` un
     * campo de más pasaría inadvertido, y sin `required` completo un campo renombrado también.
     * Con las dos, renombrar `email` a `mail` en `UserResource` pone en rojo el test del endpoint.
     */
    public function test_response_schemas_are_strict_enough_for_a_rename_to_fail(): void
    {
        foreach ($this->contract()['components']['schemas'] ?? [] as $name => $schema) {
            $this->assertObjectSchemaIsStrict($name, $schema);
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function assertObjectSchemaIsStrict(string $name, array $schema): void
    {
        if (($schema['type'] ?? null) !== 'object' || ! isset($schema['properties'])) {
            return;
        }

        $this->assertFalse(
            $schema['additionalProperties'] ?? null,
            "El esquema «{$name}» admite campos no declarados: un campo de más pasaría el test"
        );

        $optional = self::OPTIONAL_BY_DESIGN[$name] ?? [];

        foreach ($optional as $field) {
            $this->assertArrayHasKey(
                $field, $schema['properties'],
                "OPTIONAL_BY_DESIGN: «{$name}.{$field}» ya no existe en el esquema — quita la entrada"
            );
        }

        $this->assertSame(
            array_values(array_diff(array_keys($schema['properties']), $optional)),
            array_values($schema['required'] ?? []),
            "El esquema «{$name}» no exige todos sus campos: omitir uno no rompería el test. ".
            'Si es opcional de verdad, decláralo en OPTIONAL_BY_DESIGN con su porqué.'
        );

        foreach ($schema['properties'] as $property => $subSchema) {
            if (is_array($subSchema)) {
                $this->assertObjectSchemaIsStrict("{$name}.{$property}", $subSchema);
            }
        }
    }

    /**
     * La zona anidada en un producto está escrita INLINE y no como `$ref`, y esta guarda es el
     * precio de esa decisión.
     *
     * El motivo (Fase 3 · paso 1b): en OpenAPI 3.0 un `$ref` no admite `nullable` a su lado, y la
     * forma canónica de sortearlo —`allOf: [$ref]` con `nullable: true`— **no funciona** con el
     * validador de Spectator: se midió, y con ella una zona nula falla («The data (null) must match
     * the type: object») y una zona presente también («The data (object) must match the type:
     * null»). La única forma que valida las dos es el objeto escrito entero, con su `nullable`.
     *
     * Copiar un esquema abre la puerta a que las copias se separen, así que aquí se comprueba que
     * dicen exactamente lo mismo que el componente `CatalogZone`. Si mañana el validador soporta la
     * forma canónica, esto se borra junto con las copias.
     */
    public function test_the_inlined_zone_schemas_say_the_same_as_the_component(): void
    {
        $schemas = $this->contract()['components']['schemas'] ?? [];
        $component = $schemas['CatalogZone'] ?? null;

        $this->assertIsArray($component, 'falta el componente `CatalogZone`');

        foreach (['CatalogProduct', 'CatalogProductDetail'] as $owner) {
            $inline = $schemas[$owner]['properties']['zone'] ?? null;

            $this->assertIsArray($inline, "«{$owner}» ya no declara la zona inline");
            $this->assertTrue(
                $inline['nullable'] ?? false,
                "«{$owner}.zone» tiene que ser anulable: hay productos sin zona"
            );
            $this->assertSame(
                $component['properties'],
                $inline['properties'] ?? null,
                "«{$owner}.zone» ha divergido de `CatalogZone`: sus propiedades ya no coinciden"
            );
            $this->assertSame(
                $component['required'],
                $inline['required'] ?? null,
                "«{$owner}.zone» ha divergido de `CatalogZone`: sus campos obligatorios ya no coinciden"
            );
        }
    }

    /**
     * Operaciones REGISTRADAS, como `GET /me`. Se identifican por el nombre `api.v1.*` y no por el
     * path: es lo que permite añadir rutas fuera del contrato (si alguna vez hiciera falta) sin
     * que la guarda se vuelva adivinatoria.
     *
     * @return list<string>
     */
    private function registeredOperations(): array
    {
        $operations = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'api.v1.')) {
                continue;
            }

            $path = '/'.ltrim(mb_substr($route->uri(), mb_strlen(ApiSurface::PREFIX)), '/');

            foreach ($route->methods() as $method) {
                // HEAD lo añade Laravel solo por cada GET; OpenAPI no lo documenta aparte.
                if ($method === 'HEAD') {
                    continue;
                }

                $operations[] = strtoupper($method).' '.$path;
            }
        }

        sort($operations);

        return $operations;
    }

    /** @return list<string> */
    private function contractOperations(): array
    {
        $operations = [];

        foreach ($this->contract()['paths'] ?? [] as $path => $methods) {
            foreach (array_keys($methods) as $method) {
                $operations[] = strtoupper((string) $method).' '.$path;
            }
        }

        sort($operations);

        return $operations;
    }
}
