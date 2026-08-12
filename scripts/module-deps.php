<?php

/**
 * Dependencias reales entre clases de `app/`, para el paso 0 del checklist de mudanza
 * (`docs/specs/modulos-dominio.md` §5) — Fase 2.
 *
 * Por qué existe: en un namespace PLANO (`App\Support`, `App\Models`) una clase puede usar a
 * otra del mismo namespace SIN `use`. Esas referencias no aparecen en el grafo de imports y son
 * INVISIBLES; al mudar la clase se convierten en «class not found». En el paso 2 fueron la única
 * causa real de rotura (30+ referencias, 9 ficheros). Este script las saca a la luz ANTES de
 * mover, con el mismo tokenizador que usa `ModuleBoundariesTest` (no regex: los docblocks citan
 * clases a propósito y una regex las contaría como dependencias).
 *
 * Uso (dentro del contenedor):
 *   php scripts/module-deps.php                 # todo app/Support + app/Models
 *   php scripts/module-deps.php Faq Page Offer  # solo esas clases y quién las referencia
 *
 * Lee: nada fuera de `app/`. No modifica nada.
 */
$root = dirname(__DIR__);
$scanned = [$root.'/app/Support', $root.'/app/Models', $root.'/app/Domain'];
$callers = $root.'/app';

/** @return list<string> */
function phpFiles(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'php') {
            $out[] = $f->getPathname();
        }
    }
    sort($out);

    return $out;
}

function fqcnOf(string $file, string $root): string
{
    return 'App'.str_replace(['/', '.php'], ['\\', ''], substr($file, strlen($root.'/app')));
}

/**
 * Referencias `App\…` de un fichero, separadas en cualificadas (con `use`/FQCN) e INVISIBLES
 * (nombre corto de una clase HERMANA del mismo namespace, sin `use`).
 *
 * @param  array<string,string>  $siblings  nombre corto => FQCN
 * @return array{qualified: list<string>, invisible: list<string>}
 */
function referencesOf(string $file, array $siblings): array
{
    $tokens = token_get_all((string) file_get_contents($file));
    $qualified = [];
    $invisible = [];
    $afterNamespace = false;

    foreach ($tokens as $i => $token) {
        if (! is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAMESPACE) {
            $afterNamespace = true;

            continue;
        }

        if ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED) {
            if ($afterNamespace) {
                $afterNamespace = false;   // el nombre del propio namespace no es dependencia

                continue;
            }
            $name = ltrim($token[1], '\\');
            if (str_starts_with($name, 'App\\')) {
                $qualified[$name] = true;
            }

            continue;
        }

        if ($token[0] !== T_STRING || ! isset($siblings[$token[1]])) {
            continue;
        }

        // Descartar accesos a miembros y declaraciones: `->Foo`, `::Foo`, `function Foo`…
        $prev = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                continue;
            }
            $prev = $tokens[$j];
            break;
        }
        $prevId = is_array($prev) ? $prev[0] : null;
        $memberOrDeclaration = [
            T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON,
            T_FUNCTION, T_CONST, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM,
        ];
        if (in_array($prevId, $memberOrDeclaration, true)) {
            continue;
        }

        $invisible[$siblings[$token[1]]] = true;
    }

    return ['qualified' => array_keys($qualified), 'invisible' => array_keys($invisible)];
}

$files = [];
foreach ($scanned as $dir) {
    foreach (phpFiles($dir) as $f) {
        $files[fqcnOf($f, $root)] = $f;
    }
}
ksort($files);

// Nombre corto => FQCN, agrupado por namespace (para detectar hermanas).
$byNamespace = [];
foreach ($files as $fqcn => $_) {
    $cut = strrpos($fqcn, '\\');
    $byNamespace[substr($fqcn, 0, $cut)][substr($fqcn, $cut + 1)] = $fqcn;
}

$wanted = array_slice($argv, 1);
$short = fn (string $c): string => str_replace('App\\', '', $c);

$report = [];
foreach ($files as $fqcn => $file) {
    $cut = strrpos($fqcn, '\\');
    $siblings = $byNamespace[substr($fqcn, 0, $cut)] ?? [];
    unset($siblings[substr($fqcn, $cut + 1)]);

    $refs = referencesOf($file, $siblings);
    $report[$fqcn] = [
        'qualified' => array_values(array_filter($refs['qualified'], fn ($d) => $d !== $fqcn)),
        'invisible' => $refs['invisible'],
    ];
}

if ($wanted === []) {
    foreach ($report as $fqcn => $r) {
        printf("%-46s imports=%-2d INVISIBLES=%-2d %s\n",
            $short($fqcn), count($r['qualified']), count($r['invisible']),
            $r['invisible'] === [] ? '' : '⚠ '.implode(' ', array_map($short, $r['invisible']))
        );
    }
    exit(0);
}

// Modo «voy a mover ESTAS clases»: qué arrastran y quién se queda colgando.
echo "== Lo que ARRASTRAN las clases a mover (sus dependencias) ==\n";
foreach ($report as $fqcn => $r) {
    if (! in_array($short($fqcn), $wanted, true) && ! in_array(substr($fqcn, strrpos($fqcn, '\\') + 1), $wanted, true)) {
        continue;
    }
    $all = array_merge($r['qualified'], $r['invisible']);
    printf("  %-42s %s\n", $short($fqcn), $all === [] ? '— hoja' : implode(' ', array_map($short, $all)));
    foreach ($r['invisible'] as $dep) {
        printf("      ⚠ INVISIBLE (necesitará `use` al mover): %s\n", $short($dep));
    }
}

echo "\n== Quién REFERENCIA a las clases a mover (y cómo) ==\n";
$targets = [];
foreach ($files as $fqcn => $_) {
    $name = substr($fqcn, strrpos($fqcn, '\\') + 1);
    if (in_array($name, $wanted, true) || in_array($short($fqcn), $wanted, true)) {
        $targets[$fqcn] = $name;
    }
}
foreach (phpFiles($callers) as $file) {
    $fqcn = fqcnOf($file, $root);
    $cut = strrpos($fqcn, '\\');
    $siblings = $byNamespace[substr($fqcn, 0, $cut)] ?? [];
    unset($siblings[substr($fqcn, $cut + 1)]);
    $refs = referencesOf($file, $siblings);

    foreach ($targets as $target => $name) {
        if ($target === $fqcn) {
            continue;
        }
        if (in_array($target, $refs['invisible'], true)) {
            printf("  ⚠ INVISIBLE  %-44s → %s\n", $short($fqcn), $name);
        } elseif (in_array($target, $refs['qualified'], true)) {
            printf("    import     %-44s → %s\n", $short($fqcn), $name);
        }
    }
}
