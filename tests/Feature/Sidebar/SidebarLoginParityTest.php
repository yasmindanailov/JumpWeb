<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use App\Http\Middleware\SetLocale;
use App\Livewire\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.4a·2 — **el login del cajón dice lo mismo y lo dice en el mismo sitio**.
 *
 * El diff de árbol no puede ver nada de esto: descarta los nodos de texto, así que compara un banner
 * vacío con un banner lleno sin inmutarse. Y aquí hay una divergencia REAL esperando, **medida antes
 * de escribir una línea**: los dos motores tienen textos DISTINTOS para el mismo rechazo.
 *
 *  - web (`auth.failed`): «Estas credenciales no coinciden con nuestros registros.»
 *  - API (`invalid_credentials`): «El correo o la contraseña no son correctos.»
 *
 * Por eso el cajón ramifica sobre el CÓDIGO del sobre y pinta el literal del diccionario. Pintar el
 * `message` habría cambiado la copia del cajón en las tres lenguas sin que ningún gate lo dijera.
 *
 * ⚠️ Y el REPARTO importa tanto como el texto (hallazgo L-02 de la auditoría del origen): el aviso del
 * limitador va al banner `_global` y el de credenciales **bajo el campo email**. Juntarlos mezcla un
 * mensaje genérico —que no revela si el correo existe— con uno que sí dice algo del sistema.
 */
class SidebarLoginParityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'contraseña-correcta';

    private function user(string $email = 'cliente@jumpweb.test'): User
    {
        return User::factory()->create(['email' => $email, 'password' => bcrypt(self::PASSWORD)]);
    }

    // ── Credenciales que no casan ─────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El aviso va BAJO EL CAMPO y con el literal de la web, no con el `message` del sobre.**
     *
     * Se comprueban las dos cosas por separado: que coincide con lo que pinta Livewire, y que NO
     * coincide con lo que manda la API — sin lo segundo, alguien podría «simplificar» el módulo para
     * pintar `error.message` y el caso seguiría verde el día que los dos textos se parecieran.
     */
    public function test_bad_credentials_say_the_same_in_both_engines(): void
    {
        $user = $this->user();

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            // ⚠️ **Los dos motores consumen el MISMO cubo** (`SEC-06`, y es justo lo que hace segura la
            // extracción a `PasswordLogin`): tres idiomas × dos intentos agotan el limitador a mitad del
            // recorrido, y el tercer idioma compararía un 429 contra un rechazo de credenciales. Se
            // vacía entre idiomas para que cada uno mida lo que este caso compara.
            cache()->clear();

            $web = Livewire::test(Login::class, ['embedded' => true])
                ->set('email', $user->email)->set('password', 'la-que-no-es')->call('login');

            $server = (string) $web->errors()->first('email');
            $client = $this->clientErrors($user->email, 'la-que-no-es');

            $this->assertNotSame('', $server, 'el servidor tiene que rechazar unas credenciales malas');
            $this->assertSame(
                $server,
                $client['fields']['email'] ?? '',
                "El aviso de credenciales en «{$locale}» NO dice lo mismo en los dos motores.\n".
                '⚠️ El diff de árbol descarta los nodos de texto: este aviso solo lo compara este test.'
            );
            $this->assertSame('', $client['global'], 'el aviso de credenciales no va al banner (L-02)');
        }
    }

    /**
     * **La otra mitad**: el sobre de la API dice otra cosa, y por eso el cajón NO lo pinta.
     *
     * Si algún día los dos textos se unifican en el servidor, este caso caerá — y estará bien que
     * caiga: sería la señal de que el rodeo por el diccionario ya no hace falta.
     */
    public function test_the_api_message_is_a_different_text_and_that_is_why_it_is_not_painted(): void
    {
        $user = $this->user();

        $response = $this->loginByApi($user->email, 'la-que-no-es');
        $apiMessage = (string) $response->json('error.message');

        $web = Livewire::test(Login::class, ['embedded' => true])
            ->set('email', $user->email)->set('password', 'la-que-no-es')->call('login');

        $this->assertSame('invalid_credentials', $response->json('error.code'));
        $this->assertNotSame(
            (string) $web->errors()->first('email'),
            $apiMessage,
            'si los dos textos ya coinciden, el rodeo por el diccionario sobra: revísalo a propósito'
        );
        $this->assertSame(
            $apiMessage,
            $response->json('error.message'),
            'el sobre sigue trayendo su mensaje: lo que cambia es que el cajón no lo usa'
        );
    }

    // ── Límite de intentos ────────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El aviso del limitador va al BANNER y lleva los segundos del servidor.**
     *
     * El tiempo se congela para que los dos motores midan el MISMO `retry_after`: sin eso, el segundo
     * en pedirlo vería un número menor y el caso compararía dos textos que difieren en un dígito por
     * un motivo que no es el que se está probando.
     */
    public function test_the_rate_limit_notice_says_the_same_in_both_engines(): void
    {
        $user = $this->user();
        $this->freezeTime();

        // Se agota por la puerta de la API; el limitador es el MISMO servicio en los dos motores.
        for ($i = 0; $i < 12; $i++) {
            $this->loginByApi($user->email, 'la-que-no-es');
        }

        $response = $this->loginByApi($user->email, 'la-que-no-es');
        $this->assertSame(429, $response->getStatusCode(), 'el limitador tiene que haber saltado');
        $this->assertSame('too_many_requests', $response->json('error.code'));

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $web = Livewire::test(Login::class, ['embedded' => true])
                ->set('email', $user->email)->set('password', 'la-que-no-es')->call('login');

            $server = (string) $web->errors()->first('_global');
            $client = $this->clientErrors($user->email, 'la-que-no-es');

            $this->assertNotSame('', $server, 'el limitador tiene que avisar también en el motor web');
            $this->assertSame(
                $server,
                $client['global'],
                "El aviso del limitador en «{$locale}» NO dice lo mismo en los dos motores.\n".
                'Los segundos salen de `retry_after` en el sobre y del veredicto en Livewire: si el '.
                'número difiere, es que uno de los dos no está leyendo el limitador que cree.'
            );
            $this->assertSame([], $client['fields'], 'el aviso del limitador NO va bajo el campo (L-02)');
        }
    }

    // ── Validación ────────────────────────────────────────────────────────────────────────────

    /**
     * Los mensajes de validación los escribe el servidor con las MISMAS reglas en los dos motores
     * (`required|string|email`), así que el cajón los pinta tal cual. Este caso comprueba que esa
     * suposición es cierta —lo fue al medirla— y que sigue siéndolo.
     */
    public function test_the_validation_messages_are_the_same_in_both_engines(): void
    {
        $cases = [
            'vacío' => ['', ''],
            'email con formato inválido' => ['no-es-un-email', 'x'],
        ];

        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            foreach ($cases as $label => [$email, $password]) {
                $web = Livewire::test(Login::class, ['embedded' => true])
                    ->set('email', $email)->set('password', $password)->call('login');

                $client = $this->clientErrors($email, $password);

                $this->assertSame(
                    (string) $web->errors()->first('email'),
                    $client['fields']['email'] ?? '',
                    "El aviso de «{$label}» del campo email en «{$locale}» NO coincide."
                );
                $this->assertSame(
                    (string) $web->errors()->first('password'),
                    $client['fields']['password'] ?? '',
                    "El aviso de «{$label}» del campo contraseña en «{$locale}» NO coincide."
                );
            }
        }
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    private function loginByApi(string $email, string $password): TestResponse
    {
        return $this->postJson(
            '/api/v1/auth/login',
            ['email' => $email, 'password' => $password],
            ['Origin' => config('app.url')],
        );
    }

    /**
     * Los avisos que compondría el cajón: la respuesta REAL de la API pasada por el módulo REAL.
     *
     * @return array{global: string, fields: array<string, string>}
     */
    private function clientErrors(string $email, string $password): array
    {
        // ⚠️ El diccionario se toma ANTES de la petición: `SetLocale` corre en cada una y deja la app
        // en el idioma que negocie, así que leerlo después compararía español contra inglés.
        $texts = ['messages' => __('tickets'), 'auth' => __('auth')];
        $locale = app()->getLocale();

        $response = $this->loginByApi($email, $password);

        $this->app->setLocale($locale);

        return $this->runInNode([
            'response' => [
                'ok' => $response->getStatusCode() < 400,
                'status' => $response->getStatusCode(),
                'data' => $response->json(),
                'error' => $response->json('error'),
            ],
            'texts' => $texts,
        ]);
    }

    /**
     * Ejecuta `login.js` en Node, que es lo que corre en el navegador.
     *
     * @param  array<string, mixed>  $input
     * @return array{global: string, fields: array<string, string>}
     */
    private function runInNode(array $input): array
    {
        $script = <<<'JS'
            import { loginErrors } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { response, texts } = JSON.parse(raw);
                process.stdout.write(JSON.stringify(loginErrors(response, texts)));
            });
            JS;

        $path = base_path('storage/framework/testing/login-errors.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/login.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de login falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
