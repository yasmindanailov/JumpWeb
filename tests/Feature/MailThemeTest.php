<?php

namespace Tests\Feature;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El vestido de los 23 correos — `themes/brand.css` + las cinco plantillas que lo acompañan.
 *
 * ⚠️⚠️ **ESTE TEST CEMENTABA LA PALETA DEL PRIMER CLIENTE.** Hasta el 2026-09-10 aseveraba
 * `Space Grotesk`, `border-radius: 14px` y `color: #FFFFFF` como «la identidad visual» — y los
 * tres eran del tema de origen: la fuente no llega a ningún correo, el 14 no es un escalón de la
 * escala de cuatro y el blanco dejaba el botón a **3,10** de contraste. Una guarda que asevera
 * literales protege la implementación, no la regla; al cambiar el sistema se pone roja **con el
 * producto sano**, que es exactamente lo que pasó.
 * ▶ Lo que vigila ahora son PROPIEDADES: que ninguna webfont viaje, que no sobreviva la paleta
 * de origen, que los radios estén en la escala, que el modo oscuro llegue, que la marca sea la
 * del NEGOCIO y que el rótulo del botón **cumpla AA con la aritmética, no con un hex escrito**.
 */
class MailThemeTest extends TestCase
{
    use RefreshDatabase;

    /** Los cuatro valores del tema del PRIMER cliente. Ninguno puede volver. */
    private const PALETA_DE_ORIGEN = ['#ECE5D2', '#FBF7EC', '#6B675D', '#FF5B22'];

    /** Ninguna de estas carga en un cliente de correo: declararlas era fingir que sí. */
    private const WEBFONTS = ['Space Grotesk', 'Bricolage Grotesque', 'Bungee', 'Hanken Grotesk', 'JetBrains Mono'];

    public function test_brand_theme_is_active_in_mail_config(): void
    {
        $this->assertSame('brand', config('mail.markdown.theme'));
    }

    /**
     * Un `public/` TEMPORAL para que el caso no dependa de lo que haya instalado en la máquina:
     * `client-logo@4x.png` está gitignorado (es del cliente), y con él presente la cabecera cambia
     * de rama. La trampa de `#302`: un test que lee un fichero gitignorado pasa aquí y falla en un
     * clon limpio — o al revés.
     */
    private function publicDir(bool $withClientLogo): string
    {
        $dir = sys_get_temp_dir().'/mail-theme-'.uniqid();
        mkdir($dir.'/img', 0777, true);
        if ($withClientLogo) {
            file_put_contents($dir.'/img/client-logo@4x.png', 'png-falso');
        }
        $this->app->usePublicPath($dir);

        return $dir;
    }

