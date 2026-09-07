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
     * ⚠️⚠️ **Y el motor tiene que LEER ese dato antes de restaurar la cesta.** Medido en headless el
     * 2026-08-27 (`specs/menores-a-cargo.md` §9.9.6): `userId` viajaba, la raíz lo declaraba y nadie lo
     * sembraba en el store; el cajón que nace abierto (`/entradas`, `/mi-cuenta`…) restauraba con el
     * dueño a `null` y PURGABA la cesta del propio titular (2/2), mientras el que abre desde la home la
     * conservaba (4/4) porque `open()` pregunta `GET /me`. El caso de arriba pasaba igual: que el dato
     * viaje no prueba que alguien lo lea.
     *
     * Es una guarda ESTRUCTURAL sobre el entry, porque la suite no arranca el motor y el diff de árbol
     * monta los pasos por su cuenta (`TESTING.md` §2.sexies): la siembra existe y va ANTES de montar,
     * que es cuando la sección restaura en su `onMounted`.
     */
    public function test_the_engine_seeds_the_cart_owner_from_the_boot_before_mounting(): void
    {
        $entry = (string) file_get_contents(resource_path('js/sidebar/index.js'));

        // ⚠️ Sin los comentarios: la primera versión de esta guarda casó `app.mount(el)` con una
        // MENCIÓN en un comentario que va antes que la llamada, y salió roja con el fuente correcto.
        // Un `grep` que SÍ encuentra tampoco demuestra que exista (`specs/armazon-y-menu.md` §1.2).
        $code = (string) preg_replace(['~/\*.*?\*/~s', '~^\s*//.*$~m'], '', $entry);

        $seed = strpos($code, 'useCartStore(pinia).setOwner(boot.userId');
        $mount = strpos($code, 'app.mount(el)');

        $this->assertNotFalse($seed, 'el entry siembra el dueño de la cesta desde `boot.userId`');
        $this->assertNotFalse($mount, 'el entry monta la app con `app.mount(el)`');
        $this->assertLessThan($mount, $seed, 'la siembra va ANTES de montar: `restoreCart()` corre en el `onMounted` de la sección');
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
            'password_hint', 'privacy_notice', 'submit', 'submitting', 'leave_blank', 'fix_errors',
            // El botón de Google y el «o» que lo separa del formulario (T8·d): sin el segundo, la raya
            // se pinta con un hueco en medio y nadie avisa — `i18n.js` devuelve cadena vacía.
            'google_cta', 'or',
        ] as $key) {
            $this->assertNotSame(
                '', (string) ($register[$key] ?? ''),
                "El montaje no lleva `account.register.{$key}`, así que ese rótulo se pintaría VACÍO: ".
                '`i18n.js` devuelve cadena vacía cuando falta una clave, y nada avisa.'
            );
        }
    }

    /**
     * ⚠️ **El aviso de privacidad viaja con su `<a href>` DENTRO y ya interpolado.**
     *
     * El Blade lo pinta con `{!! !!}` y la URL la compone `route()`. Si viajara sin interpolar, el
     * cajón enseñaría un `:url` literal en medio de un texto legal; y partirlo en «texto + enlace»
     * obligaría al cliente a recomponer una frase traducida que no ordena igual en cada idioma.
     *
     * ⚠️ **Era una lista de DOS hasta la T8·c** (`#350`): `accept_terms` se fue con su casilla, y su
     * enlace vive ahora en el paso de pagar (`tickets.due_terms` y `tickets.terms_link`), que es donde
     * la ley pide que se pueda leer.
     */
    public function test_the_mount_payload_carries_the_legal_texts_with_their_links(): void
    {
        foreach (SetLocale::SUPPORTED as $locale) {
            $this->app->setLocale($locale);

            $register = $this->bootPayload()['account']['register'] ?? [];

            foreach (['privacy_notice' => 'legal.privacidad'] as $key => $route) {
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
                'password_hint', 'accept_waiver', 'waiver_read', 'privacy_notice',
                'submit', 'submitting', 'fix_errors', 'leave_blank',
                // ⚠️ **`google_cta` viaja SIEMPRE y su PANTALLA no** (`#343`): el rótulo lo pintan las
                // dos pestañas de auth, que las ve quien no tiene sesión, así que no hay condición
                // bajo la que esconderlo. El subgrupo `google` —los ~380 B de la pantalla que completa
                // el alta— viaja **solo en su puerta**, y eso lo fija el caso de abajo.
                'google_cta',
                // Y el «o» que lo separa del formulario (T8·d, `#350`): viaja pegado al botón porque
                // solo se pinta con él.
                'or',
            ],
            array_keys($boot['account']['register'] ?? []),
            'el subgrupo `register` ha dejado de estar podado a lo que el formulario de alta pinta'
        );

        // ⚠️⚠️ **La pantalla de Google NO viaja en una página cualquiera.** Es la primera poda por RUTA
        // de este montaje, y la razón es que a esa zona **no se llega de ninguna otra forma**: hay que
        // volver de Google, y el retorno aterriza en `/registro/google`. Sin esta guarda, sus textos
        // acabarían en cada página pública para no pintarse nunca — que es exactamente lo que el
        // presupuesto de abajo existe para cazar.
        $this->assertArrayNotHasKey('google', $boot['account'] ?? []);

        // Y su CONTROL, sin el que lo de arriba se cumpliría también si el subgrupo no existiera en
        // ninguna parte: en SU puerta sí viaja, y con lo que la pantalla pinta.
        $atDoor = $this->bootPayload((string) $this->get('/registro/google')->assertOk()->getContent());

        $this->assertArrayHasKey('google', $atDoor['account'] ?? [], 'la pantalla de Google no recibe sus textos en su propia puerta');
        $this->assertArrayHasKey('title', $atDoor['account']['google'] ?? []);
        $this->assertArrayHasKey('expired', $atDoor['account']['google'] ?? []);

        // ⚠️⚠️ **`account.title` viaja SIN sesión, y es el rótulo de uno de los botones del
        // bloque.** Sin él, quien entra en el paso 5 y vuelve al catálogo vería ese botón **en
        // blanco**: `i18n.js` devuelve cadena vacía cuando falta una clave y nada avisa. Con sesión
        // el subgrupo entero lo sustituye —y lleva esta misma clave—, así que el cajón lee un solo
        // camino haya sesión o no.
        //
        // ⚠️⚠️ **Y `account.card.title` con él desde el 2026-08-28** (revisión de `#217`): el bloque
        // ganó un cuarto botón, el atajo «Mi QR», y su rótulo se quedó SOLO en el subgrupo con sesión.
        // Resultado medido en navegador: quien se identifica en el paso 5 —que es el camino normal de
        // una primera compra— se encontraba el botón **mudo y sin nombre accesible**. La regla que
        // este caso fija es la de siempre, aplicada a un botón nuevo: **lo que el bloque puede pintar
        // sin recargar tiene que viajar siempre**. Lo que NO viaja es el resto del subgrupo (los 11
        // rótulos de la ZONA del QR): esa pantalla solo existe con sesión.
        $this->assertSame(
            ['title', 'card'], array_keys($boot['account']['account'] ?? []),
            'sin sesión, del subgrupo `account` solo deben viajar los rótulos que el bloque pinta'
        );

        $this->assertSame(
            ['title'], array_keys($boot['account']['account']['card'] ?? []),
            'de `card` solo viaja el RÓTULO del botón: la zona del QR es de quien tiene sesión'
        );

        // Y los dos entran PODADOS clave a clave, no enteros.
        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`.
            ['hello', 'login', 'sign_out'], array_keys($boot['account']['nav'] ?? []),
            'el subgrupo `nav` ha dejado de estar podado a los tres rótulos que el bloque pinta'
        );
        $this->assertSame(
            // ⚠️ El ORDEN lo fija `lang/*/account.php`, no la lista de `Arr::only`.
            ['form_pending_one', 'form_pending_many', 'guest_hello', 'guest_sub', 'upcoming_count'],
            array_keys($boot['account']['sidecart'] ?? []),
            'el subgrupo `sidecart` ha dejado de estar podado. ⚠️ `tag` NO entra: se retiró por muerta '.
            '(`display: none` incondicional, sostenida solo por su propio test)'
        );

        // ⚠️ **Y `verify` va podado clave a clave**, al revés que `login`, `register` y `forgot`: sus
        // 14 rótulos incluyen los de la PÁGINA de verificación de la web, que el cajón no pinta.
        // ▶ **`pending_notice` entró a propósito el 2026-09-01** (`#331`): es el aviso del ÍNDICE DE
        // LA CUENTA para quien entró sin verificar, que desde esa tanda es todo el mundo que acaba de
        // registrarse. Va en `verify` y no en `privacy.waiver` porque el aviso es del CORREO — la
        // exención solo cambia la frase cuando además hay una esperando.
        $this->assertSame(
            [
                'eyebrow', 'title', 'sent_to', 'spam_hint', 'resend', 'pending_notice',
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
        //
        // ⚠️ **2.850 → 2.750 el 2026-08-28, y BAJA porque el PULIDO del cajón podó sin poner nada**
        // (`specs/identidad-qr-puerta.md` §9.7 C·2, `DECISIONES #217`). La sub-línea de la próxima
        // reserva sale del bloque de cuenta —la enseña el índice del área, que es donde el cliente
        // entra a mirarla— y con ella se van `sidecart.next` y `sidecart.no_upcoming`: **−83 B**, de
        // 2.742 a **2.659**. Este grupo NO se poda por sesión —el bloque cambia de cara sin
        // recargar—, así que esos dos rótulos viajaban en TODAS las páginas públicas para no pintarse
        // nunca. Lo que ocupa su hueco («Mi QR») **no cuesta nada aquí**: se rotula con
        // `account.card.title`, que viaja solo con sesión. Con 2.850 sobraban 191 B y *un techo con
        // margen sobrante deja de apretar*: **2.750 deja 91**, la holgura de siempre.
        // ⚠️ **2.750 → 2.800 el 2026-09-02: el botón de Google** (`#343`). Medido: **2.769 B**, **+44**
        // — que es exactamente `register.google_cta` («Continuar con Google»)—. Se sube el techo a
        // propósito y **después de buscar qué podar**: los tres subgrupos que viajan sin sesión ya
        // están podados clave a clave y no queda ninguno que la pantalla no pinte, así que la única
        // alternativa era no ofrecer el botón en las pantallas de auth, que es donde tiene sentido.
        // ▶ **Y lo que NO sube es la pantalla**: los ~380 B del subgrupo `google` viajan **solo en su
        // puerta** (`/registro/google`), porque a esa zona no se llega sin volver de Google. El caso
        // de arriba lo fija con su control. Quedan 31 B de holgura.
        $this->assertLessThan(
            2800, $anonBytes,
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
        // ⚠️ `guest_minors` entra con la T3 del justificante (`#337`) y **la pantalla lo pinta**:
        // `GuestMinorsPanel.vue` usa sus cinco rótulos —el contador, la capacidad, los dos estados de
        // excepción y la frase del enlace—. Esta guarda es justo la que obliga a comprobarlo: crecer
        // aquí sin pintar sería pagar bytes en cada página con sesión para nada.
        $this->assertSame(
            ['guest_minors', 'title', 'empty', 'ref', 'show', 'hide', 'reservations', 'pagination'],
            array_keys($boot['account']['purchases'] ?? []),
            'el grupo de «Mis pedidos» ha crecido: si la pantalla no pinta lo nuevo, hay que podarlo'
        );
        $this->assertSame(['title', 'no_password', 'password', 'sessions', 'profile', 'privacy', 'dependents', 'card'], array_keys($boot['account']['account'] ?? []));

        // ⚠️ **Menores a cargo** (Fase 6 · C, `DECISIONES #199`) va ENTERO: 17 rótulos que la zona y
        // sus tarjetas pintan todos. La lista exacta es lo que impide que crezca en silencio — y lo
        // que NO está aquí es tan deliberado como lo que está: la casilla, «leer el texto», «Firmar»,
        // «Firmando…», «Firma registrada» y «PDF» se REUTILIZAN de `register.*` y `privacy.waiver.*`.
        $this->assertSame(
            [
                'title', 'intro', 'empty', 'add_title', 'name', 'name_hint',
                // `#236` (`[DECIDIDO owner]`): APELLIDOS y RELACIÓN con el titular en el alta. El
                // desplegable de relación son cinco opciones traducidas más su rótulo, su «elige» y
                // su ayuda — ocho claves, y son las que hacen que el chunk suba de 252 a 253 KiB.
                // Se pagan a propósito: la lista cerrada es lo que impide que «madre» acabe escrito
                // de veinte formas, y la relación es lo que sostiene que este adulto pueda firmar la
                // exención en nombre del menor.
                'surname', 'relationship', 'relationship_choose', 'relationship_hint',
                'relationship_father', 'relationship_mother', 'relationship_legal_guardian',
                'relationship_grandparent', 'relationship_other',
                'born_on', 'add', 'adding',
                // El alta se DESPLIEGA desde un botón (2026-08-28): el disparador se rotula con
                // `add_title` —el mismo texto que titula lo que abre— y `add_cancel` lo pliega.
                'add_cancel',
                'age', 'adult', 'remove', 'removing', 'remove_confirm',
                'waiver_unsigned',
                // `#441` · el estado que faltaba, y va PEGADO a «sin firmar» porque es su matiz:
                // falta su firma **y esta cuenta todavía no puede darla**. Hasta hoy la tarjeta
                // ofrecía casilla y botón a quien solo podía recibir un 409
                // (`waiver_email_unverified`) — el defecto que `#329` cerró para el TITULAR y que
                // seguía vivo aquí. ⚠️ Es **un rótulo y no un bloque**: el de reenviar el correo, con
                // su cuenta atrás y su límite, vive en el índice de la cuenta y NO se duplica
                // (`#331`, «para no saturar»: son dos hechos y una sola acción del cliente).
                'waiver_awaiting_verification',
                'waiver_current', 'waiver_outdated',
                // Fase 6 · tanda 4 (`DECISIONES #202`): lo que la tarjeta de «Mis reservas» pinta de
                // la asignación —el «Para:» y el despliegue—. ⚠️ Los rótulos del EMBUDO (el selector
                // de los pasos 3 y 4, el aviso de la puerta 2, el «Para:» del resumen) NO van aquí:
                // viven en `tickets.dependents`, porque este subgrupo viaja SOLO con sesión y quien
                // entra anónimo y se identifica en el paso 5 los necesita sin recargar. Se pusieron
                // aquí primero y el guion headless (§5.undecies) los encontró en blanco.
                'for_label', 'assigned_none', 'assigned_show', 'assigned_hide',
                // La lista se pagina en CLIENTE y el paginador solo se pinta con más de una página
                // (2026-08-28). Los cuatro rótulos son los de `orders.pagination`, con su propio
                // `label`: «Paginación de reservas» sobre una lista de menores sería un texto falso.
                'pagination',
            ],
            array_keys($boot['account']['account']['dependents'] ?? []),
            'el subgrupo `dependents` ha crecido: si la zona no pinta lo nuevo, hay que podarlo'
        );

        // ⚠️ **`privacy` va podado clave a clave, al revés que los tres subgrupos de al lado.**
        $this->assertSame(
            [
                'title', 'intro', 'consents_title', 'no_consents', 'export_btn',
                // El interruptor del art. 7.3 y la palabra que marca una fila RETIRADA (`#344`). Van
                // aquí —con sesión— porque nadie los pinta sin haber entrado, y en este orden porque
                // `Arr::only` conserva el de `lang/`.
                'consent_revoked', 'marketing_label', 'marketing_hint',
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
            ['form_pending_one', 'form_pending_many', 'guest_hello', 'guest_sub', 'upcoming_count'],
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
        //
        // ⚠️ **6.760 → 7.700 el 2026-08-27 por la noche: «MENORES A CARGO» en el cajón** (Fase 6 · C,
        // tanda 3, `DECISIONES #199`). Y primero se REUTILIZÓ, que es la poda que cabía: la casilla y
        // «leer el texto» son los de `register.*`, y «Firmar», «Firmando…», «Firma registrada» y «PDF»
        // los de `privacy.waiver.*` — seis rótulos que ya viajaban y no se redactan por segunda vez.
        // Lo nuevo son los **17** de `account.dependents`, todos pintados por la zona o sus tarjetas,
        // **+934 B** brutos: de 6.668 a **7.602 B**. El más largo es `intro` (~190 B) y se queda a
        // propósito: es la frase de la spec (§4.2) que le dice al titular que puede poner el nombre
        // que use en casa y que en la puerta nunca se ve. **7.700 deja 98 B**, la holgura de siempre.
        //
        // ⚠️ **7.700 → 7.800 el 2026-08-27 por la noche: la ASIGNACIÓN de entradas a menores en el
        // embudo** (Fase 6 · C, tanda 4 · U2, `DECISIONES #202`; subida por FEATURE, `#197`·2). Aquí
        // entran solo los CUATRO rótulos de la tarjeta de «Mis reservas» (el «Para:» y el despliegue),
        // **+145 B**: de 7.602 a **7.747 B**. ⚠️ Primero entraron ONCE (8.260 B, y este techo se puso
        // en 8.300): el selector, el aviso y el «Para:» del resumen viajaban también aquí, y el guion
        // headless (§5.undecies) los encontró EN BLANCO en quien entra anónimo y se identifica en el
        // paso 5 — este subgrupo viaja solo con sesión. Se mudaron a `tickets.dependents`, que va
        // siempre, y este techo BAJÓ a lo medido. **7.800 deja 53 B**: la holgura de siempre.
        //
        // ⚠️ **7.800 → 8.550 el 2026-08-28 por la mañana: «MI CARNÉ» en el cajón** (Fase 6 · A,
        // `specs/identidad-qr-puerta.md` §9.6 B·2/B·4, `DECISIONES #212`; subida por FEATURE, `#197`·2).
        // Son los **12** rótulos de `account.card`, todos pintados por la zona —el título del índice, la
        // intro, el `alt` de la imagen, «dicta este código», descargar, la nota, el carné que no se puede
        // dibujar, renovar y su «renovando…», la confirmación explícita, «renovado» y la sesión
        // caducada—, **+725 B**: de 7.747 a **8.472 B**. No hay nada que reutilizar de otros grupos: el
        // único texto parecido, el de sesión caducada, no viajaba con sesión. Los dos más largos son la
        // intro (~120 B: qué es, dónde se enseña y que **no sirve para entrar**, §4.2) y la confirmación
        // de renovar (~110 B: «el del correo y cualquier copia impresa», §4.5), y los dos se quedan:
        // son las dos frases que evitan un malentendido caro en la puerta. **8.550 deja 78 B**: la
        // holgura estrecha de siempre, a propósito.
        //
        // ⚠️ **El techo NO se mueve el 2026-08-28 con el PULIDO del cajón** (§9.7 C·2/C·4,
        // `DECISIONES #217`), y merece su párrafo porque el saldo es de tres movimientos:
        // · **−83 B** de poda: `sidecart.next` y `sidecart.no_upcoming` se retiran con la sub-línea
        //   del bloque de cuenta (el índice del área ya la enseña);
        // · **−118 B**: `card.rotate_confirm`, el texto del `window.confirm` que se retira;
        // · **+236 B**: los CUATRO rótulos de la confirmación dentro del cajón —el aviso permanente
        //   bajo el botón, la pregunta y sus dos botones—, que la zona pinta todos.
        // Neto **+35**: de 8.472 a **8.507 B**. La poda paga dos tercios de la feature y **8.550
        // sigue valiendo, ahora con 43 B**. El atajo «Mi QR» no añade rótulo: reutiliza
        // `account.card.title`, que es además la regla de «el botón se llama como su pantalla».
        //
        // ⚠️⚠️ **8.550 → 8.700 el 2026-08-28 por la tarde: las DOS pantallas que el owner mandó
        // rehacer** —la presentación del QR y «Menores a cargo»— subida por FEATURE (`#197`·2).
        // Medido: **8.507 → 8.615 B**, y el neto sale de tres movimientos:
        // · **−39 B de PODA, y es la que paga el encargo de la PALABRA**: «QR» sustituye a «carné» en
        //   los nueve rótulos de `account.card` que lo nombraban (`[DECIDIDO owner]`, §9.7 C·6) y el
        //   grupo ADELGAZA —«Mi QR» contra «Mi carné», «Tu QR» contra «Tu carné QR»—: de 835 a 796 B.
        //   Es la primera vez que un cambio de vocabulario devuelve payload en vez de costarlo.
        // · **+23 B**: `dependents.add_cancel`, el «Cancelar» que pliega el alta. Es el ÚNICO rótulo
        //   nuevo del desplegable: el disparador reutiliza `add_title`, que ya viajaba, porque el
        //   botón y la sección que abre tienen que llamarse igual.
        // · **+122 B**: `dependents.pagination`, los cuatro rótulos del paginador. No se reutilizan
        //   los de `orders.pagination` —«Anteriores», «Siguientes» y «Página :current de :last» sí
        //   valdrían, pero su `label` dice «Paginación de reservas»— y un lector de pantalla anunciaría
        //   una lista de reservas sobre una de menores. El grupo propio son 122 B por no mentir.
        // ▶ Y **el paginador no se pinta si todo cabe en una página**, así que esos 122 B viajan para
        // una barra que la cuenta normal —dos o tres menores— no llega a ver. Se pagan igual: el
        // arranque no sabe cuántos menores tiene quien abre la página.
        // **8.700 deja 85 B**: la holgura estrecha de siempre, a propósito.
        //
        // ⚠️ **8.700 → 9.100 el 2026-08-28, por FEATURE** (`#236`, `[DECIDIDO owner]`). Medido:
        // **8.615 → 9.017 B** (+402). Son las nueve claves del alta de un menor: `surname`, y el
        // desplegable de RELACIÓN entero —su rótulo, su «elige una opción», su ayuda y las cinco
        // opciones traducidas—.
        // ▶ **No hay poda que lo pague**, y se miró: las cinco opciones son la lista cerrada, que es
        // justo lo que impide que «madre» acabe escrito de veinte formas y lo que sostiene que este
        // adulto pueda firmar la exención en nombre del menor. Reutilizar rótulos de otro grupo
        // tampoco vale aquí: no hay ningún «Padre/Madre/Tutor» ya en el payload.
        // ⚠️ Y viajan **en cada apertura de cualquier página con sesión**, aunque el titular no vaya
        // a declarar a nadie — el arranque no sabe si tiene menores. Es el mismo peaje que ya pagan
        // los 122 B del paginador, y por la misma razón.
        // **9.100 deja 83 B**: la holgura estrecha de siempre.
        //
        // ⚠️ **9.100 → 9.200 el 2026-09-01, por FEATURE** (`#329`). Medido: **9.017 → 9.125 B**
        // (+108), y es UNA clave: `privacy.waiver.status_awaiting_verification`, la frase del estado
        // «la aceptaste al registrarte y falta que verifiques tu correo». Antes no existía ese estado
        // en pantalla: se le decía al cliente que no la había firmado, con un botón de firmar que
        // solo podía devolver 409.
        // ▶ **La poda ya está hecha y pagó casi el doble que la clave**: el primer intento traía
        // CUATRO rótulos (9.315 B) y tres se retiraron —dos porque la frase del estado y la del aviso
        // decían lo mismo con otras palabras, y **los dos del botón porque `verify.resend` y
        // `verify.resend_in` YA VIAJABAN** en este mismo montaje para la pantalla del alta—. −190 B.
        // *El rótulo más barato es el que ya está en el payload.*
        // ▶ Y viaja **con cualquier sesión**, aunque el correo esté verificado desde hace un año: el
        // arranque no lo sabe. Mismo peaje que el paginador de menores, y por la misma razón.
        // **9.200 deja 75 B**: la holgura estrecha de siempre.
        //
        // ⚠️ **9.200 → 9.400 el 2026-09-01, por FEATURE** (`#337`, la T3 del justificante de un menor
        // invitado). Medido: **9.125 → 9.365 B (+240)**, y son CUATRO rótulos del panel que el
        // responsable de una reserva usa para ver quién ha firmado ya y repartir el enlace: el
        // contador, los dos estados de excepción y la frase que le dice que el enlace se puede
        // compartir.
        // ▶ **La poda se hizo ANTES y está medida**: el primer intento traía CINCO y pesaba 9.475 B.
        // Se retiró `capacity` —«la reserva es de :count personas»: el contador solo ya es honesto, y
        // el denominador inventado estaba prohibido de todas formas— y se acortaron los otros cuatro.
        // **−110 B.**
        // ⚠️⚠️ **Y hay que decir lo que esto cuesta mal**: estos bytes los paga **cada página que
        // abre cualquier cliente con sesión**, y el panel solo aparece en los poquísimos pedidos que
        // traen menores invitados. *La salida buena, si algún día hay que recuperarlos, es mandar
        // estos cuatro rótulos en la RESPUESTA del endpoint —que ya se pide bajo demanda— en vez de
        // en el montaje; no se hizo ahora porque sacaría estas claves del alcance de
        // `SidebarTranslationKeysExistTest`, que es lo que impide que un rótulo se quede MUDO.*
        // **9.400 deja 35 B**: más estrecho que nunca.
        //
        // ⚠️ **9.400 → 9.650 el 2026-09-02, y lo paga un DERECHO** (`#344`): el interruptor que permite
        // **retirar** el consentimiento de marketing (art. 7.3) y la palabra que marca una fila
        // retirada en la lista de consentimientos. Medido: **9.365 → 9.604 B (+239)**, tres rótulos.
        // ▶ **La poda se hizo ANTES y está medida**: el primer intento traía CUATRO y pesaba 9.651 B.
        // Se retiró `marketing_title` porque **decía exactamente lo mismo que `consent_types.marketing`,
        // que YA VIAJABA** en este mismo montaje para la lista de arriba. **−47 B.** *El rótulo más
        // barato sigue siendo el que ya está en el payload.*
        // ⚠️⚠️ **Y aquí no cabía la salida de «mandarlo en la respuesta del endpoint»**: el interruptor
        // se pinta en la propia zona, no cuelga de una petición bajo demanda. Lo que sí es cierto es
        // que estos bytes los paga cada página con sesión — a cambio de que retirar un consentimiento
        // sea *tan fácil como darlo*, que es literalmente lo que el art. 7.3 exige.
        // **9.650 deja 46 B**: la estrechez de siempre.
        //
        // ⚠️ **9.650 → 9.900 el 2026-09-02, y lo pagan otros dos derechos** (`#344`): el aviso de que
        // quien entró con Google puede crear una contraseña —en las CUATRO pantallas que la exigen
        // (art. 12.2)— y los dos rótulos de **desvincular** una cuenta externa. Medido: **9.604 →
        // 9.826 B (+222)**, tres rótulos.
        // ▶ **La poda, antes**: el botón del aviso reutiliza `forgot.title`, que ya viajaba en TODAS
        // las páginas por ser texto de invitado, y el bloque de cuentas vinculadas **no tiene rótulo
        // de «no hay ninguna»**: sin vínculos no se pinta nada, que además dice más.
        // **9.900 deja 74 B.**
        //
        // ⚠️ **9.400 → 9.550 el 2026-09-02** (`#403`, compartir o copiar el enlace). Medido: **9.365 →
        // 9.499 B (+134)**, y son CUATRO rótulos: el nombre accesible del botón y los TRES desenlaces
        // —compartido, copiado y el fallo—. `shared` y `copied` no se funden en uno a propósito: decir
        // «copiado» cuando el sistema acaba de abrir WhatsApp sería mentir sobre lo que pasó.
        // ▶ **La poda se hizo ANTES y está medida (−18 B)**: se acortaron la pista y el mensaje de
        // fallo. Y hay una poda de signo contrario que ayudó sin buscarlo — los cuatro rótulos del
        // post-form perdieron el nombre del producto (`:product`), que era el defecto que el owner
        // vio desbordando el botón.
        // ⚠️ **La salida buena sigue siendo la escrita arriba**: mandar los rótulos del justificante
        // en la RESPUESTA del endpoint, que ya se pide bajo demanda. Cada tanda que añade uno hace
        // esa deuda más cara. **9.550 deja 51 B.**
        // ⚠️⚠️ **9.900 → 10.000: RE-MEDIDO EN EL ÁRBOL CONJUNTO el 2026-09-02, y NINGUNO de los dos
        // techos anteriores valía.** Los 9.499 B de la línea de arriba se midieron con la rama del
        // justificante SOLA (contra 9.365), y los 9.826 del carril de Google con la suya (contra los
        // mismos 9.365). Cada uno subió el techo **desde la misma base**, así que al fusionar los dos
        // el payload lleva las dos cosas: **medido 9.967 B**, por encima de los 9.900 que su rama
        // dejó puestos. *Dos ramas que suben el mismo presupuesto no se fusionan eligiendo un número.*
        // ▶ **Y la aritmética CIERRA**, que es lo que demuestra que no se coló nada: 9.365 + 461
        // (Google, 9.365→9.826) + 141 (justificante, medido aquí como el delta que aporta) = 9.967
        // exactos. Los rótulos de los dos carriles **se suman**: no comparten ni una clave.
        // ⚠️ El payload **ANÓNIMO no se mueve** (2.769 B, igual que en la rama de Google, con sus
        // 31 B de holgura intactos): los rótulos del justificante viven en `account.*` y los tres
        // derechos de la T3 en zonas con sesión, así que ninguno viaja sin ella. *Que dos presupuestos
        // hermanos se toquen no significa que los dos se muevan: se comprueban por separado.*
        // **10.000 deja 33 B**: la estrechez de siempre, a propósito.
        // ⚠️⚠️ **La deuda escrita arriba ya no es teórica**: cada tanda de cualquiera de los dos
        // carriles empuja este número, y la salida buena sigue siendo la misma — mandar los rótulos
        // del justificante en la RESPUESTA de su endpoint, que ya se pide bajo demanda.
        //
        // ⚠️ **10.000 → 10.050 el 2026-09-02** (`#347`, vincular Google desde la cuenta). Lo que entra
        // es **UN** rótulo, el del botón. Medido: 9.967 → **10.104 B** con los dos que llevaba, y
        // **10.012** tras podar. ▶ **La poda se hizo antes y está medida (−92 B)**: se retiró el
        // `link_intro` que explicaba para qué sirve vincular — bajo el título «Cuentas vinculadas», el
        // rótulo del botón ya lo dice—. Los **12 B** que quedan por encima no salen de acortar el
        // rótulo a algo peor: eso sería pagar la claridad de una frase con doce bytes.
        // **10.050 deja 38 B**, la estrechez de siempre.
        $this->assertLessThan(
            10050, $bytes,
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
            // ⚠️ **ACOTADA en la 2c·7**: el store `ctaPair` del CTA doble también tiene un `mode`,
            // y `this.mode =` casaba con el suyo. Contar «cualquier asignación de mode» convertía
            // una guarda del CAJÓN en una que se rompe cada vez que otro store elige ese nombre.
            // Se acota al bloque del store `purchase`, que es de quien habla.
            1, preg_match_all('/this\.mode\s*=/', $this->purchaseStoreSource($alpine)),
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

        // ⚠️⚠️ **Este recorte se delimitaba con `celebrate()`, y al RETIRARSE el confeti (`#278`) la
        // guarda se habría quedado VACÍA sin ponerse roja**: `mb_strpos` devuelve `false`, `mb_substr`
        // con longitud 0 da la cadena vacía, y una cadena vacía no contiene nada — así que el
        // `assertStringNotContainsString` de abajo pasaba vigilando la nada. Es la trampa que
        // `panel-navegacion.md` §5·3 ya documentó («un test se volvió VACÍO sin ponerse rojo»).
        // ▶ Ahora se delimita con el cierre del propio método y **se asevera que el corte existe**.
        $inicio = mb_strpos($alpine, 'close() {');
        $this->assertNotFalse($inicio, 'no se encuentra `close()`: esta guarda mira el fichero equivocado');

        $close = mb_substr($alpine, (int) $inicio);
        $fin = mb_strpos($close, "\n        },");

        $this->assertNotFalse(
            $fin,
            'no se encuentra el FIN de `close()`: sin él este recorte se queda con el fichero entero '.
            '(o vacío) y la aserción de abajo deja de vigilar el método que dice vigilar.',
        );

        $close = mb_substr($close, 0, (int) $fin);

        $this->assertStringContainsString(
            'scrollLock.unlock', $close,
            'el recorte de `close()` no contiene su propio cuerpo: el delimitador ha dejado de servir.',
        );

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
        // ▶ **`email_verified` entró a propósito el 2026-09-01** (`#331`): desde esa tanda el alta
        // suelta ABRE SESIÓN, así que el área de cuenta es donde se le pide al cliente que verifique
        // su correo — y para pedírselo hay que saber si le falta. Es un booleano; el aviso que
        // sostiene le ahorra al cliente la pantalla sin salida en la que terminaba el alta.
        // ▶ **`terms_pending` entró a propósito el 2026-09-02** (`#349`): las condiciones se aceptan
        // en el momento del CONTRATO y el embudo necesita saber si hay que pedirlas. Es el mismo tipo
        // de hecho que `waiver.pending` —qué le debe esta cuenta antes de comprar— y por eso viaja por
        // aquí: sembrado en cada carga y refrescado al conseguir sesión, o sea **sin una petición
        // más** en el paso de pagar. Un booleano.
        // ▶ **`extras_invite` entró a propósito el 2026-09-03** (`#413` D15): el aviso de la tarjeta
        // cuelga de «te faltan datos» y por eso MORÍA en cuanto el cliente completaba las fichas —que
        // es justo cuando le quedan extras por elegir—. Es `null` salvo cuando hay algo que ofrecer,
        // y viene acotado a UNA reserva: la lista es la única parte del contexto sin cota, y por eso
        // `pending_forms` se poda aquí.
        $this->assertSame(
            ['first_name', 'email_verified', 'upcoming_count', 'next_reservation', 'pending_forms', 'pending_forms_count', 'waiver', 'terms_pending', 'terms_updated', 'phone_missing', 'extras_invite'],
            array_keys($seed),
            'La semilla ha cambiado de forma. Cada campo nuevo viaja en el HTML de TODA página con '.
            'sesión: si hace falta, que entre a propósito — y comprueba antes que el endpoint lo '.
            'publica igual, o habrá dos formas del mismo dato.'
        );

        // (2) Y el techo, como red contra lo que crece con el cliente.
        $bytes = strlen((string) json_encode($seed, JSON_UNESCAPED_UNICODE));

        // ▶ **640 desde el 2026-09-03** (`#413` D15), y el número está MEDIDO: `extras_invite` cuesta
        // **20 B** vacío —que es el caso de este fixture y el normal— y **114 B** con una invitación
        // viva (nombre de producto + URL de post-form). El techo deja sitio al caso caro en vez de
        // ajustarse al barato, que es como un trinquete acaba saltando con el producto sano.
        // ⚠️ La clave viaja SIEMPRE aunque valga `null`: podarla por CAMPO daría dos formas del mismo
        // contexto —`AccountContextSeed` lo tiene escrito— y la poda de aquí es por CARDINALIDAD.
        $this->assertLessThan(
            640, $bytes,
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

    /**
     * El fuente del store `purchase` y solo él.
     *
     * ⚠️ Existe porque la guarda de arriba contaba `this.mode =` en TODO `app.js`, y el store
     * `ctaPair` del CTA doble (2c·7) también tiene un `mode`. Una guarda que cuenta por nombre de
     * propiedad se rompe cuando otro store elige el mismo nombre — y su mensaje culpa al cajón.
     */
    private function purchaseStoreSource(string $alpine): string
    {
        $inicio = strpos($alpine, "Alpine.store('purchase'");

        if ($inicio === false) {
            return '';   // que la guarda falle: si no encuentra el store, no puede aseverar nada
        }

        $fin = strpos($alpine, "Alpine.store('", $inicio + 30);

        return substr($alpine, $inicio, $fin === false ? null : $fin - $inicio);
    }
}
