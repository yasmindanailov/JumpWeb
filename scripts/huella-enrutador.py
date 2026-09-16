#!/usr/bin/env python3
"""
F1 del programa «producto e instancias» (`docs/specs/producto-e-instancias.md` §4.8 y §6, `DECISIONES #619`):
la HUELLA de la tabla de enrutado de `CLAUDE.md`, y la mudanza de cada fila a su documento.

El enrutador pasa de una fila de hasta 34 KB a UNA LÍNEA por fila. La regla que evita perder una trampa
al mudar es la huella: cada frase con aviso (⚠️ · ❗ · ⛔) de una fila tiene que ser localizable en el
documento al que la fila apunta ANTES de borrarse de la fila. Este guion hace las dos mitades:

    python3 scripts/huella-enrutador.py               informe: cobertura por fila y total, sin escribir
    python3 scripts/huella-enrutador.py --anexar      añade al documento destino de cada fila lo que la
                                                      fila decía y el destino no tiene, VERBATIM, como
                                                      anexo datado; vuelve a medir y exige 100 %
    python3 scripts/huella-enrutador.py --fuente F --desde A --hasta B
                                                      la misma medición sobre las líneas A..B de otro
                                                      fichero (el bloque vivo de ESTADO.md, el tracker),
                                                      solo informe: para saber qué se borra sin huella

Qué es una «frase con aviso»: el texto que va desde una racha de marcadores (⚠️ ❗ ⛔ ▶ ✅ ⏸️ 📜 🟦 ⬜ ❓ 🚀
❗❗❗…) hasta la siguiente racha, y que EMPIEZA por ⚠️, ❗ o ⛔. Se compara sin negritas ni espacios
repetidos (una spec envuelve las líneas a 100 columnas; la fila va en una sola), y sin la racha de
marcadores inicial. Una frase está cubierta si aparece en el destino primario o en cualquier otro
documento que la fila cite — salvo `docs/DECISIONES.md` y `docs/decisiones/`, a propósito: las
decisiones se buscan por número y no son el punto de entrada de nadie.

Destino primario de una fila: la primera `docs/specs/*.md` que cita; si no cita ninguna, la primera
`docs/sistemas/*.md`; si tampoco, el primer `docs/*.md` que no sea DECISIONES, INVARIANTES, el tracker
ni el índice. `DESTINO_FORZADO` corrige a mano las filas que apuntan a documentos donde un anexo no
cabe (las invariantes son una tabla, no un sitio para prosa).

Lo que NO hace: no reescribe ni resume una frase. Lo que se muda se muda tal cual; el §0 de cada spec,
que sí resume, lo escribe una persona (un agente) leyendo, no este guion.
"""
import argparse
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
ENRUTADOR = ROOT / 'CLAUDE.md'
FECHA_MUDANZA = '2026-09-16'
DECISION_MUDANZA = '#619'

MARCADOR = r'(?:⚠️|❗|⛔|▶|✅|⏸️|📜|🟦|⬜|❓|🚀|❌|🧩|🎨|📧|🏦|🏗️)'
RACHA = re.compile(rf'(?:{MARCADOR}\s*)+')
AVISO = re.compile(r'^(?:⚠️|❗|⛔)')

EXCLUIDOS_COMO_COBERTURA = {'docs/DECISIONES.md', 'docs/00-REFACTOR.md', 'docs/README.md', 'docs/ESTADO.md'}
NO_PRIMARIOS = EXCLUIDOS_COMO_COBERTURA | {'docs/INVARIANTES.md', 'docs/CONVENCIONES.md'}
# Filas cuyo destino no puede recibir un anexo: la clave es el principio de la celda «Si trabajas en…».
DESTINO_FORZADO = {
    'Aforo / franjas': 'docs/sistemas/AFORO-FRANJAS.md',
}


def normaliza(texto: str) -> str:
    texto = texto.replace('**', '').replace('~~', '')
    texto = RACHA.sub(' ', texto)
    return re.sub(r'\s+', ' ', texto).strip()