    /** Un correo real renderizado, que es lo único que demuestra qué recibe una persona. */
    private function renderConfirmation(string $code = 'R-MAIL01'): string
    {
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => $code,
            'status' => Order::STATUS_PENDING,
            'subtotal' => 1500, 'total' => 1500, 'currency' => 'EUR',
        ]);

        return (new OrderConfirmation($order))->toMail($user)->render();
    }

    private function withBusiness(string $name): void
    {
        Setting::create(['key' => 'business.name', 'value' => $name, 'group' => 'business']);
        Setting::flushMemo();
    }

    public function test_the_theme_carries_the_system_surfaces_and_not_the_first_clients(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        // Las superficies del sistema: Papel · Blanco · Tinta · Humo.
        foreach (['#F4F4F1', '#FFFFFF', '#101418', '#626A72'] as $hex) {
            $this->assertStringContainsString($hex, $html, "falta la superficie {$hex} del sistema");
        }

        // ⚠️ Y lo que de verdad protege este caso: que la paleta de origen NO vuelva.
        // `#FF5B22` es la excepción con nombre: NO es del tema, es el acento que
        // `ThemeSettings::brand()` inyecta, o sea el valor que la INSTALACIÓN elige. Sin paquete
        // de cliente instalado ése es el defecto del producto, así que aquí sale.
        foreach (array_diff(self::PALETA_DE_ORIGEN, ['#FF5B22']) as $hex) {
            $this->assertStringNotContainsString($hex, $html, "la paleta de origen ha vuelto: {$hex}");
        }
    }

    public function test_no_webfont_travels_in_a_mail(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        foreach (self::WEBFONTS as $fuente) {
            $this->assertStringNotContainsString(
                $fuente, $html,
                "«{$fuente}» no carga en un cliente de correo: declararla dibuja el correo con una ".
                'tipografía que el destinatario nunca ve'
            );
        }
        $this->assertStringContainsString('Arial', $html, 'el sustituto declarado tiene que estar');
    }

    /**
     * La escala de canto es CERRADA y tiene cuatro escalones (`0 · 10 · 16 · 999`). El correo usa
     * dos: tarjeta 16 y control 10. Los que había —14 en el botón, 14 en la tarjeta, 12 en el
     * libro y en la tarjeta de producto, 4 en el aviso— no son escalones de nada.
     */
    public function test_every_radius_is_on_the_four_step_scale(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        preg_match_all('/border-radius:\s*(\d+)px/i', $html, $m);
        $this->assertNotEmpty($m[1], 'el escáner no ve ningún radio: ¿ha cambiado el marcado?');

        $fuera = array_values(array_unique(array_diff(array_map('intval', $m[1]), [0, 10, 16, 999])));
        $this->assertSame([], $fuera, 'radios fuera de la escala de cuatro: '.implode(', ', $fuera));
    }

    /**
     * ❗ El modo oscuro tiene que llegar al HTML ENVIADO, no solo estar escrito. Se escribió
     * primero dentro de `themes/brand.css` y no llegaba: Laravel inlinea ese archivo con
     * `CssToInlineStyles`, que pega cada regla a su etiqueta y **descarta lo que no puede
     * inlinear** — y una media query no se puede inlinear. Salía un correo sin una sola
     * `@media (prefers-color-scheme)` y sin que fallara nada.
     */
    public function test_dark_mode_reaches_the_sent_html(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        $this->assertStringContainsString('prefers-color-scheme: dark', $html);
        $this->assertStringContainsString('content="light dark"', $html);
        $this->assertStringContainsString('#1A1F25', $html, 'falta la tarjeta en tinta del modo oscuro');
    }

    /**
     * ❗❗❗ **TODO COLOR DE TEXTO DEL CORREO TIENE SU PAR OSCURO, Y SE COMPRUEBA SOBRE LAS FUENTES.**
     *
     * ⚠️⚠️ Esta guarda nace de un defecto REAL que se coló en la T1 y que la suite no veía: el
     * primer bloque de modo oscuro seleccionaba por ETIQUETA y CLASE (`body, p, .table td, h1…`) y
     * **dejaba el LIBRO DEL PEDIDO INVISIBLE** — `emails/partials/book.blade.php` pinta su color
     * inline sobre `<td>` sin clase, que ningún selector de aquéllos alcanzaba. Medido sobre el
     * correo real: **11 elementos a 1,12 : 1**, o sea el desglose del dinero desaparecido entero.
     *
     * ▶ **Y por qué sobre las FUENTES y no sobre un correo renderizado**: un recorrido mide los
     * estados por los que pasa; una guarda estática los lee todos. El caso que ya existía renderiza
     * una confirmación **sin líneas**, así que no pinta ni el libro ni la tarjeta de producto — con
     * él, este defecto seguiría vivo y verde. La lección es de la tanda A del cajón.
     */
    public function test_every_light_text_colour_has_a_dark_counterpart(): void
    {
        $mapa = $this->mapaDelModoOscuro();
        $this->assertNotEmpty($mapa, 'no se encuentra el mapa de modo oscuro: ¿ha cambiado el layout?');

        $sinPar = [];
        foreach ($this->coloresDeTextoDeLasFuentes() as $hex => $donde) {
            if (isset(self::FUERA_DEL_MODO_OSCURO[$hex]) || isset($mapa[$hex])) {
                continue;
            }
            $sinPar[] = "{$hex} ({$donde})";
        }

        $this->assertSame([], $sinPar,
            "colores de texto SIN par oscuro — en modo oscuro quedan tinta sobre tinta:\n  ".
            implode("\n  ", $sinPar)."\n\n".
            'O se mapean en el bloque `@media (prefers-color-scheme: dark)` de `layout.blade.php`, '.
            'o entran en `FUERA_DEL_MODO_OSCURO` con su motivo escrito.');

        // Y el par tiene que LEERSE: no basta con que exista.
        foreach ($mapa as $claro => $oscuro) {
            $ratio = $this->contrast($oscuro, '#1A1F25');
            $this->assertGreaterThanOrEqual(4.5, $ratio,
                "el par oscuro de {$claro} es {$oscuro} y da {$ratio} sobre la tarjeta en tinta");
        }
    }

    /**
     * Los colores de texto que NO entran en el modo oscuro, cada uno con su motivo.
     * ⚠️ Esta lista **solo encoge**: si crece, alguien está declarando texto que en oscuro no se ve.
     */
    private const FUERA_DEL_MODO_OSCURO = [
        // El rótulo del botón: lo calcula `ThemeSettings::onAction()` contra el relleno de MARCA,
        // que es el mismo en los dos modos. Aclararlo lo rompería.
        '#FFFFFF' => 'texto del botón semántico, sobre su propio relleno',
        // El punto de marca es un `<span>` VACÍO con `background`: su `color` no pinta ningún texto.
        '#F2711C' => 'el punto de marca de la cabecera, que no tiene contenido',
        // La CABECERA del correo es una caja de TINTA: su contenido ya vive sobre fondo oscuro en
        // los DOS modos, así que no tiene par — invertirlo lo dejaría claro sobre claro. Lo mismo
        // el rótulo del botón de tinta.
        '#F4F4F1' => 'texto sobre la caja de tinta del hero y sobre el botón de tinta',
        '#9AA1A8' => 'las etiquetas del resguardo, dentro de la caja de tinta',
    ];

    /** El mapa `color claro → color oscuro` que declara el bloque del layout. */
    private function mapaDelModoOscuro(): array
    {
        $bloque = $this->bloqueDelModoOscuro();
        $mapa = [];
        // Cada regla: uno o más `[style*="color:#XXX"]` y un `color: #YYY !important`.
        preg_match_all('/((?:\[style\*="color:\s?#[0-9A-Fa-f]{6}"\][^{,]*,?\s*)+)\{[^}]*?color:\s*(#[0-9A-Fa-f]{6})/s',
            $bloque, $reglas, PREG_SET_ORDER);
        foreach ($reglas as $r) {
            preg_match_all('/#[0-9A-Fa-f]{6}/', $r[1], $claros);
            foreach ($claros[0] as $claro) {
                // La primera regla que mapea un color manda: las de después son los matices
                // (titulares, enlaces), que van sobre el mismo color claro.
                $mapa[mb_strtoupper($claro)] ??= mb_strtoupper($r[2]);
            }
        }

        return $mapa;
    }

    private function bloqueDelModoOscuro(): string
    {
        $layout = file_get_contents(resource_path('views/vendor/mail/html/layout.blade.php'));
        $i = mb_strpos($layout, '@media (prefers-color-scheme: dark)');
        if ($i === false) {
            return '';
        }

        return mb_substr($layout, $i, mb_strpos($layout, '</style>') - $i);
    }

    /**
     * Censo de la propiedad `color` en todas las fuentes del correo — el tema, sus plantillas y los
     * partials. ⚠️ El lookbehind evita `background-color` y `border-color`, que no son texto.
     */
    private function coloresDeTextoDeLasFuentes(): array
    {
        $ficheros = array_merge(
            [resource_path('views/vendor/mail/html/themes/brand.css')],
            glob(resource_path('views/vendor/mail/html/*.blade.php')),
            glob(resource_path('views/emails/*.blade.php')),
            glob(resource_path('views/emails/partials/*.blade.php')),
        );
        $this->assertGreaterThan(8, count($ficheros), 'el escaneo ve muy pocas fuentes: ¿han cambiado de sitio?');

        $vistos = [];
        foreach ($ficheros as $f) {
            $s = (string) file_get_contents($f);
            // El bloque de modo oscuro es el MAPA, no un uso: fuera del censo.
            $s = str_replace($this->bloqueDelModoOscuro(), '', $s);
            preg_match_all('/(?<![-\w])color:\s*(#[0-9A-Fa-f]{3,6})/', $s, $m);
            foreach ($m[1] as $hex) {
                $hex = mb_strtoupper($hex);
                // Expande la forma corta (#888) para poder compararla.
                if (mb_strlen($hex) === 4) {
                    $hex = '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
                }
                $vistos[$hex] ??= basename($f);
            }
        }

        return $vistos;
    }

    /**
     * ❗❗❗ **EL CONTRASTE SE MIDE, EN LOS DOS MODOS, SOBRE EL FONDO EFECTIVO.**
     *
     * ⚠️⚠️ Y esta guarda existe porque la anterior **estaba hecha a medias**: comprobaba que cada
     * color de TEXTO tuviera par oscuro, y el contraste lo hacen **los dos lados**. El aviso lleva
     * un tinte CLARO fijo, el mapa subía su texto a Papel 200, y en oscuro la caja entera quedaba en
     * **1,28 : 1** — texto claro sobre fondo claro. *Lo vio el owner, no el test.*
     *
     * ▶ Aquí se recorre el HTML con una PILA de fondos —el fondo de un texto es el de su ancestro
     * más cercano que declare uno, no el del `<body>`—, se aplica el mapa oscuro a los dos lados y
     * se exige AA en los dos modos.
     */
    public function test_every_text_reads_in_both_modes(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();
        $pares = $this->paresDeContraste($html);
        $this->assertGreaterThan(6, count($pares), 'el recorrido ve muy pocos textos: ¿ha cambiado el molde?');

        [$texto, $fondo] = $this->mapasDelModoOscuro();

        // El fondo del `<body>` es papel: el correo se abre sobre él, no sobre blanco.
        $flojos = [];
        foreach ($pares as [$tag, $cls, $fg, $bg]) {
            if (isset(self::SIN_TEXTO[$cls])) {
                continue;
            }
            foreach ([['claro', $fg, $bg], ['oscuro', $texto[$fg] ?? $fg, $fondo[$bg] ?? $bg]] as [$modo, $f, $b]) {
                $ratio = $this->contrast($f, $b);
                if ($ratio < 4.5) {
                    $flojos[] = "<{$tag} class=\"{$cls}\"> {$f} sobre {$b} en modo {$modo} → {$ratio}";
                }
            }
        }

        $this->assertSame([], array_values(array_unique($flojos)),
            "textos por debajo de AA:\n  ".implode("\n  ", array_unique($flojos)));
    }

    /** Elementos que declaran `color` y NO tienen texto: su contraste no existe. Solo encoge. */
    private const SIN_TEXTO = [
        'brand-dot' => 'el punto de marca de la cabecera es un span vacío con fondo',
    ];

    /**
     * Cada texto con su fondo EFECTIVO. ⚠️ La pila importa: el fondo de un `<td>` dentro del aviso
     * es el tinte del aviso, no el blanco de la tarjeta ni el papel del `<body>` — medir contra el
     * fondo de la página es exactamente cómo un texto ilegible pasa por bueno.
     *
     * @return array<int,array{0:string,1:string,2:string,3:string}>
     */
    private function paresDeContraste(string $html): array
    {
        $cuerpo = mb_substr($html, (int) mb_strpos($html, '<body'));
        preg_match_all('/<(\/?)(\w+)([^>]*)>/', $cuerpo, $m, PREG_SET_ORDER);

        $pila = ['#FFFFFF'];
        $abiertos = [];
        $pares = [];
        foreach ($m as [$todo, $cierre, $tag, $attrs]) {
            if ($cierre === '') {
                $bg = preg_match('/background(?:-color)?:\s*(#[0-9A-Fa-f]{6})/', $attrs, $b) ? mb_strtoupper($b[1]) : end($pila);
                if (! in_array($tag, ['br', 'img', 'meta', 'link'], true)) {
                    $pila[] = $bg;
                    $abiertos[] = $tag;
                }
                if (preg_match('/(?<![-\w])color:\s*(#[0-9A-Fa-f]{6})/', $attrs, $f)) {
                    preg_match('/class="([^"]*)"/', $attrs, $c);
                    $pares[] = [$tag, $c[1] ?? '', mb_strtoupper($f[1]), $bg];
                }
            } elseif ($abiertos !== [] && end($abiertos) === $tag) {
                array_pop($abiertos);
                array_pop($pila);
            }
        }

        return $pares;
    }

    /**
     * Los DOS mapas del bloque oscuro: el de texto y el de FONDO. Que existan los dos es la mitad
     * que faltaba.
     *
     * @return array{0:array<string,string>,1:array<string,string>}
     */
    private function mapasDelModoOscuro(): array
    {
        $bloque = $this->bloqueDelModoOscuro();
        $texto = $fondo = [];
        preg_match_all(
            '/((?:\[style\*="(?:background-)?color:\s?#[0-9A-Fa-f]{6}"\][^{,]*,?\s*)+)\{([^}]*)\}/s',
            $bloque, $reglas, PREG_SET_ORDER
        );
        foreach ($reglas as $r) {
            preg_match_all('/\[style\*="(background-)?color:\s?(#[0-9A-Fa-f]{6})"\]/', $r[1], $claves, PREG_SET_ORDER);
            $esFondo = preg_match('/background-color:\s*(#[0-9A-Fa-f]{6})/', $r[2], $vf);
            $esTexto = preg_match('/(?<![-\w])color:\s*(#[0-9A-Fa-f]{6})/', $r[2], $vt);
            foreach ($claves as $k) {
                $hex = mb_strtoupper($k[2]);
                if ($k[1] !== '' && $esFondo) {
                    $fondo[$hex] ??= mb_strtoupper($vf[1]);
                } elseif ($k[1] === '' && $esTexto) {
                    $texto[$hex] ??= mb_strtoupper($vt[1]);
                }
            }
        }

        return [$texto, $fondo];
    }

    /**
     * ❗❗❗ **TODO COMPONENTE DE CORREO NACE POR PARTIDA DOBLE: HTML Y TEXTO PLANO.**
     *
     * ⚠️⚠️ Y su ausencia **no se ve al renderizar**. Laravel compone el correo en las dos versiones
     * y busca cada componente bajo `mail::` en su propia carpeta; `->render()` solo produce el HTML,
     * así que un componente sin gemelo pasa todos los casos que renderizan **y revienta el ENVÍO**
     * con «View [x] not found». Pagado el 2026-09-10 con `hero` y `notice`: el correo se veía
     * perfecto en la sonda y no salía del servidor.
     */
    public function test_every_mail_component_has_its_plain_text_twin(): void
    {
        $html = glob(resource_path('views/vendor/mail/html/*.blade.php'));
        $this->assertNotEmpty($html, 'el escaneo no ve componentes: ¿han cambiado de sitio?');

        $huerfanos = [];
        foreach ($html as $f) {
            $nombre = basename($f);
            // El TEMA no es un componente y `themes/` es una carpeta aparte.
            if (! file_exists(resource_path('views/vendor/mail/text/'.$nombre))) {
                $huerfanos[] = $nombre;
            }
        }

        $this->assertSame([], $huerfanos,
            "componentes de correo SIN gemelo en texto plano:\n  ".implode("\n  ", $huerfanos)."\n\n".
            'El HTML sale bien y el ENVÍO falla con «View not found»: un caso que solo renderiza no lo ve.');
    }

    /**
     * ❗❗ LAS SUPERFICIES DE MARCA DICEN EL NEGOCIO, NO EL PRODUCTO (`DECISIONES #1`).
     * Medido el 2026-09-10: la cabecera decía «SaltoPark» y la firma y el copyright «JumpWeb»,
     * en el mismo correo y en 20 de los 21 que lee una persona.
     */
    public function test_the_brand_surfaces_say_the_business_and_never_the_product(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        // Cabecera · firma · copyright · título.
        $this->assertGreaterThanOrEqual(
            4, substr_count($html, 'Negocio Demo'),
            'el nombre del negocio tiene que estar en la cabecera, la firma, el copyright y el título'
        );
        $this->assertStringContainsString('© '.date('Y').' Negocio Demo', $html);
        $this->assertStringNotContainsString(
            config('app.name'), $html,
            'el nombre del PRODUCTO no puede aparecer en un correo que lee un cliente'
        );
    }

    /**
     * ❗❗ EL RÓTULO DEL BOTÓN CUMPLE AA, Y SE COMPRUEBA CON LA ARITMÉTICA. Aseverar el hex
     * («color: #FFFFFF») es lo que dejó pasar el defecto: el blanco sobre el naranja del producto
     * da **3,10**, que es el umbral de texto GRANDE, y este rótulo mide 15px/700 — le toca 4,5.
     * `onBrand()` prefiere blanco y decide con 3,0; `onAction()` elige el que más contraste dé.
     */
    public function test_the_button_label_passes_aa_over_whatever_brand_colour_is_set(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        // ⚠️ El relleno visual del botón se hace con los CUATRO bordes, no con `border-color` ni con
        // `padding`: Outlook no respeta el segundo dentro de un `<a>`.
        preg_match('/background-color:\s*(#[0-9A-Fa-f]{6});[^"]*?(?<![-\w])color:\s*(#[0-9A-Fa-f]{6})/', $html, $m);
        $this->assertNotEmpty($m, 'no se encuentra el botón principal con su color inyectado');

        [$fondo, $texto] = [$m[1], $m[2]];
        $this->assertGreaterThanOrEqual(
            4.5, $ratio = $this->contrast($fondo, $texto),
            "el rótulo del botón ({$texto} sobre {$fondo}) da {$ratio} y el umbral de texto normal es 4,5"
        );
    }

    private function contrast(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            $hex = ltrim($hex, '#');
            $c = array_map(static function (string $pair): float {
                $v = hexdec($pair) / 255;

                return $v <= 0.03928 ? $v / 12.92 : ((($v + 0.055) / 1.055) ** 2.4);
            }, str_split($hex, 2));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$lum($a), $lum($b)];

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }

    /**
     * **Con el LOGOTIPO de la instalación presente, la cabecera lo enseña** (lanzamiento 2026-09-01,
     * `#325`): `<img>` al PNG con el nombre del negocio como `alt` — quien bloquea imágenes sigue
     * viendo el wordmark — y ya no se pinta el punto de marca del texto.
     */
    public function test_email_header_uses_the_client_logo_when_the_installation_has_it(): void
    {
        $this->publicDir(withClientLogo: true);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation('R-MAIL03');

        $this->assertStringContainsString('img/client-logo@4x.png', $html);
        $this->assertStringContainsString('alt="Negocio Demo"', $html);
        $this->assertStringNotContainsString('brand-dot', $html);
    }

    public function test_email_wordmark_falls_back_to_app_name_without_business_name(): void
    {
        $this->publicDir(withClientLogo: false);

        // Instalación recién migrada sin settings: el header no puede quedar vacío.
        $html = $this->renderConfirmation('R-MAIL02');

        $this->assertStringContainsString(config('app.name'), $html);
    }

    /**
     * ⚠️⚠️ **Y EL HUECO QUE NO CUBRÍA EL CASO DE ARRIBA: la clave EXISTE y está VACÍA.**
     * `Setting::value($k, $default)` resuelve con `?? $default`, así que solo cae al defecto
     * cuando la clave **falta**; con la fila creada y el valor en blanco devolvía `''`, y seis
     * correos firmaban con el nombre vacío. Un operador que borre el campo en el panel llega
     * justo a este estado. Lo cierra `Setting::businessName()`.
     */
    public function test_an_emptied_business_name_falls_back_instead_of_signing_with_nothing(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('');

        $html = $this->renderConfirmation('R-MAIL04');

        $this->assertStringContainsString(config('app.name'), $html);
        $this->assertStringNotContainsString('Un saludo,<br>
</p>', $html, 'la firma no puede quedarse sin nombre');
    }
}
