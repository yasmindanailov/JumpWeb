<?php

namespace App\Http\Fiesta;

use App\Domain\Booking\Models\OrderItem;
use App\Domain\Booking\Models\PartyInvitation;
use App\Http\Instancia\InstanceViews;
use App\Http\Instancia\VariablesDeHoja;
use Carbon\CarbonImmutable;
use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * **LA IMAGEN DE LA INVITACIÓN AL COMPARTIR**, dibujada con GD para cada una (`#815`, la composición B del owner;
 * `fiesta-sistema-nuevo.md` §4.19): la banda del tema a sangre con su adorno y, encima, la tarjeta blanca con la chapa de
 * la edad, el nombre, la frase, el día con la hora y el logotipo. Es la miniatura del diseño (`InviteCard variant="thumb"`)
 * con los datos que ya salen en el `og:title`: nada más, y nunca una foto del menor.
 *
 * ▶ Producción no tiene Chromium ni Node: se DIBUJA. Con el kit de la instancia ({@see estilo}): sus TTF FIJOS
 * (`InstanceViews::fuentes('imagen')`: GD no elige el peso de una fuente variable) y sus colores (los roles de los temas,
 * resueltos con `hojas.fiesta` sobre los neutros del producto). Sin kit, `null`: la `og:image` de antes.
 *
 * ⚠️ Lo que GD no hace y aquí se hace a mano (medido con el prototipo el 01-10):
 *  · el INTERLETRAJE: glifo a glifo con su avance exacto ({@see avance}); sin los pares de kerning (GPOS), que GD no lee;
 *  · los BORDES suaves de los círculos: se dibuja al doble ({@see K}) y se reduce;
 *  · lo que la fuente no tiene (un emoji: «ð» y cajas) se quita ANTES ({@see limpiar}, con `CoberturaDeFuente`).
 * ⚠️ Determinista: lo mismo da los mismos bytes (la caché de la I2 depende de ello).
 */
final class ImagenInvitacion
{
    public const ANCHO = 1200;

    public const ALTO = 630;

    /** La versión del DIBUJO: entra en la huella de la imagen (I2), así que un cambio aquí la hace pedir otra vez. */
    public const VERSION = 1;

    /** Se dibuja al doble y se reduce: GD no suaviza los círculos, reducir sí. */
    private const K = 2;

    private const CALIDAD = 86;

    /** Los roles del kit que hacen falta para dibujar. */
    public const FUENTES = ['titular', 'texto', 'etiqueta'];

    /** La tarjeta blanca (en px de 1200 × 630). */
    private const TARJETA = ['x' => 110, 'y' => 150, 'ancho' => 980, 'alto' => 400, 'radio' => 32, 'margen' => 50];

    /** El confeti fijo del diseño (`InviteCard.jsx` → `BITS`): x e y en %, tamaño, forma y giro. */
    private const BITS = [[6, 22, 8, 'dot', 0], [14, 64, 12, 'bar', 28], [22, 30, 9, 'sq', 18], [31, 72, 7, 'dot', 0], [38, 18, 13, 'bar', -32],
        [46, 54, 8, 'sq', 40], [53, 26, 7, 'dot', 0], [60, 70, 12, 'bar', 64], [67, 36, 9, 'sq', -14], [74, 16, 8, 'dot', 0],
        [80, 60, 13, 'bar', -52], [87, 30, 8, 'sq', 30], [93, 68, 7, 'dot', 0], [10, 44, 6, 'dot', 0], [42, 82, 7, 'dot', 0],
        [70, 86, 6, 'sq', 12], [97, 12, 9, 'bar', 80], [27, 52, 6, 'dot', 0]];

    /** Las burbujas de sereno (`BUBBLES`): x e y en %, tamaño. */
    private const BURBUJAS = [[7, 34, 22], [18, 64, 12], [31, 22, 16], [45, 60, 26], [59, 24, 12], [71, 62, 18], [83, 20, 24], [93, 58, 10]];

