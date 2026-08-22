# Verificación extremo a extremo del cajón SPA — guion operativo

> Estado: vivo · Creado 2026-08-14 · **EJECUTADO el 2026-08-14** (`DECISIONES #59`) ·
> Es la forma EJECUTABLE de `specs/sidebar-spa.md` §6.
> Se invalida si: cambia el corte de pasos del cajón o el driver de pasarela.

## ▶ Resultado de la PRIMERA ejecución (2026-08-14)

Se recorrió con navegador headless (Playwright) y la pasarela REAL en sandbox, con los dos motores.
**Encontró cuatro cosas y tres estaban rotas de raíz** — el motor SPA no funcionaba en producción
pese a tener la fase entera transcrita y en verde. El detalle está en `DECISIONES #59`; en corto:

| # | Qué | Estado |
|---|---|---|
| a | **El desenlace del pago no llegaba al store**: quien volvía de pagar veía el catálogo. Toda la 4.6, invisible | ✅ arreglado + red en `store.test.js` |
| b | **El motor no montaba con el cajón nacido abierto** (`/entradas` y la vuelta del pago): hueco vacío | ✅ arreglado |
| c | **El catálogo SPA no enseñaba ni un producto** (`is-open` ausente; el diff no podía verla) | ✅ arreglado en el ORIGEN, y ahora el gate SÍ la ve |
| d | **Elegir día avanza solo en la SPA y no en Livewire** | 🟦 declarado en `DEUDA.md` — decisión de producto |

✅ **Verificado de punta a punta en los DOS motores**: el embudo entero, el cobro de la **señal**
(30,00 € de una reserva de 165,00 €), la vuelta al paso 6, y **los dos pedidos IDÉNTICOS en BD**. El
paso 11 se comprobó con la vuelta *data-less* real: sondeo cada 5 s, salto solo al paso 6 y **parada**
del sondeo. Lo que sigue sin verificarse está en §6 de este documento.

## 0. Qué es esto, y qué NO es

Es lo único que separa a `sidebar.engine = spa` de poder desplegarse. **No sustituye a ninguna
paridad**: comprueba exactamente lo que ninguna puede comprobar, que es todo lo que necesita un
navegador de verdad y una pasarela de verdad.

⚠️ **Lo primero es saber qué NO hace falta mirar.** Media docena de casos de §6 ya tienen red
automática y repetirlos a mano es tiempo tirado:

| Caso de §6 | Ya cubierto por | ¿Navegador? |
|---|---|---|
| Reservas en pausa | `SidebarPausedParityTest` + diff de árbol | no |
| Tope de pendientes · frecuencia | `SidebarAdmissionParityTest` (3 idiomas) | no |
| Agotado · fuera de horario · fecha pasada | `SidebarPayParityTest` (enum entero) | no |
| `NOT_RETRYABLE` (la DECISIÓN) | `SidebarOutcomeParityTest` | solo la pantalla |
| Anti-enumeración del alta | `Api\V1\AuthRegistrationTest` (el señuelo, indistinguible byte a byte) | no |
| Fusión de líneas · líneas fantasma | `cart.test.js` | no |
| Los once árboles | `SidebarDomContractTest` | no |

**Lo que solo se ve en el navegador**, y es la lista que este guion recorre:

1. El **auto-envío** del paso 9 (`onMounted` no corre en SSR: el gate compara el marcado sin dispararlo).
2. La **vuelta real** de la pasarela: OK, KO y *data-less*.
3. El **sondeo vivo** del paso 11 y su transición al 6.
4. El **puente hacia Livewire**: `account-context` repintando tras el login del cajón, y los modales de auth.
5. El **confeti**, el **bloqueo de scroll** y el **foco** — nada de eso deja rastro en un árbol.
6. La **cesta cruzada entre pestañas**, que es la defensa que `localStorage` introdujo.

## 1. Antes de empezar (todo MEDIDO el 2026-08-14, no supuesto)

- ✅ **El sandbox de Redsys YA está configurado en dev**: `redsys_merchant_code` `999008881`,
  `redsys_terminal` `001`, clave fijada, `redsys_environment` = `test`. No hay que tocarlo.
- ✅ **Hay datos que comprar**: 15 productos vendibles y 429 franjas futuras. Los dos packs
  —«Cumpleaños Jump» y «Cumpleaños Kids»— traen **señal fija de 30 €, 5 campos de evento, 4 campos de
  post-form y 4 complementos**, así que un pack ejercita de una vez el desglose de señal, las respuestas
  del evento, los complementos y el aviso de formulario de invitados. **Compra un pack**, no una entrada.
- ⚠️ **`redsys_merchant_url` está VACÍA.** Es la notificación server-to-server. Sin ella, el bloque B
  no se puede hacer, y el paso 11 nunca llegará a `paid`.
- ⚠️ **El flag NO es editable desde el panel**, al contrario de lo que dice `specs/sidebar-spa.md` §4.9.
  Hoy es solo una fila de `settings`. Se cambia así:

```bash
# → motor SPA
docker compose exec -u sail laravel.test php artisan tinker --execute="
use App\Domain\Platform\Models\Setting;
Setting::updateOrCreate(['key' => 'sidebar.engine'], ['value' => 'spa', 'group' => 'general']);
Setting::flushMemo();"

# → motor Livewire (el default: basta con borrar la fila)
docker compose exec -u sail laravel.test php artisan tinker --execute="
use App\Domain\Platform\Models\Setting;
Setting::where('key', 'sidebar.engine')->delete();
Setting::flushMemo();"
```

- **Cuenta de dev**: `admin@jumpweb.test` / `password`. ⚠️ La BD arrastra además el par
  `…@jumpingjump.test` del import: usa el correo completo.
- **Deja la consola del navegador abierta.** Un fallo del motor SPA que no rompa la pantalla —un chunk
  que no carga, un `fetch` rechazado— sale ahí y en ningún otro sitio.

## 2. Bloque A — sin túnel: la vuelta del navegador

**Precondición del terminal**: en el portal admin del sandbox
(`https://sis-t.redsys.es:25443/admincanales-web/index.jsp`) el terminal tiene que estar con
**«incluir datos en redirección» ACTIVADO**. Es lo que hace que la vuelta traiga los `Ds_*` firmados.

Cada caso se hace **dos veces**: primero con `livewire` y después con `spa`. Lo que se anota es la
DIFERENCIA, si la hay.

### A1 · El camino feliz (pasos 1 → 9 → 6)

1. Abre `http://localhost:8081/entradas` — es el enlace profundo que abre el cajón solo.
   · **Mirar**: el cajón abre en el catálogo y **la página de detrás no hace scroll**.
