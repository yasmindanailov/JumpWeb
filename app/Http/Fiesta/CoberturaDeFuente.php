<?php

namespace App\Http\Fiesta;

/**
 * **QUÉ CARACTERES TIENE UNA FUENTE TTF/OTF**, leído de su tabla `cmap` (`#815`; `fiesta-sistema-nuevo.md` §4.19): quien
 * dibuja con GD tiene que quitar ANTES lo que la fuente no tiene, porque GD no avisa. Medido el 01-10: un emoji sale como
 * basura («ð» y cajas: GD no decodifica los caracteres de 4 bytes) y un carácter que la fuente no trae, como una caja.
 *
 * ▶ Lee las subtablas que importan: la de Unicode completo (formato 12; plataforma 3/10 o 0/4) y, si no la hay, la del
 * plano básico (formato 4; plataforma 3/1 o 0/3). Un carácter está si su glifo no es el 0 (`.notdef`).
 *
 * ⚠️ Una fuente que no se lee (no es TTF/OTF, no tiene `cmap`, está cortada) no cubre NADA: la imagen sin texto se ve
 * enseguida; un texto con cajas, no.
 */
final class CoberturaDeFuente
{
    /** @var array<string, self> una por fichero y su `filemtime` */
    private static array $memo = [];

    /**
     * @param  list<array{0: int, 1: int, 2: int, 3: int, 4: int}>  $tramos  formato 4: [inicio, fin, delta, idRangeOffset, posición del idRangeOffset]
     * @param  list<array{0: int, 1: int, 2: int}>  $grupos  formato 12: [inicio, fin, primer glifo]
     */
    private function __construct(
        private readonly string $datos,
        private readonly array $tramos,
        private readonly array $grupos,
    ) {}

    public static function de(string $fuente): self
    {
        $firma = $fuente.'@'.(is_file($fuente) ? (int) filemtime($fuente) : 0);

        return self::$memo[$firma] ??= self::leer($fuente);
    }

    /** ¿Tiene la fuente este carácter (un solo carácter UTF-8)? */
    public function cubre(string $caracter): bool
    {
        if ($caracter === '') {
            return false;
        }
        $c = mb_ord($caracter, 'UTF-8');

        foreach ($this->grupos as [$inicio, $fin, $glifo]) {
            if ($c >= $inicio && $c <= $fin) {
                return $glifo + ($c - $inicio) !== 0;
            }
        }

        foreach ($this->tramos as [$inicio, $fin, $delta, $rango, $posicion]) {
            if ($c < $inicio || $c > $fin) {
                continue;
            }
            if ($rango === 0) {
                return (($c + $delta) & 0xFFFF) !== 0;
            }
            $donde = $posicion + $rango + 2 * ($c - $inicio);
            $glifo = self::u16($this->datos, $donde);

            return $glifo !== null && $glifo !== 0 && (($glifo + $delta) & 0xFFFF) !== 0;
        }

        return false;
    }

    private static function leer(string $fuente): self
    {
        $datos = is_file($fuente) ? (string) file_get_contents($fuente) : '';
        $vacia = new self('', [], []);
        $tablas = self::u16($datos, 4);
        if ($tablas === null) {
            return $vacia;
        }

        $cmap = null;
        for ($i = 0; $i < $tablas; $i++) {
            $registro = 12 + 16 * $i;
            if (substr($datos, $registro, 4) === 'cmap') {
                $cmap = self::u32($datos, $registro + 8);
                break;
            }
        }
        if ($cmap === null) {
            return $vacia;
        }

        $subtablas = self::u16($datos, $cmap + 2) ?? 0;
        $completa = null;
        $basica = null;
        for ($i = 0; $i < $subtablas; $i++) {
            $registro = $cmap + 4 + 8 * $i;
            $plataforma = self::u16($datos, $registro);
            $codificacion = self::u16($datos, $registro + 2);
            $desplazamiento = self::u32($datos, $registro + 4);
            if ($desplazamiento === null) {
                continue;
            }
            $sitio = $cmap + $desplazamiento;
            $formato = self::u16($datos, $sitio);
            if ($formato === 12 && (($plataforma === 3 && $codificacion === 10) || ($plataforma === 0 && $codificacion === 4))) {
                $completa ??= $sitio;
            } elseif ($formato === 4 && (($plataforma === 3 && $codificacion === 1) || ($plataforma === 0 && $codificacion === 3))) {
                $basica ??= $sitio;
            }
        }

        if ($completa !== null) {
            $grupos = [];
            $n = self::u32($datos, $completa + 12) ?? 0;
            for ($i = 0; $i < $n; $i++) {
                $g = $completa + 16 + 12 * $i;
                $inicio = self::u32($datos, $g);
                $fin = self::u32($datos, $g + 4);
                $glifo = self::u32($datos, $g + 8);
                if ($inicio === null || $fin === null || $glifo === null) {
                    break;
                }
                $grupos[] = [$inicio, $fin, $glifo];
            }

            return new self($datos, [], $grupos);
        }

        if ($basica !== null) {
            $segmentos = intdiv(self::u16($datos, $basica + 6) ?? 0, 2);
            $finales = $basica + 14;
            $iniciales = $finales + 2 * $segmentos + 2;
            $deltas = $iniciales + 2 * $segmentos;
            $rangos = $deltas + 2 * $segmentos;
            $tramos = [];
            for ($i = 0; $i < $segmentos; $i++) {
                $fin = self::u16($datos, $finales + 2 * $i);
                $inicio = self::u16($datos, $iniciales + 2 * $i);
                $delta = self::u16($datos, $deltas + 2 * $i);
                $rango = self::u16($datos, $rangos + 2 * $i);
                if ($fin === null || $inicio === null || $delta === null || $rango === null) {
                    break;
                }
                $tramos[] = [$inicio, $fin, $delta, $rango, $rangos + 2 * $i];
            }

            return new self($datos, $tramos, []);
        }

        return $vacia;
    }

    private static function u16(string $datos, int $sitio): ?int
    {
        return $sitio >= 0 && $sitio + 2 <= strlen($datos) ? unpack('n', $datos, $sitio)[1] : null;
    }

    private static function u32(string $datos, int $sitio): ?int
    {
        return $sitio >= 0 && $sitio + 4 <= strlen($datos) ? unpack('N', $datos, $sitio)[1] : null;
    }
}
