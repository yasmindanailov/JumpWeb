<?php

namespace App\Notifications\Support;

use App\Domain\Content\Services\ThemeSettings;
use App\Http\Instancia\InstanceViews;
use Illuminate\Support\Facades\Log;

/**
 * **LOS ROLES del correo** (`specs/correos-rediseno.md` §4.1.1, la R1a): los colores y los radios con los que se pinta
 * cada correo, en claro y en oscuro, por su FUNCIÓN —fondo, texto fuerte, cuerpo, apagado, filete, acción…— y no por su
 * valor. Es el `tema()` de la plantilla del diseño, en el servidor.
 *
 * ▶ **Neutros en el producto, y la instancia los redefine en su hoja** (`hojas.correo` de `instancia.json`, leída con
 * `InstanceViews::hojas('correo')`: la puerta de `#769`, sin contrato nuevo). Un correo no lee `var()`: el producto
 * lee la hoja AQUÍ y escribe cada valor en línea. Sin hoja, el correo sale con los neutros y entero.
 *
 * La hoja, como la pidió plataforma (29-09): solo `:root`, nombres PLANOS —`--correo-fuerte` y su pareja
 * `--correo-fuerte-oscuro`— y como mucho UNA indirección `var(--correo-…)` dentro de las mismas hojas. Cada valor se
 * VALIDA antes de escribirse en línea (un color `#rgb`/`#rrggbb`, un radio en px); lo que no cuadra se queda fuera con
 * aviso en el log y el rol conserva su neutro. Un `@media` no se lee: un lector de una indirección no lo entiende.
 *
 * ⚠️ **Las fuentes NO son roles**: el correo no carga fuentes web (cada apertura avisaría a Google de la IP de quien lo
 * lee, y Gmail las ignora), y nombrar una familia que no se carga es fingir que se ve (`MailThemeTest`). Lo mismo hace
 * la plantilla del diseño con `fuentes` apagado: solo las pilas de sistema, que viven en {@see self::FUENTES}.
 *
 * ⚠️ **La ACCIÓN sale del panel**: el color de acción de la instalación (`ThemeSettings::action()`) y, si no lo
 * declara, su marca (`brand()`, el del botón de siempre). Su letra la calcula
 * `ThemeSettings::onAction()` para que dé AA sobre cualquier color que se elija. Si la hoja declara `--correo-accion`
 * y no su letra, la letra se CALCULA igual: un rol a medias no puede dejar el botón ilegible.
 */
final class MailTheme
{
    /** El prefijo de los roles en la hoja: `--correo-fuerte`, `--correo-fuerte-oscuro`. */
    public const PREFIJO = '--correo-';

    /** El sufijo de la pareja oscura. */
    public const OSCURO = '-oscuro';

    /**
     * Los roles de COLOR, con su neutro en claro y en oscuro. Los valores son del producto (las familias de estado de
     * `fiesta.css` y las superficies del molde de septiembre); los alfas del sistema sobre tinta, resueltos en sólido
     * sobre `#101418`, porque en un correo no hay `rgba` fiable (el `sobre()` del diseño).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const COLORES = [
        'fondo' => ['#FFFFFF', '#101418'],
        'fuerte' => ['#101418', '#FFFFFF'],
        'cuerpo' => ['#3B434B', '#E2E3E3'],      // blanco al 88 % sobre tinta
        'apagado' => ['#626A72', '#BCBDBE'],     // blanco al 72 %
        'filete' => ['#D6D8D4', '#363A3D'],      // blanco al 16 %
        'sutil' => ['#F4F4F1', '#212428'],       // blanco al 7 %
        'enlace' => ['#101418', '#7FD4EF'],
        'enlace-hover' => ['#626A72', '#FFFFFF'],
        'ok-fondo' => ['#E2F4E7', '#25312E'],    // el oscuro, su letra al 14 % sobre tinta
        'ok-letra' => ['#1E7A3C', '#A6E3B3'],
        'error-fondo' => ['#FDE4E6', '#31272C'],
        'error-letra' => ['#B3261E', '#FF9AA6'],
        'aviso-fondo' => ['#FDF2D9', '#312E1F'],
        'aviso-letra' => ['#101418', '#FFCF4D'],
        'info-fondo' => ['#ECEFF2', '#202F36'],
        'info-letra' => ['#101418', '#7FD4EF'],
        // El de los ICONOS (la R1b, §4.1.3): uno para los dos modos, porque una imagen no cambia con el oscuro; a ≥ 3:1
        // (WCAG 1.4.11) contra el fondo y el sutil en claro y en oscuro. Medido: el apagado da 2,84 sobre el sutil oscuro;
        // éste, ≥ 3,63 en los cuatro. Su pareja oscura no se usa.
        'icono' => ['#737B83', '#737B83'],
    ];

    /** Los radios, en px: los de la escala del producto (`0 · 10 · 16 · 999`). La píldora no es un rol. */
    public const RADIOS = ['md' => 10, 'lg' => 16];