def filas_del_enrutador(md: str):
    """Devuelve [(tema, contenido, línea)] de la tabla de enrutado, sin cabecera ni separador."""
    lineas = md.split('\n')
    try:
        ini = next(i for i, l in enumerate(lineas) if l.startswith('| Si trabajas en'))
    except StopIteration:
        sys.exit('✗ huella: no encuentro la cabecera «| Si trabajas en…» en CLAUDE.md')
    filas = []
    for n in range(ini + 2, len(lineas)):
        l = lineas[n]
        if not l.startswith('|'):
            break
        celdas = [c.strip() for c in l.strip().strip('|').split(' | ')]
        if len(celdas) < 2:
            continue
        filas.append((celdas[0], ' · '.join(celdas[1:]), n + 1))
    return filas


def documentos_citados(contenido: str):
    """Rutas bajo docs/ que cita la fila, en orden de aparición y sin repetir."""
    vistos, salida = set(), []

    def añade(ruta: str):
        if ruta not in vistos and (ROOT / ruta).is_file():
            vistos.add(ruta)
            salida.append(ruta)

    for m in re.finditer(r'docs/[A-Za-z0-9_/.-]+?\.md', contenido):
        añade(m.group(0))
    for m in re.finditer(r'`([A-Za-z0-9_-]+\.md)`', contenido):
        nombre = m.group(1)
        for carpeta in ('docs', 'docs/specs', 'docs/sistemas'):
            if (ROOT / carpeta / nombre).is_file():
                añade(f'{carpeta}/{nombre}')
                break
    return salida


def destino_primario(tema: str, citados):
    for clave, ruta in DESTINO_FORZADO.items():
        if tema.replace('**', '').startswith(clave):
            return ruta
    for prefijo in ('docs/specs/', 'docs/sistemas/', 'docs/'):
        for ruta in citados:
            if ruta.startswith(prefijo) and ruta not in NO_PRIMARIOS:
                return ruta
    return None


def segmentos(contenido: str):
    """Parte la fila en trozos que empiezan en cada racha de marcadores. Conserva la racha."""
    cortes = [m.start() for m in RACHA.finditer(contenido)]
    if not cortes or cortes[0] != 0:
        cortes.insert(0, 0)
    trozos = [contenido[a:b].strip() for a, b in zip(cortes, cortes[1:] + [len(contenido)])]
    return [t for t in trozos if t]


def mide(filas, destino_de):
    """[(tema, primario, avisos, cubiertos, sin_cubrir)] con los textos normalizados de los destinos."""
    cache = {}

    def texto(ruta):
        if ruta not in cache:
            p = ROOT / ruta
            cache[ruta] = normaliza(p.read_text(encoding='utf-8')) if p.is_file() else ''
        return cache[ruta]

    informe = []
    for tema, contenido, _ in filas:
        primario, citados = destino_de(tema, contenido)
        cobertura = [r for r in ([primario] if primario else []) + citados
                     if r and r not in EXCLUIDOS_COMO_COBERTURA and not r.startswith('docs/decisiones/')]
        avisos = [s for s in segmentos(contenido) if AVISO.match(s)]
        sin_cubrir = [s for s in avisos if not any(normaliza(s) in texto(r) for r in cobertura)]
        informe.append((tema, primario, avisos, len(avisos) - len(sin_cubrir), sin_cubrir))
    return informe


def marca_de_fila(tema: str) -> str:
    """Lo que identifica en el destino que ESTA fila ya se mudó: dos filas pueden compartir destino."""
    return f'la fila **«{normaliza(tema)}»** de `CLAUDE.md`'


def anexo(tema: str, contenido: str, citados, ordinal: int) -> str:
    cuerpo = '\n'.join(f'- {s}' for s in segmentos(contenido))
    fuentes = ' · '.join(f'`{r}`' for r in citados) or '—'
    titulo = '## Anexo' if ordinal == 1 else f'## Anexo {ordinal}'
    return (
        f'\n\n{titulo} · La fila del enrutador, mudada el {FECHA_MUDANZA}\n\n'
        f'> Lo que decía {marca_de_fila(tema)} cuando el enrutador bajó a una línea por fila\n'
        f'> (`DECISIONES {DECISION_MUDANZA}`). Se conserva **verbatim** porque es historia de trampas medidas: léelo\n'
        f'> después de «Antes de tocar» y no lo reescribas.\n'
        f'> Documentos que la fila citaba: {fuentes}.\n\n'
        f'{cuerpo}\n'
    )


