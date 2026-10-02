# [SPEC] La analítica para decidir — un cuadro que se entiende, dice si va bien o mal y cubre las decisiones del operador

> Estado: ✅ **aprobada por el owner el 27-09** (§7; `#755`) → ✅ **T0, T1 y T2** · 🟦 **T3** (✅ T3a · ✅ T3b · ✅ T3c · ✅ T3d · ⏸ T3e sin fuente, `#799`; §4.13) · 🟦 **TP el público** (§4.14: ✅ TP·1 · ✅ TP·2 · ✅ TP·3a·3b · ⏸ TP·3c) · ✅ **T4 la cartera** (§4.8.quater) · ✗ **T5 el marketing con coste, no se hace** (`#800`) · ⬜ T6–T8 · Última actualización: 2026-09-29 ·
> Decisiones: `#755` (esta), `#754` (encuestas anónimas, su T1), `#758` (la T2), `#759` (la T3 en cinco tandas), `#792` (el público), `#793` (el público, anónimo; las felicitaciones), `#798` (el techo del texto para IA), `#799` (sin referencias del sector), `#800` (sin marketing con coste) · Carril: **SPA** (banda 790–819). Amplía `analitica.md`
> (el libro, los regímenes y la T2 siguen siendo suyos).

## §0 · Antes de tocar

- **La regla que ordena todo**: el operador no es analista. Cada pestaña contesta UNA pregunta; cada cifra dice qué
  es, contra qué se compara, si va bien o mal y cómo se calcula; lo que no ayuda a decidir se pliega o baja a
  «Calidad del dato». Objetivos, referencias y umbrales son DATO del panel, nunca código.
- **Empieza por** §1 (lo medido el 27-09) → §4.1 (las siete pestañas) → §4.2 (la anatomía de una cifra) → §4.3 (el
  tiempo) → §4.12 (las tandas).
- **Trampas**: (1) «Este mes» llega al día 30 y se compara con 30 días enteros: el Δ de un periodo en curso miente
  (T0, `Window`); (2) un % sobre base pequeña engaña («+900 %») y el Δ de una tasa va en PUNTOS; (3) las pestañas
  inactivas de Filament CARGAN (12 peticiones al abrir, `#736`): con siete, carga por pestaña; (4) el texto para IA
  sale a un tercero: SOLO agregados, con guarda; (5) los enlaces FIRMADOS de los correos: la marca de envío va por el
  camino de `EmailUtm` (tras firmar, ignorada al validar); (6) aperturas solo con consentimiento (`[PENDIENTE: asesoría]`).
- **Estado**: ✅ aprobada (27-09, `#755`); T0a·T0b·T0c ✅ · **T1** ✅ (la T5 de `encuestas.md`, `#754`, `#757`) · **T2** ✅ ocupación (§4.8.ter, `#758`) → **T3** Resumen, en cinco tandas (§4.13,
  `#759`): ✅ T3a la forma · ✅ T3b veredicto (mín–máx, `#790`) · ✅ T3c·1 lo que ha cambiado (`#791`) · ✅ T3c·2 objetivos →
  ✅ **TP el público** (§4.14, `#792`/`#793`; TP·1 · TP·2 · TP·3a tramos · TP·3b sin exportación · ⏸ TP·3c) → ✅ T3d
  (el texto para IA, `#798`) → ⏸ T3e (sin fuente, `#799`) → ✅ T4 la cartera (§4.8.quater) → ✗ T5 marketing con coste,
  no se hace (`#800`) → ⬜ T6–T8.
  **Nada de lo medido se pierde** (§4.1.bis, con guarda): se resume arriba y lo demás queda
  plegado o en su pestaña.
- **Invariantes**: `RGPD-01`, `RGPD-04`, `RGPD-07`, `SEC-04`, `SUITE-01`. Dinero y aforo: solo lectura.

## 1. Contexto y problema — MEDIDO (2026-09-27)

Con `scripts/sonda-analitica-panel.mjs revision-2709` (34/34, escritorio 1440 y móvil 390, BD local con los fixtures
del carril) y leyendo los informes:

| Qué | Medida |
|---|---|
| Tamaño del cuadro | **5 pestañas, 44 tarjetas** (Dinero 12 · Clientes 11 · Conversión 6 · Fiestas 9 · Encuestas 6), **15 gráficos, 33 tablas**; **12 peticiones** de Livewire al abrir |
| Veredicto | **Ninguna** cifra dice si es buena o mala; el único color es el del Δ |
| Cabecera | La misma frase técnica en las cinco pestañas («Las cifras salen de las mismas filas que el libro de cada pedido…»), también en «Encuestas» |
| Bases pequeñas | «+900 % frente al periodo anterior» (de 4 a 40 respuestas), «+600 %» (de 1 a 7) |
| Tasas | «Conversión 7,4 % · −20 %»: un Δ relativo de un porcentaje |
| Señales cruzadas | «Cobrado online −27 %» junto a «Vendido +8 %», sin explicar la señal ni el pago en el parque |
| Jerga | «Sesiones identificadas», «Fuera del recuento: 56 bots · 1 internas», «primer toque», «Anterior a la medición» y el canal «Sistema» (pedidos nacidos fuera de una petición: consola, procesos, fixtures; `AttributionContext::CHANNEL_SYSTEM`) |
| «Clientes» | 8 tarjetas de MECÁNICA de la puerta (búsquedas, tecleadas, escaneos, encontradas, fichas…) para contestar «¿cuánta gente vino?» |
| Móvil (390) | Cabecera y filtros llenan la primera pantalla: la primera cifra empieza a unos **620 px**; las pestañas se cortan (se ven 3 de 5) |
| Botones | «Exportar segmento» en la cabecera de las cinco pestañas: solo es de «Clientes» |
| **Rigor del tiempo** | `ReportPeriod::ThisMonth` = [día 1, **fin** de mes] y `Window::previous()` toma la misma longitud: el 27-09 compara **27 días con datos contra 30**; «Este año» frente al año pasado, ~270 días contra 365. **Todo Δ de un periodo en curso sale sesgado a la baja** |
| Datos sin leer | `availability_missing` (demanda sin hueco), `email_sent`, `email_clicked`, `time_chosen`, `line_removed`, `section_viewed`: se registran y **ningún informe los lee** |
| Aforo | `slots.capacity`/`online_capacity` existen; **ningún informe los lee**; «Hoy» (`DashboardStatsWidget`) suma plazas vendidas, no el % del aforo |
| Motivos | `payment_refunds.reason` (el motivo interno del reembolso) se guarda y no se enseña |
| Reseñas | `GoogleBusinessReview*` en la BD, fuera del cuadro; **la nota no guarda historia** (no hay tabla de resumen) |
| Correos | `email_sent` con persona (`RecordEmailSent`); `email_clicked` con persona SOLO si ese navegador tenía sesión (`RecordEmailClick`); aperturas, ninguna (fuera de alcance en `analitica.md` §2) |
| Anuncios | No hay gasto (`ad_spend` era la T2e, «cuando el volumen lo pida») |
| Promociones | `promotions` (plataforma, T1 en el árbol) fuera del cuadro |
| Datos reales | **Ninguno**: la analítica espera la v2.0.0 (`#670`) |

**El problema, en una frase**: el cuadro mide mucho y no dice nada. Un operador que no es analista ve 44 números sin
saber cuáles importan, si van bien, ni qué hacer; y las decisiones que más dinero mueven (precio y horario por franja,
dónde gastar en anuncios, cuándo empujar una promoción) no tienen número.

## 2. Objetivo

Que el operador, en un minuto delante de «Resumen», sepa **cómo va, qué ha cambiado y qué hacer**, y que cada decisión
de la tabla de §4.1 tenga su número, bien comparado.

Criterios de éxito, medibles (sonda y tests):
1. Cada pestaña lleva su PREGUNTA en la cabecera; **≤ 6 tarjetas** arriba (Resumen: 8), **≤ 3 gráficos** visibles, las
   tablas plegadas.
2. Cada cifra: nombre llano, valor, comparación con el MISMO TRAMO transcurrido, veredicto (bien · atención · normal · sin
   referencia) con la fuente de la referencia, una frase, y «¿Cómo se calcula?» a un toque.
3. Ningún % sobre una base < 20; toda tasa compara en puntos.
4. Móvil (390×844) y tablet (1080×810, el aparato del panel): la primera cifra dentro de la primera pantalla y las siete
   pestañas alcanzables sin desplazamiento escondido.
5. «Resumen»: ≤ 8 cifras y ≤ 5 frases de «lo que ha cambiado».
6. El texto para IA: solo agregados (guarda: ni nombre, ni correo, ni teléfono, ni texto libre, ni celdas < 5), ≤ 12 KB
   (`[DECIDIDO owner]` 29-09, `#798`; eran 8 KB, y el peor caso medido ocupa 10,2).
7. Abrir una pestaña pide solo sus widgets; cada informe ≤ 20 consultas con caché de 5 min (el presupuesto de la T2).
8. Cero rótulos de la lista de jerga (§4.11) en pantalla.

**Fuera de alcance**: la IA dentro del panel (después, si el owner quiere: coste por uso y un proveedor más); leer el
gasto de anuncios por la API de cada plataforma; el clima; una referencia entre instancias de JumpWeb (necesita el
acuerdo de cada cliente); una PREVISIÓN estadística (la cartera de §4.8 es lo vendido, no una predicción); el tiempo real.

## 3. Opciones consideradas

- **A · Añadir pestañas y tarjetas al cuadro de hoy.** Descartada: pasaría de 44 a 70 cifras sin veredicto; es el «lío»
  que el owner quiere evitar.
- **B · Dos niveles: «Resumen» que decide + pestañas por PREGUNTA con una anatomía común de cifra — ELEGIDA.** Todo lo
  medido sigue, pero ordenado por la decisión que sirve, y lo técnico baja a «Calidad del dato».
- **C · Un generador de informes a medida** (el operador elige cifras y cortes). Descartada: traslada el trabajo de analista
  a quien no lo es.
- **D · Una herramienta externa** (Looker Studio, Metabase). Descartada: la verdad es propia (`#678`), otra cuenta, otro
  sitio, y sin las reglas de dinero del libro.

## 4. Diseño elegido

### 4.1 Siete pestañas, una pregunta cada una

| Pestaña | La pregunta | Tarjetas (≤ 6) | Gráficos | Plegado al pie | La decisión que sirve |
|---|---|---|---|---|---|
| **Resumen** (nueva, primera) | ¿Cómo vamos y qué hago? | 8 cifras clave (§4.5) | la marcha frente al objetivo | — | todas: por dónde mirar |
| **Dinero** | ¿Cuánto ganamos y de qué? | Ingresos netos (principal) · Vendido · Pendiente de cobrar en el parque · Devuelto · Pedidos · Valor medio | ingresos por semana frente a la comparación · por producto | canal, método, señal, perdido, por día | precios, qué vender |
| **Ocupación** (nueva) | ¿Cómo de lleno está el parque, y cuándo? | Ocupación media · Franjas llenas · Ingreso por plaza ofrecida · Anticipación · Demanda sin hueco | mapa de calor día × hora · anticipación por tipo | por franja, por zona, por producto | horarios, precio por franja, personal, cuándo lanzar |
| **Clientes** | ¿Quién viene y quién vuelve? | Visitantes · Nuevos · Repiten · Vuelven a los 90 días · Valor de vida · Registros | nuevos frente a repiten · cohortes | la puerta (su mecánica), cómo se registran, segmentos | fidelizar, recordatorios |
| **Marketing** | ¿Qué trae ventas y cuánto cuesta? | Visitas a la web · Conversión · Ventas por anuncios · Coste por venta · Retorno por euro · Clics en correos | embudo · ingresos por fuente · correos por tipo | páginas, dispositivos, idioma, experimentos, **Calidad del dato** | dónde gastar, qué correo funciona |
| **Fiestas** | ¿Cómo van los cumpleaños? | las 9 de hoy, recortadas a 6 | las 3 de hoy | como hoy | el producto fiesta |
| **Satisfacción** (era «Encuestas») | ¿Están contentos? | Nota media · Nota de Google · Notas bajas · Volvieron tras una nota baja · Tasa de respuesta | la nota en el tiempo (encuestas y Google) · reparto por pregunta | por franja, tipo de visita, primera visita (mín. 5), textos | qué arreglar |

- **«Calidad del dato»** (plegada al final de Marketing): bots, internas, eventos rechazados, pedidos «sin dato (antes de
  medir)», el canal «Sistema», y la **cobertura del consentimiento** (qué parte de las visitas aceptó «análisis»: lo que
  depende de él se lee con esa cifra al lado). Es donde vive hoy la jerga, y ahí se queda.
- **La ficha del cliente (360)** gana «Correos» (§4.9) y pierde «Encuestas» (`#754`).

### 4.1.bis Nada de lo medido se pierde: dónde va cada cifra de hoy

`[DECIDIDO owner]` 27-09 («no quitaremos contenido, ¿no?»): **se resume, no se quita**. Arriba queda lo que decide; el
resto, plegado a un clic en su pestaña, y TODO sigue en el CSV. Las 44 tarjetas y las 33 tablas medidas el 27-09 (§1):

| Hoy | Va a |
|---|---|
| Dinero: Ingresos netos, Vendido, Devuelto, Pedidos cobrados, Valor medio del pedido | **Dinero, arriba** (+ «Pendiente de cobrar en el parque», que hoy vive en la tabla «La señal») |
| Dinero: Cobrado online, Gestiones posteriores | Dinero, plegado |
| Dinero: Compradores, Nuevos, Recurrentes, Valor medio por cliente, Valor de vida medio | **Clientes** (arriba: Nuevos, Repiten, Valor de vida; plegado: los otros dos) |
| Clientes: Cuentas nuevas | Clientes, arriba («Registros») |
| Clientes: Con el correo verificado, Que han comprado alguna vez | Clientes, plegado («Cómo se registran») |
| Clientes: las 8 de la puerta (búsquedas, tecleadas, escaneos, encontradas, distintos, fichas, visitas acreditadas, clientes con visita) | Clientes, plegado («La puerta») |
| Conversión: Visitas, Conversión | **Marketing, arriba** (las compras y lo cobrado, en la línea de detalle de «Conversión») |
| Conversión: Compras por la web o la app, Cobrado en esas compras | Marketing, plegado |
| Conversión: Sesiones identificadas, Fuera del recuento | Marketing → «Calidad del dato» |
| Fiestas: Fiestas, Vendido después de reservar, Cobrado en el parque, Formularios completados, Respuestas «sí», Justificantes firmados | Fiestas, arriba |
| Fiestas: Reservas con extras, Extras por reserva, Dentro del plazo | Fiestas, plegado |
| Encuestas: Nota media, y las dos tasas unidas en «Tasa de respuesta» | **Satisfacción, arriba** |
| Encuestas: Contestadas, Correos mandados, No preguntadas, y cada tasa por separado | Satisfacción, plegado |
| Las 33 tablas | En su pestaña, plegadas (las de Conversión, en Marketing; «Eventos rechazados», en «Calidad del dato») |

