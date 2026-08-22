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

    // ── El diccionario que viaja en el montaje ────────────────────────────────────────────────

    /**
     * ⚠️ **Lo que el paso pinta tiene que ESTAR en el payload del montaje**, y es un fallo silencioso:
     * `i18n.js` devuelve `''` cuando falta una clave —en producción un texto que falta no puede tumbar
     * el cajón—, así que un grupo mal podado deja el formulario con rótulos VACÍOS y todo en verde.
     *
     * Se comprueba contra el `data-boot` REAL de la página, no contra una idea de él.
     */
    public function test_the_mount_payload_carries_every_text_the_step_paints(): void
    {
        $boot = $this->bootPayload();

        foreach (['cta', 'eyebrow', 'title', 'email', 'password', 'remember', 'submit', 'submitting'] as $key) {
            $this->assertNotSame(
                '', (string) ($boot['account']['login'][$key] ?? ''),
                "El montaje no lleva `account.login.{$key}`, así que ese rótulo se pintaría VACÍO: ".
                '`i18n.js` devuelve cadena vacía cuando falta una clave, y nada avisa.'
            );
        }

        $this->assertNotSame('', (string) ($boot['account']['register']['cta'] ?? ''), 'falta el rótulo de la pestaña de registro');

        foreach (['failed', 'throttle'] as $key) {
            $this->assertNotSame('', (string) ($boot['auth'][$key] ?? ''), "El montaje no lleva `auth.{$key}`.");
        }
    }

    /**
     * **Y NO lleva de más.** El grupo `account` entero son 9,6 kB en español —tanto como `tickets`— y
     * viajaría en el HTML de **todas** las páginas públicas para pintar diez rótulos. La poda es la
     * decisión; sin esta guarda, el día que alguien escriba `__('account')` nadie lo notaría.
     */
    public function test_the_mount_payload_stays_pruned(): void
    {
        $boot = $this->bootPayload();

        // ⚠️⚠️ **Sin sesión, los textos del ÁREA DE CLIENTE no viajan**, y ésta es la mitad que más
        // ahorra: la landing anónima es la ruta de más tráfico del sitio —la que `PERF-02` protege— y
        // un invitado **no puede abrir** esa sección. Medido: son ~660 B por página que no pintaban
        // nada (`specs/area-cliente.md`).
        $this->assertSame(
            ['login', 'register'], array_keys($boot['account'] ?? []),
            'el montaje anónimo lleva textos que solo pinta quien ha iniciado sesión'
        );

        $anonBytes = strlen((string) json_encode([$boot['account'], $boot['auth']], JSON_UNESCAPED_UNICODE));

        // Medido: 1.671 B en español, 1.575 en inglés y 1.761 en francés, con los dos grupos que el
        // paso 5 pinta —`login` entero, `register` entero desde 4.4b·1 y `auth`—. El techo era 1.024
        // cuando solo viajaba el rótulo de la pestaña de alta; subió **a propósito** al transcribir el
        // formulario. La referencia que lo hace un presupuesto y no un número suelto: el grupo
        // `account` COMPLETO son 9,6 kB, seis veces esto, y viajaría en cada página pública.
        $this->assertLessThan(
            2048, $anonBytes,
            "Los textos de auth del montaje anónimo pesan {$anonBytes} B. Es un presupuesto, no un ".
            'objetivo: si hace falta subirlo, súbelo a propósito sabiendo que viaja en cada página.'
        );
    }

    /**
     * **Y CON sesión llegan, pero podados clave a clave.**
     *
     * ⚠️ Ésta es la mitad que aprieta cuando el ahorro anónimo ya no aplica: `account.orders` son
     * **22 claves** —el detalle del pedido, el bloque de gestión, el post-form— y las zonas de la
     * tanda 1 pintan **doce**. Sin esta guarda, un `__('account.orders')` de conveniencia doblaría el
     * payload de quien tiene sesión y **nadie lo vería**: el contrato no mira tamaños y el resto de la
     * suite, tampoco.
     */
    public function test_the_mount_payload_of_a_signed_in_customer_is_pruned_key_by_key(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $boot = $this->actingAs($user)->bootPayload();

        $this->assertSame(['login', 'register', 'account', 'sidecart', 'orders'], array_keys($boot['account'] ?? []));
        $this->assertSame(['title', 'password', 'sessions', 'profile', 'privacy'], array_keys($boot['account']['account'] ?? []));

        // ⚠️ **`privacy` va podado clave a clave, al revés que los tres subgrupos de al lado**: lleva
        // además `consents_title`, `no_consents` y los cuatro `consent_types`, y **la lista de
        // consentimientos no la pinta el cajón** — sigue solo en `/mi-cuenta`, y es un hueco con
        // nombre para la tanda 3 (`specs/area-cliente.md` §4.8).
        $this->assertSame(
            [
                'title', 'intro', 'export_btn',
                'delete_title', 'delete_intro', 'delete_password',
                'delete_confirm', 'delete_btn', 'deleting',
            ],
            array_keys($boot['account']['account']['privacy'] ?? []),
            'el subgrupo `privacy` ha dejado de estar podado a lo que la zona pinta'
        );

        // ⚠️ El aviso de «no coinciden» lo compone el SERVIDOR con `validation.confirmed`, para que
        // diga lo mismo que la página web. Si desaparece, el cajón lo pintaría VACÍO y nada avisaría.
        $this->assertNotSame('', (string) ($boot['account']['account']['password']['mismatch'] ?? ''));
        $this->assertSame(['upcoming_count'], array_keys($boot['account']['sidecart'] ?? []));

        $this->assertSame(
            [
                'title', 'subtitle', 'empty', 'pagination',
                'item_finished', 'item_cancelled',
                'retry_payment', 'retry_hint',
                'guest_form_pending', 'guest_form_done',
                'guest_form_past', 'guest_form_cancelled',
            ],
            array_keys($boot['account']['orders'] ?? []),
            'el subgrupo `orders` ha dejado de estar podado a lo que las zonas pintan'
        );

        $bytes = strlen((string) json_encode([$boot['account'], $boot['auth']], JSON_UNESCAPED_UNICODE));

        // Medido el 2026-08-22: **2.309 B** en español con las doce claves de las zonas (el anónimo
        // son 1.608, así que el área de cliente cuesta **701 B a quien tiene sesión y 0 al resto**).
        //
        // ⚠️⚠️ **4.523 B al CERRAR la tanda 2** (paso 8, las nueve claves de privacidad). El techo
        // estaba en 4.096 **subido a propósito y por adelantado** para que la tanda cupiera, y su
        // propia nota decía que al terminarla había que **bajarlo a lo medido** — que es lo que casi
        // nunca se cumple, y un presupuesto con margen de sobra deja de ser un presupuesto.
        // ▶ Se fija en **4.608** (4,5 KiB): 85 B de holgura sobre lo medido. Es tan estrecho a
        // propósito. No hay grasa que podar —las nueve claves las pinta la zona, una a una— así que
        // lo que este número tiene que provocar la próxima vez es la pregunta correcta: ¿de verdad
        // hace falta que este texto viaje en el HTML de cada página, o lo pide la pantalla al abrirse?
        // Referencia que lo hace legible: el grupo `account` COMPLETO son 9,6 kB, el doble de esto.
        $this->assertLessThan(
            4608, $bytes,
            "Los textos del montaje con sesión pesan {$bytes} B. Poda antes de subir el techo: el ".
            'grupo `account` entero son 9,6 kB, y la diferencia la paga cada página que el cliente abre.'
        );
    }

    // ── Herramientas ──────────────────────────────────────────────────────────────────────────

    /**
     * El `data-boot` que el layout inyecta de verdad, leído del HTML de la home.
     *
     * @return array<string, mixed>
     */
    private function bootPayload(): array
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/id="sidecart-spa" data-boot="/', (string) $html, 'no está el punto de montaje del cajón SPA');

        preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', (string) $html, $matches);

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }

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
