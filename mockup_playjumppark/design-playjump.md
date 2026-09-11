# Perfil de diseño — Play Jump Park (Lorca)

> **La capa de arriba de `design.md`** (el sistema del producto, en la raíz del repo): aquí están los
> VALORES de esta instalación y las reglas que su propio sistema escribe. Vive en esta carpeta
> —gitignorada, como todo el material del cliente— porque **el repo es el producto y no lleva la marca
> de ningún cliente** (`DECISIONES #1`). Lo que el producto necesita de aquí ya viaja en el paquete:
> `public/css/client.css`, `client-logo.svg`, `client-favicon.svg`, `client-kit.svg`, `THEME_FONTS`,
> `theme.*` del panel.
>
> **Fuente**: el canvas de Claude Design del owner (`projectId 8c37d2d2-…`), leído de la copia local
> el 2026-09-03: `Colores de Marca PJP` (27-08, diffeado idéntico el 28) · `Microanimaciones PJP`
> (27-08) · `Iconos PJP` (27-08, diffeado idéntico) · `Elementos Fachada` (31-08, sano) · `Landing PJP
> Modos` (28-08 09:24) · `Auditoría Landing PJP` (27-08) · las variantes de precios, zonas, cabecera
> y botón (27-08 07:34, **exploración**, algunas con la paleta anterior). ⚠️ **El canvas caduca sin
> avisar** y `DesignSync` necesita `/design-login` interactivo: antes de implementar desde aquí,
> diffear. Lo que sigue son **sus palabras y sus números**, no una lectura nuestra; cuando el
> producto se aparta de ellos, se dice y se cita la decisión.

---

## 1. La identidad, en sus propias palabras

**La frase**: *«Graffiti de rótulo con disciplina de sistema. Negro de base, un color al mando,
letra de rótulo para gritar y grotesca para explicarse. La fiesta está permitida — pero está
presupuestada.»*

**El estilo de la fachada** («letra burbuja sobre campo de spray»): rotulación urbana de tradición
hip-hop, throw-up de letra redondeada con doble contorno, degradado interior y manchas sobre panel
negro. *«Energía por acumulación: construido para funcionar en doce metros de fachada, de lejos y en
tres segundos. No está construido para leerse.»*

**Los siete rasgos del mural** → **las seis traslaciones al sistema**:

| rasgo del mural | traslación | cómo |
|---|---|---|
| Ocho colores a la vez (acumulación) | **Presupuesto** | un color manda por sección, 60/30/10, las piezas se cuentan |
| El color va dentro de la letra | **Color detrás del texto** | un degradado interior no sobrevive a 16 px: el color se muda al fondo y al relleno; el texto queda en tinta o papel |
| Contorno negro | **Negro de superficie** | lo que rodea cada letra es, en pantalla, el lienzo `#101418`: *la marca es oscura por naturaleza* |
| Letra dibujada | **Dos voces** | Bungee hereda la masa; el rotulador hereda el gesto y se queda en el eslogan; leer es trabajo de la grotesca |
| Sin retícula | **Retícula con permiso** | todo alineado; la excentricidad concentrada en mancha, cinta y sello, giradas −2° a −8° |
| Visto de lejos | **Visto a 40 cm** | cada par de colores pasa AA; los que no pueden quedan como relleno |
| Silueta plana · la ciudad como textura | → poses del kit · castillo en semitono (fondo documental) | |

**Los tres irrenunciables** («quítale esto y deja de parecer Play Jump Park»): **01** el contorno —
keyline de tinta con sombra dura en la pieza protagonista · **02** la salpicadura — mancha o goteo
entrando por un borde, nunca centrada ni simétrica · **03** la silueta plana — un cuerpo saltando,
de un solo color, apoyado en un borde.

**Lo que no somos**: Memphis noventero · Y2K de neón y cromados · marca infantil de pastel y globos
· bebida energética de aristas y cursivas · flat corporativo con una salpicadura de adorno.

---

## 2. Color

Medido sobre los píxeles del mural: **46 % azules · 36 % naranjas · 13 % verdes**; negro `#121517`,
blanco del graffiti `#E6E6E3` (neutros fríos, sin sesgo cálido — *«nada de cremas»*).

### 2.1 Núcleo (8) — cada color trae hover, pulsado y su versión oscura para poder ser texto en papel

