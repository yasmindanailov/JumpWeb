# [SPEC] La analítica para decidir — un cuadro que se entiende, dice si va bien o mal y cubre las decisiones del operador

> Estado: ✅ **aprobada por el owner el 27-09** (§7; `#755`) → 🟦 **T0 en curso** · Última actualización: 2026-09-27 ·
> Decisiones: `#755` (esta), `#754` (encuestas anónimas, su T1) · Carril: **SPA** (banda 730–759). Amplía `analitica.md`
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
- **Estado**: ✅ aprobada (27-09, `#755`); T0a·T0b·T0c ✅ → **T1** = la T5 de `encuestas.md` (`#754`). **Nada de lo medido se
  pierde** (§4.1.bis, con guarda): se resume arriba y lo demás queda plegado o en su pestaña.
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
6. El texto para IA: solo agregados (guarda: ni nombre, ni correo, ni teléfono, ni texto libre, ni celdas < 5), ≤ 8 KB.
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

- **Tu historia, primero** (la más fiable): la banda «normal para ti» es el intervalo entre los percentiles 25 y 75 de la
  misma cifra en los últimos 12 periodos COMPARABLES (mismo mes o mismo día de la semana); hacen falta ≥ 8, y antes dice
  «aún sin historia».
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

- Un botón en «Resumen» abre un texto listo para copiar (Markdown, ≤ 8 KB) y «Copiar». Lleva: las instrucciones
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

### 4.9 Marketing con coste y los correos por cliente (T5)

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
- **Glosario** (guarda: `AnalyticsJargonTest` (futuro) sobre los rótulos): «Sesiones» → «Visitas a la web» · «Primer
  toque» → «Cómo llegaron la primera vez» · «Último toque» → «Qué les hizo comprar» · «Anterior a la medición» → «Sin dato
  (antes de medir)» · «Sistema» → «Automático» (y a «Calidad del dato») · «Fuera del recuento», «bots», «internas» → solo
  en «Calidad del dato» · «Embudo» → «Del paso a paso a la compra».
- **El ojo**: cada tanda se enseña en vivo en `localhost:8081`, en tablet, móvil y escritorio, ANTES del commit (regla 7
  del owner); si el owner repite que algo se ve mal, se le enseñan opciones renderizadas.

### 4.12 Las tandas (el orden que aprobó el owner, con la T0 delante)

| | Tanda | Entrega | Verificación |
|---|---|---|---|
| T0 | **El rigor del tiempo y la anatomía** — **T0a ✅** (el tiempo, `0dc5d317`; §4.3) · **T0b ✅** (la anatomía, `6243e8d9`; §4.2) · **T0c ✅** (los que vuelven, `#756`; §4.8.bis) — las tres vistas y aprobadas por el owner | §4.3 (tramo transcurrido, mismo día de la semana) · `Metric` (futuro) con polaridad, base mínima, puntos, Wilson · las 44 tarjetas de hoy pasadas por el componente, sin datos nuevos · el censo de §4.1.bis y su guarda | test del tramo (el 27 contra el 27), de la polaridad y de la base pequeña, con su mutación · sonda |
| T1 | **Encuestas anónimas** | `encuestas.md` §4.7 (`#754`) | las de esa spec |
| T2 | **Ocupación y anticipación** | la pestaña, §4.8 | tests de la regla con aforo y líneas vivas · `EXPLAIN` con un año sintético · sonda · ojo |
| T3 | **Resumen y la reorganización** | §4.1, §4.4–§4.7, §4.11: las siete pestañas, los objetivos, las referencias (su historia; el sector con fuentes que se traen al owner), las frases, «lo que ha cambiado», el texto para IA y su guarda, la carga por pestaña, el glosario | guardas de IA y jerga · sonda (primera cifra en la primera pantalla, peticiones al abrir) · ojo |
| T4 | **La cartera** | §4.8 | test «a estas alturas» con fechas fijas · ojo |
| T5 | **Marketing con coste y correos por cliente** | §4.9 | tests de la marca en enlaces firmados, del píxel con y sin consentimiento, de la oposición · `Http` y Mailpit · ojo |
| T6 | **Cohortes** | §4.10 | test de cohorte con fechas fijas · `EXPLAIN` · ojo |
| T7 | **Pérdidas y complementos** | §4.10 (promociones con plataforma) | tests · ojo |
| T8 | **Satisfacción** | §4.10 (la foto diaria de Google) | tests · ojo |

## 5. Impacto en invariantes

`RGPD-01` (purga y export de `email_sends` y de las tablas de `#754`) · `RGPD-04` (`no-store` en el píxel y en el texto
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
- **Pendiente**: `[PENDIENTE: asesoría]` los correos por persona y el píxel.
