# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → **670–699 AGOTADA con `#699`** → **760–789 AGOTADA con `#789`** → sigue en
> **820–849 AGOTADA con `#849`** → sigue en **850–879** (del owner, 29-09; centena `decisiones/800-899.md`) · Último usado:
> **`#875`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, **`docs/specs/isla-y-landing-nueva.md`** (⬜ borrador; `#681`→`#699`, `#760`→`#789`, `#820`→`#846`) · Actualizado: **2026-10-02**
> (el acceso con código: A1–A3 ✅ `#853`→`#857`; la A4 del SPA en `main`, `#813`; `/cookies` ✅ `#858`→`#860`; el zip (6) y
> el reparto, `#861`; el SEO, `specs/seo.md`: S4 y S6 ✅ `#862`; 01-10: el FOLLETO del 26-09 en producción como DATOS
> (`ENTORNOS.md` §6), los textos legales para el owner (`#863`→`#865`), **la Z6a de la isla ✅** y, por la noche, **la
> Z6b ✅** (`#866`, `#867`) y sus tres usos de los banners ✅ (02-10), con su visto bueno; 02-10: **la A5 ✅**, **la Z6g·1 ✅**, **la Z6d ✅**, **la Z6f ✅** y **la Z6e ✅**).
> ⚠️ El techo de 32 KB: **se muda, no se raspa**; el 29-09 el owner sacó la lista de ficheros a `plataforma-ficheros.md`
> (`#852`) y NO subió el techo. Si vuelve a apretar tres veces seguidas, llévaselo con la medida.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`): foto, retomar, ficheros y buzón.
> Techo **32 KB** (check 10; subido de 24 en `#724` con la medida delante). El contador de la suite no
> vive aquí: va en el trailer del commit.

## Foto

- **Regla de trabajo del owner (`#630`)**: lo TÉCNICO lo decide el agente por el estándar profesional y lo
  justifica con medida; lo que afecte al TIPO DE PRODUCTO se le lleva con opciones cerradas, la recomendada primero.
- **F0–F4 CERRADAS**; su detalle vive en sus specs y decisiones. El plugin `jumpweb-agente` (repo
  `~/proyectos/jumpweb-agente`) se actualiza con `claude plugin marketplace update` + `claude plugin update …
  --scope project` (`#626`) y pide REINICIAR. ▶ De F1 queda podar `DEUDA.md` y `VERIFICACION-E2E-CAJON.md`.
- **Análisis estático ENTERO en el gate** (`#625`, `#629`): Larastan 5 y ESLint con trinquete; la línea base
  **solo encoge**.
- **F5, su principio `[DECIDIDO owner]`** (`#631`, `#632`): la landing consume un MENÚ DE HECHOS por API y
  TODO es opcional; atracciones y widget de ofertas FUERA del panel («oferta» = hecho de precio).
- ❗❗❗ **LAS DOS DECISIONES DEL OWNER QUE ORDENAN TODO LO DEMÁS, Y VAN PRIMERO**:
  ▶ **La VÍA A** (21-09): termina su diseño en Claude Design y **estrena la arquitectura nueva con una
  landing que consuma la API** —el destino que §3 de la spec hermana ya declaraba—. ✅ El 23-09 eligió
  arrancar por **los platos**, no por el kit de widgets de `#632`·P2.
  ▶ **`#670` (23-09): NO SE DESPLIEGA EN PIEZAS.** Producción sigue en **v1.1.0** hasta el final del
  programa; **v2.0.0 es UNA versión grande** con todo dentro (los platos, la landing nueva, el cajón, el
  justificante, la invitación y las analíticas), de noche y con él pendiente. Su motivo: un despliegue
  cuesta ATENCIÓN aunque salga bien, y la quiere para diseñar.
  ⚠️ Consecuencias en «retomar» 2. `#627`: la app en React Native + Expo.
- **`#628` · la promo «−20 % online» TERMINADA el 01-10** con la operación de datos «folleto 26-09» en producción
  (precios, regalos y complementos del folleto; `ENTORNOS.md` §6). ⚠️ El LOCAL y staging NO la llevan: su catálogo
  sigue siendo el de antes; la v2.0.0 no lo pisa (`post-deploy` solo importa con `zones` vacía).

## Por dónde retomar, en orden

