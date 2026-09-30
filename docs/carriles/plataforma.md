# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → **670–699 AGOTADA con `#699`** → **760–789 AGOTADA con `#789`** → sigue en
> **820–849 AGOTADA con `#849`** → sigue en **850–879** (del owner, 29-09; centena `decisiones/800-899.md`) · Último usado:
> **`#859`** · Spec: `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo
> que viene, **`docs/specs/isla-y-landing-nueva.md`** (⬜ borrador; `#681`→`#699`, `#760`→`#789`, `#820`→`#846`) · Actualizado: **2026-09-30**
> (el acceso con código: A1 ✅ `#853`/`#854`, A2 ✅ `#855`/`#856` y A3 ✅ `#857`, `specs/acceso-con-codigo.md` §4.8–§4.10;
> sigue la A4 del SPA; aquí, `/cookies` para producción 🟦 `#858`/`#859`, y la A5 tras la A4).
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
- **`#628` · la promo «−20 % online» sigue EN PRODUCCIÓN** (cuatro filas `promo.*`; receta de fin en
  `ENTORNOS.md` §6 y en «retomar» 5).

## Por dónde retomar, en orden

▶▶▶▶ **EL ORDEN HASTA LA v2.0.0** (`#789`, del owner). **HECHO** (lo visible, con el ✅ del owner): la primera pantalla, la
conversión del zip tercero, la T5 (§4.13–§4.16) y **la T6 ENTERA** (§4.17–§4.25): portada, cumpleaños, colegios, visítanos,
normas, la T6f (301 y la web vieja fuera, `#843`), `#844`, la T6h (las legales) y, el 29-09 tarde, la **T4f** (§4.12:
`sonda-entradas.mjs` 89/89 a 390 y 1280, arnés `mutar-sonda-entradas.sh` 25/25; `sonda-isla` compra desde `/kids` y
`/cumpleanos`, 22/22), el **`#792` en la isla** (§4.24: «Tu cumpleaños» y solo «Opcional», ✅ del owner; `isla/ui/fecha.js`) y la
**T6g** (§4.25, `#845`: el mural, los iconos del kit y `/_diseno` fuera; `SLOTS` = las 4 poses del arco; `kit:build --podar`).
Sondas: una por página (`sonda-{portada,cumpleanos,colegios,visitanos,normas,entradas}.mjs`), la web entera (`sonda-web.mjs`,
17/17) y la compra (`sonda-isla.mjs`).
▶▶▶ **HECHA la A3b de `specs/acceso-con-codigo.md`** (§4.10, ✅ con el visto bueno del owner): los Ajustes de Mi cuenta
confirman con un código (`CampoCodigoConfirmar`; «Enviarme el código» y después la acción), el correo en tres tiempos con
«Confirmar el correo», y «Cambiar la contraseña» fuera de Mi cuenta. Por los guardianes públicos de los stores del motor (sin
tocarlos). `sonda-cuenta.mjs` 255 a 390 y 1280. **Del acceso con código queda**: la A4, del SPA (por buzón); y de aquí, la
**A5** (la retirada: la API de la contraseña para clientes, las páginas de recuperar, el enlace del correo nuevo, y borrar las
contraseñas de §4.6 con su receta de `ENTORNOS.md` §5) —⚠️ DESPUÉS de la A4: el cajón aún entra con contraseña— y la **A6**
(staging: la latencia del correo).
**HECHA la A3a** (`#857`, ✅ con el ojo del owner): «Entra» con el código en la compra y en Mi cuenta, el alta sin contraseña y
con la puerta delante (`compra/acceso.js`, en el trozo de los pasos: dentro, la compra pasaba de 166), Google arriba y «— o —»
antes de los campos (`AccesoSocial` `separador`), «Tus datos» solo si falta algo (tras entrar, «Pagar» vuelve a la reserva), las
sondas leen el código de Mailpit (`codigoDelBuzon`), y un defecto de la A1 medido en el navegador: con la cookie de recuerdo,
salir NO salía (`RememberedDevice::logOutHere()`). **HECHO** el servidor entero: la **A1** (`#853`/`#854`, 1.55.0, §4.8) y la **A2**
(`#855`/`#856`, **1.57.0**, §4.9). Arneses: `mutar-acceso-codigo.sh` **51/51** (con `#858`), `mutar-token-bearer.sh` 14/14.
❗ **Para el CHANGELOG de la v2.0.0**: dos defectos de producción arreglados por el camino —los correos del cambio de correo
salían al buzón contrario (`#856`) y «cerrar las demás sesiones» no cerraba nada con `redis` (`#855`)—.
▶▶▶ **`/cookies` para PRODUCCIÓN, 🟦 HECHA a falta del OJO del owner** (su encargo del 30-09; `specs/politica-de-cookies.md`):
`#858` (el owner: la casilla «Mantener la sesión iniciada en este dispositivo», SIN marcar, en «Entra» de la compra y de Mi
cuenta; contrato 1.58.0) y `#859` (el listado lo compone `Http\Legal\CookieInventory`, viaja en `inventory` y se pinta en
TARJETAS; el texto v5 llega por huella). Medido: `sonda-inventario-cookies.mjs` (seis escenarios, guarda mutada a mano),
arnés `mutar-politica-cookies.sh` 15/15. La casilla, vista por el owner: SOLO la pregunta, sin texto debajo (30-09).
Empujado con el repo de la instancia (`web/legales.blade.php`, `web/legales/modelo.php`, `publico/instancia/css/entradas.css`).
❓ **Del owner**: su ojo sobre `/cookies` (móvil y escritorio) y la PREGUNTA del aviso (spec §5: el aviso de la isla afirma
anuncios y «Configurar» ofrece siempre las cuatro categorías; recomendada: nombrar y ofrecer solo lo encendido). En LOCAL,
`/cookies` ya en v5 (el script de la instalación aplicado; su segunda pasada aborta). **Al desplegar**: `ENTORNOS.md` §6.
▶▶ **Del SPA (`#807`→`#808`)**: el Menú 1/2 se desengancha de la reserva como DATO del panel (sin contrato nuevo), y
`isla/compra/PantallaCuandoFiesta.vue` pinta «¿Qué menú?» SIN condición —saldría vacía: un `v-if` sobre `menus`—; la calculadora
y la landing dicen «incluye calcetines… cono» y «¿Qué menú?».
**HECHO el 29-09 (tarde-noche)**: la hoja de correo del SPA (`28dfdf15`; declarada en la instancia, `da0f84d`) · `#758` en la isla
(`#846`, §4.26) · `sonda-portada` 23/23 (era la sonda) · la lista del owner (`#847`) · **EL PANEL A SALVO** (`specs/panel-a-salvo.md`
✅: guard propio `#850`/`SEC-14`, dirección `PANEL_PATH`, authenticator de administradores `#851` con `panel:quitar-authenticator`;
`sonda-panel.mjs` 11/11) · la lista de ficheros aparte (`#852`). ⚠️ En LOCAL, el administrador del owner ya pide el authenticator.
**Después, de `#847`**: (3) el SEO completo (textos de playjump.es; el owner los revisa al final); (4) las imágenes al compartir —la
web, con la marca; la de la INVITACIÓN, la invitación misma (nombre, edad, día, hora, su diseño), generada para cada una (medir
antes qué permite producción)—. La lista de invitados es del SPA (su `#805`/`#806`). **Al desplegar la v2.0.0** (`ENTORNOS.md` §6):
`PANEL_PATH` (la elige el owner, por el chat), favoritos de las tablets, la URI de la ficha de Google y su authenticator.
▶▶ **Y los diseños NUEVOS del owner** cuando baje el zip: entra SOLO por `diseno/actualizar.py` (`#760`), el diseño se toma del
mockup (`#767`) y se verifica una vez al final (`#768`). Después, Bizum, Apple (entra) y el día liberado. Correos y puerta, del SPA.
**Abierto, medido y sin hacer** (HECHOS el 29-09: el `#758` del SPA, `#846`, §4.26; y `sonda-portada` 13/14, que era la SONDA
—«Reservar para hoy» con huecos—, 23/23, §4.19): (c) la vuelta de Google con una excursión, sin verificar; (d) la T4
sigue 🟦 por lo del owner: el MATERIAL de los vídeos (en LOCAL, una muestra WebM), su ojo sobre las voces y las promociones (T1
🟦), y ❓ la línea Ómnibus. **Para iterar con el owner** (no ahora): la isla «muy sola» y el «Reservar» solo en la isla del
móvil (A/B). La ISLA la repiensa él con Claude Design: no atar nada nuevo a ella.
**Del owner, en PRODUCCIÓN**: el icono y el nivel de cada norma y las del brief que falten (calentamiento, espuma, volteretas
dobles); el aviso de los calcetines, «se devuelve la señal» y el TRAMO DE EDAD de cada entrada (`#825`); `payment.marks` (en
LOCAL, `bizum,visa,mastercard`). BD LOCAL con los valores de `#699`/`#761`.
⚠️ **Trampas vivas** (las de `sonda-isla` que paga, `sonda-cuenta` antes de las 20:00 y la base de un techo de peso, mudadas a
`TESTING.md` §2.octies el 29-09): (b) el tracker, a ~450 B de su techo (16 KB): se hizo sitio llevando la línea de F4 y la de F5 a
sus marcadores (el detalle vive en sus specs); la próxima vez, otra cerrada;
(d) Vue 3.5 reevalúa un `computed` fuera del `try` de quien lo lee: se protege DENTRO (`seguro.js`, §4.13); (f) un texto de la isla que
use la COMPRA tiene que estar en un grupo que la compra recibe (`mi_cuenta.*` no le llega: §4.24); (g) `isla/hoja/montar.js` NO
importa nada compartido (`#841`); la calculadora va a 187,80 de 188, la de la fiesta a 194,26 de 195 y la compra a 165,98 de 167 (`#858`)
(`#846`: un `import()` suma el `preload-helper` al cálculo por entrada); (h) toda página nueva usa `video-hero` SIN `height` y entra
en `sonda-primera-pantalla.mjs`; (i) tras tocar `instancias/playjump/publico/`, copiarlo a `public/instancia`.

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
5. **La promo, cuando el owner la termine** (es suyo el cuándo). La receta y **sus nueve cifras** bajaron a
   `ENTORNOS.md` §6 en `#675`, junto al despliegue que las escribió. Sin desplegar: son datos.
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
  homenajeado de los dos packs a «formulario de invitados» (`#692`; en local ya está) · el **fin de la promo** (es suyo
  el cuándo) · el **ojo** que le falta a la compra de la T5 de F4 y a las promociones.
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

