<?php

namespace App\Http\Instancia;

use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Support\Facades\Log;

/**
 * **Las vistas de la INSTANCIA** — la vía B de `docs/specs/paquete-de-instancia.md` §4.1.
 *
 * La landing de una instalación no vive en este repo. El producto presta el motor (las rutas, los
 * componentes, el cajón, el panel) y la instancia pone la página: un directorio `web/` dentro de su
 * paquete, registrado aquí como el namespace `instancia`.
 *
 * ❗❗❗ **Es un NAMESPACE y no un `prependLocation()`, y la diferencia es de seguridad, no de gusto.**
 * Con `prependLocation` la instancia sobrescribiría vistas del producto **por su nombre**: un fichero
 * llamado `mail/verify.blade.php` en el paquete de un cliente secuestraría el correo de verificación
 * del producto, y una página de error se podría sustituir por otra — sin que nada avisara y con el
 * fallo apareciendo en la instalación del cliente, no aquí. Con namespace, lo de la instancia **se
 * llama distinto** (`instancia::contacto`) y no puede colisionar con nada del producto.
 *
 * ⚠️⚠️ **`SEC-12`: esto es una ruta desde la que el servidor EJECUTA CÓDIGO.** Blade compila a PHP.
 * Por eso {@see rutaDelPaquete} solo mira la configuración y las tres comprobaciones de abajo son
 * invariante y no validación cosmética.
 *
 * ⚠️ **Sin paquete no pasa nada malo**: el namespace no se registra, {@see pick} devuelve el respaldo
 * del producto y la instalación sirve su anfitrión mínimo. Una instalación recién montada está así, y
 * un 500 ahí sería un producto que no arranca hasta que alguien le dé una landing.
 */
class InstanceViews
{
    /** El namespace con el que se nombran las vistas de la instancia: `instancia::contacto`. */
    public const NAMESPACE = 'instancia';

    /** La subcarpeta del paquete donde viven las vistas. El resto del paquete no es código de vistas. */
    public const SUBCARPETA = 'web';

    /** El fichero donde el paquete declara con qué producto está hecho para funcionar. */
    public const MANIFIESTO = 'instancia.json';

    /**
     * **La versión del CONTRATO DE INSTANCIA que sirve este producto.**
     *
     * Sube cuando un paquete existente deja de valer tal cual: cambia el nombre de una carpeta, una vista
     * pasa a recibir otras variables, se retira un componente que las landings usaban. Es el MAYOR de
     * `producto-e-instancias.md` §4.1, y por eso NO sube al añadir cosas: añadir no rompe a nadie.
     */
    public const CONTRATO = 1;

