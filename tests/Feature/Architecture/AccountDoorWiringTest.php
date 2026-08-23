<?php

namespace Tests\Feature\Architecture;

use App\Domain\Identity\Models\User;
use Database\Seeders\LandingContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * **La PUERTA del área de cliente está cableada de punta a punta** (`specs/area-cliente.md` §4.6).
 *
 * El bloque de cuenta del panel es Livewire y vive FUERA del motor Vue —es hermano de su punto de
 * montaje—, así que su botón «Mis reservas» tiene que cruzar tres piezas para llegar a la sección:
 *
 *   marcado Blade (`x-on:click`) → `$store.purchase.followAccountLink()` (Alpine, `app.js`) →
 *   `spaHandle.showAccount()` (el motor, `sidebar/index.js`) → `stores/section.js`
 *
 * ⚠️⚠️ **Este test es del mismo tipo que `SidebarIntentWiringTest`, y por el mismo motivo medido**
 * (`DECISIONES #117`): allí la cadena estaba construida hasta el penúltimo eslabón y **nadie llamaba
 * al último**, así que los enlaces profundos «no fallaban, no hacían nada» durante meses. Los tests
 * unitarios de cada pieza pasaban: un test verde de una función no dice nada sobre si alguien la
 * llama. Aquí se comprueba que **cada eslabón tiene consumidor**, no cómo se comporta.
 *
 * ⚠️ Y una cuarta cosa que sí es conducta y no puede perderse: **el `href` sobrevive**. Entre que el
 * panel se abre y el chunk del motor termina de cargar hay una ventana real en la que `spaHandle` es
 * `null`; sin `href`, un clic ahí no haría nada. Con él, el cliente acaba en la página de siempre.
 */
class AccountDoorWiringTest extends TestCase
{
    use RefreshDatabase;

    private const BLADE = 'resources/views/livewire/site/account-context.blade.php';

    private const ALPINE = 'resources/js/app.js';

    private const ENTRY = 'resources/js/sidebar/index.js';

    /** El marcado del bloque de cuenta llama al puente de Alpine, y con la zona correcta. */
    public function test_the_account_block_asks_the_drawer_for_the_orders_zone(): void
    {
        $blade = $this->source(self::BLADE);

        $this->assertStringContainsString(
            "followAccountLink(\$event, 'orders')", $blade,
            'El botón «Mis reservas» ha dejado de pedirle la zona al cajón. Sin esto vuelve a navegar '.
            'a la página, que es exactamente lo que el área de cliente existe para sustituir.'
        );
    }

    /**
     * ⚠️ **Y el `href` sigue ahí.** No es decorativo: es lo único que responde mientras el motor
     * carga, y lo que hace que el clic central o «abrir en pestaña nueva» funcionen.
     */
    public function test_the_link_still_degrades_to_the_page_when_the_engine_is_not_there(): void
    {
        $this->assertStringContainsString(
            "href=\"{{ route('account.orders') }}\"", $this->source(self::BLADE),
            'Se ha perdido el `href` del botón de cuenta. Con el motor a medio cargar, un clic ahí '.
            'ya no hace NADA — y eso no falla, no avisa y no se ve.'
        );
    }

    /** El puente de Alpine existe y **previene la navegación solo cuando el motor se hace cargo**. */
    public function test_the_alpine_bridge_exists_and_only_swallows_the_click_when_it_can(): void
    {
        $alpine = $this->source(self::ALPINE);

        $this->assertStringContainsString('followAccountLink(event, zone)', $alpine, 'no existe el puente');

        $this->assertStringContainsString(
            'if (! zone || ! this.spaHandle) return;', $alpine,
            'El puente ha dejado de comprobar que el motor esté. Sin esa guarda, se traga el clic y '.
            'no lleva a ninguna parte mientras el chunk carga.'
        );

        $this->assertStringContainsString(
            'this.spaHandle.showAccount(zone)', $alpine,
            'El puente ya no llama al motor: la cadena se rompe en el penúltimo eslabón, que es '.
            'literalmente lo que pasó con la costura de intención en `#117`.'
        );
    }

