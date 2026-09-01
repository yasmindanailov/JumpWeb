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
4. El **puente hacia Livewire**: `account-context` repintando tras el login del cajón. ⚠️ Los modales de auth estaban aquí hasta `DECISIONES #122` (2026-08-23): se retiraron, y hoy ese bloque es el ÚNICO componente Livewire del layout.
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

1. Pestaña 1: **con sesión** (Alice), añade algo al carrito — la cesta guarda su dueño.
2. Pestaña 2: cierra sesión, entra con OTRA cuenta (Bob) y recarga la pestaña 1.
   · **Mirar**: la cesta de la pestaña 1 se **purga**. Es la defensa que `localStorage` introdujo
     (`DECISIONES #38(d)`); si sobrevive, el titular cambió y la cesta no se enteró.
   ⚠️ **Corregido el 2026-08-27**: este paso decía «añade SIN iniciar sesión», y esa purga **no existe
   ni debe existir** — una cesta sin dueño se conserva sea quien sea el titular (es la fila «sin dueño
   | X | conservar» de `decideOwnership()`, el flujo principal del paso 3). La expectativa era
   inalcanzable tal como estaba escrita; se descubrió al leer la tabla contra el guion.
3. Y al revés: cesta de invitado + login del MISMO navegador → **la cesta SOBREVIVE**. Es el flujo
   principal, no un descuido.
4. ❗ **Con sesión, añade al carrito y navega a `/entradas` o a «Mi cuenta» por el pie** (el cajón nace
   abierto): la cesta **SOBREVIVE**. Hasta el 2026-08-27 se purgaba —2/2 medido en headless, mientras
   desde la home se conservaba 4/4—, porque el dueño solo lo fijaba `GET /me` y el arranque nacido
   abierto no lo pregunta; ahora se siembra desde el HTML antes de restaurar
   (`specs/menores-a-cargo.md` §9.9.6, unidad 0 de la tanda 4 de menores).

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
   ▶ **Antes delegaba en el modal de la cabecera; ahora NO debe.** ⚠️ Desde `DECISIONES #122` ese modal
   ya no existe, así que este caso solo puede fallar hacia el otro lado: que el widget NO se pinte.
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

---

## 5.quinquies · LA AUTH DENTRO DEL CAJÓN (2026-08-23) — guion de `DECISIONES #122`

> **Por qué existe este bloque.** La red de este trabajo **no es el diff de árbol**: el contrato
> congelado cubre los once pasos del embudo, y las zonas de la cuenta **no las monta**
> `scripts/render-sidebar.mjs`. Lo que aquí se recorre es exactamente lo que ningún test de este repo
> puede ver.
>
> ✅ **Validado por el owner el 2026-08-23** en dos pasadas: la primera con el modal aún presente (el
> login del cajón) y la segunda **ya sin él**. Lo que sigue se conserva como el guion completo — hay
> casos que esas dos pasadas no tocaron, y están marcados.
>
> ⚠️ **Todo en ventana de incógnito**, salvo donde diga lo contrario: la mitad de estos casos dependen
> de no tener sesión.

### V14 · LAS TRES PUERTAS por URL

1. Abre `/login`, `/registro` y `/recuperar-contrasena`, una a una.
   ▶ Cada una tiene que **servir la home con el cajón YA ABIERTO** en su zona: identificarse, crear
   cuenta y recuperar contraseña respectivamente.
   ⚠️ Si abre el catálogo de compra, la zona no llegó: mira `data-account-zone` en el `<body>`.
2. **Cierra el cajón y vuelve a abrirlo** desde el carrito.
   ▶ Tiene que abrir en la COMPRA. Si vuelve a la zona de auth, la señal no se consumió — es la
   trampa que 4.0a pagó con el desenlace del pago y `#120(u)` con la puerta por ruta.
3. Con SESIÓN iniciada, abre `/login`.
   ▶ Tiene que llevar al **índice de Mi cuenta**, no a un formulario de entrar.

### V15 · LOS CUATRO PUNTOS que abrían el modal

1. En la cabecera, pulsa **«Registrarse»**. ▶ Se abre el CAJÓN en la zona de alta. **No debe aparecer
   ningún modal** — si aparece, algo resucitó `$store.auth`.
2. Repite en **móvil**, desde el cajón de navegación. ▶ El menú se cierra y se abre el de la cuenta:
   los dos superpuestos no pueden quedarse a la vez.
3. **Clic central** sobre ese mismo botón. ▶ Abre `/registro` en una pestaña nueva. Es el `href` que
   sobrevive, y lo único que responde si el JS no ha cargado.
4. Abre el cajón y, en el bloque de cuenta, pulsa **«Iniciar sesión»** y **«Mis reservas»**.
   ▶ Los dos llevan a la zona de entrar **sin cerrar el cajón**. Si se cierra, se pierde de vista la
   cesta — que es justo lo que este trabajo arregla.

### V16 · RECUPERAR CONTRASEÑA, y su «volver» desde los TRES orígenes

1. Desde la zona de entrar, pulsa **«¿Olvidaste tu contraseña?»** → pide el enlace con un correo
   **que exista**. ▶ Pantalla «revisa tu correo». Comprueba que **el correo llega**.
2. Repite con un correo **que NO exista**. ▶ **La pantalla tiene que ser idéntica**: es `SEC-06`, y
   cualquier diferencia visible convierte esto en un oráculo de qué correos están registrados.
3. Insiste hasta agotar el limitador. ▶ El aviso sale **bajo el campo**, no en un banner.
4. **El «volver» de esta pantalla, desde sus tres orígenes** — es lo que más fácil se rompe:
   · desde la zona de entrar → vuelve a **entrar**;
   · desde `/recuperar-contrasena` → vuelve a **entrar** (se siembra por debajo);
   · desde el **paso 5 del embudo** → vuelve a **la compra, con la cesta intacta**.

### V17 · EL ALTA SUELTA y su «revisa tu correo» — ✅ **recorrido el 2026-08-23** (`DECISIONES #125`)

> ⚠️⚠️ **Y destapó el fallo que el punto 2 llevaba escrito.** Con la cuenta atrás en 30 s, cuatro
> reenvíos produjeron **DOS correos**: los pulsados a los 63 s y 123 s los tiró el limitador por
> CORREO del servidor (60 s) y el endpoint devolvió **202** en los cuatro, así que la pantalla siguió
> descontando «te quedan N». Arreglado subiendo la cuenta atrás a 60 s —el cooldown que ATA— y
> re-verificado: bandeja **1 → 2 → 3 → 4 → 5**, ni uno se pierde.

1. Crea una cuenta desde `/registro`. ▶ **Tiene que llegar el correo de verificación** y **NO** debe
   abrirse sesión: es `context: standalone`, al revés que el alta del embudo.
   ⚠️ Si entras directo sin recibir correo, el contexto se quemó otra vez y el alta suelta pasó a ser
   *pay-first* — un cambio de política que ninguna pantalla delata.
2. En «revisa tu correo»: el botón de reenviar **nace deshabilitado** con su cuenta atrás de 30 s.
   ▶ Espera y pulsa. **Comprueba que el segundo correo llega de verdad**: el endpoint responde 202
   aunque el servidor descarte el envío, así que esto solo se ve aquí.
3. Agota los cuatro reenvíos. ▶ El botón desaparece y sale el aviso de límite —y **el escape «¿ya
   tienes cuenta?» sigue estando**: quien agota los reenvíos es quien más necesita salir.
4. Pulsa el escape. ▶ Lleva a la zona de entrar.

### V18 · EL PASO 5 DEL EMBUDO sigue vendiendo — ✅ **recorrido el 2026-08-23**, 11/11

> «El más caro de romper», y no se rompió: entrar continúa la compra (paso 5 → 8 con la cesta
> intacta), el alta desde el embudo es *pay-first* (sesión abierta, **0 correos**, sigue al pago) y
> recuperar contraseña vuelve a la compra con su línea.

1. Con la cesta llena, llega al paso de identificarse y **entra**. ▶ La compra continúa donde estaba.
2. Repite creando cuenta desde ahí. ▶ **Pay-first**: abre sesión, **no** manda correo y sigue al pago.
3. Y desde ese mismo paso, ve a recuperar contraseña y vuelve. ▶ **La cesta sigue ahí.**

### V19 · Lo que se mira de reojo

- Que **no queda ni un `.modal`** de auth en el DOM de ninguna página.
- Que el **bloque de cuenta se repinta** tras entrar (saludo con el nombre), sin recargar a mano.
- Que tras entrar desde la cabecera se aterriza en **el índice de Mi cuenta con sus rótulos**, no con
  los títulos en blanco: es el bloqueante que la revisión de la spec destapó y el motivo de que el
  aterrizaje NAVEGUE en vez de quedarse.

## §5.sexies · EL BLOQUE DE CUENTA EN VUE — ✅ recorrido y validado por el owner (2026-08-23)

> `specs/account-context-vue.md`, `DECISIONES #123`. El bloque `.acct` dejó de ser Livewire.
> ⚠️⚠️ **Esto es lo único que puede decir que el cableado FUNCIONA.** El diff de árbol no ve nada de
> aquí: `render-sidebar.mjs` **no importa la raíz**, así que un `<Teleport>` colgado de ella le es
> invisible — y el `href`, el texto y el interior de un `<svg>` tampoco son atributos de contrato.
> Cliente de prueba en local: `cliente.demo@jumpweb.test` (una reserva futura y un formulario pendiente).

### V20 · El bloque, sus dos caras y su colapso

1. Sin sesión, abre el cajón. ▶ «Hola, saltador/a» con sus dos botones, y **entra deslizando**: ni un
   tirón, ni una franja crema vacía antes.
2. Con sesión. ▶ Avatar con la inicial, saludo con el nombre, la próxima reserva **con su fecha**, el
   aviso de formulario y el contador.
3. Avanza a día y hora. ▶ Se **pliega**, no desaparece de golpe. Vuelve al catálogo: reaparece.
4. Entra en «Mis reservas». ▶ Dentro del área también se pliega (ahí el bloque **sobra**: sus botones
   llevan a donde el cliente ya está).
5. Con el bloque plegado, **tabula**. ▶ No se puede llegar a sus botones. Es lo que `visibility:
   hidden` compra, y sin ello serían invisibles pero tabulables dentro de la trampa de foco.
6. Repite el punto 1 con **«reducir movimiento»** activado. ▶ Aparece **sin animación**, y eso es lo
   correcto — no es un fallo. (⚠️ El andamio headless declara `reduce` por defecto: §5.bis.)

### V21 · El repintado SIN recargar — el caso que justifica el endpoint entero

1. Sin sesión, llena la cesta y llega al paso 5. **Entra.**
2. **Vuelve al catálogo sin recargar.** ▶ El bloque saluda **por tu nombre**, trae la próxima reserva
   y **el aviso de formulario pendiente**. Es lo único que prueba que `enterWith()` avisa de verdad:
   ninguna guarda estática puede decirlo, y por eso este paso no es opcional.
3. Ahí mismo, **cierra sesión**. ▶ Sale de verdad y aterriza en la home.
   ⚠️ **Éste es el que daba 419 con el diseño anterior**: `session()->regenerate()` rota el `_token`,
   así que un `<form>` pintado por Vue leería el `<meta>` caducado. Se pide por la API a propósito.
4. Entra otra vez y abre «Mi cuenta». ▶ El índice trae **tus** reservas, no viene vacío: eso es
   `invalidate()` sobre el store del índice, que sin él seguiría con el «no hay nada» del invitado.

### V22 · El cajón que NACE ABIERTO — el camino que ya dejó un hueco vacío DOS veces

1. Con sesión, entra directo a `/mi-cuenta`. ▶ El cajón nace abierto en el área, y el bloque **no
   entra deslizando para plegarse acto seguido**: `--pending` y el modo `is-account` compiten en el
   mismo tick, y es el único sitio donde se ve.
2. Igual con `/login` sin sesión.
   ▶ Es el camino de `#59(b)` y `#120(u)`: el cajón que nace abierto **no pasa por `open()`**.

### El caso feo · SIN motor

1. Con sesión y el cajón cerrado, pon el navegador **sin conexión** y abre el cajón. ▶ Aparece **solo
   el «Cerrar sesión» servido**, y funciona. La consola dice por qué
   (`[sidebar] no se pudo cargar el motor SPA`).
   ⚠️ **Medido: `route('logout')` aparece UNA sola vez en toda la aplicación**, y es ese suelo. Sin él,
   un fallo de red deja al titular sin poder salir — y en un dispositivo compartido eso no es una
   molestia.

### V23 · EL PULIDO — ✅ **recorrido el 2026-08-23**, 22/22 (`DECISIONES #124`, `#125`)

> ⚠️⚠️ **El punto 6 salió ROJO y era un fallo real**: la zona de privacidad **no pintaba el spinner**
> —`ensureConsents()` no levantaba ninguna bandera, así que las tres ramas del `v-if/v-else-if` eran
> falsas a la vez y la tarjeta se servía vacía 2,6 s—. Arreglado en `#125` con una bandera propia.
>
> ⚠️ **Tres trampas del andamio, medidas aquí y que dieron tres falsos rojos.** `<KeepAlive>` deja el
> embudo montado, así que **`.purchase__scroll`, `.bk-back`, `.jj-spinner` y `.catalog__item` están
> DUPLICADAS** en el DOM y `querySelector` devuelve la del embudo, oculta o a 0×0: hay que filtrar por
> `offsetParent !== null` o por tamaño. Y **`.zone-tabs` colisiona con el conmutador de zonas de la
> landing** —cuatro pestañas en la home—: las del cajón son `.purchase__authtabs`.

> Cambia el ASPECTO de pantallas ya validadas, y **ningún test de este repo ve un cambio visual**.

1. **El bloque de cuenta**: tres botones en fila —«Ver mis reservas» · «Mi cuenta» · el icono de
   salir—, el de salir **sin texto y sin estirarse**. ▶ Pasa el ratón por encima: debe decir «Cerrar
   sesión». Con lector de pantalla, debe anunciarse igual.
2. **Cierra el cajón estando en «Mi cuenta» o en el paso de la fecha, y reábrelo.** ▶ El bloque
   **sigue plegado**. Era el fallo del modo del panel.
3. **Haz scroll dentro del cajón hasta el tope.** ▶ La página de detrás **no se mueve**. Pruébalo
   también en móvil, que es donde el bloqueo solo del `<body>` no bastaba.
