<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Domain\Platform\Services\Turnstile;
use App\Http\Middleware\SetLocale;
use App\Livewire\Auth\Register;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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

    // ── El efecto: pay-first ──────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El alta embebida NO manda correo y SÍ deja sesión**, en los dos motores. Es la política
     * *pay-first*: el pago sustituye a la verificación, porque un bot no paga. Un cliente que se
     * registrara con el contexto equivocado mandaría al usuario a esperar un correo que nadie envió.
     */
    public function test_the_embedded_signup_opens_a_session_and_sends_no_email(): void
    {
        Notification::fake();

        $web = Livewire::test(Register::class, ['embedded' => true]);
        foreach ($this->form('web-embebida@jumpweb.test') as $field => $value) {
            $web->set($field, $value);
        }
        $web->call('register');

        $this->assertTrue(auth()->check(), 'el motor web deja sesión abierta tras el alta embebida');
        Notification::assertNothingSent();

        auth()->logout();

        $this->postJson('/api/v1/auth/register', [
            ...$this->form('api-embebida@jumpweb.test'),
            'context' => 'purchase',
        ], ['Origin' => config('app.url')])->assertCreated();

        $this->assertTrue(auth()->check(), 'la API con `context: purchase` también deja sesión abierta');
        Notification::assertNothingSent();
    }

    /**
     * ⚠️ **El señuelo devuelve lo MISMO que un alta buena, y por eso hace falta preguntar.**
     *
     * `POST auth/register` responde 201 sin cuerpo en los dos casos —si no, un bot los distinguiría de
     * un vistazo—, así que lo único que separa un alta real de una fingida es si hay sesión después.
     * Es la razón de que `runRegister()` haga una segunda petición.
     */
    public function test_the_honeypot_is_indistinguishable_from_a_real_signup(): void
    {
        $real = $this->postJson('/api/v1/auth/register', [
            ...$this->form('persona@jumpweb.test'), 'context' => 'purchase',
        ], ['Origin' => config('app.url')]);

        auth()->logout();

        $bot = $this->postJson('/api/v1/auth/register', [
            ...$this->form('bot@jumpweb.test'), 'website' => 'soy-un-bot', 'context' => 'purchase',
        ], ['Origin' => config('app.url')]);

        $this->assertSame($real->getStatusCode(), $bot->getStatusCode(), 'el estado tiene que ser el mismo');
        $this->assertSame($real->getContent(), $bot->getContent(), 'y el cuerpo también, o el señuelo no sirve');

        $this->assertFalse(auth()->check(), 'el señuelo no identifica a nadie');
        $this->assertNull(User::where('email', 'bot@jumpweb.test')->first(), 'ni crea cuenta');
        $this->assertNotNull(User::where('email', 'persona@jumpweb.test')->first(), 'el alta real sí');
    }

    // ── El diccionario del montaje ────────────────────────────────────────────────────────────

    /**
     * ⚠️ **Los dos textos legales viajan con su `<a href>` DENTRO y ya interpolado.**
     *
     * El Blade los pinta con `{!! !!}` y la URL la compone `route()`. Si viajaran sin interpolar, el
     * cajón enseñaría un `:url` literal en medio de un texto legal; y partirlos en «texto + enlace»
     * obligaría al cliente a recomponer una frase traducida que no ordena igual en cada idioma.
     */
    public function test_the_mount_payload_carries_the_legal_texts_with_their_links(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $boot = $this->bootPayload();
            $register = $boot['account']['register'] ?? [];

            foreach (['accept_privacy' => 'legal.privacidad', 'accept_terms' => 'legal.condiciones'] as $key => $route) {
                $text = (string) ($register[$key] ?? '');

                $this->assertStringContainsString('<a ', $text, "«{$key}» tiene que llevar su enlace dentro");
                $this->assertStringNotContainsString(':url', $text, "«{$key}» viaja SIN interpolar: se vería el marcador");
                $this->assertStringContainsString(
                    parse_url(route($route), PHP_URL_PATH) ?: '', $text,
                    "«{$key}» no apunta a la página legal que compone `route()`"
                );
            }
        }
    }

    /** Y lleva TODO lo que el formulario pinta: una clave que falte se pinta VACÍA y nada avisa. */
    public function test_the_mount_payload_carries_every_label_the_form_paints(): void
    {
        $register = $this->bootPayload()['account']['register'] ?? [];

        foreach ([
            'cta', 'eyebrow', 'title', 'subtitle', 'name', 'email', 'phone', 'password',
            'password_hint', 'marketing', 'submit', 'submitting', 'leave_blank', 'fix_errors',
        ] as $key) {
            $this->assertNotSame(
                '', (string) ($register[$key] ?? ''),
                "El montaje no lleva `account.register.{$key}`, así que ese rótulo se pintaría VACÍO: ".
                '`i18n.js` devuelve cadena vacía cuando falta una clave, y nada avisa.'
            );
        }
    }

    // ── La guarda del anti-bot ────────────────────────────────────────────────────────────────

    /**
     * ⚠️ **El eslabón entre el servidor y el cajón, comprobado de punta a punta.**
     *
     * Desde 4.4b·2 el cajón MONTA su propio widget, así que este bit ya no decide «si delegar en el
     * modal de Livewire» sino **con qué clave montar el widget**. Lo que fija no ha cambiado, y es lo
     * que importa: que `GET /config` publique la clave **solo cuando el anti-bot está activo de
     * verdad** —las DOS claves, no una— y que el módulo del cajón lo lea igual. Se comprueban los dos
     * extremos con la respuesta REAL del endpoint pasada por el módulo REAL.
     *
     * ⚠️ Con la clave a medias, el cajón pintaría un widget que no verifica nada y el servidor
     * rechazaría **todas** las altas con «no eres un robot», sin correo y **sin una sola línea de
     * log**: `Turnstile::verify('')` corta antes del POST a Cloudflare y antes de su `Log::warning`.
     * Ese fallo es invisible en los dos extremos, y por eso la equivalencia se comprueba aquí.
     */
    public function test_the_cajon_knows_when_the_signup_needs_a_captcha(): void
    {
        $states = [
            'sin anti-bot' => [[], false],
            'solo la clave pública' => [['security.turnstile_site_key' => 'site'], false],
            'anti-bot ACTIVO' => [['security.turnstile_site_key' => 'site', 'security.turnstile_secret' => 'secreto'], true],
        ];

        foreach ($states as $label => [$settings, $expected]) {
            Setting::query()
                ->whereIn('key', ['security.turnstile_site_key', 'security.turnstile_secret'])->delete();

            foreach ($settings as $key => $value) {
                Setting::updateOrCreate(['key' => $key], ['value' => $value, 'group' => 'security']);
            }

            Setting::flushMemo();
            Turnstile::flushCache();

            $config = $this->getJson('/api/v1/config')->assertOk()->json();

            $this->assertSame(
                Turnstile::enabled(), $expected,
                "con «{$label}» el estado del anti-bot no es el que este caso supone"
            );

            $this->assertSame(
                $expected,
                $this->requiresCaptchaInNode($config),
                "Con «{$label}» el cajón NO decide bien si tiene que montar el widget del anti-bot.\n".
                '⚠️ Montarlo con la configuración a medias, o NO montarlo con el anti-bot activo, '.
                'rechazaría todas las altas con «no eres un robot», sin correo y sin log: un registro '.
                'que no funciona para nadie y que nada delata.'
            );
        }
    }

    /** @param array<string, mixed> $config */
    private function requiresCaptchaInNode(array $config): bool
    {
        $script = <<<'JS'
            import { signupRequiresCaptcha } from 'file://__MODULE__';
            let raw = '';
            process.stdin.setEncoding('utf8');
            process.stdin.on('data', (c) => { raw += c; });
            process.stdin.on('end', () => {
                process.stdout.write(JSON.stringify({ needs: signupRequiresCaptcha(JSON.parse(raw)) }));
            });
            JS;

        $path = base_path('storage/framework/testing/signup-captcha.mjs');

        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, str_replace('__MODULE__', base_path('resources/js/sidebar/register.js'), $script));

        $process = new Process(['node', $path], base_path());
        $process->setInput(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        $process->setTimeout(60);
        $process->run();

        $this->assertTrue($process->isSuccessful(), "El módulo de alta falló:\n".$process->getErrorOutput());

        return (bool) json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR)['needs'];
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function bootPayload(): array
    {
        $html = (string) $this->get('/')->assertOk()->getContent();

        preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $matches);

        $this->assertNotEmpty($matches, 'no está el punto de montaje del cajón SPA');

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }

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