| nombre | hex | texto encima | hover | pulsado | oscuro (texto en papel) | rol |
|---|---|---|---|---|---|---|
| **Cian PJP** | `#1AA9DE` | `#101418` | `#1795C3` | `#1480A9` | `#12769B` | identidad: titulares sobre tinta, rellenos grandes, iconografía, subrayados |
| **Azul Muro** | `#0A5C93` | `#FFFFFF` | `#095181` | `#084670` | par claro `#1AA9DE` | la voz del cian en claro: enlaces, botón fantasma, profundidad |
| **Naranja Salto** | `#F2711C` | `#101418` | `#D56319` | `#B85615` | `#AE5114` | acción: reservar, comprar, enviar — **un único relleno naranja por pantalla** |
| **Rojo Goteo** | `#D93E14` | `#FFFFFF` | `#BF3712` | `#A52F0F` | `#C83912` | urgencia y error; sobre tinta 4,10 → solo ≥ 19 px bold; en texto pequeño oscuro `#FF8A6B` (8,02) |
| **Amarillo Aviso** | `#F5C400` | `#101418` | `#D8AC00` | `#BA9500` | `#8A6E00` | marcador y anillo de foco; nunca fondo de sección ni botón |
| **Lima Bote** | `#A3C21C` | `#101418` | `#8FAB19` | `#7C9315` | `#627411` | precios, cifras, confirmación: *el color del rebote* |
| **Verde Salta** | `#5FA82E` | `#101418` | `#549428` | `#488023` | `#447921` | éxito: reserva confirmada, pago correcto |
| **Magenta Chispa** | `#E6007E` | `#FFFFFF` | `#CA006F` | `#AD0060` | `#D40074` | chispa de fiesta: cumpleaños, salpicaduras, un chip — **máx. 2 % de superficie** |

### 2.2 Neutrales (9 + 2)

Tinta `#101418` (fondo base) · Tinta 800 `#1A1F25` (tarjetas sobre tinta) · Tinta 700 `#2A3138`
(bordes) · Tinta 600 `#3B434B` (borde fantasma sobre tinta) · Humo `#626A72` (texto 2.º en papel,
4,98) · Humo Claro `#9AA1A8` (texto 2.º en tinta, 7,08 — **falla en papel**) · Papel 200 `#C9CDD1`
(cuerpo sobre tinta) · Nube `#E8E9E5` (inerte, deshabilitado) · Línea `#D6D8D4` (bordes en papel) ·
Papel `#F4F4F1` · Blanco `#FFFFFF` (tarjetas sobre papel).

### 2.3 La ley: 60 / 30 / 10 — y «los colores del mural son relleno, no texto»

60 neutro · 30 cian (identidad) · 10 naranja (acción); amarillo, rojo, verde y magenta **< 5 %** en
total. *«Cian sobre blanco da 2,70. Naranja, 2,94. Lima, 2,42. Ninguno es legible como texto en
claro. Sobre tinta todos superan 5,6. En papel, el color entra como bloque con texto tinta encima.»*

### 2.4 Roles por elemento (su tabla)

| elemento | en tinta | en papel | nota suya |
|---|---|---|---|
| Fondo de sección | `#101418` | `#F4F4F1` | *«alterna tinta y papel; nunca dos papeles seguidos»* — ⚠️ **SUSTITUIDO por su propio `S-00`** (`Auditoría Landing PJP`, Alta, Aplicado): papel continuo, el contraste lo dan las tarjetas; «ni negro ni cian a sangre». Es la norma vigente (`tema-por-instalacion.md` §1.2) |
| Superficie / tarjeta | `#1A1F25` | `#FFFFFF` | sin sombras de color |
| Borde | `#2A3138` | `#D6D8D4` | 1 px; 1,5 en fantasma |
| Texto principal | `#F4F4F1` | `#101418` | 16,79 AAA |
| Texto secundario | `#9AA1A8` | `#626A72` | **dos grises: el claro falla en papel** |
| Titular con color | `#1AA9DE` | `#101418` | en papel el titular es tinta; el color va al fondo |
| CTA primario | Naranja Salto | Naranja Salto | idéntico en ambos fondos, siempre texto tinta |
| CTA secundario | `#1AA9DE` | `#101418` | el relleno nunca compite |
| **Enlace en texto** | `#1AA9DE` | **`#0A5C93`** | hover: aclarar 15 % en tinta, oscurecer 12 % en papel |
| Precio / cifra | `#A3C21C` | `#627411` | Bungee; la cifra manda, el € en tinta |
| Éxito | `#5FA82E` | `#447921` | con texto blanco en claro (5,25) |
| Urgencia / error | `#D93E14` | `#C83912` | solo cuando informa |
| Anillo de foco | `#F5C400` | `#F5C400` | 3 px + offset 2 — ⚠️ el producto **se aparta con medida**: amarillo sobre papel da **1,49** (su auditoría no midió ese par); en papel el anillo es Azul Muro (6,43) y en tinta amarillo (`[DECIDIDO owner, 2026-08-28]`, `client.css`) |

