# [SPEC] Sustituir el idioma visual HEREDADO por el de este cliente

> Estado: 🟦 **TANDA A EN EL ÁRBOL** (el sistema de etiquetas + el motivo del cliente antiguo fuera).
> Última actualización: 2026-08-31 · Decisión asociada: **`DECISIONES #290`**.
> ⚠️ Su número se elige **mirando el REMOTO**, no el local: ya colisionó una vez con otro agente.
>
> ❗❗❗ **EL ENCARGO, EN PALABRAS DEL OWNER** (2026-08-31), y no es el que se venía haciendo:
> *«Más bien de "añadir cosas nuevas" de diseño, quiero "cambiar las que tengo"… La página web
> actual, las secciones, su orden, los elementos de diseño que tienen son del cliente antiguo, y
> necesito algo nuevo. Ya tenemos colores, formas, botones, radio, etc., pero faltan este tipo de
> elementos como los que tenemos en Elementos Fachada… Pondremos nuevos elementos de manera sutil,
> pero primero hay que cambiar lo que tenemos.»*
>
> ▶ **Esto CAMBIA el marco de `#286`.** Aquella regla —*la decoración va en la PANTALLA, no en el
> componente que se repite*— **sigue viva y no se toca**, pero es para DECORACIÓN AÑADIDA. Un badge,
> un separador o una cinta son **componentes funcionales**: su forma es suya y se repiten porque los
> datos se repiten. Confundir las dos cosas paraliza este carril entero.

---

## 1. El inventario, medido

Lo que hay hoy en la landing con idioma del cliente **antiguo**, y qué dice el nuevo para cada pieza.

| pieza | dónde | qué es hoy | idioma nuevo | estado |
|---|---|---|---|---|
| **`.jj-block`** — cuadrado «foam» girado 22° | portada, separando las cifras de zonas | **las iniciales del cliente antiguo dan nombre a la clase**; 6 variantes y **solo 1 usada** | su artboard no tiene ese motivo: separa con **tira `C2`** | ✅ **tanda A** |
| Badge de edad de zona | tarjetas de zona (×2 variantes) | pastilla blanca rellena, `--r-xs`, girada −2° | **DATO** (mono fino) | ✅ **tanda A** |
| Badge de atracción («XL») | `ride-card`, **sobre foto** | pastilla rellena del color de zona, girada −5° | **SEÑAL punteada** | ✅ **tanda A** |
| Badge de destacado («Top») | `price-card` | pastilla de tinta, girada **+6°** | **SEÑAL tinta** | ✅ **tanda A** |
| Chip de suplemento | `price-card` | pastilla gris rellena | **DATO** | ✅ **tanda A** |
| Etiqueta sobre foto | `/servicios` | pastilla de tinta, girada +5° | **SEÑAL punteada** | ✅ **tanda A** |
| **Marquesina de palabras** | `/servicios` | títulos en bucle, tipografía neutra | **`C3` cinta del eslogan**: rótulo sobre tinta, girada 2°, lenta | ⬜ **tanda B** |
| Cubos 1-2-3 (`.bd-proc__cube`) | «Cómo se reserva» del cumple | cuadrados redondeados de 44 px | por decidir | ⬜ |
| Nota de calcetines | portada, bajo precios | tarjeta con icono de línea | el icono ya es del set nuevo (`#257`); la caja no | ⬜ |
| Pliego de pictogramas | `/normas` | **imagen subida** del cliente antiguo | es CONTENIDO, no diseño: lo cambia el panel | ⬜ |
| Marquesina de polaroids | portada, galería | fotos giradas con marco blanco | por decidir | ⬜ |
| Badge de producto destacado | **SPA**, catálogo | — | va con el **cambio de presentación del catálogo**, que el owner quiere iterar aparte | ⬜ |

⚠️ **`.jj-block` sobrevive en UN sitio y es a propósito**: `BookingProgress.vue` lo usa dentro del
cajón (`.bk-context .jj-block`). El SPA es otra tanda —el owner lo separó— y renombrar la clase ahora
tocaría el contrato de **ÁRBOL** del cajón (`tests/Fixtures/sidebar-dom-manifest.json`).

---

## 2. ❗ La contradicción que hay que conocer antes de vestir nada

**Dos artboards del cliente visten los badges de forma distinta, y las dos formas son suyas.**

| | `Elementos Fachada` · E2 (el kit del mural) | `Landing PJP Modos` (su portada definitiva) |
|---|---|---|
| condición | `border: 2px dashed` tinta · `padding 6/13` | `border: 1.5px solid currentColor` · `padding 5/12` |
| tipografía | cuerpo **13 px / 700** | **mono 10,5 px / 400**, `letter-spacing .1em` |
| zona | cápsula llena + **silueta mini 14×24** | cápsula llena, mono |
| badge de atracción | punteada | mono **9,5 px sobre gris**, `padding 3/8` |

▶ **`[DECIDIDO owner, 2026-08-31]`, elegido mirando las tres renderizadas con nuestros tokens: entran
las DOS, repartidas por lo que el badge HACE.**

- **SEÑAL** → E2. Marca una zona, avisa de una condición **sobre una foto**, destaca una tarjeta.
- **DATO** → su landing. Una edad, un suplemento: se lee, no avisa.

*Su contradicción no se resuelve eligiendo un ganador: se resuelve diciendo para qué sirve cada uno.*

### 2.1 · Por qué la punteada es la de «sobre foto» y no la rellena

