<?php

namespace App\Domain\Platform\Services;

/**
 * **El código de EJEMPLO de la sección «Antes de venir»** (carril de diseño Fase 2 · T2f).
 *
 * ⚠️⚠️ **ESTO NO ES UN QR Y NO PUEDE SERLO. Es el punto entero de la clase.**
 * `[DECIDIDO owner, 2026-09-10]`: el código que se ve en la portada es **de ejemplo y no lleva a
 * ningún sitio**. El artboard lo rotula así (`aria-label="Mi Play Jump QR, de ejemplo"` y, en
 * escritorio, un «de ejemplo» escrito al lado del nombre), porque **no es el carné de nadie**: el
 * carné de verdad vive en la cuenta, es una CREDENCIAL (`specs/identidad-qr-puerta.md`) y en la
 * portada no hay sesión que consultar.
 *
 * ▶ **Por eso NO se usa {@see QrCode::svg()}, que está justo al lado.** Aquélla codifica un dato
 * real y produce un código que **escanea**. Si esta pieza lo usara, el visitante escanearía la
 * portada y aterrizaría en algo —o en nada— que nadie ha decidido, y el rótulo «de ejemplo» pasaría
 * a ser mentira. *La propiedad que hay que conservar no es que el dibujo se parezca a un QR: es que
 * NO se pueda decodificar.*
 *
 * ⚠️ **Y es estructural, no una promesa.** Un lector de QR empieza por la **información de formato**
 * —los 15 bits que rodean los patrones de localización y que dicen nivel de corrección y máscara—.
 * Aquí esa zona queda **reservada y en blanco**, así que ningún decodificador llega siquiera a leer
 * datos: rechaza el símbolo. No hay versión, ni máscara, ni corrección de errores, ni datos.
 *
 * ▶ **Qué SÍ se dibuja**, y es lo que hace que se lea como un código a un metro de distancia:
 * los **tres patrones de localización** de las esquinas, los **dos patrones de sincronía** (la fila
 * y la columna 6, alternando) y **ruido determinista** en el resto.
 *
 * ⚠️ **Determinista a propósito**: la misma semilla da siempre el mismo dibujo, así que la portada
 * no cambia de aspecto entre dos peticiones ni entre dos servidores. `Math.random()` habría hecho
 * que dos capturas de la misma pantalla no fueran comparables — y este carril compara capturas.
 *
 * ⚠️ **El dibujo NO es idéntico al del artboard, y está dicho.** Su generador usa el mismo LCG pero
 * en JavaScript, donde `semilla * 1103515245` pasa de 2^53 y **pierde precisión** antes del `&`;
 * en PHP de 64 bits la multiplicación es exacta, así que el ruido cae distinto. Es ruido: lo que se
 * copia del artboard es la ANATOMÍA (rejilla 25, tres localizadores, dos sincronías, ~50 % de
 * relleno), no cada módulo.
 */
class SampleQrCode
{
    /** La rejilla del carné real: versión 2, 25×25 módulos (`QrCode` lo mide en §3 de su spec). */
    public const MODULES = 25;

    /**
     * Semilla del ruido. Es una fecha (2026-09-06) y no un número mágico: cambiarla cambia el
     * dibujo y nada más, pero cambiarlo sin querer haría divergir las capturas de este carril.
     */
    private const SEED = 20260906;

    /**
     * El atributo `d` de un `<path>` con los módulos oscuros, en coordenadas de módulo
     * (`viewBox="0 0 25 25"`).
     *
     * ⚠️ Los módulos contiguos de una fila se funden en un solo tramo (`h{n}`), que es lo que hace
     * `connectPaths` en el generador de verdad: sin eso el atributo pesa casi el doble y la portada
     * ya va justa de marcado (el logotipo en línea es el 42 % del HTML de `GET /`, `#275`).
     */
    public static function path(): string
    {
        $n = self::MODULES;
        $rejilla = self::grid($n);
        $d = '';

        for ($y = 0; $y < $n; $y++) {
            $x = 0;

            while ($x < $n) {
                if ($rejilla[$y][$x] === 0) {
                    $x++;

                    continue;
                }

                $inicio = $x;
                while ($x < $n && $rejilla[$y][$x] === 1) {
                    $x++;
                }

                $ancho = $x - $inicio;
                $d .= 'M'.$inicio.' '.$y.'h'.$ancho.'v1h-'.$ancho.'z';
            }
        }

        return $d;
    }

    /**
     * La rejilla de módulos: 1 = oscuro.
     *
     * @return array<int, array<int, int>>
     */
    private static function grid(int $n): array
    {
        $s = array_fill(0, $n, array_fill(0, $n, 0));

        // Los tres patrones de LOCALIZACIÓN: marco de 7×7 con núcleo de 3×3.
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$ox, $oy]) {
            for ($y = 0; $y < 7; $y++) {
                for ($x = 0; $x < 7; $x++) {
                    $borde = $x === 0 || $x === 6 || $y === 0 || $y === 6;
                    $nucleo = $x >= 2 && $x <= 4 && $y >= 2 && $y <= 4;
                    $s[$oy + $y][$ox + $x] = ($borde || $nucleo) ? 1 : 0;
                }
            }
        }

        // Los dos patrones de SINCRONÍA: fila y columna 6, alternando.
        for ($i = 8; $i < $n - 8; $i++) {
            $s[6][$i] = $i % 2 === 0 ? 1 : 0;
            $s[$i][6] = $i % 2 === 0 ? 1 : 0;
        }

        // El resto, ruido determinista. Las zonas RESERVADAS se quedan en blanco: ahí es donde
        // viviría la información de formato, y dejarla vacía es lo que impide decodificar.
        $semilla = self::SEED;
        $azar = static function () use (&$semilla): float {
            $semilla = ($semilla * 1103515245 + 12345) & 0x7FFFFFFF;

            return $semilla / 0x7FFFFFFF;
        };

        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (self::reserved($x, $y, $n)) {
                    continue;
                }

                $s[$y][$x] = $azar() > 0.48 ? 1 : 0;
            }
        }

        return $s;
    }

    /**
     * Lo que se deja EN BLANCO: las tres esquinas de los localizadores con su separador, las dos
     * sincronías y **la banda de la información de formato**.
     *
     * ⚠️⚠️ **La banda de formato NO estaba, y la añadió una guarda.** El generador del artboard solo
     * reserva las esquinas de 8×8, así que el ruido caía dentro de la columna 8 y la fila 8 — que en
     * un símbolo de verdad son los 15 bits que dicen nivel de corrección y máscara—. Dejarlas al
     * azar hacía que «no se puede decodificar» fuera **incidental** (un formato aleatorio casi nunca
     * es válido) en vez de **estructural**. Aquí se quiere lo segundo: es la propiedad que sostiene
     * el rótulo «de ejemplo».
     * ▶ Cuesta unos pocos módulos de dibujo y lo aleja un poco más del artboard, que ya diverge por
     * la aritmética del LCG (ver la cabecera de la clase).
     */
    private static function reserved(int $x, int $y, int $n): bool
    {
        return ($x < 8 && $y < 8)
            || ($x > $n - 9 && $y < 8)
            || ($x < 8 && $y > $n - 9)
            || $x === 6
            || $y === 6
            || ($x === 8 && ($y <= 8 || $y >= $n - 8))
            || ($y === 8 && ($x <= 8 || $x >= $n - 8));
    }
}