    /** La escala de la tarjeta del diseño (560 px) a este lienzo, para el adorno. */
    private const ESCALA = 2.2;

    /**
     * El estilo de ESTA instalación para un tema, o `null` si la instancia no trae el kit entero (sus tres fuentes) o un
     * color del tema no llega a un hex: entonces la `og:image` de antes, sin imagen a medias.
     *
     * @return array{colores: array{banda: string, chip: string, chipLetra: string, acento: string, tinta: string, nieve: string, bits: list<string>}, decor: string, fuentes: array<string, string>, logo: ?string}|null
     */
    public static function estilo(string $tema): ?array
    {
        $fuentes = InstanceViews::fuentes('imagen');
        if (array_diff(self::FUENTES, array_keys($fuentes)) !== []) {
            return null;
        }

        $hojas = [resource_path('js/fiesta/fiesta.css'), ...array_map(public_path(...), InstanceViews::hojas('fiesta'))];
        $variables = VariablesDeHoja::de($hojas);
        $color = static function (string $rol) use ($variables): ?string {
            return preg_match('/^var\(--([a-z0-9-]+)\)$/', $rol, $v) === 1 ? VariablesDeHoja::hex($variables[$v[1]] ?? null) : VariablesDeHoja::hex($rol);
        };

        $t = Temas::de($tema);
        $colores = [
            'banda' => $color($t['band']), 'chip' => $color($t['chip']), 'chipLetra' => $color($t['chipFg']),
            'acento' => $color($t['accent']), 'tinta' => $color('var(--fiesta-tinta-900)'), 'nieve' => $color('var(--fiesta-nieve)'),
        ];
        $bits = array_map($color, $t['bits']);
        if (in_array(null, $colores, true) || in_array(null, $bits, true)) {
            Log::warning('imagen de la invitación: un color del tema no llega a un hex; sin imagen', ['tema' => $tema]);

            return null;
        }

        $logo = public_path('img/client-logo@4x.png');

        /** @var array{banda: string, chip: string, chipLetra: string, acento: string, tinta: string, nieve: string} $colores */
        return [
            'colores' => $colores + ['bits' => array_values(array_filter($bits))],
            'decor' => $t['decor'],
            'fuentes' => $fuentes,
            'logo' => is_file($logo) ? $logo : null,
        ];
    }

    /**
     * Lo que la imagen escribe de UNA invitación, en el idioma de la petición: el nombre y la edad de quien cumple, la frase
     * de la tarjeta y el día con la hora como los dice la página. `null` si no hay un nombre que la fuente del kit pueda
     * escribir (sin nombre, o solo emojis): entonces no hay imagen, y la `og:image` de antes.
     *
     * @param  array{fuentes: array<string, string>}  $estilo
     * @return array{nombre: string, edad: ?string, frase: string, cuando: string, unidad: string}|null
     */
    public static function datosDe(PartyInvitation $invitacion, OrderItem $reserva, array $estilo): ?array
    {
        $nombre = trim((string) $invitacion->honoree_name);
        if ($nombre === '' || self::limpiar($nombre, $estilo['fuentes']['titular']) === '') {
            return null;
        }

        $edad = $invitacion->honoree_age === null ? null : (string) (int) $invitacion->honoree_age;
        $fecha = $reserva->slot?->date;
        $dia = $fecha === null ? '' : Str::ucfirst(CarbonImmutable::instance($fecha)->locale(app()->getLocale())->isoFormat(__('fiesta.fecha.larga')));
        $hora = substr((string) ($reserva->slot->start_time ?? ''), 0, 5);

        return [
            'nombre' => $nombre,
            'edad' => $edad,
            'frase' => ltrim($edad === null ? (string) __('fiesta.invitacion.rest_sin_edad') : (string) __('fiesta.invitacion.rest', ['age' => $edad])),
            'cuando' => implode(' · ', array_filter([$dia, $hora], static fn (string $parte): bool => $parte !== '')),
            'unidad' => (string) __('fiesta.invitacion.unit'),
        ];
    }

