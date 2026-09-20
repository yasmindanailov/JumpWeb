# [SPEC] El paquete de instancia — la landing fuera del producto (F5 · T2)

> Estado: ✅ **APROBADA — las tres decisiones contestadas por el owner** (`#647`) ·
> Última actualización: 2026-09-19 · Decisión asociada: `DECISIONES #647`.
> Carril: **plataforma**. Origen: `specs/instancia-y-landing-fuera.md` §4.6·2 (la T2) y
> `specs/producto-e-instancias.md` §4.1 (las tres capas). Hermana: `specs/cajon-empaquetable.md` (F4).

## §0 · Antes de tocar

- **Regla que ordena todo**: el producto presta el MOTOR, la instancia pone la PÁGINA. Nada de un cliente en
  `main`, y nada de código de producto dentro de una instancia.
- **Empieza por** §1.3 (la superficie de ejecución) → §4.1 (el namespace) → §3 (por qué no la vía A hoy).
- ⚠️⚠️ **Esto NO es «servir ficheros»: es EJECUTAR CÓDIGO.** Blade compila a PHP y lo ejecuta. Un directorio
  de vistas es un directorio de código, así que la ruta se declara en configuración, **jamás** se deriva de
  una petición, y quien puede escribir ahí puede ejecutar en el servidor. Es el punto que decide el diseño.
- ⚠️ **La vía B es TRANSICIÓN, no destino** (`producto-e-instancias.md` §4.7): ata la landing a componentes
  del producto. Medido: las vistas usan **29 componentes distintos**. Se acepta a sabiendas y con fecha.
- ⚠️ **Una URL que cambia es SEO perdido y no falla nada**: el sitemap se compara antes y después. Sale de
  **nombres de ruta del producto** (§1.4), así que las rutas se quedan en `main`; solo se mudan las vistas.
- **Estado**: ✅ aprobada (`#647`). **T2a HECHA**; **T2b EN CURSO**: mudadas `/contacto`, `/normas`, `/bar`,
  los cinco legales, `/atracciones`, `/precios` y `/cumpleanos` (`#654`→`#659`; §4.4 y §4.7) y **la suite corre SIN paquete**
  (`phpunit.xml`): el producto prueba su anfitrión mínimo y la instancia se prueba con la huella. ⚠️ Mudar
  una vista es, en este orden: barrer sus reglas (§4.7), partir sus pruebas por lo que afirman (§4.5.bis),
  dejar un anfitrión mínimo que cumpla el armazón, y huella 0 — **con la página DENTRO de la huella**
  (`#657`: no lo estaba).
- **Invariantes**: **`SEC-12` es de aquí** (la ruta de vistas) y no se relaja. Ninguno más cambia.

## 1. Contexto y problema — MEDIDO (2026-09-19)

### 1.1 Qué hay que mudar

- `resources/views/home.blade.php` son **1.631 líneas**; las ocho de `resources/views/pages/`, **1.511**.
- **Ocho rutas del producto sirven la portada** y seis solo para tener algo detrás del cajón abierto
  (`instancia-y-landing-fuera.md` §1.1).

### 1.2 Qué vive YA fuera, y qué no

La rama huérfana `cliente/playjump` tiene **103 ficheros**: `aplicar.sh`, `datos/catalogo-playjump.sql`,
`entorno/cliente.env`, `fuentes/`, los mockups del canvas y **el tema entero** (`public/css/client.css` y
trece `public/img/client-*`). ▶ **Lo que NO está fuera son las VISTAS**: siguen en `main`.

▶ Y es una RAMA, no un repo. `producto-e-instancias.md` §4.1 pide `instancia-<slug>`.

### 1.3 La superficie de ejecución (lo que decide el diseño)

No existe `config/view.php` (futuro): el framework resuelve por su defecto (`resources/views`) y compila a
`storage/framework/views`. **Blade compila a PHP y lo ejecuta**, así que añadir una ruta de vistas es añadir
una ruta desde la que el servidor ejecuta código. De ahí las tres reglas duras de §4.2.

### 1.4 El sitemap sale de NOMBRES DE RUTA, no de ficheros

