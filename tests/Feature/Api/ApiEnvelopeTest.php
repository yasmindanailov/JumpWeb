<?php

namespace Tests\Feature\Api;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Http\Api\ApiSurface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

/**
 * Fase 3 · paso 0 — el sobre de error único, probado por el camino REAL (spec §4.3).
 *
 * No hereda de `ApiTestCase` a propósito: varios casos necesitan rutas que no existen en el
 * producto (una que valide, una que reviente, una que no encuentre un modelo) y validarlas contra
 * el documento OpenAPI daría un fallo de «path no declarado» en lugar de probar lo que se quiere
 * probar. Las rutas sintéticas se montan con el grupo `api` COMPLETO y el prefijo real, así que lo
 * que se ejerce es el mismo pipeline que un endpoint de verdad.
 *
 * Lo que estas guardas protegen, y que la revisión del spec señaló como riesgo:
 *  - **una sola forma de error** en toda la superficie, pase lo que pase;
 *  - que el mensaje **nunca** salga de la excepción (fuga de FQCN e ids ajenos);
 *  - que la web quede intacta: el sobre es de `/api/v1`, no del sitio.
 */
class ApiEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    private const ROOT = '/'.ApiSurface::PREFIX;

    /** Ruta sintética dentro del grupo `api`, con el mismo pipeline que un endpoint real. */
    private function fakeRoute(string $uri, callable $action, string $method = 'get'): void
    {
        Route::middleware('api')->prefix(ApiSurface::PREFIX)->{$method}($uri, $action);
    }

    public function test_an_unknown_path_answers_with_the_envelope(): void
    {
        $this->getJson(self::ROOT.'/no-existe')
            ->assertNotFound()
            ->assertExactJson(['error' => [
                'code' => 'not_found',
                'message' => __('api.errors.not_found'),
            ]]);
    }

    /** El 405 conserva `Allow`: es información correcta de HTTP, no detalle interno. */
    public function test_a_wrong_verb_answers_with_the_envelope_and_keeps_allow(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(self::ROOT.'/me');

        $response->assertStatus(405)
            ->assertJsonPath('error.code', 'method_not_allowed');
        $this->assertStringContainsString('GET', (string) $response->headers->get('Allow'));
    }

    /** Validación: 422 con los errores POR CAMPO, en la forma que ya devuelve `$e->errors()`. */
    public function test_a_validation_failure_reports_the_offending_fields(): void
    {
        $this->fakeRoute('__test/validate', static fn (Request $request) => $request->validate([
            'email' => ['required', 'email'],
        ]), 'post');

        $this->postJson(self::ROOT.'/__test/validate', ['email' => 'no-es-un-email'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['email']]]);
    }

    /**
     * Un fallo inesperado no cuenta nada de dentro. Con `APP_DEBUG` activo sí, porque eso es un
     * entorno de desarrollo por definición y quitarle el mensaje solo estorbaría a quien depura.
     */
    public function test_an_unexpected_failure_does_not_leak_internals(): void
    {
        config(['app.debug' => false]);
        $this->fakeRoute('__test/boom', static function (): never {
            throw new RuntimeException('credencial=secreta en el trace');
        });

        $response = $this->getJson(self::ROOT.'/__test/boom');

        $response->assertStatus(500)
            ->assertExactJson(['error' => [
                'code' => 'server_error',
                'message' => __('api.errors.server_error'),
            ]]);
        $this->assertStringNotContainsString('credencial=secreta', (string) $response->getContent());
    }

    /**
     * El caso que motivó la regla: `ModelNotFoundException` se convierte en un 404 cuyo
     * `getMessage()` es «No query results for model [App\Domain\...\User] 999». Servirlo tal cual
     * regalaría el FQCN del modelo y el id ajeno.
     */
    public function test_a_missing_model_does_not_leak_its_class_or_id(): void
    {
        config(['app.debug' => false]);
        $this->fakeRoute('__test/missing', static function (): never {
            throw (new ModelNotFoundException)->setModel(User::class, [999]);
        });

        $response = $this->getJson(self::ROOT.'/__test/missing');

        $response->assertNotFound()->assertJsonPath('error.code', 'not_found');
        $this->assertStringNotContainsString('User', (string) $response->getContent());
        $this->assertStringNotContainsString('999', (string) $response->getContent());
    }

    /**
     * El limitador base responde en el mismo sobre y dice CUÁNDO reintentar: sin `retry_after` un
     * cliente sensato no lo sabe, y el impaciente vuelve a golpear.
     */
    public function test_the_rate_limiter_answers_with_the_envelope_and_a_retry_hint(): void
    {
        config(['api.rate_limit.per_minute' => 1]);
        $this->fakeRoute('__test/public', static fn (): array => ['ok' => true]);

        $this->getJson(self::ROOT.'/__test/public')->assertOk();
        $response = $this->getJson(self::ROOT.'/__test/public');

        $response->assertStatus(429)
            ->assertJsonPath('error.code', 'too_many_requests')
            ->assertHeader('Retry-After');
        $this->assertIsInt($response->json('error.params.retry_after'));
    }

    /**
     * Comportamiento MEDIDO del framework, fijado aquí para que nadie lo descubra por sorpresa:
     * Laravel ordena `AuthenticatesRequests` **antes** que `ThrottleRequests`, así que en una ruta
     * con `auth:` el rechazo por falta de credencial no consume el limitador. Es el estándar y se
     * conserva a propósito (ver el razonamiento en `bootstrap/app.php`); lo que necesita techo de
     * verdad —login, registro, reset, catálogo, disponibilidad— son rutas públicas, donde el
     * limitador sí cuenta, como prueba el test anterior.
     *
     * Si algún día este test se pone en rojo, significa que alguien tocó la prioridad global de
     * middleware: hay que revisar qué le pasó a los `throttle` de la WEB antes de celebrarlo.
     */
    public function test_an_unauthenticated_rejection_does_not_consume_the_limiter(): void
    {
        config(['api.rate_limit.per_minute' => 1]);

        $this->getJson(self::ROOT.'/me')->assertUnauthorized();
        $this->getJson(self::ROOT.'/me')->assertUnauthorized();
    }

    /**
     * El kill-switch de #218 alcanza también a la API (decisión escrita en el spec §4.7): un
     * mantenimiento que apagara la web dejando el dominio abierto por otra puerta no sería tal.
     * Lo que había que resolver era el FORMATO — un cliente JSON no puede parsear la página HTML.
     */
    public function test_maintenance_reaches_the_api_as_json(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);
        Setting::flushMemo();

        $response = $this->getJson(self::ROOT.'/me');

        $response->assertStatus(503)
            ->assertJsonPath('error.code', 'maintenance')
            ->assertHeader('Retry-After');
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
    }

    /** Y la web conserva su 503 HTML on-brand: el cambio es de la API, no del sitio. */
    public function test_maintenance_still_renders_html_on_the_web(): void
    {
        Setting::updateOrCreate(['key' => 'maintenance.site'], ['value' => '1', 'group' => 'maintenance']);
        Setting::flushMemo();

        $response = $this->get('/');

        $response->assertStatus(503);
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
    }

    /** El sobre es de `/api/v1`. Un 404 de la web sigue siendo la página de error de siempre. */
    public function test_the_web_surface_keeps_its_own_error_pages(): void
    {
        $response = $this->get('/ruta-que-no-existe');

        $response->assertNotFound();
        $this->assertStringContainsString('text/html', (string) $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('"error"', (string) $response->getContent());
    }

    /** `SEC-01`: la superficie sensible no se queda fuera de las cabeceras de seguridad. */
    public function test_security_headers_reach_the_api_even_on_errors(): void
    {
        $this->getJson(self::ROOT.'/me')
            ->assertUnauthorized()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }
}
