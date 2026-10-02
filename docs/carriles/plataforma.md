# Carril · Plataforma (producto e instancias)

> Máquina: **este ordenador**, `~/proyectos/jumpweb/producto` (mudado en `#648`; las instancias al lado, en
> `jumpweb/instancias/<slug>`) · Banda: **610–639 AGOTADA con `#639`** → sigue en
> **640–669 AGOTADA con `#669`** → **670–699 AGOTADA con `#699`** → **760–789 AGOTADA con `#789`** → sigue en
> **820–849 AGOTADA con `#849`** → **850–879 AGOTADA con `#879`** → sigue en **880–909** (del owner, 02-10 noche; de
> `#880` a `#899` en `decisiones/800-899.md`, de `#900` en adelante en `900-999.md`) · Último usado: **`#881`** · Spec:
> `docs/specs/producto-e-instancias.md` (§0 y §4.9) y, para lo que viene, **`docs/specs/isla-y-landing-nueva.md`** (⬜ borrador;
> `#681`→`#699`, `#760`→`#789`, `#820`→`#846`) · Actualizado: **2026-10-02 noche** (todo con el visto bueno del owner: **su
> lista del 02-10, L1→L5** —`#876`→`#879`, §4.28; el código de la L2, por hacer—, el zip (6) de este carril, ENTERO —Z6a→Z6g,
> `#866`→`#875`— y la A5, `#869`/`#870`; y, de noche, **su lista nueva, M1→M4** —`#880`, §4.29—, sin empezar; lo anterior, en
> `git log -p` de este fichero).
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
▶▶▶▶ **LO SIGUIENTE, EN ORDEN** (actualizado el 02-10 tarde, `#876`; el reparto con el SPA, `#861` y `#876`):
0. ✅ **EL ZIP (6) DE ESTE CARRIL, CERRADO** (01→02-10, cada tanda con el visto bueno del owner en vivo; su detalle, en §4.27):
   Z6a, Z6b y sus tres usos (`#866`, `#867`), el B3 (`#868`; su MEDIDA, la Z6c·3, es del SPA), Z6d (`#872`), Z6e (`#873`,
   instancia `b359e30`), Z6f, Z6g (`#871`, `#875`; instancia `0089699`) y, por el camino, las reseñas traducidas (`#874`) y
   Mi cuenta desde «Mi QR» (`aInicio` no cargaba los Ajustes ni «Cerrar sesión»). ⚠️ El experimento B3 se CREA en el panel al
   desplegar (clave `isla`, `hoy`/`b3`, 50/50) y corre con el vídeo de verdad, dos semanas como mínimo. ⚠️ **Sondas para la
   verificación final** (`#768`): `sonda-cuenta` (sin pasar desde la Z6b; monta «hoy» antes de las 20:00; con el paso nuevo de
   su §5), las cuatro de la Z6g·1 y `sonda-panel`, `sonda-banco-movimiento` (casos del 27-09) y `sonda-entradas` (ya lee «90
   minutos saltando»). **Queda de la isla**: probarla en un móvil de verdad, en STAGING al terminarlo todo; `isla_razon` y la
   imagen de la INVITACIÓN son del SPA (`#861`).
   ▶▶▶ **SIGUE AQUÍ, en el 2**: el código de la L2 (`otra-zona.md`; **K1 ✅** —el modelo y la cesta—; **K2 ✅** —la otra zona en
   la pantalla 0 y la pregunta de los grupos de `#881`; vista por el owner—; después K3 «Pagar» y K4). ⚠️ Pendiente del owner:
   la DURACIÓN de la línea añadida (hoy, la primera fila de su zona —1 h— para una persona, sin sus complementos; propuesta:
   «¿Cuánto tiempo?» en la tarjeta, la misma que la del pedido por `duration_min`, y sus complementos); la lista nueva del owner (el 1), con M1→M3 ✅, espera solo a su M4 (después de la L2). ⚠️ La compra, a
   199,61 de 201 kB (+12,44 en una noche): la M4 empieza por CARGARLA al abrirla.