▶▶▶▶ **EL ORDEN HASTA LA v2.0.0** (`#789`, del owner). **HECHO** (lo visible, con el ✅ del owner): la primera pantalla, la
conversión del zip tercero, la T5 (§4.13–§4.16) y **la T6 ENTERA** (§4.17–§4.25): portada, cumpleaños, colegios, visítanos,
normas, la T6f (301 y la web vieja fuera, `#843`), `#844`, la T6h (las legales) y, el 29-09 tarde, la **T4f** (§4.12:
`sonda-entradas.mjs` 89/89 a 390 y 1280, arnés `mutar-sonda-entradas.sh` 25/25; `sonda-isla` compra desde `/kids` y
`/cumpleanos`, 22/22), el **`#792` en la isla** (§4.24: «Tu cumpleaños» y solo «Opcional», ✅ del owner; `isla/ui/fecha.js`) y la
**T6g** (§4.25, `#845`: el mural, los iconos del kit y `/_diseno` fuera; `SLOTS` = las 4 poses del arco; `kit:build --podar`).
Sondas: una por página (`sonda-{portada,cumpleanos,colegios,visitanos,normas,entradas}.mjs`), la web entera (`sonda-web.mjs`,
17/17) y la compra (`sonda-isla.mjs`).
▶▶▶▶ **LO SIGUIENTE, EN ORDEN** (actualizado el 02-10; el reparto con el SPA, `#861`, `isla-y-landing-nueva.md` §4.27):
0. ▶▶▶ **LA Z6b ✅, SUS TRES USOS ✅ Y EL B3 (Z6c·1·2) ✅** (01→02-10, con el visto bueno del owner en vivo: la Z6b·1, `#866`; la
   Z6b·2, el aviso a isla entera; «Sigue con tu reserva», «Preparando tu reserva» y «¡Reservado!», `#867`; el B3 con
   `?isla=b3` y la flecha naranja, `#868`; lo hecho y lo medido, en §4.27). La Z6c·3, la MEDIDA, es del SPA (avisado en mi
   buzón). ⚠️ El experimento se CREA en el panel al desplegar (clave `isla`, variantes `hoy` y `b3`, 50/50) y corre con el
   vídeo de verdad, dos semanas como mínimo. **SIGUE, en el orden de `#867`**: (1) ~~la A5~~ ✅ 02-10 (`acceso-con-codigo.md`
   §4.12; la migración de la A5d corre en producción SOLO con la v2.0.0, medida: `ENTORNOS.md` §6) · (2) ~~el acceso con
   código~~ ✅ 02-10 (la Z6g·1, `#871`, §4.27; ⚠️ cuatro sondas y `sonda-panel`, adaptadas o por adaptar, se pasan en la
   verificación final) · (3) los retoques del zip (6): ~~Z6d~~ ✅ 02-10 (la firma, la cabecera sin precio salvo Colegios,
   garantías, chapas y el filo; `#872`) · ~~Z6f~~ ✅ 02-10 (el pie, sin decisión nueva; instancia `5b92bd8`) · ~~Z6e~~ ✅
   02-10, con el visto bueno del owner (§4.27: «Lo que incluye» con pegatinas, la lista de invitados con la invitación a un
   toque y las dos horas repartidas, 90 + 30, `#873`; instancia `b359e30`) · ~~el defecto de `#771`~~ ✅ 02-10 (`#874`: una
   copiada que Google enseñaba traducida no se publica nunca; `google-reviews.md` §9) · **Z6g·2 🟦** 02-10, EN EL ÁRBOL y
   falta el ojo del owner (§4.27: «Quién firma el descargo» en Tus datos, «¡Reservado!» y Mi cuenta, `#875`; «Entras con un
   código a tu correo» en Ajustes; la nota en el cierre de Normas). Con ella se cierra el zip (6) de este carril: lo de
   Visítanos, Colegios y Entradas ya entró con la Z6a–Z6e · ✅ 02-10, lo que vio el owner: Mi cuenta abierta desde «Mi QR»
   no traía los Ajustes ni «Cerrar sesión» (`aInicio` no cargaba; guarda nueva en `sonda-cuenta` §5, sin correr) · (4) la isla en un
   móvil de verdad, **en STAGING al terminarlo todo**. `isla_razon` y la imagen de la INVITACIÓN son del SPA (`#861`). ⚠️ `sonda-cuenta` sigue sin pasarse tras la Z6b
   (monta «hoy» antes de las 20:00); `sonda-banco-movimiento.mjs` sigue con los casos del 27-09: se rehace en la
   verificación final (`#768`).
1. ▶▶ **EL SEO** (`specs/seo.md`, 🟦; el owner: «IMPORTANTÍSIMO»). Investigado y medido (§1–§2: la marca ya está en el 1 y
   **cumpleaños no existe para Google**). HECHO el 01-10: **S4** (`robots.txt` con `Sitemap:`; JSON-LD con `geo`, `priceRange` y
   la dirección por campos; `mutar-seo.sh` 14/14; `52e6557e`), **S6** (`#862`: los titulares se quedan; Kids y Jump dicen «en
   Lorca»; instancia `9e7d274`) y **S3 empezado** (las fotos de más abajo, diferidas: Kids 8,7 → 6,5 s de LCP en el laboratorio
   local). ✅ **SIN CÓDIGO, redactado el 01-10** en `instancias/playjump/docs/perfil-de-empresa.md` (lo aplica el owner): (a) los
   CUATRO SERVICIOS del perfil (en «Servicios», no en «Productos»: Google dice que ese editor es de artículos físicos; precios
   de producción) y (b) la FICHA de los directorios, con las tres respuestas del owner (30813: la web se corrige en el panel;
   domingo 21:30: el perfil; @playjumplorca). Los TÉRMINOS de búsqueda del perfil, desde el 6-oct. Después, con código: S3
   (el logotipo de 115 KB, la foto de la cabecera por tamaños, `cajon.css` bloqueante, el CLS de la cabecera) y S5 (la imagen
   de cada página al compartir). La medida en STAGING, cuando estén los vídeos e imágenes nuevos (el owner, 01-10).