2. Elige un **pack de cumpleaños** → día → hora → cantidad → rellena los campos del evento → añade un
   complemento → «Añadir al carrito».
   · **Mirar**: el pie enseña «Pagas ahora (señal)» con el desglose, y el importe cuadra con el catálogo.
3. «Ir a pagar». Si no hay sesión, entra desde el propio cajón.
   · **Mirar (puente)**: al entrar, el bloque de cuenta de ARRIBA del cajón cambia a «Hola, …» **sin
     recargar la página**. Si sigue diciendo «Iniciar sesión», el puente `logged-in` se ha roto.
4. Pantalla de pago → «Pagar con tarjeta».
   · **Mirar**: la banda de desglose está pegada ENCIMA del pie, sin doble borde entre ambos.
   · **Mirar (paso 9)**: aparece «te estamos redirigiendo» y el navegador **se va solo** a Redsys en menos
     de un segundo. Si se queda ahí, el auto-envío no se disparó — es lo que el gate no puede ver.
5. En la pasarela: VISA `4548 8100 0000 0003`, cad. `12/49`, CVV `123`.
6. Vuelves a la web.
   · **Mirar (paso 6)**: el cajón se abre solo en «reserva creada», con **confeti**, el resumen del pack
     con sus respuestas y su complemento, el desglose «Pagado online / Pendiente en el parque», el código
     del pedido y el aviso de formulario de invitados.
   · **Mirar**: recarga la página. El cajón **no** debe volver a abrirse. Si lo hace, el desenlace no se
     consumió (es el fallo que cerró el paso 4.0a).

### A2 · El pago denegado y su reintento (pasos 10 → 9)

1. Repite A1 hasta la pasarela.
2. Misma tarjeta pero **CVV `999`** (o un importe terminado en `.96`).
3. Vuelves.
   · **Mirar (paso 10)**: «El pago no se ha completado», el **motivo concreto** bajo «Motivo:», el código
     del pedido y los tres botones —reintentar, hacer otra reserva, escribirnos—.
   · **Mirar**: «Escribirnos» lleva a `/contacto`. El diff de árbol **no comprueba los `href`**.
4. Pulsa «Reintentar el pago».
   · **Mirar**: sale **directo a la pasarela**, sin pasar por la pantalla de pago. Paga bien esta vez.
   · **Mirar**: acabas en el paso 6 con el MISMO código de pedido. No se ha creado una reserva nueva.

### A3 · El reintento que ya no vale (`NOT_RETRYABLE`)

1. Llega al paso 10 como en A2 y **no** reintentes.
2. Caduca la retención a mano (sustituye `R-XXXX` por el código que enseña la pantalla):

```bash
docker compose exec -u sail laravel.test php artisan tinker --execute="
App\Domain\Booking\Models\Order::where('code','R-XXXX')->update(['expires_at' => now()->subMinute()]);"
```

3. Pulsa «Reintentar el pago».
   · **Mirar**: vuelve al **catálogo**. La plaza ya no era suya.
   · ⚠️ **Y mira lo que NO pasa**: no aparece ningún aviso explicando por qué. Es una **deuda de producto
     conocida** —está en `DEUDA.md`— y ocurre igual en los dos motores. No es un fallo de la SPA.

### A4 · La sesión perdida entre pasos

1. Llega al paso 10.
2. Borra la cookie de sesión desde las herramientas del navegador (Application → Cookies).
3. Pulsa «Reintentar el pago».
   · **Mirar**: el cajón lleva a la **pantalla de identificación**, no al catálogo ni a un error.

### A5 · La cesta cruzada entre pestañas

1. Pestaña 1: añade algo al carrito **sin** iniciar sesión.
2. Pestaña 2: inicia sesión con OTRA cuenta y recarga la pestaña 1.
   · **Mirar**: la cesta de la pestaña 1 se **purga**. Es la defensa que `localStorage` introdujo
     (`DECISIONES #38(d)`); si sobrevive, el titular cambió y la cesta no se enteró.
3. Y al revés: cesta de invitado + login del MISMO navegador → **la cesta SOBREVIVE**. Es el flujo
   principal, no un descuido.

### A6 · Accesibilidad y superpuestos (lo que ningún árbol ve)

1. Con el cajón abierto, pulsa «Iniciar sesión» en su bloque de cuenta y **cierra el modal**.
   · **Mirar**: la página de detrás **sigue sin hacer scroll**. Es el fallo que cerró `DECISIONES #58`.
2. Recorre el cajón entero **solo con teclado** (Tab / Shift-Tab / Escape).
   · **Mirar**: el Tab no se escapa del panel, y Escape lo cierra.
   · ⚠️ **Lo que sí va a fallar**: al cambiar de paso el foco **no se mueve**. Es hueco conocido y de los
     DOS motores (`DEUDA.md`); anótalo si quieres, pero no es una regresión.

### A7 · Los enlaces profundos

- `/entradas` → abre el cajón en el catálogo.
- Los CTA de la landing («ver packs», la tarjeta de una zona) → abren el cajón **en su sitio**.
  · ⚠️ Es la costura de 4.0a: con otro motor, esos avisos **no fallaban, no hacían nada**.

## 3. Bloque B — con túnel: el terminal *data-less* (paso 11)

Es el único bloque que necesita preparación, y cubre el caso que la doc llama «el cliente paga pero no
vuelve». **Sáltatelo si no vas a montar el túnel**, pero entonces el paso 11 queda declarado como NO
verificado en vivo.

1. Levanta el túnel y fija la URL de notificación (receta completa en `sistemas/REDSYS.md` §12):

```bash
cloudflared tunnel --no-autoupdate --url http://localhost:8081
# copia la URL https://<aleatoria>.trycloudflare.com del log

docker compose exec -u sail laravel.test php artisan tinker --execute="
use App\Domain\Platform\Models\Setting;
Setting::updateOrCreate(['key' => 'redsys_merchant_url'],
    ['value' => 'https://<tunel>.trycloudflare.com/pago/redsys/notificacion', 'group' => 'payment']);
Setting::flushMemo();"
```

2. **DESACTIVA «incluir datos en redirección»** en el portal admin del sandbox. Eso es lo que fuerza la
   vuelta sin datos firmados, que es la razón de existir del paso 11.
3. Compra y paga bien.
   · **Mirar (paso 11)**: «Verificando tu pago», con el código del pedido y «Ver mis reservas».
   · **Mirar en la pestaña de red**: una petición a `orders/{code}/payment-status` **cada 5 segundos**.
   · **Mirar**: en cuanto llega la notificación, la pantalla pasa **sola** al paso 6, con su confeti. Sin
     recargar nada.
   · **Mirar**: al llegar al 6, el sondeo **PARA**. Si sigue preguntando, el intervalo se ha quedado suelto
     — es un fallo que ningún diff puede ver.
