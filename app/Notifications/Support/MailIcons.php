<?php

namespace App\Notifications\Support;

use RuntimeException;

/**
 * **LOS ICONOS DE LOS CORREOS** (la R1b, `specs/correos-rediseno.md` §4.1.3): los de Lucide que pinta la plantilla, como PNG
 * del color de su rol. En un correo no hay SVG fiable (Gmail y Outlook lo descartan), así que cada icono viaja como imagen.
 *
 * ▶ **Máscaras de PALETA, teñidas por su `PLTE`**: `scripts/correo-mascaras.mjs` rasteriza UNA vez cada SVG del paquete
 * versionado (`resources/icons/lucide`, `#686`) a un PNG de paleta —256 entradas del mismo color, cada una con su alfa en
 * `tRNS`, y el índice de cada píxel = su alfa—. Teñirlo es reescribir SOLO el trozo `PLTE` (768 bytes) y su CRC: PHP puro,
 * sin GD ni Imagick en producción, sin escribir en disco. El manifiesto (`resources/correo/iconos/MANIFIESTO.json`) dice
 * qué iconos hay, y su `version` va en la URL para que un cambio de las máscaras no se quede en las cachés.
 *
 * ⚠️ Un icono que no está en el manifiesto NO tiene URL: la plantilla deja su hueco, como pide el diseño («sin imagen, queda
 * la columna»). Y pedir uno no dice nada de la persona: la URL es la misma para todos (no es una apertura, `#797`).
 */
final class MailIcons
{
    /** Donde viven las máscaras y su manifiesto, bajo `resources/`. */
    public const CARPETA = 'correo/iconos';

    /** @var array{origen: string, licencia: string, lado: int, version: string, iconos: array<string, array{bytes: int, sha256: string}>}|null */
    private static ?array $manifiesto = null;

    /**
     * @return array{origen: string, licencia: string, lado: int, version: string, iconos: array<string, array{bytes: int, sha256: string}>}
     */
    public static function manifiesto(): array
    {
        if (self::$manifiesto === null) {
            $leido = json_decode((string) @file_get_contents(resource_path(self::CARPETA.'/MANIFIESTO.json')), true);
            if (! is_array($leido) || ! is_array($leido['iconos'] ?? null) || ! is_string($leido['version'] ?? null)) {
                throw new RuntimeException('El manifiesto de los iconos de correo falta o no se lee: `scripts/correo-mascaras.mjs`.');
            }
            self::$manifiesto = $leido;
        }

        return self::$manifiesto;
    }

    /** Olvida el manifiesto leído (pruebas). */
    public static function olvidar(): void
    {
        self::$manifiesto = null;
    }

    public static function existe(string $nombre): bool
    {
        return isset(self::manifiesto()['iconos'][$nombre]);
    }

    /**
     * La URL absoluta del icono en ese color (`#rrggbb`), o `null` si el icono no está en el manifiesto o el color no es un
     * hex de seis: sin URL, la plantilla deja el hueco.
     */
    public static function url(string $nombre, string $color): ?string
    {
        $hex = MailTheme::hex($color);
        if ($hex === null || ! self::existe($nombre)) {
            return null;
        }

        return route('correo.icono', [
            'v' => self::manifiesto()['version'],
            'color' => strtolower(substr($hex, 1)),
            'nombre' => $nombre,
        ]);
    }

    /** El PNG del icono teñido de `$color` (`rrggbb` o `#rrggbb`), o `null` si no hay tal icono o tal color. */
    public static function png(string $nombre, string $color): ?string
    {
        $hex = MailTheme::hex(str_starts_with($color, '#') ? $color : '#'.$color);
        if ($hex === null || strlen($hex) !== 7 || ! self::existe($nombre)) {
            return null;
        }

        return self::tenir((string) file_get_contents(resource_path(self::CARPETA.'/'.$nombre.'.png')), $hex);
    }

    /**
     * Tiñe una máscara de paleta: cambia el `PLTE` por el color repetido en sus 256 entradas y recalcula su CRC. Lo demás
     * —la cabecera, el alfa de `tRNS`, los píxeles— queda byte a byte.
     */
    public static function tenir(string $png, string $hex): string
    {
        if (! str_starts_with($png, "\x89PNG\r\n\x1a\n")) {
            throw new RuntimeException('No es un PNG.');
        }
        $rgb = (string) hex2bin(substr($hex, 1));
        $salida = substr($png, 0, 8);
        $pos = 8;
        $tenida = false;

        while ($pos + 12 <= strlen($png)) {
            $largo = unpack('N', substr($png, $pos, 4))[1];
            $tipo = substr($png, $pos + 4, 4);
            if ($tipo === 'PLTE') {
                if ($largo !== 768) {
                    throw new RuntimeException('La máscara no tiene las 256 entradas de paleta.');
                }
                $datos = str_repeat($rgb, 256);
                $salida .= pack('N', 768).'PLTE'.$datos.pack('N', crc32('PLTE'.$datos));
                $tenida = true;
            } else {
                $salida .= substr($png, $pos, 12 + $largo);
            }
            $pos += 12 + $largo;
        }

        if (! $tenida) {
            throw new RuntimeException('La máscara no tiene paleta: no se puede teñir.');
        }

        return $salida;
    }
}
