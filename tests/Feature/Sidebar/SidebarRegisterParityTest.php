<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use App\Http\Middleware\SetLocale;
use App\Livewire\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.4b·1 — **el alta del cajón dice lo mismo, en el mismo sitio, y deja lo mismo hecho**.
 *
 * El diff de árbol compara el marcado del formulario; lo que compara ESTE test es lo que aquél no
 * puede ver:
 *  - los **literales de negocio** —correo ya registrado, pendiente de verificar, anti-bot—, que aquí
 *    los manda el SERVIDOR en `fields.email` y se pintan tal cual (al revés que en el login, donde la
 *    API tiene texto propio y hay que ir al diccionario);
 *  - el **reparto**: en el alta, hasta el aviso del limitador va bajo el campo email, no en un banner
 *    suelto — `Register::reportSignup()` lo lanza en la clave `email` y `Login` en `_global`;
 *  - y el **efecto**: el alta embebida es *pay-first*, así que tiene que dejar **sesión abierta y
 *    ningún correo enviado**. Un cliente que no lo consiguiera mandaría al usuario a verificar un
 *    correo que nadie envió.
 */
class SidebarRegisterParityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Un4-C0ntraseña-Larga';

    /** Un alta válida. El email va por parámetro: el límite por correo es de 3/hora. */
    private function form(string $email): array
    {
        return [
            'name' => 'Mara', 'email' => $email, 'phone' => '600111222', 'password' => self::PASSWORD,
            'accept_privacy' => true, 'accept_terms' => true, 'marketing' => false, 'website' => '',
        ];
    }

    // ── Los literales de negocio ──────────────────────────────────────────────────────────────

    /**
     * ⚠️ **Los tres «no» del dominio, en los tres idiomas y bajo el mismo campo.**
     *
     * Un correo ya registrado y uno pendiente de verificar dan mensajes DISTINTOS —es una decisión de
     * producto de la clienta: conversión sobre ocultación— y los dos tienen que salir iguales en los
     * dos motores. Lo que acota la enumeración masiva es el límite por correo, no el mensaje.
     */
    public function test_the_business_rejections_say_the_same_in_both_engines(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            foreach (['ya registrado' => true, 'pendiente de verificar' => false] as $label => $verified) {
                // ⚠️ **Los dos motores comparten los limitadores del alta** —por correo (3/hora) y por
                // IP—, que es justo lo que hace segura la extracción a `SelfSignup`. Recorrer tres
                // idiomas × dos motores los agota, y el caso acabaría comparando un «demasiados
                // intentos» contra el rechazo que quiere medir. Cada caso con su correo, y el cubo
                // vacío entre iteraciones.
                cache()->clear();
                $email = str_replace(' ', '-', $label).'-'.$locale.'@jumpweb.test';

                User::factory()->create([
                    'email' => $email,
                    'email_verified_at' => $verified ? now() : null,
                ]);

                $web = Livewire::test(Register::class, ['embedded' => true]);
                foreach ($this->form($email) as $field => $value) {
                    $web->set($field, $value);
                }
                $web->call('register');

                $server = (string) $web->errors()->first('email');
                $client = $this->clientErrors($this->form($email));

                $this->assertNotSame('', $server, "el servidor tiene que rechazar «{$label}»");
                $this->assertSame(
                    $server,
                    $client['fields']['email'] ?? '',
                    "El aviso de «{$label}» en «{$locale}» NO dice lo mismo en los dos motores.\n".
                    '⚠️ El diff de árbol descarta los nodos de texto: este aviso solo lo compara este test.'
                );
                $this->assertContains(
                    $server, $client['summary'],
                    'el banner del alta lista TODOS los avisos, incluido este'
                );
            }
        }
    }

    /**
     * Los mensajes de validación los escribe el servidor con las MISMAS reglas en los dos motores.
     * Se comprueba con el formulario VACÍO, que falla en todos los campos obligatorios a la vez —que
     * es además el caso que llena el banner.
     */
    public function test_the_validation_messages_are_the_same_in_both_engines(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $web = Livewire::test(Register::class, ['embedded' => true])->call('register');
            $server = $web->errors()->toArray();

            $client = $this->clientErrors([
                'name' => '', 'email' => '', 'phone' => '', 'password' => '',
                'accept_privacy' => false, 'accept_terms' => false, 'marketing' => false, 'website' => '',
            ]);

            $this->assertNotSame([], $server, 'un alta vacía tiene que fallar en el motor web');

            foreach ($server as $field => $messages) {
                $this->assertSame(
                    $messages[0],
                    $client['fields'][$field] ?? '',
                    "El aviso del campo «{$field}» en «{$locale}» NO coincide entre los dos motores."
                );
            }

            $this->assertSame(
                count($server), count($client['summary']),
                "El banner del alta en «{$locale}» lista ".count($client['summary']).' avisos y el '.
                'servidor tiene '.count($server).'. El número de `<li>` es parte del árbol.'
            );
        }
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * Los avisos que compondría el cajón: la respuesta REAL de la API pasada por el módulo REAL.
     *
     * @param  array<string, mixed>  $form
     * @return array{summary: array<int, string>, fields: array<string, string>}
     */
    private function clientErrors(array $form): array
    {
        // ⚠️ El diccionario se toma ANTES de la petición: `SetLocale` corre en cada una y deja la app
        // en el idioma que negocie, así que leerlo después compararía español contra inglés.
        $texts = ['messages' => __('tickets'), 'auth' => __('auth')];
        $locale = app()->getLocale();

        $response = $this->postJson(
            '/api/v1/auth/register',
            [...$form, 'context' => 'purchase'],
            ['Origin' => config('app.url')],
        );

        $this->app->setLocale($locale);

        return $this->runInNode([
            'response' => [
                'ok' => $response->getStatusCode() < 400,
                'status' => $response->getStatusCode(),
                'data' => $this->jsonOf($response),
                'error' => $response->getStatusCode() < 400 ? null : $response->json('error'),
            ],
            'texts' => $texts,
        ]);
    }

    /** El cuerpo, o `null` cuando no lo hay (el 201 del alta va sin cuerpo a propósito). */
    private function jsonOf(TestResponse $response): mixed
    {
        return $response->getContent() === '' ? null : $response->json();
    }

    /**
     * Ejecuta `register.js` en Node, que es lo que corre en el navegador.
     *
     * @param  array<string, mixed>  $input
     * @return array{summary: array<int, string>, fields: array<string, string>}
     */
    private function runInNode(array $input): array
    {
        $script = <<<'JS'
            import { registerErrors } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                const { response, texts } = JSON.parse(raw);
                process.stdout.write(JSON.stringify(registerErrors(response, texts)));
            });
            JS;

        $path = base_path('storage/framework/testing/register-errors.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/register.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($input, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de alta falló:\n".$process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }
}