1. ▶▶▶ **LA LISTA DEL OWNER DEL 02-10 (noche)** (`#880`; su censo, medido, en `isla-y-landing-nueva.md` §4.29), en este orden:
   ✅ **M1** la compra de la isla enseña TODOS los complementos que se venden al reservar el producto, «los que sean» (los de la
   lista de invitados, no; visto por el owner, §4.29: `compra/complementos.js`, la lista diferida, `calcetinDe` con tope, los
   techos de peso subidos con su base). ⚠️ Sin pintar: un grupo de elección en una ENTRADA o un segundo en un pack: va con la
   L2 (`#881`, en `otra-zona.md` §4.6) → ✅ **M2** (`#881`) lo que falta, ENCIMA del botón mientras falte (la nota del pie, que
   se toca) y, al pulsar, en rojo en su pregunta (§4.29, `compra/falta.js`; visto por el owner) → ✅ **M3** (§4.29:
   `datos.js::vistaInicial` y `atras`, la puerta con su aviso; visto por el owner). Era: sin sesión, el
   correo primero: «Entra o crea tu cuenta» tras el producto, con el texto del owner (§4.29); solo un correo sin cuenta rellena
   el resto (`PantallaEntrar` con `cuenta` y `PantallaDatos` con `nueva` ya existen) → el código de la L2 (el 2) → ⬜ **M4** el
   pulido: la carga (sobre todo la isla), las transiciones sin saltos y el HERO (en ordenador, la portada: al bajar y volver a
   subir, las esquinas izquierdas cuadradas; sospecha sin verificar, el `will-change` del vídeo), con el S3 del SEO, que se
   solapa. Cada una, en vivo antes del commit.
2. ▶▶▶ **LA LISTA DEL OWNER DEL 02-10 (tarde)** (`#876`; su censo, medido, y el reparto en `isla-y-landing-nueva.md` §4.28):
   ✅ **L1** el panel recuerda UN día a todos (`#877`, `panel-a-salvo.md` §4.4: «Recordarme» marcada, 24 h; arnés 14/14,
   `sonda-panel` 14/14 a 1280 y 390; visto por el owner). El código ya se enviaba solo (`#867`, medido) → ✅ **L2** la
   SPEC aprobada, `specs/otra-zona.md` (`#878`: un día y una hora para todo, «Quitar», la zona por su nombre) → ✅ **L3** la
   isla en las páginas (§4.28, visto por el owner): «Reservar» abre la compra con la intención de la página (instancia
   `e8f742e`), la frase del cumpleaños solo con lista (`guest_form`, contrato 1.61.0) y «¿Qué menú?» con condición → ✅ **L4**
   (§4.28, visto por el owner): la 404 del paquete (`'ocupa' => '404'`, `InstanceNotFound` y el comodín; `mutar-404.sh` 12/12)
   y el hero a 1400 (instancia `6cf107d`) → ✅ **L5** (`#879`, §4.28, visto por el owner): el resumen A4 del calendario,
   APAISADO, del día, la semana o el mes, con «Qué incluir» (todo, cumpleaños, excursiones o entradas) y nueve columnas (casilla,
   hora, producto con sus complementos, cliente, teléfono, cantidad, homenajeado con su edad, merienda y tarta); el
   cumpleaños es el pack que pide la edad del homenajeado (dato, no nombre); `mutar-resumen.sh` 12/12. ⚠️ En ESTE local la
   columna Tarta sale vacía: la tarta de la lista (bloque `cake`) es dato del panel, la fila 4 del SPA (en producción, al desplegar).
   ▶ **SIGUE**: el código de la L2, **K1→K4 de `otra-zona.md`** (DINERO y AFORO: `INVARIANTES` primero; los pesos de la compra,
   al techo: su §0) → al final, tras sus vídeos e imágenes, los textos («parking gratis» es falso). Al SPA, en el buzón.
