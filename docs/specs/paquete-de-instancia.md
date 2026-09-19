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
- **Estado**: ✅ aprobada (`#647`). **T2a HECHA**: el mecanismo, `SEC-12`, la `plantilla/` y `/contacto`
  resolviendo por la instancia. ⚠️ **La vista NO se mudó**, y es lo que la T2a descubrió (§4.5): mudarla
  deja sus 14 pruebas sin sujeto y el gate sale verde en una máquina y rojo en otra. **T2b empieza
  eligiendo dónde viven las pruebas de la landing**, no moviendo ficheros.
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
| `home.blade.php` y `pages/*.blade.php` | Las **rutas** y sus nombres (el sitemap, §1.4) |
| — | Los **32 componentes** de `site/` (son mecanismo; siete ya leen el arte de fuera) |
| — | El cajón, el panel, los correos, `/mi-cuenta`, `/api/v1` |

### 4.5 ⚠️⚠️ Mudar una vista es mudar sus PRUEBAS, y eso no estaba diseñado

**Medido el 19-09, intentándolo con `/contacto`**: con la vista fuera del producto, **9 de los 14 casos de
`ContactPageTest` fallan** en cualquier máquina que no tenga el paquete. Y no hay `.env.testing` ni variable
en `phpunit.xml`, así que la suite lee el `.env` de cada máquina: el gate habría salido **verde en el
ordenador que tiene el paquete y rojo en el otro**, que es la peor forma de romper algo.

No es un detalle de fontanería: **el producto no puede probar una página que ya no es suya**, y la instancia
no es una app PHP con suite propia. Las salidas posibles, que la T2b tiene que elegir con su coste delante:

1. **Las pruebas se van con la vista** y la instancia monta su CI contra un checkout del producto.
2. **El producto trae un paquete de instancia de PRUEBA** en sus fixtures y la suite lo usa: prueba el
   mecanismo siempre, y el contenido de la landing deja de ser asunto suyo.
3. **Las pruebas de contenido se retiran** y lo que queda en el producto es la huella de maquetación, que ya
   cubre las 34 pantallas y no mira el marcado por dentro.

▶ Hasta que eso se decida, la T2a deja **el mecanismo vivo y la vista en su sitio**: `/contacto` resuelve
`instancia::contacto` si el paquete la trae, y la del producto si no. Una instancia ya puede vestir esa
página; el producto sigue bastándose solo.

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