4. **«Mis reservas»**: cada pedido en su **tarjeta** —borde, fondo y padding— y la paginación
   centrada. Antes salía todo plano.
5. **Privacidad**: las dos secciones en **tarjetas separadas**, la de borrar con su borde de peligro.
6. **Entra en cada zona**: mientras cargan debe verse el **spinner**, no una zona en blanco ni un «no
   tienes nada» falso.
7. **Índice de «Mi cuenta»**: un **icono** a la izquierda de cada una de las cinco entradas.
8. **Entrar / Crear cuenta**: el título sale **una sola vez**, y las pestañas ocupan **el ancho
   completo**. ⚠️ Mira también el **paso 5 del embudo**: comparte esa barra y ahora es ancho completo
   siempre (antes cambiaba con el tamaño de ventana).
9. **Crea una cuenta desde el cajón.** ▶ En «revisa tu correo» **no hay pestañas**: la salida es el
   enlace «¿ya tienes cuenta?». Antes, tocar la pestaña destruía la pantalla.

## §5.septies · LOS ARREGLOS DE `#125` — ✅ recorrido el 2026-08-23, 12/12

> Los tres cambios de `DECISIONES #125` que **ningún test de este repo puede ver**: un velo en vuelo,
> una geometría y una navegación percibida. El cuarto —el reenvío— se verifica en `V17`, porque su
> único oráculo es la bandeja.

### V24 · El velo de PRIVACIDAD

1. Con sesión, entra en «Privacidad y datos» **reteniendo `GET /me/consents`** (en el andamio,
   `page.route` con un retardo; a mano, estrangulando la red).
   ▶ Tiene que verse el **spinner**, no una tarjeta con solo su título.
2. Mientras carga, mira «Descargar mis datos» y el formulario de borrado.
   ▶ **No pueden estar bloqueados**: leer una lista de cortesía no es una gestión del titular.
   ⚠️ Es lo que se rompería si el velo colgara de `busy` en vez de su bandera propia.
3. Al llegar los datos: el velo se va y sale la lista de consentimientos.

### V25 · Las pestañas 50/50

1. Abre `/login`. ▶ Las dos pestañas miden **lo mismo** y su texto va centrado. Medido: 193 y 193 px
   de una barra de 400 (antes, 103 y 156).
2. Mira también el **paso 5 del embudo**: comparte el componente y tiene que verse igual.

### V26 · «Volver» desde «Crear cuenta»

1. En `/login`, pulsa la pestaña **«Crear cuenta»** y luego **«Volver»**.
   ▶ **Sale del área** —vuelve a la compra o al catálogo—, no cambia de pestaña.
   ⚠️ Si vuelve a «Entrar», o la siembra ha regresado o las pestañas han vuelto a `go()`. Son **dos**
   causas distintas del mismo síntoma y hay que mirar las dos.
2. Entra en frío a `/registro` y pulsa «Volver». ▶ Lo mismo: sale. Es el efecto secundario aceptado.
3. Entra en frío a `/recuperar-contrasena` y pulsa «Volver». ▶ **Aquí SÍ lleva a «entrar»**: no es una
   pestaña, es una pantalla aparte a la que se llega por un enlace de dentro. Si esto también saliera
   del área, la siembra se ha retirado de más.

## §5.octies · «MIS RESERVAS» POR RESERVA — ✅ recorrido el 2026-08-23, 16/16

> `specs/mis-reservas-por-reserva.md`, `DECISIONES #126`.
> ⚠️ **Necesita datos que el fixture no tiene.** `cliente.demo@` traía 4 reservas: no basta para ver
> paginación en ninguno de los dos ámbitos. Se sembraron 11 más (6 futuras y 5 pasadas) y quedan en la
> BD de desarrollo con códigos `DEMO-U*` / `DEMO-P*` — reutilízalas en vez de volver a sembrar.

### V27 · Una tarjeta por RESERVA, no por pedido

1. Con sesión, entra en «Mis reservas». ▶ **Cinco tarjetas**, cada una con **una** reserva y la
   referencia de su pedido debajo («Pedido DEMO-…»).
2. Busca `DEMO-LEDGER`, que tiene **tres** reservas. ▶ Tienen que salir **por separado** y en su sitio
   por fecha, no agrupadas en un bloque. Es el caso que motivó todo el trabajo.
3. Mira el orden: **de la más próxima a la más lejana**. Una reserva **sin fecha asignada** va arriba
   del todo.

### V28 · La paginación, y que el orden se sostenga ENTRE páginas

1. Pulsa «Siguientes». ▶ Otras cinco, y **ninguna repetida**.
   ⚠️ Que la última de la página 1 no sea posterior a la primera de la 2 es lo único que distingue
   esta solución de aplanar en el cliente: aplanando, el orden solo sería cierto dentro de una página.
2. Comprueba en la pestaña de red que pide `GET /api/v1/me/reservations/upcoming?page=N`.

### V29 · El historial, en su propia pantalla

1. Al final de la lista, **después de la paginación**, hay «Ver historial de reservas». Púlsalo.
   ▶ Pide `/me/reservations/past` y las tarjetas salen **atenuadas** (opacidad 0,72).
2. ▶ De la **más reciente a la más antigua**, y con su distintivo «Cancelada» o «Finalizado».
3. Pasa el ratón por una tarjeta, y luego **tabula dentro de ella**. ▶ Recupera opacidad plena en los
   dos casos: una tarjeta atenuada sigue siendo interactiva, y quien navega con teclado no pasa el
   ratón. Es la mitad que se olvida.
4. Pulsa «Volver». ▶ Vuelve a **«Mis reservas»**, no fuera del área: el historial es una zona y tiene
   su sitio en la pila.

### V30 · El pedido, bajo demanda

1. En cualquier tarjeta, pulsa **«Ver pedido»**. ▶ Pide `GET /api/v1/orders/{code}` **en ese momento**
   —no antes— y entonces aparece el ledger: subtotal, señal, a cobrar en puerta, devuelto, total final.
   ⚠️ Si el ledger ya estuviera ahí antes de pulsar, alguien lo ha metido en la lista: se repetiría
   tantas veces como reservas tenga el pedido, con importes que no cuadran con la tarjeta que los rodea.
2. **Un pedido a medio pagar**: su reserva tiene que estar en «Mis reservas» —no en el historial— y su
   botón de reintentar **fuera** del desplegable. Es el único camino que le queda al cliente para no
   perder la plaza, y esconderlo tras un clic sería enterrarlo.

## §5.nonies · EL WAIVER EN EL CAJÓN (2026-08-26) — guion de `DECISIONES #166` — ✅ **recorrido en headless el 2026-08-26** (`#169`) · ⬜ **pendiente del OJO del owner**

> ⚠️ Este bloque nació llamándose «5.sexies», **el mismo ordinal que el bloque de cuenta en Vue** de
> más arriba (validado el 23/08): dos familias de citas compartían el ancla con sentidos opuestos. Es
> `§5.nonies` desde el 26/08; las citas vivas se corrigieron (`DECISIONES #166` conserva la vieja).
>
> **Por qué existe este bloque.** El diff de árbol **no ve** la casilla del waiver: cuelga de un
> documento que en SSR no existe, así que el manifiesto congelado del alta sigue en verde con la
> casilla rota o sin ella. Y las zonas de la cuenta **no las monta** `scripts/render-sidebar.mjs`.
> Lo que aquí se recorre es exactamente lo que ningún test del repo puede ver
> (`specs/waiver-probatorio.md` §9.9). ✅ **Recorrido en navegador headless** (abajo, «Resultado») ·
> ⬜ **Pendiente del OJO del owner** (`CONVENCIONES §3.bis`: un headless mide, no valida).
>
> ✅ **El anti-bot ya no estorba** (`DECISIONES #171`, 2026-08-26 noche): la primera pasada en headless
> destapó que con claves de Turnstile el alta suelta de `/registro` **no terminaba** (el widget nunca
> se montaba: el formulario nace antes de que `/config` traiga la clave). Arreglado en `turnstile.js`
> y verificado en headless **con el anti-bot encendido** (script inyectado tras `/config`, token a
> 3,1 s, `201`). **Recorre el guion con la instalación como en producción**: claves puestas.
>
> ⚠️⚠️ **Preparación, y SOLO EN LOCAL (`localhost:8081`), nunca en staging ni en producción**:
> publicar una versión es **irreversible** y el texto del waiver **sigue siendo un borrador** (§8.1).
> En local: (1) panel → Ajustes → `waiver.mode` = **interno**; (2) página del waiver en el CMS → quita
> el marcador `[PENDIENTE…]` del texto (solo en tu BD local) → **«Publicar versión firmable»** → v1.
> Sin (1) no hay casilla ni tarjeta que firmar; sin (2), `GET /legal/waiver` devuelve `document: null`
> y el alta de siempre no cambia — **compruébalo antes**: en ventana de incógnito, `/registro` **no**
> tiene que enseñar la casilla mientras no haya versión publicada.

### Resultado de la ejecución HEADLESS (2026-08-26, carril A — `DECISIONES #169`)

> **Andamio**: Playwright 1.62 + Chromium 151 dentro del contenedor (`/root/e2e`, receta §5.bis con lo
> que se añade abajo), Mailpit por su API (`http://mailpit:8025`), los PDF leídos con `pdf-parse`, el
> panel conducido por su UI real (login, edición del texto, **la acción «Publicar versión firmable» con
> su modal**, Ajustes → Avanzado → Puerta → `Gestión del waiver`, y la puerta). Cuatro pasadas: la 1ª
> murió en el alta por el anti-bot (**hallazgo**, no fallo del andamio); la 2ª y la 3ª cayeron por
> fallos del propio script (textos ES usados contra la tarjeta FR; el `h2` del formulario leído como
> el de «revisa tu correo»; `button.catalog__item` es también la clase de los productos del EMBUDO y
> hay que acotarlo con `:visible`); la 4ª: **86/90 ✓** hasta V35, y V31·4 suelto **8/9 ✓**.
> **Total: 99 comprobaciones, 94 ✓; de los 5 ✗, 2 del script (los textos coincidían letra por letra
> con los FR esperados), 1 una aserción que el guion no pide, y 2 REALES** (abajo, V34).
>
> ⚠️ **Re-recorrido la noche del 26/08 con la conducta NUEVA** (`#178` casilla obligatoria · `#179`
> firma al verificar · `#183`): **111/111 ✓, 0 desviaciones** en una sola pasada de V31 a V35 y V31·4, sin
> presuponer la versión de partida (la BD ya iba por la v5). Lo nuevo que midió: el **422 sin
> casilla** con su error visible y el `GET /legal/waiver` de relectura detrás; la aceptación
> **pendiente y SIN firma en BD** antes de verificar (canal `web`, 0 firmas) y la firma nacida al
> verificar; la aceptación **descartada** cuando el texto cambia antes del enlace (0 firmas, aviso en
> el índice, texto nuevo en la tarjeta); y el resto —409 bajo los pies, re-firma, idiomas, puerta,
> externo/interno— igual que en la 4ª pasada. **Las filas V31·2, V31·3 y V32 de abajo describen la
> pasada ANTERIOR (opt-in, firma en el alta): léelas con el guion nuevo delante.** Dos trampas del
> arnés que costaron tres pasadas: tras el 422 el cajón relee el texto y re-renderiza la casilla —hay
> que esperar ese `GET` antes de marcarla— y con el texto desplegado el botón queda bajo el pliegue de
> un contenedor con scroll suave: `scrollIntoViewIfNeeded()` antes del clic, o el clic no envía nada.

| V | Resultado | Lo medido |
|---|---|---|
| **V31·1** | ✅ | Casilla «He leído y acepto la exención…» desmarcada bajo la contraseña; «Leer el texto completo» plegado; al desplegar, **5 secciones = las 5 de `GET /legal/waiver`** (es, v1 id 7) |
| **V31·2** | ✅ ⚠️ conducta anterior a `#178`/`#179` | Alta sin marcar → `POST /auth/register` 201 con `accept_waiver=false · waiver_document_id=null`; cara «Confirma tu email»; correo «Verifica tu dirección de email» en Mailpit; su enlace abre sesión y aterriza en `/mi-cuenta/pedidos`; índice con «Tienes pendiente… · Firmarla»; Privacidad «Todavía no la has firmado.» con «Firmar» |
| **V31·3** | ✅ ⚠️ conducta anterior a `#179` | Alta marcándola → en red `accept_waiver=true · waiver_document_id=7` (el servido); Privacidad «Firmada, versión vigente (v1).», 1 firma `v1·es` con «PDF»; `GET /me/waiver/{id}/pdf` → 200 `application/pdf`, `no-store`, 1,1 MB, **leído**: nombre, correo y «Conocimiento del riesgo» (ES), sin «Awareness of risk» |
| **V31·4** | ✅ 8/9 | `waiver.mode` → externo **por Ajustes** (`button[role=combobox]`, «Configuración guardada») → `GET /legal/waiver` `mode=externo, document=null`; `/registro` en incógnito **sin casilla** (3 casillas); índice sin aviso; Privacidad «La gestiona el parque fuera de esta web.» sin formulario; vuelta a interno → v3. ⚠️ **Observación** (no la pide el guion): en externo la tarjeta **sigue listando las firmas ya registradas** con su PDF. Decidir si es lo deseado |
| **V32·1–2** | ✅ (re-recorrido con la conducta nueva) | Botón «Firmar» **deshabilitado** sin casilla, habilitado al marcar; textos del botón `["Firmar","Firmando…"]` → «Firma registrada ✓»; red `POST /me/waiver 201 · GET /me/account-context 200`; estado «(v1)»; 1 firma; **de vuelta al índice sin recargar, el aviso ha desaparecido** |
| **V33·1–3** | ✅ | v2 publicada **desde el panel** (texto ES editado con un marcador, «Guardado», acción → modal → «Publicar versión 2» → «Versión 2 publicada») → API v2; el aviso **vuelve**; Privacidad «La firmaste en una versión anterior…» con el texto **nuevo** (marcador) plegado; firma → «(v2)», **dos** firmas `v2·es`/`v1·es`; **el PDF de la v1 sigue enseñando el v1** (sin marcador) y el de la v2 lleva el marcador |
| **V34·1–3** | ✅ / ✗✗ | A3 en **FR**: Privacidad con v2 en memoria; v3 publicada (ES+FR) desde el panel; marcar y «Signer» → **`POST /me/waiver → 409 waiver_document_stale` · `GET /me/waiver` · `GET /legal/waiver`**, sin «Signature enregistrée ✓», **texto recargado: v3** ✓. ❌ **Pero la casilla sigue MARCADA y el botón habilitado** tras la recarga (2 ✗: es `CAJ-3` de la revisión, spec §10.3, `PrivacyZone.vue:73-80`) — ✅ **arreglado la misma noche (`#175`)** y re-verificado en headless: tras el 409 la casilla está desmarcada y el botón deshabilitado. Segunda firma → «Signée, version en vigueur (v3).» y **PDF en FR** («Conscience du risque», marcador v3, nombre) |
| **V35·1** | ✅ | `/registro` con la web en EN: «Read the full text», API `locale=en`, 5 secciones EN; A2 (firmó v1 en ES) en EN: aviso «Your liability waiver is pending. Sign it», tarjeta «You signed an earlier version…», texto EN plegado, y **su PDF v1 sigue en ES**; en FR, «Vous avez signé une version antérieure…» |
| **V35·2** | ✅ | Puerta (`/admin/puerta/validar`): A3 (v3 vigente) → «Registrado · Waiver aceptado el 26/08/2026.»; A2 (v1) → «Registrado · Waiver aceptado el… · **Su waiver es de una versión anterior del texto: puede pasar**…» |
| **BD** | ✅ | 7 firmas, todas `channel=web`, `subject=holder`, `holder_name/email` copiados, `accepted_tz=Europe/Madrid`; A1: `prev_hash` de la v2 = `hash` de la v1; `WaiverChain::verify()` OK en los 6 titulares; **`waiver:verify-chain --workers=8` sobre MySQL: 9 filas, 0 repetidos, lineal** |

