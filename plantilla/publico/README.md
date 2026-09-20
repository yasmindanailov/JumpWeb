# `publico/` — el material de la instalación que se sirve tal cual

Los ficheros que el visitante descarga por su URL: fotos del catálogo, vídeos, cualquier medio propio.
**Esta carpeta refleja la estructura del `public/` del producto**, así que `publico/images/attractions/`
va a `public/images/attractions/` y no hay nada que traducir.

⚠️⚠️ **Y refleja esa estructura porque NO es libre**: las rutas de las fotos del catálogo se guardan **en
la base de datos** (`attractions.image`, `zones.image`, `landing_services.image` llevan
`images/attractions/<fichero>`), así que elegir otra carpeta obligaría a migrar datos.

## Por qué existe esta carpeta

`[DECIDIDO owner, 2026-09-20]` (`DECISIONES #663`). El material de un cliente **no vive en `main`**: es
exactamente lo que `#610` separó —lo que sería distinto para un segundo cliente es de la INSTANCIA—. Sale
por el mismo camino que el paquete de tema, que nunca estuvo versionado en el producto.

## Cómo se ponen en su sitio

A mano, como el paquete de tema (`docs/INSTALACION-CLIENTE.md` del producto):

    cp -r publico/. /ruta/al/producto/public/

⚠️ **Con `cp`, nunca con `rsync --delete`**: `public/` del producto tiene también lo suyo —`build/`, sus
hojas, los logotipos de terceros que exige una atribución— y un borrado se lo llevaría.

⚠️ **El despliegue los RESPETA pero no los sube.** `scripts/deploy.sh` los excluye del `rsync`, que es
justo lo que los salva de su `--delete`; el precio es que **una instalación nueva no los recibe**: se
copian la primera vez.

## Lo que hay que saber antes de olvidarse

**Faltar no da error**, y las dos mitades no se comportan igual (medido en `#663`):

- **Las FOTOS del catálogo se notan**: el seeder hace `file_exists()` y guarda `null` si no están, así que
  el catálogo queda sin ellas. ⚠️ Pero **ninguna prueba lo declara roto, y es deliberado**: exigirlo
  pondría el gate rojo en cualquier máquina de desarrollo sin este paquete instalado, que es el defecto
  que `paquete-de-instancia.md` §4.5 llama «la peor forma de romper algo».
- **El VÍDEO es silencioso del todo**: la portada responde 200 sin él y la suite entera sigue verde.

▶ Se comprueba mirando la página, en el §7 de `INSTALACION-CLIENTE.md`.

⚠️⚠️ **Y si algún día estos ficheros vuelven a salir de un repo**: `git rm --cached` los conserva **en ese
momento**, pero el commit registra un borrado — al rebasar, git los restaura desde el remoto y luego
reaplica tu commit, que **los borra del disco**. Se copian fuera ANTES de retirarlos (`#663`).
