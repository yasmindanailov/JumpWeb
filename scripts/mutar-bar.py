#!/usr/bin/env python3
"""Arnés de mutación de `#536` — la página `/bar` (Fase 3 · T3b): la carta publicada como IMAGEN
desde el panel, la foto del local, la chapa de la barra, los alérgenos y la puerta de la portada.

Mismo molde endurecido que `mutar-contacto.py` (`#535`) y `mutar-normas.py` (`#533`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️ **Varias mutaciones prueban una AUSENCIA** —que sin nombre no haya destino, que sin carta no se
pinte nada, que la página no estrene un botón— y ésas se mutan al revés: se AÑADE lo que no debe
estar, o se RETIRA la condición que lo impide.

⚠️⚠️ **Desde `#655` (F5 · T2b) la VISTA de PlayJump vive en la instancia** y las guardas se parten por lo
que afirman (`#649`): `BarPageTest` afirma sobre lo que `BarPage` publica y sobre la ruta, y mata los
mutantes del servicio, del modelo, del controlador y del inventario; los mutantes de VISTA apuntan al
ANFITRIÓN MÍNIMO del producto (`anfitrion/bar.blade.php`) y los mata `AnfitrionBarTest`. El de «la página
estrena un botón de compra» se fue con la vista: es diseño (`#536`), y su juez es la huella.

    python3 scripts/mutar-bar.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'Tests\\\\Feature\\\\Landing\\\\BarPageTest|AnfitrionBarTest'
VISTA = 'resources/views/anfitrion/bar.blade.php'
# ⚠️ Desde `#666` la portada de PlayJump también vive en la instancia: la puerta del bar se muta en
# el ANFITRIÓN MÍNIMO, que es la portada que el producto sirve.
PORTADA = 'resources/views/anfitrion/portada.blade.php'
CONTROLADOR = 'app/Http/Controllers/BarController.php'
HOME = 'app/Http/Controllers/HomeController.php'
SERVICIO = 'app/Domain/Content/Services/BarPage.php'
MODELO = 'app/Domain/Content/Models/BarImage.php'
DESTINOS = 'app/Domain/Content/Services/SiteDestinations.php'
FICHEROS = [VISTA, PORTADA, CONTROLADOR, HOME, SERVICIO, MODELO, DESTINOS]

# (nombre, fichero, texto que se busca, texto por el que se cambia[, cuántas veces se espera])
MUTACIONES = [
    # ── Sin nombre, el bar no existe ──
    ("la página se sirve aunque el bar no tenga nombre",
     CONTROLADOR,
     "abort_unless(BarPage::isPublished(), 404);",
     "// mutación"),

    ("el destino se ofrece en el menú y el pie sin bar publicado",
     DESTINOS,
     "if ($route === 'bar' && ! BarPage::isPublished()) {",
     "if (false) {"),

    ("la tarjeta de la portada ignora el inventario (y se pinta en mantenimiento)",
     HOME,
     "collect(SiteDestinations::pages())->contains('route', 'bar') ? BarPage::name() : null,",
     "BarPage::name(),"),

    # ⚠️ Esta mutación estaba etiquetada como «un nombre en blanco» y muta `freeEntry()`, no
    # `name()`. Conservada con el nombre CORRECTO porque destapó un agujero real de la guarda: con
    # ella, un `bar.free_entry` desconocido llegaba a la vista y ésta pintaba la clave de traducción
    # en crudo. La guarda que lo caza nació de aquí.
    ("un valor desconocido de acceso cuenta como decidido",
     SERVICIO,
     "return in_array($v, ['yes', 'no'], true) ? $v : null;",
     "return $v === '' ? null : $v;"),

    # ── La carta ──
    ("la sección de la carta se pinta sin ninguna carta subida",
     VISTA,
     "@if ($barMenu->isNotEmpty())",
     "@if (true)"),

    ("las caras de la carta dejan de ser un enlace a su fichero",
     VISTA,
     '<a class="bar-sheet" href="{{ $sheet->imageUrl() }}" target="_blank" rel="noopener">',
     '<span class="bar-sheet">'),

    ("la carta se publica sin decir qué se ve en ella",
     VISTA,
     'alt="{{ $sheet->tr(\'alt\') }}"',
     'alt=""'),

    ("la carta deja de declarar sus dimensiones y la página salta",
     VISTA,
     '@if ($sheet->width && $sheet->height) width="{{ $sheet->width }}" height="{{ $sheet->height }}" @endif',
     ''),

    ("una cara desactivada se publica igual",
     SERVICIO,
     "->ofKind(BarImage::KIND_MENU)\n            ->active()",
     "->ofKind(BarImage::KIND_MENU)"),

    ("un tipo que el producto no declara se publica igual",
     SERVICIO,
     "->ofKind(BarImage::KIND_MENU)",
     "->whereIn('kind', [BarImage::KIND_MENU, 'promo'])"),

    ("la línea de alérgenos desaparece de la carta",
     VISTA,
     '<p class="bar-menu__allergens">{{ __(\'site.bar_allergens\') }}</p>',
     ''),

    # ── La foto del local y el acceso ──
    ("se publican todas las fotos del local y no la primera",
     SERVICIO,
     "->ofKind(BarImage::KIND_VENUE)\n            ->active()\n            ->ordered()\n            ->first();",
     "->ofKind(BarImage::KIND_VENUE)\n            ->active()\n            ->orderByDesc('position')\n            ->first();"),

    ("el acceso al bar se afirma aunque nadie lo haya decidido",
     VISTA,
     "@if ($barFreeEntry)",
     "@if (true)"),

    # ── El dato ──
    ("las dimensiones dejan de medirse al subir",
     MODELO,
     "[$img->width, $img->height] = self::measure((string) $img->image);",
     "// mutación"),

    ("el fichero viejo se queda huérfano al reemplazar la imagen",
     MODELO,
     "if ($img->isDirty('image') && ($old = $img->getOriginal('image'))) {",
     "if (false && ($old = $img->getOriginal('image'))) {"),

    ("el fichero se queda huérfano al borrar la fila",
     MODELO,
     "static::deleted(function (BarImage $img): void {\n            if ($img->image) {",
     "static::deleted(function (BarImage $img): void {\n            if (false) {"),

    # ── La puerta de la portada ──
    ("la tarjeta del bar desaparece de la sección 03",
     PORTADA,
     "@if (filled($barName))",
     "@if (false)"),
]


def git(*args):
    return subprocess.run(['git', *args], cwd=RAIZ, capture_output=True, text=True)


def restaura():
    git('checkout', '-q', '--', *FICHEROS)


def verde():
    r = subprocess.run(
        ['docker', 'compose', 'exec', '-u', 'sail', '-T', 'laravel.test',
         'php', 'artisan', 'test', '--filter=' + FILTRO],
        cwd=RAIZ, capture_output=True, text=True)
    return r.returncode == 0, r.stdout + r.stderr


def main():
    sucio = git('status', '--porcelain', '--', *FICHEROS).stdout.strip()
    if sucio:
        print('✗ hay cambios sin commitear en los ficheros que se mutan — commitea antes:')
        print(sucio)
        return 2

    print('── CONTROL: la guarda tiene que estar VERDE antes de mutar ──')
    ok, salida = verde()
    if not ok:
        print('✗ el árbol limpio ya sale ROJO: el arnés no mide nada')
        print(salida[-1500:])
        return 2
    print('✓ verde\n')

    print('── mutaciones ──')
    vivas = []
    try:
        for mutacion in MUTACIONES:
            nombre, rel, busca, cambia = mutacion[:4]
            esperadas = mutacion[4] if len(mutacion) > 4 else 1
            ruta = RAIZ / rel
            antes = ruta.read_text(encoding='utf-8')
            n = antes.count(busca)
            if n != esperadas:
                print('  ⚠ NO APLICADA (%d coincidencias, se esperaban %d)  %s' % (n, esperadas, nombre))
                vivas.append(nombre + ' [no aplicada]')
                continue
            ruta.write_text(antes.replace(busca, cambia, esperadas), encoding='utf-8')
            assert ruta.read_text(encoding='utf-8') != antes, 'el fichero no cambió'

            ok, _ = verde()
            print(('  ✗ SOBREVIVE  ' if ok else '  ✓ muere      ') + nombre)
            if ok:
                vivas.append(nombre)
            restaura()
    finally:
        restaura()

    print('\n' + '─' * 60)
    print('%d/%d mutaciones mueren' % (len(MUTACIONES) - len(vivas), len(MUTACIONES)))
    for v in vivas:
        print('   sobrevive: ' + v)
    return 1 if vivas else 0


if __name__ == '__main__':
    sys.exit(main())