**Desviaciones declaradas**: el anti-bot se apagó durante la prueba (clave secreta vacía) y se
restauró al terminar · V34 se recorrió en FR (así hay una firma en un idioma distinto del ES para
V35·1) · los marcadores de versión (`[E2E-v2]`, `[E2E-v3]`) quedan en el texto de la BD local.
**Artefactos**: capturas, textos de los PDF y `resultado.json` en `/root/e2e/out` del contenedor
(copiados al scratchpad de la sesión; **no sobreviven a recrear el contenedor**).

**Lo que este recorrido añade a la receta de §5.bis** (para no volver a pagarlo):
- El puente 8081→80 tiene que escuchar en **todas** las interfaces (`localhost` resuelve a `::1` en el
  contenedor) y arrancarse con `docker compose exec -d` — un `nohup … &` dentro de `exec -T` muere con
  la sesión.
- `npm i pdf-parse` para **leer** los PDF (no hay `pdftotext` en el contenedor).
- `button.catalog__item` es la clase de los productos del embudo Y de las entradas del índice de la
  cuenta: acotar con `:visible`. La segunda cara del alta se espera por `p.auth__sent[role=status]`,
  no por `h2.auth__title` (el del formulario ya existe).
- Filament: los rótulos obligatorios llevan `*` (`getByText(…, {exact:true})` no casa); el campo del
  modo es `#form\.waiver\.mode` (`button[role=combobox]` → `getByRole('option')`); la sección es
  `form h2.fi-section-header-heading`; las pestañas del texto son `getByRole('tab', {name: 'Español'})`
  y el último `textarea:visible` es la última sección; el modal de publicar se confirma con
  `getByRole('button', {name: 'Publicar versión N'})`.
- Turnstile con claves de prueba: el token aparece en `input[name="cf-turnstile-response"]` dentro del
  contenedor (~2 s tras inyectar el script); esperar por él, no por reloj. (El 26/08 por la tarde el
  widget **no se montaba** en el alta suelta — defecto arreglado esa noche, `#171`.)
- El enlace de verificación del correo **abre sesión** y aterriza en `/mi-cuenta/pedidos`: no hace
  falta pasar por el login para entrar en Mi cuenta.

### V31 · La casilla del ALTA (ventana de incógnito)

> ⚠️ Reescrito el 26/08 (noche) tras `#178` (**la casilla es OBLIGATORIA en interno con versión
> publicada**) y `#179` (**el alta NO firma: la firma nace al verificar el correo**). La versión
> anterior de estos pasos decía «es opt-in» y «Firmada» nada más crear la cuenta: ya no es así.

1. Abre `/registro`. ▶ Debajo de la contraseña, la casilla **«He leído y acepto la exención de
   responsabilidad (waiver).»** —desmarcada— y, bajo ella, **«Leer el texto completo»** plegado.
   Despliégalo: son las secciones del texto publicado, en el idioma de la página.
2. Rellena todo y pulsa «Crear cuenta» **SIN marcarla**. ▶ **No se crea la cuenta**: `422` sobre
   `accept_waiver`, y el formulario lo dice bajo la casilla y en el banner («Para crear la cuenta hay
   que leer y aceptar la exención de responsabilidad (waiver).»). En red: `POST /auth/register` con
   `accept_waiver: false · waiver_document_id: null` → `422`, y **a continuación un `GET /legal/waiver`**
   (el cajón relee el texto; si el formulario se montó antes de publicarse la versión, es lo que hace
   aparecer la casilla). No llega ningún correo.
3. Marca la casilla y vuelve a pulsar (con el texto desplegado el botón queda bajo el pliegue: haz
   scroll). ▶ `201` → cara «Confirma tu email» y llega el correo. ⚠️ **Todavía no hay firma**: el alta
   deja la aceptación **pendiente** (`users.waiver_pending_document_id`, con el canal, la IP y el
   navegador de ESTE momento) y la firma nace **al verificar**. En red, el alta tiene que haber mandado
   `accept_waiver: true` **y** `waiver_document_id: <id>`; si va `true` con `null`, el servidor lo
   rechaza en el campo y el banner tiene que decirlo. Abre el enlace del correo. ▶ Aterrizas en Mi
   cuenta con sesión; **sin aviso** en el índice; en **Privacidad**: **«Firmada, versión vigente
   (vN).»**, una firma en la lista con su fecha y su enlace **«PDF»**. Púlsalo: baja un PDF con el
   texto **en el idioma en que lo aceptaste**, tu nombre y tu correo.
4. **La aceptación caduca si el texto cambia antes de verificar.** Otra cuenta, marcando la casilla; y
   ANTES de abrir su enlace, publica una versión nueva en el panel. Abre el enlace. ▶ Entras, pero
   **sin firma**: la aceptación se descarta porque el texto ya no es el que leíste (log
   `waiver.pending_dropped`). En el índice, el aviso **«Tienes pendiente la exención de
   responsabilidad (waiver).» · «Firmarla»**; en Privacidad, **«Todavía no la has firmado.»** con el
   texto **nuevo** plegado y el botón «Firmar». (Es, además, la única manera de tener en modo interno
   una cuenta verificada sin firma: la necesitan V32 y V34.)
5. Cambia `waiver.mode` a **externo** y vuelve a `/registro`. ▶ **No hay casilla** y Privacidad dice
   **«La gestiona el parque fuera de esta web.»**. Devuélvelo a interno.

### V32 · La TARJETA de Privacidad: firmar

1. Con la cuenta del punto 4 de V31, en Privacidad: despliega el texto, marca la casilla y pulsa
   **«Firmar»**. ▶ «Firmando…» → **«Firma registrada ✓»**, el estado pasa a «Firmada, versión vigente
   (vN).» y la firma aparece en la lista con su «PDF». **Sin recargar**, vuelve al índice: el aviso
   «Tienes pendiente…» **ha desaparecido** — es el refresco del contexto de cuenta al firmar.
2. El botón **sin** marcar la casilla. ▶ No hace nada (deshabilitado): firmar es marcar y pulsar, no
   pulsar.

### V33 · La RE-FIRMA: el texto cambia

1. En el panel (local), edita el texto del waiver y publica **v2**.
2. Con la cuenta de V32, abre Mi cuenta. ▶ El aviso del índice **vuelve**; en Privacidad: **«La
   firmaste en una versión anterior del texto: puedes entrar igual, pero te pedimos que aceptes la
   nueva.»**, con el texto **nuevo** plegado debajo, la casilla y «Firmar».
3. Firma. ▶ «Firmada, versión vigente (v2).» y **dos** firmas en la lista, cada una con su PDF: el de
   la v1 sigue enseñando el texto v1. Ninguna firma se borra: es un registro, no un estado.

### V34 · El texto que cambia BAJO LOS PIES (`409 waiver_document_stale`)

1. Pestaña A: Privacidad con el texto vigente cargado (sin firmar todavía: otra cuenta, creada como en
   V31·4). Pestaña B, el panel: publica una versión **nueva**.
2. Vuelve a A **sin recargar**, marca y pulsa «Firmar». ▶ **NO** sale «Firma registrada ✓»: la tarjeta
   **recarga el texto** —ahora v3— y vuelve a pedir la casilla. En red: `POST /me/waiver` → **409**
   con `waiver_document_stale`, y a continuación `GET /legal/waiver` y `GET /me/waiver`.
3. Marca y firma otra vez. ▶ Ahora sí: «Firmada, versión vigente (v3).». Lo firmado es lo que se
   enseñó, nunca lo que había en memoria.

### V35 · Los tres idiomas, y la puerta

1. Cambia el idioma de la web a EN y a FR. ▶ El texto plegado del alta y de Privacidad llega **en ese
   idioma** (la API negocia el `Accept-Language`); el PDF de cada firma sale **en el idioma en que se
   firmó**, no en el de la página.
2. En el panel → puerta, valida el registro de una de estas cuentas. ▶ Con la firma vigente, pasa; con
   una firma **anterior**, pasa igual y señala «versión anterior» (§4.8 no crea cola).

### Qué comparar en BD al terminar

`waiver_signatures`: una fila por firma, `channel` = `web`, `holder_name`/`holder_email` como estaban
al firmar, y `prev_hash` encadenando las de un mismo titular. Y `php artisan waiver:verify-chain`
tiene que salir limpio. Si el hash de una fila no cuadra, el problema no es del cajón.

## §5.decies · «MENORES A CARGO» EN EL CAJÓN — ✅ recorrido en headless el 2026-08-27 (20/20), pendiente del OJO del owner (`DECISIONES #199`)

> Fase 6 · C, tanda 3 (`specs/menores-a-cargo.md` §9.8). Mismo andamio que §5.bis (Playwright dentro
> del contenedor, puente 8081→80). Guion: `/root/e2e/dep-probe.js`; helper de BD:
> `dep-db.php` bajo `storage/app/e2e/` (ignorado por git; `setup` · `state` · `publish` · `cap N` · `cleanup`).
> Requiere `waiver.mode = interno` y una versión publicada. ⚠️ **`publish` deja una versión de prueba
> `[E2E-DEP]` en la BD local en cada pasada.** Cuenta: `e2e-dependents@jumpweb.test`.

### D1 · La entrada y la zona vacía
1. Entra por `/mi-cuenta` (zona de login dentro del cajón) con la cuenta de prueba.
2. En el índice hay una entrada **«Menores a cargo»** con su icono (dos personas), entre «Tus datos» y
   «Cambiar contraseña». Ábrela: el título es «Menores a cargo», el texto dice que todavía no hay
   ninguno, y debajo está «Añadir un menor» con **dos campos** (nombre y fecha de nacimiento).

### D2 · Declarar
3. Nombre «Mayor», fecha `2000-01-01`, «Añadir» → aviso rojo con el texto del servidor («…tiene que
   ser menor de edad») y **ninguna tarjeta**.
4. Nombre «Lucas», fecha `2017-03-12`, «Añadir» → tarjeta «Lucas · 9 años · 12/03/2017 · Exención sin
   firmar en su nombre», con «Leer el texto completo», la casilla y «Firmar» **deshabilitado**. El
   formulario de alta queda vacío.

### D3 · Firmar en su nombre
5. Marca la casilla → «Firmar» se habilita. Púlsalo → «Firma registrada ✓», la frase pasa a «versión
   vigente (vN)», aparece «vN · fecha · PDF» y la casilla desaparece. El PDF abre (`application/pdf`)
   y dice «En nombre de: Un menor a su cargo: Lucas (fecha de nacimiento 12/03/2017)» y la nota de
   que los datos los declaró el titular. ⚠️ **El titular NO queda firmado** por esto: en Privacidad su
   propia exención sigue como estaba.
6. Publica una versión nueva del waiver desde el panel (Páginas → waiver → «Publicar versión
   firmable»). Recarga y vuelve a la zona: la tarjeta dice «versión anterior… acepta la nueva» y
   vuelve a ofrecer la casilla. Firma → «versión vigente (vN+1)».

### D4 · El tope, quitar, volver
7. Ajustes → «Puerta» → «Menores a cargo por cuenta (máx.)» = 1. Intenta añadir «Vera» → aviso «Ya
   has llegado al máximo de menores a cargo de tu cuenta (1)». Vacía el ajuste → «Vera» entra.
8. «Quitar» en Lucas → diálogo de confirmación → la tarjeta desaparece; Vera sigue. En el panel, el
   registro del waiver de la cuenta sigue listando las dos firmas «en nombre del menor a su cargo
   Lucas» (la fila queda desvinculada, no borrada).
9. «Volver» → el índice, con la entrada.

### Lo que el guion no cubre y hay que mirar con el ojo
- El aspecto de la tarjeta con el tema (no hay CSS nuevo: es el de las tarjetas de la cuenta).
- Los tres idiomas de la zona (EN/FR tienen sus 17 rótulos; el guion corre en `es`).
- Un menor que cumple 18 entre la declaración y hoy (la tarjeta lo marca y no ofrece firmar): exige
  cambiar el reloj, así que lo cubre `MeDependentWaiverTest`, no el navegador.


## §5.undecies · LA ASIGNACIÓN DE ENTRADAS A MENORES, EN EL EMBUDO — ✅ recorrido en headless el 2026-08-27 por la noche (19/19), pendiente del OJO del owner (`DECISIONES #202`)