**Lo único que SE VA**, y por decisión: «Por atender» con nombre y el bloque «Encuestas» de la 360 (`#754`: pasan a
«Notas bajas y si volvieron», sin persona), y la frase técnica de la cabecera, que baja a «¿Cómo se calcula?».
**Guarda** (T0, `AnalyticsCensusTest` (futuro)): el censo de hoy, tecleado a mano, y cada cifra tiene que seguir existiendo
en una tarjeta, una tabla plegada o el CSV; quitar una rompe el test.

### 4.2 La anatomía de una cifra (un solo componente)

> ✅ **T0b, construida (27-09; `6243e8d9`, vista y aprobada por el owner)** — y corrige lo de abajo en DOS cosas: (1) **el color
> es de un cambio CLARO al 95 %, no de tener base**: recuentos, prueba binomial condicionada con la duración de cada
> ventana; tasas, intervalos de Wilson que no se solapan; dinero, la SUMA DE LOS CUADRADOS de sus importes (una suma:
> Poisson compuesto; una media: dos medias con su error), que los informes traen de las mismas consultas (`*_sq`). Sin
> ella, gris: el primer borrador coloreaba el dinero con base, y «Valor medio del pedido −1 %» salía en ROJO (sonda del
> 27-09). (2) El **veredicto normal/bajo/alto** y la frase siguen en la T3: la T0b pinta el cambio y su nota («pocos datos
> para comparar», «no es un cambio claro: puede ser azar», el intervalo de una tasa). Detalle y lo que enseñó, al final
> de esta sección.

Todas las tarjetas salen de un objeto `Metric` de la capa de entrega y de UN componente de pantalla; los
informes dejan de devolver arrays sueltos para las tarjetas (las tablas y el CSV siguen como están, `tablesFor()`).

- **Nombre llano** (del glosario, §4.11) · **valor** · **comparación**: «frente al 1–27 de agosto: +310 € (+8 %)», el
  absoluto SIEMPRE y el relativo solo si la base es ≥ 20; una TASA compara en puntos («+1,2 puntos»).
- **Polaridad** por cifra (`up_is_good` · `down_is_good` · `neutral`): sin ella el color del Δ miente («Devuelto −19 %» es
  bueno; «No preguntadas +600 %», malo; «Pedidos del panel», ninguna de las dos).
- **Veredicto**: `good` · `watch` · `normal` · `none`, con icono Y palabra (nunca solo color) y la fuente al lado: «Normal
  para ti (60–75 %)» o «Sector: 1–3 % · fuente». Sin referencia, `none` y no se inventa.
- **Una frase** (§4.6) y **«¿Cómo se calcula?»**: definición, de qué tabla sale, qué excluye y desde cuándo hay datos.
- **Pocos datos**: con una base menor que el mínimo de la cifra, el valor se enseña y el veredicto no («pocos datos para
  juzgar»); las tasas llevan su intervalo de Wilson al 95 % como los experimentos (`ExperimentsReport`).
- **Números**: euros sin céntimos en las tarjetas a partir de 100 € (con céntimos en las tablas y el CSV); un decimal
  como mucho en porcentajes.
- **Cómo se construyó (T0b)**: `Metric` (`count` · `money` · `rate` · `text`; clave estable, polaridad `Polarity` SIN valor
  por defecto, base, sumas de cuadrados, `detail`) y su `reading()`; la vista `filament.widgets.analytics.metric` (el marcado
  de la tarjeta de Filament, las notas y «¿Cómo se calcula?» en un `<details>` con zona de toque de 44 px, sin JS); el
  trait `AnalyticsWidget::metric()` con `windowShare()` (qué parte del tiempo es de cada ventana). Las 44 tarjetas migradas
  con su polaridad declarada y su definición escrita DESDE EL CÓDIGO de su informe (`admin.analytics.how.*`, es y zh_CN).
  `Delta` y los diez `*_hint` que pasaron a las definiciones, retirados. Informes: `refunds` y las `*_sq` (dinero,
  conversión, fiestas), sin consultas nuevas. Tests: `MetricTest` (13), `AnalyticsCensusTest` (4: las 44 del 27-09 en el
  CSV, cada tarjeta con su definición en los dos idiomas, la duración de las ventanas, ningún widget sondea); arnés
  `mutar-analitica-decidir.sh` **42/42 + control**; sonda 35/35 y consola limpia.
- **Lo que enseñó la T0b**: (1) **«Cobrado online» mentía**: suma TODOS los cobros con éxito, también el efectivo y el
  datáfono del mostrador; ahora «Cobrado» (y «Cobro medio»), con la definición exacta. (2) **La pista de «Clientes con
  visita acreditada» hablaba del botón de la puerta**, que no existe desde `#234`: la visita se acredita al escanear
  (`#741`). (3) **Cada widget del cuadro sondeaba el servidor cada 5 s** (`CanPoll` de Filament): el cuadro quieto pedía
  una petición cada 5 s (6 en 30 s) para informes cacheados 5 min, y la que estaba en vuelo al tocar el filtro se abortaba
  en la consola (siete rechazos `{status: null…}`); sin sondeo, 0 en 30 s. (4) Un `share()` del trait lo PISABA el de
  `RegistrationsWidget` (el «96 % de las 47»): «Clientes» habría reventado; lo cazó el censo, y se llama `windowShare()`.
  (5) Un cero en el periodo comparado sigue diciendo «Sin datos…»: puede ser «aún no se medía», y «+1.531 € frente al año
  pasado» sugeriría un crecimiento que nadie ha visto. (6) `getByText` de Playwright busca por subcadena: «El desglose»
  casaba con un «¿Cómo se calcula?» plegado; la sonda espera ya una coincidencia VISIBLE.

### 4.3 El tiempo, bien comparado (T0)

- **Un periodo en curso termina HOY** para lo que ya pasó (cobros, pedidos, visitas): «Este mes» el 27-09 es 1–27 de
  septiembre, y la comparación es el MISMO TRAMO (1–27 de agosto; o 1–27 de septiembre de 2025). El rótulo lo dice con
  fechas. Lo que mira hacia delante (la cartera, la señal pendiente) lleva su propia ventana.
- **Día y semana se comparan con el mismo día de la semana**: en un parque un sábado no se compara con un viernes. «Ayer»
  compara con el mismo día de la semana anterior; «Esta semana», con los mismos días de la anterior.
- Se hace en `Window`/`ReportPeriod`/`Comparison` (un solo dueño) con su test: el 27 de un mes compara 27 días a cada
  lado, y el cruce de medianoche del parque sigue la regla de `SqlTime`.
- ✅ **T0a, construida (27-09; `0dc5d317`, vista y aprobada por el owner en vivo)**: `Window` sabe su UNIDAD (`UNIT_DAY` · `WEEK` ·
  `MONTH` · `QUARTER` · `YEAR` · `SPAN`) y su FIN de periodo; `ReportPeriod::window()` la corta con `upTo(ahora)`, y
  `previous()`/`yearAgo()` desplazan principio, corte y fin con la MISMA regla, en hora de pared del parque (día y
  semana −7/−364 días; mes, trimestre y año sin desbordar; tramo, su longitud). `Comparison` no cambia. Bajo el filtro,
  `WindowLabel` escribe las dos ventanas («Del 1 al 27 sep. 2026, hasta ahora» · «Del 1 al 27 ago. 2026, hasta la misma
  hora»), con los formatos como dato del idioma. Tests: `ReportPeriodTest` reescrito (24), `WindowLabelTest` (3),
  `MoneyReportTest` +1 (el tramo, con el cobro de este segundo dentro y el del siguiente fuera), `AnalyticsPageTest` +1;
  arnés `scripts/mutar-analitica-decidir.sh` **17/17 + control**; sonda 35/35. **Medido con los datos locales** (no
  reales), la misma pestaña antes y después: «Cobrado online» −27 % → −14 %, «Ingresos netos» −27 % → −16 %,
  «Conversión» −20 % → −9 %, «Visitas» +36 % → +53 %.
- **Lo que enseñó la T0a**: (1) **lo escrito en el segundo en curso** quedaba fuera: la ventana es `[from, to)` y la BD
  guarda los instantes sin fracción; el corte es el principio del segundo SIGUIENTE. (2) Un **freno** «el corte no pasa
  del fin» sobrevivió al arnés: los desplazamientos son monótonos y era código muerto; se quitó, y lo que sostiene «el 31
  de marzo contra febrero entero» es el `NoOverflow`, que sí muerde. (3) Los fixtures de junio sembraban cobros el 30 con
  el reloj en el 10: la aritmética del mes entero se mide con una ventana explícita (`Window::ofDays(…, UNIT_MONTH)`),
  y el corte, con su caso. (4) ⚠️ **«Fiestas» de «Este mes» cuenta ahora las celebradas HASTA HOY** (se corta por fecha de
  la fiesta) frente al mismo tramo del mes anterior; las que aún vienen son de la cartera (T4). La señal pendiente no
  cambia: se corta por el cobro y se reparte por la visita frente a hoy.

### 4.4 Referencias: normal, bajo o alto

- **Tu historia, primero** (la más fiable): la banda «normal para ti» es el intervalo entre ~~los percentiles 25 y 75~~ **la
  más baja y la más alta** (`[DECIDIDO owner]` 28-09, `#790`: la P25–P75 marcaba el 57 % de los periodos normales; §4.13) de la
  misma cifra en los últimos 12 periodos COMPARABLES (mismo mes o mismo día de la semana); hacen falta ≥ 8, y antes dice
  «aún sin historia».
- ⏸ **`[DECIDIDO owner]` 29-09 (`#799`): sin referencias del sector por ahora** —no hay en abierto un rango con método para
  un parque de salto (§4.13, «T3e, la búsqueda completa»)—; lo de abajo se retoma solo si aparece esa fuente.
- **El sector, solo con fuente**: `analytics_benchmarks` (futuro): sector, clave de la cifra, bajo, alto, unidad, fuente
  (título, enlace, fecha), editable en «Ajustes». El producto trae de serie SOLO los rangos con fuente publicada; sin
  fuente, no hay fila. El sector de la instalación es un ajuste (JumpWeb es multisector). La lista de rangos y sus
  fuentes se investiga y se trae al owner en la T3, antes de sembrar nada.
- Nunca se mezclan: la cifra dice CUÁL de las dos usa.
- **Objetivos**: `analytics_goals` (futuro): cifra, mes, objetivo; se ponen en «Resumen → Objetivos»; la tarjeta enseña el
  avance frente al ritmo del mes («vas al 82 % del objetivo con el 90 % del mes pasado»).

### 4.5 «Resumen»: ocho cifras, lo que ha cambiado y «Explícamelo»

- **Las ocho**: Ingresos netos · Ocupación · Visitantes (las plazas de las visitas del periodo, por fecha de visita) ·
  Conversión de la web · Valor medio del pedido · Clientes que repiten · Satisfacción · Cartera de los próximos 30 días.
  Cada una enlaza a su pestaña.
- **Lo que ha cambiado** (≤ 5 frases): una cifra entra si sale de su banda normal Y su base alcanza el mínimo (en tasas,
  intervalos que no se solapan); se ordenan por dinero en juego. Sin ninguna: «Nada fuera de lo normal». Reglas fijas,
  sin IA, con tests.
- **Objetivos del mes** y el botón **«Explícamelo con IA»** (§4.7).

### 4.6 Que el cuadro hable

- Cada tarjeta lleva una frase de plantilla, determinista: «Has vendido 4.200 €, un 12 % más que del 1 al 27 de agosto:
  bien.» Claves en `lang/{es,zh_CN}/admin.php` (los dos idiomas del panel), una plantilla por polaridad y veredicto.
- La cabecera de cada pestaña es su pregunta (§4.1), no una nota técnica; la nota baja a «¿Cómo se calcula?».

### 4.7 «Explícamelo con IA»

- Un botón en «Resumen» abre un texto listo para copiar (Markdown, ≤ 12 KB desde `#798`) y «Copiar». Lleva: las instrucciones
  («explica en lenguaje llano; tres cosas que van bien, tres que vigilar y tres acciones; di cuándo hay pocos datos; no
  inventes»), el negocio en genérico (sector, idioma), el periodo y su comparación, cada cifra con su definición, valor,
  comparación y referencia CON su fuente, «lo que ha cambiado» y las notas de calidad del dato. En el idioma del panel.
- **Se construye SOLO desde los `Metric`** (agregados), nunca desde filas: guarda que falla si el texto lleva un correo,
  un teléfono, un nombre de la tabla `users`, un texto libre de encuesta o una celda < 5. Rastro `analytics.explained`
  (sin PII) como el CSV.
- Después, si el owner quiere: el mismo texto a la API de una IA dentro del panel (proveedor, clave y coste: decisión suya).

### 4.8 Ocupación, anticipación y cartera (T2 y T4)

- **Ocupación** = plazas vendidas (líneas vivas de pedidos cobrados, la regla de `paidScheduledPrincipal()`) / aforo
  ofrecido (`slots.capacity`; y la online contra `online_capacity`), por fecha y hora de pared de la franja (sin
  convertir). ⚠️ **A medir en la T2**: si la venta en taquilla entra como pedido; si no, la ocupación total cuenta de menos
  y la tarjeta lo dice.
- **Mapa de calor** día de la semana × hora, con el % escrito en cada celda (no solo el color). **Franjas llenas**: cuántas
  al 100 % online y con cuánta antelación se llenaron (último cobro frente al inicio). **Ingreso por plaza ofrecida**.
  **Demanda sin hueco**: `availability_missing` por producto y mes.
- **Anticipación**: días entre el cobro y la visita, mediana y reparto por tipo (entrada, fiesta, grupo) y por día de la
  semana. Es lo que dice CUÁNDO lanzar: si los sábados se compran dos días antes, la promoción sale el miércoles.
- **Cartera** (T4): plazas y euros ya vendidos para los próximos 7, 30 y 90 días, frente a lo que había vendido A ESTAS
  ALTURAS el año pasado (cobros hasta hoy − 1 año para visitas en la ventana − 1 año); sin año anterior, frente a la
  media de las 4 semanas previas al mismo horizonte. Por semana que viene, barras «vendido ya» frente a «a estas alturas».

### 4.8.ter La T2 al detalle — medido el 27-09 noche, antes de codificar (`#758`)

**Medido** (BD local): la venta en taquilla SÍ entra como pedido cuando se hace desde el panel (`attribution_channel =
panel`, 9 cobrados, con efectivo y datáfono); la que se cobre fuera del sistema no existe para el cuadro, y la tarjeta lo
dice. `slots.capacity` = `online_capacity` en las 3.666 franjas locales (en una instalación pueden diferir: la reserva de
taquilla). `slots.seats_taken` está MUERTA (`MODELO-DATOS.md` §6): no se usa. La zona `jump` vende entradas Y dos packs
(sus fiestas ocupan plazas de salto, como cuenta el aforo). El tope de fiestas es un ajuste (`packs.max_per_slot` = 5,
`packs.max_guests_per_slot` = 60) con override por zona. **`availability_missing` está en el `Contract` y NADIE lo emite**
(0 eventos): la demanda sin hueco no tenía de dónde salir. La rejilla SE SOLAPA (`AFORO-12`): sumar franjas contaría dos
veces; la regla es la de PRESENCIA.