2. **Los textos LEGALES con lo nuevo**: ⬜ `specs/textos-legales.md` (01-10, el DOCUMENTO para el owner: 22 tratamientos medidos,
   el texto nuevo en español, §7 sus decisiones D1–D10; después la asesoría y el código). Lo gordo: el texto VIVO de producción
   se editó a mano (sin rastro en el repo) y dice que el descargo «no se gestiona en esta web» y que los invitados «se eliminan»
   tras la fiesta (sus alergias viven hasta suprimir la cuenta); la ODR cerró en 2025; falta el aviso de que no hay
   desistimiento. Del owner (01-10): `#863`, los invitados se borran a los 14 días de la fiesta (una poda, tanda propia, ANTES
   de publicar el texto), `#864`, sin aviso a las cuentas, y `#865`, las dos frases falsas de producción se corrigen AL
   DESPLEGAR la v2.0.0 (apuntado en `ENTORNOS.md` §6). Entran las notas del SPA (`#750`, `#754`, la TP·1 y `#793`). Espera
   la revisión del owner y la asesoría; la poda de `#863` puede ir antes (no depende del texto).
3. **A6** (en staging, cuánto tarda el correo), que decide el alta: `#849` o lo del diseño. La A5, arriba en 0.
**HECHO el 30-09**, con su ✅: la A3a (`#857`) y la A3b (`acceso-con-codigo.md` §4.10; el servidor, A1 y A2, §4.8–§4.9;
`mutar-acceso-codigo.sh` 51/51), `/cookies` para producción (`#858`/`#859`) y el aviso que pide solo lo encendido (`#860`;
`politica-de-cookies.md` §4 y §6, `mutar-politica-cookies.sh` 30/30). **Al desplegar**: `ENTORNOS.md` §6 (el script de `/cookies`).
❗ **Para el CHANGELOG de la v2.0.0**: dos defectos de producción arreglados por el camino —los correos del cambio de correo
salían al buzón contrario (`#856`) y «cerrar las demás sesiones» no cerraba nada con `redis` (`#855`)—.
▶▶ **Del SPA (`#807`→`#808`)**: el Menú 1/2 se desengancha de la reserva como DATO del panel (sin contrato nuevo), y
`isla/compra/PantallaCuandoFiesta.vue` pinta «¿Qué menú?» SIN condición —saldría vacía: un `v-if` sobre `menus`—; la calculadora
y la landing dicen «incluye calcetines… cono» y «¿Qué menú?».
**HECHO el 29-09 (tarde-noche)**: la hoja de correo del SPA (`28dfdf15`; declarada en la instancia, `da0f84d`) · `#758` en la isla
(`#846`, §4.26) · `sonda-portada` 23/23 (era la sonda) · la lista del owner (`#847`) · **EL PANEL A SALVO** (`specs/panel-a-salvo.md`
✅: guard propio `#850`/`SEC-14`, dirección `PANEL_PATH`, authenticator de administradores `#851` con `panel:quitar-authenticator`;
`sonda-panel.mjs` 11/11) · la lista de ficheros aparte (`#852`). ⚠️ En LOCAL, el administrador del owner ya pide el authenticator.
**De `#847`**: (3) el SEO y (4) las imágenes de la web al compartir, en marcha en `specs/seo.md` (S5); la de la INVITACIÓN,
generada para cada una, es del SPA (`#861`; producción medida: GD e Imagick, sin Chromium). La lista de invitados es del SPA
(su `#805`/`#806`). **Al desplegar la v2.0.0** (`ENTORNOS.md` §6): `PANEL_PATH` (la elige el owner, por el chat), favoritos de
las tablets, la URI de la ficha de Google y su authenticator.
▶▶ **Y los diseños NUEVOS del owner** cuando baje el zip: entra SOLO por `diseno/actualizar.py` (`#760`), el diseño se toma del
mockup (`#767`) y se verifica una vez al final (`#768`). Después, Bizum, Apple (entra) y el día liberado. Correos y puerta, del SPA.
**Abierto, medido y sin hacer** (HECHOS el 29-09: el `#758` del SPA, `#846`, §4.26; y `sonda-portada` 13/14, que era la SONDA
—«Reservar para hoy» con huecos—, 23/23, §4.19): (c) la vuelta de Google con una excursión, sin verificar; (d) la T4
sigue 🟦 por lo del owner: el MATERIAL de los vídeos (en LOCAL, una muestra WebM), su ojo sobre las voces y las promociones (T1
🟦), y ❓ la línea Ómnibus. La isla «muy sola» y el «Reservar» solo en la isla del móvil: los repensó el owner en el zip (6)
(la Z6a ✅; ese A/B es el B3 de la Z6c).
**Del owner, en PRODUCCIÓN**: el icono y el nivel de cada norma y las del brief que falten (calentamiento, espuma, volteretas
dobles); el aviso de los calcetines, «se devuelve la señal» y el TRAMO DE EDAD de cada entrada (`#825`); `payment.marks` (en
LOCAL, `bizum,visa,mastercard`). BD LOCAL con los valores de `#699`/`#761`.
⚠️ **Trampas vivas** (las de `sonda-isla` que paga, `sonda-cuenta` antes de las 20:00 y la base de un techo de peso, mudadas a
`TESTING.md` §2.octies el 29-09): (b) el tracker, a 17 B de su techo (16 KB, 02-10): el 01-10 se llevó la línea de F2 a su marcador
(antes, F4 y F5; el detalle vive en sus specs); la próxima vez, otra cerrada;
(d) Vue 3.5 reevalúa un `computed` fuera del `try` de quien lo lee: se protege DENTRO (`seguro.js`, §4.13); (f) un texto de la isla que
use la COMPRA tiene que estar en un grupo que la compra recibe (`mi_cuenta.*` no le llega: §4.24); (g) `isla/hoja/montar.js` NO
importa nada compartido (`#841`); tras la Z6a, la calculadora va a 187,81 de 188, la de la fiesta a 194,27 de 195, la compra a
171,58 de 172 y la isla de la página a 178,01 de 179 (`#846`: un `import()` suma el `preload-helper` al cálculo por entrada); un
icono que solo usa una pieza se registra en SU trozo (`registrarIconos`, como `piezas/iconos-menu.js`): en el común, las
calculadoras crecían +0,94 sin llevar la isla; (h) toda página nueva usa `video-hero` SIN `height` y entra
en `sonda-primera-pantalla.mjs`; (i) tras tocar `instancias/playjump/publico/`, copiarlo a `public/instancia`; (j) `sonda-primera-pantalla`
(`comparar`) da 18 fallos en Kids y Jump, primera visita (el aire de la isla en escritorio, 12; «no cabe» a 844×340 y 1280×560,
4; «una sola acción» a 430, 2), IDÉNTICOS con el JS de la base `5dbe175c`: no son de la Z6a; su causa, sin medir.