### 2.5 Su auditoría de contraste (20 pares) — los cuatro «✕ NUNCA»

Papel/Tinta 16,79 AAA · Humo Claro/Tinta 7,08 · Humo/Papel 4,98 · Cian/Tinta 6,85 · Naranja/Tinta
6,30 · Lima/Tinta 9,06 · Amarillo/Tinta 11,26 · Tinta/Naranja 6,30 (botón primario) · Tinta/Cian
6,85 · Tinta/Amarillo 11,26 · Azul Muro/Papel 6,43 · Blanco/Azul Muro 7,08 · Blanco/Rojo 4,52 ·
Rojo/Tinta 4,10 (solo grande) · Rojo Claro/Tinta 8,02.
**✕ NUNCA**: **Cian sobre Papel 2,45** · **Naranja sobre Blanco 2,94** · **Blanco sobre Verde 2,95**
(«usa Verde oscuro») · **Lima sobre Papel 2,42** — *solo relleno*.
▶ La web de hoy sirve dos de estos cuatro (`docs/specs/auditoria-diseno.md` C2 y C3).

### 2.6 Prohibido (suyo)

Cian, lima, amarillo o naranja como texto sobre claro · tres primarios (un solo relleno de acción
por pantalla) · arcoíris (el único degradado válido es de un color a su pulsado, y solo decorativo)
· magenta grande (nunca fondo de sección ni botón principal).

### 2.7 Migración desde el kit anterior (por qué cada valor)

`#EFEDE7`→`#F4F4F1` (el crema competía con Amarillo Aviso) · `#141517`→`#101418` (negro real de la
fachada + un grado de azul) · `#2FB6DE`→`#1AA9DE` (el del logotipo mide `#0094C6`) ·
`#1E7BC8`→`#0A5C93` (el azul anterior no llegaba a AA: 4,05 → 6,43) · `#8CC63F`→ Lima `#A3C21C` para
cifras + Verde Salta `#5FA82E` para éxito · `#E33B2E`→`#D93E14` (alineado con el goteo `#CA4309`) ·
`#6E6A64`→`#626A72` (AA real: 4,98). ⚠️ Los artboards de exploración (`Menu PJP`, `Hero PJP
variantes`, `Info PJP variantes`, `Boton Reservar variantes`, `Logotipo variantes`, `Elementos
Fachada`) llevan la paleta **anterior**: de ellos se toma la forma, nunca el color.

---

## 3. Tipografía — «cuatro fuentes con contrato»

*«En la fachada no hay ninguna tipografía: hay letra pintada a mano con contorno negro y un eslogan
a rotulador. El sistema no la imita, la traduce.»*

| rol | familia | regla suya |
|---|---|---|
| Rótulo | **Bungee** | solo mayúsculas · mín. 20 px · nunca en párrafo ni en botón |
| Texto | **Hanken Grotesk** 400/500/700/800 | cuerpo, subtítulos y botones · mín. 16 px |
| Guiño | **Permanent Marker** | el eslogan, literalmente; máx. 6 palabras · nunca precios, botones ni normas |
| Dato | **JetBrains Mono** | 10–19 px · mayúsculas con `.16em` en etiquetas |
| *(Logotipo)* | Lilita One, en el lockup del mockup | ⚠️ **quinta familia** — el producto la evita: el logotipo entra como SVG en contornos (`client-logo.svg`, `#254`) |