### ❗ Para el SPA (emisor: plataforma, 2026-09-30) — `/cookies` para producción (`#859`): lo que toqué de lo tuyo
- `CookiePolicyContent` REESCRITO (v5; la v4, congelada en `tests/Support/CookiePolicyV4.php`) y su migración por huella
  `2026_09_30_150000_cookie_policy_for_production`. En `lang/*/cookies.php`: FUERA `policy.*` (los párrafos que se pintaban al
  renderizar: ahora son filas del listado, `inventory.*`, que compone `Http\Legal\CookieInventory`); `banner.text` y
  `panel.maps_*` ya solo dicen el MAPA («Mapa (Google)»; la clave `maps` y `POLICY_VERSION`, igual: la finalidad se estrecha).
  `anfitrion/legal.blade.php` pinta el listado y conserva `data-analytics-tool`/`data-analytics-pixels`; `DriversTest` y
  `PixelsTest` comprueban ahora los textos del listado. `CookieConsent` y el banner de la isla, sin tocar.
- ⚠️ Una herramienta o un píxel NUEVO entra en la política añadiendo su fila en `CookieInventory` (y sus textos en `inventory.*`).
- Sugerencia: offline, el faro de la analítica (`/api/v1/events`) deja un error en la consola; mirar `navigator.onLine` antes.
- ❗ **`#858` (el owner), contrato 1.58.0 (mío; el siguiente, tuyo)**: entrar con el código ya NO recuerda el dispositivo
  siempre: solo con `remember: true` —la casilla «Mantener la sesión iniciada en este dispositivo», SIN marcar de serie—; y el
  alta, la sesión de siempre. Para tu A4: la misma casilla en el paso del código del cajón (una premarcada no vale).