> Fase 6 · C, tanda 4 · U2 (`specs/menores-a-cargo.md` §9.9). Mismo andamio que §5.decies. Guion:
> `/root/e2e/dep-assign-probe.js`; helper de BD: `dep-assign-db.php` bajo `storage/app/e2e/`
> (`seed` · `state` · `sign` · `unsign` · `cleanup`). `seed` deja al titular con DOS menores —Lucas con
> la exención vigente, Vera sin firmar— en modo interno; `cleanup` CADUCA los pedidos del guion por el
> dominio (`orders:expire`, que es lo que libera el aforo) y luego los borra. Cuenta:
> `e2e-dependents@jumpweb.test`. ⚠️ Las dos puertas crean un pedido `pending` cada una y se van a la
> pasarela: el guion aborta esa navegación; en el navegador **no pagues** salvo en staging.
>
> ⚠️⚠️ **Este guion cazó DOS defectos que ningún test veía**, y por eso existe ANTES del ojo:
> (1) el cajón que **nace abierto con sesión** (`/entradas` tras entrar) no pedía los menores —solo
> se pedían al restaurar una cesta o tras el login del paso 5— y el paso 3 salía **sin selector**; el
> arreglo (un `watch` sobre el titular) nació con un TDZ que Vue tragaba en silencio y que hacía saltar
> la carga **una vez, también sin sesión** (401 en consola) y nunca más; (2) quien entra anónimo y se
> identifica en el paso 5 volvía a la cesta con el selector y el aviso **en blanco**: los rótulos
> viajaban en `account.dependents`, que solo va con sesión. Hoy viven en `tickets.dependents`.

### P1 · Con sesión desde el principio (la puerta 1)
1. Entra por `/mi-cuenta` y ve a `/entradas`. Elige una ENTRADA (no un pack), un día y una hora.
2. Bajo la cantidad aparece **«¿Para quién son estas entradas?»** con una casilla por menor: «Lucas ·
   9 años» se puede marcar; «Vera · 6 años — exención sin firmar: fírmala en «Menores a cargo»» sale
   **deshabilitada** con ese motivo. Sube la cantidad a 2 y marca a Lucas.
3. «Añadir al carrito» → la línea del carrito lleva el mismo selector con Lucas marcado y **ningún**
   aviso. En el almacén del navegador (`jw.cart.v1`) la línea guarda `dependent_ids: [id]` y **nunca
   el nombre**.
4. «Ir a pagar» → el resumen dice **«Para: Lucas»** bajo la línea.
5. (Solo con el helper) `unsign` y pulsa pagar: **422**, ningún pedido creado, el cajón vuelve al
   carrito con «Falta su exención firmada: fírmala en «Menores a cargo» antes de asignarle una
   entrada.» en el pie y **Lucas desmarcado** (D3: la línea rechazada vuelve sin asignar).
6. `sign`, marca a Lucas otra vez y paga: **201** con `items[0].dependent_ids = [id]`; en BD una fila
   en `dependent_assignments` y una entrada de auditoría `dependents.assigned` **sin el nombre**.
   `GET /orders/{code}/event-data` lista `dependents: [{id, name: "Lucas"}]`; `GET /me/orders` **no
   contiene «Lucas»** (el nombre solo por el canal lateral, como las respuestas del pack).

### P2 · Sin sesión hasta el paso 5 (la puerta 2)
7. Ventana limpia. Misma entrada, cantidad 1, sin selector (no hay sesión). «Añadir al carrito» → «Ir a
   pagar» → paso 5, pestaña «Entrar».
8. Entra con la cuenta. **No va a pagar**: vuelve al **carrito** con el aviso «Tienes menores a cargo:
   indica para quién es cada entrada antes de pagar (o déjalas como adultos).» y el selector ya
   pintado, **con sus rótulos**. Marca a Lucas → «Ir a pagar» → «Para: Lucas» → pagar → 201.
9. Si al identificarse **no** hubiera menores asignables (o todas las líneas fueran packs), iría a
   pagar directamente: no hay aviso que dar.

### Lo que el guion no cubre y hay que mirar con el ojo
- El aspecto del selector con el tema (cero CSS nuevo: `addons`/`addons__intro`, `form__hint`, `form__checks`,
  `check`). ⚠️ El owner ya cazó uno que este guion NO ve —el checkbox a 400 px y el nombre fuera del cajón por
  `.eventfields input`—: el guion comprueba nodos y textos, no la cascada. Está arreglado y re-medido
  (`specs/menores-a-cargo.md` §9.9.8·7); si vuelve a verse raro, mide `getComputedStyle(input).width`.
- EN/FR: los nueve rótulos de `tickets.dependents` existen en los tres idiomas; el guion corre en `es`.
- «Mis reservas»: la tarjeta de una reserva con menor asignado ofrece «Ver para quién es» → «Para:
  Lucas» (lo fija el test de la tarjeta; el guion no llega porque el pedido queda `pending`).
- El menor que cumple 18 antes del día de la visita (la casilla dice «ya tiene 18 años» y el servidor
  responde `not_minor_on_date`): exige jugar con fechas; lo cubre `DependentAssignerTest`.

---

## §5.duodecies · EL RELLENO DE ACCIÓN (el quinto mecanismo del tema) — ✅ recorrido en headless el 2026-08-28 (12/12), pendiente del OJO del owner (`DECISIONES #209`)

> Carril C (`specs/tema-por-instalacion.md` §15). Mismo andamio que §5.bis: Playwright **dentro del
> contenedor** (`/home/sail/e2e/`), la web en `http://localhost` desde dentro. Guion:
> `accion.mjs`, ~60 líneas, sin helper de BD ni cuenta de prueba — **no toca datos**, solo lee
> colores computados y manipula el `<style id="jj-theme">` en memoria.
>
> ❗ **Este guion es la ÚNICA prueba de que el rol tiene sus DOS conductas.** Ninguna guarda estática
> puede verlo: lo que se comprueba es el color que **resuelve el navegador** en cada ámbito.
>
> ⚠️ **Requisito**: la instalación tiene que tener `theme.action` puesto (en esta máquina,
> `#F2711C`). Sin él el bloque A no aplica y solo se puede recorrer el B.
>
> ⚠️⚠️ **Dos trampas del ARMAZÓN que este guion pagó, y que costarán lo mismo al siguiente**: el CTA
> del nav **nace oculto** (`navCtaReveal` lo destapa al bajar del hero) y la barra **se retira al
> bajar** (`nav--hidden`). Hay que **bajar ~1,6 pantallas y volver a subir ~240 px**; si no,
> Playwright espera a un elemento invisible o «fuera del viewport» y agota el tiempo. No es un fallo
> del mecanismo: es la coreografía de `#194`/`#203`.

### A1 · El conmutador llega al navegador
1. Abre `/`. En `getComputedStyle(document.documentElement)`, `--action-brand` vale **`#F2711C`** y
   `--on-action-brand` vale **`#14130F`** (tinta) — el color del rótulo lo elige el CONTRASTE, no el
   gusto: sobre naranja gana tinta.

> ⚠️⚠️ **CADUCADO EN PARTE por `#213`, y la corrección va delante.** Los pasos 2–5 conducían
> `.cta-med` —el CTA del armazón— y **ese botón ya NO es de acción**: por decisión del owner cambia
> de rol dentro del menú (tinta cerrado → aviso abierto, como su mockup). Los pasos reescritos
> están abajo.
> ❗ **Y con eso el producto se queda SIN UN SITIO donde se pueda VER la independencia de
> superficie**: era el único botón de acción que entraba en un ámbito de tinta. El mecanismo sigue
> entero —lo fija `SurfaceScopeTest::test_the_action_role_keeps_its_indirection`, y el bloque B de
> abajo aún lo demuestra en vivo—, pero **de la mitad A ya no queda demostración visual**. Volverá
> a haberla el día que un botón de acción viva sobre una sección oscura.

### A2 · Con color de acción, el relleno es el del cliente
2. Baja hasta la sección de tarifas. El CTA de la tarifa destacada (`.price__cta`) tiene
   `background-color` **`rgb(242, 113, 28)`** con el rótulo en `rgb(20, 19, 15)`.
3. Pasa el cursor por encima de un `.btn` sólido: el fondo pasa a **`rgb(213, 99, 25)`** — el
   `#D56319` que declara el sistema del cliente, **derivado ×0,88, no tecleado**.
4. Abre el menú (☰). La barra gana `data-surface="ink"`. ⚠️ **El CTA del armazón se vuelve
   AMARILLO**, no naranja: eso es `#213` y es correcto, no un fallo de este mecanismo.
5. Mira al lado: el botón **fantasma** «Registrarse» SÍ se ha adaptado al fondo oscuro — es la otra
   mitad de la regla del cliente («el secundario cambia según el fondo»).

### B · Sin color de acción, el relleno SÍ sigue a la superficie
6. Con el menú abierto, borra el conmutador en vivo:
   `s = document.getElementById('jj-theme'); s.textContent = s.textContent.replace(/--(on-)?action-brand[^;]*;/g, '')`.
   Es exactamente lo que ve una instalación que no ha declarado color de acción.
7. Cierra el menú y mira `.price__cta`: **se vuelve TINTA** (`rgb(20, 19, 15)`), la conducta
   histórica del producto. El fallback resuelve contra el `--fg` de su superficie.
8. Cero errores de JavaScript en toda la pasada.

### Lo que el guion no cubre y hay que mirar con el ojo
- **Las otras 12 reglas de acción**: el guion solo conduce `.cta-med`. Los demás (`.btn`,
  `.cta-prime` de la barra de móvil, `.price__cta`, `.bd-pack__cta`, `.zone-intro__cta`,
  `.acct__btn--primary`, `.cartbar`, `.svc-cta--book`) leen los mismos tokens y lo fija
  `ActionFillTest`, pero **verlos en su sitio es del ojo**.
- **Que ningún relleno que NO es acción se haya vuelto naranja**: pegatinas, pestañas activas,
  hovers de fantasma. Es lo que más se notaría y ninguna captura lo demuestra sola.
- **La barra de compra de MÓVIL** (`.book-bar__cta`), cuyo estado pulsado usa `--action-hover`: en
  táctil no hay hover y ese estado solo se ve con el dedo.

## §5.terdecies · EL «NO» DEL LOGIN EN EL ÁREA y el «VOLVER» DEL CARRITO — ✅ recorrido en headless el 2026-08-28 (9/9 + 9/9), pendiente del OJO del owner (`DECISIONES #210`)

> Carril A. Mismo andamio que §5.bis (`/root/e2e`, puente 8081→80). Dos guiones sin helper de BD:
> `login-probe.js` (una cuenta que NO existe: `probe-nadie@jumpweb.test`, así no gasta el limitador
> de nadie) y `cart-back-probe.js` (visitante anónimo, la primera entrada del catálogo). Los dos
> imprimen **los errores de consola**, y eso no es decoración: ⚠️ **un `[console] error` en un
> sondeo es un hallazgo** — el `ReferenceError` del TDZ de `PurchaseSection.vue` llevaba impreso
> desde el guion 19/19 de §5.undecies sin que nadie lo leyera.

### L1 · La contraseña mala se DICE
1. Abre `/login` (el cajón nace en la zona de entrar). Escribe cualquier correo y una contraseña
   mala. Pulsa «Iniciar sesión».
2. Bajo el campo del correo aparece **«Estas credenciales no coinciden con nuestros registros.»**
   (`.form__error`, ~13 px). ❗ Hasta `#210` aquí **no aparecía nada** — ni texto ni cambio de estado —
   y ese es exactamente el «no sale nada» del owner.

### L2 · El limitador se DICE, y en el banner
3. Repite hasta el sexto intento seguido (5 fallos por correo+IP en 60 s, `SEC-06`).
4. Arriba del formulario, en rojo, **«Demasiados intentos. Inténtalo de nuevo en N segundos.»**
   (`.auth__errors[role=alert]`). El aviso de credenciales desaparece: son excluyentes a propósito
   (hallazgo L-02 del origen).

### L3 · La consola, limpia
5. En toda la pasada, **cero `ReferenceError`** al montar el cajón y **ningún `GET /me/dependents`**
   de un visitante sin sesión. (Los 401/429 del propio login sí salen: son la respuesta esperada.)

### C1 · El carrito tiene «Volver»
6. En incógnito, abre `/entradas`: el catálogo **no** lleva «Volver» (no hay a dónde). Elige una
   entrada, un día, una hora y «Añadir».
7. En el carrito, **ANTES del título «Tu carrito»**, hay un «← Volver» (`bk-back purchase__back`),
   el mismo que tienen «Identifícate» y «Pagar». ❗ Hasta `#210` la única salida era «+ Añadir otra
   reserva», al pie — y con la cesta vacía, ninguna.

### C2 · «Volver» no destruye nada
8. Púlsalo: vuelves al **catálogo** y la barra-carrito sigue diciendo «1 artículo · 9,90 € · Ir al
   carrito». La cesta se conserva; solo cambia la pantalla.

### C3 · La barra-carrito del catálogo LLEVA al carrito (`DECISIONES #215`)
9. En el catálogo con cesta, pulsa la barra del pie («… · Ir al carrito»): **vuelves al carrito** con
   tu línea. ❗ Hasta `#215` este botón era **mudo** —desde 4.3·2, dos semanas—: la máquina no tenía la
   arista `CATALOG → CART` y rechazaba el salto en silencio. Pruébalo también tras «+ Añadir otra
   reserva», que es el otro camino que deja al cliente ahí.

### Resultado headless (2026-08-28, 06:40, hora de Madrid)
- `login-probe.js` tras el arreglo: **9/9** — L1 ×5 (401 y el `.form__error` visible, con caja y
  con el texto de `auth.failed`) · L2 ×2 (429 con `.auth__errors[role=alert]` y «…N segundos»; el
  aviso de credenciales desaparece) · L3 ×2 (cero errores de consola; ningún `GET /me/dependents`).
  ⚠️ La primera versión del guion **no aseveraba nada** —imprimía y se leía a ojo— y la revisión
  adversarial de `#210` lo señaló: los `check()` son de después, y el 9/9 es de la re-ejecución.
  **Antes del arreglo, el mismo guion**: `fields.email = ""`, `global = ""`, `visibles: []`.
- `cartbar-probe.js` (`#215`, 2026-08-28 a las 08:05): **4/4** tras el arreglo — A1 el catálogo con cesta
  ofrece «Ir al carrito» · A2 pulsarlo lleva al carrito (paso 4, la línea sigue) · B lo mismo tras
  «+ Añadir otra reserva» · C cero errores de consola. **Antes del arreglo, el mismo guion: 2/4** (A2 y
  B con el paso clavado en 1).
