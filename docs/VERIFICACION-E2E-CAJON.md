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
| Anti-enumeración del alta | `SidebarRegisterParityTest` | no |
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
  WSL NO las tiene): `mkdir /root/e2e && cd /root/e2e && npm i playwright && npx playwright install chromium`.
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

## 6. Lo que este guion NO cubre, y hay que decirlo

- **El 3DS con challenge** (`4548 8172 1249 3017`). El flujo del cajón no cambia —la pasarela se encarga—,
  pero nadie lo ha recorrido.
- **Los tres idiomas en vivo**: los textos ya se comparan palabra por palabra en `es`/`en`/`fr` en las
  paridades; aquí solo se recorre el idioma activo.
- **Móvil real**: el cajón del nav y el de compra se superponen en pantallas pequeñas, y eso solo se ve en
  un móvil de verdad.
- **El widget de Turnstile** (4.4b·2): necesita claves de Cloudflare. Mientras no esté, con el anti-bot
  activo el alta del cajón montaba su widget desde 4.4b·2 (antes delegaba en el modal de Livewire).
  ⚠️ **Lo que hay que mirar en el navegador, y NO lo cubre ningún test**: que el widget se pinte
  DENTRO del cajón (no solo en el modal de la cabecera), que el alta pase con él, y **que tras un
  fallo del alta el widget se REINICIE** — el token es de un solo uso y el servidor lo quema antes de
  comprobar si el correo ya existe, así que reenviar sin reset da «no eres un robot» con el tick verde
  puesto. El reset está implementado y probado con dobles, pero **contra Cloudflare real no lo ha
  visto nadie**.