    /**
     * La HUELLA de lo que se pinta (`#816`): cambia con los datos, el tema, el kit (sus ficheros) o la versión del dibujo, y
     * va en la URL de la `og:image` para que WhatsApp la vuelva a pedir cuando cambie. No lleva nada legible.
     *
     * @param  array{nombre: string, edad: ?string, frase: string, cuando: string, unidad: string}  $datos
     * @param  array{colores: array<string, mixed>, decor: string, fuentes: array<string, string>, logo: ?string}  $estilo
     */
    public static function huella(array $datos, array $estilo): string
    {
        $fichero = static fn (string $ruta): array => [basename($ruta), (int) @filesize($ruta), (int) @filemtime($ruta)];

        return substr(hash('sha256', (string) json_encode([
            self::VERSION, $datos, $estilo['colores'], $estilo['decor'],
            array_map($fichero, $estilo['fuentes']), $estilo['logo'] === null ? null : $fichero($estilo['logo']),
        ])), 0, 16);
    }

    /**
     * La imagen, en JPEG de 1200 × 630.
     *
     * @param  array{nombre: string, edad: ?string, frase: string, cuando: string, unidad: string}  $datos  la frase y el día ya
     *                                                                                                      compuestos en su idioma
     * @param  array{colores: array{banda: string, chip: string, chipLetra: string, acento: string, tinta: string, nieve: string, bits: list<string>}, decor: string, fuentes: array<string, string>, logo: ?string}  $estilo
     */
    public static function dibujar(array $datos, array $estilo): string
    {
        $f = $estilo['fuentes'];
        $c = $estilo['colores'];
        $nombre = self::limpiar($datos['nombre'], $f['titular']);
        if ($nombre === '') {
            throw new \InvalidArgumentException('La imagen de la invitación necesita un nombre que la fuente pueda escribir.');
        }
        $edad = $datos['edad'] !== null ? self::limpiar($datos['edad'], $f['titular']) : '';
        $frase = self::limpiar($datos['frase'], $f['titular']);
        $cuando = self::limpiar($datos['cuando'], $f['texto']);
        $unidad = self::limpiar(mb_strtoupper($datos['unidad']), $f['etiqueta']);

        $k = self::K;
        $im = self::lienzo(self::ANCHO * $k, self::ALTO * $k);

        // La banda a sangre con su adorno.
        imagefilledrectangle($im, 0, 0, self::ANCHO * $k, self::ALTO * $k, self::color($im, $c['banda']));
        self::adorno($im, $estilo['decor'], $c, self::ANCHO * $k, self::ALTO * $k, self::ESCALA * $k);

        // La tarjeta blanca.
        $t = self::TARJETA;
        self::rectRedondo($im, $t['x'] * $k, $t['y'] * $k, $t['ancho'] * $k, $t['alto'] * $k, $t['radio'] * $k, self::color($im, $c['nieve']));

        // El logotipo, arriba a la derecha de la tarjeta.
        if ($estilo['logo'] !== null) {
            self::logo($im, $estilo['logo'], ($t['x'] + $t['ancho'] - 250) * $k, ($t['y'] + 36) * $k, 200 * $k);
        }

        // La chapa de la edad, en el borde de arriba de la tarjeta.
        if ($edad !== '') {
            $cx = ($t['x'] + 110) * $k;
            $cy = $t['y'] * $k;
            $r = 72 * $k;
            $e = 2.25 * $k;
            self::circulo($im, $cx, $cy, $r + (int) round(4 * $e), self::color($im, $c['nieve']));
            self::circulo($im, $cx, $cy, $r, self::color($im, $c['chip']));
            $px = 32 * $e;
            self::texto($im, $f['titular'], $px, $cx - (int) round(self::ancho($f['titular'], $px, $edad) / 2), $cy + (int) round(4 * $e), $c['chipLetra'], $edad);
            $pu = 10.5 * $e;
            self::texto($im, $f['etiqueta'], $pu, $cx - (int) round(self::ancho($f['etiqueta'], $pu, $unidad, 0.06) / 2), $cy + (int) round(18 * $e), $c['chipLetra'], $unidad, 0.06);
        }

        // El nombre, la frase y el día, con su ajuste.
        $x = ($t['x'] + $t['margen']) * $k;
        $util = ($t['ancho'] - 2 * $t['margen']) * $k;
        $n = self::ajustarNombre($nombre, $f['titular'], $util / $k);
        foreach ($n['lineas'] as $i => $linea) {
            self::texto($im, $f['titular'], $n['px'] * $k, $x, (int) round($n['bases'][$i] * $k), $c['acento'], $linea, -0.035);
        }
        $ultima = end($n['bases']);
        $pxFrase = self::reducir($frase, $f['titular'], 40, 28, $util / $k, -0.02);
        self::texto($im, $f['titular'], $pxFrase * $k, $x, (int) round(($ultima + 57) * $k), $c['tinta'], $frase, -0.02);
        $pxCuando = self::reducir($cuando, $f['texto'], 32, 24, $util / $k);
        self::texto($im, $f['texto'], $pxCuando * $k, $x, (int) round(($ultima + 125) * $k), $c['tinta'], $cuando);

        $final = self::lienzo(self::ANCHO, self::ALTO);
        imagecopyresampled($final, $im, 0, 0, 0, 0, self::ANCHO, self::ALTO, self::ANCHO * $k, self::ALTO * $k);
        ob_start();
        imagejpeg($final, null, self::CALIDAD);

        return (string) ob_get_clean();
    }