4. **El pedido que caduca mientras se sondea**: repite hasta el paso 11 y, antes de que llegue la
   notificación, caduca el pedido con el comando de A3.
   · **Mirar**: el cajón vuelve al catálogo. La pantalla 11 **nunca** se queda colgada para siempre.
5. **La carrera notificación-antes-que-navegador**: paga y, antes de que el navegador vuelva, comprueba
   que el pedido ya está `paid` por la notificación.
   · **Mirar**: al volver, el cajón enseña el paso 6 y **no** vuelve a cobrar ni duplica nada.

⚠️ **Al terminar el bloque B, deja el terminal como estaba** («incluir datos en redirección» activado) y
borra `redsys_merchant_url` si el túnel era efímero — la URL cambia en cada arranque y una obsoleta hace
que Redsys reintente contra la nada.

## 4. Lo que se compara en BD al terminar

El criterio de §6 es «comparando el pedido en BD»: **una compra equivalente con cada motor tiene que
dejar el mismo pedido**. Con los dos códigos a mano:

```bash
docker compose exec -u sail laravel.test php artisan tinker --execute="
use App\Domain\Booking\Models\Order;
foreach (['R-LIVEWIRE','R-SPA'] as \$code) {
    \$o = Order::with('items.children')->where('code',\$code)->first();
    if (! \$o) { echo \$code.\": NO ENCONTRADO\n\"; continue; }
    echo \$code.' · '.\$o->status.' · total='.\$o->total.' · online='.\$o->onlineDueCents()
        .' · puerta='.\$o->financialSummary()->pendingAtGate().' · líneas='.\$o->items->whereNull('parent_item_id')->count()
        .' · postform='.(\$o->needsGuestForm() ? 'sí' : 'no').PHP_EOL;
    foreach (\$o->items->whereNull('parent_item_id') as \$i) {
        echo '    - '.\$i->ticketType?->tr('name').' x'.\$i->quantity.' = '.\$i->chargedSubtotalCents()
            .' · complementos='.\$i->children->count().' · respuestas='.count((array) \$i->event_data).PHP_EOL;
    }
}"
```

**Tienen que coincidir**: estado, total, importe online, pendiente en puerta, número de líneas, subtotales
por línea, número de complementos y número de respuestas del evento. ⚠️ **No** coincidirán el código ni
las marcas de tiempo, obviamente.

## 5. Qué anotar

Para cada caso, una línea: `A1 spa ✅` / `A2 spa ⚠️ el motivo salía vacío`. Lo que importa del informe son
**las diferencias entre motores** y **lo que no se pudo probar**. Si algo falla, apunta:

- en qué paso, con qué motor y qué se esperaba;
- lo que dijo la consola del navegador (literal);
- el código del pedido, que es lo que permite reconstruirlo desde la BD.

## 5.bis El ANDAMIO automático (opcional, y fuera del repo)

La primera ejecución no se hizo a mano: se montó un navegador headless. **No está en el repo ni en el
gate a propósito** —115 MB de navegador y un tercero en el camino crítico del `pre-push` son una
decisión con coste, no un detalle— pero la receta es corta y reproducible:

- **Dentro del contenedor**, que ya trae las librerías de Chromium (Sail las incluye para Dusk; el host
  WSL NO las tiene): `mkdir /root/e2e && cd /root/e2e && npm init -y && npm i playwright && npx playwright install chromium`.
  ⚠️ **NO sobrevive a recrear el contenedor** (medido el 2026-08-22: había que reinstalarlo). Son
  ~115 MB y un par de minutos; el bloqueo no es el tiempo sino acordarse de que hay que hacerlo.
- **Puente de puerto 8081→80** dentro del contenedor (15 líneas de Node con `net.createServer`): hace
  falta porque `APP_URL` es `http://localhost:8081` y de ahí salen las cookies, las URLs absolutas y la
  vuelta de Redsys. Sin él, la vuelta apunta a un puerto que dentro no existe.
- **`reducedMotion: 'no-preference'`** en el contexto: headless declara `reduce` y `celebrate()` se salta
  el confeti con esa preferencia — sin eso se mide la ausencia como si fuera un fallo.

⚠️ **Lo que el andamio aprendió de la pasarela, y ahorra una hora:**

1. **El botón «Pagar» solo se habilita si se rellena el TITULAR.** Con la tarjeta perfectamente escrita
   sigue `disabled`, y no hay ningún mensaje que lo diga.
2. Hay que **TECLEAR** (`pressSequentially`), no `fill()`: la pasarela rellena sus campos ocultos desde
   eventos de teclado reales.
3. El sandbox mete **siempre** un **simulador EMV 3DS** por medio, con tres salidas (éxito / denegar /
   cancelar por el titular).
4. ⚠️ **Denegar el 3DS NO devuelve al comercio**: vuelve al formulario de tarjeta pidiendo otra. El KO se
   provoca **cancelando** en la pasarela.
5. Tras pagar hay un **resguardo** con un botón «Continuar»; no hay redirección automática.
6. **Cada recorrido abortado deja un pedido pendiente**, y a los cinco el tope de pendientes deniega el
   checkout: parece un fallo del andamio y es la app haciendo lo correcto. Hay que caducarlos entre casos.
7. El catálogo tarda más en la SPA (chunk + tres peticiones): **esperas por CONDICIÓN, no por reloj**.
8. Varios recursos de la pasarela dan **404 en su propio sandbox** (`999008881-1-ni.js`, algunos PNG) y
   su página lanza `$ is not defined`. Es ruido suyo, no nuestro: no lo cuentes como error de la app.

## 5.ter · LA SESIÓN DE STAGING (2026-08-20) — guion en bloque

> `ENTORNOS.md` §5: **staging se toca EN BLOQUE, con guion escrito, no a goteo.** Esto es ese guion.
> Estado de partida, medido y dejado listo el 2026-08-20: commit `9fc8922` desplegado ·
> **`sidebar.engine = spa`** (el cajón que se sirve ES el nuevo) · anti-bot ACTIVO con las claves de
> `jumpwebtest`, cuyo hostname es `jumpweb.sites.aelium.app` · `redsys_merchant_url` configurada ·
> `redsys_environment = test` · correo en `log` (NO sale) · catálogo neutro con 1440 franjas.
> Admin: `yasi09265@gmail.com`.
>
> ⚠️⚠️ **CADUCADO — NO INTENTES ESTO.** Esta sesión decía «si algo se tuerce, el flag es la marcha
> atrás: `app:set-setting sidebar.engine livewire`». **Ese flag ya no existe** (`#112` lo retiró con
> el componente), así que ese comando no te va a salvar: te va a hacer perder el tiempo justo cuando
> menos lo tienes. **Hoy la marcha atrás es desplegar un commit anterior** — es exactamente lo que
> `#100` avisó que pasaría al borrar el motor. Se deja escrito, tachado, en vez de borrarlo: quien
> recuerde el truco tiene que encontrar aquí por qué ya no vale.