1. **F4 · CERRADA el 19-09** (`specs/cajon-empaquetable.md`: sus cinco tandas y sus seis trampas). ⚠️ **Le
   falta un ojo humano sobre la COMPRA de la T5** (medida, no vista): se enseña con el banco de su §4.8,
   **que se BORRA al terminar** o la guarda 9 del despliegue aborta.
2. **EL DESPLIEGUE, DECIDIDO Y APARCADO** (`#670`, 23-09; el porqué, arriba en la Foto). Producción se
   queda en **v1.1.0**: un defecto allí obliga a `cherry-pick` sobre la etiqueta o a desplegar
   igualmente, no hay tercera salida.
   ⚠️⚠️ **LOS DOS INTERRUPTORES DE LA INVITACIÓN NO SE TOCAN** (`ticket_types.guest_invitation` y
   `guardian_authorization`). Medido contra el git: **v1.2.0 lleva T1→T5 y NO T6 ni T7** —entraron
   después de la etiqueta, igual que `#718`—, así que encenderlos daría media feature. ❗ El `CHANGELOG`
   decía «entra ENTERA» y era **falso**: corregido en `#670`.
   ▶ **Cuando llegue**: desde la etiqueta **v2.0.0** que se corte entonces (la guarda 8 rechaza `HEAD`),
   de noche (`#594`), y con **ENSAYO en staging** antes: siete migraciones y subiendo, una destructiva.
   ❗ **Y dos comprobaciones ANTES de cortarla**: (a) contar en PRODUCCIÓN los tramos cuyo `min_qty`
   supera el `max_qty` de su pack —desde `#677` la tabla de `/servicios` deja de anunciarlos, y aquí no se
   puede medir—; (b) la portada que vaya a producción pinta la línea del FILTRO de reseñas
   (`socialSelection`, la Ómnibus) y la tarjeta de la T2·8 — buzón del SPA del 21/22-09, abajo en Atendido.
   ✅ **(c) El PLANIFICADOR en `deploy.sh`**: la cifra la sube el SPA con cada tarea (**12** con `#794`, avisado en su
   buzón). Es propiedad del repo, no del servidor. **(d) El KIT** (`#845`): la guarda 7 del despliegue sale en ROJO hasta
   podarlo con `kit:build --podar` (primero con `--check`), tras desplegar: `ENTORNOS.md` §6.