**`[DECIDIDO owner]` 27-09 (`#758`)**: (1) **la demanda sin hueco se mide desde ya** (el cajón la emite en esta tanda; la
isla, plataforma, por buzón); (2) **dos cifras**: la ocupación de las ENTRADAS (plazas) y las FIESTAS por franja (fiestas
frente al tope), nunca mezcladas.

**Las definiciones** (las cierra el agente contra el objetivo —«rigurosa y clave para decidir»—, cada una en su «¿Cómo se
calcula?»):
- **La fuente**: `Booking\Services\OccupancyReader` (futuro) calcula, por LOTES (tres o cuatro consultas por ventana), la
  presencia de cada punto de la rejilla con la MISMA aritmética que `SlotAvailability::occupancyMap()` (entradas: toda
  línea principal viva de pedido PAGADO que ocupa plazas en esa zona, en cada franja cuyo inicio cae en su tramo;
  `duration_min + extra_minutes`; nula = hasta el cierre) y que `PackAvailability::occupancyMaps()` (fiestas: su ventana con
  la preparación si la zona la cuenta). Sin pendientes ni cestas: es lo que PASÓ. **Paridad**: tests contra los dos
  contadores sobre fixtures con la rejilla solapada, duración ilimitada, hora extra, preparación y líneas canceladas.
  No se tocan los ficheros del `CRITICAL_RE`.
- **Ocupación de las entradas** = Σ presencia / Σ capacidad en los puntos de las zonas que venden entradas (sin franjas
  cerradas), por fecha de VISITA hasta hoy. Cada punto es un momento del parque: con la rejilla de 30 min, cada media
  hora cuenta una vez.
- **Fiestas por franja** = Σ fiestas presentes / Σ tope, en los puntos de las zonas con packs; con tope 0 («sin tope»)
  se enseña el recuento y no el %.
- **Franjas llenas** = puntos con la presencia ≥ `online_capacity` (la web ya no puede vender); su **antelación** = inicio
  del punto − el último cobro de quienes lo llenan, mediana en días.
- **Ingreso por plaza-hora ofrecida** = lo vendido de las líneas presentes (`chargedSubtotalCents()`, visitas del periodo)
  / plazas-hora ofrecidas (capacidad × minutos hasta el siguiente inicio de la zona y día, o hasta su fin). En euros.
- **Anticipación** = días entre el cobro y la visita, por línea principal: mediana y reparto (el mismo día · 1–2 · 3–7 ·
  8–30 · más de 30), por TIPO (fiesta › grupo › entrada: la regla de `PaidVisits`) y por día de la semana de la visita.
- **Demanda sin hueco** = eventos `availability_missing` del periodo, por producto y mes. **Cuándo lo emite el cajón**: al
  cargar la oferta de un producto, una vez por producto y mes, por cada mes desde el EN CURSO hasta el anterior al
  primero con días (y el en curso si no hay ninguno). Regla pura en `calendar.js` con su `node --test`.
- **La pestaña «Ocupación»** (tras «Dinero», el orden de §4.1): seis tarjetas —Ocupación de las entradas (principal),
  Fiestas por franja, Franjas llenas, Ingreso por plaza-hora, Anticipación (mediana), Demanda sin hueco—; el mapa de calor
  día × hora con el % escrito en cada celda; la anticipación por tipo; plegado: por hora, por zona, por producto, las
  fiestas por zona, la anticipación por día y la demanda por producto y mes. CSV `occupancy`. Presupuesto ≤ 20 consultas;
  `EXPLAIN` sobre un año sintético.

**Cómo se construyó (27-09 noche; ✅ vista y aprobada por el owner: «buen trabajo»)**:
- `Booking\Services\OccupancyReader`: `points()` (cada punto con su aforo, `minutes`, zona de entradas o de fiestas, sus
  topes —leídos de `PackAvailability`, no copiados—, las plazas, las fiestas, los niños y el último cobro) y `paidLines()`
  (las líneas principales cobradas con su visita, lo cobrado —`chargedSubtotalCents()`— y el tipo); `partyWindow()` es la
  copia DECLARADA de la ventana privada de `PackAvailability`. `Filament\Analytics\OccupancyReport` corta por el instante
  de la visita, separa entradas y fiestas y calcula el resto. Widgets: `OccupancyOverviewWidget` (seis tarjetas con su
  anatomía), `OccupancyHeatmapWidget` (una TABLA: el % escrito, tooltip, cinco pasos de UN tono —el azul validado de la
  T2f— por `color-mix()`, blanco solo sobre el paso lleno), `AnticipationChart` y `OccupancyBreakdownWidget` (siete tablas;
  la del día × hora es la vista de tabla del mapa). Pestaña «Ocupación» tras «Dinero»; CSV `occupancy`.
- La demanda sin hueco: `calendar.js::missingMonths()` y `sidebar/missing.js::createMissingReporter()` (una vez por
  producto y mes en la visita; `JumpWeb.track`), llamados por `usePurchaseFlow::selectProduct()` si la oferta llegó. La
  isla NO pasa por ahí (tiene su camino): buzón a plataforma.
- **Medido**: en la BD local, «este mes» 57 ms y 14 consultas; un año, 62 ms y 11. Con CARGA sintética (10.000 líneas
  cobradas en 3.666 franjas, dentro de una transacción deshecha, `probe-t2-carga.php`): `points()` 653 ms,
  `paidLines()` 134 ms, 5 consultas. `EXPLAIN` (MySQL): las franjas por fecha van por el índice único con *skip scan*
  (pocas zonas); las líneas entran por el índice de estado del pedido y llegan a la franja por clave primaria.
- **Tests**: `OccupancyReaderParityTest` (3: punto a punto contra los DOS contadores del aforo con rejilla solapada,
  ilimitada, hora extra, preparación y cancelada —y la cifra tecleada—; lo pendiente, fuera a propósito; aforo, minutos y
  topes por punto) · `OccupancyReportTest` (6: el fixture a mano) · `calendar.test.js` +3 y `missing.test.js` (4) ·
  `AnalyticsEventsTest` +1 (el evento llega entero: un mes tiene seis cifras y el filtro de teléfonos pide nueve) ·
  `AnalyticsCensusTest` (+6 tarjetas, cada una con su «¿Cómo se calcula?» en es y zh_CN) · `AnalyticsPageTest`. Arnés
  `mutar-analitica-decidir.sh`: **+16 mutaciones de la T2 (todas muerden), 71/71 + control**; su `verde()` corre también
  los dos `node --test` del cajón. Sonda del panel 41/41. ⚠️ **No verificado en el navegador**: la línea de
  `usePurchaseFlow::selectProduct()` que llama al reportero —el cajón local abre en la ISLA (`sidebar.shell = isla`,
  «no deshacer sin él»)—; la regla y el evento, sí, por sus tests.
- **Lo que enseñó**: (1) el evento de la demanda sin hueco estaba en el contrato y nadie lo emitía; (2) el selector del
  CSV pintaba «admin.analytics.export.report.surveys» en crudo desde la T4 de las encuestas (arreglado de paso); (3) el
  mapa de la BD local enseña horas sin franjas: NO es el lector —`SlotGenerator` «jamás toca fechas pasadas»—, es que la
  local no corre el cron diario; lo dice la consulta directa. (4) Una mutación vieja del arnés («¿Cómo se calcula?» en
  chino) había dejado de aplicarse desde la T1 —su ancla era la definición de la nota media, ampliada con la frase del
  anonimato— y la T1 no corrió este arnés: al tocar un texto que un arnés usa de ancla, se corre ese arnés.

### 4.8.bis Los que VUELVEN (T0c, `#756`) — ✅ vista y aprobada por el owner (27-09: «buen trabajo»)

**El owner, 27-09**: «los clientes que vuelven no los veo, o sea que se registran y vienen otro día y se escanea de nuevo su
QR, o cuántas veces vuelven cada X tiempo… hay que medir como que vienen aquellos que también se busquen por correo o
número móvil»; y de las compras: «clientes recurrentes sí, eso me sirve». Adelantada a la T1 (antes era parte de la T3/T6).

**Medido antes**: el cuadro solo tenía «Clientes con visita acreditada» (distintos del periodo, no quién repite) y
«Recurrentes» (repiten compra por CUALQUIER canal, sin comparación). Una visita solo se acreditaba al escanear el carné
(`#741`); la búsqueda por correo o móvil abría la ficha sin acreditar; y del 28-08 al 25-09 no se acreditó ninguna
(`DEUDA.md`).

**Construido**:
- **La puerta** (`#756`): `ValidarRegistro::search()` acredita al cliente encontrado por correo o móvil como el escaneo
  (idempotente por cliente y día, con el permiso de la ficha); `customer_visits.source` (migración aditiva) dice `card` o
  `lookup`, y `null` en las del botón de antes. Consecuencia declarada: la encuesta interna se ofrece también
  tras la búsqueda, y la EXTERNA del día siguiente llega a quien se buscó así (si fue una consulta, también).
- **`CustomersReport::returns()`**: UNA consulta —cada visita del periodo con la ANTERIOR del cliente (`LAG` sobre toda su
  historia en una tabla derivada; el periodo se filtra después)—: `returning` (ya había venido), `first_time`, `repeat` (dos
  o más días en el periodo), la mediana de días entre vueltas y su reparto (una semana · un mes · tres meses · más), y las
  visitas por origen. El periodo comparado trae `returning`, `first_time` y `repeat`.
- **`ReturnsWidget`** («Vuelven al parque», en Clientes, tras «La puerta»): cuatro tarjetas con la anatomía de la T0b —ya
  habían venido, vienen por primera vez, vinieron dos o más días, cada cuánto vuelven—. Cómo se acreditó cada visita
  (carné · búsqueda · de antes, sin origen) va como DETALLE de «Visitas acreditadas»: como tarjeta partía el valor en dos
  líneas (sonda del 27-09), y es un desglose de las visitas, no una cifra.
- **«Repiten por la web»** (`MoneyReport::buyerCounts()`): compradores del periodo que ya habían comprado (por cualquier
  canal) y ahora compran por la web o la app; los recuentos de compradores traen ya su periodo comparado. ⚠️ No se dice
  si antes compraban en el mostrador: los pedidos de antes de medir no guardan canal.
- Censo: 44 + 5 tarjetas; el CSV lleva las nuevas. Tests: `CustomersReportTest` +2 (vuelven, con fixture a mano; los
  bordes 7/30/90), `MoneyReportTest` +1, `AnalyticsCensusTest` +1 (el desglose por origen, asimétrico), los de la puerta
  cambiados A PROPÓSITO (`GateSurveyTest`, `ValidarRegistroProfileTest`, `GateVisitsTest`). Arnés: 55 mutaciones.
- ⚠️ **Lo que el diseño NO cubre**: quien entra sin cuenta o como invitado de una fiesta no se acredita; si hubo días sin
  acreditar (el hueco de agosto y septiembre), quien vino solo entonces cuenta como «primera vez». `[DECIDIDO owner]`
  27-09: **no se reconstruye** la historia desde el rastro de la puerta (se ofreció con `puerta.profile_viewed`); cuenta
  desde que la puerta empezó a acreditar.

### 4.8.quater La T4 al detalle — la cartera, medida el 29-09 antes de codificar (decidido por el agente, vetable al ojo)

- **Medido** (BD local, 29-09): vendido para los próximos 7 días, 14 líneas, 82 plazas y 1.352 €; para 30 y 90, 23 líneas,
  148 plazas y 2.006 € (lo más lejano, el 27-10). Los complementos: 29 líneas hijas, 25 sin franja propia (cuelgan de la visita
  de su línea principal). La antelación entre el cobro y la visita: mediana 13 días, p95 29, máximo 34 (datos de montaje). Los
  pedidos, desde el 04-07: en local no hay año anterior. `Order::STATUS_REFUNDED` ya no lo pone nadie (datos antiguos), y
  cancelar un pedido FECHA cada línea (`cancelLiveItems`, `#127`): 2 pedidos cancelados antiguos tienen líneas sin fecha.
- **Qué es**: lo ya vendido para las visitas de los próximos 7, 30 y 90 días (de hoy en adelante, en días del parque): plazas
  (las líneas principales vivas, la regla de `OccupancyReader::paidLines()`) y euros (lo cobrable de la línea y de sus
  complementos vivos). Mira hacia delante: NO depende del filtro de la página (§4.3).
- **«A estas alturas»** (la foto de un instante *s*): cuenta una línea si su pedido se cobró antes de *s* y la línea no se había
  cancelado antes de *s* (`cancelled_at` vacío o posterior); una línea de pedido cancelado SIN fecha no cuenta en ninguna foto
  (no se sabe cuándo). La foto de *ahora* coincide con `paidLines()`, y una prueba lo ata. **Límite declarado**: una línea que
  se cambió de fecha después de *s* cuenta en su fecha de hoy (el libro no guarda la fecha anterior).
- **La referencia**: la foto de hace un año con **−364 días** (el mismo día de la semana: una ventana de 7 días lleva los mismos
  sábados); si no vale, la **media de las fotos de hace 1, 2, 3 y 4 semanas** al mismo horizonte (§4.8), diciendo cuántas
  valieron. Una foto VALE si los pedidos se miden desde antes de *s* menos la antelación p95 del último año (mínimo 7 días): un
  sistema recién puesto aún no ve lo que se vendió antes de existir. Sin ninguna, «aún sin con qué comparar».
- **Normal para ti** (`#790`): las fotos de las últimas 12 semanas al mismo horizonte (las que valen; hacen falta 8), su
  mín–máx, con la frase de siempre. El cambio frente a la referencia va en la línea de detalle y **sin color**: las fotos
  semanales se solapan (una ventana de 30 días comparte 23 con la de la semana anterior) y la prueba de la T0b no vale ahí.
- **Dónde**: en «Resumen», la octava cifra (`booked.cents_30`, «Vendido para los próximos 30 días»: euros, y las plazas en su
  detalle); en «Ocupación», bajo las tarjetas, un gráfico por semana (esta y las 12 siguientes, en plazas: «vendido ya» frente
  a «a estas alturas») con su tabla plegada (plazas y euros), y plegadas las seis cifras (plazas y euros a 7, 30 y 90 días).
  Al CSV de «Ocupación» y al censo; a «lo que ha cambiado» y al texto para IA como las demás.
- **Código**: el lector, en `Booking` (`OccupancyReader::bookedLines()`, con los complementos y las fechas de cancelación);
  el informe, `Filament\Analytics\BookedReport` (futuro; las fotos, en PHP sobre una sola lectura, 5 min de caché); las cifras,
  `BookedMetrics` (futuro; con su historia de fotos, no la de `MetricSet`).
- **Verificación**: pruebas con fechas fijas («a estas alturas» con una línea cobrada tarde, otra cancelada después y otra
  antes, un pedido cancelado sin fecha, los complementos, la foto válida o no, −364 frente a las 4 semanas, la historia), la
  paridad con `paidLines()`, su arnés `SOLO=T4`, la sonda y el ojo.