3. ▶▶ **EL SEO** (`specs/seo.md`, 🟦; el owner: «IMPORTANTÍSIMO»). Investigado y medido (§1–§2: la marca ya está en el 1 y
   **cumpleaños no existe para Google**). HECHO el 01-10: **S4** (`robots.txt` con `Sitemap:`; JSON-LD con `geo`, `priceRange` y
   la dirección por campos; `mutar-seo.sh` 14/14; `52e6557e`), **S6** (`#862`: los titulares se quedan; Kids y Jump dicen «en
   Lorca»; instancia `9e7d274`) y **S3 empezado** (las fotos de más abajo, diferidas: Kids 8,7 → 6,5 s de LCP en el laboratorio
   local). ✅ **SIN CÓDIGO, redactado el 01-10** en `instancias/playjump/docs/perfil-de-empresa.md` (lo aplica el owner): (a) los
   CUATRO SERVICIOS del perfil (en «Servicios», no en «Productos»: Google dice que ese editor es de artículos físicos; precios
   de producción) y (b) la FICHA de los directorios, con las tres respuestas del owner (30813: la web se corrige en el panel;
   domingo 21:30: el perfil; @playjumplorca). Los TÉRMINOS de búsqueda del perfil, desde el 6-oct. Después, con código: S3
   (el logotipo de 115 KB, la foto de la cabecera por tamaños, `cajon.css` bloqueante, el CLS de la cabecera) y S5 (la imagen
   de cada página al compartir). La medida en STAGING, cuando estén los vídeos e imágenes nuevos (el owner, 01-10).
4. **Los textos LEGALES con lo nuevo**: ⬜ `specs/textos-legales.md` (01-10, el DOCUMENTO para el owner: 22 tratamientos medidos,
   el texto nuevo en español, §7 sus decisiones D1–D10; después la asesoría y el código). Lo gordo: el texto VIVO de producción
   se editó a mano (sin rastro en el repo) y dice que el descargo «no se gestiona en esta web» y que los invitados «se eliminan»
   tras la fiesta (sus alergias viven hasta suprimir la cuenta); la ODR cerró en 2025; falta el aviso de que no hay
   desistimiento. Del owner (01-10): `#863`, los invitados se borran a los 14 días de la fiesta (una poda, tanda propia, ANTES
   de publicar el texto), `#864`, sin aviso a las cuentas, y `#865`, las dos frases falsas de producción se corrigen AL
   DESPLEGAR la v2.0.0 (apuntado en `ENTORNOS.md` §6). Entran las notas del SPA (`#750`, `#754`, la TP·1 y `#793`). Espera
   la revisión del owner y la asesoría; la poda de `#863` puede ir antes (no depende del texto).
5. **A6** (en staging, cuánto tarda el correo), que decide el alta: `#849` o lo del diseño. La A5, arriba en 0.
**Lo HECHO del 29 y el 30-09** (la A3, `/cookies` y su aviso, el panel a salvo, `#758` en la isla, `#847`): en sus specs y
decisiones; lo de desplegar (el script de `/cookies`, `PANEL_PATH`, tablets, la ficha de Google, el authenticator), en
`ENTORNOS.md` §6. ❗ **Para el CHANGELOG de la v2.0.0**: dos defectos de producción arreglados por el camino —los correos del
cambio de correo salían al buzón contrario (`#856`) y «cerrar las demás sesiones» no cerraba nada con `redis` (`#855`)—.
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
`TESTING.md` §2.octies el 29-09): (b) el tracker, a 8 B de su techo (16 KB, 02-10 noche, tras la M1 de `#880`; la portada 📜 ya en una línea): el detalle de una línea cerrada se MUDA
a su spec (el 02-10, F3 a `producto-e-instancias.md` §4.6; antes, F2, F4 y F5); la próxima vez, otra cerrada;
(d) Vue 3.5 reevalúa un `computed` fuera del `try` de quien lo lee: se protege DENTRO (`seguro.js`, §4.13); (f) un texto de la isla que
use la COMPRA tiene que estar en un grupo que la compra recibe (`mi_cuenta.*` no le llega: §4.24); (g) `isla/hoja/montar.js` NO
importa nada compartido (`#841`); los pesos, medidos el 02-10 (tras la Z6g·2, con los métodos de `SidebarBundleBudgetTest`): la
compra 186,98 de 188, los pasos 50,79 de 52, Mi cuenta 124,43 de 126, sus Ajustes 29,82 de 30, la isla de la página 194,70 de 196,
la calculadora 186,79 de 188 y la de la fiesta 193,25 de 195 (`#846`: un `import()` suma el `preload-helper` al cálculo por entrada); un
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