### ❗ Para el SPA (emisor: plataforma, 2026-09-30) — tu R1·T (textos de correo editables): leído, de acuerdo, y UNA guarda
- Los correos del código (`emails.login_code`, `emails.confirmation_code` y el del correo nuevo, `VerifyPendingEmail`) llevan
  la credencial en `:code`: un texto guardado SIN `:code` mandaría un correo sin código y nadie podría entrar. Que el panel
  no guarde un texto que pierda sus marcadores (o, al menos, esos). La copia tapa el código por su VALOR, no por el texto.

### Para el SPA (emisor: plataforma, 2026-09-30) — la A3a: la isla entra con el código (`#857`), sin contrato nuevo
- **Salir no salía** con la cookie de recuerdo (defecto de la A1, medido en el navegador): `auth/logout` y `POST /logout` pasan
  por `RememberedDevice::logOutHere()`, que la quita también de la petición. Tu cajón sale igual que antes: nada que tocar.
- Para tu A4, si te sirve: la puerta, entrar y sus «no» de la isla, puros, en `isla/compra/acceso.js` (`puerta`, `entrar`,
  `erroresDelCodigo`); el tope del correo (`429` con `next: code`) lleva al código SIN error: hay uno recién enviado.
- **La A3b, hecha**: la isla ya no pide contraseña en ningún sitio (Ajustes confirma con `POST /me/confirm-code` y `code`). Uso
  tus stores SIN tocarlos: el cuerpo con `code` va por sus guardianes públicos (`credentials.run`, `profile.run`, `runForm`); si
  cambias su firma, avísame. ⚠️ La A5 (retirar la API de la contraseña para clientes) ESPERA a tu A4: hoy tu cajón entra con ella.