`SitemapController` compone sus **11 URLs** con nombres de ruta del producto (`home`, `precios`,
`cumpleanos`, `servicios`, `contacto`, `normas` y las cinco `legal.*`) y sus `lastmod` de modelos del
producto. ▶ Mudar las VISTAS no toca el sitemap; mudar las RUTAS sí. Es el argumento medido de que en la T2
las rutas se quedan.

### 1.5 Cuánto ata la vía B

Las vistas de la landing usan **29 componentes distintos** del producto (`<x-site.nav>` y `<x-site.facade>`
diez veces cada uno, `<x-layout>` nueve). De los **32** componentes de `site/`, **siete** leen material del
cliente (`favicon`, `brand`, `menu`, `sample-qr`, `kit-ico`, `ilu`, `facade`) — y lo hacen por el mecanismo
del hueco que ya existe: el código es genérico y el arte vive fuera. ▶ O sea: la deuda de la vía B **no** es
material del cliente dentro del producto; es que la instancia depende de 29 nombres de componente del
producto. Renombrar uno rompe la instancia sin que falle un test de `main`.

## 2. Objetivo y criterios de éxito

1. **El visitante no nota nada**: el sitemap sigue dando las mismas **11 URLs** y la huella de maquetación de
   las **34 pantallas** (390 y 1280) no tiene ni una diferencia entre antes y después.
2. **`main` deja de contener la landing de PlayJump**: `home.blade.php` y `pages/` salen del producto.
3. **El producto arranca SIN paquete de instancia**: sin él sirve el anfitrión mínimo, no un error.
4. **La plantilla existe y se puede estrenar**: de ella sale un `instancia-<slug>` vacío que levanta.
5. **La ruta de vistas no puede venir de una petición**, y hay una guarda que lo demuestra.

**Fuera**: la vía A (estáticos); retirar los seis recursos del panel (T3); `zones` (T4); la v2.0.0 (T5);
rediseñar la landing (es del owner y de su canvas).

## 3. Opciones consideradas

- **A · Namespace de vistas de instancia** (`instancia::home`) — **ELEGIDA**, §4.1.
- **B · `prependLocation()`**: la instancia sobrescribe por NOMBRE (`home`, `pages.pricing`). **Descartada**:
  es sobrescritura implícita. Un fichero mal llamado secuestra una vista del producto —el correo de
  verificación, un error— sin que nada avise, y el fallo aparece en la instalación del cliente, no aquí.
  Con namespace, lo de la instancia se LLAMA distinto y no puede colisionar.
- **C · Vía A ya (estáticos servidos por el servidor web)**: es el DESTINO, no el paso de hoy. **Descartada
  para la T2**: hoy las vistas usan 29 componentes del producto, ocho rutas de servidor sirven la portada y
  el sitemap sale de nombres de ruta (§1.4). Ir a la vía A ahora es rehacer la landing, no mudarla — y la
  T2 dice «tal cual», que es lo que permite comparar la huella y saber que no se rompió nada.

## 4. Diseño elegido

### 4.1 El namespace `instancia`

