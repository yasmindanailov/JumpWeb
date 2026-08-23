<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Identity\Models\User;
use App\Http\Middleware\SetLocale;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Fase 4 · paso 4.7·2b·3 — **el punto de montaje del cajón SPA**, que es el único que queda.
 *
 * Nació como `SidebarEngineTest` para vigilar la CONVIVENCIA de los dos motores tras el flag
 * `sidebar.engine` (paso 4.1). Retirado `Purchase.php` y con él el flag, sus tres casos del ajuste y
 * el que comparaba un motor con el otro se quedaron sin sujeto y se fueron con ellos
 * (`CONVENCIONES §3.quater`: se clasifica por el SUJETO, no por la regla que mencionan). Lo que
 * sigue vivo —y por eso el fichero se queda, con nombre nuevo— es lo que el SERVIDOR le pone al
 * cajón en el HTML y que el cliente no puede pedir:
 *
 *  1. que el hueco de montaje exista dentro del cuerpo del cajón;
 *  2. el payload de i18n, la identidad del titular y el desenlace del pago ya CONSUMIDO;
 *  3. y que `@livewireScripts` se siga sirviendo, que es la guarda delicada de todo esto.
 */
class SidebarMountTest extends TestCase
{
    use RefreshDatabase;

    // ── El hueco de montaje ───────────────────────────────────────────────────────────────────

    /**
     * El cuerpo del cajón y su hueco van JUNTOS en un caso: `sidecart__body` es el contenedor que
     * `app.js` abre y cierra, y el hueco es lo que se monta dentro. Antes eran dos casos espejo —uno
     * por motor— y el del motor viejo aseveraba `assertDontSee('id="sidecart-spa"')`, que es
     * exactamente lo que ahora tiene que verse.
     */
    public function test_the_page_carries_the_drawer_body_with_the_spa_mount_point_inside(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('sidecart__body', false);
        $response->assertSee('id="sidecart-spa"', false);
    }

    /**
     * ⚠️⚠️ **La ÚNICA aserción de `livewire.js` de toda la suite, y lo que de verdad vigila NO es lo
     * que decía la nota que heredó.** Lo que sigue está MEDIDO con la tabla de verdad entera, no
     * leído (`CONVENCIONES §3.quater`), porque la primera mutación salió INERTE.
     *
     * Lo que hay que proteger es el RESULTADO: que el bundle de Livewire llegue a la página, porque
     * **Alpine lo trae Livewire** —`app.js` no lo importa ni lo arranca, usa el global que Livewire
     * expone—. Sin él caen a la vez `$store.purchase` (lo que ABRE este cajón desde los once puntos
     * de la landing), `$store.auth` y los tres modales de auth.
     *
     * Y a ese resultado llegan **DOS fuentes redundantes**, que es justo por lo que una mutación de
     * una sola rama no dice nada (§3.quater, trampa 4):
     *
     *  · la directiva `@livewireScripts`, que emite **incondicionalmente**;
     *  · la **auto-inyección** de Livewire (`inject_assets`, default del paquete: el proyecto no
     *    publica `config/livewire.php`), que inyecta el bundle al terminar la petición **solo si un
     *    componente Livewire llegó a renderizarse** (`SupportAutoInjectedAssets`:
     *    `$hasRenderedAComponentThisRequest`).
     *
     * ⚠️⚠️ **Y desde el 2026-08-23 el layout renderiza UNO, no cuatro** (`DECISIONES #122`): los tres
     * modales de auth se retiraron y solo queda `account-context`. La redundancia sigue existiendo,
     * pero **colgando de un solo hilo**: el día que `account-context` migre a Vue —la última ficha de
     * `DEUDA.md`— la directiva pasa a ser la fuente ÚNICA, y retirarla dejará la web sin Alpine y con
     * ella el cajón entero. Este caso es el que se pondrá rojo entonces, y por eso asevera el
     * RESULTADO y no la directiva.
     *
     * Tabla medida el 2026-08-21 sobre este mismo caso:
     * directiva SÍ + componentes SÍ → verde (estado real) · directiva NO + componentes SÍ → **verde**
     * · directiva SÍ + componentes NO → **verde** · directiva NO + componentes NO → **ROJO**.
     *
     * ▶ De ahí las dos consecuencias que importan al siguiente que pase por aquí:
     *  1. **Retirar hoy `@livewireScripts` NO rompe nada** —la auto-inyección lo tapa—, así que la
     *     nota de `layout.blade.php` que lo daba por fatal estaba equivocada y se ha corregido.
     *  2. **El peligro real es el ÁREA DE CLIENTE** (`DECISIONES #66`): ese trabajo retira el modal
     *     de auth de la cabecera y puede llevarse `account-context`. El día que caiga el último
     *     componente Livewire del layout, la directiva pasa a ser la **fuente única** y retirarla sí
     *     deja la web sin Alpine. Este caso es el que se pondrá rojo entonces, y por eso asevera el
     *     resultado y no la directiva.
     *
     * Eran dos casos —uno por motor— porque Livewire memoiza que ya emitió sus assets y ese estado
     * estático sobrevive entre peticiones del mismo test (familia `SUITE-02`): con los dos modos en
     * un solo caso, el segundo `get()` no los emitía. Con un motor único basta una petición, así que
     * el motivo de partirlos se fue con el flag.
     */
    public function test_livewire_scripts_are_still_served(): void
    {
        $this->get('/')->assertSee('livewire.js', false);
    }