**Escala de 10 niveles** (escritorio / móvil · interlínea · tracking):
Display XL Bungee 76/42 · .92 · −.015em (hero, una vez) · Display L Bungee 52/34 · .95 (cifra
grande, cabecera de bloque) · Título Bungee 36/26 · 1.05 · Subtítulo Hanken 700 26/22 · 1.25
(título de tarjeta, pregunta de FAQ) · Entradilla Hanken 500 21/18 · 1.5 (máx. 65 caracteres) ·
Cuerpo Hanken 400 17/16 · 1.6 (máx. 70 por línea) · Cuerpo S 15 · 1.55 · Botón Hanken 800 16 · 1 ·
.01em (nunca Bungee, nunca < 16) · Etiqueta JetBrains 12 · 1.2 · .16em (mayúsculas) · Eslogan
Permanent Marker 30/24 · 1.15 (girado 2°, uno por página).

Contrato: máx. 2 familias por pantalla + mono en etiquetas · **cero cursivas** («ninguna de las
cuatro tiene una real») · Bungee y rotulador nunca en la misma línea · salto mínimo entre niveles 1,25×.
Servidas por `fonts.bunny.net` (único host que admite la CSP del producto).

---

## 4. Forma, borde, sombra, rotación

*«Panel recto, letra redonda»*: a escala de página el canto es duro; a escala de componente,
generoso. Radios **0** (secciones a sangre, fotos completas, banda del rótulo) · **6** (chips mono,
muestras, sellos, elementos dentro de tarjeta) · **10** (botones e inputs: alto 48, así 10 es canto
redondo, no cápsula) · **16** (tarjetas, tiles, imágenes en tarjeta — el defecto) · **24** (bloques
grandes con fondo propio, modales, tarjeta del hero) · **999** (cápsulas). Radios concéntricos
(16 con 8 de aire → foto a 6).
▶ En el paquete: `--r-xs`/`--r-sm` 6 · `--r-md`/`--r-btn` 10 · `--r` 16 · `--r-lg` 24 · `--r-pill` 999
(el producto tiene siete roles y el sistema seis escalones: dos pares colapsan).

**Pegatina** = borde 2 px tinta + sombra dura 5 px: *«es el keyline negro de las letras. Un solo
elemento por pantalla lo lleva; en una rejilla de tarjetas, jamás»*. ⚠️ El producto la usa como
**tarjeta de lo que se elige** con borde de 1 px (`#303`, `#323`, `[DECIDIDO owner]` sobre opciones
renderizadas): es una desviación consciente de su «un solo elemento por pantalla».
**Bordes** 1 px · 1,5 fantasma · 2 pegatina; nunca de color saturado alrededor de una tarjeta.
**Sombras**: dura `5px 5px 0 #101418` o ninguna; la difusa solo en modales `0 24px 60px rgba(0,0,0,.45)`.
▶ Paquete: `--shadow-lift: none` · `--shadow-float: 5px 5px 0 var(--paper-fg)` · `--shadow-modal`.
⚠️ El mobiliario del armazón lleva difusa por decisión del owner (`#217`: gana su mockup sobre su
norma `M-05`).
**Rotación**: −2° a −8°, solo manchas, cinta del eslogan y sello de precio.

---

## 5. Botones y texto resaltado

Primario **Naranja Salto** con texto tinta («184° de distancia de tono respecto al cian»), **uno por
pantalla**; secundario tinta en papel / cian en tinta; **fantasma siempre Azul Muro**. Anatomía: radio
10 (14 en grande) · alto mín. 48 · peso 800 Hanken, nunca Bungee · foco 3 px. Hover del sistema: *la
pegatina se aplasta* (translate 3 px · sombra 5→2; activo translate 5 · sombra 0 · 180 ms) — *el
color no cambia nunca en hover*. ▶ El producto responde con color (`--action-hover` ×0,88) y no
salta (`#321`): la física de aplastar está pendiente de decisión (auditoría D5).

Cuatro formas de resaltar y no hay quinta: **A** marcador amarillo (máx. 1 por bloque) · **B** palabra
en color (solo sobre tinta) · **C** enlace en claro Azul Muro (el cian queda de subrayado) · **D** dato
grande (precios en Lima; urgencia en Rojo; chips al 14 % sobre tinta).

---

## 6. Movimiento — «Todo rebota. Casi nada se mueve.»