**Cómo se construyó la T4 (29-09; ✅ vista y aprobada por el owner el 29-09: «procede, visto bueno ok»)**:
- **Piezas**: `OccupancyReader::bookedLines()` (las líneas con sus complementos —que cuelgan de la franja de su principal y caen
  con ella— y sus fechas de cobro y de cancelación) y `leadDays()`; `Filament\Analytics\BookedReport` (dos lecturas —estas
  semanas y, si vale, la de hace un año— y las fotos en PHP; 5 min de caché; 55 ms en local); `BookedMetrics` (seis cifras con
  su historia de fotos y la comparación en el detalle); `BookedChart` (13 semanas, «Vendido ya» y «A estas alturas») y
  `BookedMoreWidget` (plegado) en «Ocupación»; la tabla por semana en su grupo plegado y las seis en el resumen del CSV;
  `booked.cents_30` en «Resumen»; la cartera en «lo que ha cambiado» y en el texto para IA.
- **Frente al plan**: (1) la cifra de «Resumen» vive PLEGADA en su pestaña —«Ocupación» ya lleva seis arriba (§4.1, §4.11)—;
  `AnalyticsTabsTest` lo declara como su única excepción, y el texto para IA lleva lo de «Resumen» aunque esté plegado. (2) El
  gráfico es 1:1 y con todos sus rótulos: con el 2:1 de siempre medía ~200 px y Chart.js saltaba la mitad de las semanas.
- **Medido en local** (datos de montaje): margen 30 días (el p95 de la antelación); sin año anterior, la referencia es la media
  de 4 semanas y la historia tiene 8 fotos: «Vendido para los próximos 30 días: 2.224 €. Normal para ti: entre 2.155 € y 3.704 €
  en tus últimas 8 semanas. 148 plazas · a estas alturas, de media en las 4 semanas anteriores: 2.355 € (−6 %)».
- **Pruebas**: `BookedReportTest` (11: la foto con su borde al segundo, el lector —complementos, el cancelado sin fecha, el
  pendiente—, la paridad con `paidLines()`, −364 frente a 365 y la media de 4 semanas, el margen del p95, la foto de antes de
  medir, las semanas con los dos cambios de hora, el gráfico, «lo que ha cambiado» y el detalle), `ExplainerTest` +1 aserción,
  el censo (+6 tarjetas y 7 rótulos del CSV), `AnalyticsTabsTest` y `AnalyticsPageTest` al día. Arnés `SOLO=T4`: **16/16** y
  los controles en verde. Sonda `storage/app/audit/sonda-t4-panel.mjs` 17/17, cifra a cifra contra el informe, a 1280 y a 390.
- **Lo que enseñó**: (1) un `round()` «por el cambio de hora» sobrevivió al arnés: Carbon cuenta días de CALENDARIO en la zona
  del parque —de medianoche a medianoche sale 6,0 también cuando la noche dura 23 horas (medido)—; era código muerto y se
  quitó, y la prueba de primavera se queda vigilando el resultado. (2) Filament no deja un `Chart` global: los datos del
  gráfico se leen de su `x-data` (`chart({ cachedData, … })`). (3) Tres expectativas de la prueba estaban mal y el código
  bien (una suma, el punto de «oct.» y el redondeo de 2.354,50 €): el instrumento, primero.

### 4.9 Marketing con coste y los correos por cliente (T5)

▶▶ **Rehecha el 28-09 (owner)**: el **gasto en anuncios, aplazado** («no lo haremos ahora mismo»); los **correos por cliente
van PRIMERO y con spec propia**, `correos-salientes.md` (⬜ borrador): ver lo que recibe cada cliente, su vista previa y si
lo abrió y pulsó, y DESPUÉS, en conjunto, a «Marketing». Lo de abajo es el plan de antes y queda como referencia.
▶▶ **29-09: hecho allí**, C1→C4 (`#794`→`#797`): «Marketing» gana dos grupos plegados antes de «Calidad del dato», «Los
correos» (por correo) y «Cuándo abren y pulsan» (día × hora con sus sumas, solo con los correos que al cliente le llegan).
Detalle: `correos-salientes.md` §4.14–§4.15.
✗ **`[DECIDIDO owner]` 29-09 (`#800`): el marketing con coste NO se hace** («marketing con costes como te dije no lo haremos»):
ni gasto tecleado, ni coste por venta, ni retorno por euro, ni sus tarjetas en «Marketing» (§4.1). Lo de abajo queda como
referencia de lo que se descartó.

- **Gasto tecleado**: `ad_spend` (futuro): plataforma, campaña (el `utm_campaign` de sus anuncios), mes, céntimos, nota.
  Un formulario en «Marketing → Gasto en anuncios», con permiso propio (`analytics.manage` (futuro), admin por defecto) y
  rastro. **Coste por venta** = gasto / pedidos atribuidos (último toque); **retorno por euro** = ingresos atribuidos /
  gasto; la regla de atribución se dice en «¿Cómo se calcula?». Condición: los anuncios llevan UTM (la plantilla de
  `INSTALACION-CLIENTE.md` §3.bis).
- **Valor del cliente por canal de llegada**: desde el SELLO del primer pedido cobrado de cada cliente (régimen del
  contrato, al 100 %), su valor a 90 y 365 días.
- **Correos por cliente**: `email_sends` (futuro): clave aleatoria, `user_id`, clase del correo, `sent_at`, `first_opened_at`,
  `opens`, `first_clicked_at`, `clicks`. La clave viaja en cada enlace (`jw_e`, pegada con los UTM por `EmailUtm`, tras
  firmar e ignorada al validar) y en un píxel (futuro) solo si la cuenta tenía el consentimiento de análisis al mandarlo.
  - **Clics por cliente para todos**, con aviso en `/privacidad` y el interruptor «Análisis» de Privacidad
    (`analytics_opt_out`) para oponerse: con él, el correo sale sin marca y el clic se cuenta sin persona.
  - **Aperturas por cliente** solo con ese consentimiento, rotuladas **«aproximadas»**: Apple Mail abre las imágenes por
    su cuenta, Gmail las guarda la primera vez, Outlook las bloquea. El dato fiable es el clic.
  - En «Marketing», por clase de correo: enviados, % con clic, % abiertos (aprox., entre quienes consintieron), compras en
    los 7 días tras el clic. En la 360: los últimos correos del cliente con enviado · abierto · clic.
  - `RGPD-01`: `anonymize()` suelta `user_id`, el export lo lleva, poda a 24 meses. `[PENDIENTE: asesoría]` los clics por
    persona con interés legítimo y el píxel con consentimiento; `/privacidad` y `/cookies` son del carril web (buzón).

### 4.10 Cohortes, pérdidas, complementos y satisfacción (T6–T8)

- **Cohortes** (T6): clientes por mes de su primera compra cobrada; qué parte vuelve a comprar a 30, 90 y 365 días; la
  mediana hasta la segunda compra. Tabla de calor de las últimas 12 cohortes, con el % escrito.
- **Pérdidas y complementos** (T7): cancelaciones, reembolsos por motivo (`payment_refunds.reason`), cambios de fecha, la
  tasa de cobros rechazados por el banco y el valor de las cestas caducadas; qué parte de los pedidos lleva cada
  complemento y cuánto suma por pedido; las promociones (uso y coste del descuento) junto con plataforma (`promociones.md`).
- **Satisfacción** (T8): las encuestas ANÓNIMAS de `#754` (nota media, notas bajas y si volvieron, tasa de respuesta; los
  mínimos de 5) y la nota de Google en el tiempo, que exige empezar a guardar una foto diaria de la nota y el recuento.

### 4.11 La pantalla: reglas de UX y UI

- **Cabecera**: el título y la pregunta de la pestaña; nada técnico.
- **Filtros**: una fila, periodo y comparación; en móvil, una píldora «Este mes · frente a agosto ▾» que abre los dos.
- **Pestañas**: las de Filament en escritorio y tablet; **en móvil, un selector nativo** (hoy se cortan: 3 de 5).
- **Tarjetas**: ≤ 6 por pestaña (8 en Resumen); la principal, a doble ancho.
- **Gráficos**: una pregunta por gráfico; los tres colores validados de la T2f y el gris de «sin dato»; el veredicto
  en verde, ámbar o rojo con icono y palabra, texto ≥ 4,5:1; toda serie con su tabla plegada (ya es la regla).
- **Vacíos**: dicen por qué («la medición empezó el …», «aún sin historia», «menos de 5»).
- **Botones**: «Descargar CSV» al pie de cada pestaña (sus tablas); «Exportar segmento» solo en Clientes.
- **Carga**: solo los widgets de la pestaña activa (medido: hoy cargan las cinco); la pestaña sigue en la URL.
- **Tamaños de referencia**: tablet 1080×810 (el aparato del panel), móvil 390×844 y escritorio 1440×900.
- **Glosario** (guarda: `AnalyticsJargonTest` sobre los rótulos, T3a): «Sesiones» → «Visitas a la web» · «Primer
  toque» → «Cómo llegaron la primera vez» · «Último toque» → «Qué les hizo comprar» · «Anterior a la medición» → «Sin dato
  (antes de medir)» · «Sistema» → «Automático» (y a «Calidad del dato») · «Fuera del recuento», «bots», «internas» → solo
  en «Calidad del dato» · «Embudo» → «Del paso a paso a la compra».
- **El ojo**: cada tanda se enseña en vivo en `localhost:8081`, en tablet, móvil y escritorio, ANTES del commit (regla 7
  del owner); si el owner repite que algo se ve mal, se le enseñan opciones renderizadas.

### 4.12 Las tandas (el orden que aprobó el owner, con la T0 delante)

| | Tanda | Entrega | Verificación |
|---|---|---|---|
| T0 | **El rigor del tiempo y la anatomía** — **T0a ✅** (el tiempo, `0dc5d317`; §4.3) · **T0b ✅** (la anatomía, `6243e8d9`; §4.2) · **T0c ✅** (los que vuelven, `#756`; §4.8.bis) — las tres vistas y aprobadas por el owner | §4.3 (tramo transcurrido, mismo día de la semana) · `Metric` (futuro) con polaridad, base mínima, puntos, Wilson · las 44 tarjetas de hoy pasadas por el componente, sin datos nuevos · el censo de §4.1.bis y su guarda | test del tramo (el 27 contra el 27), de la polaridad y de la base pequeña, con su mutación · sonda |
| T1 | **Encuestas anónimas** — ✅ (27-09, `#757`; vista y aprobada por el owner: «está perfecto, visto bueno») | `encuestas.md` §4.7 (`#754`; lo construido, su «Cómo se construyó») | las de esa spec |
| T2 | **Ocupación y anticipación** — ✅ (27-09, `#758`; aprobada por el owner: «buen trabajo») | la pestaña, §4.8 y §4.8.ter | tests de la regla con aforo y líneas vivas · `EXPLAIN` con un año sintético · sonda · ojo |
| T3 | **Resumen y la reorganización** — en cinco tandas, T3a→T3e (§4.13, `#759`) | §4.1, §4.4–§4.7, §4.11: las siete pestañas, los objetivos, las referencias (su historia; el sector con fuentes que se traen al owner), las frases, «lo que ha cambiado», el texto para IA y su guarda, la carga por pestaña, el glosario | guardas de IA y jerga · sonda (primera cifra en la primera pantalla, peticiones al abrir) · ojo |
| TP | **El público** (`#792`; tras la T3c·2 y antes de la T3d) | §4.14: TP·1 la captura · TP·2 las cifras de conjunto · TP·3 los padres por la edad de sus hijos | tests de la fecha (futura, < 18, borrada al anonimizar), de las celdas < 5 y del opt-in al exportar, con su mutación · sonda · ojo |
| T4 | **La cartera** — ✅ (29-09, aprobada por el owner) | §4.8 y §4.8.quater | test «a estas alturas» con fechas fijas · ojo |
| T5 | ~~**Marketing con coste** y correos por cliente~~ — ✗ el coste NO se hace (`[DECIDIDO owner]` 29-09, `#800`); los correos por cliente, hechos en `correos-salientes.md` (C1→C4) | §4.9 | — |
| T6 | **Cohortes** | §4.10 | test de cohorte con fechas fijas · `EXPLAIN` · ojo |
| T7 | **Pérdidas y complementos** | §4.10 (promociones con plataforma) | tests · ojo |
| T8 | **Satisfacción** | §4.10 (la foto diaria de Google) | tests · ojo |

### 4.13 La T3 al detalle — medido el 28-09, antes de codificar (`#759`)

**Medido** (BD local, sonda de solo lectura `probe-t3-antes`): al abrir piden **10 widgets**, los de las seis pestañas
(`#736`); a 390×844 se ven **3 de 6** pestañas y la primera cifra cae a **732 px** (el filtro creció con sus fechas escritas,
T0a); a 1080×810, a 540. Jerga en pantalla: «Conversión», seis rótulos (sesiones, primer toque, fuera del recuento, bots,
internas, embudo); «Fiestas», uno (embudo). De los cinco gráficos de «Conversión», el de dispositivos tiene su tabla y **el de
las horas no** (su dato no está en ninguna tabla ni en el CSV). Filament trae `Tabs::livewireProperty()`: con él una pestaña
inactiva se pinta vacía y sus widgets ni existen ni piden.

**En cinco tandas** (decidido por el agente contra el objetivo, vetable; `#759`), cada una al ojo del owner antes del commit:

| | Entrega |
|---|---|
| T3a | **La forma**: las siete pestañas con su pregunta, la carga por pestaña, el reparto de §4.1.bis, «Resumen» con sus cifras (sin veredicto aún), móvil, los botones al pie, el glosario y su guarda |
| T3b | **El veredicto y la frase**: «normal para ti» (la historia, §4.4) y la frase de plantilla (§4.6) en cada tarjeta |
| T3c | **«Resumen» que decide**: «lo que ha cambiado» (§4.5) y los objetivos del mes |
| T3d | **«Explícamelo con IA»** (§4.7) y su guarda |
| T3e | **El sector**: los rangos con fuente, investigados y traídos al owner ANTES de sembrar nada |

**La T3a**:
- **Un catálogo de cifras por informe** (`Filament\Analytics\Metrics\*`): la composición de cada `Metric` sale de los widgets a
  un sitio —una clave, una cifra— que leen las pestañas, «Resumen» y, después, «lo que ha cambiado» y el texto para IA. Un
  widget ELIGE claves; ninguna cifra se define dos veces.
- **Las pestañas** (§4.1): Resumen · Dinero · Ocupación · Clientes · Marketing (era «Conversión») · Fiestas · Satisfacción (era
  «Encuestas»); en la URL `summary`, `money`, `occupancy`, `customers`, `marketing`, `parties`, `satisfaction` (`traffic` y
  `surveys` siguen abriendo la suya). La cabecera es la pregunta; la frase técnica baja al «¿Cómo se calcula?» de «Ingresos netos».