    /**
     * ⚠️⚠️ **La segunda puerta: la que llega por RUTA, con el cajón NACIDO ABIERTO.**
     *
     * La retirada de `/mi-cuenta/…` (tanda 3, `DECISIONES #120(u)`) convirtió esas rutas en puertas:
     * el servidor emite `data-account-zone` y el motor entra en esa zona. Su eslabón frágil es
     * **dónde se aplica**, y lo cazó el navegador (`V12`): la primera versión lo hacía en `open()`,
     * que en este camino **no se llama nunca** —el cajón ya nace abierto—, así que el cliente que
     * venía de un correo aterrizaba en el índice en vez de en sus reservas.
     *
     * ▶ Es EXACTAMENTE el mismo camino que dejó el hueco vacío en `#59(b)`, y por eso este caso ancla
     * en `bootSpaEngine`, que es el punto por donde pasan los dos: el que abre a mano y el que nace
     * abierto.
     */
    public function test_the_route_door_applies_its_zone_where_both_paths_converge(): void
    {
        $alpine = $this->source(self::ALPINE);

        $this->assertStringContainsString(
            "accountZone: document.body.dataset.accountZone || ''", $alpine,
            'El motor ha dejado de leer la zona que emite el servidor: las rutas de `/mi-cuenta/…` '.
            'abrirían el cajón en el catálogo de compra, y los 8 correos ya entregados con ellas.'
        );

        // El bloque de `bootSpaEngine()`, que es por donde pasan los DOS caminos.
        $boot = mb_substr($alpine, (int) mb_strpos($alpine, 'async bootSpaEngine()'));
        $boot = mb_substr($boot, 0, (int) mb_strpos($boot, 'open() {'));

        $this->assertStringContainsString(
            'this.applyAccountZone(this.spaHandle)', $boot,
            'La zona se aplica FUERA de `bootSpaEngine()`. Si vuelve a colgar solo de `open()`, el '.
            'cajón que nace abierto —que es como llega toda puerta por ruta— no entrará en su zona: '.
            'es el fallo que `#59(b)` ya pagó con el hueco vacío.'
        );
    }

    /**
     * ⚠️⚠️ **La zona tiene UN SOLO consumidor, y eso es lo que impide que llegue tarde.**
     *
     * Desde que los botones de la cabecera abren el cajón (`specs/auth-en-cajon.md` §4.5) hay **dos**
     * caminos que necesitan aplicarla: el que nace abierto por una ruta y el que la pide con el clic.
     * Escribir la consumición en los dos habría dejado dos sitios que **vacían la misma señal**, y con
     * eso uno llega a una zona ya consumida — o, si a alguien se le olvida vaciarla, cerrar y reabrir
     * el cajón devuelve al cliente a esa pantalla una y otra vez (la trampa de 4.0a y de `#120(u)`).
     */
    public function test_the_zone_is_consumed_in_exactly_one_place(): void
    {
        $alpine = $this->source(self::ALPINE);

        $this->assertSame(
            1, substr_count($alpine, 'showAccount(this.accountZone)'),
            'Hay más de un sitio que aplica la zona pendiente. La señal se VACÍA al aplicarla, así '.
            'que con dos consumidores uno de los dos llega tarde y su pantalla no se abre.'
        );

        $this->assertStringContainsString(
            "this.accountZone = ''", $alpine,
            'La zona ya no se CONSUME. Sin vaciarla, cerrar y reabrir el cajón devolvería al cliente '.
            'a esa pantalla una y otra vez, y no podría llegar al embudo sin recargar la página.'
        );
    }

    /**
     * **El puente de la CABECERA: abre el cajón y además lo lleva a su zona.**
     *
     * ⚠️ Es distinto de `followAccountLink()` y las dos cosas hacen falta. Aquél lo usan botones que
     * viven DENTRO del cajón —ya está abierto, solo hay que conmutar—; éste, los de fuera, donde el
     * cajón está **cerrado y el motor puede no existir todavía**. Por eso cuelga de la PROMESA: entre
     * el clic y el montaje hay una ventana real en la que `spaHandle` es `null`, y aplicar la zona
     * ahí sería aplicarla sobre nada — el clic «no fallaría y no haría nada» (`DECISIONES #117`).
     */
    public function test_the_header_bridge_opens_the_drawer_and_waits_for_the_engine(): void
    {
        $alpine = $this->source(self::ALPINE);

        $this->assertStringContainsString('openAccount(event, zone)', $alpine, 'no existe el puente de la cabecera');

        // ⚠️ El corte termina en la DEFINICIÓN del consumidor (`applyAccountZone(handle) {`), no en su
        // nombre a secas: el puente lo LLAMA, así que cortar por el nombre dejaba fuera justo la línea
        // que este caso quiere leer. La primera versión de esto falló por eso.
        $bridge = mb_substr($alpine, (int) mb_strpos($alpine, 'openAccount(event, zone)'));
        $bridge = mb_substr($bridge, 0, (int) mb_strpos($bridge, 'applyAccountZone(handle) {'));

        $this->assertStringContainsString(
            'this.open()', $bridge,
            'El puente de la cabecera ya no ABRE el cajón: conmutaría la sección de un panel que '.
            'sigue cerrado, y el clic no enseñaría nada.'
        );

        $this->assertStringContainsString(
            'this.applyAccountZone(handle)', $bridge,
            "El puente aplica la zona SIN esperar al motor.\n".
            '⚠️ El chunk se trae con `import()`: en esa ventana `spaHandle` es `null` y la zona se '.
            'aplicaría sobre nada. El clic no fallaría y no haría nada.'
        );
    }

    /** Y el motor lo publica. Sin esto, el puente llamaría a un método que no existe. */
    public function test_the_engine_publishes_the_entry_point(): void
    {
        $this->assertStringContainsString(
            'showAccount: (zone) =>', $this->source(self::ENTRY),
            'El motor ha dejado de publicar `showAccount`. El puente de Alpine llamaría a `undefined` '.
            'y el botón dejaría de responder sin que nada avise.'
        );
    }