    /**
     * **EL CONTRATO DE VISTA**: qué recibe cada vista que una instancia puede vestir (`#649`).
     *
     * ❗❗❗ **Es una promesa hacia fuera, no una nota interna.** Quien escribe la landing de una
     * instalación programa contra esta lista; si una variable se renombra aquí, **se rompen todas las
     * instancias a la vez**, cada una en su servidor y sin que la suite del producto se entere. Por eso
     * lo vigila `InstanceViewContractTest`, que compara el conjunto EXACTO.
     *
     * ⚠️ Son los DATOS, nunca el marcado. El HTML es de la instancia y el producto no opina.
     *
     * ⚠️ Añadir una variable también rompe la guarda, y está bien que así sea: es contrato nuevo, hay
     * que declararlo, y declararlo es lo que hace que el siguiente que escriba una landing sepa con qué
     * puede contar.
     *
     * ❗❗❗ **Medido el 19-09 al escribir la guarda, y la cifra sorprendió: son NUEVE, no dos.** El
     * controlador pasa `answers` y `topics`; las otras siete las inyecta el **composer global**
     * (`View::composer('*')`) en TODAS las vistas sin que nadie las pida. O sea que el producto ya
     * promete —sin saberlo— `site`, `heroStatus`, `offers`, el par de `ctaMinPrice*` y las dos de
     * cookies. Escribirlas aquí es lo que convierte esa promesa tácita en una declarada.
     *
     * ⚠️⚠️ **Y esta lista va a ENCOGER**: ese composer es «la pieza que hay que sustituir por el menú»
     * (`instancia-y-landing-fuera.md` §1.1) y `offers` es uno de los seis recursos que `#631` retira
     * del panel. Cuando eso pase, el contrato de instancia sube de MAYOR y hay que avisar a cada
     * instalación: su landing dejará de recibir lo que hoy recibe. **Sin esta lista, ese día nadie se
     * habría enterado hasta ver la web del cliente rota.**
     *
     * @var array<string, array{ruta: string, datos: list<string>}>
     */
    public const CONTRATO_DE_VISTAS = [
        'contacto' => [
            'ruta' => 'contacto',
            'datos' => [
                // Lo que pone el controlador de la página.
                'answers', 'topics',
                // Lo que pone el composer global, en toda vista. ⚠️ Se va con el menú de hechos.
                ...self::DEL_COMPOSER,
            ],
        ],
        // `/normas` (`#655`): el tablero por momento, la escala de altura resuelta y si se ofrece el descargo.
        'normas' => [
            'ruta' => 'normas',
            'datos' => ['board', 'scale', 'waiverEnabled', ...self::DEL_COMPOSER],
        ],
        // `/bar` (`#655`): lo que el panel publica del bar; `partyUrl` sale del inventario (o es `null`).
        'bar' => [
            'ruta' => 'bar',
            'datos' => [
                'barName', 'barLede', 'barPhoto', 'barPhotoCaption', 'barMenu', 'barFreeEntry', 'partyUrl',
                ...self::DEL_COMPOSER,
            ],
        ],
        // `/atracciones` (`#657`): las zonas de la landing que TIENEN atracciones activas —cada una con
        // las suyas cargadas y en el orden del panel—, el recuento de lo que la página enseña y la zona
        // que llega elegida por `?zona=`, ya saneada. La vista no vuelve a decidir ninguna de las tres.
        'atracciones' => [
            'ruta' => 'atracciones',
            'datos' => ['zones', 'total', 'active', ...self::DEL_COMPOSER],
        ],
        // `/precios` (`#658`): TODO llega compuesto por `RateTable` —la tabla por zona con sus filas, la
        // semana dibujada, las dos etiquetas de columna, el rótulo de la tarifa especial y los días
        // llanos— más el calendario de fechas especiales y el QR del registro externo, si lo hay. La
        // landing no calcula un precio: los escribe el producto, incluido el «antes» tachado.
        'precios' => [
            'ruta' => 'precios',
            'datos' => [
                'rateTable', 'week', 'colNormal', 'colSpecial', 'specialLabel', 'plainDays', 'holidays',
                'registrationSvg',
                ...self::DEL_COMPOSER,
            ],
        ],
        // `/cumpleanos` (`#659`): los packs de la superficie (de ellos sale la `<meta description>`), la
        // ZONA de la que la página publica la foto, la comparativa ya compuesta —columnas, filas, el
        // contador y sus totales por número de niños—, lo que pide el post-form y los grupos de elección
        // del menú. `compare` y `form` son `null` cuando no hay packs vendibles: vacío es una respuesta.
        'cumpleanos' => [
            'ruta' => 'cumpleanos',
            'datos' => ['packages', 'zone', 'compare', 'form', 'choices', ...self::DEL_COMPOSER],
        ],
        // `/servicios` (`#660`): las secciones editoriales del panel, sus tablas de grupo ya compuestas,
        // el «desde» de cada una YA ESCRITO por el producto, los rótulos de columna y los packs de
        // cumpleaños en corto. La landing no elige qué precio anuncia ni cómo se escribe.
        'servicios' => [
            'ruta' => 'servicios',
            'datos' => ['services', 'groupRates', 'groupFrom', 'rateColumns', 'birthdayCards', ...self::DEL_COMPOSER],
        ],
        /*
         * **LA PORTADA** (`#666`), y es el contrato más grande de todos: VEINTE claves del
         * controlador más las siete del composer. Las ocho secciones que pinta reciben cada una lo
         * suyo ya compuesto —las tarjetas de zona con su escala de altura y los dos extremos del
         * eje, el mosaico y su recuento, la puerta del bar, las tarifas con su «desde» ya escrito,
         * los packs, las dudas, la prueba social en sus tres estados y la entradilla del horario—.
         *
         * ⚠️⚠️ **La landing NO calcula ninguno de estos datos, y ése es el contrato**: ni compone la
         * escala de estatura, ni elige qué precio anuncia, ni escribe un importe, ni resuelve el
         * horario. La portada de una instancia que quiera otra cosa **pinta distinto**, no calcula
         * distinto.
         * ❗ **`menuSections` es la única que vuelve hacia el armazón**: son las anclas que el menú y
         * el pie de las DOCE vistas anuncian de ESTA página, y por eso el anfitrión mínimo las pinta
         * todas. Un ancla a una sección que no está no falla —el navegador se queda donde estaba—,
         * así que nadie lo vería.
         * ⚠️ `noindex` es del producto y no de la página: es cierto en las tres puertas de auth
         * (`/registro`, `/login`, `/recuperar-contrasena`), que sirven esta misma vista.
         */
        'portada' => [
            'ruta' => 'home',
            'datos' => [
                'zoneCards', 'zoneAxisCeiling', 'zoneAxisFloor',
                'rideMosaic', 'ridesTotal', 'barName', 'barLede',
                'rateCards', 'ratesFrom', 'ratesSpecialLabel',
                'partyCards', 'partyFrom',
                'faqs', 'menuSections',
                'socialProof', 'socialRating', 'socialLocked',
                'guestWaiverOffered', 'noindex', 'scheduleLede',
                ...self::DEL_COMPOSER,
            ],
        ],
        // Los cinco legales (`#655`) comparten vista y contrato: la página del panel. Se mide con una.
        'legal' => [
            'ruta' => 'legal.privacidad',
            'datos' => ['page', ...self::DEL_COMPOSER],
        ],
    ];