- **Arriba (≤ 6) y plegado** (§4.1.bis), cada grupo plegado con su título:
  - **Dinero**: Ingresos netos (la principal, a doble ancho) · Vendido · **Pendiente de cobrar en el parque** (nueva: la fila
    de «La señal») · Devuelto · Pedidos cobrados · Valor medio del pedido. Plegado: Cobrado y Gestiones posteriores.
  - **Clientes**: **Visitantes** (nueva: las plazas de las visitas PAGADAS del periodo, por el instante de la visita; la misma
    fuente que la ocupación) · Nuevos · Repiten · Ya habían venido · Valor de vida · Registros. Plegado: «Más de los clientes»
    (Compradores, Valor medio por cliente, Repiten por la web, Vienen por primera vez, Vinieron dos o más días, Cada cuánto
    vuelven), «Cómo se registran» y «La puerta». «Vuelven a los 90 días» (§4.1) es de las cohortes (T6): hasta entonces, «Ya
    habían venido» (T0c).
  - **Marketing**: Visitas a la web · Conversión (las compras y lo cobrado, en su detalle); las de la T5 llegarán aquí. Plegado:
    Compras por la web o la app, Cobrado en esas compras, los experimentos y las tablas; al final **«Calidad del dato»**: visitas
    identificadas, fuera del recuento, eventos rechazados y qué son «Sin dato (antes de medir)» y «Automático». Gráficos: el paso
    a paso, las fuentes y la serie; las horas y los dispositivos, a tabla plegada (la de las horas, nueva).
  - **Fiestas**: las seis de §4.1.bis; plegadas, las otras tres. **Satisfacción**: Nota media · **Tasa de respuesta** (nueva: las
    respuestas entre lo ofrecido en la puerta y los correos mandados); plegado, las cinco de antes. «Notas bajas y si volvieron» sigue.
  - **Resumen**: Ingresos netos · Ocupación de las entradas · Visitantes · Conversión · Valor medio del pedido · Repiten · Nota
    media: la MISMA cifra que en su pestaña (clave y definición), con su enlace. La cartera llega con la T4.
- **Carga por pestaña**: `livewireProperty('tab')` y la pestaña en la URL (`?pestana=`). **Móvil** (< md): un `<select>` nativo en
  lugar de la fila de pestañas, y el filtro tras una píldora con el periodo y la comparación.
- **Botones**: «Descargar CSV» al pie de cada pestaña, de su informe y sin modal; «Exportar segmento», al pie de Clientes.
- **Glosario** (§4.11) con su guarda `AnalyticsJargonTest`: pinta cada widget de cada pestaña y falla si la jerga sale
  fuera de «Calidad del dato». **Censo**: las tres nuevas, al CSV y al censo; lo renombrado, con su correspondencia en el test.
- **Verificación**: la carga por pestaña (con X abierta, ningún widget de otra), el catálogo (cada clave una vez; «Resumen»
  reusa), la jerga y el censo, con su mutación; la sonda con siete pestañas, la primera cifra a 390 y 1080 y las peticiones al
  abrir; el ojo del owner en tablet, móvil y escritorio.

**Cómo se construyó la T3a (28-09; ✅ vista en vivo y aprobada por el owner el 28-09: «buen trabajo, todo correcto»)**:
- **El catálogo**: `Filament\Analytics\Metrics\{Money,Occupancy,Customers,Marketing,Parties,Surveys}Metrics` sobre `MetricSet`:
  `for()` lee el informe cacheado y `from()` compone (un test le da la forma REAL de un informe con los números a mano). 58
  cifras, una clave cada una. `MetricsWidget` declara `KEYS`, `PRINCIPAL` (doble ancho desde tableta), `FOLDED` y `MAX_TOP`:
  los widgets de tarjetas ELIGEN; `SummaryWidget` reusa las de arriba de su pestaña con «Ver en …».
- **Widgets**: nuevos `SummaryWidget`, `CustomersOverviewWidget` y los plegados `MoneyMore`, `CustomersMore`, `TrafficMore`,
  `PartiesMore`, `SurveysMore` y `DataQualityWidget` (tarjetas + los rechazados); `RegistrationsWidget` y `GateWidget`, plegados;
  fuera `MoneyCustomersWidget`, `ReturnsWidget` y los gráficos de horas y dispositivos (a tabla en `PagesWidget`). Las notas
  técnicas de las cabeceras bajan a la descripción de su grupo plegado.
- **Cifras nuevas**: «Pendiente de cobrar en el parque» (neutra, sin comparación: el informe no la trae), «Visitantes»
  (`Metric::units()`: las plazas llegan en LOTES —una fiesta, veinte— y se prueban como una suma, con las reservas de base) y
  «Tasa de respuesta».
- **La página**: `Tabs::livewireProperty('tab')` con `#[Url(as: 'pestana')]`; `normalizeTab()` (las claves viejas y lo que
  mande el navegador); `REPORTS`, el CSV de cada pestaña al pie; «Exportar segmento» al pie de Clientes; la píldora y el
  selector (`filament.pages.analytics.*`). Las preguntas no prometen lo que aún no hay: «¿Cómo vamos?» (el «qué hago» llega
  con la T3c) y «¿Qué trae visitas y ventas?» (el coste, con la T5).
- **Glosario**: «Compradores nuevos/recurrentes» (en Clientes perdían el título que les daba sentido), «Visitas a la web»
  (junto a «Visitantes»), «Visitas identificadas», «Automático», «Sin dato (antes de medir)», «Cómo llegaron la primera vez»,
  «Qué les hizo comprar», «Del paso a paso a la compra», las encuestas «En la puerta» y «Por correo»; el CSV, con el nombre de
  su pestaña.
- **Medido** (BD local): al abrir, **10 → 1** petición (solo «Resumen»); a 390, de 3 de 6 pestañas a la vista → el selector, y
  la primera cifra de **732 → 416 px**; a 1080, 540 → 492; jerga en pantalla, 7 → 0; al cambiar de pestaña, los widgets de la
  anterior viajan como `$commit` vacío y el servidor devuelve 0 B de su HTML (no los pinta). Sonda del panel **107/107**
  (escritorio, tableta y móvil), consola limpia.
- **Tests**: `AnalyticsTabsTest` (6), `AnalyticsJargonTest` (3: las claves de idioma y lo pintado; «iniciar sesión» no es
  jerga), `MetricsCatalogTest` (5), `MetricTest` +1, `OccupancyReportTest` +3, y cambiados A PROPÓSITO `AnalyticsCensusTest`
  (58; lo renombrado, con su correspondencia), `AnalyticsPageTest`, `AnalyticsExportTest` y `SegmentsExportTest`. Arnés
  `mutar-analitica-decidir.sh`: +21 mutaciones de la T3a, **91/92 + control** y la 92.ª («lo plegado nace abierto») vista
  morder aparte tras arreglar su prueba; copia por RUTA (dos `admin.php` chocaban por su nombre). Suite 6373.
- **Lo que enseñó**: (1) `CENSUS + CENSUS_SINCE` es una UNIÓN de arrays: con la clave `money` en los dos, la cifra nueva del
  dinero no se habría comprobado nunca. (2) El desglose de la ocupación llamaba al ayudante de los días, mudado al catálogo,
  SOLO con reservas: la suite estaba verde y Larastan lo vio; ahora una prueba lo pinta con datos. (3) En Filament el `id` de
  un grupo va por `->id()`: por `extraAttributes` pierde contra el suyo vacío. (4) El instrumento contaba las peticiones de
  «Hoy» (12 en vez de 10): se espera a que termine. (5) La pestaña por defecto no se escribe en la URL. (6) Un grupo que
  RECUERDA su estado lleva `fi-collapsed` desde el servidor aunque nazca abierto: lo que decide es `isCollapsed:
  $persist(true)` de Alpine; la prueba miraba la clase y el arnés la dejó en evidencia. (7) Al medir la T3b (28-09): la
  caché de la OCUPACIÓN (T2) llevaba el corte al segundo en su clave —los otros cinco informes, por fechas—, así que no
  servía entre los widgets de una pestaña y cada uno recalculaba el informe; ahora va por fechas (`OccupancyReportTest`,
  con su mutación).

**La T3b al detalle — medido el 28-09, antes de codificar (`#790`)**:
- **Medido**: la BD local tiene pedidos de julio a septiembre (3 meses) y sesiones desde el 26-07: «este mes» dirá «aún sin
  historia»; por semanas y por día de la semana hay 9. La historia por el informe entero (12 periodos con sus desgloses y sus
  comparados) cuesta de 0,2 a 1,1 s y hasta 638 consultas por informe; por sus TOTALES (`totalsOnly()`, lo que ya alimenta la
  comparación) es ~10 veces menos (dinero: 49 ms y 36 consultas). Simulado: un periodo NORMAL cae fuera de la banda P25–P75 de
  12 periodos el **57 %** de las veces (P10–P90, 32 %; mín–máx, 15 %; con 8 periodos, 60 · 36 · 22 %).
- **`[DECIDIDO owner]` 28-09 (`#790`)**: «normal para ti» es el rango **mín–máx de los últimos 12 periodos comparables**;
  fuera de él, «la más alta / la más baja de tus últimos 12 …». Sustituye a la P25–P75 de §4.4.
- **La historia**: los 12 periodos anteriores encadenando `previous()` (la misma unidad y el mismo tramo: día y semana, el mismo
  día de la semana), cada uno por los TOTALES de su informe, y leída con el MISMO catálogo (el valor «comparado» de cada cifra):
  la banda sale de la misma fórmula que la tarjeta. Un periodo cuenta solo si empieza cuando su fuente YA medía (pedidos,
  sesiones, visitas de la puerta, cuentas, encuestas): un cero de antes de medir no es un dato. Hacen falta 8; si no, «aún sin
  historia (N de 8)». Una cifra sin comparación (las medias de siempre, los textos) no lleva veredicto.
- **El veredicto** (en `Metric`): normal · alta · baja · sin historia · pocos datos (una tasa con menos de 20 casos); el tono
  (bien · atención · neutro) sale de la POLARIDAD. **La frase** (§4.6) es la línea del veredicto, con icono, palabra y la
  banda: «Normal para ti: entre 1.200 € y 1.900 € en tus últimos 12 meses». La del cambio ya estaba en la tarjeta (T0b): no se
  repite. La unidad se dice: meses, semanas, «tus últimos 12 lunes».
- **Caché**: por fechas y la hora del corte redondeada a la caducidad: 5 min en día y semana (una hora de más en un día
  sesgaría) y 1 h en mes, trimestre, año y tramo (una hora en un mes es ~0,1 %).

**Cómo se construyó la T3b (28-09; ✅ vista en vivo y aprobada por el owner el 28-09: «buen trabajo, visto bueno»)**:
- `XReport::baselineTotals()` (los seis): una envoltura pública de su `totalsOnly()`, sin copiar su aritmética. `MetricSet`:
  `for()` = `from()` + `judged()`; `history()` encadena 12 `previous()`, pone los totales de cada periodo donde van los del
  comparado (`withTotals()`; el dinero cambia además `customers.previous`) y lee con `from()` el valor «comparado» de cada
  cifra. `MeasuredSince`: el primer registro de cada fuente (pedidos, sesiones, puerta, cuentas, encuestas, demanda sin
  hueco), una hora en caché; cada catálogo dice su fuente (`sources()`).
- `Metric`: `history`, `historyUnit`, `withHistory()`, `verdict()` (mín–máx, `#790`), `verdictLine()` y `format()`. La
  tarjeta pinta la frase bajo la cifra con icono, palabra y color de refuerzo (`data-metric-verdict`/`-tone`), y la regla de la
  banda dentro de «¿Cómo se calcula?».
- **Medido** (BD local): pintar las tarjetas de arriba de una pestaña con su historia, en frío, 130–790 ms (hasta 633
  consultas, «Resumen» con cuatro informes); en caliente, ≤ 12 ms. Con «La semana pasada», veredictos reales (la conversión:
  «Atención: la más baja de tus últimas 8 semanas (iba de 6,3 % a 13,3 %)»); con «Este mes», «aún sin historia (1 de 8
  meses)»: en local solo hay tres meses.
- **Tests**: `MetricTest` +5 (la banda con sus bordes, el tono por polaridad, los 8 periodos, pocos casos, la historia plana),
  `MetricsHistoryTest` (5, fixture a mano: la historia desde que se mide, la PARIDAD —el periodo más reciente de la historia es
  el comparado de la tarjeta—, la fuente que aún no mide, el mismo día de la semana y la media sin casos, la tarjeta con su
  color). Arnés: +13.
- **Lo que enseñó (el navegador, no la suite)**: (1) un lunes a primera hora, «esta semana» y sus 12 anteriores valían 0: la
  frase decía «entre 0,00 € y 0,00 €» → una historia PLANA dice «siempre 0,00 €». (2) «Valor medio del pedido» de una semana
  SIN pedidos entraba en la historia como 0 €: una media o una tasa sin casos no existe y no entra; y una media con menos de 20
  casos no se juzga, como una tasa. (3) «tus últimos 12 semanas»: el adjetivo va con la unidad. (4) La clave de la fuente
  de las visitas se llamaba `'sessions'` y `AccessRevocationTest` (nadie nombra la tabla de credenciales fuera de su punto
  único) la paró en la suite completa: ahora es `web_visits`.

**La T3c al detalle — medido el 28-09, antes de codificar (`#791`)**: en dos, cada una al ojo del owner: **T3c·1 «lo que ha
cambiado»** (sin esquema nuevo) y **T3c·2 los objetivos del mes** (tabla, permiso y formulario).
- **Medido** (BD local, los seis catálogos de la T3b): leerlos todos cuesta ~1–1,3 s en frío (~900 consultas) y ≤ 15 ms en
  caliente. Con «La semana pasada» salen de su rango 5 cifras; tres son la misma historia (la web vendió menos: compras,
  conversión y lo cobrado) y varias son números diminutos («siempre había sido 1» → 0 visitas por primera vez un domingo).
- **La regla** (§4.5; del agente contra el objetivo, vetable, `#791`): entran TODAS las cifras de los seis catálogos, también
  las plegadas —su valor es sacar lo que no está arriba— cuyo veredicto es alta o baja (que ya exige 8 periodos y, en tasas y
  medias, 20 casos) **y** cuya escala llega a 20: `max(base, base comparada) ≥ 20` (la base de un recuento es él mismo; la del
  dinero, sus operaciones). Se ordenan por **dinero en juego** —los euros fuera de su banda— y después las que no son dinero,
  por lo lejos que quedan de su banda (relativo a ella). Como mucho 5, y si hay más, «y N más» (nunca un tope callado).
- **Cada frase**: el icono y el tono del veredicto, «Conversión: 5,4 %. Atención: la más baja de tus últimas 8 semanas (iba de
  6,3 % a 13,3 %).» y el enlace a su pestaña. **Sin ninguna**: «Nada fuera de lo normal» (y cuántas se miraron); sin historia
  en ninguna, «aún sin historia para decir qué ha cambiado».
- **Dónde**: `ChangesWidget` en «Resumen», bajo sus cifras; la selección, pura, en `Filament\Analytics\Changes` (la leerán
  también los objetivos y el texto para IA).

