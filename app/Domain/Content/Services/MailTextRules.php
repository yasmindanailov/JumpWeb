<?php

namespace App\Domain\Content\Services;

/**
 * LAS REGLAS DE UN TEXTO DE CORREO DEL PARQUE (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): lógica pura, sin base
 * de datos, para que el guardado (`MailTexts::guardar`), el cargador (`MailTextLoader`) y la pantalla digan LO MISMO.
 *
 * El texto de fábrica escribe sus variables como el traductor (`:code`); el del parque, entre llaves (`{code}`), que no
 * se confunden con un «Nota:» ni con una hora. Las reglas, cada una con su porqué:
 *  - las VARIABLES del texto de fábrica son OBLIGATORIAS y no se admiten otras: un plazo, un importe o un código que
 *    desaparece deja un correo que ya no es verdad («nada que ya no sea verdad», regla 5 del brief), y una que el correo no
 *    conoce saldría tal cual, con sus llaves;
 *  - sin HTML: el molde escapa cada línea y solo entiende `**negrita**` (`MailDocument::rico()`), que va balanceada;
 *  - llaves sueltas no: `{nombre` es casi siempre una variable mal escrita;
 *  - ni vacío ni más largo que su tope (el asunto se corta en la bandeja; un párrafo de 1.000 ya no es un correo).
 */
final class MailTextRules
{
    /** El tope de un asunto, una chapa o un titular: lo que se lee de un vistazo (y en la bandeja, cortado). */
    public const TOPE_CORTO = 150;

    /** El de la línea de adelanto, que la bandeja enseña detrás del asunto. */
    public const TOPE_ADELANTO = 200;

    /** El de un párrafo. */
    public const TOPE = 1000;

    /** Los motivos de rechazo, en el orden en que se comprueban (y sus textos, `admin.mail_texts.errores.*`). */
    public const MOTIVOS = ['vacio', 'largo', 'html', 'llaves', 'desconocida', 'falta', 'negrita'];

    /**
     * Las variables de un texto de FÁBRICA (`:code`), ordenadas y sin repetir.
     *
     * @return list<string>
     */
    public static function variablesDeFabrica(string $fabrica): array
    {
        preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $fabrica, $m);

        return self::limpias($m[1]);
    }

    /**
     * Las variables de un texto del PARQUE (`{code}`), ordenadas y sin repetir.
     *
     * @return list<string>
     */
    public static function variablesDelParque(string $texto): array
    {
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', $texto, $m);

        return self::limpias($m[1]);
    }

    /**
     * El texto de fábrica escrito como lo edita el parque: cada variable suya, entre llaves.
     */
    public static function aParque(string $fabrica): string
    {
        $vars = self::variablesDeFabrica($fabrica);
        if ($vars === []) {
            return $fabrica;
        }
        // Las más largas primero: `:day_label` no puede quedarse en `{day}_label`.
        usort($vars, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $patron = '/:('.implode('|', array_map(static fn (string $v): string => preg_quote($v, '/'), $vars)).')(?![a-zA-Z0-9_])/';

        return (string) preg_replace($patron, '{$1}', $fabrica);
    }

    /**
     * El texto del parque como lo lee el traductor: `{code}` → `:code`, solo las variables de su texto de fábrica.
     *
     * @param  list<string>  $variables
     */
    public static function aTraductor(string $texto, array $variables): string
    {
        foreach ($variables as $v) {
            $texto = str_replace('{'.$v.'}', ':'.$v, $texto);
        }

        return $texto;
    }

    /** El tope de una clave, por lo que es (su último tramo). */
    public static function tope(string $clave): int
    {
        $ultimo = substr($clave, (int) strrpos($clave, '.') + 1);

        return match (true) {
            $ultimo === 'preheader' => self::TOPE_ADELANTO,
            str_starts_with($ultimo, 'subject'), $ultimo === 'badge', str_starts_with($ultimo, 'headline') => self::TOPE_CORTO,
            default => self::TOPE,
        };
    }

    /**
     * ¿Qué le pasa a este texto del parque? `null` = se puede guardar. Si no, el PRIMER motivo que falla, con lo que hace
     * falta para decirlo (`tope`, `variables`).
     *
     * @return array{motivo: string, tope?: int, variables?: list<string>}|null
     */
    public static function problema(string $texto, string $fabrica, string $clave): ?array
    {
        if (trim($texto) === '') {
            return ['motivo' => 'vacio'];
        }
        $tope = self::tope($clave);
        if (mb_strlen($texto) > $tope) {
            return ['motivo' => 'largo', 'tope' => $tope];
        }
        if (preg_match('/<\s*[a-zA-Z\/!]/', $texto) === 1) {
            return ['motivo' => 'html'];
        }
        $sinVariables = (string) preg_replace('/\{[a-zA-Z_][a-zA-Z0-9_]*\}/', '', $texto);
        if (str_contains($sinVariables, '{') || str_contains($sinVariables, '}')) {
            return ['motivo' => 'llaves'];
        }
        $deFabrica = self::variablesDeFabrica($fabrica);
        $delParque = self::variablesDelParque($texto);
        $desconocidas = array_values(array_diff($delParque, $deFabrica));
        if ($desconocidas !== []) {
            return ['motivo' => 'desconocida', 'variables' => $desconocidas];
        }
        $faltan = array_values(array_diff($deFabrica, $delParque));
        if ($faltan !== []) {
            return ['motivo' => 'falta', 'variables' => $faltan];
        }
        if (substr_count($texto, '**') % 2 !== 0) {
            return ['motivo' => 'negrita'];
        }

        return null;
    }

    /**
     * @param  array<int, string>  $nombres
     * @return list<string>
     */
    private static function limpias(array $nombres): array
    {
        $nombres = array_values(array_unique($nombres));
        sort($nombres);

        return $nombres;
    }
}