Un proveedor del producto registra **un** namespace de vistas apuntando a la carpeta `web/` del paquete de
instancia, leída de configuración (`instancia.ruta` (futuro), de una clave del `.env`, **no** de la BD: hace
falta antes de que la BD conteste).

    resources/views/            ← producto: el cajón, el panel, los correos, el anfitrión mínimo
    <paquete>/web/              ← instancia: home.blade.php y pages/*.blade.php

Las ocho rutas de la portada resuelven **`instancia::home`** y caen al anfitrión mínimo del producto si el
namespace no tiene esa vista. La resolución vive en UN sitio (un helper), no repartida por los controladores.

### 4.2 Las tres reglas duras de la ruta — `SEC-12`

1. **Viene de configuración y nunca de una petición.** Ni de un parámetro, ni de una cabecera, ni del
   host. La función que la resuelve **no acepta argumentos**: lo que no se puede pasar no se puede colar.
2. **Absoluta.** Una relativa se resolvería contra el cwd del proceso, distinto en `artisan`, en php-fpm y
   en la cola: tres landings según quién renderice.
3. **Fuera del ÁRBOL del producto** (`base_path()`), que cubre dos peligros de un golpe: bajo `public/` el
   servidor entregaría el `.blade.php` en crudo, y en cualquier otro sitio del árbol el `rsync --delete`
   del despliegue se lo llevaría. ▶ Es además lo coherente con el diseño: el paquete es **otro repo**.

⚠️ **Sin paquete no se cae**: no se registra el namespace, `pick()` devuelve el respaldo del producto y la
instalación sirve su anfitrión mínimo. Una instalación recién montada está justo así.

▶ **Corregido al implementar (19-09)**: el primer borrador decía «fuera del docroot» y que el despliegue
excluiría la ruta del `--delete`. Las dos cosas eran flojas: un paquete en `public_html/instancia/` está
fuera del docroot (`public/`) pero DENTRO del árbol desplegado, y el `--delete` se lo lleva. La regla de
`base_path()` no necesita exclusión ninguna en el despliegue.

### 4.3 La plantilla que publica el producto

Una carpeta `plantilla/` (futuro) en `main` con el esqueleto de `instancia-<slug>`: `web/` con una portada
mínima de ejemplo, `tema/`, `config/`, `datos/`, `docs/`, `instalar.sh` y un fichero que declara **la versión
del contrato de instancia** que espera. El producto valida esa versión al arrancar (aviso, no caída).

⚠️ La plantilla es **producto** (un mecanismo, igual para todos); lo que salga de ella es **instancia**.

### 4.4 Qué se muda y qué no

| Se muda a la instancia | Se queda en el producto |
|---|---|
| `home.blade.php` y `pages/*.blade.php` · **✅ `/contacto`, `/normas`, `/bar`, los cinco legales, `/atracciones`, `/precios` y `/cumpleanos`** (`#654`→`#659`, 20-09); quedan `servicios` y `home` | Las **rutas** y sus nombres (el sitemap, §1.4) · un **anfitrión mínimo** por vista mudada, en `resources/views/anfitrion/` |
| — | Los **32 componentes** de `site/` (son mecanismo; siete ya leen el arte de fuera) |
| — | El cajón, el panel, los correos, `/mi-cuenta`, `/api/v1` |

### 4.5 ⚠️⚠️ Mudar una vista es mudar sus PRUEBAS, y eso no estaba diseñado

**Medido el 19-09, intentándolo con `/contacto`**: con la vista fuera del producto, **9 de los 14 casos de
`ContactPageTest` fallan** en cualquier máquina que no tenga el paquete. Y no hay `.env.testing` ni variable
en `phpunit.xml`, así que la suite lee el `.env` de cada máquina: el gate habría salido **verde en el
ordenador que tiene el paquete y rojo en el otro**, que es la peor forma de romper algo.

No es un detalle de fontanería: **el producto no puede probar una página que ya no es suya**, y la instancia
no es una app PHP con suite propia.

▶ **Se plantearon tres salidas y NINGUNA era la buena** (se dejan escritas porque la descartada es
información): (1) las pruebas se van con la vista y la instancia monta CI de PHP contra un checkout del
producto; (2) el producto trae un paquete de PRUEBA en sus fixtures y la suite lo usa; (3) las de contenido
se retiran y queda la huella de maquetación. Las tres **daban por hecho que los 14 casos son una unidad que
hay que colocar en algún sitio**, y no lo son.

### 4.5.bis · La salida elegida: partir por lo que AFIRMA cada caso (`#649`)

**El propio experimento trazó la línea**: de los 14, **cayeron 9 y aguantaron 5**. Los que aguantaron no
miran el HTML —hacen `POST /contacto`, comprueban que un tema inventado se rechaza y que el tema llega al
asunto del correo—; los que cayeron afirman sobre el **marcado**: que el botón de enviar es el relleno
secundario, que el aviso de privacidad va como nota, que no hay mapa.

> **La regla, en una línea**: el contrato entre el producto y una instancia son los **DATOS que recibe la
> vista**, no el **HTML que la vista produce**.

Es la línea que separa una prueba de CONTRATO —la escribe quien produce— de una de RENDERIZADO —la escribe
quien consume—. Hoy están mezcladas: el producto prueba su propia conducta **a través del marcado de un
cliente**, y por eso mudar una vista le rompe la suite.

| El producto prueba | La instancia prueba |
|---|---|
| Que el controlador pasa `answers` y `topics` | Que su página pinta lo que ella quiere |
| Que un tema inválido se rechaza | Que el botón lleva la clase que ella decidió |
| Que el tema llega al asunto del correo | Que no hay mapa, si ella no quiere mapa |
| Que el mecanismo resuelve la vista y cae al respaldo | — |

⚠️⚠️ **Y la herramienta de la instancia NO es `assertStringContainsString`: es la huella de maquetación**
(34 pantallas). La diferencia no es de estilo: una afirmación de cadena se rompe cada vez que el cliente
rediseña —que es su derecho—, y eso es un fallo EQUIVOCADO; la huella solo dice «esto cambió sin querer»,
que es la pregunta buena.

**Por qué escala**: diez clientes y la suite del producto ni crece ni depende de ninguno · un cliente
rediseña y no se pone rojo nada del producto · un cliente rompe lo suyo y es suyo · y **nadie monta CI de
PHP en un repo que no es PHP**, que es lo que hundía la salida 1.

### 4.6 El CONTRATO DE VISTA, que es lo que hay que añadir

Hoy, si alguien renombra la variable `answers` en `ContactController`, **se rompen todas las instancias a la
vez y ninguna prueba del producto se entera**. Eso es justo lo que el producto tiene que guardar.

Cada vista que una instancia puede vestir **declara qué variables recibe**, y una guarda verifica que el
controlador las sigue pasando. Es el hermano de `instancia.json`: aquél dice con qué VERSIÓN del producto
funciona un paquete; éste dice qué le PROMETE el producto a cada vista.

⚠️ La guarda afirma sobre los **datos de la vista**, nunca sobre el HTML — si mirara el marcado volvería a
atar el producto a la landing de un cliente, que es el defecto que esta sección arregla.

▶ **Medido al escribirla (19-09): la vista recibe NUEVE variables, no dos.** El controlador pone `answers` y
`topics`; las otras siete las inyecta el composer global en TODA vista (`site`, `heroStatus`, `offers`, los
dos `ctaMinPrice*` y las dos de cookies). El producto ya promete siete cosas sin saberlo — y esa lista **va
a encoger**, porque ese composer es «la pieza que hay que sustituir por el menú» (§1.1 de la spec hermana) y
`offers` lo retira `#631`. Ese día sube el MAYOR del contrato de instancia y hay que avisar a cada
instalación. Sin la lista, nadie se habría enterado hasta ver la web de un cliente rota.

### 4.7 Una regla escrita DENTRO de una vista se va con la vista (`#650`)

Es el corolario práctico de §4.5.bis, y la T2b empieza por aquí: antes de mudar una vista hay que mirar **qué
reglas del producto viven dentro de ella**, porque el día de la mudanza se van con ella y cada instancia las
re-deriva a su manera.

**Caso medido y ya arreglado**: la dirección de dos líneas se unía **con coma** —«Ctra. de Prueba, 1 30000
Ciudad» se lee como si el código postal fuera el portal— y esa regla estaba escrita **dos veces**: en línea
en `pages/contact.blade.php` y en `StructuredData::postalAddress()`, con filtros distintos. Y `/site`
publicaba las dos líneas sueltas, así que la tercera copia la habría escrito **cada instancia**.

▶ Ahora la regla tiene un hogar (`Platform\Services\VenueAddress`), la API publica `address.written` además
de las líneas, y su guarda afirma sobre el dato: **sobrevive a la mudanza**.

⚠️ La duplicación ya había cobrado una pieza: el `streetAddress` del JSON-LD hacía pasar en verde un caso
que aseveraba sobre la página entera aunque el bloque visible dijera otra cosa.

▶ **Los tres barridos hechos** (20-09), y lo que enseñó cada uno:
- `#650` · la **dirección** (dos copias con filtros distintos → `VenueAddress`, y `/site` publica `address.written`).
- `#651` · los **separadores numéricos**, que eran un defecto vivo en inglés (`4,8`) → `LocalNumber`.
- `#653` · la **`<meta description>`**, escrita CUATRO veces —normas, atracciones, legales y cumpleaños—,
  cada una a su manera y una **sin tope** → `MetaDescription`, y `/rules` y `/legal/documents/{clave}`
  publican `summary`. ⚠️ Lo que no se publica también es decisión: las atracciones salen del producto (`#631`)
  y la descripción del pack solo viaja en el detalle (`#645`); ahí la instancia llama al servicio (vía B).
  **Medido** en 8 páginas × 3 idiomas, antes y después: `/normas` idéntica; las cinco legales cambian solo el
  corte (en palabra entera: «de que e...» → «de que...»); `/atracciones` se llena hasta el tope (paraba en 8
  nombres, 111 caracteres); `/cumpleanos` se acota (180 → 155). Nada visible cambia.
- Y una **cuarta regla, medida y sin tocar**: `pages/services.blade.php` escribe el precio con un `$fmt`
  propio (`1500 €` sin millares, coma fija en inglés), una tercera variante de `Money`. Es del carril de la
  web y `/servicios` está pausada por el owner (`#534`): avisado en el buzón, se arregla con su rediseño.

▶ **`/contacto` ya está PARTIDA por lo que afirma** (20-09, aplicando §4.5.bis): de los 14 casos de
`Landing/ContactPageTest`, los dos de conducta (el tema desconocido se rechaza; el tema llega al asunto) se
fueron a `tests/Feature/ContactPageTest`, que es el fichero de CONDUCTA del producto y afirma sobre `answers`,
`topics`, la sesión y el correo —los atajos (una página en mantenimiento, el ancla de Dudas) se comprueban
ahora sobre el DATO, no sobre los `href`—. Los doce de marcado se quedan allí bajo su «guarda de la guarda»
(el escáner que exige las piezas de la página y que sin la vista cae: no hay verde en vacío) y **se mudan
con la vista**.

▶ **`bar` y `pricing`, barridas sin hallazgo** (20-09): todo les llega compuesto del controlador. En
`contact` había dos MECANISMOS del producto escritos en línea, y se fueron a componente antes de la mudanza:
el honeypot (su nombre es un contrato con el controlador: `Platform\Services\Honeypot` y `<x-site.honeypot>`)
y el widget de Turnstile (`<x-site.turnstile>`: sin él, con claves, el formulario no entregaría nada).

▶ **`/contacto` MUDADA** (`#654`, 20-09): la vista va tal cual al paquete (mismo DOM; huella 0 diferencias
en 34 pantallas; sitemap 11=11) y el producto se queda `anfitrion/contacto`, que cumple el armazón y funciona
sin arte. **La suite corre SIN paquete** (`phpunit.xml` fija `INSTANCIA_RUTA` vacía): es la salida de §4.5.
Los doce casos de marcado se fueron con la vista: dos eran reglas del componente de canales
(`ContactChannelsTest`), uno de `lang/` (`CopyHasNoBusinessDataTest`), y los nueve de diseño están en la
doc de la instancia (`paginas/contacto.md`), con la huella de juez. `mutar-contacto.py` conserva los
mutantes del producto y `mutar-paquete-instancia.sh` gana tres del anfitrión.
⚠️ **La línea base (huella, DOM, sitemap) se toma ANTES de tocar el controlador que elige la vista**: con
`pick()` ya apuntando al anfitrión, «la vista de antes» que se captura es el respaldo nuevo, y se compara
consigo mismo. Costó una pasada.

▶ **`/normas`, `/bar` y los cinco legales, MUDADAS** (`#655`, 20-09) con el mismo método, tres vistas de una
vez. El barrido cazó una regla MUERTA (el aviso de borrador de los legales, apagado por una lista que ya
tenía las cinco) y una VIVA en `/normas` (la fecha: «September de 2026» en inglés → `LocalDate`, `#656`).
Dos guardas transversales cambiaron de sujeto: la decoración de cabecera se prueba en el componente
`page-head`, y el corpus de la fachada mira la portada. Medido: mismo DOM salvo la fecha corregida, huella
0 diferencias en 34 pantallas, sitemap 11=11.
⚠️ `InstanceViewContractTest` prepara lo que cada página necesita para responder (`/bar` no existe sin
nombre): un contrato que no se puede medir no vigila nada.
⚠️⚠️ **El material se queda y su consumidor no.** En la T2 solo se mudan las vistas (§4.4), así que el CSS
de la landing y las ranuras del kit siguen en el producto; pero las guardas de huérfanos del producto
(`FacadeCssHasNoOrphansTest`, `ZonesSectionTest`) dejan de ver a su consumidor y piden retirarlo, y
retirarlo rompería la landing de la instancia sin que nada fallara aquí. La salida es UNA lista,
`InstanceViews::MATERIAL_CONSUMIDO_POR_LA_INSTANCIA` (pieza → vista que la pinta), que las dos guardas leen:
excluyen esas piezas y exigen lo contrario de ellas —que ninguna vista del producto las pinte—. El corpus de
consumidores excluye esa declaración, como excluye las hojas que mide. **Es la deuda de la vía B hecha
lista, y se vacía cuando el material se mude con las vistas** (T3–T5).

▶ **`/atracciones`, MUDADA** (`#657`, 20-09), con el mismo método. Lo que enseñó, y no es de esta página:
- **El barrido bajó la foto al modelo**: `asset($ride->image)` vivía en la vista y **dos veces** en el
  mosaico de la portada, y el producto ofrece al lado la respuesta EQUIVOCADA para la misma pregunta
  (`TicketType::imageUrl()` antepone `uploads/`, porque aquello sí es una subida). Nace
  `Attraction::imageUrl()`, letra por letra el de `Zone` (`#645`). Sin cambio de HTML.
- ❗❗ **El JUEZ no miraba la página que se mudaba.** `huella-maquetacion.mjs` recorría **14 vistas** y ni
  `/atracciones` ni `/waiver` estaban en la lista —y `/waiver` es **uno de los cinco legales que `#655` ya
  mudó**, así que aquella tanda dio «huella idéntica» habiendo medido cuatro de sus cinco páginas—. Antes de
  tomar la línea base se añaden las dos: **16 vistas, 38 pantallas**. *Una lista de vistas escrita a mano
  envejece sin avisar; se comprueba contra `route:list` al mudar una página.*
- **Una guarda re-apuntada no puede quedar más débil**: `ZoneIdentityIsUniqueTest` —que nació de tres
  paneles abriendo a la vez por identificar la zona con el ACENTO— ya se había quedado sin sujeto dos veces
  (`#295`, `#482`). El anfitrión mínimo conserva a propósito los tres sitios que miraba (los dos `id` y el
  `aria-controls`), y su test lo declara para que nadie los «simplifique».
- Y lo que NO es del anfitrión: las tres cuentas de contraste de la cifra de zona se mudan a
  `Theme/ZoneInkIsLegibleTest`. Son regla de TEMA medida contra la hoja del producto, no dato de la página.

▶ **`/precios`, MUDADA** (`#658`, 20-09). Su barrido no halló ninguna regla escrita en la vista —todo le
llega compuesto— pero sí **tres variables MUERTAS** (`tickets`, `zones`, `registrationUrl`): viajaban a la
vista y ninguna se leía. ⚠️⚠️ **El barrido de una vista que se muda incluye QUÉ VARIABLES USA**, y no es
higiene: mientras es una variable, un dato sin consumidor no se nota; en cuanto se declara en
`CONTRATO_DE_VISTAS` es una **promesa a cada instalación**, y retirarla después sube el MAYOR del contrato
y obliga a avisar a todas. *Lo que no se usa se retira ANTES de prometerlo.*
▶ Y dos cosas cambian de sujeto, no de fuerza: la mitad de `PageHeadTest` que exigía el abanico en su
ranura de fachada se prueba sobre el **componente** (que es del producto), y **seis mutantes de
`mutar-precios.py` se retiran con el suyo** —los del bloque de complementos y la hora extra (`#583`), el
del chip que solo marcaba a la que lidera (`#585`) y los dos de la línea a mano hacia cumpleaños, hoy
banda—. Un mutante cuyo texto ya no existe sale «NO APLICADA» y solo mide que nadie lo mira.

⚠️ **Y una vista se mueve TAL CUAL**, porque hay guardas que la leen como TEXTO: las cadenas viajan con
ella, así que se re-apunta cada guarda al fichero nuevo y se MUTA allí. Se busca ANTES —`grep -rln
"pages/pricing" tests scripts`—, que es como salieron las once de `js/app.js` en su día.

▶ **`/cumpleanos`, MUDADA** (`#659`, 20-09). Su barrido halló la misma clase de regla que `#657`: la foto
de la zona se re-derivaba con `asset()` teniendo el producto su `Zone::imageUrl()` desde `#645`. Y **estrena
de verdad la lista de material**: `.trio` y `.trio-stand` conservan consumidor en el producto (la portada),
pero el MODIFICADOR `trio--page` no, así que se declara con su vista. ⚠️ *La entrada de la lista es el
MODIFICADOR, no la familia*: declarar `trio` entero habría tapado que la portada sí lo pinta.

▶ **Lo que queda de la T2b**: `servicios` y, la última, `home`, con el mismo método: barrido de reglas →
**barrido de variables muertas** → partir pruebas → anfitrión mínimo → huella. Después, T3–T5 (spec
hermana §4.6).

▶ Hasta aquí llega la T2a: el mecanismo vivo y la vista en su sitio. `/contacto` resuelve
`instancia::contacto` si el paquete la trae, y la del producto si no.

## 5. Impacto en invariantes

- **Ninguno existente cambia.**
- ▶ **Propone uno NUEVO** (`SEC-NN` (futuro)): *la ruta de vistas de instancia sale de configuración, vive
  fuera del docroot y jamás se deriva de una petición*. Un invariante nuevo es **decisión del owner**
  (`CONVENCIONES §9.1-2`), y por eso esta spec no lo da por hecho.
- `PERF-02` y `SEC-01`: sin cambios; esta tanda no añade rutas públicas ni lecturas nuevas.

## 6. Plan de verificación empírica

1. **Guarda de la ruta**: un caso que demuestre que la ruta de vistas no cambia con la petición (parámetro,
   cabecera y host), y su mutación en el arnés.
2. **Respaldo**: sin paquete configurado, las ocho rutas responden **200** con el anfitrión mínimo, no 500.
3. **Sitemap idéntico**: las 11 URLs, comparadas antes y después con el mismo comando.
4. **Huella de maquetación**: `scripts/huella-maquetacion.mjs` sobre las 34 pantallas, **0 diferencias**.
5. **Estreno de la plantilla**: de ella sale una instancia vacía que levanta y sirve su portada de ejemplo.
6. La suite entera en verde, y `VERIFY_CONC` no aplica (ni dinero ni aforo).

**Medido el 20-09 con `/contacto`** (`#654`): sitemap 11=11 · huella 0 diferencias en 34 pantallas · sin
paquete, `/contacto` responde 200 con el anfitrión mínimo; con un paquete que no trae la vista, también
(`AnfitrionContactoTest`) · la suite entera en verde SIN paquete.

## 7. Revisión y decisión

**2026-09-19 · las tres contestadas por el owner** (`#647`), con la spec delante:

- **D1 · La ruta de vistas es INVARIANTE** → nace `SEC-12`. *Descartado*: dejarlo solo en un test.
- **D2 · Se estrena con UNA página** (`/contacto`) y después el resto: el mecanismo se prueba con poco en
  juego, y un fallo de diseño se ve en una página y no entre 3.142 líneas. *Descartado*: las nueve de una.
  ▶ Parte la T2 en **T2a** (mecanismo + plantilla + `/contacto`) y **T2b** (el resto de las vistas).
- **D3 · `instancia-playjump` nace como REPO** y `cliente/playjump` se retira. *Descartado*: la rama una
  tanda más.

▶ **Deuda aceptada a sabiendas**: la instancia dependerá de **29 componentes** del producto (§1.5), y
renombrar uno la rompe sin que falle un test de `main`. Es el precio de la vía B, que es transición.

## Anexo · fila del enrutador

`| El paquete de instancia · la landing fuera · la vía B · la plantilla | docs/specs/paquete-de-instancia.md §0 |`