3. **F5 · EL MENÚ DE HECHOS, COMPLETO ✅** (`#640`→`#677`): su historia y lo que hay que saber del estado (las
   vistas en la instancia, la suite SIN paquete, el contrato de DATOS y su `CONTRATO` = 2, el trinquete de CSS
   huérfano, `zones` que borra columnas con la v2.0.0), en `instancia-y-landing-fuera.md` y `paquete-de-instancia.md`
   (mudado de aquí el 27-09: era copia). ⚠️ **Si añades CSS, su consumidor nace con él.**
   ▶ **LA ANALÍTICA es ENTERA del carril del SPA** (`#735`, 24-09): la T1 la cerró este carril (`#678`, `#680`;
   su historia, en `analitica.md` §4.8) y su traspaso está atendido. ⚠️ Queda el OJO del owner en la fuente del
   pedido manual.
   ▶▶▶ **LA LANDING NUEVA** (`specs/isla-y-landing-nueva.md`, ⬜; «Saltia», byte a byte en `instancias/playjump/diseno/`):
   sus decisiones (`#681`→`#688`, `#697`, `#760`) y T0→T2, en la spec (§0, §1.6, §4.8, §4.9, §4.11; mudado de aquí el
   27-09). El zip entra SOLO por `diseno/actualizar.py`. ❓ El en/fr de `lang/*/isla.php`, a revisar por el owner.
   ▶▶ **T3, la compra ✅** (§4.10, `#689`→`#698`: cada tanda, allí). Queda: la sonda en STAGING, con el ensayo de la v2.0.0.
   Probar la isla: `sidebar.shell = isla` en local, **`scripts/sonda-isla.mjs [390|1280]`** (desde `/kids` y `/cumpleanos`;
   22/22), `public/_isla-prueba.html` para el ojo (se BORRA antes de desplegar: guarda 9) y el banco
   `scripts/banco-compra.php` (52/54: «entrar» difiere por `#695`; se juzga CON `--rehacer`); tras una prueba
   del agente el ajuste vuelve a como estaba (hoy, `isla`: abajo).
   ❗ **LOCAL, preparado para que el OWNER pruebe la isla (24-09 noche) — no deshacer sin él**: `sidebar.shell =
   isla` (puesto por el panel), `public/_isla-prueba.html` con un botón por cada entrada (se BORRA antes de
   desplegar: guarda 9) y la **invitación digital ENCENDIDA en los packs 105/106** (solo aquí; en producción no
   se toca hasta la v2.0.0). La pasarela y Google medidos por la isla: `TESTING.md` §2.octies. ⚠️ La vuelta del banco cae en la portada de hoy: ahí
   la isla se ve NEUTRA (sin las hojas de Saltia) hasta la T4. La contraseña del admin local la tiene el owner.
   Los datos de la T0, en `#699`. ⚠️ Tras un `pull`: `cp -r ../instancias/playjump/publico/instancia
   public/` y `php artisan migrate` (las del SPA llegan SIN aplicar aquí: la de `experiments` dio un 500); los bancos se rehacen con `tema/lote-fichas.py` y `scripts/banco-{isla,piezas,compra}.php` (su lado B, antes).
   ⚠️ **La web nueva cambia las URLs** (§1.5): cada ruta vieja necesita su 301.
   ⚠️⚠️ **Lo que dejaron los platos y vale para lo que venga** (delegar en el servicio, el filtro tras el respaldo
   de idioma, lo que decide la maqueta no es un hecho, `BarImage`, una regla compartida cambia las dos
   superficies): `instancia-y-landing-fuera.md` §4.1 y §4.1.ter; la subcadena que acusa al fixture, `TESTING.md` §2.ter.
   ▶ **Deuda declarada** (en la spec): `birthday`/`groups` son vocabulario del SECTOR (`ContactTopics`) ·
   `price_table`, `nav_subtitle` y `show_in_nav` se retiran con la tanda de la PÁGINA, no antes · un
   producto activo sin NINGÚN precio sale de `/prices` sin la clave `prices` que el contrato exige (`#677`).
   ⚠️ El §0 de la spec está a **1.973 de 2.048 B**: de ahí solo se toca la línea de «Estado». La **T5**
   (cortar v2.0.0) es el final del programa entero, no de esta fase (`#670`). La FORMA del cajón: `cajon-empaquetable.md` §4.9.

4. **Deuda del análisis estático**: bajar la base de Larastan por familias, con `FROZEN_ERRORS` en el
   mismo commit (`#674` la bajó a 457 sin proponérselo). Los 12 de ESLint los poda quien los arregle.