    /**
     * **Material del producto que hoy solo pinta una vista de la INSTANCIA** (F5 · T2b, `#655`).
     *
     * ⚠️⚠️ **Es la deuda de la vía B hecha lista.** El CSS de la landing y las ranuras del kit siguen en el
     * producto (spec §4.4: en la T2 solo se mudan las VISTAS), pero su consumidor ya no está en
     * `resources/views`, así que las guardas de huérfanos —`FacadeCssHasNoOrphansTest`, `ZonesSectionTest`—
     * lo darían por muerto y pedirían retirarlo. Retirarlo rompería la landing de la instancia sin que
     * fallara nada aquí. Se declara, con la vista que lo consume, y las dos guardas lo excluyen; y las dos
     * exigen además que ninguna vista del PRODUCTO lo pinte, o la entrada sobra.
     *
     * ▶ **El día que el material se mude con las vistas (T3–T5), esta lista se vacía.** Una entrada sin
     * sujeto en el paquete es deuda que hay que ver.
     *
     * @var array<string, string> pieza (clase CSS de la fachada, o ranura del kit) => quién la consume
     */
    public const MATERIAL_CONSUMIDO_POR_LA_INSTANCIA = [
        'grain--fade' => 'web/normas.blade.php · la trama que se apaga, en la cabecera de /normas (`#655`)',
        // ⚠️ `.trio` y `.trio-stand` siguen teniendo consumidor en el producto (la sección 04 de la
        // portada), así que solo el MODIFICADOR de la página se queda sin sujeto: el trío de
        // `/cumpleanos` va a la derecha del titular y a escala 0,8, y eso solo lo pide esa página.
        'trio--page' => 'web/cumpleanos.blade.php · la colocación del trío en la página (`#659`)',
        // ⚠️⚠️ Aquí la familia ENTERA se queda sin sujeto en el producto: la cinta `C3` solo la pintaba
        // `/servicios`, y se va con ella (`#660`). Se declaran las CINCO clases, no la familia: las
        // guardas de huérfanos miran clase a clase, y con solo el bloque sus cuatro hijos seguían
        // saliendo huérfanos (medido: el test los nombró uno a uno).
        'brand-band' => 'web/servicios.blade.php · la cinta C3, la única pantalla que la pinta (`#660`)',
        'brand-band__inner' => 'web/servicios.blade.php · el recorte de la cinta C3 (`#660`)',
        'brand-band__track' => 'web/servicios.blade.php · el carril que desplaza la cinta C3 (`#660`)',
        'brand-band__item' => 'web/servicios.blade.php · cada título dentro de la cinta C3 (`#660`)',
        'brand-band__dot' => 'web/servicios.blade.php · el punto separador de la cinta C3 (`#660`)',
        'slot-ico-altura' => 'web/normas.blade.php · el icono de «La altura, de un vistazo» (`#655`)',
        'slot-ico-saltador' => 'web/normas.blade.php · el icono del grupo «Mientras saltas» (`#655`)',
        // ⚠️⚠️ **LA PORTADA** (`#666`). De las 208 clases que se quedaron sin consumidor al mudarla
        // (`#665`), las guardas del producto ven exactamente CUATRO piezas: el modificador del trío
        // —`.trio` y `.trio-stand` las emite el COMPONENTE, que es del producto, así que conservan
        // su sujeto— y las tres ranuras del kit que solo pintaba la portada. Las otras 207 son CSS y
        // se van con la **T2c** (`instancia-y-landing-fuera.md` §4.6): esta lista mira pieza a pieza
        // lo que alguien declaró, no la hoja entera.
        'trio--events' => 'web/portada.blade.php · el trío junto al titular de la sección 04 (`#666`)',
        'slot-dudas' => 'web/portada.blade.php · la mancha del lockup de «Dudas» (`#666`)',
        'slot-resenas' => 'web/portada.blade.php · la mancha detrás de la tarjeta de opinión (`#666`)',
        'slot-ico-calcetines' => 'web/portada.blade.php · el icono del aviso de los calcetines (`#666`)',
    ];