    public const PILDORA = 999;

    /** Las franjas de la cabecera (la del parque, cuatro colores). Sin hoja, ninguna: la franja es de la MARCA. */
    public const FRANJAS = 4;

    /** Las pilas de sistema de la plantilla del diseño (`tema()` con `fuentes` apagado). */
    public const FUENTES = [
        'titular' => "'Arial Black','Helvetica Neue',Helvetica,Arial,sans-serif",
        'texto' => "'Helvetica Neue',Helvetica,Arial,sans-serif",
        // La del código de un solo uso (la R1c, el `codigo()` del diseño): cifras de ancho fijo, que se leen y se copian sin
        // confundir un 1 con una l.
        'mono' => "ui-monospace,Menlo,Consolas,'Courier New',monospace",
    ];

    /** @var array<string, self> una por firma de hojas: ruta + `filemtime` */
    private static array $memo = [];

    /**
     * @param  array<string, string>  $claro
     * @param  array<string, string>  $oscuro
     * @param  array<string, int>  $radios
     * @param  list<string>  $franja
     */
    private function __construct(
        private readonly array $claro,
        private readonly array $oscuro,
        private readonly array $radios,
        private readonly array $franja,
    ) {}

    /**
     * El tema de ESTA instalación. Se lee la hoja una vez por firma (sus rutas y su `filemtime`): un cambio de la hoja
     * cambia la firma y un proceso largo —la cola— la vuelve a leer; la acción del panel se lee siempre.
     */
    public static function current(): self
    {
        $hojas = array_values(array_filter(
            array_map(static fn (string $ruta): string => public_path($ruta), InstanceViews::hojas('correo')),
            'is_file',
        ));
        $firma = implode('|', array_map(static fn (string $f): string => $f.'@'.(int) filemtime($f), $hojas));
        $leido = self::$memo[$firma] ??= self::leer($hojas);

        return $leido->conAccion(ThemeSettings::action() ?? ThemeSettings::brand());
    }

    /**
     * El tema a partir de unas hojas CSS (rutas absolutas) y el color de acción del panel, sin memoria: para las pruebas
     * y para quien ya tiene las hojas en la mano.
     *
     * @param  list<string>  $hojas
     */
    public static function desdeHojas(array $hojas, string $marca): self
    {
        return self::leer($hojas)->conAccion($marca);
    }

    /** Olvida lo leído (pruebas). */
    public static function olvidar(): void
    {
        self::$memo = [];
    }

    /**
     * Lo que dicen las hojas, sin la acción del panel (que se lee siempre, fuera de la memoria).
     *
     * @param  list<string>  $hojas
     */
    private static function leer(array $hojas): self
    {
        $declarados = self::declarados($hojas);
        $claro = array_map(static fn (array $par): string => $par[0], self::COLORES);
        $oscuro = array_map(static fn (array $par): string => $par[1], self::COLORES);
        $radios = self::RADIOS;
        $franja = [];

        foreach ($declarados as $nombre => $valor) {
            if (str_ends_with($nombre, self::OSCURO) && isset(self::COLORES[$rol = substr($nombre, 0, -strlen(self::OSCURO))])) {
                self::color($nombre, $valor, static function (string $hex) use (&$oscuro, $rol): void {
                    $oscuro[$rol] = $hex;
                });
            } elseif (isset(self::COLORES[$nombre])) {
                self::color($nombre, $valor, static function (string $hex) use (&$claro, $nombre): void {
                    $claro[$nombre] = $hex;
                });
            } elseif (in_array($nombre, ['accion', 'accion-letra', 'accion'.self::OSCURO, 'accion-letra'.self::OSCURO], true)) {
                self::color($nombre, $valor, static function (string $hex) use (&$claro, &$oscuro, $nombre): void {
                    $oscura = str_ends_with($nombre, self::OSCURO);
                    $rol = $oscura ? substr($nombre, 0, -strlen(self::OSCURO)) : $nombre;
                    if ($oscura) {
                        $oscuro[$rol] = $hex;
                    } else {
                        $claro[$rol] = $hex;
                    }
                });
            } elseif (preg_match('/^franja-([1-9])$/', $nombre, $n) === 1 && (int) $n[1] <= self::FRANJAS) {
                self::color($nombre, $valor, static function (string $hex) use (&$franja, $n): void {
                    $franja[(int) $n[1]] = $hex;
                });
            } elseif (preg_match('/^radio-(md|lg)$/', $nombre, $r) === 1) {
                if (preg_match('/^(\d{1,3})px$/', $valor, $px) === 1) {
                    $radios[$r[1]] = (int) $px[1];
                } else {
                    self::descartar($nombre, $valor, 'un radio es un entero en px');
                }
            } else {
                self::descartar($nombre, $valor, 'no es un rol del correo');
            }
        }

        ksort($franja);

        return new self($claro, $oscuro, $radios, count($franja) === self::FRANJAS ? array_values($franja) : []);
    }