    // ── El payload del montaje ────────────────────────────────────────────────────────────────

    /**
     * La SPA no tiene canal de i18n propio (§4.5): son 169 claves × 3 locales que salen de `__()` en
     * servidor. Viajan en el montaje, no por un endpoint, porque ya están resueltas al pintar la
     * página y pedirlas costaría una petición más justo en el arranque.
     */
    public function test_the_mount_point_carries_the_translations_of_the_active_locale(): void
    {
        $boot = $this->bootPayload($this->get('/')->getContent());

        $this->assertIsArray($boot['messages'] ?? null);
        $this->assertSame(__('tickets.title'), $boot['messages']['title'] ?? null);
    }

    /**
     * ⚠️ **Quién es el titular viaja con el HTML, y de eso depende una defensa de seguridad.**
     *
     * La cesta del cajón SPA vive en `localStorage` y lleva su dueño dentro; si cambia, se purga
     * (`DECISIONES #38(d)`). La identidad llega en el montaje —antes de que exista ningún `fetch`, en
     * cada carga de página— porque el **logout es una navegación completa**, y ese es justo el caso que
     * la sesión resolvía sola con `invalidate()` y que `localStorage` no tiene: sin este dato, la cesta
     * de quien acaba de salir sigue en pantalla para el siguiente que use el dispositivo.
     *
     * Se comprueban las DOS formas porque la purga compara contra `null`: si `auth()->id()` dejara de
     * viajar, el visitante anónimo parecería «el mismo de siempre» y no se purgaría nada.
     */
    public function test_the_mount_point_carries_who_the_owner_is(): void
    {
        $anonymous = $this->bootPayload($this->get('/')->getContent());

        $this->assertArrayHasKey('userId', $anonymous, 'la clave debe estar siempre: la purga compara contra null');
        $this->assertNull($anonymous['userId']);

        $user = User::factory()->create();
        $authenticated = $this->bootPayload($this->actingAs($user)->get('/')->getContent());

        $this->assertSame($user->id, $authenticated['userId'] ?? null);
    }

    /**
     * ⚠️ **El que consume el desenlace es el LAYOUT.** El paso 4.0a dejó anotado el contraste: con el
     * componente `lazy` se usaba `peek()` aquí porque su `mount()` corría en una petición POSTERIOR;
     * con la SPA **el motor es este mismo documento**, así que el que consume es este.
     *
     * Y consumir importa: una clave que sobreviva reabre el cajón en cada página hasta que caduque
     * la sesión.
     */
    public function test_the_payment_outcome_travels_in_the_payload_and_is_consumed(): void
    {
        SidebarEntry::confirmed('R-ABC123');

        $boot = $this->bootPayload($this->get('/')->getContent());

        $this->assertSame(SidebarEntry::OUTCOME_CONFIRMED, $boot['outcome'] ?? null);
        $this->assertSame('R-ABC123', $boot['orderCode'] ?? null);

        // Segunda carga: ya no queda nada, así que el cajón no se auto-abre otra vez.
        $second = $this->bootPayload($this->get('/')->getContent());

        $this->assertArrayHasKey('outcome', $second, 'la clave debe seguir estando, con valor nulo');
        $this->assertNull($second['outcome'], 'un desenlace que sobrevive reabre el cajón en cada página');
    }