    /**
     * El nombre en una línea (de 104 a 72 px) o, si no cabe, en DOS equilibradas por un espacio (de 72 a 48 px, la segunda
     * por debajo del logotipo); sin espacio por donde partir, una línea más pequeña (hasta 24 px). Nunca se corta.
     * Las líneas de base, en px de 1200 × 630.
     *
     * @return array{px: int, lineas: list<string>, bases: list<float>}
     */
    public static function ajustarNombre(string $nombre, string $fuente, float $util): array
    {
        for ($px = 104; $px >= 72; $px -= 2) {
            if (self::ancho($fuente, $px, $nombre, -0.035) <= $util) {
                return ['px' => $px, 'lineas' => [$nombre], 'bases' => [355.0]];
            }
        }

        $partes = self::partir($nombre, $fuente);
        if ($partes !== null) {
            for ($px = 72; $px >= 48; $px -= 2) {
                if (max(self::ancho($fuente, $px, $partes[0], -0.035), self::ancho($fuente, $px, $partes[1], -0.035)) <= $util) {
                    return ['px' => $px, 'lineas' => $partes, 'bases' => [380 - 0.95 * $px, 380.0]];
                }
            }
        }

        for ($px = 70; $px > 24; $px -= 2) {
            if (self::ancho($fuente, $px, $nombre, -0.035) <= $util) {
                return ['px' => $px, 'lineas' => [$nombre], 'bases' => [355.0]];
            }
        }

        return ['px' => 24, 'lineas' => [$nombre], 'bases' => [355.0]];
    }

    /**
     * Lo que se puede escribir con esta fuente: compuesto (NFC: «a» + tilde suelta es «á»), sin lo que la fuente no tiene
     * (un emoji, otra escritura) y con los espacios en uno.
     */
    public static function limpiar(string $texto, string $fuente): string
    {
        $texto = class_exists(\Normalizer::class) ? (string) \Normalizer::normalize($texto, \Normalizer::FORM_C) : $texto;
        $cobertura = CoberturaDeFuente::de($fuente);
        $limpio = '';
        foreach (mb_str_split($texto) as $caracter) {
            if (preg_match('/^\s$/u', $caracter) === 1) {
                $limpio .= ' ';
            } elseif ($cobertura->cubre($caracter)) {
                $limpio .= $caracter;
            }
        }

        return trim((string) preg_replace('/ {2,}/', ' ', $limpio));
    }