Medido en la hoja comparativa y confirmado en la página: sobre una imagen, **la punteada es la única
de las tres que se lee sin poner un rectángulo de color delante**, porque el trazo discontinuo se
distingue de cualquier textura. Y encaja con la regla del propio mural: *la pintura no tapa*.
▶ Consecuencia declarada: el badge de atracción **deja de ir relleno del color de zona**. El color de
zona sigue mandando en la tarjeta; lo que se retira es el parche encima de la foto.

---

## 3. Tanda A · el sistema de etiquetas (hecha)

### 3.1 · Lo que había: **cinco formas para la misma función**

Medido antes de tocar: radios `--r-xs`, `--r-pill` y `--r-md` · paddings **3/8 · 6/12 · 7/13 · 7/14 ·
8/14** · tallas **10 · 10,5 · 11 · 12,5 · 13** · y **cuatro rotaciones** (−2°, −5°, +5°, +6°).

▶ Es el hallazgo de `#196` con las sombras, otra vez: **no era una escala con ruido, es que no había
ninguna.**

### 3.2 · Lo que hay ahora

```
.tag                → canto de la escala (--r-pill) + el giro por PERILLA (--tag-tilt, defecto 0)
.tag--senal         → E2: gap 7 · padding 7/14 · cuerpo 13/700
  .tag--punteada    → + border 2px dashed currentColor · padding 6/13 (1 px menos: compensa el borde)
  .tag--tinta       → + fondo de tinta
.tag--dato          → landing: border 1.5px solid currentColor · mono 10,5/400 · padding 5/12
```

⚠️ **`--zona`, `--norma` y `--estado` de E2 NO se declaran**, y no es un olvido: hoy no tienen
consumidor, y `FacadeCssHasNoOrphansTest` pone la suite roja con una regla sin pantalla. **Entran con
su pantalla.** El sitio natural de `--zona` (cápsula llena con silueta mini, que es el ejemplo
cabecera de E2) son las **pestañas de zona**; queda propuesto, no hecho.

⚠️ **Sin `text-transform` en el registro SEÑAL**: el texto de estos badges lo escribe el PANEL
(`tr('badge')`), y forzar mayúsculas aquí decidiría por el operador. **Consecuencia visible**: donde
el panel tiene «Top», ahora se lee «Top» y no «TOP». En el registro DATO sí van en mayúsculas, porque
así lo escribe él y porque un dato en mono se lee como ficha técnica.

### 3.3 · El giro: **uno solo**

`[DECIDIDO owner]`: **−2°** en todas, y solo en las que se pegan **encima** de algo. Una etiqueta en
el flujo del texto no se gira. Medido en su landing: sus giros son −2°/−3° y son pocos.

### 3.4 · El separador: la punteada, **como token y no como clase**

`[DECIDIDO owner]`: fuera el «foam», y se separa como él — con la tira punteada de `C2`.

⚠️⚠️ **La primera versión era un `<span class="brand-dots">` por hueco, dentro del `@foreach`, y
`FacadeDecorationIsPerScreenTest` la puso ROJA con razón**: una textura repetida por fila es lo que
el owner rechazó tres veces en `#286`. ▶ **Un separador es PUNTUACIÓN, y la puntuación la pinta el
CSS** (`.zone-stats__item + .zone-stats__item::before`). El patrón entra como token `--dots-tile`, así
que sigue disponible para la tira completa el día que alguien la necesite **sin quedarse huérfano hoy**.

### 3.5 · La guarda, y lo que vigila de verdad

`TagSystemTest` (5 casos, **5 mutaciones muerden**). El caso que importa es
`test_no_consumer_redeclares_the_shape`: **un sistema de etiquetas no se rompe de golpe**, se rompe
cuando la siguiente tarjeta necesita «un pelín más de padding» y se lo escribe en su regla. A los seis
meses hay cinco formas otra vez — que es el estado del que se viene.

▶ Ya cazó una: el `<b>` del chip de suplemento seguía con mono **600 a 12,5 px** dentro de una
cápsula que ya es mono de 10,5. Dos tallas dentro de la misma etiqueta, y no lo veía nadie.

---

## 4. Lo que queda, y en qué orden

| # | tanda | qué |
|---|---|---|
| **B** | La marquesina de `/servicios` → **cinta `C3`** | `[DECIDIDO owner]` quitar la de palabras. ⚠️ **Choca con `#252`**, que retiró la marquesina de la portada por espacio: la cinta vuelve, pero en `/servicios`, no en la portada |
| **C** | Los cubos 1-2-3 del cumple · la nota de calcetines | forma por decidir |
| **D** | La galería de polaroids | ¿sigue el lenguaje polaroid o pasa a cinta con poses (`F11`)? |
| **E** | **El SPA**: catálogo y badge de destacado | el owner quiere **cambiar la presentación**, no solo vestirla: es su propia spec |

### 4.1 · Decisiones abiertas

1. **Las pestañas de zona**: ¿entran como `--zona` con la silueta mini del kit? Es el ejemplo
   cabecera de E2 y el único sitio donde esa variante tiene sentido hoy.
2. **«Top» en caja normal** en vez de «TOP»: es consecuencia de respetar lo que escribe el panel.
   Si se prefiere en mayúsculas, es una línea — pero entonces el panel deja de mandar.
3. **El pliego de pictogramas de `/normas`** es una imagen del cliente antiguo: no se arregla con
   CSS, lo sustituye el operador desde el panel.
