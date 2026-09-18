<?php

namespace App\Http\Sidebar;

use App\Domain\Identity\Services\GoogleAuth;
use App\Domain\Platform\Services\SiteLocales;
use Illuminate\Support\Arr;

/**
 * **El ARRANQUE del cajón: lo que el servidor sabe y el cliente no puede adivinar** (F4 · T1,
 * `docs/specs/cajon-empaquetable.md` §4.5, `DECISIONES #631`).
 *
 * Hasta el 2026-09-18 estas 300 líneas vivían DENTRO de `components/layout.blade.php`, como un
 * `json_encode([...])` en el atributo `data-boot` del hueco del motor. Funcionaba, y tenía un
 * límite: **solo una página pintada por Blade podía arrancar el cajón**. Una landing a mano —que es
 * a donde va el programa «producto e instancias»— no puede, y lo que hace posible que lo haga es
 * que el arranque deje de ser un trozo de plantilla y pase a ser un MODELO DE LECTURA con dos
 * transportes: el atributo del layout (hoy) y la API (`GET /api/v1/cajon/boot` y `cajon/session`).
 *
 * Por eso se parte en DOS mitades, y la frontera es una sola pregunta — *¿depende de quién mira?*:
 *
 *  · {@see shared()} — NO: los rótulos del idioma activo y las rutas. Es lo que pesa (18,3 de los
 *    18,7 kB del montaje anónimo), es igual para todo visitante y por eso se puede cachear.
 *  · {@see personal()} — SÍ: quién es el titular, su contexto de cuenta, el desenlace de un pago
 *    pendiente (que se CONSUME al leerlo) y los rótulos y rutas que solo viajan con sesión.
 *
 * {@see forCurrentRequest()} las funde **en el orden exacto de claves que tenía el layout**. No es
 * manía: la T1 se midió comparando byte a byte el `data-boot` de siete contextos antes y después
 * de la mudanza, y `json_encode` respeta el orden de inserción.
 *
 * ⚠️ **Todos los comentarios de abajo se mudaron con el código, y son la mitad del valor**: cada
 * poda clave a clave es una trampa ya pagada — `i18n.js` devuelve CADENA VACÍA cuando falta una
 * clave (`#333`), así que un rótulo que no entre aquí se pinta mudo y nada avisa.
 */
final class SidebarBoot
{
    /**
     * El payload entero, tal y como lo pinta el layout en `data-boot`.
     *
     * @return array<string, mixed>
     */
    public static function forCurrentRequest(): array
    {
        // ⚠️⚠️ **Los textos de la pantalla que completa un alta con Google viajan SOLO en su puerta**
        // (`specs/auth-con-google.md` §7). Es la primera vez que este montaje poda por RUTA y no por
        // sesión, y el motivo es que a esa zona **no se llega de ninguna otra forma**: hay que volver
        // de Google, y el retorno aterriza justo en `/registro/google`. Mandar sus ~380 B en cada
        // página pública sería pagarlos para no pintarlos nunca, que es lo que el presupuesto del
        // montaje anónimo existe para cazar.
        // ▶ La condición es la MISMA que abre el cajón en esa zona (`AccountDoor::zone()`), no una
        // copia: si la puerta cambia de nombre, el texto la sigue.
        $shared = self::shared(withGoogleSignup: AccountDoor::zone() === 'google-signup');
        $personal = self::personal();

        return [
            'outcome' => $personal['outcome'],
            'orderCode' => $personal['orderCode'],
            'messages' => $shared['messages'],
            'ui' => $shared['ui'],
            // `array_replace` y no `+`: con sesión, el subgrupo `account` ENTERO sustituye al de una
            // sola clave que viaja para todos, y conserva su posición — el cajón lee un solo camino
            // (`account.account.title`) haya sesión o no.
            'account' => array_replace($shared['account'], $personal['account']),
            ...(array_key_exists('locales', $personal) ? ['locales' => $personal['locales']] : []),
            'auth' => $shared['auth'],
            'userId' => $personal['userId'],
            'accountContext' => $personal['accountContext'],
            'urls' => $shared['urls'] + $personal['urls'],
        ];
    }