> ✅ **RECORRIDO ENTERO EL 2026-08-20 por el owner, y las seis en verde** (`DECISIONES #110`). Lo que
> sigue se conserva como RECETA —es la que se repetirá en cada instalación de cliente—, no como
> pendiente. Corroborado en BD: 2 pedidos en `paid`, 16 entradas, y la señal ejercitada (30,00 € sobre
> totales de 151,20/127,20).
> ⚠️ **Con un límite que hay que saber**: `paid` **no distingue** el S2S del retorno del navegador, y
> no hay access log accesible al usuario del sitio. Ver `#110(c)`.

### T1 · El widget del anti-bot DENTRO del cajón
1. Abre la web **en ventana de incógnito** (sin sesión) y abre el cajón.
2. Llega al paso de identificarse y pulsa la pestaña **«Crear cuenta»**.
   ▶ **Antes delegaba en el modal de la cabecera; ahora NO debe.** Si se abre el modal, la delegación
   no se retiró y hay regresión.
3. **Mira que el widget se pinte dentro del cajón** y complete su comprobación.
   ⚠️ Si aparece un hueco vacío: abre la consola. El módulo avisa (`[turnstile] no cargó …`) — es el
   único sitio donde ese fallo deja rastro: `Turnstile::verify('')` corta antes del POST a Cloudflare
   y antes de su log, así que **no habrá nada en `storage/logs` ni en el panel de Cloudflare**.
4. Da de alta una cuenta nueva → debe crearse y seguir el flujo.

### T2 · El RESET del widget — el caso que ningún test puede ver
Es el más importante de la sesión, porque el reset está probado **con dobles, no contra Cloudflare**.
1. En el alta, escribe **un correo que YA exista** (p. ej. el del admin) y envía.
2. Debe salir el aviso de que esa cuenta ya existe.
3. **Corrige el correo por uno nuevo y vuelve a enviar SIN recargar la página.**
   ▶ **Debe funcionar.** Si sale «no eres un robot» con el tick verde puesto, el reset no está
   surtiendo efecto contra el proveedor: el token es de un solo uso y `SelfSignup` lo quema **antes**
   de comprobar si el correo existe.

### T3 · La notificación S2S (bloque B, ahora sin túnel)
La URL ya está configurada, así que el bloque B de §3 se puede recorrer **contra staging directamente**,
sin `cloudflared`. Sigue esa receta y comprueba que el pedido llega a `paid` por la notificación.

### T4 · 3DS con challenge
Tarjeta `4548 8172 1249 3017`. El flujo del cajón no cambia —se encarga la pasarela—, pero nadie lo ha
recorrido nunca.

### T5 · Móvil real
El cajón del nav y el de compra se superponen en pantallas pequeñas, y eso solo se ve en un móvil.

### T6 · Los tres idiomas en vivo
Los textos ya se comparan palabra por palabra en las paridades; aquí solo hay que ver que el cambio de
idioma no rompe el cajón. Verificado por HTTP el 2026-08-20 que `/lang/{es,en,fr}` cambia el
`<html lang>`; falta recorrer el cajón en uno que no sea `es`.

⚠️ **Todo hallazgo vuelve al repo** (`ENTORNOS.md` §5): como TEST si se puede testear; si no —navegador,
pasarela, móvil—, como receta escrita aquí con su trampa medida.

## 5.quater · LA SESIÓN DE STAGING (2026-08-21) — las DOS que nadie ha visto

> Estado de partida, **medido tras desplegar**, no supuesto: commit **`1977db7`** desplegado el
> 2026-08-21 · las 6 comprobaciones de salud de `deploy.sh` en verde · `redsys_environment = test`
> (guarda 1 ✓) · correo en `log` (guarda 3 ✓) · `robots.txt` con `Disallow: /` verificado por HTTP
> (guarda 4 ✓) · nada pendiente de migrar · 1440 franjas.
>
> 🟩 **Y una diferencia de fondo con la sesión anterior: el cajón SPA es ya el motor ÚNICO también
> AQUÍ.** Este despliegue se llevó `Purchase.php` del servidor (verificado por `ls`). No hay flag, no
> hay segundo motor y **no hay marcha atrás que no sea desplegar un commit anterior**.

Son **DOS**, las dos visuales, y las dos existen porque `#100` no las incluyó entre sus cuatro
caminos: se desplegaron rotas y nadie lo vio. **El DoD (`CONVENCIONES §3.bis`) dice que una feature
visible no es ✅ hasta que el owner la valida**, así que `4.7` no cierra hasta esto.

⚠️ **Hazlas en ventana de incógnito**: el cajón persiste la cesta y el estado, y una sesión sucia
puede enseñarte un cajón que ya está en el paso 4 y hacerte creer que el enlace profundo funcionó.

> ✅ **EJECUTADA el 2026-08-21 con navegador headless** (receta abajo). Resultado, sin adornos:
> **V1 PASÓ · V2 FALLÓ y destapó que la funcionalidad nunca se había cableado** (`DECISIONES #117`),
> se arregló y se re-verificó. Lo que sigue conserva el guion porque se repetirá en cada instalación.
>
> ⚠️ **Sigue pendiente el OJO DEL OWNER** (`CONVENCIONES §3.bis`): un navegador headless mide, no
> valida. Lo que aporta es que ya no vas a *descubrir* nada, solo a confirmarlo.
>
> ⚠️⚠️ **Y una limitación del ENTORNO que hay que saber antes de repetirlo**: staging tiene 4
> productos, **todos `pack` y ninguno `entry`**, así que el catálogo pinta **una sola sección**. Eso
> deja **dos cosas sin poder verificarse allí**: el enlace profundo de ZONA (no hay sección
> «Entradas» donde aterrizar) y el efecto VISUAL del de packs (desplazarse a la única sección es un
> no-op). Se verificaron por el valor devuelto por el cableado, no por la pantalla.
>
> **El andamio, contra staging, es más simple que en local**: es una URL pública HTTPS, así que
> **sobra el puente de puertos** de §5.bis. Dentro del contenedor (que ya trae las librerías de
> Chromium — verificadas las seis):
> `mkdir -p /home/sail/e2e && cd /home/sail/e2e && npm i playwright && npx playwright install chromium`.
> ⚠️ **Fuera del árbol del proyecto a propósito, y no solo por peso**: `deploy.sh` no excluye una
> carpeta nueva en la raíz, así que ahí dentro **se subiría a staging en el siguiente `rsync`**.
> ⚠️ `networkidle` **no vale** en estas páginas (hay actividad continua): esperar por CONDICIÓN
> —«el catálogo tiene contenido y no es el estado vacío»—, que es la trampa 7 de §5.bis.