**Cómo se construyó la T3c·1 (28-09; ✅ vista en vivo y aprobada por el owner el 28-09: «buen trabajo, visto bueno»)**: `Changes::select()` (puro) y `Changes::for()` (los seis
catálogos con su historia); `AnalyticsPage::tabOf()` (dónde vive una cifra, sin contar «Resumen») para el enlace; `ChangesWidget`
con su vista (icono, palabra y color del veredicto, como la tarjeta; «y N más»; y por qué no hay nada: «Nada fuera de lo
normal: las N cifras con historia…» o «Aún sin historia… hacen falta 8 meses con datos»). **Medido**: con «La semana pasada»
salen las dos que predijo la medida —«Conversión: 5,4 %. Atención: la más baja…» y «Cuentas nuevas: 33. Bien: la más alta…»—
y ningún número diminuto; a 390 px sin desbordar; sonda 108/108. **Tests**: `ChangesTest` (5: la entrada con escala —también
la de una bajada, por el periodo de antes—, el orden —dinero delante; lo demás relativo, con un caso en que lo absoluto
mentiría—, el tope con «y N más», los dos vacíos y la lista con su enlace), `AnalyticsTabsTest` +1 (`tabOf`). Arnés: +9,
**114/115 + control y la 115.ª vista morder aparte**. **Lo que enseñó**: (1) `TestCase::count()` y `Assert::countOf()` son de
PHPUnit y finales —un ayudante con ese nombre tumba el fichero con un error fatal que un filtro por «⨯» no enseña: el
veredicto, por el código de salida—. (2) El arnés dejó viva «el dinero deja de ir delante»: en la prueba los céntimos ya
pesaban más que cualquier distancia relativa. Faltaba el caso que las separa —un céntimo fuera frente a 1,5 veces el borde—.

**La T3c·2 al detalle (diseño, 28-09)** — los objetivos del mes (§4.4), decidido contra el objetivo y vetable:
- **Dato**: `analytics_goals` (futuro) — `metric_key`, `month` (su primer día), `target` (en la unidad de la cifra: céntimos,
  unidades o puntos básicos), `set_by` (`nullOnDelete`), fechas; única por cifra y mes. Modelo `Platform\Models\AnalyticsGoal`
  (futuro) con su alias de morfo; sin datos personales.
- **Qué cifras**: las que ACUMULAN en el mes —Ingresos netos, Vendido, Pedidos cobrados, Visitantes, Fiestas, Cuentas nuevas— y
  dos tasas de NIVEL —Ocupación de las entradas y Conversión—. Las demás no tienen «objetivo del mes» que tenga sentido.
- **Qué dice la tarjeta** (solo con «Este mes» o «El mes pasado», que son el mes del objetivo): acumulada y en curso, «Objetivo:
  20.000 € · vas al 82 % con el 90 % del mes pasado» (bien si el avance llega al ritmo; atención si no); acumulada y cerrada,
  «alcanzado (104 %)» o «no alcanzado (82 %)»; una tasa, «Objetivo: 8,0 % · por encima / por debajo».
- **Dónde se ponen**: «Objetivos del mes», al pie de «Resumen»: un formulario con el mes (este o el siguiente) y un campo por
  cifra. Permiso propio `analytics.manage` (futuro; de gestión, el admin por `Gate::before`), re-exigido al guardar (`SEC-04`), y
  rastro `analytics.goals_updated` (antes y después, sin PII).
- **Compartido (aviso antes, por buzón)**: `PermissionSeeder`/`PermissionCatalog`, `AuditLog::ACTIONS`, el morfo de
  `AppServiceProvider` y el recuento de migraciones de `docs/README.md` y `MODELO-DATOS.md`.

**Cómo se construyó la T3c·2 (28-09; ✅ vista en vivo y aprobada por el owner el 28-09: «buen trabajo, tienes el visto bueno»)**:
- **Dato**: `analytics_goals` y `Platform\Models\AnalyticsGoal` (alias `analytics_goal`), con su único lector y escritor
  `Platform\Services\Analytics\AnalyticsGoals`: `forMonth()` (caché de una hora por mes) y `save()` (un número pone o cambia, `null`
  quita, lo que no viene no se toca; bajo `lockForUpdate`; sin cambios no escribe ni deja rastro; olvida la caché del mes;
  rastro `analytics.goals_updated` con el mes y el antes y el después de las claves que cambiaron).
- **La regla**, pura, en `Filament\Analytics\Goal`: las ocho de `KEYS` con su unidad (`UNITS`); `applies()` solo con la unidad
  `month` que empieza el día 1 («Este mes», «El mes pasado»; un tramo a medida del 1 al 30 no); lo que se acumula, por el RITMO
  —lo que llevas contra la parte del mes que ha pasado, lineal— y, cerrado, alcanzado o no (llegar justo cuenta); una tasa, por
  encima o por debajo; **los tres primeros días (`MIN_ELAPSED` = 10 % del mes) el ritmo no se juzga** (del agente, vetable: un
  día de ventas no dice nada). `Metric::withGoal()` la lleva junto a su historia; `MetricSet::for()` la cuelga (`withGoals()`),
  así sale en su pestaña y en «Resumen»; la tarjeta pinta la línea con bandera, palabra y tono (`data-metric-goal`).
- **El formulario** (`Filament\Analytics\GoalsForm`, acción `goals` al pie de «Resumen»): el mes (este o el siguiente) y las ocho
  en la unidad del operador (euros, personas, %), cada una con **«El mes pasado: …»** del mismo catálogo; en blanco o cero, sin
  objetivo; una tasa, como mucho el 100 %. Tras guardar, las tarjetas se repintan sin recargar (evento `analytics-goals-saved`).
- **Medido**: `SEC-04` lo cumple el propio `visible()` del botón, porque Filament lo vuelve a evaluar al enviar
  (`callMountedAction` → `isDisabled()` → `isHidden()`), y un mes forjado lo para la validación del `Select` contra sus opciones:
  las dos comprobaciones que había escrito DENTRO de la acción no se alcanzaban nunca y se quitaron; las vigilan dos pruebas.
  Sonda del panel **111/111** (+3: el formulario con sus nueve campos y sus pistas, guardar y las dos tarjetas con su tono, el
  formulario a 390 px), consola limpia; con 3.000 € y 8 % de prueba el 28-09: «Por detrás del ritmo: llevas el 50 % y ha pasado
  el 92 % del mes» y «Por debajo».
- **Tests**: `GoalTest` (7, las ventanas a mano: el día 16 de junio a las 00:00 es la mitad exacta), `AnalyticsGoalsTest` (10: el
  guardado y su rastro, la caché, lo que se rechaza, las unidades, los meses, el botón solo al pie de «Resumen», la tarjeta en su
  pestaña y en «Resumen», fuera de un mes, el permiso, el permiso retirado con el formulario abierto, el mes forjado, el oyente)
  y `MetricsCatalogTest` +1 (las ocho existen en su unidad y suben cuando van bien). Arnés: +33, **33/33 + control** con el modo
  nuevo `SOLO=T3c2` (5 min 25 s; el entero, 148 a ~38 s cada una, ~95 min, se paró en 55/55 porque el owner no podía esperar:
  desde el 28-09 el entero se corre al cerrar un bloque y cada tanda, con `SOLO=`).
- **Lo que enseñó**: (1) `Livewire::withQueryParams()` se QUEDA para la siguiente `test()` del mismo caso: la segunda página se
  abría en la pestaña de la primera. (2) En una prueba de Livewire cualquier ida y vuelta repinta, así que el oyente del evento
  no se ve fallar por lo pintado: se comprueba que está, y el navegador (la sonda) que repinta.

**La T3d al detalle — medido el 29-09, antes de codificar** (§4.7; decidido por el agente contra el objetivo, vetable al ojo):
- **Medido** (BD local, con `medir-t3d.php` en la carpeta de auditoría de `storage`, fuera de git; solo lectura): las **58** cifras de los seis catálogos con todo lo que
  dice su tarjeta ocupan **15,5–16,8 KB** («La semana pasada», «Este mes», «Últimos 90 días»; es y zh_CN). Sin su «¿Cómo se
  calcula?», 7,6–8,9 KB. El techo de §2 es 8 KB: con las 58 no cabe. Las de ARRIBA de cada pestaña son **28** (6 · 6 · 6 · 2
  · 6 · 2); con la tarjeta entera y la primera frase de su definición, 7,0 KB en es y 8,2 KB en zh_CN, sin instrucciones.
  Leer los seis catálogos, 0,2–1 s en frío (ya los lee «lo que ha cambiado»).
- **Qué cifras**: las 28 de arriba —las que el propio cuadro dice que deciden (§0: «lo que no ayuda a decidir se pliega»)— y
  las plegadas que estén en «lo que ha cambiado», que mira todas. Las demás, contadas en una línea («N cifras más en el panel,
  plegadas y dentro de lo normal»): nunca un tope callado.
- **La forma** (Markdown): las instrucciones de §4.7 · el negocio sin su nombre (lo que vende, desde los tipos de producto
  activos; la moneda) · el periodo y su comparación (`WindowLabel`) · «lo que ha cambiado» (`Changes`, con sus frases) · una
  tabla por pestaña, compacta: cifra · valor · antes · cambio (con «claro», «puede ser azar» o «pocos datos») · normal para
  ti (el rango y cuántos periodos, o «aún sin historia», o «pocos casos») · qué es cada cifra (su «¿Cómo se calcula?»: una sola
  definición, sin copia que se desvíe) · la calidad del dato (visitas identificadas y fuera del recuento, eventos rechazados,
  cuántas sin historia o con pocos casos, y que aún no hay referencia del sector: T3e). La referencia y su fuente: «normal»
  es el mín–máx de la historia del PROPIO negocio (`#790`), y el texto lo dice.
- **La guarda** (`#793`, `RGPD-07`): (1) se construye SOLO desde `Metric` y `Changes`: ni filas, ni tablas de desglose, ni el
  texto de una pregunta de encuesta; (2) antes de enseñarlo, si algo casa con `Contract::PII_VALUE_RE` (correo o teléfono), NO
  se enseña —se avisa y se anota en el log, sin el contenido—: falla cerrada, como la ingesta; (3) una prueba con nombres,
  correos, teléfonos, textos libres de encuesta y una encuesta de 2 respuestas comprueba que nada de eso sale, que el tamaño
  queda por debajo de 8 KB en es y zh_CN, y que un texto normal no dispara la guarda (las fechas se escriben como en el
  panel: una fecha ISO seguida de otra casaba como teléfono).
- **Dónde y quién**: «Explícamelo con IA» al pie de «Resumen», junto a los objetivos: un modal con el texto de solo lectura,
  su tamaño y «Copiar». Permiso **`reports.export`**, el del CSV: es el mismo acto —sacar agregados del panel— y no hace falta
  un permiso nuevo en el catálogo compartido. Rastro `analytics.explained` al prepararlo (periodo, comparación, idioma, bytes
  y cifras; sin PII), como el CSV. En el idioma del panel (es y zh_CN), y la IA contesta en él.
- **Código**: `Filament\Analytics\Explainer` (capa de entrega, como `Changes`), con la composición pura separada de la
  lectura; `Metric` gana la lectura compacta del cambio sin duplicar su prueba; la proporción de tiempo entre las dos
  ventanas pasa de la traza de los widgets a `Comparison::share()`, para que el texto y la tarjeta juzguen igual.
- **Fuera**: mandarlo a la API de una IA desde el panel (§4.7, «después, si el owner quiere»: proveedor, clave y coste son suyos).

**Cómo se construyó la T3d (29-09; ✅ vista y aprobada por el owner el 29-09: «buen trabajo, visto bueno ok»)**:
- **El techo, `[DECIDIDO owner]` 29-09 (`#798`): 12 KB**. Lo medido al construir: con las 28 y todo lo de arriba, lo normal
  ocupa **7,7–8,0 KB** en es y 7,2 en zh_CN, y el peor caso —rangos de siete cifras y cinco plegadas fuera de lo normal, cada
  una con su fila y su definición— **10,2 KB** en es y 9,4 en zh_CN. Con 8 KB no cabía; el owner eligió subirlo.
- **Lo que cambió frente al plan de arriba, y por qué**: (1) sin columna «antes»: el cambio con su signo ya la lleva, y era
  ~0,3 KB; (2) de las plegadas fuera de lo normal entran en las tablas las cinco que nombra «lo que ha cambiado», y las demás
  se cuentan («y N más»): así el tamaño tiene techo aunque se salgan muchas; (3) «lo que ha cambiado» no copia las frases del
  panel —hablan al operador («tus últimas 8 semanas») y el texto habla a la IA en primera persona—, sino la misma lectura
  corta de la tabla; (4) la unidad de la historia va una vez, en la nota («en sus semanas anteriores (entre paréntesis,
  cuántos)»), y cada fila dice cuántos periodos; (5) la definición es la PRIMERA frase de «¿Cómo se calcula?»: lo que sigue
  habla de la tarjeta («Debajo, …»); (6) los descartes de navegación solo se dicen si hay.
- **Visto al leer el texto**: «Nota media | menos de 5» se leía como la NOTA (menor que 5). La tarjeta, el resumen del CSV y
  el texto dicen ahora «Sin nota» (la tarjeta ya dice debajo cuántas respuestas; el CSV lo dice entero), y la primera frase
  de su «¿Cómo se calcula?» lleva la regla. Los recuentos de 1 a 4 siguen diciendo «menos de 5».
- **Piezas**: `Explainer::for()` (lee) · `compose()` (pura) · `guarded()` (falla cerrada y anota en el log el número de casos,
  nunca el contenido); `AnalyticsPage::topKeys()`, `explainAction()` —al pie de «Resumen», antes de los objetivos— y la vista
  `filament.pages.analytics.explain` (el texto EN EL HTML, «Copiar» con Alpine y la vuelta de `execCommand`); `Metric::shift()`
  (lo que `reading()` usaba por dentro); `Comparison::share()`; `Changes::unitOf()`; la acción de auditoría
  `analytics.explained`; `admin.analytics.explain.*` en es y zh_CN.
- **Pruebas**: `ExplainerTest` (6): lo que dice y en qué idioma · el cambio juzgado con la duración de cada ventana (julio
  contra junio: 61 frente a 41 es claro con mitad y mitad y puede ser azar con la real) · nada de una persona en es y zh_CN
  (nombres, correos, teléfonos, un texto libre y una encuesta de 2 respuestas) y el pack inactivo fuera · la guarda (un
  correo, un teléfono y dos fechas ISO seguidas, rechazados y anotados; importes, porcentajes y fechas del panel, no) y el
  modal de un texto rechazado · el peor caso contra 12 KB en los dos idiomas · el botón (solo con `reports.export`) y su
  rastro sin PII. Arnés `SOLO=T3d`: **11/11 muerden** y los controles quedan en verde; la mutación de la proporción, mudada a
  `Comparison.php`, vista morder aparte.