- `cart-back-probe.js`: **9/9** — C0 sin «Volver» en el catálogo · C1 uno en el carrito, antes del
  título, rótulo «Volver», con caja · C2 paso 1 con `lines = 1` y el pie intacto · C3 cero errores de
  consola y ningún `/me/dependents` espontáneo.

### Lo que el guion no cubre y hay que mirar con el ojo
- **El color del aviso**: `.form__error` se computa en tinta (`rgb(20, 19, 15)`), no en `--err`. Es
  legible; si debe ser rojo lo decide el tema (carril C).
- **Los otros avisos del área** que salían de `auth.*` y llegaban vacíos —limitador del alta, del
  recuperar, del cambio de contraseña, de perfil/privacidad/menores—: el arreglo es el mismo (una
  prop) pero solo el login se ha conducido con un «no» delante.
- **El «Volver» con la cesta VACÍA**: alcanzable (producto retirado con el cajón abierto), lo fija el
  árbol, no se ha conducido.
---

## §5.quaterdecies · EL MENÚ SE CIERRA Y SIEMPRE OFRECE COMPRAR — ✅ recorrido en headless el 2026-08-28 (15/15), pendiente del OJO del owner (`DECISIONES #211`)

> Carril C (`specs/armazon-y-menu.md` §6). Playwright dentro del contenedor, `http://localhost`.
> Guion: `menu.mjs`. **No toca datos.** ⚠️ Requiere la ventana a **1440×900 o más**: por debajo de
> 1080 px manda el cajón lateral, que es otra pieza.

### M1 · Arriba del todo, sin haber bajado nada
1. Abre `/`. **El CTA de compra del armazón NO se ve** (nace oculto; lo destapa `navCtaReveal` al
   pasar el hero). La hamburguesa tiene `aria-expanded="false"` y enseña **las tres rayas**.
2. Pulsa la hamburguesa. El menú se abre a pantalla completa. La hamburguesa pasa a
   `aria-expanded="true"`, **enseña una X** y su nombre accesible es **«Cerrar menú»**.
3. ❗ **Y aparece el botón de comprar**, en **`rgb(242, 113, 28)`** — el naranja de acción, sobre la
   tinta del menú. Antes de `#211`, aquí no había ningún sitio donde comprar.

### M2 · Las tres salidas
4. Pulsa la hamburguesa otra vez → **el menú se cierra** y vuelven las rayas. (Antes de `#211` esto
   no ocurría: el botón solo abría.)
5. Reabre y pulsa `Escape` → cierra. Reabre y pulsa cualquier destino → cierra y navega.
6. Con el menú cerrado en la portada, el CTA vuelve a esconderse.

### M3 · Los colores de zona
7. Baja a las tarjetas de zona: **ninguna usa `#FF5B22` ni `#C6FF3A`** (la paleta del primer
   cliente). Kids es cian `#1AA9DE` y Jump lima `#A3C21C`.
   ⚠️ **En esta máquina saldrán además dos tarjetas «Cap»** en naranja: son **zonas de prueba**
   (`slug = cap` y `cap2`, sondas de aforo) que están ACTIVAS y salen en la portada pública. No es
   un defecto del tema; es dato de esta base de datos. Ficha en `DEUDA.md`.

### Lo que el guion no cubre y hay que mirar con el ojo
- **El menú por debajo de 1080 px**: ahí manda el cajón lateral, que es la 2c·4b y sigue sin hacer.
- **La animación** del recorte circular al abrir y el aspa al alternar: el guion mira estados, no
  el camino entre ellos.
- **El logotipo**: sigue siendo el nombre en la fuente de rótulo, no la marca del cliente.


## §5.quindecies · «MI CARNÉ» EN EL CAJÓN y «ROTAR CARNÉ» EN EL PANEL — ✅ recorrido en headless el 2026-08-28 (14/14), pendiente del OJO del owner (`DECISIONES #212`)

> Carril A (`specs/identidad-qr-puerta.md` §9.6). Mismo andamio que §5.bis. Guion `card-zone-probe.js`
> con un cliente de prueba propio (`probe-card@jumpweb.test`, correo verificado; se crea con `tinker`,
> no vive en el repo). ⚠️ **Trampa del guion**: la sección de compra está en el DOM aunque no se vea
> (`v-show`), así que un selector sin `:visible` sobre `.catalog__item` cuenta también el catálogo —
> la primera pasada dijo que «Mi carné» era la entrada 18.

### K1 · La entrada del índice
1. Entra por `/mi-cuenta`. En el índice, **tercera entrada** tras «Mis reservas» y «Mis pedidos»:
   «Mi carné», con un icono de QR (tres cuadrados y una rejilla).

### K2 · La zona
2. Púlsala. Aparece el **QR** (264×264, dibujado por el servidor: es el MISMO PNG del correo), debajo
   «Si la cámara falla, dicta este código:» y el token en **grupos de 4** (`JW.. .... ....`), el enlace
   «Descargar (PNG)» y el botón «Renovar carné».
3. Descarga el PNG y ábrelo: es un QR nítido con margen blanco. ▶ **Es el que hay que probar con el
   LECTOR real** (§6 de la spec): la cámara del móvil ya lo lee (§9.5·5).

### K3 · Renovar
4. Pulsa «Renovar carné». Sale una confirmación: «El carné actual dejará de valer en el acto: el del
   correo y cualquier copia impresa. ¿Renovar?». Acepta.
5. El token de la pantalla **cambia** y el QR **se repinta** (otra imagen); arriba, «Carné renovado. El
   anterior ya no vale.».
6. Con el token ANTERIOR en la puerta (`/admin/puerta/validar`): «Carné caducado». Con el nuevo:
   verde + ficha.

### K4 · El panel
7. Panel → Usuarios → la ficha de ese cliente → **«Rotar carné QR»** en la cabecera (solo si tienes
   `users.manage`; no aparece sobre staff, sobre ti ni sobre una cuenta anonimizada). El modal dice
   «El carné actual (emitido el DD/MM/AAAA) dejará de valer EN EL ACTO…» — o «todavía no tiene carné».
8. Confirma: aviso verde. En Incidencias, `cards.rotated` con **tu usuario** de actor y el cliente de
   target (así se distingue del que rota el propio cliente). El cliente, al reabrir «Mi carné», ve otro.

### Resultado headless (2026-08-28, 07:40, hora de Madrid)
- **14/14**: Z0 índice con icono y tercero · Z1 `GET /me/card` con token y `png_url`, imagen
  264×264 cargada desde `/api/v1/me/card/png?v=…`, `image/png` + `no-store` + firma PNG, token en
  grupos de 4, descargar al mismo PNG · Z2 «Renovar» presente, confirmación con «dejará de valer en el
  acto», `POST` 201 con token nuevo, pantalla con el nuevo e imagen repintada, aviso «Carné renovado» ·
  Z3 `GET /me/card` devuelve el nuevo · Z4 cero errores de consola. BD: carné anterior `rotated` en el
  mismo segundo, nuevo activo; auditoría `issued → rotated → issued`.
- El panel (K4) lo cubre `RotateCardActionTest` (6 casos, incluido el re-check entre render y submit);
  no se condujo en navegador.

### Lo que el guion no cubre y hay que mirar con el ojo
- **El QR en el MÓVIL** (tamaño, nitidez, el `download` en iOS/Android) y con el **lector real**.
- **El estado degradado** (clave del servidor rotada): «Este carné ya no se puede mostrar» + renovar.
  Lo fijan `MeCardTest` y `card.test.js`; no se ha provocado en navegador.
- **La acción del panel**, con el ojo (K4): el modal con la fecha, el aviso, la fila de Incidencias.

---

## §5.sexdecies · EL CTA DOBLE DE LA CABECERA — ✅ recorrido en headless el 2026-08-28 (12/12), pendiente del OJO del owner (`DECISIONES #214`)

> Carril C (`specs/armazon-y-menu.md` §8). Playwright dentro del contenedor. Guion: `par.mjs`.
> **No toca datos.** ⚠️ Ventana **≥ 1080 px**: por debajo manda la barra flotante, que es la otra
> mitad de la misma pieza.
> ⚠️ Recuerda las dos trampas del armazón: el CTA **nace oculto** y la barra **se retira al bajar**
> — hay que bajar ~1,6 pantallas y volver a subir ~240 px.

### P1 · Reposo: uno ancho, el otro reducido a su icono
1. **Comprar está expandido** (~167 px) y la mitad de la cuenta **colapsada a su icono** (~54 px).
   El cuerpo de la cuenta está a opacidad 0; el de comprar, a 1.
2. ❗ **La mitad colapsada INVITA**: se mueve sola cada 4,6 s (`cta-asoma`) y un **aro** late a su
   alrededor (`cta-aro`). Míralo unos segundos: es lo que hace que un botón de dos pasos se
   descubra sin instrucciones.

### P2 · El primer clic EXPANDE, no actúa
3. Pulsa la mitad colapsada. **Los anchos se intercambian** con transición: ahora la cuenta es la
   ancha y comprar queda en su icono. ⚠️ **El cajón NO se abre**: el primer clic solo expande.
4. **La invitación se apaga** — y ya no vuelve en esa visita, aunque expandas la otra mitad.
5. Los nombres accesibles **dicen qué hacen AHORA**: la colapsada se llama «Cambiar a reservar
   entradas», no «Reservar». Compruébalo con el inspector o con un lector de pantalla.
6. Pulsa **otra vez** la mitad ancha: ahora sí actúa (abre el cajón en su zona).

### P3 · Las tres situaciones de la mitad de la cuenta
7. **Invitado sin trámite externo** → «Registrarse», glifo de persona con +, y abre el alta.
8. **Invitado con trámite externo configurado** (Ajustes → Registro) → el rótulo y subtítulo del
   parque, glifo de portapapeles, y abre su sistema en pestaña nueva.
9. **Con sesión** → «Mi cuenta», glifo de persona, y abre el área de cliente. Si hay formulario
   pendiente, el **punto de aviso** sigue sobre el icono.

### P4 · El suelo
10. Con JavaScript desactivado, **las tres mitades navegan de una sola pulsación** (`/entradas`,
    `/registro` o la URL del parque, `/mi-cuenta`). El doble paso no existe ahí, a propósito.
11. Con **«reducir movimiento»** activado en el sistema, la invitación **no se anima** — ni el
    asomo ni el aro. El par sigue funcionando igual.

### Lo que el guion no cubre y hay que mirar con el ojo
- **La transición en sí**: el guion mira estados antes y después, no el camino entre ellos.
- **Que la invitación no moleste**: 4,6 s es el valor del mockup, pero si en pantalla resulta
  insistente, el token `--dur-invite` está para eso.
- **El par por debajo de 1080 px**: ahí es la barra flotante (`#205`), que ya tenías validada.

---

## §5.septdecies · EL ARMAZÓN NACE BAJO EL HERO y el CTA doble es el del mockup — ✅ medido en headless el 2026-08-28, pendiente del OJO del owner (`DECISIONES #216`)

> **Esto cambia cómo se ve la PRIMERA PANTALLA de la portada**, así que es lo primero que hay que
> mirar. Y ojo: **la primera visita trae el banner de cookies**, que en escritorio cae sobre la
> esquina inferior izquierda y tapa parte del hero. Acéptalo o recházalo antes de juzgar el hero.

### A · La portada, de arriba abajo (escritorio, ≥ 1100 px)

| | Qué hacer | Qué tiene que pasar |
|---|---|---|
| A1 | Cargar `/` y **no tocar nada** | **No hay logotipo, ni CTA, ni hamburguesa.** El hero llena la pantalla y dentro lleva eslogan → titular → **«Reservas aquí» (naranja) + «Ver precios»** → estado de apertura |
| A2 | Bajar **muy despacio** | Los tres entran **a la vez**, bajando 16 px y apareciendo. No antes del ~18 % del recorrido del hero |
| A3 | Intentar pulsar el CTA **mientras entra** | No se puede hasta que está casi entero. Es a propósito: pulsar algo medio invisible es un accidente |
| A4 | Volver arriba del todo | Se van los tres, con la misma curva |
| A5 | Seguir bajando y **subir de golpe** | El armazón vuelve al subir (2c·2), y **dentro del hero no se retira nunca**: si no, entraría y saldría a la vez |

### B · El CTA doble, que es un PAR

| | Qué hacer | Qué tiene que pasar |
|---|---|---|
| B1 | Mirarlo sin tocarlo | «RESERVAR / desde X €» **ancho** (224 px) y la cuenta reducida a **su icono** (56 px). Las dos miden **lo mismo de alto** |
| B2 | Esperar unos segundos | La mitad colapsada **asoma** y un **aro late** a su alrededor, cada 4,6 s |
| B3 | Pulsar **una vez** la colapsada | **Intercambian**: la de la cuenta se abre y comprar se reduce a su icono. El rótulo que entra **espera un instante** a que le hagan sitio — no se cruzan |
| B4 | Volver a pulsarla | Ahora **actúa**: abre el cajón en la cuenta |
| B5 | Pasar el ratón por encima | **El botón NO se mueve.** Cambia de color; el salto se retiró |
| B6 | Abrir el menú (☰) | Comprar pasa a **amarillo aviso** y **la cuenta se queda BLANCA** — no se tiñe. La ☰ se convierte en X |
| B7 | Abrir el menú **desde arriba del todo** | El racimo de la derecha **aparece igual**: si no, el menú tapa la página y te deja sin comprar **y sin la X** |

### C · La marca del cliente

| | Qué hacer | Qué tiene que pasar |
|---|---|---|
| C1 | Mirar la pestaña del navegador | El icono del parque, no la «J» del producto |
| C2 | Mirar el logotipo tras el scroll | El lockup a color, sobre fondo claro |
| C3 | **Abrir el menú y mirar el logotipo** | Cambia a su **variante BLANCA**. Es la pieza nueva: una imagen no se adapta al fondo como lo hacía el texto |
| C4 | **En un móvil real**, «añadir a pantalla de inicio» | El icono del parque. Es lo que el alcance anterior (solo SVG) no daba |

### D · Sin JavaScript (una vez, y basta)

Desactiva JavaScript y carga `/`: **el armazón sale entero desde el primer píxel**. Si no sale,
falta el `<noscript>` y la portada se queda sin navegación para quien no ejecuta scripts.