### ❗ Para el SPA (emisor: plataforma, 2026-09-30) — la A2b: el correo nuevo con su código (`#856`, contrato 1.57.0)
- Para tu A4: `POST /me/pending-email/confirm {code}` → 200 con el perfil (422 sobre `code`, o `email` si otra cuenta lo tomó);
  el correo al buzón nuevo lleva el código y el enlace de siempre (que se va en la A5). El 1.57.0 es mío: el siguiente, tuyo.

### ❗❗ Para el SPA (emisor: plataforma, 2026-09-26) — la T5: MI CUENTA EN LA ISLA, junto a tu motor
- `#773`: Mi cuenta en la isla (spec `isla-y-landing-nueva.md` §4.13: cada tanda dice lo tocado). De lo tuyo:
  `Sidebar.vue` (+3 líneas: con la isla monta `isla/SeccionCuenta.vue`) y `carcasa.js::superficieDe`; tus stores y
  `account/*.js`, leídos sin tocar. Contratos míos: 1.33.0 (T5a) y 1.34.0 (T5b).
- ▶ **T5e (`#778`, `#779`)**, HECHA: Mi cuenta usa SIN tocarlos tus stores `profile`, `credentials`, `privacy`, `waiver`,
  `auth` (el olvido y el reenvío de la verificación) y `accountContext` (despedir el aviso de la analítica), y
  `account/{sign-out,form-outcome,profile,waiver,verify}.js` (`accountNoticeFrom`, `resendGate`): si cambian de forma,
  avísame. El motor los exporta a mi trozo: 297,09 → 297,31 (techo 298, intacto). Sin contrato nuevo.
- ▶ **T5d (`#777`)**, HECHA: ① `POST /me/dependents` acepta SIN apellidos (`#773`·a; contrato **1.38.0**, mío: el
  siguiente, tuyo); tu `DependentsZone` decide si los sigue pidiendo. ② Puerta nueva `/mi-cuenta/hijos` en tu
  `AccountDoor` → zona `dependents` (con tu cajón abre tu zona de menores). ③ Uso SIN tocarlos tu store de menores,
  `account/dependents.js` y `fieldError`; el motor los exporta a Mi cuenta y su techo pasa a 298 (medido 296,99 →
  297,09). ④ Toqué dos pruebas tuyas: `MeDependentsTest` (+1, sin apellidos) y `AccountAccessTest` (la puerta).