    /**
     * La mitad que NO depende de quién mira: rótulos del idioma activo y rutas. Cacheable.
     *
     * @return array{messages: array<string, mixed>, ui: array<string, mixed>, account: array<string, mixed>, auth: array<string, mixed>, urls: array<string, string>}
     */
    public static function shared(bool $withGoogleSignup): array
    {
        return [
            // · `messages` — el grupo `tickets` del locale activo (§4.5 de `sidebar-spa.md`). La SPA
            //   no tiene canal de i18n propio: son 169 claves × 3 locales que salen de `__()` en
            //   servidor.
            // ⚠️ `terms_link` lleva un `<a href>` dentro y viaja **ya interpolado**, por lo mismo que
            // los del alta: recomponer una frase traducida en el cliente obliga a partirla, y en
            // francés y en inglés no ordena igual. La URL la decide `routes/web.php`, no el cajón
            // (`#349`).
            // ⚠️⚠️ **`due_terms` salió de aquí en `#562`**: la casilla dejó de llevar el enlace dentro
            // —19 px de alto contra un suelo táctil de 48— y las condiciones se abren desde su propia
            // fila, que recibe la URL por `urls.terms`. Al ser ya texto plano, interpolarlo sería
            // pasarle un `:url` que nadie sustituye.
            'messages' => array_replace(__('tickets'), [
                'terms_link' => __('tickets.terms_link', ['url' => route('legal.condiciones')]),
            ]),
            // · `ui` — el grupo `ui` (hoy, el rótulo del velo de carga). Va SEPARADO de `messages` y
            //   no fundido con él porque son dos grupos distintos de `lang/`: aplanarlos aquí crearía
            //   una tercera forma del diccionario que no existe en ningún otro sitio. Son 25 bytes.
            'ui' => __('ui'),
            // ⚠️ `account` va PODADO a lo que cada pantalla pinta, y la poda es la decisión: el grupo
            // entero son **9,6 kB** en español (tanto como `tickets`) y viajaría en el HTML de todas
            // las páginas públicas. Se conserva el CAMINO real de `lang/` (`account.login.email`) en
            // vez de aplanarlo, porque `i18n.js` lee por camino y una forma nueva del diccionario
            // sería la tercera.
            'account' => [
                'login' => __('account.login'),
                // ⚠️ El texto de privacidad lleva un `<a href>` dentro y viaja **ya interpolado**: la
                // URL la compone `route()`, y partirlo en «texto + enlace» obligaría al cliente a
                // recomponer una frase traducida —que en francés y en inglés no ordena igual—. El
                // cajón lo pinta con `v-html`; el contenido sale de `lang/` y de `route()`, nunca de
                // un usuario.
                // ⚠️ `register` va PODADO clave a clave desde el 2026-08-26 (Fase 6, `DECISIONES
                // #166`); antes viajaba entero. Se quedan fuera `must_accept`, `already_exists`,
                // `exists_unverified` y `bot_check_failed`: son literales que publica el SERVIDOR
                // dentro del 422 —`register.js::registerErrors()` los pinta tal cual— y el cajón nunca
                // los leía del arranque. Viajaban en TODAS las páginas públicas. La casilla del waiver
                // y su «leer el texto» entran aquí porque los pinta el formulario de alta.
                // ⚠️ Y desde la T8·c (`#350`) se van también `accept_terms` y `marketing`: sus casillas
                // salieron de las dos altas —las condiciones se aceptan al contratar y el marketing
                // vive en el interruptor de la cuenta—, así que eran bytes que viajaban en cada página
                // pública sin tener quien los pintara.
                // ⚠️ **`eyebrow` se va en `#566`** (grieta 12, `[DECIDIDO owner]`): las cinco pantallas
                // de auth pierden su antetítulo —era la costura del modal del que se mudaron— y en las
                // otras veinte el cajón titula y punto.
                // ⚠️ **Y `array_replace` se va con él**: estaba solo para interpolar el `:url` dentro
                // de `privacy_notice`, y desde `#566` el enlace vive en su propia fila (`privacy_read`)
                // con la URL viajando suelta en `urls.privacy`.
                'register' => Arr::only(__('account.register'), [
                    'cta', 'title', 'subtitle', 'name', 'email', 'phone', 'password',
                    // ⚠️ `phone_hint` entra con el campo (`#561`): la pista dice para qué se pide el
                    // teléfono, y sin ella en esta lista el `t()` del cajón devolvería CADENA VACÍA
                    // sin fallar — el hueco de `#333`, que se ve como un campo sin explicación y no
                    // como algo roto.
                    'password_hint', 'phone_hint', 'accept_waiver', 'waiver_read', 'privacy_notice',
                    // ⚠️ La pista del descargo (`#588`): sin ella aquí, `t()` la devuelve VACÍA (`#333`).
                    'waiver_hint',
                    // ⚠️ El rótulo de la fila que abre la política (`#566`). Sin él aquí, la fila se
                    // pinta MUDA: `t()` devuelve cadena vacía sin fallar (`#333`).
                    'privacy_read',
                    'submit', 'submitting', 'fix_errors', 'leave_blank',
                    // ⚠️ **El botón de Google viaja SIEMPRE y su pantalla NO** (`#343`): el rótulo lo
                    // pintan las dos pestañas de auth, que las ve quien no tiene sesión, así que no
                    // hay condición bajo la que esconderlo. Cuesta ~35 B. El subgrupo `google` —la
                    // pantalla que completa el alta— viaja solo en su puerta: a ella no se llega sin
                    // volver de Google.
                    // ⚠️ Y con él su «o» (T8·d, `#350`): el separador se pinta pegado al botón y
                    // desaparece con él, así que viaja igual y por lo mismo.
                    'google_cta', 'or',
                ]),
                // ⚠️ **Recuperar contraseña viaja SIN sesión, igual que entrar y darse de alta**
                // (`specs/auth-en-cajon.md` §4.1): sus tres pantallas son precisamente las que ve
                // quien NO ha entrado, así que podarlas al invitado dejaría la zona con los rótulos en
                // blanco — que es el fallo que `i18n.js` no puede avisar. El subgrupo entero son 9
                // claves.
                'forgot' => __('account.forgot'),
                ...($withGoogleSignup ? ['google' => __('account.google')] : []),
                // ⚠️⚠️ **Los rótulos del BLOQUE DE CUENTA viajan para TODO EL MUNDO, y no es
                // comodidad** (`specs/account-context-vue.md` §4.12). El bloque cambia de cara **sin
                // recargar**: quien entra en el paso 5 del embudo tiene en memoria el payload de
                // INVITADO, y el bloque pasa a saludarle por su nombre en ese mismo instante. Podarlos
                // por sesión le dejaría el saludo, la sub-línea y el aviso **en blanco** —`i18n.js`
                // devuelve cadena vacía cuando falta una clave y en producción un texto ausente no
                // puede tumbar el cajón—, y **nada avisaría**. Es exactamente el bloqueante que la
                // revisión de `specs/auth-en-cajon.md` paró con los textos del área.
                // ▶ Misma decisión y mismo motivo que `login`, `register` y `forgot`.
                // ⚠️ Podados clave a clave: de `nav` se pintan tres de sus rótulos, y de `sidecart`
                // cinco — `tag` se retiró por muerta.
                // ⚠️⚠️ **Y `next`/`no_upcoming` salen el 2026-08-28** (`specs/identidad-qr-puerta.md`
                // §9.7 C·2, `DECISIONES #217`): la sub-línea de la próxima reserva se retira del bloque
                // de cuenta —el índice del área ya la enseña— y su hueco lo ocupa «Mi QR». Este grupo
                // **no se poda por sesión**, así que esos dos rótulos viajaban en TODAS las páginas
                // públicas para no pintarse nunca: es la poda que el presupuesto del montaje pide
                // antes de subir ningún techo. También se fueron de `lang/`.
                'nav' => Arr::only(__('account.nav'), ['hello', 'sign_out', 'login']),
                'sidecart' => Arr::only(__('account.sidecart'), [
                    'guest_hello', 'guest_sub',
                    'form_pending_one', 'form_pending_many', 'upcoming_count',
                ]),
                // ⚠️ **El rótulo de «Mi cuenta» viaja SIEMPRE, por lo mismo que los de arriba**: es uno
                // de los tres botones del bloque, y el bloque cambia de cara **sin recargar**. Con
                // sesión, el subgrupo entero lo sustituye ({@see personal()}) —y lleva esta misma
                // clave—, así que el cajón lee un solo camino (`account.account.title`) haya sesión o
                // no.
                // ⚠️⚠️ **Y con él, el de «Mi QR»** (2026-08-28, revisión de `#217`): el bloque tiene
                // CUATRO botones desde que existe el atajo al QR, y el cuarto se rotula con
                // `account.account.card.title`. Sin esta línea, quien gana la sesión **sin recargar**
                // —el paso 5 del embudo, o el área— repinta el bloque con un botón MUDO y sin nombre
                // accesible: el rótulo solo viajaba en el subgrupo con sesión, que en esa página nunca
                // llegó. Medido en navegador por la revisión.
                // ▶ Es la MISMA regla que el rótulo de arriba, y por el mismo motivo: **lo que el
                // bloque puede pintar sin recargar tiene que viajar siempre**. Cuesta 26 B por página
                // anónima; el subgrupo entero (12 rótulos, ~800 B) NO viaja: la ZONA del QR sigue
                // siendo solo para quien tiene sesión.
                'account' => ['title' => __('account.account.title'), 'card' => ['title' => __('account.account.card.title')]],
                // ⚠️ **Podado clave a clave**: el subgrupo `verify` son 14 rótulos y esta pantalla
                // pinta **nueve**. Los cinco que se quedan fuera —`intro` y los tres
                // `notice_resend_*`— son de la PÁGINA de verificación de la web (`/email/verificar`),
                // que sobrevive intacta y no la pinta el cajón.
                // ⚠️ `resending` NO entra, aunque la web lo pinte: allí el botón enseña «Reenviando…»
                // mientras Livewire da la vuelta al servidor. Aquí no hay vuelta que esperar —el botón
                // se deshabilita y arranca la cuenta atrás en el acto—, así que ese rótulo no lo pinta
                // nadie. Un texto que viaja en cada página para no pintarse nunca es exactamente lo
                // que este presupuesto existe para cazar.
                // ⚠️ `eyebrow` se va en `#566`, como en `register`: el «Casi listo» era la cuarta
                // costura del modal, y el canvas solo había contado tres.
                'verify' => Arr::only(__('account.verify'), [
                    'title', 'sent_to', 'spam_hint', 'resend',
                    'resend_in', 'resends_left', 'resend_limit', 'already_have_account',
                    // `#331`: el aviso del índice de la cuenta para quien entró sin verificar. Va en
                    // `verify` y no en `privacy.waiver` porque el aviso es del CORREO — la exención
                    // solo le añade una frase cuando la hay.
                    'pending_notice',
                ]),
            ],
            'auth' => __('auth'),
            // ⚠️ **Las rutas las compone el SERVIDOR, no el cajón** (Fase 4 · paso 4.6·2). Las pintan
            // las pantallas de desenlace —«escribirnos» y «ver mis reservas»— y quemarlas en el JS
            // sería la segunda fuente de una URL que ya decide `routes/web.php`; el día que cambie un
            // slug, el cajón mandaría a un 404 **y ningún gate lo vería**: `href` no es atributo de
            // contrato del diff de árbol, como enseñaron el WhatsApp del aviso de pausa y el enlace de
            // registro.
            'urls' => [
                'contact' => route('contacto'),
                'my_orders' => route('account.orders'),
                // ⚠️ **La PUERTA del índice, y es lo que hace posible aterrizar en «Mi cuenta» tras
                // entrar dentro del cajón** (`specs/auth-en-cajon.md` §3.3). Los textos del área
                // viajan solo con sesión, así que quien consigue sesión SIN recargar aterrizaría en un
                // índice en blanco: se navega aquí, la página se recarga ya identificada y el cajón
                // nace abierto en su zona. La ruta la compone el SERVIDOR, como las otras dos.
                'account' => route('account'),
                // ⚠️ A dónde va quien cierra sesión DESDE el cajón. Es la misma redirección que hace
                // `LogoutController` en la web, y por el mismo motivo: la ruta actual puede ser una
                // PUERTA (`/mi-cuenta`) y recargarla reabriría el cajón en una zona que ya no se puede
                // ver. La compone el SERVIDOR — un «/» quemado en el cliente fallaría en una
                // instalación con prefijo de idioma.
                'home' => route('home'),
                // ⚠️ **Las CONDICIONES de reserva, para la fila del paso de pagar** (`#562`). Antes la
                // URL viajaba dentro del literal `due_terms`; al salir el enlace de la frase tiene que
                // viajar suelta, y por el mismo motivo que las otras tres: es `routes/web.php` quien
                // decide el slug, y un `href` no es atributo de contrato del diff de árbol, así que un
                // enlace roto aquí pasaría el gate en verde.
                'terms' => route('legal.condiciones'),
                // ⚠️ **La POLÍTICA DE PRIVACIDAD, para la fila de las dos altas** (`#566`). Antes
                // viajaba interpolada dentro de `privacy_notice`; al salir el enlace de la frase tiene
                // que viajar suelta, y por el mismo motivo que `terms`: el slug lo decide
                // `routes/web.php` y un `href` no es atributo de contrato del diff de árbol, así que
                // un enlace roto aquí pasaría el gate en VERDE.
                'privacy' => route('legal.privacidad'),
                // ⚠️ **La IDA a Google, y solo si esta instalación la ofrece**
                // (`specs/auth-con-google.md` §10): su presencia ES el interruptor del botón — sin
                // claves no viaja la clave y el botón no se pinta, que es el mismo hueco que falla
                // hacia invisible del logotipo o el kit.
                // ▶ Va aquí y no en `GET /config` porque el botón lo pintan las zonas de AUTH del
                // área, y esa sección **no pide config**: solo lo hace el embudo. Un segundo sitio del
                // que leerlo sería el `if` que un día discrepa.
                ...(GoogleAuth::enabled() ? ['google' => route('auth.google.redirect')] : []),
            ],
        ];
    }