### V1 · Los ICONOS (`DECISIONES #113`)
Se servían los **20 vacíos**. Está medido a los dos lados, así que aquí solo falta el ojo:
el bundle de antes tenía **0 geometrías** en 33 `<svg>`; el que se sirve ahora tiene **40** en 42, y
la diferencia son **7.441 bytes** — que cuadra con los 7,26 KiB que midió `#113(d)`.

1. Abre `https://jumpweb.sites.aelium.app/entradas` → el cajón abre en el catálogo.
2. **Mira que cada tarjeta y cada botón lleven su dibujo**, no un hueco.
3. Recorre día → hora → cantidad → complementos → carrito mirando los iconos de cada paso.
   ▶ Lo que buscas es un icono **en blanco**: el `<svg>` se pinta y ocupa su sitio, así que el
   síntoma no es un roto, es un **hueco** donde debería haber un dibujo.

✅ **MEDIDO el 2026-08-21 · 3 pantallas (catálogo, fecha, hora) · 16 `<svg>` · 0 vacíos · 0 sin
geometría · 16 visibles**, y confirmado además MIRANDO la captura: se ven el icono de la sección, el
de cada pack, las flechas de fila, el de iniciar sesión y el pin. El embudo se cortó en «hora» porque
el pack elegido no tenía horas seleccionables ese día — **no es un fallo, es el dato de staging**, y
deja los pasos 4–6 sin censar.

### V2 · A7 · Los ENLACES PROFUNDOS (`DECISIONES #111(h)`)
**No son URLs: son tres botones de la landing** que abren el cajón *pidiendo algo concreto*. El bug
era que el adaptador de intención de Livewire se registraba sin mirar el motor y `flushIntent()`
**consume** la intención antes de aplicarla, así que se la comía y despachaba a un componente que ya
no se renderizaba. Resultado: los tres abrían el cajón **en el catálogo raíz**.
Medido en el bundle: las referencias a `Livewire` en `app.js` pasaron de **6 a 3**.

| Dónde | Qué pulsar | Qué DEBE pasar |
|---|---|---|
| `/` (home) | el CTA de una tarjeta de zona (hay tres: `jump`, `kids`, `cumpleanos`) | el cajón abre **en las entradas de ESA zona** |
| `/servicios` | el botón de «ver packs» | el cajón abre **en la sección de packs** |
| `/cumpleanos` | el botón de «ver packs» de la sección de eventos | el cajón abre **en la sección de packs** |

▶ **El fallo se ve solo si sabes qué mirar**: el cajón SÍ se abre en los tres casos. Lo que estaba
roto es **dónde** abre. Si aterrizas en el catálogo raíz en vez de en la zona o en los packs, la
regresión sigue viva.

⚠️⚠️ **RESULTADO 2026-08-21: FALLÓ, y por un motivo peor que una regresión** (`DECISIONES #117`).
Medido en vivo: tras pulsar el CTA, con el cajón abierto y el catálogo cargado,
`machine.takeIntent()` **seguía devolviendo `{type:'packs'}`** — nadie la había consumido. La costura
llegaba hasta `queueIntent()` y ahí moría: `takeIntent()` no lo llamaba nadie en producción, solo
`machine.test.js`. `#111(h)` quitó el adaptador que se COMÍA la intención, pero **aplicarla nunca se
transcribió a la SPA**.
✅ **Arreglado y re-verificado el mismo día** (`intent.js` + `SidebarIntentWiringTest`):
`{applied:true, anchor:'catalog-sec-services'}` y la intención ya consumida.
⚠️ **La mitad de ZONA sigue abierta**: `catalog.js::toItem()` descarta el campo `zone`, así que el
cajón aterriza en «Entradas» pero **no en la zona pedida**. Se devuelve marcado `exact:false` en vez
de fingir paridad; cerrarlo toca el manifiesto de árbol congelado y es una decisión, no un parche.
⚠️ **Y el seed de staging no lo pinta**: en `/` y `/servicios` los CTA viven dentro de un `@if`
(`isPurchasable`, «pack vendible + zona operativa») que los 4 packs del seed no cumplen, así que solo
`/cumpleanos` tiene botón. Para probar los tres hace falta un catálogo más completo.

⚠️ **Y por eso este caso es el que enseña algo**: un camino que «no falla, no hace nada» es invisible
para cualquier gate y para un ojo que no sepa qué esperaba. Es la misma forma de fallo que los iconos
vacíos, y las dos se colaron por el mismo sitio — cuatro caminos verificados y estos dos fuera.

### V4 · EL NIVEL SECCIÓN (área de cliente · tanda 1 · paso 1, `specs/area-cliente.md`)
El cajón deja de tener una sola sección. **Su red es ésta y solo ésta**: `render-sidebar.mjs` no
importa la raíz, así que el contrato de árbol **no ejerce el orquestador** (`DECISIONES #119(g)`).

| Qué | Cómo se comprueba | Por qué importa |
|---|---|---|
| Conmutar y volver | `spaHandle.showAccount()` / `showPurchase()` desde la consola | En el paso 1 el botón **no está cableado a propósito**: la sección de cuenta es su armazón, y una puerta a una habitación vacía es peor que ninguna |
| **Memoria del embudo** | avanzar a la fecha, ir a la cuenta, volver | Volver debe dejar el paso y la cesta como estaban. Es lo que decidió `v-show` frente a `v-if` |
| **Cero peticiones al conmutar** | contar `/api/v1` durante ida y vuelta | Un remontaje repetiría las cinco cargas del embudo y podría reabrir un desenlace ya visto |
| **El bloque `.acct` colapsado** | tabular de verdad hasta él | `is-account` lo colapsa igual que `is-booking`/`is-cart`. Vive FUERA del cajón: **ningún diff de árbol lo ve** |
| **La cadena flex** (§4.9) | `.purchase__scroll` con `overflow-y: auto` y altura > 0 | Un envoltorio de más la parte **sin que falte una clase**, y el propio CSS avisa de que no hay test que lo vea |
| **El árbol oculto no atrapa el foco** | contar focusables del calendario y recorrer el panel con Tab | Es el riesgo que se le atribuyó a `v-show` al elegirlo |

