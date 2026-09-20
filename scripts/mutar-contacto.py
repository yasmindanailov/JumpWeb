#!/usr/bin/env python3
"""Arnés de mutación de `#535` — la página `/contacto` rehecha desde su artboard (Fase 3 · T3b):
los canales del dato, el formulario en tarjeta con su tema y su aviso de privacidad, la chapa de
atajos sacada del inventario y la dirección escrita SIN mapa.

Mismo molde endurecido que `mutar-normas.py` (`#533`) y `mutar-precios.py` (`#531`):
  · en PYTHON, y cada mutación verifica que el fichero CAMBIÓ antes de correr nada;
  · EXIGE VERDE antes de mutar (`#337`) y ÁRBOL LIMPIO en los ficheros que muta (`#181`);
  · restaura SIEMPRE, también si el proceso revienta (`#448`).

⚠️⚠️ **Desde `#654` (F5 · T2b) la VISTA vive en la instancia** (`instancias/playjump/web/contacto.blade.php`)
y sus ocho mutantes de marcado —el mapa que no vuelve, la coma de la dirección, el ancla, el botón de
tinta, el aviso sin casilla, el tema sin elegir, la entradilla— se fueron con ella: una instancia no es una
app PHP y su juez es la huella de maquetación (`docs/paginas/contacto.md` del paquete los lista). Aquí
quedan los del PRODUCTO: el componente de canales, el controlador y el inventario de destinos, con las
guardas que afirman sobre datos (`ContactChannelsTest`, `tests/Feature/ContactPageTest`).

    python3 scripts/mutar-contacto.py
"""
import subprocess
import sys
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent
FILTRO = 'ContactChannelsTest|Tests\\\\Feature\\\\ContactPageTest'
CANALES = 'resources/views/components/site/contact-channels.blade.php'
CONTROLADOR = 'app/Http/Controllers/ContactController.php'
DESTINOS = 'app/Domain/Content/Services/SiteDestinations.php'
FICHEROS = [CANALES, CONTROLADOR, DESTINOS]

# (nombre, fichero, texto que se busca, texto por el que se cambia[, cuántas veces se espera])
MUTACIONES = [
    # ── Los canales ──
    ("el mismo número vuelve a salir en dos tarjetas",
     CANALES,
     "$mismoNumero = $whatsapp !== '' && $phoneTel !== ''\n        && preg_replace('/\\D/', '', $phoneTel) === $whatsapp;",
     "$mismoNumero = false;"),

    ("un canal sin dato se pinta igual",
     CANALES,
     "if ($email !== '') {",
     "if (true) {"),

    # ── El formulario (lo que decide el CONTROLADOR) ──
    ("el tema acepta cualquier valor que mande el cliente",
     CONTROLADOR,
     "'topic' => ['nullable', 'string', 'in:'.implode(',', self::TOPICS)],",
     "'topic' => ['nullable', 'string'],"),

    ("el tema elegido no llega al correo",
     CONTROLADOR,
     "'topic' => $data['topic'] ?? null,",
     "'topic' => null,"),

    # ── La chapa de atajos ──
    ("una página en mantenimiento se sigue ofreciendo en la chapa",
     DESTINOS,
     "if (MaintenanceSettings::pageInMaintenance($route)) {",
     "if (false) {"),

    ("se ofrece el ancla de Dudas aunque la portada no pinte la sección",
     DESTINOS,
     "if ($withFaq) {",
     "if (true) {"),

    # ── El honeypot (`#654`): el nombre del campo tiene UN hogar ──
    ("el controlador deja de preguntar por el honeypot (un bot entrega igual que una persona)",
     CONTROLADOR,
     "if (Honeypot::tripped($request)) {",
     "if (false) {"),
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