    /**
     * La mitad que SÍ depende de quién mira. **Nunca se cachea**, y leerla tiene un efecto: el
     * desenlace del pago se CONSUME.
     *
     * @return array{outcome: ?string, orderCode: ?string, account: array<string, mixed>, locales?: array<int|string, mixed>, userId: int|string|null, accountContext: mixed, urls: array<string, string>}
     */
    public static function personal(): array
    {
        // · `outcome` — en qué quedó el pago. Lo posee `SidebarEntry` y llega ya CONSUMIDO; mirarlo
        //   dos veces reabriría el cajón en cada página hasta que caducara la sesión (el fallo que
        //   cerró el paso 4.0a).
        // ⚠️ Aquí se CONSUME, no se mira, y es el matiz que el paso 4.0a dejó anotado: el `<body>`
        // del layout usa `peek()` para decidir si el cajón nace abierto. `consume()` está memoizado
        // por petición, de modo que ese `peek()` sigue viendo lo suyo y nadie se roba el valor.
        $entry = SidebarEntry::consume();

        return [
            'outcome' => $entry->outcome,
            'orderCode' => $entry->orderCode,
            // ⚠️ El ÁREA DE CLIENTE (`specs/area-cliente.md`) entra con **una sola clave**, no con el
            // subgrupo `account.account` entero: ahí viven además los seis textos de privacidad, que
            // no pinta ninguna zona de la tanda 1. Misma poda y mismo motivo que en `shared()`: el
            // grupo completo son 9,6 kB en el HTML de todas las páginas públicas.
            // ⚠️⚠️ **Los textos del ÁREA DE CLIENTE viajan SOLO con sesión, y es una decisión medida**
            // (`specs/area-cliente.md`). Un invitado no puede abrir esa sección —la puerta solo se
            // cablea con sesión (§4.6)—, así que sus ~660 B serían puro desperdicio **en la ruta de
            // más tráfico del sitio**, que es justo la que `PERF-02` existe para proteger. Con sesión,
            // el ahorro no aplica y los textos hacen falta antes de que el cliente pulse nada:
            // pedirlos al abrir metería una petición en el camino.
            // Lo vigila `SidebarMountTest::test_the_mount_payload_stays_pruned`.
            //
            // ⚠️ Y va PODADO clave a clave, no por subgrupos: `account.orders` entero son 1.279 B y lo
            // que estas zonas pintan, **659** — medido. El grupo `account` completo son 9,6 kB.
            'account' => auth()->check() ? [
                'account' => [
                    'title' => __('account.account.title'),
                    // La salida de quien entró con Google y no tiene contraseña (`#344`): la pintan
                    // las CUATRO pantallas que exigen contraseña. Va aquí —con sesión— porque ninguna
                    // se ve sin haber entrado. El rótulo de su botón se reutiliza de `forgot.title`,
                    // que ya viaja para todos.
                    'no_password' => __('account.account.no_password'),
                    // ⚠️ Los dos subgrupos que pintan las zonas de la tanda 2, ENTEROS y no podados
                    // clave a clave: son 9 y 4 rótulos que la pantalla usa todos —etiqueta, ayuda,
                    // botón y su estado «guardando»—, así que recortarlos sería trabajo de
                    // mantenimiento sin ahorro.
                    // ⚠️ El aviso de «no coinciden» se compone AQUÍ y no en el cliente: es
                    // `validation.confirmed` de Laravel con su atributo interpolado, así que dice
                    // exactamente lo mismo que la página web para el mismo caso. Componerlo en JS
                    // habría sido una segunda redacción.
                    'password' => __('account.account.password') + [
                        'mismatch' => __('validation.confirmed', ['attribute' => __('account.account.password.new')]),
                    ],
                    'sessions' => __('account.account.sessions'),
                    'profile' => __('account.account.profile'),
                    // ⚠️ Privacidad SÍ va podado clave a clave, al revés que los tres de arriba: el
                    // subgrupo lleva además los CUATRO `consent_types`, y el rótulo del documento lo
                    // publica la API (`type_label`), para que el cliente no lleve una segunda tabla
                    // que envejece sola.
                    // ⚠️ `consents_title` y `no_consents` entran en la tanda 3 (paso 11): la lista de
                    // consentimientos se retira de `/mi-cuenta` y pasa a esta zona
                    // (`specs/area-cliente.md` §4.8).
                    // ⚠️ `waiver` entra ENTERO el 2026-08-26 (Fase 6, `DECISIONES #166`): son 12
                    // rótulos y la tarjeta y el aviso del índice los pintan todos. La casilla y su
                    // «leer el texto» van en `register`, que ya viaja.
                    'privacy' => Arr::only(__('account.account.privacy'), [
                        'title', 'intro', 'consents_title', 'no_consents', 'export_btn',
                        'delete_title', 'delete_intro', 'delete_password',
                        // ⚠️ **Los dos rótulos de la pregunta entran en `#565`**, cuando borrar la
                        // cuenta dejó de confirmarse con `window.confirm`. Sin ellos aquí la clave
                        // EXISTE en `lang/` y el botón sale MUDO — `t()` devuelve cadena vacía en
                        // silencio (la trampa de `#333`), y el botón mudo sería el que borra la cuenta.
                        'delete_confirm', 'delete_confirm_yes', 'delete_confirm_no',
                        'delete_btn', 'deleting', 'waiver',
                        // El interruptor de marketing y la palabra que marca una fila RETIRADA (art.
                        // 7.3, `#344`). Van con sesión, como el resto de esta zona: nadie los pinta
                        // sin haber entrado.
                        'consent_revoked', 'marketing_label', 'marketing_hint',
                    ]),
                    // ⚠️ **Menores a cargo** entra ENTERO (Fase 6 · C, `specs/menores-a-cargo.md`
                    // §9.8): 17 rótulos que la zona y sus tarjetas pintan todos. Y primero se
                    // REUTILIZÓ, que es lo que el presupuesto pide antes de subirlo: la casilla y
                    // «leer el texto» son los de `register.*`, y «Firmar», «Firmando…», «Firma
                    // registrada» y «PDF» los de `privacy.waiver.*` — seis rótulos que ya viajaban y
                    // no se redactan por segunda vez.
                    'dependents' => __('account.account.dependents'),
                    // ⚠️ **Mi carné** entra ENTERO (Fase 6 · A, `specs/identidad-qr-puerta.md` §9.6
                    // B·2): 12 rótulos que la zona pinta todos. Solo con sesión: es una credencial.
                    'card' => __('account.account.card'),
                ],
                'orders' => Arr::only(__('account.orders'), [
                    // ⚠️ «Mis reservas» de cara al cliente, `orders` en el código: manda el texto de
                    // `lang/` (`account.orders.title`), y el nombre técnico se queda para no
                    // confundirlo con `GET /me/reservations`, que es otro.
                    'title', 'empty', 'pagination',
                    'item_finished', 'item_cancelled',
                    'retry_payment', 'retry_hint',
                    'guest_form_pending', 'guest_form_done',
                    'guest_form_past', 'guest_form_cancelled',
                    // Las respuestas del pack, bajo demanda (tanda 3).
                    'event_data_show', 'event_data_hide',
                    // ⚠️ **El historial y la referencia del pedido** (2026-08-23,
                    // `specs/mis-reservas-por-reserva.md`). Esta poda es clave a clave, así que una
                    // clave nueva que no se añada aquí **viaja vacía**: `i18n.js` devuelve `''` cuando
                    // falta y el botón se pinta SIN TEXTO, sin que nada avise. Medido en navegador al
                    // escribir la pantalla: el «Ver pedido» salió mudo y la referencia del pedido, en
                    // blanco.
                    'history', 'order_ref', 'order_show',
                ]),
                // ⚠️ **«Mis pedidos» tiene grupo PROPIO y va ENTERO** (`DECISIONES #129`): son 8
                // rótulos y la pantalla los usa todos, así que podarlo clave a clave sería
                // mantenimiento sin ahorro. El grupo se llama `purchases` y no `orders` porque aquél
                // ya es el de «Mis reservas» — la misma inversión de nombres que documenta
                // `account/navigation.js`, y por el mismo motivo: la ruta `/mi-cuenta/pedidos` es un
                // contrato que no se puede reasignar.
                'purchases' => __('account.purchases'),
            ] : [],
            // Los idiomas que el selector del perfil ofrece, con su nombre nativo. Van solo CON
            // SESIÓN, como el resto de lo que solo pinta el área de cliente.
            ...(auth()->check() ? ['locales' => SiteLocales::options()] : []),
            // · `userId` — quién es el titular AHORA. La cesta del cajón SPA vive en `localStorage` y
            //   lleva su dueño dentro, así que hace falta para purgarla si cambia (`DECISIONES
            //   #38(d)`). El LOGOUT es una navegación completa, y ese es justo el caso que la sesión
            //   resolvía sola con `invalidate()` y que `localStorage` no tiene. `null` para el
            //   visitante anónimo, que es el valor que la purga compara.
            'userId' => auth()->id(),
            // ⚠️ **El contexto de cuenta que pinta el bloque `.acct`** (§4.3 de
            // `specs/account-context-vue.md`). Sale del MISMO Resource que `GET /me/account-context`,
            // así que el store ve UNA forma venga de donde venga.
            // ⚠️ Podado por CARDINALIDAD y nunca por campo: la lista de formularios pendientes llega
            // con como mucho uno —es la única parte sin cota, y lleva PII—, pero `next_reservation` va
            // entera. El porqué, en el propio fichero.
            // ⚠️ `null` para el anónimo, no ausente: cuesta 24 B medidos y evita que cada consumidor
            // distinga «no está» de «está vacío».
            'accountContext' => AccountContextSeed::forCurrentRequest(),
            // ⚠️ **La de VINCULAR con Google viaja SOLO con sesión** (`#347`): sin ella la ruta
            // responde con la redirección de `auth` y el botón no tendría a dónde llevar. Es además la
            // poda: quien no ha entrado no paga sus bytes.
            'urls' => GoogleAuth::enabled() && auth()->check()
                ? ['google_link' => route('auth.google.link')]
                : [],
        ];
    }
}