    /** El tamaño mayor (de `$max` a `$min`) con el que el texto cabe; si no cabe ni al mínimo, el mínimo. */
    private static function reducir(string $texto, string $fuente, int $max, int $min, float $util, float $tracking = 0.0): int
    {
        for ($px = $max; $px > $min; $px -= 2) {
            if (self::ancho($fuente, $px, $texto, $tracking) <= $util) {
                return $px;
            }
        }

        return $min;
    }

    /** @return array{0: string, 1: string}|null el nombre en dos por el espacio que deja las dos líneas más parejas */
    private static function partir(string $nombre, string $fuente): ?array
    {
        $palabras = explode(' ', $nombre);
        if (count($palabras) < 2) {
            return null;
        }
        $mejor = null;
        $peor = PHP_FLOAT_MAX;
        for ($i = 1; $i < count($palabras); $i++) {
            $a = implode(' ', array_slice($palabras, 0, $i));
            $b = implode(' ', array_slice($palabras, $i));
            $mayor = max(self::ancho($fuente, 72, $a), self::ancho($fuente, 72, $b));
            if ($mayor < $peor) {
                $peor = $mayor;
                $mejor = [$a, $b];
            }
        }

        return $mejor;
    }

    /**
     * El AVANCE de un glifo: GD no lo da, y la caja de tinta de un glifo suelto arrastra sus márgenes (medido: separaba las
     * letras). «|g|» menos «||» los cancela y deja el avance.
     */
    private static function avance(string $fuente, float $pt, string $glifo): float
    {
        $con = imagettfbbox($pt, 0, $fuente, '|'.$glifo.'|');
        $sin = imagettfbbox($pt, 0, $fuente, '||');
        if ($con === false || $sin === false) {
            return 0.0;
        }

        return ($con[2] - $con[0]) - ($sin[2] - $sin[0]);
    }

    /** El ancho de un texto a `$px`, con su interletraje en em. */
    private static function ancho(string $fuente, float $px, string $texto, float $tracking = 0.0): float
    {
        $pt = $px * 0.75;
        $ancho = 0.0;
        foreach (mb_str_split($texto) as $glifo) {
            $ancho += self::avance($fuente, $pt, $glifo) + $tracking * $px;
        }

        return $ancho;
    }

    /** Un texto glifo a glifo, con su avance y su interletraje (em). GD mide en puntos: 96 ppp. */
    private static function texto(GdImage $im, string $fuente, float $px, int $x, int $y, string $hex, string $texto, float $tracking = 0.0): void
    {
        $pt = $px * 0.75;
        $color = self::color($im, $hex);
        $xx = (float) $x;
        foreach (mb_str_split($texto) as $glifo) {
            imagettftext($im, $pt, 0, (int) round($xx), $y, $color, $fuente, $glifo);
            $xx += self::avance($fuente, $pt, $glifo) + $tracking * $px;
        }
    }

