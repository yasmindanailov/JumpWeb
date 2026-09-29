<?php

namespace Tests\Feature;

use App\Domain\Booking\Models\Order;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\Setting;
use App\Http\Instancia\InstanceViews;
use App\Notifications\OrderConfirmation;
use App\Notifications\Support\BrandedMailMessage;
use App\Notifications\Support\MailTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\HtmlString;
use Tests\TestCase;

/**
 * El VESTIDO de los correos del molde: la plantilla del diseño (la R1a, `specs/correos-rediseno.md` §4.1.1), pintada con
 * los ROLES de `MailTheme` —neutros en el producto, redefinidos por la hoja de la instancia— y el oscuro por CLASE.
 *
 * ⚠️⚠️ **ESTE TEST CEMENTÓ UNA VEZ LA PALETA DEL PRIMER CLIENTE** (hasta el 2026-09-10 aseveraba `Space Grotesk`, un radio
 * de 14 y un blanco que dejaba el botón a 3,10). Una guarda que asevera literales protege la implementación, no la regla.
 * ▶ Vigila PROPIEDADES, las de septiembre sobre el motor nuevo —ninguna fuente web, nada de la paleta de origen, los radios
 * en la escala, el oscuro en el HTML enviado, todo texto AA en los dos modos sobre su fondo EFECTIVO, cada bloque con su
 * gemela de texto, la marca del NEGOCIO, el botón AA por la aritmética— y tres nuevas: **ningún color escrito a mano en
 * las plantillas**, **con una hoja de instancia cada color sale de ella**, y **todo texto con color tiene su clase de
 * oscuro** (el oscuro va por clase: un color en línea sin clase se queda claro en un correo oscuro).
 */
class MailThemeTest extends TestCase
{
    use RefreshDatabase;

    /** Los cuatro valores del tema del PRIMER cliente. Ninguno puede volver (`#FF5B22`, la marca por defecto, aparte). */
    private const PALETA_DE_ORIGEN = ['#ECE5D2', '#FBF7EC', '#6B675D'];

    /** Ninguna de estas carga en un correo: nombrarlas es fingir que se ven. Las tres últimas, las del diseño de PlayJump. */
    private const WEBFONTS = ['Space Grotesk', 'Bricolage Grotesque', 'Bungee', 'Hanken Grotesk', 'JetBrains Mono', 'Archivo', 'Figtree', 'DM Mono'];

    /** Las fuentes de la plantilla: sus vistas y las dos piezas de marcado que pinta (el libro, la ficha de producto). */
    private function plantillas(): array
    {
        $ficheros = array_merge(
            glob(resource_path('views/correo/*.blade.php')) ?: [],
            glob(resource_path('views/correo/html/*.blade.php')) ?: [],
            [resource_path('views/emails/partials/book.blade.php'), resource_path('views/emails/partials/product-card.blade.php')],
        );
        $this->assertGreaterThan(10, count($ficheros), 'el escaneo ve muy pocas plantillas: ¿han cambiado de sitio?');

        return $ficheros;
    }

    /** El fuente sin comentarios de Blade ni de PHP: un `#503` en un comentario no es un color. */
    private function sinComentarios(string $f): string
    {
        return (string) preg_replace(['/\{\{--.*?--\}\}/s', '#/\*.*?\*/#s', '#^\s*//.*$#m'], '', (string) file_get_contents($f));
    }

    private ?string $paquete = null;

    private string $carpeta = '';

    private bool $publicoExistia = true;

    protected function setUp(): void
    {
        parent::setUp();
        MailTheme::olvidar();
    }