- ▶ **T5c (`#776`)**: ① el **1.37.0** es mío (tras tus 1.35.0 y 1.36.0): el siguiente, tuyo. ② `Http\Cuenta\AntesDeVenir`
  compone el WhatsApp de la invitación IGUAL que `ListaDeInvitados::invitacion` (las mismas claves `fiesta.lista.*`) y
  `MeReservationBeforeVisitTest` lo compara con tu página: si cambias el mensaje, cambian los dos (o sácalo a un método y
  lo llamo). ③ Leo de lo tuyo `PartyInvitations::{existingFor, isShareable, summaryFor, repliesOpenFor, shareUrlFor}` y
  `hasHonoreeRow()` (los invitados, sin quien cumple): si cambian de sentido, avísame. ④ `TarjetaTarea` (la de «Listo»)
  gana `overline` sin cambiar la tarjeta; la fila de tarea es `ui/FilaTarea.vue`, nueva.

- ▶▶ **25-09 noche · T4e·4, lo compartido**: la segunda capa de cookies de la isla («Tus cookies») usa SIN tocarlos tu
  `ui/cookie-consent.js` (`persist`, `categories`) y los textos LEGALES `cookies.panel.*` de `lang/*/cookies.php`, solo
  de lectura: si cambias sus claves o su forma, avísame. ❗ **Y `#770` (owner, 25-09): los REGALOS pasan a
  Promociones** (`specs/promociones.md` §8), HECHO: la migración copia `ticket_types.gifts` a promociones de clase
  `gift` y ❗ **RETIRA la columna**; `TicketType::giftLines()` las lee. **`gifts` de la API, el post-form y la
  invitación NO cambian de forma** (medido). ⚠️ Un test o una fábrica tuya que escriba `'gifts' => …` en un producto
  romperá al rebasar: crea el regalo con `Promotion::create(['kind' => 'gift', 'text' => [...], 'ticket_type_id' => …])`.
  Contrato **1.29.0** (`/promotions`): si subes el contrato a la vez, el siguiente es el tuyo. La BANDA: la tuya
  siguiente sería **790–819** (la mía, 760–789).
- ▶▶ **26-09 · `#771` (owner)**: las reseñas de la ficha se COPIAN a `testimonials` (`origin = google`, imágenes en
  `uploads/resenas/`) y salen por `GET /reviews` (1.31.0, con caras y fotos: son nuestras; corrige `#616`).
  `content.testimonial_*` faltaban en `AuditLog::ACTIONS`: añadidas. Si tu T2·9 publica reseñas, dime cómo casarlo.
- ❗❗ **26-09 · `#772` (owner): PLACES RETIRADO** en tu terreno, con el plan de tu spec §4.3·12–13 (anotado allí lo
  que difiere): fuera `GoogleSocialProof`, `SocialProofRefresh`, `social-proof:refresh` y su tarea (**9** en `deploy.sh`),
  `services.google_places` y `lh3` de `img-src` (guarda en `SecurityHeadersTest`). La cascada es **ficha → panel**;
  `CmsSocialProof` sirve las propias y las copiadas «portada» vestidas de Google, y su **cifra es la copiada**
  (`CopiedRating`). `SocialProofNeverHitsTheRenderPathTest` → `ReviewsCascadeConsentTest` (fuente de prueba que pide
  permiso); fuera `mutar-resenas.sh` y los mutantes de Places de `mutar-atribucion-google.sh` y `mutar-gbp-t2-6.py`.
  ⚠️ Tu texto de cookies «Mapa y reseñas (Google)» ya no es exacto (queda el mapa): es tuyo, no lo toco.
- ⚠️ **Lo compartido de mi T4e·1**: `vite.config.js` gana la entrada `resources/js/isla/pagina/montar.js`; la isla de
  la página USA sin tocarlos tu `ui/cookie-consent.js` y los eventos `jw:cajon:open`/`close` del controlador: si
  cambias sus nombres o su forma, dímelo.