    /**
     * El `peek()` del `<body>` y el `consume()` del hueco corren en la MISMA petición. Si el segundo
     * se llevara el valor antes que el primero, el cajón no se abriría solo y el cliente volvería de
     * pagar a una página normal. `SidebarEntry::consume()` está memoizado por petición para eso.
     */
    public function test_the_drawer_still_opens_itself_on_the_request_that_consumes(): void
    {
        SidebarEntry::confirmed('R-ABC123');

        $this->get('/')->assertSee('data-purchase-open="1"', false);
    }

    // ── La PODA del payload, y lo que tiene que llegar entero ─────────────────────────────────
    //
    // ⚠️⚠️ **Estos cinco casos vivían en `SidebarLoginParityTest` y `SidebarRegisterParityTest`, y
    // se mudan aquí el 2026-08-23** (`specs/auth-en-cajon.md` §4.7.bis). No es orden: aquellos dos
    // ficheros se retiran cuando la auth entre en el cajón —comparan el cajón con un modal que
    // desaparece— y **estos casos no comparan nada**: afirman del `data-boot`, que sobrevive
    // intacto. Clasificados por SUJETO, no por el fichero en que estaban (`CONVENCIONES §3.quater`).
    //
    // ⚠️⚠️⚠️ Y uno de ellos lleva dentro **el techo del payload del montaje**. Borrarlo con su
    // fichero se habría llevado el ÚNICO guardián de lo que viaja en el HTML de todas las páginas
    // públicas, sin que nada fallara — el cuño de `DECISIONES #112`.

    /**
     * ⚠️ **Lo que el paso de identificación pinta tiene que ESTAR en el payload**, y su ausencia es un
     * fallo silencioso: `i18n.js` devuelve `''` cuando falta una clave —en producción un texto que
     * falta no puede tumbar el cajón—, así que un grupo mal podado deja el formulario con rótulos
     * VACÍOS y todo en verde.
     *
     * Se comprueba contra el `data-boot` REAL de la página, no contra una idea de él.
     */
    public function test_the_mount_payload_carries_every_text_the_login_step_paints(): void
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

    /** Y lleva TODO lo que el formulario de ALTA pinta: una clave que falte se pinta VACÍA y nada avisa. */
    public function test_the_mount_payload_carries_every_label_the_signup_form_paints(): void
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

            $register = $this->bootPayload()['account']['register'] ?? [];

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
        // ⚠️ **`forgot` entra el 2026-08-23 y viaja SIN sesión a propósito**
        // (`specs/auth-en-cajon.md` §4.1): las tres pantallas de auth son precisamente las que ve
        // quien NO ha entrado, así que podarlas al invitado las dejaría con los rótulos en blanco.
        $this->assertSame(
            ['login', 'register', 'forgot', 'verify'], array_keys($boot['account'] ?? []),
            'el montaje anónimo lleva textos que solo pinta quien ha iniciado sesión'
        );

        // ⚠️ **Y `verify` va podado clave a clave**, al revés que `login`, `register` y `forgot`: sus
        // 14 rótulos incluyen los de la PÁGINA de verificación de la web, que el cajón no pinta.
        $this->assertSame(
            [
                'eyebrow', 'title', 'sent_to', 'spam_hint', 'resend',
                'resend_in', 'resends_left', 'resend_limit', 'already_have_account',
            ],
            array_keys($boot['account']['verify'] ?? []),
            'el subgrupo `verify` ha dejado de estar podado a lo que la zona de alta pinta'
        );

        // ⚠️ **`resending` fuera a propósito**: la web lo pinta mientras Livewire da la vuelta al
        // servidor; aquí no hay vuelta que esperar. Un texto que viaja en cada página para no
        // pintarse nunca es lo que este presupuesto existe para cazar.
        $this->assertArrayNotHasKey('resending', $boot['account']['verify'] ?? []);

        $anonBytes = strlen((string) json_encode([$boot['account'], $boot['auth']], JSON_UNESCAPED_UNICODE));

