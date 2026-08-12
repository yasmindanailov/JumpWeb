<?php

namespace App\Domain\Payments\Services;

/**
 * Traduce los códigos numéricos que Redsys devuelve para la **marca de la tarjeta**
 * (`Ds_Card_Brand`) y el **país emisor** (`Ds_Card_Country`, ISO-3166-1 numérico) a
 * etiquetas legibles en el panel admin (sub-fase 7.2a refinada, decisión #130).
 *
 * Diseño:
 *  - Marcas: el catálogo Redsys (manual oficial, Anexo 2) es corto y estable; mapa en código.
 *  - Países: ISO-3166-1 numérico = 250+ códigos; nos quedamos con UE + comunes en habla
 *    hispana. Cualquier código fuera del mapa devuelve `null` y la vista hace fallback al
 *    código crudo para que el operativo tenga el dato (no es UX bonita pero sí útil para
 *    soporte cuando el cliente reclame y el cargo viniera de un país raro).
 *  - Los nombres están en código (ES). Si el panel se ve en `zh_CN`, podríamos i18nizarlos
 *    con `lang/{es,zh_CN}/admin.php`, pero ZH del país emisor de la tarjeta es operativa
 *    interna marginal — primer iteración: ES único. Si la clienta lo pide en chino se mueve
 *    a lang/.
 */
class RedsysCardCodes
{
    /**
     * Marca de la tarjeta a partir del código numérico de `Ds_Card_Brand`.
     * Anexo 2 del manual Redsys: códigos publicados por la pasarela.
     */
    public static function brand(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return match ($code) {
            '1' => 'Visa',
            '2' => 'Mastercard',
            '6' => 'Discover',
            '7' => 'Diners',
            '8' => 'American Express',
            '9' => 'JCB',
            '22' => 'JCB',           // alias histórico observado en algunos terminales.
            default => null,
        };
    }

    /**
     * País emisor a partir del código ISO-3166-1 numérico de `Ds_Card_Country`.
     * Mapa con países UE + más comunes; fuera de eso, `null` para que la vista decida
     * mostrar el código crudo. Códigos con padding a 3 dígitos (`'040'`, `'056'`).
     */
    public static function country(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        // Normalizar padding: '40' → '040', '56' → '056'.
        $key = str_pad($code, 3, '0', STR_PAD_LEFT);

        return match ($key) {
            '724' => 'España',
            '250' => 'Francia',
            '826' => 'Reino Unido',
            '380' => 'Italia',
            '276' => 'Alemania',
            '620' => 'Portugal',
            '528' => 'Países Bajos',
            '056' => 'Bélgica',
            '372' => 'Irlanda',
            '040' => 'Austria',
            '208' => 'Dinamarca',
            '752' => 'Suecia',
            '578' => 'Noruega',
            '246' => 'Finlandia',
            '756' => 'Suiza',
            '300' => 'Grecia',
            '348' => 'Hungría',
            '616' => 'Polonia',
            '203' => 'Chequia',
            '703' => 'Eslovaquia',
            '705' => 'Eslovenia',
            '233' => 'Estonia',
            '428' => 'Letonia',
            '440' => 'Lituania',
            '470' => 'Malta',
            '196' => 'Chipre',
            '100' => 'Bulgaria',
            '642' => 'Rumanía',
            '191' => 'Croacia',
            '442' => 'Luxemburgo',
            '352' => 'Islandia',
            '840' => 'Estados Unidos',
            '124' => 'Canadá',
            '484' => 'México',
            '032' => 'Argentina',
            '152' => 'Chile',
            '170' => 'Colombia',
            '604' => 'Perú',
            '858' => 'Uruguay',
            '076' => 'Brasil',
            '356' => 'India',
            '156' => 'China',
            '392' => 'Japón',
            '410' => 'Corea del Sur',
            '036' => 'Australia',
            '554' => 'Nueva Zelanda',
            '504' => 'Marruecos',
            default => null,
        };
    }
}