### Lo que el guion NO cubre y hay que mirar con el ojo
- **La curva de entrada**: el guion mide dos puntos, no el camino. Si «salta» en vez de posarse, se
  toca `--nav-reveal-span`, que es un token.
- **Si el hero encoge bien con los dos botones dentro**: el contenido creció y el hero es la misma
  caja de antes.
- ⚠️ **El logotipo se pinta a 26 px de alto y tu mockup lo pinta a 54.** Medido, **no cambiado**: no
  estaba en el encargo. Si a tu ojo se ve pequeño, es una línea.

## §5.octodecies · EL PULIDO DE LOS OCHO PUNTOS — ✅ recorrido en headless el 2026-08-28 (38 ✓ el cajón · 15/15 la puerta · 4 sondas), pendiente del OJO del owner (`DECISIONES #217`)

> Carril A. Lo que el owner pidió tras su prueba en staging, en el orden en que se ve. Guiones dentro
> del contenedor: `/root/e2e/qr-dep-probe.js` (38 comprobaciones), `puerta-redesign-probe.js` (colores
> computados en claro y oscuro, antes y después de un `wire:update`) y el ya existente `puerta-probe.js`
> (15/15, conducta). Cliente de prueba `probe-card@jumpweb.test`.
>
> ⚠️ **Antes de empezar, mira el MODO de la exención** (Ajustes → Waiver). En **interno**, un menor sin
> su exención firmada NO se puede asignar a una entrada y su fila sale atenuada **a propósito**
> (`#202`·2): es lo que el owner confundió con «no me deja pulsar». Fírmala en «Menores a cargo» o pon
> el modo en externo.

### P1 · «Mi cuenta» en tarjetas
1. Cajón → Mi cuenta. Las ocho entradas son **tarjetas en 2 columnas**, icono grande arriba y rótulo
   debajo, sin la flecha de antes. Medido: rejilla `196px 196px`, icono computado **26×26** (el `<svg>`
   sigue con `width="18"` en el fuente — la paridad byte a byte con el sistema de diseño no se toca).

### P2 · «Mi QR» junto al nombre
2. En el panel de cuenta, bajo la cabecera: ya **no** está la línea de la próxima reserva (la sigue
   diciendo el índice, que es donde se lee sin colapsar) y a la **derecha del nombre** hay un botón
   pequeño **«Mi QR»** con el icono del código. Lleva a la zona del QR.

### P3 · El QR, presentado como credencial
3. Entra en «Mi QR»: el código va **dentro de un marco con aire**, el número debajo en un bloque
   legible, «Descargar (PNG)» como acción principal y **«Renovar mi QR» discreta**. Medido a 380 px de
   cajón: recuadro 264×264, imagen 234 px, **cero desbordes** (`scrollWidth == clientWidth`).
4. **El QR lleva el icono de la instalación en el centro, con margen**. Escanéalo con el móvil: tiene
   que leer igual. ▶ Y **descarga el PNG y pásalo por el LECTOR del recinto**: eso es lo que ninguna
   suite puede medir.
5. Pulsa «Renovar mi QR»: sale un aviso permanente («el QR anterior dejará de funcionar…») y una
   **confirmación dentro del cajón** (ya no el diálogo del navegador). Al confirmar, el código y la
   imagen cambian; el anterior deja de valer en la puerta.

### P4 · Menores a cargo
6. Zona «Menores a cargo»: el formulario de alta **ya no está desplegado**; hay un botón **«Añadir
   menor»** que lo revela con el foco en el primer campo y lo cierra al guardar.
7. Con **más de 6** menores aparece el paginador (el mismo de «Mis pedidos»); con dos o tres **no
   aparece nada**. El número sale de medir la tarjeta: 196 px cuando solo informa y 322 mientras la
   exención está sin firmar.

### P5 · Asignar menores en la compra
8. Compra una entrada. En el paso de la hora y en el carrito, el bloque **«¿Para quién son estas
   entradas?»** es ahora una sección con una fila por menor (nombre · edad · estado de la exención).
   La fila que **no se puede marcar** se ve atenuada (`opacity 0.55`, `cursor: not-allowed`) y su
   motivo va **en su propia línea**, alineado bajo el nombre (sangría medida: 33 px, incluidos los 7 px
   que Chromium le pone al checkbox por su cuenta).

### P6 · La ficha del cliente en el panel
9. Panel → Usuarios → un cliente con menores: sección **«Menores a cargo»** con nombre, **edad de hoy**,
   exención con su versión, cuándo se declaró y si está retirado. Una cuenta anonimizada no lista a
   nadie y dice dónde mirar.

### P7 · La pantalla de puerta
10. `/admin/puerta/validar`: **la paleta ya no está rota** (antes los grises y el color de marca
    computaban vacío: el texto salía negro puro y el borde del formulario negro sólido). Ahora el
    semáforo es **un solo bloque de color**, la ficha va en tarjetas (Hoy · Otros días · Exención · QR ·
    Menores · Visita), el foco del buscador es del **color de tu marca** y la tipografía es la del panel.
11. ⚠️ **El modo oscuro está activo** (sigue el mismo ajuste que el panel). Es una decisión del agente:
    las variantes estaban escritas y muertas. Si no lo quieres, se revierte en 8 líneas.
12. Los menores siguen sin nombre: **edad y estado de la exención**, nunca más.

### Lo que el guion no cubre y hay que mirar con el ojo
- **El lector real** con el PNG descargado de la web (no solo con la cámara del móvil).
- **La tablet del recinto**: tamaños táctiles y la ficha completa en su pantalla.
- **El icono dentro del QR con el SVG de un cliente real**: en local no hay rasterizador de SVG en
  Imagick (sí `rsvg-convert`) y en staging al revés — el resultado se ha probado con los dos, pero con
  el icono del PRODUCTO.
- **El contraste** de los dos tonos nuevos de la puerta (`--success-*` sobre blanco y sobre gris
  oscuro): medidos los colores, no calculados los ratios.

---

## §5.novodecies · LA FECHA Y LA HORA EN MÓVIL — ✅ medido en headless el 2026-08-28 (22/22 + 2/2), pendiente del OJO del owner (`DECISIONES #239`)

El rediseño de los pasos 2 y 3 del embudo a **390×844** (`specs/cajon-en-movil.md`). Playwright dentro
del contenedor (`/home/sail/e2e/`), la web en `http://localhost`. La sonda vive fuera del árbol a
propósito: `deploy.sh` no excluye una carpeta nueva en la raíz.

### Lo que la sonda midió, y contra qué se compara

| | Antes (`#237`) | Ahora |
|---|---|---|
| Chips de día reservable | — | **182**, con separador por mes (`Ago · Sept · … · Feb 2027`) |
| Celda del calendario | 43×43 | **45×45** |
| Flecha de mes | 32×32 | **44×44** |
| Chip de día | — | **56×76** |
| Chip de hora | 68×39 | **72×44** |
| «Ver más fechas» | — | 350×44 |
| Controles bajo 44 px · fecha / hora | 11 de 12 · 12 de 13 | **0 · 0** |
| Horas visibles sin deslizar | 11 de 11 | **4 de 11** |
| Desborde horizontal de la página | — | **0 px** |

Y además: el calendario **nace plegado** con `aria-expanded="false"`, «Ver más fechas» lo abre y lo
anuncia; la tira **desplaza de verdad** (11.535 px de contenido en 390 de carril); al **volver** con el
día 40 elegido el carril se coloca en él (`scrollLeft 2445`, chip a la vista); y el clic **10 px por
encima** del «Volver» sigue siendo suyo.

El camino completo del aviso —panel → `/config` → store → chip— con el umbral a **100**: **11 de 11**
horas con «Casi llena» y el rótulo literal correcto. Con el umbral por defecto (8) y 40 plazas libres:
**ninguna**, que es lo correcto.

⚠️⚠️ **DOS instrumentos propios salieron mal antes de acertar.** Medir la CAJA PINTADA daba «Volver»
como defecto (61×17) cuando su área táctil son 45 px —llamaba defecto a la solución—; medirlo con
`elementFromPoint()` daba **178 defectos** en la tira, porque los chips fuera del carril están fuera
del VIEWPORT y ahí no hay nada que golpear. Lo que vale es geometría: la caja **más el pseudo-elemento
que amplía el área**.

### ❗ Lo que tiene que mirar el OWNER (un headless mide, no valida)

1. **Deslizar las dos tiras con el dedo**: que el ajuste no pelee con el impulso y que el chip cortado
   por el borde de la pantalla se lea como «hay más».
2. **La hora enseña 4 de 11**: decir si compensa. Está discutido y decidido en
   `specs/cajon-en-movil.md` §4.2, con el número que lo motivó.
3. **«Ver más fechas»** → el calendario, elegir un día de dentro de tres meses, y volver.
4. **«Casi llena»**: subir el umbral en *Ajustes → Aspecto y opciones de la web*, ver el rótulo, y
   dejarlo donde quiera. `0` lo apaga.
5. **El hueco vertical** (`specs/cajon-en-movil.md` §7.4): 366 px vacíos en fecha y **452 en hora**.
   Está medido y sin resolver a propósito — rellenarlo es decisión suya.

---

## §5.vicies · LAS FLECHAS DE LAS TIRAS y las fichas de hora — ✅ medido en headless el 2026-08-28 (11/11), pendiente del OJO del owner (`DECISIONES #241`)

La vuelta del owner sobre `#239`: «en la fecha en desktop el UX se queda a medias, no hay manera de
deslizar con el ratón y no hay flechas». Sonda `sonda-flechas.mjs`, dos contextos en la misma corrida.

| | |
|---|---|
| **Escritorio 1440** · fecha | la flecha SIGUIENTE se ve; la ANTERIOR está apagada al principio |
| | pulsarla **mueve la tira** (scrollLeft 0 → 367) y aparece la ANTERIOR |
| | al final del recorrido la SIGUIENTE **se apaga** |
| **Escritorio 1440** · hora | la tira de horas también la tiene, y también mueve |
| **Móvil 390 con `hasTouch`** | **no se pinta ninguna flecha** — el dedo ya desliza |
| Móvil | chip de día **56×76** · chip de hora **76×76** · **4 de 11** horas visibles |

⚠️⚠️ **La primera corrida encontró las flechas MUERTAS, y el defecto era real**: el cableado se
enganchaba en `onMounted` y el carril vive dentro de un `v-if` que espera la oferta del servidor, así
que al montar el componente **el nodo todavía no existía**. Medido: 11.535 px de recorrido en un
carril de 440 y la flecha oculta por su propio `v-show`. Arreglado observando el NODO.

⚠️ **Y una trampa de la propia sonda**: contó la flecha (32×32) como control por debajo del mínimo
táctil. No lo es — solo existe dentro de `@media (hover: hover) and (pointer: fine)`, o sea que **con
el dedo no se pinta**, y los 44 px son del puntero grueso.

### ❗ Lo que tiene que mirar el OWNER

1. **Con el ratón**, en la portada: deslizar las dos tiras con las flechas y con la rueda.
2. **Con el dedo**, en el móvil: que NO aparezca ninguna flecha y que el gesto siga igual.
3. Las **fichas de hora** al tamaño de las de fecha: si le compensa ver 4 de 11 con ese peso.

---

## §5.unvicies · LOS MENORES Y EL BUG DE «MI CUENTA» — ✅ medido el 2026-08-29 (16/16 + reproducción en los dos sentidos), pendiente del OJO del owner (`DECISIONES #242`)

### El selector de menores, a 390×844 (`sonda-menores.mjs`, 16/16)

Tres menores declarados y una línea de **una** entrada, que es el caso que el owner describió:

| | |
|---|---|
| Antes de marcar | 0 filas apagadas · ninguna línea de «no caben más» · **ningún «exención firmada»** |
| Al marcar el primero | las **otras dos se apagan** (opacidad **0,55**) y sus casillas quedan deshabilitadas |
| | el aviso **«1 entrada asignada»** aparece **en la fila marcada** |
| | y es SUTIL: **10 px** frente a los 13 del nombre, sin mayúsculas forzadas |
| Al desmarcar | las filas apagadas **vuelven** |

### El bug de «Mi cuenta» (`repro-cuenta.mjs`), medido en los DOS sentidos

Con sesión y una línea en la cesta, pulsando el chip de cuenta de la cabecera en la portada:

| | |
|---|---|
| con el código de antes | `is-cart` · título **«Tu carrito»** |
| con el arreglo | `is-account` · título **«Mi cuenta»** |

⚠️⚠️ **Dos trampas antes de poder reproducirlo**: el racimo **nace bajo el hero** (`#216`), así que un
clic forzado sobre un contenedor con `pointer-events: none` **no dispara nada** —el estado no se movía
y parecía un fallo del arreglo—; y `.sidecart__panel` lleva su clase de modo **también con el cajón
cerrado**, así que leerla sin comprobar `is-open` no dice nada.

### ❗ Lo que tiene que mirar el OWNER

1. Con **tres menores y una entrada**: marcar, ver los otros dos apagados, desmarcar y verlos volver.
2. Pulsar **«Mi cuenta»** desde la portada **con algo en el carrito**: tiene que abrir su cuenta.
3. Y desde el **pie**, que es una navegación normal: también.

---

## §5.duovicies · EL OBJETIVO TÁCTIL DE 44 EN LA LANDING — ✅ medido el 2026-08-29 (37 → 1), pendiente del OJO del owner (`DECISIONES #264`)

> Recorre las **siete vistas públicas renderizables** a 390×844 con `hasTouch` y mide el **área
> efectiva** de cada control: su caja, más los pseudo-elementos absolutos que la amplían, menos lo
> que le recorte un ancestro. Guion: `/home/sail/e2e/tap44.mjs`.

❗❗ **LEE ESTO ANTES DE CREERTE UN NÚMERO SUYO.** Esta sonda salió mal **dos veces** y las dos daban
cifras plausibles:

1. **Recortar en coordenadas de viewport** con el control desplazado fuera de la parte visible de su
   carril devuelve un área **negativa**, y un negativo pasa el filtro de «menor que 44» como si
   fuera un defecto. Salieron anchos de **−652**.
   ▶ Solo se recorta contra un ancestro que **de verdad contiene la caja del control**.