        // Medido: 1.671 B en español, 1.575 en inglés y 1.761 en francés, con los dos grupos que el
        // paso 5 pinta —`login` entero, `register` entero desde 4.4b·1 y `auth`—. El techo era 1.024
        // cuando solo viajaba el rótulo de la pestaña de alta; subió **a propósito** al transcribir el
        // formulario. La referencia que lo hace un presupuesto y no un número suelto: el grupo
        // `account` COMPLETO son 9,6 kB, seis veces esto, y viajaría en cada página pública.
        //
        // ⚠️⚠️ **2.048 → 2.176 el 2026-08-23, y lo paga RECUPERAR CONTRASEÑA**
        // (`specs/auth-en-cajon.md` §4.1). Medido: **2.120 B**, **+449** — que es exactamente el
        // subgrupo `account.forgot` entero, sus 9 claves, todas pintadas por la zona: no hay nada que
        // podar. Quedan 56 B de holgura.
        // ▶ **Y la pregunta que este presupuesto existe para provocar se hizo**: ¿tiene que viajar en
        // cada página pública para una pantalla que casi nadie abre? Sí, y no por comodidad: a la
        // zona de recuperar se llega **sin sesión** —por su ruta puerta y desde la de entrar—, así
        // que no hay condición bajo la que esconderla, y pedirla por un endpoint costaría una
        // petición en el arranque para 449 B. Es además la misma decisión que ya se tomó con `login`
        // y `register`, que llevan viajando así desde 4.4b·1.
        //
        // ⚠️ **2.176 → 2.688 el 2026-08-23 (A5): «revisa tu correo» del alta suelta.** Medido:
        // **2.594 B**, **+474** — y ya viene PODADO: de los 14 rótulos de `account.verify` viajan
        // nueve. Se dejaron fuera `intro` y los tres `notice_resend_*`, que son de la PÁGINA de
        // verificación de la web, y también `resending`, que la web pinta esperando a Livewire y aquí
        // no pinta nadie. Quedan 94 B de holgura.
        // ▶ **La referencia que hace legible el número**, medida sobre el `data-boot` real del
        // 2026-08-23: el montaje entero son **12.712 B**, de los que `messages` —el grupo `tickets`,
        // que viaja incondicionalmente desde 4.3·1— son **9.743** (77 %) y TODA la auth, **2.497**
        // (20 %): `login` 294, `register` 1.217, `forgot` 456 y `verify` 481. O sea que esta subida es
        // un **+3,8 %** del payload del cajón, no un salto de orden. Reproducible leyendo el
        // `data-boot` de la home y midiendo cada rama.
        $this->assertLessThan(
            2688, $anonBytes,
            "Los textos de auth del montaje anónimo pesan {$anonBytes} B. Es un presupuesto, no un ".
            'objetivo: si hace falta subirlo, súbelo a propósito sabiendo que viaja en cada página.'
        );
    }

    /**
     * **Y CON sesión llegan, pero podados clave a clave.**
     *
     * ⚠️ Ésta es la mitad que aprieta cuando el ahorro anónimo ya no aplica: `account.orders` son
     * **22 claves** —el detalle del pedido, el bloque de gestión, el post-form— y las zonas pintan
     * **doce**. Sin esta guarda, un `__('account.orders')` de conveniencia doblaría el payload de
     * quien tiene sesión y **nadie lo vería**: el contrato de árbol no mira tamaños y el resto de la
     * suite, tampoco.
     */
    public function test_the_mount_payload_of_a_signed_in_customer_is_pruned_key_by_key(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $boot = $this->bootPayload((string) $this->actingAs($user)->get('/')->assertOk()->getContent());

        $this->assertSame(['login', 'register', 'forgot', 'verify', 'account', 'sidecart', 'orders'], array_keys($boot['account'] ?? []));
        $this->assertSame(['title', 'password', 'sessions', 'profile', 'privacy'], array_keys($boot['account']['account'] ?? []));

        // ⚠️ **`privacy` va podado clave a clave, al revés que los tres subgrupos de al lado.**
        $this->assertSame(
            [
                'title', 'intro', 'consents_title', 'no_consents', 'export_btn',
                'delete_title', 'delete_intro', 'delete_password',
                'delete_confirm', 'delete_btn', 'deleting',
            ],
            array_keys($boot['account']['account']['privacy'] ?? []),
            'el subgrupo `privacy` ha dejado de estar podado a lo que la zona pinta'
        );

        // ⚠️ **Los cuatro `consent_types` siguen FUERA a propósito**: el rótulo del documento lo
        // publica la API (`Consent.type_label`), para que el cajón no lleve una segunda tabla de
        // nombres que envejece sola el día que se añada un quinto tipo de consentimiento.
        $this->assertArrayNotHasKey(
            'consent_types', $boot['account']['account']['privacy'] ?? [],
            'el cajón ha empezado a llevar su propia tabla de rótulos de consentimiento'
        );

        // ⚠️ El aviso de «no coinciden» lo compone el SERVIDOR con `validation.confirmed`, para que
        // diga lo mismo que la página web. Si desaparece, el cajón lo pintaría VACÍO y nada avisaría.
        $this->assertNotSame('', (string) ($boot['account']['account']['password']['mismatch'] ?? ''));
        $this->assertSame(['upcoming_count'], array_keys($boot['account']['sidecart'] ?? []));

        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`: aquél conserva
            // el del array de origen. Escribirlo aquí como se escribió el filtro daba un rojo que se
            // lee como «falta una clave» cuando lo único que pasa es que están en otro sitio.
            [
                'event_data_show', 'event_data_hide',
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

        // ⚠️⚠️ **EL TECHO DEL PAYLOAD DEL MONTAJE.** Historia de cómo llegó a 4.800, porque es lo que
        // lo convierte en un presupuesto y no en un número suelto:
        // · **2.309 B** al cerrar la tanda 1 (el anónimo son 1.608, así que el área cuesta **701 B a
        //   quien tiene sesión y 0 al resto**);
        // · **4.523 B** al cerrar la tanda 2. El techo estaba en 4.096, subido por adelantado para que
        //   la tanda cupiera, y su propia nota decía que al terminar había que **bajarlo a lo medido**
        //   — que es la mitad de la regla que casi nunca se cumple. Se fijó en **4.608**: 85 B de
        //   holgura, tan estrecho a propósito para provocar la pregunta correcta la próxima vez;
        // · y la provocó **a los dos pasos**: la tanda 3 añadió `consents_title` y `no_consents` y se
        //   pasó por **6 B**. La respuesta fue mirar qué NO añadir —los cuatro `consent_types` se
        //   quedaron fuera y su rótulo lo publica la API—, y creció 91 B en vez de ~150.
        // ▶ Techo **5.120** mientras duró la tanda 3 y **bajado a lo medido al cerrarla**: **4.708 B**,
        //   así que **4.800**, 92 B de holgura. Referencia que lo hace legible: el grupo `account`
        //   COMPLETO son 9,6 kB, el doble de esto.
        //
        // ⚠️ **4.800 → 5.248 el 2026-08-23**, por el mismo `account.forgot` que sube el anónimo: son
        // los MISMOS 449 B, porque las tres pantallas de auth viajan para todo el mundo. Medido:
        // **5.157 B**, y el techo deja **91 B** — la misma holgura estrecha de siempre, a propósito.
        //
        // ⚠️ **5.248 → 5.720 el 2026-08-23 (A5)**, por el mismo `account.verify` que sube el anónimo:
        // los MISMOS 474 B, porque las pantallas de auth viajan para todo el mundo. Medido: **5.631
        // B**, con **89 B** de holgura.
        $this->assertLessThan(
            5720, $bytes,
            "Los textos del montaje con sesión pesan {$bytes} B. Poda antes de subir el techo: el ".
            'grupo `account` entero son 9,6 kB, y la diferencia la paga cada página que el cliente abre.'
        );
    }

    /**
     * El `data-boot` que el layout inyecta de verdad.
     *
     * Sin argumento pide la home; con él, lee el HTML que se le pase — que es como lo usan los casos
     * que necesitan una petición concreta (con sesión, con desenlace sembrado…).
     *
     * @return array<string, mixed>
     */
    private function bootPayload(?string $html = null): array
    {
        $html ??= (string) $this->get('/')->assertOk()->getContent();

        if (preg_match('/id="sidecart-spa" data-boot="([^"]*)"/', $html, $matches) !== 1) {
            $this->fail('no se ha encontrado el punto de montaje de la SPA en la página');
        }

        return json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, 512, JSON_THROW_ON_ERROR);
    }
}