    /**
     * Lo que el composer global (`View::composer('*')`) pone en TODA vista. ⚠️ Se va con el menú de hechos
     * (`instancia-y-landing-fuera.md` §1.1), y ese día sube el MAYOR: por eso está escrito una sola vez.
     *
     * ❗❗ **PÚBLICA desde `#666`, y no por comodidad de un test: porque marca una frontera real.**
     * `CONTRATO_DE_VISTAS` mezcla **dos contratos** —lo que pone el CONTROLADOR, que es de la página y
     * tiene que pintarlo ella, y esto, que lo reparte el composer a TODA vista y lo consumen el layout, el
     * nav y el pie, o sea el ARMAZÓN—. La guarda que exige que un anfitrión consuma su contrato entero
     * (`AnfitrionPortadaTest`) nació roja con estas seis claves, y ninguna era un olvido: *exigirle a una
     * página que pinte lo que pinta su marco es pedirle que lo pinte dos veces.*
     *
     * @var list<string>
     */
    public const DEL_COMPOSER = [
        'site', 'heroStatus', 'offers', 'ctaMinPriceCents', 'ctaMinPriceLabel', 'cookieBannerEnabled', 'cookieConsent',
    ];

    public function __construct(private readonly ViewFactory $vistas) {}

    /**
     * Registra el namespace si hay un paquete VÁLIDO. Se llama una vez, al arrancar.
     *
     * Devuelve la ruta registrada, o `null` si no se registró nada — que es el caso normal en
     * desarrollo y en una instalación sin landing propia todavía.
     */
    public function registrar(): ?string
    {
        $raiz = self::rutaDelPaquete();

        if ($raiz === null) {
            return null;
        }

        $web = $raiz.DIRECTORY_SEPARATOR.self::SUBCARPETA;

        if (! is_dir($web)) {
            // ⚠️ Se avisa pero NO se revienta: un paquete a medio desplegar deja la web con el
            // anfitrión mínimo, que es feo pero está en pie. Reventar aquí tumbaría también
            // `/admin` y `/api/v1`, que no tienen nada que ver con la landing.
            Log::warning('instancia: el paquete no tiene carpeta de vistas', ['web' => $web]);

            return null;
        }

        $this->avisarSiElContratoNoCuadra($raiz);

        $this->vistas->addNamespace(self::NAMESPACE, $web);

        return $web;
    }

    /**
     * **El contrato del paquete contra el del producto: avisa, NO tumba** (spec §4.3).
     *
     * ⚠️⚠️ Y esto es deliberado, no una tibieza: negarse a servir una landing porque su manifiesto dice `1`
     * y el producto va por `2` deja la web del cliente EN BLANCO por un número, justo después de un
     * despliegue y sin que el visitante tenga la culpa de nada. El aviso va al log, donde lo ve quien puede
     * arreglarlo; la web sigue en pie mientras tanto.
     *
     * ⚠️ Un manifiesto ausente o ilegible tampoco tumba nada: hay paquetes hechos a mano y eso no es un
     * error, es una instalación que todavía no lo declara.
     */
    private function avisarSiElContratoNoCuadra(string $raiz): void
    {
        $fichero = $raiz.DIRECTORY_SEPARATOR.self::MANIFIESTO;

        if (! is_file($fichero)) {
            return;
        }

        $manifiesto = json_decode((string) file_get_contents($fichero), true);

        if (! is_array($manifiesto) || ! isset($manifiesto['contrato'])) {
            return;
        }

        if ((int) $manifiesto['contrato'] !== self::CONTRATO) {
            Log::warning('instancia: el paquete está hecho para otra versión del producto', [
                'paquete' => (int) $manifiesto['contrato'],
                'producto' => self::CONTRATO,
            ]);
        }
    }