    /**
     * El adorno del tema sobre la banda: el confeti del diseño, la cuerda con banderines de fiesta o las burbujas de sereno.
     *
     * @param  array{bits: list<string>}  $c
     */
    private static function adorno(GdImage $im, string $decor, array $c, int $w, int $h, float $e): void
    {
        $bits = $c['bits'];
        if ($bits === []) {
            return;
        }
        if ($decor === 'burbujas') {
            foreach (self::BURBUJAS as $i => [$bx, $by, $s]) {
                $hex = $bits[$i % count($bits)];
                $r = (int) round($s * $e / 2);
                $cx = (int) round($w * $bx / 100) + $r;
                $cy = (int) round($h * $by / 100) + $r;
                if ($i % 2 === 0) {
                    imagesetthickness($im, (int) round(2 * $e));
                    imageellipse($im, $cx, $cy, 2 * $r, 2 * $r, self::color($im, $hex));
                    imagesetthickness($im, 1);
                } else {
                    self::circulo($im, $cx, $cy, $r, self::color($im, $hex, 32));
                }
            }

            return;
        }

        $fiesta = $decor === 'fiesta';
        foreach (self::BITS as $i => [$bx, $by, $s, $forma, $giro]) {
            if ($fiesta && $by <= 30) {
                continue;
            }
            $color = self::color($im, $bits[$i % count($bits)]);
            $tam = $s * $e;
            $cx = $w * $bx / 100 + $tam / 2;
            $cy = $h * $by / 100 + $tam / 2;
            if ($forma === 'dot') {
                self::circulo($im, (int) $cx, (int) $cy, (int) round($tam / 2), $color);

                continue;
            }
            $ancho = $forma === 'bar' ? $tam * 0.42 : $tam;
            $a = deg2rad($giro);
            $puntos = [];
            foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1]] as [$sx, $sy]) {
                $px = $sx * $ancho / 2;
                $py = $sy * $tam / 2;
                $puntos[] = (int) round($cx + $px * cos($a) - $py * sin($a));
                $puntos[] = (int) round($cy + $px * sin($a) + $py * cos($a));
            }
            imagefilledpolygon($im, $puntos, $color);
        }

        if ($fiesta) {
            // La cuerda y once banderines: en el diseño, colores 0, 3, 1 y 2 del confeti.
            $n = 11;
            $bw = 16 * $e;
            $bh = 20 * $e;
            $orden = [0, 3, 1, 2];
            imagefilledrectangle($im, 0, 0, $w, (int) round(2 * $e), self::color($im, '#ffffff', 38));
            $paso = ($w - 20 * $e - $bw) / ($n - 1);
            for ($i = 0; $i < $n; $i++) {
                $x = 10 * $e + $i * $paso;
                $hex = $bits[$orden[$i % 4] % count($bits)];
                imagefilledpolygon($im, [(int) $x, 0, (int) ($x + $bw), 0, (int) ($x + $bw / 2), (int) $bh], self::color($im, $hex));
            }
        }
    }

    private static function logo(GdImage $im, string $ruta, int $x, int $y, int $ancho): void
    {
        $logo = @imagecreatefrompng($ruta);
        if ($logo === false) {
            return;
        }
        $alto = (int) round(imagesy($logo) * $ancho / max(1, imagesx($logo)));
        imagecopyresampled($im, $logo, $x, $y, 0, 0, $ancho, $alto, imagesx($logo), imagesy($logo));
    }

    private static function lienzo(int $w, int $h): GdImage
    {
        $im = imagecreatetruecolor(max(1, $w), max(1, $h));
        if ($im === false) {
            throw new \RuntimeException('GD no pudo crear el lienzo de la imagen de la invitación.');
        }
        imagealphablending($im, true);

        return $im;
    }

    private static function circulo(GdImage $im, int $cx, int $cy, int $r, int $color): void
    {
        imagefilledellipse($im, $cx, $cy, 2 * $r, 2 * $r, $color);
    }

    private static function rectRedondo(GdImage $im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        foreach ([[$x + $r, $y + $r], [$x + $w - $r, $y + $r], [$x + $r, $y + $h - $r], [$x + $w - $r, $y + $h - $r]] as [$cx, $cy]) {
            self::circulo($im, $cx, $cy, $r, $color);
        }
    }

    /** Un color de GD desde `#rrggbb` (y su alfa de GD, 0 opaco – 127 transparente). */
    private static function color(GdImage $im, string $hex, int $alfa = 0): int
    {
        $h = ltrim($hex, '#');
        $color = imagecolorallocatealpha($im, (int) hexdec(substr($h, 0, 2)), (int) hexdec(substr($h, 2, 2)), (int) hexdec(substr($h, 4, 2)), $alfa);

        return $color === false ? 0 : $color;
    }
}