- **Sonda** (`storage/app/audit/sonda-t3d-panel.mjs`, 22/22): el botón al pie de «Resumen», el modal con el texto, que es el
  MISMO que da el informe (7.860 bytes con «La semana pasada»), «Copiar» lo lleva entero al portapapeles (se lee de vuelta) y
  dice «Copiado», sin desbordar a 1280 ni a 390, consola limpia.
- **Lo que enseñó**: (1) en Livewire 4 los modales de Filament son un `wire:partial`: su contenido no está en el HTML de una
  prueba tras `mountAction()`; se mira en `getModalContent()`, como hace `WaiverProofActionTest`. (2) La expresión de
  teléfonos cuenta cifras con separadores: dos fechas ISO con un guion entre ellas son un «teléfono»; el texto usa las fechas
  del panel. (3) Una cifra tapada que dice «menos de 5» junto a una escala del 1 al 5 se lee como el valor: el instrumento
  (leer el texto entero antes de medirlo) lo vio antes que nadie.

**T3e, primera búsqueda de fuentes (28-09; NADA sembrado, para el owner)**: casi todo lo publicado son MEDIAS de un
informe, no rangos, y pocas veces de parques de salto. Candidatas, con su pega: (a) ROLLER, *2025 Attractions Industry
Benchmark Report* (datos de sus operadores; el blog no da la muestra): en parques de salto, el 31 % de las reservas es
online y el 66 % en taquilla; 3,4 personas por reserva online, 2,1 en taquilla y 10,1 en fiestas y grupos; las fiestas se
reservan con 2–4 semanas. (b) Revinate, *2026 Hospitality Benchmark Report* (HOTELES): la encuesta por correo se completa
en menos del 5 % (3,64 % Norteamérica · 4,41 % Asia-Pacífico). (c) Contentsquare, *Digital Experience Benchmark 2026*
(6.500 webs; «Viajes y hostelería», 539): la conversión por sector está en el informe descargable, no en abierto.
Descartado: los «60–70 % de ocupación en punta» y los «2,5–3 % de conversión» de blogs sin método.