2. **El rectángulo de un pseudo-elemento no está donde dicen su `top` y su `left`: está donde lo
   deja su `transform`.** Sin aplicarlo, el `translate(-50%, -50%)` del área se perdía y salían
   altos de **58** donde son 44, más siete solapes inventados.
   ▶ La matriz calculada trae los porcentajes ya resueltos a px: `matrix(a,b,c,d,tx,ty)`.

⚠️ Y **dos controles en capas distintas se solapan siempre** en coordenadas de viewport: con el menú
abierto la FAQ sigue detrás. El filtro es `checkVisibility()` + `elementFromPoint(centro)`, usados
**como filtro** y no como medida — que es la diferencia con el error de §5.novodecies, donde
`elementFromPoint` se usó *para medir* y dio 178 falsos.

### Lo que dice, corrido el 2026-08-29

| | Antes | Ahora |
|---|---|---|
| Controles distintos bajo 44 | **37** | **1** — el enlace en línea del texto de cookies, exento por WCAG |
| `[data-tap]` recortados por un ancestro | — | **0** |
| Solapes entre áreas | 4 | 11, **todos con ganador inequívoco** (en 9 gana la pieza flotante) |
| Pie a 390 px | 365 | **335** · bloque legal 78 (3 renglones) → **44** (una tira) |
| Pie a 1280 px | 287 · 40 · 53 · 14 | **idéntico** |
| Desborde horizontal | — | **0 px** en las siete |

▶ **La pasada de CONTROL no es opcional aquí**, y esta tanda lo pagó: el barrido recorre la página
por **fracciones de su alto**, y como el pie encogió 30 px las mismas fracciones caen en otro sitio
— cuatro de los siete solapes «nuevos» eran solapes viejos que el barrido anterior no había
visitado. *Un barrido relativo no compara con el de antes salvo que la página mida lo mismo.*

⚠️ El `skip-link` **no lo ve esta sonda**: solo es visible con foco de teclado. Se mide aparte
(`Tab` y leer su caja): **154×44**.

### ❗ Lo que tiene que mirar el OWNER

1. **El pie en un teléfono de verdad**: los dos carriles —destinos y legales— se deslizan con el
   dedo, la vela dice que siguen, y ningún enlace se queda inalcanzable.
2. **La FAQ**: pulsar 8 px por encima del texto de una pregunta ya la abre. Es el área invisible.
3. **El menú**: las cápsulas y el desplegable de idioma miden 44 y se ven así — ahí sí se creció.
4. **Que en el ordenador no ha cambiado nada del pie**, que es donde manda el mockup.

---

## §5.tervicies · EL SALTO DEL LOGOTIPO — ⚠️⚠️ **ESTA SECCIÓN ESTÁ CORREGIDA POR §5.quatervicies: su «0,000 px» NO ERA UNA MEDIDA EN PÍXELES** (`DECISIONES #266`)

> ❗❗❗ **LEE LA CORRECCIÓN ANTES QUE EL TEXTO.** Lo que sigue describe un guion que valida la FORMA
> de la curva y es **ciego a la amplitud**: normaliza la escala fuera (`const escala =
> muestras[0][1] / 90` y luego `y / escala`), así que su primer punto vale 90 por definición y la
> cifra resultante es **adimensional**. Además lee el `transform` computado de `#fig`, que vive en
> `<defs>` y **no se pinta**, mientras el texto afirma que lee «el transform pintado».
> ▶ Con él en verde, el salto **no se veía en absoluto** y su amplitud estaba **7,5 veces corta**.
> **La sección viva es §5.quatervicies.** Ésta se conserva porque la lección es el guion, no el número.

### El texto original de `#265`, conservado tal cual

> Guion: `/home/sail/e2e/logo.mjs`. **No compara capturas: compara TRAYECTORIAS.** Reimplementa la
> animación del mockup —sus ocho fotogramas y sus siete curvas de Bézier, resueltas por bisección—,
> fija `currentTime` en 21 puntos del recorrido y lee el `transform` pintado en cada uno.

▶ **Por qué así y no con una captura**: el defecto que motivó la tanda era que las posiciones
coincidían y **el movimiento entre ellas no**. Cualquier captura de un fotograma clave habría salido
idéntica antes y después; lo que cambia es la curva de en medio.

| | Resultado |
|---|---|
| Desviación máxima frente a la fórmula del mockup | **0,000 px** (21 muestras) |
| Vuelo / espera / asentamiento | **1000 / 466,7 / 1166,7 ms** — los suyos, con el factor `v = 0.9` |
| Curvas declaradas por tramo | **7 de 7** |
| Hover del logotipo tras la coreografía | vivo: `rotate(-1.5deg) translateY(-2px)` |

⚠️⚠️ **DOS TRAMPAS DE ESTE GUION, las dos pagadas:**
1. **`matrix(1, 0, 0, 1, 0, 0)` NO es «no hay transform»: es la identidad.** La primera versión
   preguntaba «¿tiene transform?» para comprobar el hover, respondía que sí, y el hover estaba
   muerto —lo mataba un `fill: both`—. El criterio es **comparar reposo con hover**, no ver si
   existe.
2. **La vista que se abre importa.** El salto se mide en `/servicios` y no en la portada: allí el
   armazón nace bajo el hero y la animación espera a `.nav--live`, así que al cargar no se dispara.
   Es el reverso de `#263`.

### ❗ Lo que tiene que mirar el OWNER

1. **Recargar `/servicios`** y ver el salto entero: entra desde abajo, sube, cae, aplasta, rebota
   dos veces y **el logotipo entero se hunde 2 px** al recibirlo.
2. **Pasar el cursor por encima** después: tiene que seguir levantándose y girando.
3. **El CTA flotante en un teléfono**: mide 56 y su sombra es la misma que la del botón de arriba.


---

## §5.quatervicies · EL SALTO DEL LOGOTIPO, MEDIDO EN PÍXELES Y CON CONTROL — ✅ 2026-08-29 (`DECISIONES #266`)

> Guion: `/home/sail/e2e/ver.mjs`. **No lee estilos computados: compara PÍXELES**, y trae un
> **control** que demuestra que el instrumento sabe detectar movimiento antes de creerle un cero.

❗❗❗ **POR QUÉ HACE FALTA UN CONTROL, y es la lección más cara de este carril.** Tres tandas
midieron que la animación del logotipo estaba declarada (`#254`), que existía en las doce vistas
(`#263`) y que su valor computado recorría la curva del mockup (`#265`). Las tres en verde, y **el
logotipo no se movía**: la animación caía sobre `#fig`, que vive dentro de `<defs>` y no se dibuja.
▶ *Que una animación exista y compute no es que el dibujo se mueva.* Y un cero sin control no
distingue «no se mueve» de «no lo estoy mirando bien».

### Cómo mide

1. Congela **todas** las animaciones del documento menos `brand-hop` —si no, el asentamiento corre
   en tiempo real y sus 2 px de desplazamiento ensucian cada captura: eso ya produjo un falso
   «sí se mueve» de 993 px.
2. Fija `currentTime` en varios puntos del recorrido y captura la **página entera** (no el
   elemento: `locator.screenshot()` recorta a su caja y ahí un salto que se sale no se ve).
3. Difiere contra el reposo y reporta **cuántos píxeles** cambian **y en qué banda vertical**.
4. **Control**: mueve el grupo a mano con `style.transform` y comprueba que eso sí repinta.

### Lo que dijo, corrido el 2026-08-29

| instante | píxeles distintos | banda vertical | qué significa |
|---|---|---|---|
| 0,05 · entrando | 492 | y **19-96** | la silueta está abajo, fuera del logotipo |
| 0,38 · cima | 835 | y **0-53** | arriba, sale por encima |
| 0,70 · aplasta | 665 | y 19-57 | |
| 0,91 · último rebote | 546 | y 19-55 | |
| **CONTROL** (a mano) | **871** | y 19-96 | el instrumento sabe ver movimiento |

▶ **La banda vertical cambia con el instante**: eso es movimiento, no aparición y desaparición.
▶ Amplitud: `translateY(300%)` mueve la figura **84,67 px** = 3,00 × sus 28,22 px pintados, que es
el ratio del mockup (90 px sobre una silueta de 30).

⚠️ **Y una trampa del sujeto**: en `/servicios` hay **dos** `.nav__brand-logo--inline` —el del
armazón y el del cajón, que está a 0×0 y oculto—. Un `querySelector` sin acotar a `.nav` mide el
que no se ve.

### ❗ Lo que tiene que mirar el OWNER

1. **Recargar `/servicios`**: el saltador entra desde abajo, sube por encima del lockup, cae,
   aplasta, rebota dos veces y **el logotipo entero se hunde 2 px** al recibirlo.
2. **Pasar el cursor por encima** después: tiene que seguir levantándose y girando.

---

## §5.quinvicies · LA SOMBRA DEL LOGOTIPO, CONTRA EL LOCKUP DEL MOCKUP — ✅ 2026-08-29 (`DECISIONES #267`)

> Guion: `/home/sail/e2e/somb/somb.html` + `medir.mjs`. **Renderiza el lockup del owner y el nuestro
> en la MISMA página** y compara la densidad de sombra sobre el mismo papel.

❗❗❗ **POR QUÉ HACE FALTA, y es la lección de tres vueltas del owner.** `#253` y `#263` ajustaron
nuestro filtro y lo compararon **consigo mismo** —`#263` llegó a comparar «cuatro combinaciones en
el navegador, a tamaño real»: cuatro variantes **nuestras**—. El original no entró en ninguna de las
dos, y la sombra acabó con **la mitad** de densidad que la suya.
▶ *Comparar variantes propias entre sí no es comparar con el original.* Se pudo hacer solo cuando el
owner entregó su lockup en HTML, que es lo que permite renderizarlo al lado.

### Cómo mide

Cuatro filas sobre el mismo papel (`#F4EFE3`), todas capturadas juntas:
**A** su lockup con su filtro · **B** nuestro SVG con SU filtro · **C** nuestro SVG con el filtro a
evaluar · **D** nuestro SVG sin filtro (control: la sombra que ya trae horneada).

Para cada fila cuenta los píxeles **oscurecidos entre 2 y 70 de luminancia** respecto al papel: por
debajo de 2 es ruido, por encima de 70 es la tinta del dibujo. Suma esa diferencia → **densidad**.

| | densidad | lectura |
|---|---|---|
| **A** · su lockup, su filtro | 1.193.218 | la referencia |
| **B** · nuestro SVG, su filtro | 1.252.968 | **5 % de A** → el sujeto no es el problema |
| **C** · el filtro de `#263` | 645.997 | **la mitad** |
| **D** · sin filtro | 207.507 | solo el relieve horneado |

⚠️ **La fila D no sobra**: sin ella no se sabe cuánta de la sombra medida es del filtro y cuánta del
relieve que el SVG ya trae. Un control por abajo, como el de §5.quatervicies por arriba.

⚠️ **Y el mojibake**: el bloque HTML del owner llegó pegado en el chat con la codificación rota
(`â` por guiones, `Ã±` por ñ) y **no es reparable con un `latin-1 → utf-8`**: el daño es mixto. Solo
afecta a la prosa —el CSS y el marcado son ASCII—, así que para medir se extrajo lo técnico y se
descartó el texto. **No se guardó el fichero pegado como activo**, que es lo que `#262` §25.1 ya
advirtió.

---

## §5.sexvicies · EL INTERRUPTOR DEL TITULAR PARA Y DESCANSA ENCENDIDO — ✅ 2026-08-30 (`DECISIONES #280`)

> Tres sondas, cada una con su CONTROL. Corren desde `/home/sail/e2e` (receta de §5.bis) contra
> `http://localhost/`. ⚠️ El contexto se abre con **`reducedMotion: 'no-preference'`**: headless
> declara `reduce` por defecto y con él **no hay ninguna animación que medir**.

### 1 · El defecto de partida (sonda `sw-base.mjs`, antes de tocar el CSS)

Acotando las iteraciones sobre el CSS de entonces, sin promover el reposo:

| | pista | bulbo | rótulo |
|---|---|---|---|
| en marcha (`infinite`) | — | — | — |
| **acotado a 2 ciclos** | `rgba(0,0,0,0)` + borde `rgb(154,161,168)` | `rgb(154,161,168)`, `translateX(0)` | **`opacity: 1`** |
| **CONTROL** (`reducedMotion: 'reduce'`) | `rgb(95,168,46)` = `--ok` | `rgb(16,20,24)`, `translateX(48,09)` | `opacity: 1` |

⚠️⚠️ **No es «se queda apagado»: es la palabra ON encendida sobre un interruptor apagado.**
⚠️ Y ya aquí se ve lo que decide el diseño del rearranque: al terminar, **`getAnimations()` devuelve
`[]`**.

### 2 · Que para donde descansa (sonda `sw-para.mjs`)

| punto | resultado |
|---|---|
| primer paint (`waitUntil: 'commit'`) | **apagado** — el reposo promovido no destella |
| 9 % del ciclo | apagado — **control** de que la animación corre de verdad |
| justo antes de parar (t = 2,6 ciclos) | encendido, 1 animación viva |
| ya parado | encendido, **0 animaciones** |
| **¿coinciden?** | **SÍ — 0 px de salto** en las tres piezas |
| **CONTROL** `reduce` | mismo reposo encendido, 0 animaciones |

### 3 · El rearranque y sus dos controles (sonda `sw-rearranca.mjs`)

| gesto | animaciones | veredicto |
|---|---|---|
| parado arriba | 0 | reposo |
| **CONTROL: quedarse quieto 1,2 s** | 0 | no rearranca solo |
| bajar hasta perder el hero | 0 | — |
| **volver arriba** | **1, `running`, `currentTime` 350 ms** | **rearranca ✓** |
| dejar que termine otra vez | 0 | — |
| **CONTROL: temblor** (30 · 0 · 45 · 10 · 0 px, sin salir) | **0** | no es un tic ✓ |
| **CONTROL: `reduce`, ida y vuelta** | **0**, pista encendida | el rearranque es un no-op ✓ |

### 4 · El presupuesto de movimiento, antes y después (sonda `sw-presupuesto.mjs`)

El «antes» se reproduce **inyectando `animation-iteration-count: infinite`** sobre las tres piezas,
que es exactamente lo que había; se espera a que pasen los 2,6 ciclos para contar solo lo permanente.