✅ **MEDIDO el 2026-08-22 en navegador, todo en verde:**
· catálogo → `purchase` · `is-catalog` · bloque de cuenta visible y alcanzable con Tab;
· fecha → paso **2** · `is-booking`;
· cuenta → `account` · **`is-account`** · `.acct` a altura 0, `visibility: hidden` y **no alcanzable
  con Tab** · la compra sigue en el DOM con `display: none` · `.purchase__scroll` de **841 px** con
  `overflow-y: auto` · título «Mi cuenta» y botón de volver presentes;
· vuelta → `purchase` · **paso 2 intacto** · `is-booking`;
· **0 peticiones a `/api/v1`** en toda la ida y vuelta.

⚠️⚠️ **Y el dato que zanjó una duda de diseño**: el calendario tiene **12 elementos focusables**
cuando la compra está visible y **0 cuando está oculta**; el recorrido del tabulador por el panel
entero solo pasa por la sección de cuenta y el botón de cerrar. **Cero fugas de foco.** El riesgo que
se le atribuyó a `v-show` —«deja dos árboles dentro del mismo `role="dialog"`»— **no existe**:
`display: none` saca del árbol de accesibilidad y del orden de tabulación. Se midió en vez de
suponerlo, en las dos direcciones.

⚠️ El primer intento de esta verificación dio un **falso rojo**: contaba `offsetWidth > 0` como
«tabulable», y un bloque colapsado con `grid-template-rows: 0fr` conserva el tamaño intrínseco de sus
hijos. **El criterio estaba mal, no la app.** Queda escrito porque es la trampa que hace perder media
hora: para accesibilidad se mide **tabulando de verdad**, no midiendo cajas.

### V5 · LA NAVEGACIÓN DE ZONAS (área de cliente · tanda 1 · paso 2)
El área tiene índice y zonas, y **su modelo de navegación no es el del embudo**: se navega libre, con
pila de retorno. Lo que aquí se mira es que la pila **haga lo que dice** en un navegador de verdad.

| Qué | Qué DEBE pasar |
|---|---|
| Entrar al área | índice «Mi cuenta», con la fila «Mis reservas» y **sin** «volver» dentro del área |
| Pulsar la fila | zona «Mis reservas» con su estado vacío, y ya **sí** hay «volver» |
| «Volver» | al índice, **sin salir** de la sección |
| «Volver» otra vez | **sale a la compra**, y el embudo sigue en su paso |
| Entrada DIRECTA (`showAccount('orders')`) | aterriza en la zona, y el **primer** «volver» ya sale: no se inventa un índice que nadie visitó |
| Reentrar | la historia está **vacía**: no se arrastra el recorrido de la visita anterior |

✅ **MEDIDO el 2026-08-22 · 7/7 · 0 peticiones a `/api/v1` en todo el recorrido.** Con el embudo en el
paso 2 antes de entrar y en el paso 2 al volver.

⚠️ **La trampa del andamio, que costó una ejecución**: `.bk-back` **existe DOS veces en el DOM** —el
de la banda de progreso de la compra oculta y el de la cuenta—, así que `locator('.bk-back').first()`
apunta al que está en `display: none` y el clic caduca a los 30 s. **Hay que acotar al visible**:
`.purchase:visible .bk-back`. No es un fallo de la app —lo oculto no lo ve ni el cliente ni el
tabulador (V4)— pero sí de cualquier script que no lo sepa.

### V6 · «MIS RESERVAS» CON DATOS REALES (área de cliente · tanda 1 · paso 3b)
La zona pinta lo que el servidor publica. Lo que aquí se mira es que **el valor LLEGUE** — la familia
de fallos de `#117`, `#118` y `#119(f)`: piezas verdes por separado y el medio sin cablear.

⚠️ **Hace falta un pedido en la BD de desarrollo, que viene VACÍA de pedidos.** Se siembra con tinker:
un cliente verificado, un pedido `paid` con un **pack con `guest_fields`** (si no, no hay post-form),
un complemento anidado y un **`OrderAdjustment` de tipo `deposit_remainder`** (la señal no es un campo
del pedido: es un ajuste por línea). ▶ **Y la franja tiene que ser FUTURA de verdad**: con una de hoy
cuya hora ya pasó no hay «próxima reserva» ni aviso de señal —`aCobrarPuerta` pasa a `cobradoPuerta`—
y se lee como si el código fallara. Costó una ejecución.

✅ **MEDIDO el 2026-08-22 · 2/2**, con sesión abierta por `POST /api/v1/auth/login` desde la propia
página (cookie y CSRF los pone el cliente, como en producción):
· **índice** → «Mi cuenta» · próxima reserva «Mié. 26 ago. · 10:00–12:00 · Cumpleaños Jump» · la fila
  «Mis reservas» con su contador;
· **zona** → 1 tarjeta · `DEMO-0001` · «Completado» · «Mié. 26 ago. · 10:00–12:00» · **98,00 €** ·
  **«Señal 68,00 € · 30,00 € en el parque»** · «Completa el formulario de Cumpleaños Jump» enlazando a
  `/reserva/3/datos-invitados`;
· peticiones: **exactamente dos** —`GET /me/reservations` y `GET /me/orders?page=1`—, cada una en su
  zona y ninguna repetida.

⚠️ **El 68,00 € no es un error de redondeo y lo predijo la paridad**: `paid_online_cents` es lo pagado
online **de la reserva completa** —la señal de 60,00 más los 8,00 del complemento—, no solo la señal.
El contrato lo dice y `SidebarAccountParityTest` lo fijó antes de verlo en pantalla.

⚠️ **Y una trampa de accesibilidad que solo se ve mirando**: el primer intento puso en el `sr-only` del
contador el subtítulo de la zona, y un lector de pantalla leía «Mis reservas 1 Aquí tienes tus reservas
y su estado» — que no dice qué es ese 1. Hoy usa la misma clave que el bloque `.acct`. ⚠️ Esa clave no
tiene forma plural, así que dice «1 reservas próximas»: es **preexistente** y se reproduce a propósito
(paridad). Ficha en `DEUDA.md`.

### V7 · LA PUERTA del área de cliente (tanda 1 · paso 4)
El botón «Mis reservas» del bloque de cuenta deja de navegar y abre la sección dentro del cajón. La
cadena cruza tres piezas y **dos tecnologías** —Blade/Livewire → Alpine → el motor Vue—, que es
justo la forma de fallo de `DECISIONES #117`: extremos probados y el medio sin cablear.

| Qué | Qué DEBE pasar |
|---|---|
| Con sesión, pulsar «Mis reservas» | abre el área en la zona **sin cambiar la URL**, con la lista pintada y el bloque `.acct` colapsado |
| **Con el motor CAÍDO** | el enlace **navega** a `/mi-cuenta/pedidos`, como siempre |

