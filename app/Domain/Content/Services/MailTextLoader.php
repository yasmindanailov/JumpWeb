<?php

namespace App\Domain\Content\Services;

use Closure;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Arr;

/**
 * EL ENGANCHE DE LOS TEXTOS DE CORREO DEL PARQUE (R1·T de `specs/correos-rediseno.md` §4.2.1, `#802`): envuelve el cargador
 * de ficheros del traductor y, al cargar un grupo, le SUPERPONE lo que el parque escribió en el panel. Es el único punto por
 * el que pasan todos los textos de los 30 correos (`__()` y la cabecera, que deriva `.badge`/`.headline`/`.preheader` de su
 * grupo), así que ninguna notificación cambia.
 *
 * Tres filtros, cada uno con su porqué:
 *  - solo las claves EDITABLES (el catálogo de los correos): una fila de otra clave —otra puerta, un error— no cambia la
 *    web ni lo legal;
 *  - solo si su texto de fábrica EXISTE todavía (una clave renombrada en el producto deja la fila huérfana, no la pinta);
 *  - solo si PASA HOY LAS REGLAS contra el texto de fábrica de hoy, las mismas que al guardar (`MailTextRules::problema`):
 *    si el producto cambió ese texto y sus datos, o una regla, el del parque se queda sin pintar y sale el de fábrica —un
 *    correo con una variable que ya no llega saldría con `{code}` tal cual; uno con negrita donde no se pinta, con sus
 *    asteriscos— y la pantalla lo marca desfasado. Un código de acceso no puede desaparecer ni por la base.
 */
final class MailTextLoader implements Loader
{
    /**
     * @param  Closure(string): bool  $editable  ¿esta clave entera es un texto editable de un correo?
     * @param  list<string>  $grupos  los grupos de idioma que tienen textos de correo (`emails`, `account`…)
     */
    public function __construct(
        private readonly Loader $archivos,
        private readonly Closure $editable,
        private readonly array $grupos,
    ) {}

    public function load($locale, $group, $namespace = null)
    {
        $lineas = $this->archivos->load($locale, $group, $namespace);

        if (($namespace !== null && $namespace !== '*') || ! in_array($group, $this->grupos, true)) {
            return $lineas;
        }

        foreach (app(MailTexts::class)->enIdioma((string) $locale) as $clave => $texto) {
            if (! str_starts_with($clave, $group.'.') || ! ($this->editable)($clave)) {
                continue;
            }
            $dentro = substr($clave, strlen($group) + 1);
            $fabrica = Arr::get($lineas, $dentro);
            if (! is_string($fabrica)) {
                continue;
            }
            if (MailTextRules::problema($texto, $fabrica, $clave) !== null) {
                continue;
            }
            Arr::set($lineas, $dentro, MailTextRules::aTraductor($texto, MailTextRules::variablesDeFabrica($fabrica)));
        }

        return $lineas;
    }

    /**
     * El texto de FÁBRICA de una clave entera en un idioma (`emails.order_confirmation.intro`), sin lo del parque: con él se
     * valida, se enseña al lado y se vuelve.
     */
    public function deFabrica(string $locale, string $clave): ?string
    {
        [$grupo, $dentro] = array_pad(explode('.', $clave, 2), 2, '');
        $valor = Arr::get($this->archivos->load($locale, $grupo), $dentro);

        return is_string($valor) ? $valor : null;
    }

    /**
     * Las hojas de un tramo de FÁBRICA (`emails.order_confirmation` → `['subject' => …, 'intro' => …]`), en el orden del
     * fichero: de ahí salen los bloques editables de cada correo.
     *
     * @return array<string, string>
     */
    public function hojasDeFabrica(string $locale, string $tramo): array
    {
        [$grupo, $dentro] = array_pad(explode('.', $tramo, 2), 2, '');
        $arbol = Arr::get($this->archivos->load($locale, $grupo), $dentro);

        return is_array($arbol) ? array_filter(Arr::dot($arbol), 'is_string') : [];
    }

    public function addNamespace($namespace, $hint)
    {
        $this->archivos->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->archivos->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->archivos->namespaces();
    }

    /**
     * Lo demás del cargador de ficheros (`addPath()`, `paths()`, `jsonPaths()`…) pasa tal cual: envolverlo no puede quitarle
     * nada a quien lo llame.
     *
     * @param  array<int, mixed>  $argumentos
     */
    public function __call(string $metodo, array $argumentos): mixed
    {
        return $this->archivos->{$metodo}(...$argumentos);
    }
}