    protected function tearDown(): void
    {
        if ($this->paquete !== null) {
            File::deleteDirectory($this->paquete);
            File::deleteDirectory(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta));
            if (! $this->publicoExistia) {
                File::deleteDirectory(public_path(InstanceViews::PUBLICO));
            }
        }
        MailTheme::olvidar();
        parent::tearDown();
    }

    /**
     * Un `public/` TEMPORAL para que el caso no dependa de lo que haya instalado en la máquina: `client-logo@4x.png` está
     * gitignorado (es del cliente), y con él presente la cabecera cambia de rama (la trampa de `#302`).
     */
    private function publicDir(bool $withClientLogo): void
    {
        $dir = sys_get_temp_dir().'/mail-theme-'.uniqid();
        mkdir($dir.'/img', 0777, true);
        if ($withClientLogo) {
            file_put_contents($dir.'/img/client-logo@4x.png', 'png-falso');
        }
        $this->app->usePublicPath($dir);
    }

    /** Una hoja de correo de instancia (`hojas.correo`), en un paquete falso fuera del árbol, como `InstanceSheetsTest`. */
    private function conHoja(string $css): void
    {
        $this->paquete = sys_get_temp_dir().'/instancia-tema-'.getmypid().'-'.uniqid();
        File::ensureDirectoryExists($this->paquete.'/'.InstanceViews::SUBCARPETA);
        $this->publicoExistia = is_dir(public_path(InstanceViews::PUBLICO));
        $this->carpeta = 'prueba-tema-'.getmypid();
        File::ensureDirectoryExists(public_path(InstanceViews::PUBLICO.'/'.$this->carpeta));
        File::put(public_path(InstanceViews::PUBLICO."/{$this->carpeta}/correo.css"), $css);
        File::put($this->paquete.'/'.InstanceViews::MANIFIESTO, (string) json_encode([
            'contrato' => InstanceViews::CONTRATO, 'hojas' => ['correo' => ["{$this->carpeta}/correo.css"]],
        ]));
        config(['instancia.ruta' => $this->paquete]);
        MailTheme::olvidar();
    }

    private function withBusiness(string $name): void
    {
        Setting::create(['key' => 'business.name', 'value' => $name, 'group' => 'business']);
        Setting::flushMemo();
    }

    /** Un correo REAL renderizado por el camino que se envía. */
    private function renderConfirmation(string $code = 'R-MAIL01'): string
    {
        $user = User::factory()->create(['locale' => 'es']);
        $order = Order::create([
            'user_id' => $user->id, 'code' => $code, 'status' => Order::STATUS_PENDING,
            'subtotal' => 1500, 'total' => 1500, 'currency' => 'EUR',
        ]);

        return (string) (new OrderConfirmation($order))->toMail($user)->render();
    }

    /**
     * Un correo con TODOS los bloques de la R1a en un tono: cabecera con chapa, resguardo, texto con negrita, un marcado
     * con enlace (el de las bajas), aviso, botón, cierre y el pie entero. Con él, el recorrido ve cada estado.
     */
    private function renderCompleto(string $tono): string
    {
        foreach (['address.line1' => 'Calle Uno, 2', 'contact.phone' => '600 00 00 00', 'contact.whatsapp' => '34600000000', 'contact.email' => 'hola@demo.test'] as $k => $v) {
            Setting::updateOrCreate(['key' => $k], ['value' => $v, 'group' => 'contact']);
        }
        Setting::flushMemo();

        return (string) (new BrandedMailMessage)
            ->hero('emails.order_declined', $tono, ['Cuándo' => 'Sáb 26 · 17:00', 'Pedido' => 'R-1'])
            ->line('**Uno** dos.')
            ->line(new HtmlString('Porque sí. <a href="https://x.test/baja">Baja</a>'))
            ->notice('Ojo', 'Esto importa.', $tono)
            ->action('Ver', 'https://example.test/x')
            ->line('Cierre.')
            ->render();
    }

    /**
     * El mapa del oscuro que viaja en el `<style>`: clase → propiedad → valor, de la `@media`.
     *
     * @return array<string, array<string, string>>
     */
    private function mapaOscuro(string $html): array
    {
        $this->assertSame(1, preg_match('/@media \(prefers-color-scheme:dark\)\{(.*?)\}\n/s', $html, $m), 'no se encuentra el bloque del oscuro');
        preg_match_all('/\.(pjm-[a-z0-9-]+)\{([a-z-]+):(#[0-9A-F]{6})!important\}/', $m[1], $reglas, PREG_SET_ORDER);
        $mapa = [];
        foreach ($reglas as [, $clase, $prop, $valor]) {
            $mapa[$clase][$prop] = $valor;
        }

        return $mapa;
    }

    /**
     * Cada texto con su color y su fondo EFECTIVO, en claro y en oscuro. ⚠️ La pila importa: el fondo de un texto es el
     * de su ancestro más cercano que declare uno, no el del `<body>`. En oscuro, un color o un fondo cambian SOLO si el
     * elemento lleva una clase del mapa; si lo declara en línea sin clase, se queda el claro (y eso es lo que se caza).
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: string}> etiqueta, color claro, fondo claro, color oscuro, fondo oscuro
     */
    private function pares(string $html): array
    {
        $mapa = $this->mapaOscuro($html);
        $cuerpo = (string) preg_replace('/<!--.*?-->/s', '', substr($html, (int) strpos($html, '<body')));
        preg_match_all('/<(\/?)(\w+)([^>]*)>/', $cuerpo, $m, PREG_SET_ORDER);

        $clasesDe = static fn (string $attrs): array => preg_match('/class="([^"]*)"/', $attrs, $c) === 1 ? preg_split('/\s+/', trim($c[1])) : [];
        $delMapa = static function (array $clases, string $prop) use ($mapa): ?string {
            foreach ($clases as $c) {
                if (isset($mapa[$c][$prop])) {
                    return $mapa[$c][$prop];
                }
            }

            return null;
        };

        $pila = [['#FFFFFF', '#FFFFFF']];
        $abiertos = [];
        $pares = [];
        foreach ($m as [, $cierre, $tag, $attrs]) {
            if ($cierre !== '') {
                if ($abiertos !== [] && end($abiertos) === $tag) {
                    array_pop($abiertos);
                    array_pop($pila);
                }

                continue;
            }
            $clases = $clasesDe($attrs);
            [$fondoClaro, $fondoOscuro] = end($pila);
            $propio = preg_match('/background(?:-color)?:\s*(#[0-9A-Fa-f]{6})/', $attrs, $b) === 1 ? strtoupper($b[1])
                : (preg_match('/bgcolor="(#[0-9A-Fa-f]{6})"/', $attrs, $b) === 1 ? strtoupper($b[1]) : null);
            if ($propio !== null) {
                $fondoClaro = $propio;
                $fondoOscuro = $delMapa($clases, 'background-color') ?? $propio;
            }
            if (preg_match('/(?<![-\w])color:\s*(#[0-9A-Fa-f]{6})/', $attrs, $f) === 1) {
                $claro = strtoupper($f[1]);
                $pares[] = [$tag.'.'.implode('.', $clases), $claro, $fondoClaro, $delMapa($clases, 'color') ?? $claro, $fondoOscuro];
            }
            if (! in_array(strtolower($tag), ['br', 'img', 'meta', 'link', 'hr', 'input'], true)) {
                $pila[] = [$fondoClaro, $fondoOscuro];
                $abiertos[] = $tag;
            }
        }

        return $pares;
    }

    private function contrast(string $a, string $b): float
    {
        $lum = static function (string $hex): float {
            $c = array_map(static function (string $pair): float {
                $v = hexdec($pair) / 255;

                return $v <= 0.03928 ? $v / 12.92 : ((($v + 0.055) / 1.055) ** 2.4);
            }, str_split(ltrim($hex, '#'), 2));

            return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
        };
        [$x, $y] = [$lum($a), $lum($b)];

        return round((max($x, $y) + 0.05) / (min($x, $y) + 0.05), 2);
    }

    // ── El motor ─────────────────────────────────────────────────────────────────────────────────

    /** El tema de Markdown sigue activo: lo usan los dos avisos internos (`Mail/`) hasta la R1c. */
    public function test_brand_theme_is_active_in_mail_config(): void
    {
        $this->assertSame('brand', config('mail.markdown.theme'));
    }

    public function test_the_mold_paints_with_the_template_and_not_with_markdown(): void
    {
        $this->publicDir(withClientLogo: false);
        $html = $this->renderConfirmation();

        $this->assertStringContainsString('data-bloque="cabecera"', $html);
        $this->assertStringContainsString('data-bloque="pie"', $html);
        $this->assertStringNotContainsString('class="wrapper"', $html, 'el layout Markdown de septiembre sigue pintando');
        foreach (self::PALETA_DE_ORIGEN as $hex) {
            $this->assertStringNotContainsString($hex, $html, "la paleta de origen ha vuelto: {$hex}");
        }
    }

    // ── Los colores son roles ────────────────────────────────────────────────────────────────────

    /**
     * ❗❗ **NINGÚN COLOR ESCRITO A MANO EN LAS PLANTILLAS**: todos salen de `MailTheme`. Un hex en una vista es un color que
     * la hoja de la instancia no puede cambiar, o sea la marca de alguien quemada en el producto (`DECISIONES #1`).
     */
    public function test_no_colour_is_written_in_the_templates(): void
    {
        $sueltos = [];
        foreach ($this->plantillas() as $f) {
            if (preg_match_all('/(?<!&)#(?:[0-9A-Fa-f]{6}|[0-9A-Fa-f]{3})\b/', $this->sinComentarios($f), $m) > 0) {
                $sueltos[] = basename(dirname($f)).'/'.basename($f).': '.implode(', ', array_unique($m[0]));
            }
        }

        $this->assertSame([], $sueltos, "colores escritos a mano en la plantilla:\n  ".implode("\n  ", $sueltos));
    }

    /**
     * ❗❗ **CON UNA HOJA DE INSTANCIA, CADA COLOR DEL CORREO SALE DE ELLA.** Todos los roles a un valor distinto: lo que
     * se pinte en línea que no sea de la hoja es un color del producto que se cuela en la marca del cliente.
     */
    public function test_with_an_instance_sheet_every_colour_in_the_mail_comes_from_it(): void
    {
        $valores = [];
        $css = '';
        foreach ([...array_keys(MailTheme::COLORES), 'accion', 'accion-letra', 'franja-1', 'franja-2', 'franja-3', 'franja-4'] as $i => $rol) {
            $valores[] = $hex = sprintf('#%02X2A%02X', 0x10 + $i, 0x80 + $i);
            $css .= "--correo-{$rol}:{$hex};";
        }
        // ⚠️ Primero el `public/` temporal y después la hoja DENTRO de él: al revés, la hoja se escribe en el `public/` de
        // antes y el correo sale neutro (medido al escribir este caso: nueve colores del producto «colados»).
        $this->publicDir(withClientLogo: false);
        $this->conHoja(":root{{$css}}");

        $html = $this->renderCompleto('warn');
        $cuerpo = substr($html, (int) strpos($html, '<body'));
        preg_match_all('/#[0-9A-Fa-f]{6}\b/', $cuerpo, $m);

        $ajenos = array_values(array_diff(array_unique(array_map('strtoupper', $m[0])), $valores));
        $this->assertNotEmpty($m[0], 'CONTROL: el escaneo no ve ningún color');
        $this->assertSame([], $ajenos, 'colores que NO salen de la hoja de la instancia: '.implode(', ', $ajenos));
        foreach (['#102A80', '#152A85'] as $rol) {   // el fondo (rol 0) y el sutil (rol 5) de la hoja, pintados
            $this->assertStringContainsString($rol, $cuerpo);
        }
    }

    /**
     * ❗❗❗ **TODO TEXTO CON COLOR LLEVA SU CLASE DE OSCURO.** El oscuro de la plantilla va por CLASE (el `!important` del
     * `<style>` gana al color en línea solo si la etiqueta tiene la clase); un color en línea sin clase se queda claro en un
     * correo oscuro. Se comprueba sobre las FUENTES: un recorrido mide los estados por los que pasa, la guarda estática
     * los lee todos (la lección del libro invisible, `#503`).
     */
    public function test_every_coloured_text_in_the_templates_has_its_dark_class(): void
    {
        $this->publicDir(withClientLogo: false);
        $mapa = $this->mapaOscuro($this->renderCompleto('info'));
        $conColor = array_keys(array_filter($mapa, static fn (array $p): bool => isset($p['color'])));
        $this->assertContains('pjm-strong', $conColor, 'CONTROL: el mapa no se lee');

        $sinClase = [];
        $conTy = 0;
        foreach ($this->plantillas() as $f) {
            // ⚠️⚠️ Las expresiones de Blade se NEUTRALIZAN antes de partir etiquetas: el `>` de `$correo->ty(…)` cortaba los
            // atributos en `[^>]*`, y toda etiqueta estilada con `ty()` —casi todas— se escapaba de esta guarda sin avisar.
            // Lo destapó el arnés (29-09): el `alt` del logotipo sin su clase SOBREVIVÍA.
            $src = (string) preg_replace_callback(
                '/\{\{.*?\}\}|\{!!.*?!!\}/s',
                static fn (array $e): string => strtr($e[0], ['->' => '::', '=>' => '=:']),
                $this->sinComentarios($f),
            );
            preg_match_all('/<(\w+)\b([^>]*)>/s', $src, $etiquetas, PREG_SET_ORDER);
            foreach ($etiquetas as [$todo, $tag, $attrs]) {
                $pintaTexto = preg_match('/style="[^"]*(?:(?<![-\w])color:|::ty\()/s', $attrs) === 1;
                $conTy += (int) str_contains($attrs, '::ty(');
                if (! $pintaTexto) {
                    continue;
                }
                $clase = preg_match('/class="([^"]*)"/', $attrs, $c) === 1 ? $c[1] : '';
                $estaticas = preg_split('/\s+/', trim((string) preg_replace('/\{\{.*?\}\}/s', ' ', $clase))) ?: [];
                $ok = array_intersect($estaticas, $conColor) !== []
                    || preg_match('/pjm-tono-\{\{[^}]+\}\}-t/', $clase) === 1                    // la letra del tono
                    || $this->dinamicaDeRol($clase, $src, $conColor);
                if (! $ok) {
                    $sinClase[] = basename($f).': <'.$tag.' class="'.$clase.'">';
                }
            }
        }

        $this->assertGreaterThan(15, $conTy, 'CONTROL: el escaneo no ve las etiquetas estiladas con `ty()`');
        $this->assertSame([], $sinClase, "textos con color SIN clase de oscuro:\n  ".implode("\n  ", $sinClase));
    }

    /**
     * Una clase escrita con una expresión es de rol si TODAS las clases entre comillas que la expresión (o la variable
     * que nombra, en el mismo fichero) puede dar están en el mapa. La del saldo del libro sale de un `match`.
     *
     * @param  list<string>  $conColor
     */
    private function dinamicaDeRol(string $clase, string $src, array $conColor): bool
    {
        if (preg_match('/^\{\{\s*(.*?)\s*\}\}$/s', trim($clase), $e) !== 1) {
            return false;
        }
        $expr = $e[1];
        if (preg_match('/^\$(\w+)$/', $expr, $v) === 1) {
            // La variable: su asignación en el fichero (`[$color, $clase] = match (…) { … };` o `$clase = …;`).
            if (preg_match('/\$'.$v[1].'\]?\s*=\s*(.*?);\s*$/ms', $src, $asig) !== 1) {
                return false;
            }
            $expr = $asig[1];
        }
        preg_match_all("/'(pjm-[a-z0-9-]+)'/", $expr, $q);

        return $q[1] !== [] && array_diff($q[1], $conColor) === [];
    }

    /** El oscuro llega al HTML ENVIADO, y llega dos veces: `prefers-color-scheme` y los atributos de Outlook.com. */
    public function test_dark_mode_reaches_the_sent_html(): void
    {
        $this->publicDir(withClientLogo: false);
        $html = $this->renderConfirmation();

        $this->assertStringContainsString('content="light dark"', $html);
        $this->assertStringContainsString('@media (prefers-color-scheme:dark){', $html);
        $mapa = $this->mapaOscuro($html);
        $this->assertSame('#101418', $mapa['pjm-bg']['background-color'] ?? null, 'el fondo oscuro neutro');

        foreach ($mapa as $clase => $props) {
            foreach ($props as $prop => $valor) {
                if ($prop === 'border-color') {
                    continue;   // Outlook.com no los reescribe
                }
                $attr = $prop === 'color' ? 'data-ogsc' : 'data-ogsb';
                $this->assertStringContainsString("[{$attr}] .{$clase}{{$prop}:{$valor}!important}", $html, "falta la regla de Outlook.com de .{$clase}");
            }
        }
    }

    /**
     * ❗❗❗ **EL CONTRASTE SE MIDE, EN LOS DOS MODOS, SOBRE EL FONDO EFECTIVO**, y en los cinco tonos. El contraste lo
     * hacen los dos lados: el aviso de septiembre subía su texto en oscuro y dejaba el tinte claro, 1,28 : 1 (lo vio el
     * owner, no el test). Y la letra de la chapa del diseño no tenía clase: en el oscuro automático conservaba el color
     * claro sobre el fondo oscuro del tono.
     */
    public function test_every_text_reads_in_both_modes_in_every_tone(): void
    {
        $flojos = [];
        $vistos = 0;
        $alt = false;
        // Los cinco tonos, y el primero también con el LOGOTIPO de la instalación: su `alt` se pinta con las imágenes
        // bloqueadas y en oscuro tiene que leerse (el arnés lo vio sobrevivir: sin logotipo, este recorrido no lo pinta).
        foreach ([['ok', false], ['warn', false], ['err', false], ['info', false], ['neutro', false], ['ok', true]] as [$tono, $logo]) {
            $this->publicDir(withClientLogo: $logo);
            foreach ($this->pares($this->renderCompleto($tono)) as [$donde, $fc, $bc, $fo, $bo]) {
                $vistos++;
                $alt = $alt || str_starts_with($donde, 'img');
                foreach ([['claro', $fc, $bc], ['oscuro', $fo, $bo]] as [$modo, $f, $b]) {
                    if (($r = $this->contrast($f, $b)) < 4.5) {
                        $flojos[] = "{$tono} · <{$donde}> {$f} sobre {$b} en {$modo} → {$r}";
                    }
                }
            }
        }

        $this->assertGreaterThan(60, $vistos, 'el recorrido ve muy pocos textos: ¿ha cambiado la plantilla?');
        $this->assertTrue($alt, 'CONTROL: el recorrido no ve el `alt` del logotipo');
        $this->assertSame([], array_values(array_unique($flojos)), "textos por debajo de AA:\n  ".implode("\n  ", array_unique($flojos)));
    }

    /**
     * La escala de canto del producto es CERRADA (`0 · 10 · 16 · 999`); sin hoja, el correo solo usa esos. Con la hoja de
     * una instancia, los suyos: el radio es un rol y no un número de la plantilla.
     */
    public function test_every_radius_is_a_role(): void
    {
        $this->publicDir(withClientLogo: false);
        $radios = static function (string $html): array {
            preg_match_all('/border-radius:\s*(\d+)px/i', $html, $m);

            return array_values(array_unique(array_map('intval', $m[1])));
        };

        $neutro = $radios($this->renderCompleto('ok'));
        $this->assertNotEmpty($neutro, 'el escáner no ve ningún radio');
        $this->assertSame([], array_values(array_diff($neutro, [0, 10, 16, 999])), 'radios fuera de la escala: '.implode(', ', $neutro));

        $this->conHoja(':root{--correo-radio-md:14px;--correo-radio-lg:20px;}');
        $conHoja = $radios($this->renderCompleto('ok'));
        $this->assertSame([], array_values(array_diff($conHoja, [14, 20, 999])), 'radios que no son de la hoja: '.implode(', ', $conHoja));
        $this->assertContains(14, $conHoja);
        $this->assertContains(20, $conHoja);
    }

    // ── Lo demás de septiembre, sobre el motor nuevo ─────────────────────────────────────────────

    public function test_no_webfont_travels_in_a_mail(): void
    {
        $this->publicDir(withClientLogo: false);
        $html = $this->renderCompleto('ok');

        foreach (self::WEBFONTS as $fuente) {
            $this->assertStringNotContainsString($fuente, $html, "«{$fuente}» no carga en un correo: nombrarla es fingir que se ve");
        }
        $this->assertStringNotContainsString('fonts.googleapis', $html, 'cada apertura avisaría a Google de la IP de quien lee');
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringContainsString('Arial', $html, 'la pila de sistema tiene que estar');
    }

    /**
     * ❗ **TODO BLOQUE NACE POR PARTIDA DOBLE**, y su ausencia no se ve al renderizar: `render()` solo produce el HTML y el
     * ENVÍO revienta con «View not found» (pagado el 2026-09-10 con `hero` y `notice`). Y las gemelas no escapan: la
     * versión de texto se pinta tal cual, y `{{ }}` escribiría `&amp;` donde el cliente lee «&».
     */
    public function test_every_block_has_its_plain_text_twin_that_does_not_escape(): void
    {
        $html = glob(resource_path('views/correo/html/*.blade.php')) ?: [];
        $this->assertGreaterThanOrEqual(8, count($html), 'el escaneo no ve los bloques: ¿han cambiado de sitio?');

        $huerfanos = $escapan = [];
        foreach ($html as $f) {
            $gemela = resource_path('views/correo/texto/'.basename($f));
            if (! is_file($gemela)) {
                $huerfanos[] = basename($f);
            } elseif (str_contains((string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($gemela)), '{{')) {
                $escapan[] = basename($gemela);
            }
        }

        $this->assertSame([], $huerfanos, 'bloques SIN gemela de texto: '.implode(', ', $huerfanos));
        $this->assertSame([], $escapan, 'gemelas de texto que escapan con {{ }}: '.implode(', ', $escapan));
    }

    /**
     * ❗❗ LAS SUPERFICIES DE MARCA DICEN EL NEGOCIO, NO EL PRODUCTO (`DECISIONES #1`): la cabecera y el pie, en el HTML y en
     * el texto. Medido el 2026-09-10: la cabecera decía «SaltoPark» y la firma y el copyright «JumpWeb».
     */
    public function test_the_brand_surfaces_say_the_business_and_never_the_product(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation();

        $this->assertMatchesRegularExpression('#data-bloque="cabecera".*?>Negocio Demo</a>#s', $html, 'la cabecera nombra al negocio');
        $this->assertMatchesRegularExpression('#data-bloque="pie".*?>Negocio Demo</p>#s', $html, 'el pie nombra al negocio');
        $this->assertStringNotContainsString(config('app.name'), $html, 'el nombre del PRODUCTO no puede aparecer en un correo que lee un cliente');
    }

    /**
     * ❗❗ **EL BOTÓN PRINCIPAL VA EN EL ROL DE ACCIÓN Y SU RÓTULO CUMPLE AA, POR LA ARITMÉTICA** (`#803`: como el diseño).
     * Aseverar un hex es lo que dejó pasar el blanco sobre naranja a 3,10. Con varios colores de acción del panel, y en los
     * dos modos (el oscuro reescribe el relleno y la letra por su clase).
     */
    public function test_the_main_button_takes_the_action_role_and_its_label_passes_aa(): void
    {
        $this->publicDir(withClientLogo: false);

        foreach (['#FFD400', '#0A0B0C', '#FF6A13', '#1E7A3C', '#7FD4EF'] as $accion) {
            Setting::updateOrCreate(['key' => 'theme.action'], ['value' => $accion, 'group' => 'theme']);
            Setting::flushMemo();

            $html = $this->renderCompleto('info');
            $this->assertSame(1, preg_match('/class="pjm-btn"[^>]*background:(#[0-9A-F]{6});/', $html, $f), "{$accion}: no se encuentra el botón");
            $this->assertSame(1, preg_match('/class="pjm-btn-t"[^>]*color:(#[0-9A-F]{6});/', $html, $l));
            $this->assertSame($accion, $f[1], 'el botón no toma el color de acción de la instalación');
            $this->assertGreaterThanOrEqual(4.5, $r = $this->contrast($f[1], $l[1]), "{$l[1]} sobre {$f[1]} da {$r}");

            $mapa = $this->mapaOscuro($html);
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($mapa['pjm-btn']['background-color'], $mapa['pjm-btn-t']['color']), "{$accion}, en oscuro");
            $this->assertSame(1, substr_count($html, 'class="pjm-btn"'), 'un solo principal por correo');
        }
    }

    /** Con el LOGOTIPO de la instalación, la cabecera lo enseña con el nombre del negocio como `alt` (`#325`). */
    public function test_email_header_uses_the_client_logo_when_the_installation_has_it(): void
    {
        $this->publicDir(withClientLogo: true);
        $this->withBusiness('Negocio Demo');

        $html = $this->renderConfirmation('R-MAIL03');

        $this->assertStringContainsString('img/client-logo@4x.png', $html);
        $this->assertStringContainsString('alt="Negocio Demo"', $html);
        $this->assertDoesNotMatchRegularExpression('#data-bloque="cabecera".*?>Negocio Demo</a>#s', $html, 'con logotipo no se pinta además el nombre');
    }

    public function test_email_wordmark_falls_back_to_app_name_without_business_name(): void
    {
        $this->publicDir(withClientLogo: false);

        $html = $this->renderConfirmation('R-MAIL02');

        $this->assertStringContainsString('>'.config('app.name').'</a>', $html);
    }

    /**
     * ⚠️⚠️ **La clave EXISTE y está VACÍA**: `Setting::value($k, $default)` solo cae al defecto cuando falta, y un operador
     * que borre el campo en el panel llega a este estado. Lo cierra `Setting::businessName()`: ni la cabecera ni el pie se
     * quedan sin nombre.
     */
    public function test_an_emptied_business_name_falls_back_instead_of_signing_with_nothing(): void
    {
        $this->publicDir(withClientLogo: false);
        $this->withBusiness('');

        $html = $this->renderConfirmation('R-MAIL04');

        $this->assertStringContainsString('>'.config('app.name').'</a>', $html);
        $this->assertStringContainsString('>'.config('app.name').'</p>', $html);
        $this->assertStringNotContainsString('></a>', $html, 'un enlace sin texto: el nombre vacío llegó al correo');
    }
}