✅ **MEDIDO el 2026-08-22 · 2/2:**
· la URL se queda en `/entradas`, la sección pasa a `account`, la zona a `orders`, se pinta 1 tarjeta
  y el bloque de cuenta queda a **altura 0**;
· y **cortando el chunk del motor** con `route("**/assets/sidebar-*.js", abort)` —que reproduce la
  ventana real entre abrir el panel y que el `import()` acabe—, el clic **navega a la página**.

⚠️ **Esa segunda mitad es la que de verdad valía la pena medir.** El `href` del enlace parece
decorativo cuando hay un `x-on:click` al lado, y quitarlo no rompe ningún test de conducta: solo deja
un botón que, mientras el chunk carga, **no falla y no hace nada**. Bloquear el chunk lo demuestra en
vez de suponerlo.

### V8 · LAS GESTIONES DE CREDENCIALES (tanda 2 · paso 6b)
Cambiar la contraseña y cerrar las demás sesiones, desde el cajón.

⚠️⚠️ **Restaura la contraseña del usuario de pruebas ANTES de cada pasada, desde el SERVIDOR**:
`User::where('email','cliente.demo@jumpweb.test')->first()->update(['password' => 'password'])`.
El recorrido la cambia de verdad, y hacerlo desde el propio script no basta —si algo falla antes, la
restauración no llega—. **Costó dos ejecuciones en falso**, y el síntoma engaña: sin poder iniciar
sesión, los textos del área **no viajan** (solo van CON sesión, §9) y **todo sale vacío**, como si el
paso estuviera roto.

✅ **MEDIDO el 2026-08-22 · 5/5 + 3/3:**
· índice con **tres** entradas (reservas · contraseña · sesiones);
· contraseña actual equivocada → «La contraseña actual no es correcta.» **bajo su campo**;
· las dos copias de la nueva no coinciden → «La confirmación de Nueva contraseña no coincide.» y
  **cero peticiones**: se corta antes de salir;
· cambio correcto → el formulario **se vacía solo**;
· sesiones: contraseña mal → error; bien → se vacía y **`GET /me` sigue devolviendo 200**.

⚠️ **Ese `200` es lo que de verdad valía la pena mirar**: revocar «las demás» credenciales tiene que
conservar la propia (`RGPD-06`). Si el titular se autoexpulsara al defenderse, el fallo se vería como
un cajón que de pronto pide entrar — y el test de servidor no lo distingue, porque allí la credencial
de la petición no es una cookie de navegador.

⚠️ **Y una trampa de aserción que dio verde en falso a la primera**: el caso de «las copias no
coinciden» solo comprobaba que **hubiera** error, y lo había — el del intento anterior, que seguía en
pantalla. Se arregló en las dos puntas: la zona **limpia lo que dijo el servidor** al cortar el envío,
y el caso exige ahora el texto exacto. Mirar «hay un error» nunca distingue el nuevo del viejo.

### V9 · «TUS DATOS» y el ciclo del correo (tanda 2 · paso 7b)
El perfil del titular dentro del cajón, con lo que de verdad hay que mirar: que **cambiar el correo
NO lo cambia**.

⚠️ Restaura el estado del usuario de pruebas antes de la pasada, **desde el servidor** (misma trampa
que `V8`): contraseña `password`, sin `pending_email` y con sus datos.

✅ **MEDIDO el 2026-08-22 · 7/7:**
· índice con **cuatro** entradas · «Tus datos» con el perfil cargado y el selector con los tres
  idiomas **por su nombre nativo** (`Español`, `English`, `Français`);
· cambiar el teléfono **no pide contraseña** y guarda;
· cambiar el correo **sí la pide** —el campo aparece solo entonces— y al confirmar deja el bloque:
  «Te hemos enviado un enlace de confirmación a **otro.demo@…**. Caduca en **60 min**. Mientras tanto,
  sigues usando **cliente.demo@…**»;
· ⚠️⚠️ **y el campo de email vuelve a mostrar el VIGENTE**, no el pedido: es la señal visible de que
  el cambio no se ha aplicado, y de que el titular no ha perdido el acceso a su cuenta;
· cancelar quita el bloque.

⚠️ El mensaje del pendiente lleva **los tres datos a propósito** —a dónde se envió, cuánto queda y
cuál sigue valiendo—. Sin el tercero, el cliente puede creer que su correo ya cambió y que se ha
quedado fuera.

### V10 · PRIVACIDAD Y DATOS (tanda 2 · paso 8)
Los dos derechos RGPD dentro del cajón: descargarse los datos (art. 20) y **borrar la cuenta**
(art. 17). Cierra la tanda 2.

⚠️⚠️ **El caso del borrado necesita un titular DESECHABLE, y esto no es una comodidad**: anonimizar
es irreversible, así que ejecutarlo contra `cliente.demo@` deja el entorno sin el usuario de `V6`,
`V8` y `V9`. Se crea uno nuevo desde el servidor **antes de cada pasada**:
`User::create([...'email' => 'borrame.demo@jumpweb.test'...])` con `email_verified_at`.

⚠️ **Y hay que aceptar el diálogo nativo**: el borrado lleva `window.confirm`, y Playwright los
**descarta** por defecto — sin `page.on('dialog', d => d.accept())` el caso da verde sin haber
borrado nada. Es la trampa gemela de `reducedMotion`.

✅ **MEDIDO el 2026-08-22 · 17/17:**
· índice con **cinco** entradas (reservas · tus datos · contraseña · sesiones · **privacidad**);
· la zona pinta los dos derechos y la advertencia de que el borrado no se puede deshacer;
· descarga → fichero `mis-datos-2026-08-22.json`, con el perfil y los pedidos dentro, y **una sola
  petición** a `GET /api/v1/me/export`. La pantalla confirma **qué** se descargó;
· contraseña mal → error bajo su campo, `GET /me` sigue en **200** y el campo **conserva lo escrito**
  (para poder corregir, como en las otras tres pantallas);
· contraseña bien → sale a `/`, `GET /me` pasa a **401** y se ve el aviso de despedida.

⚠️⚠️ **Lo que este recorrido encontró y ninguna suite vio**: tras borrar desde el cajón **la home
salía MUDA**. La web termina en `redirect('/')->with('status', …)` y el layout pinta ese aviso; el
cajón sale a `/` por su cuenta, así que la despedida hay que dejarla en la sesión NUEVA —después de
`invalidate()`, que la vacía—. Hoy tiene caso propio y su mutación lo tumba.

