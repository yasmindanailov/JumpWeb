#!/usr/bin/env python3
"""Trae el set de iconos Lucide, en una versión FIJA, a `resources/icons/lucide/` (`DECISIONES #683`, `#686`).

El sistema de diseño nuevo pinta sus iconos con `lucide-static@0.544.0` desde jsDelivr (`Icon.jsx`). El
producto no los pide a un CDN en cada visita: los trae una vez, del registro de npm, **comprueba la integridad
del paquete** (el `sha512` que publica el propio registro) y los deja versionados. Así el icono de la web es
el mismo fichero que dibuja el diseño —se comprobó contra jsDelivr—, sin tercero en el camino y sin
depender de que la red responda.

    python3 scripts/traer-lucide.py            # la versión de VERSION
    python3 scripts/traer-lucide.py 0.545.0    # otra: cambia VERSION y el manifiesto

⚠️ No toca `package.json` (es compartido entre carriles) ni pide `npm`: el paquete es solo SVG.
Deja `icons/*.svg`, `LICENSE` (ISC) y `MANIFIESTO.json` (versión, integridad del paquete y un sha256 de todo
el set). `LucideSetTest` comprueba que lo versionado es exactamente lo que dice el manifiesto.
"""
import base64
import hashlib
import io
import json
import os
import shutil
import sys
import tarfile
import urllib.request

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DESTINO = os.path.join(RAIZ, 'resources', 'icons', 'lucide')
VERSION_POR_DEFECTO = '0.544.0'


def bajar(url):
    with urllib.request.urlopen(url, timeout=60) as r:
        return r.read()


def huella_del_set(directorio):
    """Un sha256 de todo el set: nombre y contenido de cada SVG, en orden."""
    h = hashlib.sha256()
    for nombre in sorted(os.listdir(directorio)):
        with open(os.path.join(directorio, nombre), 'rb') as f:
            h.update(nombre.encode() + b'\0' + hashlib.sha256(f.read()).digest())
    return h.hexdigest()


def main():
    version = sys.argv[1] if len(sys.argv) > 1 else VERSION_POR_DEFECTO
    meta = json.loads(bajar(f'https://registry.npmjs.org/lucide-static/{version}'))['dist']
    paquete = bajar(meta['tarball'])
    alg, esperado = meta['integrity'].split('-', 1)
    if base64.b64encode(hashlib.new(alg, paquete).digest()).decode() != esperado:
        sys.exit(f'✗ el paquete no coincide con la integridad que publica npm ({meta["integrity"]})')

    iconos = os.path.join(DESTINO, 'icons')
    if os.path.isdir(iconos):
        shutil.rmtree(iconos)
    os.makedirs(iconos)
    with tarfile.open(fileobj=io.BytesIO(paquete), mode='r:gz') as tar:
        for m in tar.getmembers():
            if m.isfile() and m.name.startswith('package/icons/') and m.name.endswith('.svg') and '/' not in m.name[len('package/icons/'):]:
                with open(os.path.join(iconos, os.path.basename(m.name)), 'wb') as f:
                    f.write(tar.extractfile(m).read())
            elif m.name == 'package/LICENSE':
                with open(os.path.join(DESTINO, 'LICENSE'), 'wb') as f:
                    f.write(tar.extractfile(m).read())

    total = len(os.listdir(iconos))
    manifiesto = {
        'paquete': 'lucide-static',
        'version': version,
        'tarball': meta['tarball'],
        'integridad': meta['integrity'],
        'iconos': total,
        'sha256_del_set': huella_del_set(iconos),
        'licencia': 'ISC',
    }
    with open(os.path.join(DESTINO, 'MANIFIESTO.json'), 'w', encoding='utf-8') as f:
        json.dump(manifiesto, f, ensure_ascii=False, indent=2)
        f.write('\n')
    print(f'✓ lucide-static {version}: {total} iconos, integridad {alg} correcta → resources/icons/lucide/')


if __name__ == '__main__':
    main()