    /**
     * El nombre de vista a renderizar: la de la instancia si existe, y si no el respaldo del producto.
     *
     * Se usa así, y la decisión de qué respaldo toca es del llamante porque solo él sabe qué página
     * es equivalente a la suya:
     *
     *     return view($this->vistas->pick('contacto', 'anfitrion.contacto'), [...]);   (`ContactController`)
     *
     * ⚠️ El respaldo es el ANFITRIÓN MÍNIMO del producto (`resources/views/anfitrion/`), no la landing de
     * un cliente: desde `#654` la de PlayJump vive en su paquete, y lo que queda aquí funciona sin arte.
     */
    public function pick(string $deLaInstancia, string $respaldo): string
    {
        $nombre = self::NAMESPACE.'::'.$deLaInstancia;

        return $this->vistas->exists($nombre) ? $nombre : $respaldo;
    }

    /**
     * **La ruta del paquete, leída SOLO de configuración** (`SEC-12`).
     *
     * ⚠️⚠️ Estática y sin parámetros **a propósito**: así no hay forma de pasarle nada. Una firma que
     * aceptara una ruta invitaría a que algún día alguien le pasara `$request->input(...)`, y eso
     * sería ejecución remota de código con un `?paquete=`. Lo que no se puede pasar no se puede
     * colar.
     *
     * Las tres comprobaciones son el invariante, no cortesía:
     *  1. **absoluta** — una relativa se resolvería contra el cwd del proceso, distinto en `artisan`,
     *     en php-fpm y en la cola: tres landings según quién renderice;
     *  2. **existe y es directorio** — si no, no hay nada que registrar;
     *  3. **FUERA DEL ÁRBOL DEL PRODUCTO** (`base_path()`), que es la que de verdad muerde y cubre dos
     *     peligros de golpe:
     *     · bajo `public/` el servidor web entregaría el `.blade.php` **en crudo**, con lo que lleve
     *       dentro — y `public/` está dentro del árbol, así que esta comprobación ya lo prohíbe;
     *     · en cualquier otro sitio del árbol, el `rsync --delete` del despliegue **se lo lleva**: la
     *       instalación se quedaría sin landing en el siguiente despliegue y nadie sabría por qué.
     *     ▶ Y es lo coherente con el diseño: el paquete es OTRO repo (`instancia-<slug>`), así que no
     *     tiene nada que hacer dentro de éste.
     */
    public static function rutaDelPaquete(): ?string
    {
        $bruta = config('instancia.ruta');

        if (! is_string($bruta) || trim($bruta) === '') {
            return null;
        }

        $ruta = rtrim(trim($bruta), DIRECTORY_SEPARATOR);

        if (! str_starts_with($ruta, DIRECTORY_SEPARATOR)) {
            Log::error('instancia: la ruta del paquete no es absoluta y se ignora', ['ruta' => $ruta]);

            return null;
        }

        $real = realpath($ruta);

        if ($real === false || ! is_dir($real)) {
            return null;
        }

        if (self::dentroDelProducto($real)) {
            Log::error(
                'instancia: la ruta del paquete está DENTRO del árbol del producto y se ignora (SEC-12)',
                ['ruta' => $real],
            );

            return null;
        }

        return $real;
    }

    /**
     * ¿Cuelga esta ruta del árbol del producto?
     *
     * ⚠️ Se compara con el separador pegado (`/var/www/html/`) y no con el prefijo a secas: sin él,
     * `/var/www/html-instancia` daría positivo por empezar igual, y una carpeta legítima quedaría
     * descartada. Es el mismo cuidado que pide cualquier comparación de prefijos de ruta.
     */
    private static function dentroDelProducto(string $real): bool
    {
        $arbol = realpath(base_path());

        if ($arbol === false) {
            return false;
        }

        return $real === $arbol
            || str_starts_with($real.DIRECTORY_SEPARATOR, $arbol.DIRECTORY_SEPARATOR);
    }
}