⚠️⚠️ **Y una trampa del ANDAMIO que costó una pasada**: el caso del borrado esperaba
`waitForURL('/')` **estando ya en `/`**, así que la espera se cumplía al instante y la comprobación
siguiente medía el estado de ANTES —dos rojos que parecían de la app y eran del guion—. Se entra
desde `/entradas` para que la navegación sea real. Es `DECISIONES #115` dentro del propio andamio:
*una comprobación que mide una cosa y se lee como otra es peor que no tenerla*.

### V11 · EL LEDGER FINANCIERO Y LOS CONSENTIMIENTOS (tanda 3 · pasos 10 y 11)
Lo que hay que mirar aquí **no es que se pinte algo**, sino que el cajón diga los MISMOS números que
`/mi-cuenta/pedidos`: mientras las dos superficies convivan, una divergencia es dinero mal contado en
pantalla.

⚠️ **Necesita un pedido que ejercite el ledger entero**, y montarlo tiene una trampa medida: la franja
del pedido tiene que ser **FUTURA**. Con una de hoy que ya terminó, los ajustes cuentan como
**resueltos** —cobrados en recepción— y el desglose sale VACÍO sin que nada falle. Receta:
un pack con `deposit_remainder` y `extra_due`, una línea **cancelada**, una segunda línea viva y una
fila de `payments` **pagada** (sin ella, «pendiente de devolución» vale 0 contra cualquier pedido).

✅ **MEDIDO el 2026-08-22 · 22/22:**
· subtotal 133,00 € · pagado online · a cobrar en el parque **+48,00 €** · pendiente de devolución
  **−23,00 €** · **Total 110,00 €** —distinto del facturado, que es lo que el campo existe para decir—;
· el desglose entra **plegado** y al abrirlo salen las **dos** líneas, la segunda con su producto
  («Resto de la señal de Cumpleaños Jump +31,00 €»);
· la **página** dice los mismos seis importes;
· la zona de privacidad lista los **dos consentimientos** con su nombre, su fecha y su versión — y
  **sin la IP**.

⚠️⚠️ **Y otra trampa del ANDAMIO, la segunda de la fase**: el guion buscaba `.orders__card` —la clase
del **cajón**— dentro de la **página**, que usa `.orders__item`. Son dos marcados distintos **a
propósito** (§1.3: aquí la paridad es de DATOS, no de árbol), y confundirlos dio seis rojos que
parecían una divergencia de importes. **Al comparar dos superficies, cada una con su selector.**

### V12 · LAS PUERTAS (tanda 3 · la retirada)
Muere la vista, vive la ruta. Lo que hay que ver aquí no es que la página ya no exista —eso lo dice la
suite— sino que **quien llega desde uno de los 8 correos ya entregados acaba delante de su pantalla**.

✅ **MEDIDO el 2026-08-22 · 13/13:** `/mi-cuenta/pedidos` responde 200, el cajón se abre **solo** y
aterriza en «Mis reservas» · `/mi-cuenta` abre el **índice** con sus cinco entradas · al cerrar y
reabrir **no vuelve a saltar** a la cuenta (la señal se consume) · la home no abre nada · `/entradas`
sigue abriendo la **compra** · la descarga RGPD sigue respondiendo 200.

⚠️⚠️ **Y aquí volvió a caer el fallo de siempre, en el mismo camino**: la zona se aplicaba en
`open()`, y **el cajón que llega por una puerta NACE ABIERTO**, así que `open()` no se llama nunca —
el cliente aterrizaba en el índice en vez de en sus reservas—. Es literalmente el camino de `#59(b)`,
**documentado en el propio `app.js` tres párrafos más abajo de donde estaba el fallo**. Hoy se aplica
en `bootSpaEngine()`, por donde pasan los dos caminos.
▶ **La lección**: leer el aviso no es aplicarlo. Estaba escrito y aun así se repitió.

⚠️ **Trampa del andamio**: hay que esperar por el MOTOR (`spaHandle.section.onAccount`) y no por el
marcado — la sección de compra sigue en el DOM con `v-show`, así que sus selectores resuelven aunque
la cuenta no haya montado. Costó una pasada.

### V13 · LAS RESPUESTAS DEL PACK, BAJO DEMANDA (tanda 3 · cierre)
Son datos de un MENOR (art. 9), así que el caj��n las pide **solo al desplegarlas**.

✅ **MEDIDO el 2026-08-22 · 6/6:** al listar **no están en pantalla** y **no se ha pedido** el endpoint
· la tarjeta ofrece el despliegue solo si hay un pack · al abrirlo llegan con su etiqueta («Nombre del
homenajeado/a: …») y **entonces** se pide `GET /orders/{code}/event-data` · replegar y volver a abrir
**no repite la petición**.

⚠️ Para montarlo hace falta un pedido con un pack que tenga campos de evento **contestados**: el
catálogo de demostración ya los trae, y la etiqueta que sale es la del panel, no la del fixture.

### V3 · Lo que se aprovecha estando dentro (opcional, pero barato)
Ya que hay una sesión abierta y el motor es otro:
- **Que el cajón entero siga vendiendo** con el motor único: catálogo → pagar → volver. `#110` lo
  verificó con el flag en `spa`, pero **con el componente Livewire todavía presente**; hoy no está.
- **Los tres idiomas** (`T6` sigue pendiente de recorrer el cajón en uno que no sea `es`).

⚠️ **Todo hallazgo vuelve al repo** (`ENTORNOS.md` §5): como TEST si se puede testear; si no
—navegador, pasarela, móvil—, como receta escrita aquí con su trampa medida.

## 6. Lo que este guion NO cubre, y hay que decirlo

- ~~**El 3DS con challenge**~~ ✅ recorrido el 2026-08-20 (`#110`).
- **Los tres idiomas en vivo**: los textos ya se comparan palabra por palabra en `es`/`en`/`fr` en las
  paridades; aquí solo se recorre el idioma activo.
- ~~**Móvil real**~~ ✅ recorrido el 2026-08-20 (`#110`).
- ~~**El widget de Turnstile**~~ ✅ recorrido el 2026-08-20 (`#110`): se pinta dentro del cajón y el
  RESET funciona contra Cloudflare real.
  ⚠️ **Lo que hay que mirar en el navegador, y NO lo cubre ningún test**: que el widget se pinte
  DENTRO del cajón (no solo en el modal de la cabecera), que el alta pase con él, y **que tras un
  fallo del alta el widget se REINICIE** — el token es de un solo uso y el servidor lo quema antes de
  comprobar si el correo ya existe, así que reenviar sin reset da «no eres un robot» con el tick verde
  puesto. El reset está implementado y probado con dobles, pero **contra Cloudflare real no lo ha
  visto nadie**.