| | `/` | `/entradas` |
|---|---|---|
| antes (`infinite`) | 5 | 6 |
| **ahora** | **2** — cumple el techo de dos de su artboard | **4** |

⚠️ **Corrige a `#279`**: el interruptor eran **3** bucles, no 2. Su tabla contó lo que la sonda veía
en un instante y el rótulo pasa por `opacity: 0` en parte del ciclo, donde la sonda —con razón— no lo
cuenta como visible. *Contar animaciones en un instante subestima una pieza cuyo ciclo apaga una de
sus partes*: hay que **unir las muestras de varias paradas**.

⬜ **Pendiente del OJO del owner**: un headless mide, no valida (`CONVENCIONES §3.bis`). Lo que hay
que mirar es que los tres saltos se lean como una invitación y no como un tic, y que el último
asiente sin respingo.

---

## 5.sexies · EL LIBRO EN EL CAJÓN (2026-09-01) — guion de la T3·4 del libro (`DECISIONES #314`)

> **Por qué existe este bloque.** Con la T3·4 el modelo de dos ejes ya no está en el árbol: lo que
> «Mis pedidos» pinta sale de `OrderBook` por la API (`GET /api/v1/me/orders` → `ledger`) y lo
> transcribe `PurchaseCard.vue`. La paridad cajón ↔ API la vigila un test de Vue con datos FALSOS;
> lo que ningún test del repo ve es **el recorrido entero por HTTP real con pedidos que nacieron por
> `OrderCreator`** —las cuatro puertas de `desglose-dinero-cliente.md` §4.quater incluidas— y que el
> libro que llega al navegador es el mismo que el dominio compone. Eso es lo que aquí se mide.
>
> ✅ **Recorrido en headless el 2026-09-01: 17 comprobaciones, 17 ✓, 0 ✗** — y ampliado la misma
> mañana a **11 pedidos en cuatro páginas, 54 ✓, 0 ✗** (los siete de demostración, abajo). Queda el
> OJO del owner (V18–V21, al final).

### Los cuatro pedidos (sembrados por el dominio, para `probe-card@jumpweb.test`)

`seed-libro.php` (scratchpad de la sesión; se corre con `tinker --execute='require "/tmp/seed-libro.php";'`
tras `docker compose cp`) borra los `LB-*` anteriores y crea, con `OrderCreator::createPendingOrder`
sobre la primera franja abierta que el creador acepte (≥ 7 días, zona del producto), cobrando por
`Payment` el `onlineDueCents()` del pedido:

| Código | Qué es | Libro que compone el dominio |
|---|---|---|
| `LB-SENAL` | Cumpleaños Kids × 8 (señal 30,00) | Total 88,00 · Pagado 30,00 · **a pagar en el parque 58,00** |
| `LB-BAJADA` | Jump · 1 hora × 3 pagadas, reducidas a 1 (`recordEdit(−19,80)`) | Total 9,90 · Pagado 29,70 · **a devolver en el parque 19,80** |
| `LB-CANCEL` | Jump · 1 hora × 2 pagadas y el pedido cancelado (`cancelLiveItems`) | Total 0,00 · Pagado 19,80 · **pendiente de devolución 19,80** |
| `LB-MIXTA` | Cumpleaños Jump × 8 (señal 30,00); edades `8·9·9·9·9·9·9·4` por `submitGuestForm` → un invitado corresponde a Kids | Total 116,00 · Pagado 30,00 · **a pagar en el parque 86,00** (descuento −4,00 como línea) |

Los cuatro cierran (I1–I4) al sembrarlos; el propio guion lo imprime.

### La sonda (`/root/e2e/libro-probe.js` del contenedor, `BASE=http://localhost`)

1. Entra por `/mi-cuenta/pedidos` con el cliente de prueba. ⚠️ Tras el login el cajón puede quedarse
   en la zona de pedidos (la señal de la URL) **o en el índice de la cuenta**: la sonda espera a
   cualquiera de los dos y, si es el índice, pulsa la tarjeta «Mis pedidos». La primera versión
   esperaba solo `.orders__item` y abortó por *timeout* con el producto sano — el instrumento, otra vez.
2. Lee `GET /api/v1/me/orders` con la sesión del navegador.
3. Por cada `LB-*`: abre su desglose (`.orders__gate-toggle`) y recoge **movimientos**
   (`.orders__mov` del bloque de valor), **liquidaciones** (`.orders__ledger--cash .orders__mov`),
   **Total/Pagado** (`.orders__final`) y **el saldo con su clase** (`.orders__balance--<kind>`).
4. Comprueba: que el pedido aparece; que hay tantos movimientos pintados como en `ledger.movements`;
   que **cada etiqueta** de la API está pintada; y que la clase del saldo es `ledger.balance.kind`.

### Lo que pintó el cajón (literal)

| Pedido | Movimientos | Liquidaciones | Total · Pagado | Saldo |
|---|---|---|---|---|
| `LB-SENAL` | Reserva realizada · 01/09/2026 **+88,00 €** | Pagado online · 01/09/2026 +30,00 € | 88,00 · 30,00 | `pay_at_park` · «A pagar en el parque 58,00 €» |
| `LB-BAJADA` | Reserva realizada +29,70 € · **Cantidad: 3 → 1 −19,80 €** | Pagado online +29,70 € | 9,90 · 29,70 | `refund_at_park` · «A devolver en el parque 19,80 €» |
| `LB-CANCEL` | Reserva realizada +19,80 € · **Cancelado: Jump · 1 hora · 2 entradas −19,80 €** | Pagado online +19,80 € | 0,00 · 19,80 | `refund_pending` · «Pendiente de devolución 19,80 €» |
| `LB-MIXTA` | Reserva realizada +120,00 € · **Descuento por 1 invitado que corresponde a Cumpleaños Kids −4,00 €** | Pagado online +30,00 € | 116,00 · 30,00 | `pay_at_park` · «A pagar en el parque 86,00 €» |

▶ Es el libro de `#305` tal cual: una línea por gestión con su fecha y su signo, un Total, lo Pagado y
UN saldo con su clase — sin «pendiente de devolución» y «a cobrar en puerta» conviviendo, sin «a tu
favor», sin canales. La frase del descuento mixto es la de `MovementLabel::mixed` (con el nombre del
pack destino), y la de la cancelación lleva el sustantivo (`2 entradas`).

### Los pedidos de DEMOSTRACIÓN para el owner (2026-09-01, misma sesión)

A petición del owner —«crea pedidos de prueba para que vea los huecos y cómo funciona el sistema»—
`seed-libro-2.php` (scratchpad) añade SIETE más al mismo cliente, cuatro del sistema funcionando y
tres de los huecos que `specs/desglose-libro.md` §6.3.7 y `DECISIONES #315` dejan escritos:

| Código | Qué enseña | Libro |
|---|---|---|
| `LB-DEVUELTO` | Se devuelve EXACTAMENTE lo debido (3 → 1, se devuelven 19,80 en modo manual): sin cortesía | Reserva +29,70 · Cantidad 3 → 1 −19,80 · **Devuelto en el parque (registrado) −19,80** · Total 9,90 = Pagado 9,90 · saldado |
| `LB-CORTESIA` | Se devuelve MÁS de lo debido (4 → 2, debidos 19,80, devueltos 29,70) | … · **Compensación −9,90** · Total 9,90 = Pagado 9,90 · saldado |
| `LB-SUPLEMENTO` | Fiesta Kids × 8 con un invitado de 8 años | **Suplemento por 1 invitado que corresponde a Cumpleaños Jump +4,00** · a pagar en el parque 62,00 |
| `LB-COMPLEMENTO` | 2 entradas pagadas + 2 pares de calcetines añadidos desde el panel | **+2 Calcetines antideslizantes +4,00** · a pagar en el parque 4,00 |
| `LB-ORDEN` ⚠️ hueco 4 | El operador **devuelve ANTES** de registrar la bajada: con la línea aún en 3 no se debe nada, los 19,80 salen como «Compensación», y la bajada posterior vuelve a restar 19,80 | **Total −9,90** · Pagado 9,90 · «a devolver en el parque 19,80» de dinero YA devuelto. **El libro cierra (I1–I4) y aun así miente**: el orden de las gestiones importa y nadie avisa |
| `LB-PUERTA` ⚠️ hueco 1 | Visita de AYER, pedido pagado con señal | **«Liquidado en el parque 58,00»** sin que nadie registrara lo que cobró la recepción: es inferido |
| `LB-REVISION` ⚠️ huecos 2 y 3 | `Order.total` fabricado (9,90 con dos entradas de 9,90) | «Este desglose no cuadra» en el panel; el cliente ve solo lo cobrado y la frase de revisión; **no hay acción para corregirlo** |

✅ **Sonda ampliada a los 11 `LB-*`, recorriendo las CUATRO páginas de «Mis pedidos»: 0 fallos** (54
comprobaciones). ⚠️ Dos lecciones del instrumento: (1) «Mis pedidos» pagina de **5 en 5** — la sonda
que solo leía la primera página dio seis «no aparece» con el producto sano; (2) `settled`, `expired` y
`under_review` **no llevan línea de saldo en el cajón** (por diseño, `orders.js`) y un pedido en
revisión **no pinta movimientos** (T3·1: Total, cobros y la frase): la sonda que esperaba línea y
movimientos en esos dos casos daba tres ✗ falsos. ⚠️ Cosmética de la siembra: pago y devolución en
el MISMO segundo salen con la devolución primero (empate en `occurred_at`); en producción median días.

### Lo que este guion NO cubre → el OJO del owner (T3·4b)

- **V18 · el panel**: `ViewOrder` de `LB-MIXTA` (bloque «Totales del pedido», la tarjeta de la reserva,
  el modal del calendario) y la lista de pedidos con Total/Pagado del libro. La sonda no entra en
  `/admin` a propósito: el login del panel tiene limitador y una sesión que lo agota mide la pantalla
  de LOGIN creyendo medir el pedido.
- **V19 · la hoja PDF** con precios de `LB-SENAL` (líneas + libro + UNA caja de saldo) y **V20 · la
  puerta** con `LB-BAJADA` (la tarjeta «a devolver» con la misma alerta que «a cobrar»).
- **V21 · los correos** en Mailpit (`:8028`): reenviar la confirmación de `LB-SENAL` y de `LB-CANCEL`
  desde el panel y leer el bloque del libro en Gmail/Outlook (la tabla `data-book-*`).
- Los pedidos `LB-*` **quedan vivos en local** (retienen aforo real de franjas abiertas y llevan
  `event_data`/edades ficticios marcados como tal); se borran con
  `Order::where('code','like','LB-%')->get()->each->delete()`.

### La T4 sobre los mismos pedidos (2026-09-01, `DECISIONES #317`)

`seed-libro-t4.php` (scratchpad de la sesión de la T4; se corre igual que los anteriores) borra
`LB-ORDEN`, `LB-BAJADA`, `LB-CORTESIA` y las tres sondas viejas de «en revisión» (`T4-PRB01`, `R-IBX8B1`,
`R-D3AN8Q` — `#316` decisión 4) y re-siembra los tres por el dominio:

| Código | Lo que hace el guion | Libro que compone el dominio |
|---|---|---|
| `LB-ORDEN` | Intenta «devolver lo que se le debe» (19,80) ANTES de registrar la bajada → **`exceeds_owed`, ni una fila**; registra la bajada 3 → 1 y devuelve 19,80 como lo debido | Reserva +29,70 · Cantidad 3 → 1 −19,80 · Devuelto en el parque (registrado) −19,80 · **Total 9,90 = Pagado 9,90 · saldado, sin cortesía** (era Total −9,90) |
| `LB-BAJADA` | La misma bajada con la franja movida a AYER | Reserva +29,70 · Cantidad 3 → 1 −19,80 · **«Devuelto en el parque» −19,80 (inferido, D9 bis)** · Total 9,90 = Pagado 9,90 · **saldado** (era «a devolver en el parque» para siempre) · `owedToCustomerCents() = 19,80` (D-T4·6) |
| `LB-CORTESIA` | 4 → 2 (debidos 19,80), se devuelven 29,70 como COMPENSACIÓN con motivo | … · **«Descuento por cortesía» −9,90** con el motivo debajo en el panel (`data-book-note`) y **sin motivo en la API** · saldado |

✅ **Sonda `libro-probe.js` sobre los 11 `LB-*`: 53 comprobaciones, 53 ✓, 0 ✗** (una menos que antes:
`LB-ORDEN` ya no tiene la línea «Compensación»). El cajón pintó «Devuelto en el parque · 31/08/2026
−19,80 €» y «Descuento por cortesía · −9,90 €» **sin tocar `orders.js`**: pinta por signo y por
etiqueta, como manda el contrato. ⚠️ Cosmética: la liquidación inferida va fechada al FIN de la franja
(ayer) y por eso sale ANTES del «Pagado online» de hoy — es la siembra; en producción el cobro
precede a la visita.

Queda para el OJO del owner (V22): el modal «Reembolsar» de `LB-BAJADA` (ofrece «devolver lo que se le
debe» hasta 19,80 y explica que el libro lo da por devuelto en recepción), el de `LB-ORDEN` sin bajada
registrada (la opción deshabilitada con la frase), y `LB-CORTESIA` en la ficha (el motivo bajo la línea).

### El libro PLEGADO en el panel (2026-09-01, `DECISIONES #318`)

`panel-fold-probe.js` (scratchpad) entra UNA vez al panel (`admin@jumpingjump.test`; el login tiene
limitador) y mide el bloque «Totales del pedido» de `LB-CORTESIA` con capturas: **17/17 ✓**. Plegado:
0 líneas de valor y 0 liquidaciones visibles, la nota del motivo oculta, «Total 9,90 €», «Pagado
9,90 €», «Nada pendiente» y el CTA «Ver el desglose» (`aria-expanded=false`), con las 3 + 2 líneas
presentes en el HTML; abierto: las tres líneas de valor («Descuento por cortesía» incluida), las dos
liquidaciones, «Motivo: …» y «Cerrar el desglose»; vuelve a plegarse; «Ver historial completo» UNA
vez en la página (la tarjeta «Detalles»). ⚠️ Trampa de sonda: `/admin/login` casa con
`/\/admin(\/|$)/` — la señal de haber entrado es SALIR del login, no «estar en /admin».
Queda para el OJO del owner (V23): el pliegue en la tarjeta de cada reserva y en el modal del calendario.