### ❗ Para el SPA (emisor: plataforma, 2026-10-02 noche) — la isla compone pedidos de VARIAS líneas (la L2, `otra-zona.md`)
- Desde la K1/K2 (`#878`), una compra de ENTRADAS de la isla puede llevar la OTRA zona (Kids + Jump) en UN pedido: la cesta se
  compone en `isla/compra/linea.js::meterLineas` (`cartStore.setLines`), sin pasar por el `addToCart` del motor. Su §4.5 pedía,
  antes de la K2, una prop en `line_added` que diga de dónde vino una línea (`otra_zona` | `otra_entrada`): es del contrato de
  eventos, tuyo. Mídelo: hoy la isla quizá no emite `line_added` en ninguna de sus líneas. Sin contrato nuevo por mi parte.

### ❗ Para el SPA (emisor: plataforma, 2026-10-02 noche) — contrato **1.61.0**: `guest_form` en la ficha, y «¿Qué menú?» con condición
- `GET /catalog/products/{id}` gana `guest_form` (booleano, aditivo; la L3 de `#876`, `isla-y-landing-nueva.md` §4.28): la
  reserva del pack lleva la lista de invitados (`TicketType::asksGuestForm()`, la regla de `OrderItem::guestFormStatus()`, que ya
  la usa en sus tres sitios). Si subes la versión del contrato, parte de **1.61.0**.
- La isla ya no pinta «¿Qué menú?» sin menús al reservar: tu K3 (el menú de Kids en la lista, `c19b9532`) no deja la pregunta
  vacía. Al final de la pantalla del cumpleaños, si el pack lleva la lista: «Los invitados y los detalles de la fiesta, después
  y sin prisa, en tu lista de invitados.» (`isla.compra.cuando.fiesta_despues`).

### Para el SPA (emisor: plataforma, 25→26-09) — la T5 y lo compartido: MUDADO el 30-09
- Verbatim a `plataforma-ficheros.md` («Lo del SPA que este carril usa sin tocarlo»): es el registro de lo tuyo que uso sin
  tocarlo (tus stores, `account/*.js`, `PartyInvitations`, `ui/cookie-consent.js`…). Si cambias algo de ahí, avísame.

### ❗ Para el carril de la WEB (emisor: plataforma, 2026-09-25) — tu `lang/*/landing.php`, un carácter en claves mías
- `#763`: las frases del plazo (`products.cancellation_*`) y de «con un adulto» (`zones.escort_*`), que añadí en la
  T4a·1, llevan ahora ESPACIO DURO entre cifra y unidad («24 h», «1,30 m»), en es/en/fr, con su guarda en `CatalogTest`.
  Las de altura (`zones.height_*`) tienen la misma pega y son tuyas: no las toco. ▶ 26-09 (`#775`): dos más, mías y
  aditivas, `products.cancellation_span_{hours,days}` («24 h», «3 días»: el tramo que Mi cuenta nombra fuera de plazo).

### Atendido
- **SPA 02-10 noche** (`c19b9532`): tomó las filas 4 y 8 de `#876` (la 4, dato del panel sin código, montada en su local; la 8,
  código suyo, y medido: un QR impreso hoy no cuenta hasta la v2.0.0) y acusó seis avisos míos (`#876`, `#875`, `#874`, la Z6e, la
  Z6g·1 y la A5); el del B3 lo cerró su Z6c·3: los siete, RETIRADOS de mi buzón. El texto, en `git log -p`.
- **SPA 02-10, su Z6c·3 EN `main`** (`ccf692d8`, contrato 1.60.0): los gestos de la isla en `Contract::EVENTS` y su informe
  (`isla_accion` por visita en móvil). La isla ya los emitía (`isla/medir.js`): nada que tocar. El experimento `isla` lo crea
  el owner en el panel al desplegar (ya en «retomar» 0).
- **Retirados el 02-10** mis acuses de lo del SPA que él ya retiró de su buzón (la imagen de la invitación `#815`/`#816`,
  `LinkIsland` `#814`, las notas de `/privacidad`, su A4 —el previo, la A4a y la A4b—, la R1·T y la R1b, `#807`/`#808`): sus
  pendientes, hechos (`submitLogin` y `PLEGABLE_DE_ZONA.password`, fuera; el (2) de la R1·T, en la A5b). El texto, en `git log -p`.