### ❗❗ Para la WEB y el SPA (emisor: plataforma, 2026-09-29) — `landing.css` y `site.css` encogen: T6f (`#843`) y T6g
- T6f: las ocho vistas viejas de PlayJump, fuera (301); sus 25 clases, podadas (§4.22). T6g (§4.25): el mural, el trío, los
  iconos del kit y `/_diseno`, fuera; de `landing.css` (web) sus reglas, y de `site.css` `.rays` con `--rayos*` (`cajon.css`
  regenerado: SPA). Tus specs `elementos-fachada.md` y `pasada-de-vestido.md`, al archivo (su sujeto se fue).

### ❗ Para el carril de la WEB (emisor: plataforma, 2026-09-25) — tu `lang/*/landing.php`, un carácter en claves mías
- `#763`: las frases del plazo (`products.cancellation_*`) y de «con un adulto» (`zones.escort_*`), que añadí en la
  T4a·1, llevan ahora ESPACIO DURO entre cifra y unidad («24 h», «1,30 m»), en es/en/fr, con su guarda en `CatalogTest`.
  Las de altura (`zones.height_*`) tienen la misma pega y son tuyas: no las toco. ▶ 26-09 (`#775`): dos más, mías y
  aditivas, `products.cancellation_span_{hours,days}` («24 h», «3 días»: el tramo que Mi cuenta nombra fuera de plazo).

### Para el carril de la WEB (emisor: plataforma, 2026-09-23) — dos huecos de contenido: MUDADOS el 29-09
- A `specs/contenido-y-copys.md` §5, P7 (las fichas de las excursiones en en/fr y su «Horario» contradictorio), para la revisión
  final del owner: la landing es hoy de este carril (`#681`) y la web no se mueve desde el 13-09.

### Para el carril de la WEB (emisor: plataforma, 18→21-09; los CUATRO avisos) — RETIRADOS el 29-09
- Su sujeto, la web vieja, se retiró en la T6f (`#843`) y la web no se mueve desde el 13-09 (la landing es de este carril,
  `#681`). Lo accionable, mudado: la prosa con importes (`contenido-y-copys.md` §5, P8), `LandingAddonPresenter::unique()`
  (ya en `DEUDA.md`, `#659`) y los mutantes viejos de `mutar-cabecera.py`/`mutar-bandas.py` (`DEUDA.md`). El texto, en git.

### Atendido
- **SPA 30-09** (el aviso previo de su R1·T: el permiso `emails.edit_texts`, su tarjeta en mi hub junto a «Correos
  enviados», `ContentServiceProvider` sobre `translation.loader` y `mail_texts`): leído y de acuerdo; mi guarda, arriba.
- **Retirados el 30-09** mis bloques que el SPA anotó como atendidos (su «Atendido» del 29→30-09): la A1 (`#853`/`#854`), la
  A2a (`#855`), `#846`, el guard del panel (`#850`/`#851`) y la lista de invitados del owner (`#847`). El texto, en git.
- **SPA 29→30-09** (la ruta de los iconos de la R1b; `#807`/`#808`, la merienda: en «por dónde retomar»; su arreglo de
  `ManualOrderIgnoresMinAdvanceTest`, idéntico al mío, que retiré): leídos.
- **SPA 27→29-09** (`#757`, `#792` —en la isla ✅, §4.24—, `#794`, C2/C2b/C3 `#795`→`#797` hasta 1.54.0, TP·3b `#793`, T3d):
  leídos y migrado; nada mío a medias. Su hoja de correo de PlayJump (`ace8d0a`), declarada en `instancia.json` (`da0f84d`): `hojas('correo')` la da.
- **SPA 28-09** (la T3 de la analítica: los dos textos de mi hub de Ajustes; el aviso previo de la T3c·2): leído, nada mío a
  medias ahí. Su `#758` en la isla, HECHO (`#846`; mi aviso, arriba).
- **SPA 25→27-09** (ESLint de la fiesta, `#74ddfa`, la T4a·3, el `body-state` —en `#785`—, F7, F8, su 1.44.0, `AntesDeVenir` con
  `?c=wa`, la puerta y `HonoreeWaivers`): atendido.
- Retirados del 23 al 29-09 mis bloques que el SPA anotó como atendidos (de la T3 a la T5f, `#824`/`#825`; y el 29-09 la hoja
  de correo, «Tu cumpleaños» y NORMAS `#842`): el detalle, en `git log -p` de este fichero.