*«La física de la marca ya está decidida por el producto: una lona que devuelve.»* Cuatro curvas:
**Bote** `.34,1.56,.64,1` 320 ms (entrada de tarjeta, hover de icono; el salto del hero la estira a
620) · **Lona** `.2,1.56,.25,1` 420 ms (cascada, sello, confirmación: **una por pantalla**) · **Salida**
`.4,0,.2,1` 180 ms (cierres, foco, **todo el hover**) · **Lineal** 900 en bucle (solo cargadores).
Duraciones 120 · 180 · 240 · 320 · 420 (techo) · 620 (única excepción, el hero) · 900.
Once microanimaciones; **máx. 2 a la vez**; **los únicos bucles son los tres cargadores** (aro
900, tres botes 900/120, barra 1400) — *«fuera de estos tres, cualquier bucle es ruido»*. Cuatro
que ocurren una vez: cargando→confirmado · sello de reserva (única rotación animada) · cascada de
franjas (desfase 90) · salto en la lona.
Prohibido: rebote en la salida · texto en movimiento · animar color de marca · cascada en cada
scroll. Movimiento reducido: fuera desplazamientos y escalas, **se mantienen** los fundidos de 120 ms.
▶ Paquete: `--ease-entra/cae/sale/bucle` y las siete duraciones son **las suyas**; el interruptor del
titular (3 ciclos, `#280`) y el latido del CTA doble son bucles que el owner declaró ambientales.

---

## 7. Iconos

*«Silueta plana, pegatina cuando grita»*: 47 iconos UI + 3 cargadores + **14 de zona en 7 familias**
(arte del parque) · rejillas 24 y 64 · `currentColor` siempre · 4 estados. Sus lotes de revisión
eligen `ui/registro` (persona y más), `ui/entrada` = pulsera (2a) o troquel 3c, `ui/reserva` =
calendario y check, `ui/cumple` = tarta o gorro, `ui/pack` = pila. ▶ En el producto: 55 componentes,
anatomía ejecutable (`#257`, `#258`); los 14 de zona **no están** — van por el hueco del kit.

---

## 8. Kit de fachada — 32 piezas de 40

`Elementos Fachada` (31-08, sano). Grupos **A** texturas (trama de puntos 20 px, trama que se apaga,
rayos, niebla) · **B** seis manchas fijas (se recolorean y giran, no se deforman; 01–02 redondas,
03–04 con lengüetas, 05–06 explosivas) y sus composiciones (titular sobre mancha, foto en mancha,
precio y promo girado, mancha de esquina) · **C** tiras (cinco colores en orden fijo: continua,
cuñas, punteada) y cinta del eslogan · **F** nueve poses de adulto (P1–P9) con friso, arco de rebote,
pareja, racimo, plano/contorno, troquel, enjambre, titular con pose, **iconos de zona** (Jump P1 ·
Ninja P3 · Foam P2 · Cumples P5), cinta de poses · **G** tres poses de niño (K1–K3, **62 % del
adulto**, en Kids manda el niño) · **E** aplicado (botones, etiquetas, tarjeta de zona).
❌ **Grupo D (siluetas) retirado entero** (`[DECIDIDO owner, 2026-08-30]`, `#281`) — del kit no
sale ninguna animación.
**Sus cinco reglas**: una mancha grande por pantalla · la pintura nunca debajo de un párrafo · trama o
rayos, nunca los dos · el rotulador solo para el eslogan · una pose no se repite en la misma pantalla.
▶ Mecanismo en el producto: `client-kit.svg` + `<use>` por nombre (`docs/specs/hueco-ilustracion.md`).

---

## 9. El orden de página que su diseñador propuso — dos versiones suyas