**T3e, la búsqueda completa (29-09; NADA sembrado, para el owner)**. Solo las TASAS admiten referencia del sector (conversión,
repetición, antelación, ocupación, tasa de respuesta, nota media); los recuentos y el dinero dependen del tamaño de cada
negocio. Lo que hay publicado:
- **ROLLER, *2026 Attractions Industry Benchmark Report*** ([resumen](https://www.roller.software/blog/2026-attractions-industry-benchmark-report-key-stats-roller),
  [fiestas](https://www.roller.software/blog/party-insights-2026-benchmark-report)): «más de 3.500 locales» de su software, sin
  método ni periodo publicados; todo MEDIAS del sector entero, nada por parques de salto: nota media de sus encuestas **4,29 / 5**
  (3,95 el año anterior); vuelven el **40,8 %** de los clientes de los locales con fiestas y el **25,6 %** de los que no las tienen;
  el **33 %** de las reservas es online y trae el 45 % de los ingresos. La edición de 2025 da el **31 %** online en parques de salto
  y «la mayoría de las fiestas se reservan con **2–4 semanas**» (sin decir cuántas).
- **Contentsquare, *Digital Experience Benchmark 2026*** ([guía](https://contentsquare.com/guides/travel-hospitality-digital-experience/conversions/)):
  6.000 webs, del T4 2024 al T4 2025; en abierto solo los cambios interanuales («Entertainment & Restaurants», +2,3 %); la
  tasa absoluta, en el informe que se descarga dejando un correo. Un buscador citó 4,5 % en escritorio y 2,1 % en móvil para
  viajes y hostelería: **no verificado** (la página no lo dice).
- **Encuestas por correo, en HOTELES**: GuestRevu 2025 ([nota](https://www.hospitalitynet.org/news/4130210.html); 1.245
  campañas): **~20 %** de respuesta de media, del 17 al 23 % según el asunto; Revinate: completadas **< 5 %** (3,64–4,41 % por
  región). Otro sector y otra definición.
- **IAAPA, *2025 Benchmark Series – Entertainment Centers*** ([ficha](https://iaapa.org/research/2025-iaapa-benchmark-series-entertainment-centers)):
  datos de 2024, mundial con Europa; la única que podría traer RANGOS de centros de ocio (no verificado: la ficha no lo dice).
  **De pago**: 499 $ sin ser socio. Y usar sus cifras dentro del producto exige mirar su licencia.
- **Descartado**: Arival (turistas que reservan experiencias de viaje, no familias del barrio); los 14 $ por persona de una
  nota de prensa antigua; las horquillas de ocupación de blogs sin método.
- **Conclusión**: no hay en abierto ni un RANGO con método para las cifras de un parque de salto. Las medias de ROLLER son las
  más cercanas, sin método publicado. Sembrarlas contradice §4.4 («solo rangos con fuente»): se lleva al owner.
- ⏸ **`[DECIDIDO owner]` 29-09 (`#799`): no se siembra nada** y la T3e queda en espera hasta que aparezca una fuente con rangos y
  método; el cuadro sigue con la historia propia. Descartados: comprar IAAPA, las medias de ROLLER, una tabla vacía editable.

### 4.14 El público (TP) — `#792`, medido el 28-09

`[DECIDIDO owner]` 28-09 («conocer mejor al público… este tipo de analítica ayuda a crear marketing especializado»): la
pregunta de «Clientes», «¿quién viene?», con su edad, sus hijos y con quién viene; y listas de padres por la edad de sus hijos.

**Lo que ya hay** (medido en el código y en la BD local):

| Dato | Dónde | Hoy |
|---|---|---|
| Los hijos del titular: fecha y parentesco (padre, madre, tutor, abuelo, otro) | `dependents.born_on`, `relationship` | solo de quien los declara (al asignar una entrada o firmar el descargo); local: 6 menores de 2 cuentas |
| Una entrada asignada a un menor | `dependent_assignments` | local: 1 |
| Un producto solo para menores | tramo de edad con tope < 18 (`TicketType::onlyGuestsUnder`, `#825`) | del catálogo |
| El titular salta | su propio descargo (`waiver_signatures` sin sujeto) | |
| Los invitados: edad, parentesco y contacto del adulto | `guardian_authorizations.minor_born_on`, `guardian_relationship`; la edad por invitado en `order_items.guest_data`; `party_invitations.honoree_age` | local: 45 autorizaciones, 26 invitaciones |
| **La edad del titular** | — | **no existe**: `users.born_on` (futuro) |

- **Google no la da**: pedimos `openid email profile` (`GoogleOAuth::SCOPES`); la fecha exige `user.birthday.read`, un permiso
  SENSIBLE, con verificación de la app por Google y una pantalla más para el cliente. Descartado (`#792`).
- **«¿Vino solo o con sus hijos?»** no se guarda: se DEDUCE del pedido (con menores si lleva una entrada asignada a un menor o un
  producto solo para menores); si no se puede saber, «sin dato», nunca se adivina.
- **El hueco** (lo señaló el owner): ningún alta declara la mayoría de edad y nadie la comprueba. Queda abierto (`#792`); una
  fecha que diga menos de 18 años se rechaza.

**En tres tandas**, TP·1 y TP·2 tras la T3c·2 y antes de la T3d: la captura tiene PLAZO —el dato solo se acumula desde que se
pide y nada sale antes de la v2.0.0— y la T3d y la T3e no. Cada una, con su «al detalle» medido antes de codificar y al ojo:
- **TP·1 La captura**: `users.born_on` (futuro), fecha entera y opcional, en las cinco altas —el registro del cajón y de la API
  (`SelfSignup`), el paso tras Google (`GoogleSignup`), la puerta (`ValidarRegistro`), el pedido manual (`CreateManualOrderPage`)
  y la isla (plataforma, por buzón)— y en Mi cuenta; se rechaza si es futura o dice < 18 (`Dependent::ADULT_AGE`). Contrato
  menor (`User.born_on`). `RGPD-01`: `anonymize()` la borra y el export la lleva. La ficha del panel la enseña.
- **TP·2 Las cifras de conjunto**, en «Clientes», grupo plegado «Quién viene»: la edad de los titulares por tramos; las familias
  por número de hijos declarados; la edad de los hijos el día de la visita; quién los declara; con quién vienen (con menores ·
  sin dato); y de las fiestas, la edad del cumpleañero y la de los invitados. Sin persona, **ninguna celda < 5** (se agrupa) y
  siempre «de N con dato». Al catálogo, al censo y al CSV.
- ~~**TP·3 Los padres por la edad de sus hijos**, exportables con opt-in~~ → **rehecha por `#793`** (`[DECIDIDO owner]` 28-09,
  abajo): el público es ANÓNIMO y nada exporta personas; las fechas sirven para felicitar, sin vender.

**La TP·3 rehecha (`#793`, `[DECIDIDO owner]` 28-09)** — «no necesito sus datos, solo saber el público que es»: los segmentos
son para definir el público de Google, Meta y TikTok Ads y pulir el tono y el copy (madres, padres, con hijos o sin, jóvenes);
y «felicitar al padre en su cumpleaños si tenemos su fecha… para el del niño lo mismo, sin vender, un detalle; hay que trabajar
el copy al máximo». Tres tandas, cada una con su «al detalle» medido y **sin código hasta que el owner lo vea** (lo pidió):
- **TP·3a Los tramos de las plataformas** (vetable, mío): «Quién viene» (TP·2) reparte con los cortes que piden los anuncios,
  para copiarlos tal cual: quien reserva 18–24 · 25–34 · 35–44 · 45–54 · 55–64 · 65+ (Google y Meta); los hijos 0–2 · 3–5 ·
  6–8 · 9–12 · 13–17 (los «padres de…» de Meta); y dos cifras: con hijos declarados (el «estado parental») y madres frente a
  padres (el parentesco es lo único que dice el sexo: no se pregunta). Anónimo, con los mínimos de `RGPD-07`.
- **TP·3b Retirar «Exportar segmento»**: la acción del pie de «Clientes», su ruta y `SegmentsExportController`; los segmentos
  quedan como recuentos. A medir en su «al detalle»: el permiso `analytics.export` (si no tiene otro uso, sale del catálogo y
  del seeder: compartido, aviso a plataforma), la columna «con opt-in» (sin exportación, ¿dice algo?) y las pruebas que la vigilan.
- **TP·3c Las felicitaciones** — ⏸ **con el rediseño de la plantilla de correos** (owner, 28-09: «los correos los haremos
  cuando hagamos el rediseño de la plantilla de correos»). Su spec antes que el código: al titular el día de su
  cumpleaños (`users.born_on`) y al adulto el del cumpleaños de su hijo menor (`dependents.born_on`, `Dependent::active()`),
  **solo con `marketing_opt_in`**, una por persona y año, por la mañana del parque, nunca a una cuenta anonimizada, la baja en
  un toque (LSSI art. 22.1) y un interruptor por felicitación en el panel. **Sin precios, sin botón de reservar, sin
  descuento**: el éxito no se mide en conversión. El copy se escribe CON el owner (es/en/fr) antes de construir: `[PENDIENTE:
  owner]`. Se apoya en lo que ya hay de `#750` (el aviso a invitados: su comando horario, su «una vez por cumpleaños» y su baja),
  que sigue igual. `/privacidad` lo nombra (`textos-legales.md` §4.2.1, punto 15, «⟨si felicitaciones⟩»).

**La TP·3a y la TP·3b al detalle — medido el 29-09, antes de codificar** (✅ el owner lo vio: «Bien. Continúa», 29-09; lo
construido, al final de la lista):
- **Medido, lo que hay** («Quién viene», TP·2, `AudienceReport`): quien reserva en 18–24 · 25–34 · 35–44 · 45–54 · 55+; los
  hijos de tres en tres (0–2 … 15–17); cuántos hijos (1 · 2 · 3+); **quién los declara** (padre, madre, tutor, abuelo, otro:
  «madres frente a padres» YA está, `Dependent::RELATIONSHIPS`); con quién viene; las fiestas. Todo con los mínimos de `RGPD-07`.
- **Medido, lo que piden los anuncios** (fuentes oficiales, 29-09):
  - [Google Ads](https://support.google.com/google-ads/answer/2580383?hl=en): 18-24 · 25-34 · 35-44 · 45-54 · 55-64 · 65+; y
    el «estado parental», con Parent, Not a parent y Unknown.
  - [TikTok](https://ads.tiktok.com/help/article/age-and-gender-targeting?lang=en): 18-24 · 25-34 · 35-44 · 45-54 · 55+.
  - Meta: un rango libre de 13 a 65+. Sus categorías «padres de…» por la edad del hijo NO las he podido verificar hoy
    (Meta retiró opciones detalladas entre 2022 y 2024): se proponen por etapas y se comprueban en el Administrador de anuncios.
- ⚠️ **El producto NO sabe «no es padre»**: no declarar hijos no es no tenerlos. El estado parental solo puede decir «con hijos
  declarados» o «sin dato», nunca «no es padre».
- **TP·3a, propuesta (vetable)**:
  - Quien reserva, con los cortes de Google: 18–24 · 25–34 · 35–44 · 45–54 · 55–64 · 65+ (TikTok junta los dos últimos).
  - Los hijos, por etapas: 0–2 · 3–5 · 6–8 · 9–12 · 13–17.
  - Una fila «Para los anuncios» que dice el estado parental como Google (con hijos declarados / sin dato) y el pie de las
    fuentes. Es un cambio de cortes en `AudienceReport` y sus rótulos, con sus pruebas.
- **TP·3b, medido**: la exportación vive en la acción `exportSegment` del pie de «Clientes» (`AnalyticsPage`, con
  `PERMISSION_SEGMENTS_EXPORT`), la ruta `/admin/analitica/segmentos/csv` con `SegmentsExportController`, la acción del rastro
  `segments.exported`, el permiso `analytics.export` (catálogo y seeder, COMPARTIDOS con plataforma), sus textos y
  `SegmentsExportTest`. Hay precedente para retirar un permiso ya sembrado: una migración borra su fila y el pivote cae en
  cascada (`drop_contact_messages_table`).
- **TP·3b, propuesta**:
  - Retirar la acción, la ruta, el controlador, el permiso (con su migración y aviso a plataforma) y la prueba.
  - `segments.exported` se QUEDA en `AuditLog::ACTIONS`, para que el rastro viejo se siga leyendo.
  - La columna «con opt-in» se queda: dice a cuántos se les podría escribir (las felicitaciones de la TP·3c), en conjunto.
  - ⚠️ **Visto al medir**: `SegmentsWidget` enseña recuentos de 1 a 4 sin tapar. Con `#793` (el público es anónimo) se
    propone taparlos como en «Quién viene».
- ✅ **Lo construido (29-09; el owner lo vio y lo aprobó: «Bien. Buen trabajo», 29-09)**:
  - **TP·3a**: `AudienceReport::ADULT_BRACKETS` con los cortes de Google (55–64 y 65+) y `CHILD_BRACKETS` por etapas (9–12 ·
    13–17). La tabla «Para los anuncios» cierra «Quién viene» (`AudienceWidget::forAds`), sobre las personas que vienen: «Con
    hijos declarados» y «Sin dato». Con menos de 5 personas no tiene filas, y un grupo de 1 a 4 se escribe «menos de 5», sin su %.
    Entra en el CSV de «Clientes» por `tablesFor()`, y en es y zh_CN.
  - **TP·3b**: fuera la acción `exportSegment`, `PERMISSION_SEGMENTS_EXPORT`, la ruta, `SegmentsExportController`,
    `SegmentsExportTest`, sus textos y **`SegmentsReport::members()`** (devolvía nombre, correo y teléfono; su única llamada era
    el controlador). El permiso `analytics.export` sale del catálogo y del seeder, y la migración
    `drop_analytics_export_permission` borra su fila (el pivote cae en cascada). `SegmentsWidget` escribe 1–4 como «menos de 5»
    en las dos columnas. `segments.exported` se queda en `AuditLog::ACTIONS`.
  - **Pruebas**: `AudienceReportTest` +2 (los cortes y el reparto; el enmascarado y la tabla vacía con menos de cinco) y
    `SegmentsReportTest` +2 (el widget enmascara y no nombra a nadie; nada exporta personas: el catálogo, el seeder, el 404 y la
    migración con su pivote); sin las aserciones de `members()`. Arnés `SOLO=TP3`: **12/12 muerden** y los controles quedan en
    verde. La sonda versionada `sonda-segmentos.mjs` se reescribe para la retirada.
  - **Sonda del ojo** (`storage/app/audit/sonda-tp3-panel.mjs`, 20/20): con `ojo-tp3.php` montado y «Últimos 90 días», los seis
    tramos, las cinco etapas y la tabla «Para los anuncios» coinciden cifra a cifra con el informe (9·9·8·8·7·8 · 11·11·12·22·20
    · 40·18). Además: ninguna cifra de 1 a 4, ni un correo, sin botón y con el 404, sin desplazamiento lateral a 1280 y a 390,
    y el CSV con la tabla nueva.
  - **Lo que enseñó**: con pocos datos, los tramos pequeños se funden con el vecino (`RGPD-07`). Con los 34 de «Este mes» en
    local se leía «18–34 · 35 o más» y parecía que los cortes nuevos no estaban: el instrumento, no el producto. Para ver los
    tramos hacen falta cinco por tramo.

**La TP·1 al detalle — medido el 28-09, antes de codificar.** Tres correcciones al plan de arriba:
- **Las altas son cuatro puertas, no cinco**: `ValidarRegistro` (la puerta) NO crea cuentas (medido: los únicos `User::create`
  del producto son `SelfSignup`, `GoogleSignup` y `CustomerRegistrar`). El alta de mostrador es el modal «Dar de alta» de
  `CreateManualOrderPage`. Las cuatro: el alta con correo (`POST /auth/register`: el cajón, suelta y en la compra, y la isla), el
  paso tras Google (`POST /auth/google/complete`: el cajón y la isla), el mostrador, y Mi cuenta (`PATCH /me`: el cajón y la isla).
- ⚠️⚠️ **En `PATCH /me` la fecha AUSENTE no cambia nada** (`null` la borra): la isla guarda Mi cuenta con `{name, phone, locale,
  email}` (`isla/cuenta/useAjustesCuenta.js:128`) y, con la regla contraria, cada guardado de la isla la borraría. Con su caso.
- **Sin descargo interno, la pantalla tras Google no se pinta** (Q6, `GoogleSignupController::waiverRequired()`): esa alta no
  puede pedir la fecha y queda Mi cuenta. Local: modo interno, 82 clientes, ninguno con fecha (la columna no existe).

**Lo que se construye** (vetable lo técnico): `users.born_on` (DATE, nula; `immutable_date`, como `dependents.born_on`) · UNA regla,
`Identity\Rules\AdultBirthDate`, para las cuatro puertas: `Y-m-d`, no futura, **≥ 18 años el día del parque** (`DisplayTime::
today()` y `Dependent::ageBetween()`, fecha con fecha: el día del 18.º cumpleaños ya vale) y **≤ 120 años** (una errata como
`0198` envenenaría las cifras de la TP·2); tres avisos distintos · el alta y la pantalla de Google la aceptan opcional, el mostrador
con un `DatePicker` opcional, Mi cuenta con `sometimes` · el control del cajón, **el `<input type="date">` que ya pide la fecha de
un hijo** (`DependentsZone.vue`): uno solo en el producto · `RGPD-01`: `anonymize()` la pone a `null` y entra en `SCRUBBED` del censo;
el export la lleva en `profile` · la ficha del panel, «Fecha de nacimiento» con la edad · contrato **1.49.0** (`User`,
`RegisterRequest`, `GoogleSignupRequest`, `ProfileUpdateRequest`, `ExportedProfile`) · la isla, por buzón: el motor ya la reenvía
si su formulario la trae (`runRegister`, `runGoogleSignup`, `profile.apply` solo si la clave viene).
- **Fuera de la TP·1**: la puerta (la edad en su ficha, si se quiere, va aparte) y cualquier cifra (TP·2).

**TP·1, lo construido (28-09, ✅ vista y aprobada por el owner en vivo: «perfecto, continuamos»; la pantalla tras Google, solo
por tests —en local no hay Google—; el formulario de la isla, de plataforma por buzón)**: lo de arriba entero, más la edad de hoy en la ficha
(`User::age()`, la cuenta de un hijo) y la pieza del cajón `steps/BornOnField.vue` (rótulo, `type="date"`, `autocomplete="bday"` y
la pista «Opcional. Para conocer mejor a nuestro público.», atada con `aria-describedby`; **desde el 29-09, «Tu cumpleaños» con
solo «Opcional», como la isla** —el owner quitó la frase; en fr «Ton anniversaire», el tú del cajón—, ✅ en vivo: «perfecto»; y las
dos altas de la API nombran el campo en sus avisos con `validation.attributes.born_on`, no con el rótulo). Pruebas: `Api\V1\HolderBirthDateTest` (10:
las tres puertas de la API, el 18.º cumpleaños a las 00:30 de Madrid, «ausente no cambia», el export), `Admin\Users\
HolderBirthDatePanelTest` (4: el mostrador y sus dos vías sin formulario, la ficha), el censo de `RGPD-01` y los `node --test` del
alta, Google y «Tus datos»; arnés `SOLO=TP1`. **Lo que enseñó**: (1) `registerCustomerFromData()` es PÚBLICA en Livewire y
`$pendingNoEmailCustomer` la reescribe el navegador: la fecha se re-valida en `performRegistration()`, por donde pasan las dos vías;
(2) Larastan no entiende `immutable_date` (la toma por `string`): `@property` a mano en `User`, y la línea base ENCOGE cuatro
entradas del mostrador que eran el mismo hueco de tipos; (3) el motor ya pesaba 298,83 kB con techo 299: la TP·1 lo sube a 301
con su medida, tras sacar `profileBody` de `account/profile.js` (lo metía entero en la descarga, +0,77); (4) los textos del
montaje con sesión, 10.738 → 10.836 B, techo a 10.900 tras acortar la pista; (5) la sonda del panel: el selector de Filament
abre en HOY y no se teclea, y una fecha de nacimiento se DICTA: en el mostrador va el `type="date"` NATIVO (el del cajón).

**La TP·2 al detalle — medido el 28-09, antes de codificar.** Local: 1 titular con fecha, 6 menores de 2 cuentas (3 sin
parentesco), 1 entrada asignada, 23 de 26 invitaciones con la edad de quien cumple, 127 reservas pagadas con visita en 90 días de
63 titulares. El molde ya existe: un widget de TABLAS plegado (`tables.blade.php`, `tablesFor()`) que el CSV de «Clientes» reutiliza,
y `SurveysReport::MIN_CELL` (5). Lo que se construye (lo técnico, mío y vetable):
- **Quién viene = los titulares con una reserva pagada cuyo DÍA DE VISITA cae en el periodo** (`paidScheduledPrincipal` +
  `slotDateBetween`, la base de «Ocupación»), cada persona UNA vez y con la edad de su primer día de visita del periodo.
- **Seis tablas en «Quién viene»** (`AudienceReport` + `AudienceWidget`, plegado tras «Más de los clientes»), cada una con «de N
  con dato»: (1) la edad de quien reserva: 18–24 · 25–34 · 35–44 · 45–54 · 55 o más; (2) cuántos hijos ha declarado: 1 · 2 · 3 o
  más, y aparte «sin menores declarados» (no declarar no es no tener); (3) la edad de esos hijos el día de la visita, de 3 en 3
  (0–2 … 15–17; los que ya cumplieron 18 no cuentan); (4) quién los declara, por titular: madre · padre · tutor · abuelo/a · otro ·
  varios · sin dato; (5) con quién vienen, por RESERVA: con menores (una entrada asignada a un menor o un producto solo para menores,
  `onlyGuestsUnder(18)`) · sin dato; (6) de las fiestas cuyo día cae en el periodo (la regla de `PartiesReport`): la edad de quien
  cumple (`honoree_age`) y la de los invitados (la edad de cada ficha, con la clave del pack; sin la fila de quien cumple), por año.
- **Ninguna celda de 1 a 4**: en las ordenadas se funde con la vecina («25–44»), en las de categoría va a «Otros»; con menos de 5
  con dato, la tabla no se reparte («Menos de 5 con dato»). Sin nombres ni ids: solo recuentos. ≤ 20 consultas, caché 5 min.
- **Al CSV y al censo** (`AnalyticsCensusTest`, tecleado a mano); el «¿cómo se calcula?» de cada tabla va en su nota. Las tarjetas
  no cambian (el catálogo de cifras es de números sueltos; esto son repartos).

**TP·2, lo construido (28-09, ✅ vista y aprobada por el owner: «buen trabajo, continúa con rigor»)**: `AudienceReport` + `AudienceWidget` (plegado, tras «Más
de los clientes»), al CSV de «Clientes» y al censo. Cambios al escribirlo: (1) los hijos que cuentan son los **MENORES el día de la
visita** en las tres tablas de hijos (uno declarado que ya cumplió 18 no es un niño que viene); (2) el título de cada tabla es FIJO
(el censo lo busca exacto en el CSV) y la cobertura va de primera fila, «Con dato · 12 de 63»; (3) «Con quién viene» son dos filas
que suman todas las reservas, con «menos de 5» en vez de un recuento de 1 a 4. Pruebas: `AudienceReportTest` (8: fundir y su
propiedad en tramos y categorías, la población con su paridad contra `paidScheduledPrincipal`, el primer día, los menores de ese
día, con quién viene, las fiestas, ≤ 12 consultas fijas, la pantalla). Arnés `SOLO=TP2`. **Lo que enseñó**: dos supervivientes en
«Otros» (el bucle que lo junta hasta cinco COMPENSABA las dos mutaciones con los casos que tenía): faltaban el caso de una pequeña
sola y el de «otro» ≥ 5 con otras dos que ya suman cinco. Fixture del ojo, `ojo-tp2.php` (carpeta de auditoría de `storage`, fuera de git; reversible) y
sonda `sonda-tp2-panel.mjs` (la pantalla contra el informe, ninguna cifra de 1 a 4, el CSV).

## 5. Impacto en invariantes

`RGPD-01` (purga y export de `email_sends`, de las tablas de `#754` y de `users.born_on` (futuro), TP·1) · `RGPD-04` (`no-store` en el píxel y en el texto
para IA) · `RGPD-07` (el libro sigue sin persona en el régimen exento; los correos por persona van en su tabla, no en
el libro) · `SEC-04` (re-autorizar al guardar objetivos, referencias y gasto) · `SUITE-01`. Dinero y aforo: **solo
lectura**; ningún fichero del `CRITICAL_RE` (a confirmar con `grep` en cada tanda).

## 6. Plan de verificación empírica

- **Tests por tanda** (§4.12) y guardas nuevas, cada una vista fallar con su mutación en `scripts/mutar-analitica-decidir.sh`
  (futuro), con un CONTROL: el tramo transcurrido, la polaridad, la base pequeña, el texto para IA sin PII, la jerga, la
  marca de correo en un enlace firmado, el píxel sin consentimiento, la carga por pestaña.
- **Presupuesto**: cada informe ≤ 20 consultas (`DB::listen`), caché 5 min; `EXPLAIN` sobre un fixture de un año antes de
  la T2 y la T6, y otra vez en staging con volumen (lo pendiente de `analitica.md`).
- **Sonda**: `sonda-analitica-panel.mjs` ampliada: siete pestañas, primera cifra en la primera pantalla a 390×844 y
  1080×810, peticiones al abrir, ningún rótulo de la jerga, los CSV.
- **El ojo del owner en vivo** por tanda (tablet, móvil, escritorio).

## 7. Revisión y decisión

- **27-09, owner** (tras la revisión del agente, §1): «quiero mejorarlas y ampliarlas… una vista panorámica, rigurosa y
  clave para la toma de decisiones del operador»; «medir los correos, cuántas veces se leen y los clics, por cliente»;
  «el operador no entiende tantos números: tal vez un prompt para IA con todos los datos… y una referencia del sector,
  tipo "x es normal, bajo o alto"»; «hay que organizar bien las analíticas, si no será un lío, también UX y UI». Aprobó
  el orden (encuestas → ocupación → resumen → cartera → marketing → cohortes → pérdidas → satisfacción) y el gasto de
  anuncios tecleado a mano. → `#755`.
- Decidido por el agente contra esos objetivos (vetable): la T0 del rigor delante; los clics por cliente para todos con
  oposición y las aperturas solo con consentimiento; el texto para IA copiado (sin proveedor) antes que integrado; las
  referencias del sector solo con fuente.
- **27-09, owner — APROBADA**: «la apruebo, pero no quitaremos contenido, ¿no?, ¿o lo resumiremos?; primero analítica,
  sí» → se resume y no se quita (§4.1.bis, con guarda), y la analítica va antes que los correos y la puerta (`#789`).
- **28-09, owner** («conocer mejor al público: sabemos que tienen hijos, su edad y cuántos… la fecha de nacimiento en el
  registro… la lista de invitados tiene mucha información… marketing especializado»; «tú valoras cuándo») → la tanda TP
  (§4.14): fecha ENTERA y OPCIONAL, el opt-in de hoy para el marketing por la edad de los hijos, el hueco de los menores que
  se registran sin cerrar (`#792`, contra mis tres recomendaciones: la casilla propia, el año, la casilla «soy mayor de edad»).
- **28-09, owner** (ante la exportación de la TP·3: «la idea es todo anónimo para las analíticas… no quiero exportar datos de
  los menores ni de los clientes… solo analítica. No escribas código»; «los segmentos a mí solo me hacen falta para ir a Google
  Ads, Meta Ads o TikTok Ads y definir mi público objetivo… pulir mi tono, mi copy»; «felicitar al padre en su cumpleaños…
  para el cumpleaños del niño lo mismo, sin vender, un detalle») → `#793`: nada exporta personas y la TP·3 se rehace (§4.14).
- **Pendiente**: `[PENDIENTE: asesoría]` los correos por persona y el píxel; `[PENDIENTE: owner]` el copy de las dos
  felicitaciones (TP·3c), escrito con él antes de construirlas.