def main():
    ap = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    ap.add_argument('--anexar', action='store_true', help='escribe los anexos y exige 100 %% después')
    ap.add_argument('--fuente', help='medir otro fichero en vez de CLAUDE.md (solo informe)')
    ap.add_argument('--desde', type=int, default=1)
    ap.add_argument('--hasta', type=int, default=10**9)
    ap.add_argument('--todo', action='store_true', help='lista cada frase sin cubrir')
    ap.add_argument('--enrutador', default=str(ENRUTADOR),
                    help='otro CLAUDE.md que medir (p. ej. el histórico: git show <sha>:CLAUDE.md > /tmp/x)')
    args = ap.parse_args()

    if args.fuente:
        lineas = (ROOT / args.fuente).read_text(encoding='utf-8').split('\n')[args.desde - 1:args.hasta]
        contenido = ' '.join(lineas)
        citados = documentos_citados(contenido)
        filas = [(f'{args.fuente} L{args.desde}–{min(args.hasta, args.desde + len(lineas) - 1)}', contenido, args.desde)]
        informe = mide(filas, lambda t, c: (None, citados))
        tema, _, avisos, cubiertos, sin_cubrir = informe[0]
        print(f'{tema}: {cubiertos}/{len(avisos)} frases con aviso localizables en los {len(citados)} documentos que cita '
              f'(sin contar decisiones, tracker, estado ni índice)')
        if args.todo:
            for s in sin_cubrir:
                print('   ·', normaliza(s)[:160])
        return

    md = Path(args.enrutador).read_text(encoding='utf-8')
    filas = filas_del_enrutador(md)

    def destino_de(tema, contenido):
        citados = documentos_citados(contenido)
        return destino_primario(tema, citados), citados

    if args.anexar:
        escritos = 0
        for tema, contenido, _ in filas:
            primario, citados = destino_de(tema, contenido)
            avisos = [s for s in segmentos(contenido) if AVISO.match(s)]
            if not avisos:
                continue
            if primario is None:
                sys.exit(f'✗ huella: la fila «{normaliza(tema)[:60]}» tiene {len(avisos)} avisos y ningún destino donde mudarlos')
            p = ROOT / primario
            if not p.is_file():
                sys.exit(f'✗ huella: el destino forzado «{primario}» no existe; créalo con su cabecera antes de anexar')
            actual = p.read_text(encoding='utf-8')
            if marca_de_fila(tema) in actual:
                continue   # esta fila ya está mudada (la guarda es por FILA: dos filas pueden compartir destino)
            ordinal = 1 + len(re.findall(r'^## Anexo( \d+)? · La fila del enrutador', actual, flags=re.M))
            p.write_text(actual.rstrip('\n') + anexo(tema, contenido, citados, ordinal), encoding='utf-8')
            escritos += 1
        print(f'→ huella: {escritos} anexos escritos')

    informe = mide(filas, destino_de)
    total_avisos = sum(len(a) for _, _, a, _, _ in informe)
    total_cubiertos = sum(c for _, _, _, c, _ in informe)
    print(f'{"fila":<52} {"destino":<44} {"avisos":>6} {"ok":>4}')
    for tema, primario, avisos, cubiertos, sin_cubrir in informe:
        if not avisos:
            continue
        print(f'{normaliza(tema)[:50]:<52} {(primario or "— SIN DESTINO —")[:42]:<44} {len(avisos):>6} {cubiertos:>4}')
        if args.todo:
            for s in sin_cubrir:
                print('   ·', normaliza(s)[:160])
    pct = 100.0 * total_cubiertos / total_avisos if total_avisos else 100.0
    print(f'\nHUELLA: {total_cubiertos}/{total_avisos} frases con aviso localizables ({pct:.1f} %) · '
          f'{len(filas)} filas, {sum(1 for _, _, a, _, _ in informe if a)} con avisos')
    if args.anexar and total_cubiertos != total_avisos:
        sys.exit('✗ huella: tras anexar sigue habiendo frases sin cubrir — no borres nada del enrutador')


if __name__ == '__main__':
    main()