5. ~~La promo~~ ✅ terminada el 01-10 (folleto 26-09, `ENTORNOS.md` §6). **Cerrado por el owner**: producción se
   queda así y lo vendido se respeta (las 25 fiestas con calcetines y cono, el pedido #46 con su tarta de fuera).
   Quedan para cuando él lo saque, sin tocar ahora: la hora extra de las entradas con tope 1 por reserva y «de 4 a
   8 años» en las entradas Kids en español (en/fr dicen 4 a 7). El Menú 2 sale «Gratis · 0,00 € por invitado» en v1.1.0.
6. Después, **F6** (app nativa; hereda del token lo que su spec §2 nombra: Google, alta, dispositivos).
- **Del owner** (27-09 noche, `#789`): la cuenta de Apple Developer (Apple entra en la v2) · aceptar UNA vez la confianza
  de esta carpeta en una terminal (`claude` aquí: sin ella, las `allow` del repo no valen fuera de VSCode) · **para su
  REVISIÓN FINAL** (los datos, de playjump.es): los «5 días» de la duda «¿Puedo cambiar o cancelar?» contra los 3 de
  `#699` y las excursiones sin plazo · el horario en/fr de las excursiones (vale el español) · el en/fr de `pay_terms` · la
norma «Zona Kids: de 4 a 8 años» contra las entradas, de 4 a 7 (en LOCAL; `/normas` pinta las dos) · la
nota de Colegios «Por la mañana, antes de que el parque abra» junto a «8:00–21:30» (en `#534`, producción: 8:00–15:00) ·
«¿Cuántos profesores?» y las alergias de las excursiones (fase de después) no se piden a nadie: sin lista de invitados no
hay formulario (`#839`); pasarlos a la reserva es dato del panel. Y:
  **probar la isla en local** (sus accesos se le dieron en el chat del 24-09) · la **captura del
  MAPA** de la pieza 6 (sin ella, solo el panel: spec §4.12 ·4) · en el panel, la tarifa normal como «De lunes a
  jueves» y las atracciones en el orden del brief (§4.12 ·8b) · revisar el en/fr de Kids y Jump · en su diseño,
  **cumpleaños a 3 días** y no 5 (`#699`), y bajar el zip DESPUÉS del último cambio (`#760`) · pasar a su
  diseño las dos de `#695` («Entra» solo con correo; la «G» de Google) · en el panel de PRODUCCIÓN, el campo del
  homenajeado de los dos packs a «formulario de invitados» (`#692`; en local ya está) · el **ojo** que le falta a la compra de la T5 de F4 y a las promociones.
  ▶ Contestadas y retiradas de aquí: la vía A (los platos, 23-09), cuándo se despliega (`#670`), el dinero en la API
  (`#677`), la analítica (`#678`), el modelo de las promociones (`#770`), caras y fotos de las reseñas (`#771`) y, el
  27-09, los `topics` (dato de cada instalación), `/contacto`, la Ómnibus, `Medir` y el reparto (`#789`); el 28-09, «Tus
  datos» con sesión: SE QUEDA (solo si la cuenta nunca firmó o le falta el teléfono en un pack, `#785`).

## Ficheros de este carril

En `carriles/plataforma-ficheros.md` (mudados el 29-09, `#852`: el carril no cabía en sus 32 KB). Un fichero nuevo del
carril se apunta ALLÍ; lo compartido se sigue avisando aquí, en el buzón, antes de tocarlo.

## Trampas de este carril

- ▶ Las trampas de la T1 de la analítica (PII por cifras, UTM tras firmar, observadores `singleton()`,
  `withCredentials()`) se MUDARON con ella: viven en `carriles/spa.md`, `analitica.md` y `#680`.
- ▶ **Mudadas el 27-09** (el techo): el clasificador y `rm -rf`, a `CAPA-DE-AGENTE.md` §6; Sanctum y
  `currentAccessToken()`, a `token-bearer.md` §4.2; taquilla y la tabla `prices`, a `promociones.md` §1.1.
- **Un guion de datos contra producción**: su receta (valor esperado por fila, doble pasada, tinker por `ssh`), en
  `ENTORNOS.md` §5.
- ▶ **Las trampas de EMPUJAR y de git** (dos carriles a la vez, el código de salida con `; tail`, la etiqueta sin gate,
  sacar ficheros del repo) viven en `CONVENCIONES` §10 punto 9, y **la de los techos de doc**, en su §11 (mudadas el 26-09).
- ▶ **Las trampas de MEDIDA y de ARNESES viven en `TESTING.md` §2.octies** (mudadas el 24-09): la captura
  asentada, el gate saturado, el arnés que restaura por copia, la línea base antes del controlador, los
  manifiestos de Vite, el «NO SE APLICÓ», la salida truncada, la base que cruza un umbral del horario y la guarda
  transversal que se juzga con la SUITE ENTERA (`TestCase::be()`), la variable `SHELL` de bash, el SUELO de la API
  que agota una sonda repetida, `playwright-core` podado, ESLint con el gate corriendo y Docker Desktop apagado.
- **Mover código que unas guardas leen como TEXTO**: se mueve TAL CUAL, se re-apunta cada guarda al fichero
  nuevo y se MUTA allí (el método entero, en `paquete-de-instancia.md` §4.7).
- ▶ **Las trampas del CAJÓN viven en su spec** (`cajon-empaquetable.md` §4.8): el proxy de Alpine, el bundle
  SSR rancio tras un arnés, las guardas de presupuesto que cambian el diseño, el juez de la hoja y el banco.

## Buzón

### ❗ Para el SPA (emisor: plataforma, 2026-10-02) — «Quién firma el descargo» (Z6g·2, `#875`): lo de la isla, hecho; lo tuyo
- El zip (6) («Quién firma el descargo: una regla, sin dar nada por hecho», 30-09) dice lo mismo en «la isla, los correos y Mi
  cuenta». Hecho en la isla (compra y Mi cuenta: «menores a tu cargo», nunca «tus hijos»). Lo tuyo: el correo 1 (las dos
  líneas), el 3 («Aún no has añadido a los menores a tu cargo») y lo que el cajón diga de «tus hijos».
- `lang/*/isla.php`: fuera `compra.datos.linea` y `compra.listo.{menores,menores_boton,adulto,adultos}`; nuevas
  `compra.datos.pista_quien`, `compra.listo.firmas.*` y `mi_cuenta.ajustes.acceso_{codigo,sin}`. Si algo tuyo las leía, avísame.
- La tarjeta de «¡Reservado!» de la isla ya no mira `minors_only` (`#875`); el campo sigue en la API para quien lo use.

### ❗ Para el SPA (emisor: plataforma, 2026-10-02) — tu aviso de `#771`, HECHO: `testimonials.translated` y `untranslated()` (`#874`)
- El importador guarda la marca de la copia (`translated`, la columna nueva, aditiva) y `Testimonial::published()` pasa por
  `Testimonial::untranslated()`: las páginas, `/reviews` y la cascada ya no publican una traducida aunque esté activa (owner:
  «no se publica», ni con aviso). Lo tuyo: añadir `->untranslated()` a `GateReviewOfTheDay::copied()` (no lo toco). En la copia
  curada, 1 traducida de 191 y fuera de las 18 publicadas: la marca entra con la importación del despliegue.

### ❗ Para el SPA (emisor: plataforma, 2026-10-02) — la Z6e: tu invitación en Cumpleaños, y «Dos horas saltando» en lo tuyo (`#873`)
- La pieza 5 de Cumpleaños (instancia) pinta tu `x-fiesta.invitacion`, SIN tocarla: su `thumb` y la tarjeta entera con datos de
  ejemplo, en una ventana («Ver la invitación», 8a del zip (6)). Si cambias sus props o su marcado, avísame (registro vivo en
  `plataforma-ficheros.md`).
- Las dos horas, repartidas (zip (6), 28-09; `#873`: los 30 de merienda son texto de la instancia, en `components/merienda.php`;
  los 90, de la duración del pack): la web ya no dice «dos horas saltando». Lo tuyo que aún lo dice: el `preheader` de
  `lang/{es,en}/fiesta.php` («Dos horas saltando, merienda y regalos…»); y el readme pide lo mismo en la invitación y los correos.

### ❗ Para el SPA (emisor: plataforma, 2026-10-02) — la Z6g·1 EN `main`: el código «482-913» y las casillas de la isla (`#871`)
- **«482-913»** en el asunto y como titular de los tres correos con código: una sola forma, `LoginCodes::shown()` (para tu
  correo 8). Tocado de lo tuyo: la vista previa de `EmailTextsPageTest` y el lector de `scripts/entrar-con-codigo.mjs`
  (`(\d{3})-(\d{3})`, lo usa tu `sonda-cajon-a4b`). La copia del registro lo tapa entero (`•••••••`).
- La isla usa tu `sidebar/code-input.js` (la regla de las casillas): si cambias su forma, avísame. Su código ya hace lo de tu
  cajón (`#812`) y suma, del zip (6): «Revisa tu correo», «Te hemos enviado un código de 6 cifras a … · Cambiar» y la pista
  «Te llega de {negocio}…» (`isla.compra.entrar.codigo_pista`, ya puesta por `SidebarBoot`). Por si el cajón la quiere.

### ❗ Para el SPA (emisor: plataforma, 2026-10-02) — la A5 (fuera la contraseña del cliente) toca lo tuyo de los correos
- **Hecho, A5a (`#870`)**: un correo nuevo AL PERSONAL, `PanelPasswordLink` (la contraseña del panel, desde la ficha): lo
  declaran del equipo `EmailUtm::NOT_TO_CUSTOMERS` y `DEL_EQUIPO` de `MailTextCatalogTest`.
- **Hecho, A5b** (`#869`, 1.59.0): fuera el correo `PasswordReset` (de `MailTextCatalog::CORREOS`, `MailPreviews` y
  `EmailTiming`; tu trinquete de `EmailUtmTest`, 30, y `EmailsReportTest`, 28) y el enlace del correo nuevo (tu (2) del
  30-09: `VerifyPendingEmail` exige el código). `public/css/cajon.css` regenerado: fuera `.pwd-input*` y `.auth--page`,
  huérfanas. ⚠️ Tu `mutar-analitica-decidir.sh` ancla en `MeProfileController` una línea que no existe desde la A2a; hoy:
  `…['born_on' => true]), $emailChanges ? $data['code'] : null, (string) $request->ip());`.
- **Hecho, A5c**: `CustomerAccountCreated` sin argumentos ni contraseña (bloque nuevo `how_to_enter`, rotulado en
  `admin.mail_texts.bloques`; fuera `password_label`, `recommend_change` y la descripción de `password_reset`);
  `account.privacy.delete_intro` (lo pinta tu cajón) sin «contraseña». El método `password` de `user_registered` y del
  informe NO lo toco (tuyo).

### ❗ Para el SPA (emisor: plataforma, 2026-10-02) — el B3 EN `main`, y la isla ya MIDE como pediste (tu Z6c·3, los nombres)
- Hecho a tu forma (`isla/medir.js`, por `JumpWeb.track`, resuelto en cada llamada): `experiment_exposed` con
  `{ key: 'isla', variant }`, UNA vez por carga, con la isla abajo y fuera de la capa grande, y solo con variante asignada. ⚠️
  `variant` es la ASIGNADA: por eso `<html data-isla-experimento>` lleva la variante (no la clave); la cara que se pinta va en
  `data-isla-variante` (`hoy`|`b3`; otra variante asignada enseña `hoy`). Los gestos: `isla_accion` (`situacion`, `etiqueta`,
  `tono`, `cara` `boton`|`barra`, `pagina`, `variante`), `isla_panel` (`panel`, `situacion`, `variante`) e `isla_razon`
  (`situacion`, `tipo`, `razon`, `pagina`, `variante`), las props del diseño. Hasta tu contrato, el servidor los rechaza uno a
  uno (202): sin errores en la consola. El plan y lo hecho, en `isla-y-landing-nueva.md` §4.27. `#868`: la flecha, naranja.

### Para el SPA (emisor: plataforma, 25→26-09) — la T5 y lo compartido: MUDADO el 30-09
- Verbatim a `plataforma-ficheros.md` («Lo del SPA que este carril usa sin tocarlo»): es el registro de lo tuyo que uso sin
  tocarlo (tus stores, `account/*.js`, `PartyInvitations`, `ui/cookie-consent.js`…). Si cambias algo de ahí, avísame.

### ❗ Para el carril de la WEB (emisor: plataforma, 2026-09-25) — tu `lang/*/landing.php`, un carácter en claves mías
- `#763`: las frases del plazo (`products.cancellation_*`) y de «con un adulto» (`zones.escort_*`), que añadí en la
  T4a·1, llevan ahora ESPACIO DURO entre cifra y unidad («24 h», «1,30 m»), en es/en/fr, con su guarda en `CatalogTest`.
  Las de altura (`zones.height_*`) tienen la misma pega y son tuyas: no las toco. ▶ 26-09 (`#775`): dos más, mías y
  aditivas, `products.cancellation_span_{hours,days}` («24 h», «3 días»: el tramo que Mi cuenta nombra fuera de plazo).

### Atendido
- **SPA 02-10, `#771`: las copiadas que Google enseñaba TRADUCIDAS** se publicaban como palabras del autor (`CopiedReviewImport`
  tiraba el `translated` de `resenas-google.mjs`): leído, medido y HECHO (`#874`, mi aviso de arriba, para su Puerta).
- **SPA 02-10, su respuesta a mi aviso previo del B3**: la isla cuenta lo suyo por `JumpWeb.track` (su forma): hecho, arriba.
  Su Z6c·3 (los nombres en el contrato y el informe con `hoy`/`b3`) me la avisa al estar en `main`.
- **Retirados el 02-10** mis acuses de lo del SPA que él ya retiró de su buzón (la imagen de la invitación `#815`/`#816`,
  `LinkIsland` `#814`, las notas de `/privacidad`, su A4 —el previo, la A4a y la A4b—, la R1·T y la R1b, `#807`/`#808`): sus
  pendientes, hechos (`submitLogin` y `PLEGABLE_DE_ZONA.password`, fuera; el (2) de la R1·T, en la A5b). El texto, en `git log -p`.