    /**
     * **El extremo del servidor**: el bloque se sirve con su cableado en el HTML real.
     *
     * ⚠️ No basta con que el fichero Blade lo tenga: `account-context` es un componente Livewire que
     * podría dejar de renderizarse —o renderizar otra rama— sin que el fichero cambie. Esto mira lo
     * que el cliente RECIBE.
     */
    public function test_the_served_html_carries_the_wiring_for_a_signed_in_customer(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $html = (string) $this->actingAs($user)->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('acct__btn--reservas', $html, 'no se está sirviendo el botón de cuenta');
        // ⚠️ Se asevera sobre el HTML **tal cual se sirve**, con sus comillas simples sin escapar:
        // Blade no escapa el contenido de un atributo escrito a mano, y dar por hecha una entidad
        // (`&#039;`) haría que este caso fallara por una razón que no es la que vigila.
        $this->assertStringContainsString("followAccountLink(\$event, 'orders')", $html,
            'el botón llega al navegador SIN su cableado: navegaría a la página en vez de abrir el área');
    }

    /**
     * **Y un invitado SÍ recibe cableado — se INVIRTIÓ el 2026-08-23** (`specs/auth-en-cajon.md` §4.5).
     *
     * ⚠️ Este caso decía lo contrario, y era correcto entonces: sin sesión no había área que abrir, así
     * que sus botones abrían el modal de la cabecera. Con las tres pantallas de auth dentro del cajón,
     * **identificarse ES una zona**, así que el invitado tiene a dónde ir y su botón tiene que
     * llevarle — sin cerrar el cajón, que es lo que le hacía perder de vista la cesta.
     *
     * ▶ Se conserva el caso en vez de borrarlo porque su SUJETO sobrevive: qué recibe un invitado en
     * el HTML. Lo que cambió es la respuesta correcta (`CONVENCIONES §3.quater`).
     */
    public function test_a_guest_now_gets_wiring_because_signing_in_lives_in_the_drawer(): void
    {
        $this->seed(LandingContentSeeder::class);

        $html = (string) $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString(
            "openAccount(\$event, 'login')", $html,
            'El bloque de cuenta de un invitado ya no lleva al cajón: habría vuelto a depender de un '.
            'modal que este trabajo retira.'
        );

        // ⚠️⚠️ **Se CUENTAN los dos CTA de alta, y el recuento no es adorno: la primera versión de
        // este caso aseveraba «contiene el href» y una mutación que se lo quitó al CTA de ESCRITORIO
        // pasó en verde**, porque el del cajón móvil seguía teniéndolo. Es la trampa 3 de
        // `CONVENCIONES §3.quater`: un ancla que no es única mide la mitad que no falla.
        $this->assertSame(
            2, substr_count($html, "openAccount(\$event, 'register')"),
            'Los CTA de alta son DOS —escritorio y cajón móvil— y no llegan los dos cableados. '.
            '⚠️ Si el fixture configurara un registro EXTERNO, el de escritorio sería otro enlace: '.
            'este recuento también lo delata.'
        );

        // ⚠️ Y el `href` de los dos sobrevive: es lo que responde sin JS, con el clic central y al
        // abrir en pestaña nueva. La ruta existe como PUERTA justo para eso.
        $this->assertSame(
            2, substr_count($html, 'href="'.route('registro').'"'),
            'Alguno de los dos CTA de alta perdió su `href`: sin él, ese clic no hace NADA cuando el '.
            'motor todavía no ha cargado, y «abrir en pestaña nueva» deja de funcionar.'
        );

        // ⚠️⚠️ **Control negativo: ya no queda NADIE que abra el modal.** Sin esto, el caso pasaría
        // igual con los dos caminos vivos a la vez —un clic abriría el modal ENCIMA del cajón que
        // acaba de abrirse en la misma zona—, que es el estado a medias que este paso existe para no
        // dejar.
        //
        // ⚠️ Se mira **en los ficheros que abrían el modal**, y no en el HTML entero, y el matiz es
        // real: las plantillas del propio modal siguen renderizándose para un invitado y sus enlaces
        // internos («¿no tienes cuenta?», «volver a entrar») todavía nombran el store. Son código
        // MUERTO —a ese modal no llega nadie— y se van con él cuando se retire. Aseverar sobre el HTML
        // entero daría un rojo por algo que no es lo que este caso vigila.
        foreach (['resources/views/components/site/nav.blade.php',
            'resources/views/livewire/site/account-context.blade.php'] as $opener) {
            $this->assertStringNotContainsString(
                '$store.auth.open(', $this->source($opener),
                "«{$opener}» ha vuelto a abrir el modal de auth en vez de llevar al cajón."
            );
        }

        // Y por URL tampoco: sin `data-auth-modal` con valor, ninguna ruta lo despierta al cargar.
        $this->assertStringContainsString('data-auth-modal=""', $html, 'una ruta sigue abriendo el modal al cargar');
    }

    private function source(string $relative): string
    {
        $path = base_path($relative);

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