**`Colores de Marca` §14 (el «stack» 01–08)**: 01 cabecera fija (tinta) · 02 hero (vídeo + eslogan
a rotulador + CTA; el único Display XL) · **03 zonas del parque** (tarjetas E3, mancha de esquina) ·
**04 precios** (dato XL en Lima; máx. un sello B5) · **05 cómo funciona · normas** («cero manchas:
la pintura nunca debajo de un párrafo; enlaces en Azul Muro») · 06 castillo · Salta la Ciudad
(identidad local, sin CTA) · 07 cierre · 08 pie (mono 12, nada de color de acción).
**`Landing PJP Modos` (la landing montada)**: entradas · zonas · cumpleaños · **antes de venir**
(«Llega y salta: tres minutos de lectura y te ahorras la cola: normas, calcetines y registro») ·
info (horario y cómo llegar) · opiniones · reservar (cierre).
▶ Las dos ponen **«cómo funciona / antes de venir» como sección propia**, y la primera pone las
**zonas antes que los precios**. El producto hoy: tarifas → cumpleaños → zonas → visítanos → normas
→ dudas (`#314`). ⚠️ `[owner]`: de `Landing PJP Modos` **solo se toma la sección de reseñas** como
estructura; el resto es sistema visual, no arquitectura.

**Presupuesto de elementos por página (suyo)**: 1 Display XL · 1 rotulador · 1 campo de siluetas ·
1 sello de precio · 1 cinta del eslogan · **3 CTA de relleno (cabecera, precios, cierre)** · 1 mancha
grande por pantalla. *«El kit tiene 24 piezas y una página usa unas 15. Lo que sobra no es material
de repuesto: es lo que hace que el hero siga pareciendo el hero.»*

---

## 10. Lo que su diseñador ya razonó sobre PRECIOS y ZONAS (exploración, para no repetirla)

`Precios PJP variantes`, siete turnos. **Turno 4 — «el día se elige al reservar, no al mirar»**:
*«Fuera el conmutador: precio “desde” y el día donde ya hay que elegirlo, en el calendario. Para que
“desde” no huela a cebo, cada entrada lleva impreso el precio de finde en pequeño: nadie llega a caja
con otra cifra en la cabeza.»* Y sus dos avisos: *«“Desde” sin más es cebo»* · *«si vuelve atrás y
cambia de fecha, el resumen tiene que recalcular y avisar»*. Turno 3 (su recomendación entonces):
**3a «La taquilla»** — marquesina con el conmutador de día dentro y dos entradas troqueladas con talón
de precio; **3b «La tira»** — la entrada se arranca de la tira al elegir («la elección es el gesto,
no un radio button»). Turno 5b: **seis billetes a la vista, cero conmutadores** («se compara Kids y
Jump de un vistazo, sin pulsar nada»). Turno 7: bloques de tinta sobre papel, sin panel.
`Zonas PJP variantes`, tres turnos: **1a el plano** («pulsas una zona y aparecen sus juegos; la barra
se ve donde está de verdad») · **1b tres carriles** («todo a la vista, sin un solo clic») · 1c dos
pistas y una plaza · 2a galería por zona · 2b mosaico con filtro · 3a con el sistema puesto.
⚠️ Las variantes de zona incluyen la **zona de Ocio/bar**, que el owner **descartó** (`[DECIDIDO
owner, 2026-08-31]`): de ellas vale la forma de contar dos zonas, no la tercera pata.
`Cabecera Seccion variantes`: cuatro maneras de no partir la pantalla en dos (número de sección ·
bajada en la contraforma · mancha y pie · cinta de tinta). ▶ El producto eligió titular a **una
palabra sin etiqueta** (`#303`).

---

## 11. Dónde se aparta el producto de este sistema, y por qué (todo decidido)

| tema | su sistema | el producto | decisión |
|---|---|---|---|
| foco en papel | amarillo único | Azul Muro en papel, amarillo en tinta | `#209`, medido 1,49 |
| CTA del armazón en el menú | «el amarillo nunca es botón» vs. su mockup | aviso, como su mockup | `#213` |
| sombra del mobiliario | `M-05` solo modal | difusa en el racimo | `#217` |
| fondo de sección alterno | roles §05 | papel continuo | su propio `S-00` |
| logotipo | lockup CSS Lilita One + sombra | SVG en contornos, sin sombra CSS | `#254`, `#273`, `#275` |
| pegatina | un elemento por pantalla | la tarjeta de lo que se elige, borde 1 px | `#303`, `#323` |
| hover del botón | se aplasta | responde con color, no salta | `#321`; aplastar pendiente (D5) |
| bucles | solo cargadores | + interruptor 3 ciclos, latido, cinta | `#280`, `#205`, `#279` |
| grupo D del kit | ocho tratamientos | retirado | `#281` |
| zona de Ocio | tres zonas | dos | `[DECIDIDO owner, 2026-08-31]` |