    /** El color de un rol en claro. */
    public function claro(string $rol): string
    {
        return $this->claro[$rol] ?? throw new \InvalidArgumentException("El correo no tiene el rol «{$rol}».");
    }

    /** El color de un rol en oscuro. */
    public function oscuro(string $rol): string
    {
        return $this->oscuro[$rol] ?? throw new \InvalidArgumentException("El correo no tiene el rol «{$rol}».");
    }

    /** Un radio, en px. */
    public function radio(string $rol): int
    {
        return $rol === 'pildora' ? self::PILDORA : ($this->radios[$rol] ?? throw new \InvalidArgumentException("El correo no tiene el radio «{$rol}»."));
    }

    /** @return list<string> la franja de la cabecera: cuatro colores, o ninguno */
    public function franja(): array
    {
        return $this->franja;
    }

    /** La pila de una familia (`titular` o `texto`). */
    public function fuente(string $rol): string
    {
        return self::FUENTES[$rol] ?? throw new \InvalidArgumentException("El correo no tiene la fuente «{$rol}».");
    }

    /**
     * Con la acción del PANEL donde la hoja no la declara, y su letra calculada donde falte: la hoja manda si la pone.
     */
    private function conAccion(string $marca): self
    {
        $claro = $this->claro;
        $oscuro = $this->oscuro;
        $claro['accion'] ??= self::hex($marca) ?? '#101418';
        $claro['accion-letra'] ??= ThemeSettings::onAction($claro['accion']);
        $oscuro['accion'] ??= $claro['accion'];
        $oscuro['accion-letra'] ??= ThemeSettings::onAction($oscuro['accion']);

        return new self($claro, $oscuro, $this->radios, $this->franja);
    }

    /**
     * Las declaraciones `--correo-*` del `:root` de cada hoja, en orden (la última gana), con UNA indirección
     * `var(--correo-x)` resuelta contra lo declarado. Sin el prefijo.
     *
     * @param  list<string>  $hojas
     * @return array<string, string>
     */
    private static function declarados(array $hojas): array
    {
        $crudos = [];
        foreach ($hojas as $hoja) {
            $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($hoja));
            // Solo los `:root` de primer nivel: lo que va dentro de un `@media` no es de este lector.
            $css = (string) preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/s', '', $css);
            preg_match_all('/(?<![\w-]):root\s*\{([^{}]*)\}/', $css, $raices);
            foreach ($raices[1] as $cuerpo) {
                preg_match_all('/'.preg_quote(self::PREFIJO, '/').'([a-z0-9-]+)\s*:\s*([^;]+);?/', $cuerpo, $m, PREG_SET_ORDER);
                foreach ($m as [, $nombre, $valor]) {
                    $crudos[$nombre] = trim($valor);
                }
            }
        }

        $resueltos = [];
        foreach ($crudos as $nombre => $valor) {
            if (preg_match('/^var\(\s*'.preg_quote(self::PREFIJO, '/').'([a-z0-9-]+)\s*\)$/', $valor, $v) === 1) {
                $destino = $crudos[$v[1]] ?? null;
                if ($destino === null || str_starts_with($destino, 'var(')) {
                    self::descartar($nombre, $valor, 'la indirección no llega a un valor en una sola vuelta');

                    continue;
                }
                $valor = $destino;
            }
            $resueltos[$nombre] = $valor;
        }

        return $resueltos;
    }

    /** @param  callable(string): void  $guardar */
    private static function color(string $nombre, string $valor, callable $guardar): void
    {
        $hex = self::hex($valor);
        if ($hex === null) {
            self::descartar($nombre, $valor, 'un color es #rgb o #rrggbb');

            return;
        }
        $guardar($hex);
    }

    /** `#abc` o `#aabbcc`, normalizado a `#AABBCC`; cualquier otra cosa, `null`. */
    public static function hex(string $valor): ?string
    {
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', trim($valor), $m) !== 1) {
            return null;
        }
        $h = $m[1];
        if (strlen($h) === 3) {
            $h = $h[0].$h[0].$h[1].$h[1].$h[2].$h[2];
        }

        return '#'.strtoupper($h);
    }

    private static function descartar(string $nombre, string $valor, string $porque): void
    {
        Log::warning('correo: un valor de la hoja no vale y se queda fuera', [
            'rol' => self::PREFIJO.$nombre, 'valor' => mb_substr($valor, 0, 80), 'porque' => $porque,
        ]);
    }
}
