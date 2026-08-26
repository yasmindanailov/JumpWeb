<?php

namespace Tests\Feature\Sidebar;

use App\Domain\Booking\Models\Order;
use App\Domain\Booking\Models\Slot;
use App\Domain\Booking\Models\TicketType;
use App\Domain\Booking\Models\Zone;
use App\Domain\Identity\Models\User;
use App\Http\Middleware\SetLocale;
use App\Http\Sidebar\SidebarEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
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
     * ⚠️⚠️⚠️ **Y EL DÍA QUE ESTE CASO ANUNCIABA LLEGÓ: EL 2026-08-23 EL LAYOUT NO RENDERIZA NINGUNO.**
     * `account-context` —el último— migró a Vue (`specs/account-context-vue.md`), así que la
     * auto-inyección de Livewire **ya no dispara** y `@livewireScripts` es la **FUENTE ÚNICA**.
     * Retirarla deja la web sin Alpine, y con Alpine se caen `$store.purchase` —lo que ABRE el cajón
     * desde los once puntos de la landing— y el cajón entero.
     *
     * Tabla medida el 2026-08-21, cuando aún quedaba un componente:
     * directiva SÍ + componentes SÍ → verde · directiva NO + componentes SÍ → **verde** ·
     * directiva SÍ + componentes NO → verde · directiva NO + componentes NO → **ROJO**.
     *
     * ▶ **Y RE-MEDIDA el 2026-08-23, ya sin componentes: retirar la directiva pone este caso ROJO.**
     * O sea que **hasta hoy no discriminaba y desde hoy sí**. Es la fila que el propio caso llevaba
     * anunciando desde que se escribió, y por eso asevera el RESULTADO (`livewire.js` en la página) y
     * no la directiva: una guarda escrita sobre la directiva habría estado verde y muerta dos días.
     *
     * ⚠️ **Y una trampa de la MUTACIÓN, medida al comprobarlo**: un `sed` sobre `@livewireScripts`
     * sustituye **también** la mención que hay dentro de un comentario del layout, y si el reemplazo
     * lleva `--}}` cierra ese comentario antes de tiempo y filtra su texto —que nombra
     * `livewire.js`— al HTML. El caso pasa en verde por el motivo equivocado. **Mutar la línea, no
     * la cadena.**
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
        // ⚠️⚠️ **`nav` y `sidecart` entran el 2026-08-23 y viajan SIN sesión a propósito**
        // (`specs/account-context-vue.md` §4.12): son los rótulos del bloque de cuenta, que desde hoy
        // pinta Vue y **cambia de cara sin recargar**. Quien entra en el paso 5 del embudo tiene en
        // memoria este payload de invitado y el bloque pasa a saludarle por su nombre en ese mismo
        // instante: podarlos por sesión le dejaría el saludo, la sub-línea y el aviso **en blanco**,
        // y nada avisaría. Es el mismo motivo por el que ya viajaban `login`, `register` y `forgot`.
        $this->assertSame(
            ['login', 'register', 'forgot', 'nav', 'sidecart', 'account', 'verify'], array_keys($boot['account'] ?? []),
            'el montaje anónimo lleva textos que solo pinta quien ha iniciado sesión'
        );

        // ⚠️ **`register` va PODADO clave a clave desde el 2026-08-26** (Fase 6, `DECISIONES #166`), y
        // antes viajaba entero. Fuera `must_accept`, `already_exists`, `exists_unverified` y
        // `bot_check_failed`: los publica el SERVIDOR dentro del 422 y `register.js` los pinta tal
        // cual, así que el cajón nunca los leía del arranque. Siguen en `lang/`, que es de donde los
        // lee `Api\V1\AuthRegistrationController`. Sin esta lista, el día que alguien añada una clave
        // al grupo viajaría en todas las páginas públicas sin que nada avise.
        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`.
            [
                'cta', 'eyebrow', 'title', 'subtitle', 'name', 'email', 'phone', 'password',
                'password_hint', 'accept_waiver', 'waiver_read', 'accept_privacy', 'accept_terms',
                'marketing', 'submit', 'submitting', 'fix_errors', 'leave_blank',
            ],
            array_keys($boot['account']['register'] ?? []),
            'el subgrupo `register` ha dejado de estar podado a lo que el formulario de alta pinta'
        );

        // ⚠️⚠️ **`account.title` viaja SIN sesión, y es el rótulo de uno de los TRES botones del
        // bloque.** Sin él, quien entra en el paso 5 y vuelve al catálogo vería ese botón **en
        // blanco**: `i18n.js` devuelve cadena vacía cuando falta una clave y nada avisa. Con sesión
        // el subgrupo entero lo sustituye —y lleva esta misma clave—, así que el cajón lee un solo
        // camino haya sesión o no.
        $this->assertSame(
            ['title'], array_keys($boot['account']['account'] ?? []),
            'sin sesión, del subgrupo `account` solo debe viajar el rótulo que el bloque pinta'
        );

        // Y los dos entran PODADOS clave a clave, no enteros.
        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`.
            ['hello', 'login', 'sign_out'], array_keys($boot['account']['nav'] ?? []),
            'el subgrupo `nav` ha dejado de estar podado a los tres rótulos que el bloque pinta'
        );
        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`.
            ['next', 'form_pending_one', 'form_pending_many', 'guest_hello', 'guest_sub', 'no_upcoming', 'upcoming_count'],
            array_keys($boot['account']['sidecart'] ?? []),
            'el subgrupo `sidecart` ha dejado de estar podado. ⚠️ `tag` NO entra: se retiró por muerta '.
            '(`display: none` incondicional, sostenida solo por su propio test)'
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
        // ⚠️ **Re-medido el 2026-08-23 tras los TRES botones**: **3.137 B**, **+35** sobre los 3.102 de
        // abajo — es `account.title`, el rótulo del botón de «Mi cuenta». **El techo NO sube**: los
        // 3.200 puestos entonces lo absorben y quedan 63 B.
        //
        // ⚠️⚠️ **2.688 → 3.200 el 2026-08-23: los RÓTULOS DEL BLOQUE DE CUENTA.** Medido: **3.102 B**,
        // **+508** —`nav` podado a tres y `sidecart` a siete—, con **98 B** de holgura. Es la subida
        // más grande desde que existe este presupuesto, y hay que decir por qué se paga entera en el
        // montaje anónimo: el bloque cambia de cara **sin recargar**, así que sus dos caras tienen que
        // viajar siempre (ver el párrafo de las claves, arriba).
        // ▶ **Y el balance es negativo, que es lo que la hace legible**: con estos rótulos se retira
        // el marcado del bloque Livewire, que pesaba ~2,2 kB de HTML en cada página pública —de los
        // que ~0,7 eran andamiaje `wire:*`—. **Medido sobre la home anónima real, antes y después:
        // 170.753 B → 169.341 B, o sea −1.412 B netos por visita**, en la ruta que `PERF-02` protege.
        // (El neto no es la resta directa: entran también el hueco servido, `urls.home` y la clave
        // `accountContext`.)
        //
        // ⚠️ **3.200 → 2.850 el 2026-08-26, y BAJA: el WAIVER en el cajón podó más de lo que puso**
        // (Fase 6 · tanda 3b, `DECISIONES #166`). El alta gana dos rótulos —`register.accept_waiver`
        // y `waiver_read`, ~115 B—. La poda que pide el presupuesto con sesión (abajo) se paga aquí
        // también, porque `login` y `register` viajan para todo el mundo: fuera `login.no_account` y
        // `register.has_account` (nadie los leía) y fuera del ARRANQUE los cuatro literales del 422
        // que publica el servidor —seis claves, **≈443 B** sumando sus longitudes en español—.
        // Medido por esta guarda: **2.742 B** —`login` 240, `register` 899, `forgot` 439, `nav` 77,
        // `sidecart` 340, `account` 21, `verify` 464 y `auth` 187—. Con 3.200 sobraban 458 B, y *un
        // techo con margen sobrante deja de apretar* (`SidebarBundleBudgetTest`): **2.850 deja 108**.
        // ⚠️ Un `tinker` contra la BD de desarrollo dio 2.505 con todos los grupos un ~10 % más
        // cortos; la cifra que vale es la de aquí, que mide el mismo `data-boot` que asevera.
        $this->assertLessThan(
            2850, $anonBytes,
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

        // ⚠️ `purchases` entra el 2026-08-24 con «Mis pedidos» (`DECISIONES #129`) y va ENTERO, al
        // revés que `orders`: son 8 rótulos y la pantalla los usa todos, así que podarlo clave a
        // clave sería mantenimiento sin ahorro. Que esté en esta lista es lo que impide que crezca
        // en silencio hasta ser el `__('account.orders')` de conveniencia que esta guarda persigue.
        $this->assertSame(['login', 'register', 'forgot', 'nav', 'sidecart', 'account', 'verify', 'orders', 'purchases'], array_keys($boot['account'] ?? []));
        $this->assertSame(
            ['title', 'empty', 'ref', 'show', 'hide', 'reservations', 'pagination'],
            array_keys($boot['account']['purchases'] ?? []),
            'el grupo de «Mis pedidos» ha crecido: si la pantalla no pinta lo nuevo, hay que podarlo'
        );
        $this->assertSame(['title', 'password', 'sessions', 'profile', 'privacy'], array_keys($boot['account']['account'] ?? []));

        // ⚠️ **`privacy` va podado clave a clave, al revés que los tres subgrupos de al lado.**
        $this->assertSame(
            [
                'title', 'intro', 'consents_title', 'no_consents', 'export_btn',
                'delete_title', 'delete_intro', 'delete_password',
                'delete_confirm', 'delete_btn',
                // Fase 6 · waiver (`DECISIONES #166`): el subgrupo entero, 12 rótulos que la tarjeta
                // y el aviso del índice pintan todos. Va antes de `deleting` porque `Arr::only`
                // conserva el orden de `lang/`, y ahí `waiver` se declaró junto a `delete_btn`.
                'waiver', 'deleting',
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
        // ⚠️ `sidecart` ya NO es «solo el contador»: desde el 2026-08-23 lleva los rótulos del bloque
        // de cuenta y viaja **sin sesión también**, así que su contenido se asevera arriba, en el caso
        // del montaje anónimo. Aquí solo se comprueba que con sesión no ha crecido de más.
        $this->assertSame(
            ['next', 'form_pending_one', 'form_pending_many', 'guest_hello', 'guest_sub', 'no_upcoming', 'upcoming_count'],
            array_keys($boot['account']['sidecart'] ?? []),
        );

        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`: aquél conserva
            // el del array de origen. Escribirlo aquí como se escribió el filtro daba un rojo que se
            // lee como «falta una clave» cuando lo único que pasa es que están en otro sitio.
            [
                'event_data_show', 'event_data_hide',
                // ⚠️ `subtitle` sale el 2026-08-24: NINGUNA superficie lo pintaba y viajaba en cada
                // página con sesión. Lo mismo `order_hide` —«Ver pedido» ya no pliega, lleva a «Mis
                // pedidos»— y `history.back`. Son 139 B que se pagaban por costumbre, y podarlos es
                // lo que esta guarda pide ANTES de subir ningún techo (`DECISIONES #129`).
                'title', 'empty', 'pagination',
                'item_finished', 'item_cancelled',
                // ⚠️ El historial y la referencia del pedido entran el 2026-08-23
                // (`specs/mis-reservas-por-reserva.md`). La poda es clave a clave, así que una clave
                // que no esté en `Arr::only` **viaja vacía** y su botón se pinta SIN TEXTO: pasó al
                // escribir la pantalla y solo se vio en el navegador.
                'history', 'order_ref', 'order_show',
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
        // ⚠️⚠️ **5.720 → 6.272 el 2026-08-23**, por los MISMOS rótulos que suben el anónimo: el bloque
        // de cuenta viaja para todo el mundo. Medido: **6.160 B**, **+529**, con **112 B** de holgura.
        //
        // ⚠️ **6.272 → 6.600 el 2026-08-24: «Mis pedidos»** (`DECISIONES #129`). Y primero se PODÓ,
        // que es lo que este mensaje pide: fuera `orders.subtitle`, `orders.order_hide` y
        // `orders.history.back` —**medido: ninguna superficie del repo los lee**, y viajaban en cada
        // página con sesión—. Eso devolvió **205 B**. El grupo nuevo son **298 B** de rótulos que la
        // pantalla sí pinta, así que el neto es **+93**: de 6.382 a **6.475 B**, con **125 B** de
        // holgura. Sin la poda habrían sido 6.680.
        //
        // ⚠️ **6.600 → 6.760 el 2026-08-26: el WAIVER en el cajón** (Fase 6 · tanda 3b, `DECISIONES
        // #166`). Y otra vez primero se PODÓ, en dos capas:
        // · fuera de `lang/` **tres claves que no leía NADIE**, ni el cajón ni la web ni el servidor
        //   —`login.no_account`, `register.has_account` y `profile.email_resend_throttle`—;
        // · y fuera del ARRANQUE, pero no de `lang/`, `register.must_accept`, `already_exists`,
        //   `exists_unverified` y `bot_check_failed`: los publica el SERVIDOR dentro del 422 y
        //   `register.js::registerErrors()` los pinta tal cual, así que el cliente nunca los leía de
        //   aquí y viajaban en TODAS las páginas públicas. `register` pasa de entero a `Arr::only`.
        // Lo nuevo son **+701 B** brutos —`register.accept_waiver` y `waiver_read`, y
        // `privacy.waiver` entero, 12 rótulos que la tarjeta y el aviso del índice pintan todos—; la
        // poda devuelve **508**; el neto es **+193**: de 6.475 a **6.668 B**, con **92 B** de
        // holgura. Sin la poda habrían sido 7.176 y el techo tendría que haber ido a 7.200.
        $this->assertLessThan(
            6760, $bytes,
            "Los textos del montaje con sesión pesan {$bytes} B. Poda antes de subir el techo: el ".
            'grupo `account` entero son 9,6 kB, y la diferencia la paga cada página que el cliente abre.'
        );
    }

    /**
     * ⚠️⚠️ **RESCATADO de `Site\AccountContextTest` al retirarlo el 2026-08-23**
     * (`specs/account-context-vue.md` §4.11).
     *
     * Aquel fichero murió con su sujeto —el componente Livewire del bloque de cuenta—, pero uno de
     * sus cuatro casos era de sujeto MIXTO: además del tag muerto, era **el ÚNICO sitio de la suite
     * que aseveraba el puente de la clase de modo del panel**, que vive en `layout.blade.php` y
     * **sobrevive intacto**. Dejarlo morir habría quitado esa guarda sin que nada se pusiera rojo.
     *
     * ▶ Es exactamente lo que ya pasó al borrar el motor Livewire (`DECISIONES #111`), y por eso
     * `CONVENCIONES §3.quater` manda clasificar por SUJETO y no por fichero.
     *
     * ⚠️ Lo que ese puente sostiene: `is-{modo}` es lo que COLAPSA el bloque de cuenta en tres modos
     * —y desde hoy el bloque lo pinta Vue, así que depende de una clase que escribe Alpine sobre un
     * elemento del layout. Sin el puente, el panel se queda en `is-catalog` para siempre y el bloque
     * no se colapsa nunca (`DECISIONES #118`).
     */
    public function test_the_panel_still_bridges_its_mode_class_to_the_store(): void
    {
        $this->get(route('entradas'))
            ->assertOk()
            ->assertSee("'is-' + \$store.purchase.mode", false);
    }

    /**
     * ⚠️⚠️ **EL MODO DEL PANEL TIENE UN SOLO ESCRITOR: EL MOTOR** (2026-08-23).
     *
     * `is-{modo}` es lo que colapsa el bloque de cuenta en tres pantallas, y lo publica el `watch` de
     * `Sidebar.vue` a partir de la sección activa y el paso del embudo. **Hasta hoy `close()` lo
     * pisaba**: escribía `mode = 'catalog'` directamente en el store de Alpine, así que al reabrir el
     * panel decía «catálogo» mientras el cajón seguía en «mi cuenta» o en el paso de la fecha — y **el
     * bloque de cuenta reaparecía** en pantallas donde el CSS lo colapsa.
     *
     * ▶ **Y el `watch` no lo corregía**, que es lo que lo hacía silencioso: no había cambiado nada
     * REACTIVO, así que no se volvía a disparar. El estado de Vue y el de Alpine quedaban divergidos
     * hasta el siguiente cambio de paso.
     *
     * ▶ Era herencia del motor Livewire, donde reabrir provocaba un round-trip y el `x-effect`
     * re-publicaba el modo. Es exactamente el argumento que el propio `close()` lleva escrito desde
     * 4.0a para `identifying` — solo que a `mode` no se le aplicó.
     *
     * ⚠️ Se asevera sobre el FUENTE porque el síntoma solo se ve en un navegador: cerrar, reabrir y
     * mirar si el bloque está donde debía estar colapsado.
     */
    public function test_only_the_engine_publishes_the_panel_mode(): void
    {
        // ⚠️⚠️ **Se quitan los COMENTARIOS antes de contar, y no es pulcritud.** El propio `close()`
        // explica en prosa qué línea se retiró y la cita literalmente; contarla como código haría que
        // esta guarda fallara por documentar su propio arreglo. Es la misma lección que
        // `SidebarEntryTest` dejó escrita —allí tres docblocks citaban las claves de sesión a
        // propósito— y la que costó una mutación en falso al medir `@livewireScripts`: **mirar el
        // código, no el texto**.
        $alpine = (string) preg_replace(
            ['#/\\*.*?\\*/#s', '#^\\s*//.*$#m'],
            '',
            (string) file_get_contents(base_path('resources/js/app.js')),
        );

        $this->assertSame(
            1, preg_match_all('/this\.mode\s*=/', $alpine),
            "Alguien ha vuelto a escribir el modo del panel fuera de `setMode()`.\n".
            "⚠️ El modo lo publica el MOTOR (`Sidebar.vue`), y un segundo escritor lo desincroniza sin \n".
            "que nada falle: al reabrir el cajón, el panel dice «catálogo» y el bloque de cuenta \n".
            'reaparece en pantallas donde el CSS lo colapsa. La única asignación legítima es la de '.
            '`setMode()`, que es por donde entra el motor.'
        );

        $this->assertMatchesRegularExpression(
            '/close\(\)\s*\{(?:(?!\}\s*,).)*?scrollLock\.unlock/s',
            $alpine,
            'no se encuentra el `close()` del cajón: esta guarda mira el fichero equivocado'
        );

        $close = mb_substr($alpine, (int) mb_strpos($alpine, 'close() {'));
        $close = mb_substr($close, 0, (int) mb_strpos($close, 'celebrate()'));

        $this->assertStringNotContainsString(
            "this.mode = 'catalog'", $close,
            'CERRAR el cajón ha vuelto a resetear el modo del panel. Es lo que hacía que el bloque de '.
            'cuenta reapareciera al reabrir — y el propio `close()` explica, para `identifying`, por '.
            'qué eso no se hace aquí.'
        );
    }

    // ── La SEMILLA del contexto de cuenta ─────────────────────────────────────────────────────
    //
    // ⚠️⚠️ **Es la ÚNICA rama del montaje cuyo tamaño depende de los DATOS del cliente**, no de los
    // textos de la instalación. Todo lo demás son rótulos: si crecen, crecen para todos y se ven en
    // el diff. Esto crece con los años de cliente, y por eso necesita techo propio — los dos que ya
    // existen miden `[$boot['account'], $boot['auth']]` y **no lo verían nunca**.

    /**
     * Sin sesión no viaja el contexto, pero **la clave sí**, con `null`.
     *
     * Cuesta 24 B medidos en la ruta de más tráfico del sitio, y a cambio ningún consumidor tiene que
     * distinguir «no está» de «está vacío» — el mismo argumento que `BookingStatusResource` escribe
     * para su aviso, y el mismo patrón que `userId` y `outcome`.
     */
    public function test_an_anonymous_mount_carries_the_key_with_null(): void
    {
        $boot = $this->bootPayload();

        $this->assertArrayHasKey('accountContext', $boot, 'la clave tiene que estar siempre');
        $this->assertNull($boot['accountContext']);
    }

    /**
     * ⚠️⚠️ **LA SEMILLA Y EL ENDPOINT SON LA MISMA FORMA**, y esto lo compara sobre respuestas reales.
     *
     * Los dos salen de `AccountContextResource` a propósito (`Http\Sidebar\AccountContextSeed`).
     * Escribir la semilla «a mano» en el Blade es una tentación de cinco líneas, y dejaría al store
     * del cajón normalizando **dos formas** del mismo dato: una al cargar la página y otra al
     * refrescar tras entrar sin recargar. Una de las dos envejecería sin que nadie lo notara.
     *
     * ⚠️ Se compara con **un solo** formulario pendiente a propósito: es el único caso en que las dos
     * formas deben coincidir ENTERAS. La diferencia con varios es de cardinalidad y la fija el caso
     * de abajo.
     */
    public function test_the_seed_is_the_same_shape_as_the_endpoint(): void
    {
        $user = $this->customerWithPendingPacks(1);

        $semilla = $this->bootPayload($this->actingAs($user)->get('/')->getContent())['accountContext'];
        $endpoint = $this->actingAs($user)->getJson('/api/v1/me/account-context')->assertOk()->json();

        $this->assertSame(
            $endpoint, $semilla,
            "La semilla del montaje ha dejado de ser lo que publica `GET /me/account-context`.\n".
            '⚠️ Las dos tienen que salir del MISMO Resource, o el store del cajón acaba normalizando '.
            'dos formas del mismo dato y una de ellas envejece sola.'
        );
    }

    /**
     * ⚠️⚠️ **La lista de formularios pendientes viaja PODADA a uno, y el contador NO.**
     *
     * Es la única parte del contexto **sin cota**: un cliente con ocho packs pendientes arrastraría
     * ocho nombres de producto y ocho URLs de post-form —PII— en el HTML de **cada** página que
     * abriera. El bloque solo pinta uno: con varios, su aviso lleva al listado y usa el CONTADOR.
     *
     * ▶ Y se poda **por cardinalidad, nunca por campo**: de `next_reservation` no se quita nada
     * aunque el bloque no pinte `time_window`, porque eso sí daría dos formas del mismo objeto.
     */
    public function test_the_seed_prunes_the_pending_forms_by_cardinality_and_keeps_the_count(): void
    {
        $user = $this->customerWithPendingPacks(3);

        $seed = $this->bootPayload($this->actingAs($user)->get('/')->getContent())['accountContext'];

        $this->assertCount(
            1, $seed['pending_forms'],
            'la semilla lleva más de un formulario pendiente: son nombre + URL por cada uno, en el '.
            'HTML de cada página'
        );
        $this->assertSame(3, $seed['pending_forms_count'], 'el CONTADOR no se poda: es lo que pinta el aviso');

        // Y `next_reservation` va ENTERA: no se poda por campo.
        $this->assertSame(
            ['date', 'date_label', 'time_window', 'product_name'],
            array_keys($seed['next_reservation']),
            'se ha podado un campo de `next_reservation`: eso da dos formas del mismo objeto según '.
            'venga de la semilla o del refresco'
        );
    }

    /**
     * ⚠️⚠️ **EL TECHO DE LA SEMILLA — y lo que este techo NO puede cazar, dicho aquí.**
     *
     * Es la única rama del montaje cuyo tamaño depende de los DATOS del cliente y no de los textos de
     * la instalación: varía con la longitud de los nombres de producto y con el dominio. Eso lo hace
     * un instrumento **débil** para lo estructural —un campo nuevo cabe de sobra dentro de la holgura
     * que la variación de datos obliga a dejar— y **fuerte** para lo que de verdad importa aquí: que
     * no vuelva a entrar nada SIN COTA.
     *
     * ▶ Por eso van los dos, y cada uno mide lo suyo:
     *  · **el juego de claves**, que es exacto y caza un campo nuevo el día que se añade;
     *  · **el techo en bytes**, que caza que algo crezca con el cliente — una lista que se dejó de
     *    podar, un campo que resultó ser un array.
     *
     * Medido el 2026-08-23 con este fixture (próxima reserva + 3 packs pendientes, podados a 1):
     * **320 B**. El techo deja holgura deliberada para un dominio de instalación largo y nombres de
     * producto largos, que son las dos partes que varían de cliente a cliente y que **no deben
     * producir un rojo que no viene de un cambio** — el fallo que esta suite ya pagó dos veces por
     * otra vía (`DECISIONES #64`, `#97`).
     *
     * ▶ **Referencia que lo hace legible**: el bloque Blade que esta migración retira pesaba **~2,2 kB
     * de HTML** en cada página pública. La semilla es un séptimo de eso, y solo con sesión.
     */
    public function test_the_seed_stays_within_its_budget(): void
    {
        $user = $this->customerWithPendingPacks(3);

        $seed = $this->bootPayload($this->actingAs($user)->get('/')->getContent())['accountContext'];

        // (1) Lo ESTRUCTURAL, que es lo que discrimina de verdad.
        // `waiver` entró a propósito el 2026-08-26 (`DECISIONES #163`, spec del waiver §4.8): cuatro
        // campos cortos, y el endpoint lo publica igual (`MeAccountContextTest`, contrato).
        $this->assertSame(
            ['first_name', 'upcoming_count', 'next_reservation', 'pending_forms', 'pending_forms_count', 'waiver'],
            array_keys($seed),
            'La semilla ha cambiado de forma. Cada campo nuevo viaja en el HTML de TODA página con '.
            'sesión: si hace falta, que entre a propósito — y comprueba antes que el endpoint lo '.
            'publica igual, o habrá dos formas del mismo dato.'
        );

        // (2) Y el techo, como red contra lo que crece con el cliente.
        $bytes = strlen((string) json_encode($seed, JSON_UNESCAPED_UNICODE));

        $this->assertLessThan(
            512, $bytes,
            "La semilla del contexto de cuenta pesa {$bytes} B en el HTML de cada página con sesión, ".
            "sobre 320 medidos.\n".
            '⚠️ Mira primero si lo que ha crecido es una LISTA: la de formularios pendientes se poda '.
            'por cardinalidad justamente porque no tiene cota. Subir el techo sin mirar eso es '.
            'subirlo para que el histórico del cliente quepa.'
        );
    }

    /**
     * Un cliente con `n` packs pendientes y su próxima reserva.
     *
     * ⚠️ **UNA sola lectura del reloj** para el fixture: con dos, el cruce de medianoche entre ellas
     * daría un rojo que no viene del código (`DECISIONES #64`, `#97`).
     */
    private function customerWithPendingPacks(int $n): User
    {
        $user = User::factory()->create(['name' => 'Cliente Demo', 'email_verified_at' => now()]);

        $zone = Zone::create([
            'slug' => 'jump', 'name' => ['es' => 'Jump'], 'accent' => 'jump',
            'color' => '#FF5B22', 'position' => 1, 'is_active' => true,
        ]);

        $date = now()->addDays(3)->toDateString();

        for ($i = 1; $i <= $n; $i++) {
            $type = TicketType::create([
                'name' => ['es' => "Cumpleaños Jump {$i}"], 'type' => TicketType::TYPE_PACK,
                'zone_id' => $zone->id, 'duration_min' => 120, 'seats_per_unit' => 1,
                'is_sellable' => true, 'is_active' => true,
                'guest_fields' => [
                    ['key' => 'nombre', 'label' => ['es' => 'Nombre'], 'type' => 'text', 'phase' => 'booking', 'required' => true],
                ],
                'position' => (int) TicketType::max('position') + 1,
            ]);

            $order = Order::create([
                'user_id' => $user->id, 'code' => 'R-'.Str::upper(Str::random(6)),
                'status' => Order::STATUS_PAID, 'subtotal' => 1000, 'tax' => 0, 'total' => 1000,
                'currency' => 'EUR', 'paid_at' => now(),
            ]);

            // ⚠️ Una franja por pack, con hora distinta: `slots` es único por (zona, fecha, inicio).
            $slot = Slot::create([
                'zone_id' => $zone->id, 'date' => $date,
                'start_time' => sprintf('%02d:00:00', 9 + $i), 'end_time' => '23:00:00',
                'capacity' => 10, 'online_capacity' => 10,
            ]);

            $order->items()->create([
                'ticket_type_id' => $type->id, 'slot_id' => $slot->id, 'parent_item_id' => null,
                'quantity' => 2, 'seats' => 2, 'unit_price' => 1000,
            ]);
        }

        return $user;
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
