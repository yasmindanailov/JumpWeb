<?php

namespace Tests\Feature\Api;

use App\Http\Api\ApiSurface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spectator\Spectator;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — base de los tests de `/api/v1`.
 *
 * Hace dos cosas, y las dos importan:
 *
 * 1. **Cablea el contrato.** Cada petición de un test que herede de aquí se valida contra
 *    `openapi/v1.yaml`: no solo «existe la ruta», sino que la **respuesta real** encaja con el
 *    esquema (criterio 2 de §2 del spec). Se configura aquí y no publicando `config/spectator.php`
 *    porque Spectator es dependencia de DESARROLLO: dejar su fichero de config en `config/` lo
 *    haría viajar a producción sin paquete que lo lea.
 *
 * 2. **Fija el prefijo.** El documento declara sus rutas relativas al servidor `/api/v1`, así que
 *    Spectator necesita saber qué parte del path de la petición es prefijo. Sale de
 *    `ApiSurface::PREFIX`, la misma constante que registra las rutas: si el prefijo cambiara, no
 *    hay copia que actualizar.
 *
 * `RefreshDatabase` va aquí porque toda la API toca base de datos, aunque sea solo para
 * autenticar.
 */
abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    /** Raíz de la API con barra inicial: `/api/v1`. Útil para construir URLs en los tests. */
    protected const ROOT = '/'.ApiSurface::PREFIX;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'spectator.sources.local.base_path' => base_path((string) config('api.openapi.directory')),
        ]);

        Spectator::using((string) config('api.openapi.file'));
        Spectator::setPathPrefix(self::ROOT);
    }
}
