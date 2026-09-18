<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Services\ApiTokenIssuer;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * F4 del programa — **toda ruta autenticada de `/api/v1` exige la ability del cliente**
 * (`docs/specs/token-bearer.md` §4.3, `DECISIONES #630`).
 *
 * Una ability que nadie comprueba no es una guarda: es un campo. Los tokens de la app nacen con
 * `ApiTokenIssuer::ABILITY` y nada más, precisamente para que un token emitido mañana con OTRO fin
 * (la puerta, un kiosko, una integración) no abra la cuenta de nadie — y eso solo es verdad si la
 * superficie lo exige en TODAS sus rutas autenticadas. El modo de fallo es el de siempre: alguien
 * añade una ruta suelta con `->middleware('auth:sanctum')`, fuera del grupo, y se deja la segunda
 * mitad. No falla ningún test, y esa ruta acepta cualquier token.
 *
 * Con cookie de sesión Sanctum entrega un `TransientToken` que responde «sí» a todo, así que la
 * SPA no lo nota (`AuthTokenTest::test_the_authenticated_surface_demands_the_client_ability`).
 */
class ApiTokenAbilityTest extends TestCase
{
    /** @return list<Route> */
    private function authenticatedApiRoutes(): array
    {
        return array_values(array_filter(
            RouteFacade::getRoutes()->getRoutes(),
            static fn (Route $route): bool => str_starts_with($route->uri(), 'api/v1/')
                && in_array('auth:sanctum', $route->gatherMiddleware(), true),
        ));
    }

    /** El escaneo nunca puede pasar en vacío: un prefijo renombrado daría verde sin mirar nada. */
    public function test_the_scan_sees_the_authenticated_surface(): void
    {
        $this->assertGreaterThanOrEqual(30, count($this->authenticatedApiRoutes()), 'Se esperaban al menos 30 rutas tras `auth:sanctum` en `/api/v1` (eran 36 el 2026-09-18).');
    }

    public function test_every_authenticated_api_route_demands_the_client_ability(): void
    {
        $required = 'abilities:'.ApiTokenIssuer::ABILITY;

        $missing = array_map(
            static fn (Route $route): string => implode('|', $route->methods()).' '.$route->uri(),
            array_filter(
                $this->authenticatedApiRoutes(),
                static fn (Route $route): bool => ! in_array($required, $route->gatherMiddleware(), true),
            ),
        );

        $this->assertSame([], array_values($missing), "Rutas autenticadas de la API sin `{$required}`: aceptarían un token emitido para cualquier otra cosa. ".
            'Declárala dentro del grupo autenticado de `routes/api.php`, o añade `$tokenAbility` a su middleware.');
    }

    public function test_the_ability_middleware_alias_resolves(): void
    {
        $this->assertArrayHasKey('abilities', app('router')->getMiddleware(), 'Sanctum trae `CheckAbilities` pero no registra su alias: sin él, `abilities:` revienta en la primera petición.');
    }
}
