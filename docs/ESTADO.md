# Estado del proyecto — foto viva

> Documento CORTO (carga obligatoria al arrancar). Solo «dónde estamos / qué sigue».
> ❗ **2026-08-24 · lo primero que tienes que saber**: los pedidos de la BD de desarrollo **se
> borraron y se reconstruyeron** (25, uno por acción accionable — ver el índice de `specs/
> desglose-dinero-cliente.md` §22), y 🟩 **el DESGLOSE DE DINERO DEL CLIENTE queda CERRADO**: `L6`
> está ejecutado (`#134` · §23). **Lo siguiente es la Fase 5** — y su primer punto no es implementable
> tal como está escrito: empieza por una decisión de infraestructura del owner, no por código.
> El «qué pasó» de cada paso vive en `00-REFACTOR.md` (tracker) y `DECISIONES.md` (el porqué):
> aquí solo se enlaza. Última actualización: **2026-08-24** (**el DESGLOSE de dinero del cliente,
> CERRADO**: tandas A, B y C ejecutadas, los CUATRO defectos de lectura arreglados, la auditoría de
> las 25 acciones pasada y un desglose que no cuadra ya no se sirve como si nada.
> `DECISIONES #127` a **`#134`**; `specs/desglose-dinero-cliente.md` §15 → **§23**).

## ▶ Dónde estamos

**Fase 0 ✅ · Fase 1 ✅ · Fase 2 ✅ · Fase 3 (API v1) ✅ · Fase 4 (sidebar SPA) ✅ — CERRADA el
2026-08-22, y el 2026-08-23 se le añadió el último trozo de `#66`: la AUTH dentro del cajón.**
🟩 **Y el 2026-08-23 cae `account-context`**: el bloque de cuenta lo pinta Vue, la clase Livewire y su
Blade **ya no existen** y la página sirve **0 atributos `wire:`** (`DECISIONES #123`).
🟩 **Y el 2026-08-23 «Mis reservas» pasa a listarse POR RESERVA**, con el pasado en su propia pantalla
(`DECISIONES #126`).
🟩 **Y el 2026-08-24 se arregla el DESGLOSE DE DINERO que ve el cliente** (`DECISIONES #127`), en dos
tandas: el **dominio** dejó de mentir en cuatro casos que ninguna auditoría anterior había construido
—y de paso se cerró un **agujero de ingresos**: mover la fecha de una reserva no re-tarificaba— y la
**proyección** pasó a componerse en un solo sitio, en dos ejes y con una frase que explica cada
estado. Medido: **0 de 23** gestiones del panel dejan la columna ilegible, cuando eran 18.
🟩 **Y ese mismo 2026-08-24, mirando un pedido REAL en pantalla, salieron TRES defectos de LECTURA y
también están arreglados** (`DECISIONES #128`): el **ancla de caja** —lo único que el cliente puede
cotejar con su banco— **no se enseñaba si no había devoluciones**, la cantidad se pintaba pegada al
importe (`8×216,00 €` se lee como 1.728 €) y la nota de la reserva llamaba «señal» a lo que no lo es.
Medido: el eje de caja pasa de **9 de 38** pedidos sanos a **37 de 58**, y la divergencia
panel↔cliente sobre ese número —**19 pedidos**— desaparece. El desglose pasa de LEGIBLE a
**VERIFICABLE**.
🟩 **Y el 2026-08-24 se cierra la TANDA C: «Mis pedidos» es una pantalla propia** (`DECISIONES #129`).
⚠️ Prometía ser «la única que no toca dinero» y lo primero que encontró fue que **`GET /me/orders`
PERDÍA PEDIDOS**: ordenaba solo por `created_at` y, medido sobre los 57 reales del cliente demo, dos
salían repetidos y **dos no salían en ninguna página**. Arreglado y guardado.
🟩 **Y una SEGUNDA VUELTA con el owner delante** (`DECISIONES #130` · spec §20), que cambió dos cosas
y destapó una tercera: **«Mis reservas» ya no enseña dinero** —es «qué tengo y cuándo»; el desglose
está a un clic—, **el eje de caja solo aparece cuando dice algo que la columna de arriba no diga ya**
—el mismo importe salía dos veces con dos nombres casi iguales— y ⚠️ **a «Mis pedidos» le faltaba una
línea**: los complementos no se pintaban, así que un pedido REAL ponía 120,00 € en la reserva y
124,00 € de total sin nada que explicara los 4,00 €.
▶ ⚠️ **Los dos defectos pasaban la suite entera**: uno porque la guarda miraba la composición y el
fallo estaba en el marcado; el otro porque **no hay test que mida si algo se entiende**.
🟩 **Y una TERCERA vuelta, revisando `R-L6UTIA` a fondo** (`DECISIONES #131` · spec §21): la **fecha
del cobro se pegaba a un importe que no se cobró ese día** —medido: **7 pedidos, 6 SANOS**— y la línea
«↳ Cumpleaños Jump 12,00 €» **no decía por qué se cobra**, cosa que pasa en los OCHO `extra_due` de la
BD, cinco de ellos escritos por el flujo REAL del panel.
✅ **RESUELTO el 2026-08-24** (`DECISIONES #132` · spec §22.4): las dos identidades se evalúan **en
EJECUCIÓN** y hay asimetría deliberada — al **cliente** se le oculta la descomposición (se queda el
valor, lo cobrado y una frase honesta), al **operador** se le ENSEÑA en rojo, y el parque se entera
por `Log::warning('ledger.no_cuadra', …)`.
🟩 **Y ANTES se hizo la AUDITORÍA DE LAS 25 ACCIONES** (§22): corpus borrado entero y reconstruido por
los flujos REALES, **un pedido por acción accionable**. Resultado: **25/25 identidades cierran**,
**25/25 lo que la pantalla lista suma el total**, 0 acciones fallaron y las 2 que deben bloquearse lo
hacen con su audit. Los 25 pedidos están en la BD de desarrollo para mirarlos en el navegador.
✅ **Lo que la auditoría dejó planteado, RESUELTO** (§22.2 · `#133` decide, `#134` ejecuta): «Compensación
devuelta» como única línea del valor → **`L4` APARCADO** · el resto de señal de un complemento
escondido en la línea del principal → **`L5` RETIRADO, no era un defecto** · una BAJADA que no deja
más rastro que «Importe al reservar» → **`L6` HECHO**.
🟩 **Y el 2026-08-24 se cierra con la AUDITORÍA DE LAS 25 ACCIONES y su decisión** (`#132`, `#133`).
🟩 **Y ese mismo 2026-08-24 se EJECUTA `L6`, que era lo último** (`DECISIONES #134` · spec §23): la
línea «Importe al reservar» decía *que* el pedido había cambiado y ahora dice **hacia dónde y
cuánto** —«Al reservar se facturaron 180,00 €. El pedido cambió después y ahora vale 60,00 € menos.»—,
compuesta por el DOMINIO y nombrando la **diferencia**, no el valor. Sin una línea nueva.
▶ ⚠️⚠️ **Y con la frase se movió la CONDICIÓN**, que es el trozo que importa: `invoiced_hint` es
`null` exactamente cuando no hay nada que trazar, así que la pantalla **dejó de comparar los dos
importes por su cuenta**. Es `L1` aplicado *antes* de que cueste — allí la condición re-derivada
divergió en 19 de 58 pedidos sin que nada fallara.
▶ Medido por HTTP real y compuesto por el módulo REAL del cajón: **26 pedidos, 11 publican frase**
(6 subidas, 5 bajadas), **0 se quedan cortos y 0 se pasan**.
▶ ⚠️⚠️ **Y una guarda NACIÓ DECORATIVA**: la de idiomas salía verde con la clave francesa borrada,
porque Laravel **cae al idioma de respaldo** — una clave que falta no se ve como una clave en crudo,
se ve como un cliente francés leyendo castellano en su pantalla de dinero. Arreglada con
`Lang::has(…, false)`.
▶ 🟩 **CON ESTO EL DESGLOSE DE DINERO QUEDA CERRADO.** Después, la **Fase 5**.

**Fase 4**: 4.0a–4.0c ✅ · 4.1 ✅ · 4.2 ✅ · 4.3 ✅ · 4.4a ✅ · **4.4b ✅ (·1 y ·2)** · 4.5 ✅ · 4.6 ✅ ·
**4.7 ✅ (validado por el owner el 2026-08-22)** · **ÁREA DE CLIENTE ✅ (tres tandas, `#120`)** →
**los ONCE pasos están transcritos**, el extremo a extremo con navegador y pasarela real se hizo
(`#59`), **los cuatro caminos que `#100` exigía están VERIFICADOS en staging** (`#110`),
**`Purchase.php` está RETIRADO** (`#112`) y **`/mi-cuenta/…` también** (`#120(u)`). El corte del
diseño está en `docs/specs/sidebar-spa.md` §4.10 y el del área, en `docs/specs/area-cliente.md`.

✅ **Las dos comprobaciones de navegador que faltaban están HECHAS y el owner ha validado el cajón**
(2026-08-22). `4.7` está cerrado.

🟩 **EL ÁREA DE CLIENTE ESTÁ TERMINADA** (`#66`, `#120`), y con ella la Fase 4 cierra su alcance:
**1 (solo lectura) ✅** · **2 (gestiones) ✅** · **3 (retirar `/mi-cuenta/…`) ✅** — las páginas ya no
existen y **sus rutas viven como PUERTA** que abre el cajón en su zona (`#120(u)`).
✅ **Los DOS trozos que el área dejó fuera a propósito están HECHOS**: la AUTH (`#122`) y
**`account-context`** (`#123`), los dos el 2026-08-23. Sus fichas de `DEUDA.md` quedan cerradas.

🟩 **EL CAJÓN SPA ES EL MOTOR ÚNICO.** Con el componente se fueron su blade, el placeholder y el flag
`sidebar.engine`: **no hay vuelta atrás sin desplegar**, que es lo que `#100` pedía asegurar antes y
`#110` verificó. Está **en `main`**. ✅ La rama `wip/4.7-2b-3-retirada-purchase` **ya no existe**
(verificado el 2026-08-22: el remoto solo tiene `main`), así que `/arranque-sesion` no la sacará.

❗ **Y el propio despliegue destapó un defecto de instalación** (`DECISIONES #135`, 2026-08-25): el
`pre-push` se puso rojo en un test que parecía intermitente, y no lo era. **`app:create-admin`
imprimía una contraseña distinta de la que guardaba** cuando la generada llevaba `\<` o `\>` —el
formateador de consola de Symfony se come la barra— y esa contraseña **se enseña una sola vez**: el
owner quedaba fuera de su propio panel sin recuperación. Medido: **1.263 de 200.000 (0,63 %), una de
cada 158**. Arreglado (salida RAW) y guardado con un caso DETERMINISTA — el que existía sorteaba.

✅ **STAGING SIRVE `e551851`** desde el **2026-08-25**: el salto de **22 commits y 132 ficheros de
código** que traía `#123` → `#135` —el bloque de cuenta en Vue, el `no-store` global, «Mis reservas»
por reserva y **el desglose de dinero ENTERO**—. Canal: `scripts/deploy.sh` (dry-run por defecto;
detalle en `ENTORNOS.md` §4 y el porqué en `#105`–`#110`).
▶ **El despliegue se auto-verifica y salió limpio**: `/up` y `/` en 200, guarda del `robots.txt`,
`redsys_environment = 'test'`, **1 migración aplicada** (`add_intent_to_payment_refunds`, aditiva),
0 pendientes, 0 `failed_jobs`, 0 jobs varados y las 5 tareas del scheduler registradas.
▶ **Y se comprobó que llegó LO DE ESTA SESIÓN, no solo que el sitio arranca**: las dos claves de `L6`
en los tres idiomas, `invoicedNoteFor()` en el dominio, el campo en `LedgerResource` y el cliente ya
sin componer la frase. Sobre los datos reales de staging, `R-S9XDYB` la publica:
«Al reservar se facturaron 202,20 €. El pedido cambió después y ahora vale 13,00 € menos.»
⚠️ **Lo que hay que recordar del canal**: los assets se construyen AQUÍ y se suben compilados —en
staging no hay node/npm— y el `.env` **nunca viaja**: se lee y se valida.
⚠️ **El único aviso del despliegue**: el script **no ve ningún demonio cron** en el servidor, así que
el crontab instalado puede no ejecutarse nunca. Se comprueba en el panel del hosting, no por SSH
(`DECISIONES #115`). No es nuevo de este despliegue.
❗ **LA REGLA QUE ESTA LÍNEA PAGÓ DOS VECES: la revisión NO se copia, se MIDE.** Llegó a afirmar «no
hay diferencia de código» cuando ya eran 3 commits y 68 ficheros, y después «sirve el desglose viejo»
durante todo el día siguiente. Antes de creerte lo de arriba:
`git log --oneline e551851..HEAD` y
`git diff --stat e551851..HEAD -- . ':(exclude)docs' ':(exclude)*.md'`, sustituyendo `e551851` por lo
que sirva staging de verdad.
▶ **Consecuencia práctica**: las dos comprobaciones que quedan y piden dispositivo (`V23·3` en móvil,
`V20·6`) **ya no están bloqueadas por el despliegue**.

⚠️ **Los pasos se parten por DEPENDENCIA, no por pantalla** — es la regla que ha ordenado toda la fase.
El detalle de cada corte está en el tracker; el índice de abajo enlaza cada uno con su decisión.

- Suite **2752 en verde** (16.047 aserciones, `--parallel` **~39 s** medidos el 2026-08-25) ·
  ⚠️ Sale con **1 `PHPUnit Notice`**, y **NO es de este trabajo**: estaba ya al arrancar la sesión
  (medido en la primera corrida, con 2.726 casos). No se ha investigado; queda anotado para que el
  siguiente no lo persiga creyéndolo nuevo.
  ▶ **+2 con `#132`**: que un desglose que NO cierra se publique como tal, se avise por log y cambie
  la frase; y su control, que un pedido sano siga diciendo que cuadra. En `node --test`, **668** casos
  —con el que fija que el cliente **no descompone** un desglose que no cuadra—.
  ▶ **+1 con la tercera vuelta** (`DECISIONES #131`): que el respaldo de la etiqueta de puerta
  **explique** en vez de nombrar, y su control de que no se coma las tres ramas precisas. En
  `node --test`, **667** casos — con el que fija que la fecha **no** se pega a un importe que no se
  cobró ese día.
  ▶ **+3 con la segunda vuelta** (`DECISIONES #130`): el eje de caja **callado** cuando repetiría, el
  eje de caja **presente** cuando lo cobrado no cuadra con lo pagado, y **DOS guardas sobre el
  MARCADO** —que la tarjeta de la reserva no pinte dinero y que la del pedido siga pintándolo entero,
  complementos incluidos—. Esas dos miran la plantilla porque el defecto que las trajo **no se ve en
  la composición**. En `node --test`, **666** casos (se fueron con su sujeto los de la nota de señal).
  ▶ **+7 con la TANDA C** (`DECISIONES #129`): el orden total de la paginación —uno de conducta y
  **uno estructural, porque el de conducta sale verde en SQLite**—, `containing` con su caso del
  **oráculo** y el de `page` explícito, y las dos paridades extremo a extremo de «Mis pedidos» (que la
  lista y el pedido suelto compongan el MISMO dinero, y que abrir desde una reserva aterrice en la
  página que lo contiene). En `node --test`, **668** casos.
  ▶ **+4 en el corte anterior: los TRES defectos de LECTURA** (`DECISIONES #128`) — el ancla de
  caja publicada sin devoluciones, el pedido nunca cobrado que no la enseña, el cobrado en TAQUILLA
  que no dice «por web», y la cantidad con su sustantivo. ⚠️ **Y varios casos que ya existían miden
  ahora más**: la paridad extremo a extremo del cajón fija `L1`, `L2` y `L3` sobre la respuesta REAL
  del servidor, y `SidebarTextParityTest` pasó de conceder su excepción a **demostrarla**.
  ▶ **+22 en el corte anterior: 17 de la TANDA A y 5 de la TANDA B.** Las de la B son la
  guarda que faltaba desde el principio —**que lo PUBLICADO sume**, recorrida sobre escenarios—,
  la frase de estado, y `LedgerSingleSourceTest`, que prohíbe **el mecanismo**: ninguna superficie
  puede volver a derivar un canal restando otros. ⚠️ Esa última lleva su propia guarda-de-la-guarda,
  porque un `grep` mal escrito queda verde para siempre sin mirar nada.
  ▶ Las de la TANDA A (`DECISIONES #127`): **5**
  escenarios nuevos de `OrderFinancialInvariantsTest` —los que ejercitan los cuatro defectos que se
  arreglaron; los seis viejos ya pasaban porque su hueco estaba en el FIXTURE—, **6** de
  `ItemDateChangeRetariffTest` (la re-tarificación al mover la fecha, con su límite y su control),
  **2** que conducen las ACCIONES REALES del panel para la cascada de cancelación —una guarda sobre
  el helper suelto NO veía el cableado, medido por mutación—, **3** del motivo del reembolso y **1**
  que asevera que el reparto del reembolso total no pierde céntimos.
  ▶ Y el fichero de invariantes pasa de **5 aserciones cruzadas a 13**: las dos identidades del
  modelo, los cuatro cruces que faltaban, la exclusión mutua de los canales web, la no-negatividad de
  cada canal y la guarda de construcción de la columna de reembolso.
  ▶ Las del cierre ANTERIOR (2026-08-23), que siguen dentro: **+30, y la mayoría GUARDAS QUE
  FALTABAN**, no cobertura de código nuevo. Las del relevo:
  **5** de `NoStoreWebResponsesTest` (nadie aseveraba `no-store` en una página web: la ponía un
  accidente de Livewire), **1** de `SidebarIconParityTest` (la paridad de iconos no miraba 10 de 32
  componentes), y **2** en `CustomerAccountContextTest` — un pack **sin franja** seguía avisando solo
  porque lo decía un comentario, y nadie medía que la consulta de formularios pendientes **no
  materializara el histórico**. Las otras 15 cubren `GET /me/account-context` y su semilla en el montaje.
  ▶ Y las del PULIDO (`#124`): **4** de `SidebarStyleWiringTest` —toda clase que el cajón emite tiene
  una regla, el `#113` aplicado al CSS—, **3** del scroll y del modo del panel, y **2** del encabezado
  propio de una zona. Ninguna cubre código nuevo: todas cierran un hueco que ya existía.
  ▶ Y la de `#125`: **+1** en `SidebarVerifyScreenTest` —cuál de los DOS cooldowns del reenvío ata—.
  El neto es +1 porque esa guarda ya existía y lo que hizo falta fue **re-apuntarla**, no duplicarla.
  ▶ Y las de `#126` (**+25**): **13** del REPARTO de los dos ámbitos en el dominio —incluida la que
  asevera la propiedad, `upcoming + past = total` y sin solapamiento— y **12** del endpoint nuevo.
  **670 tests JS** (`node --test`) · Pint
  limpio (842 ficheros) · `docs-check` verde ·
  ⚠️ **Esta cifra de JS decía 648 y llevaba VEINTIDÓS cierres de retraso** —los deltas de arriba ya
  cantaban 666, 667 y 668—: corregida el 2026-08-24. **El `pre-push` solo vigila el contador de PHP**
  (`DECISIONES #116`), así que el de JS depende de que alguien lo mire; si vuelve a divergir, mídelo
  con `npm run test:js` en vez de sumar los deltas.
  ⚠️ **El contador ha BAJADO dos cierres seguidos, y las dos veces a propósito**: `/mi-cuenta/…` se
  llevó 63 casos (`#120(u)`) y el modal de auth, 42 (`#122`). Ninguno se perdió por descuido — en el
  segundo se midió **por mutación** cuáles cazaba también la API antes de borrar, y los tres que eran
  guardián único se re-apuntaron. **Un contador que solo puede subir acaba premiando el test que no se
  retira.**
  `composer audit` y `npm audit` en **0** · `npm run build` y `build:ssr` OK. El contador
  «PHPUnit Notices: 1» sale solo en la paralela completa y es del runner (ver `TESTING.md`).
  ✅ **Y desde el 2026-08-21 este número YA TIENE GUARDA**: el `pre-push` compara lo que acaba de dar
  la suite con lo que declara esta línea y **corta si no cuadran** (`DECISIONES #116`). Antes no lo
  vigilaba nadie —`docs-check` no lo mira— y derivó tres veces en un solo día.
  ⚠️ **Sigue siendo el ÚNICO sitio donde vive**: duplicarlo en otro documento crea una copia que no
  guarda nadie. El histórico de cómo llegó hasta aquí está en `DECISIONES #112(a)` y `#116`.
- ⚠️ **La suite NO está auditada contra la FECHA, y ya mordió DOS veces** (`DECISIONES #64`, `#97`):
  tres casos amanecieron rojos sin que nadie tocara nada, y el **2026-08-16 a las 00:02 de Madrid** el
  `pre-push` cayó con **1 fallo** en el cruce de medianoche; el reintento salió verde.
  ⚠️ **Y no se supo cuál era**: la salida del gate no se capturó y se perdió. **Si el `pre-push` cae,
  vuelca su salida a fichero antes de reintentar** — un rojo transitorio sin nombre no se puede
  arreglar. Están arreglados congelando el reloj, pero **nadie ha
  barrido el resto**. Si te encuentras un rojo que no viene de tu cambio, **guarda el árbol y prueba en
  el commit anterior antes de tocar nada** — es lo que separó el diagnóstico en minutos de una sesión
  perdida. Ficha en `DEUDA.md`.
- **El gate son SEIS pasos** —docs-check · Pint · `npm run build` · `npm run build:ssr` ·
  `npm run test:js` · suite—, y `PrePushGateTest` los vigila uno a uno, incluido que el build vaya
  ANTES que la suite (se añadió tras un fallo real: un manifest a 0 bytes tumbó la web entera).
- ⚠️ **`SidebarDomContractTest` compara contra un ARTEFACTO** (`storage/ssr/render-sidebar.js`). Tiene
  guarda contra bundle rancio (`#69`) porque un bundle viejo daba **verde falso**; ha saltado **tres
  veces en tres días** —la última tumbando sus 30 casos de golpe (`#78`)—. Si tocas un módulo del cajón,
  o lo mutas y lo restauras, `npm run build:ssr` **antes** de leer ningún resultado.
- **Auditorías de dependencias = verificación de CIERRE, no de instalación** (`#25`): el árbol npm pasó
  de 0 a 5 avisos en unas horas sin que el lock cambiara. Correrlas en cada cierre.
- **Los dos verificadores de concurrencia: VERDES sobre MySQL real** (2026-08-14, 8+8 workers).
  **La lista viva de lo que exige `VERIFY_CONC=1` es el `CRITICAL_RE` de `.githooks/pre-push`** — no se
  copia aquí para que no envejezca, y `CriticalPathGateTest` vigila que siga cubriendo lo que debe.
  ✅ **Y desde la tanda 3 el gate ya no deja fuera ninguna superficie de dinero** (`#120(u)`): el
  reintento web —el último que quedaba sin cubrir— se retiró con la página que lo servía, porque el
  cajón reintenta por `POST /api/v1/orders/{code}/payment`, que sí entra por el `CRITICAL_RE`. (La
  compra la había dejado antes `#112`, al retirar `Livewire\Tickets\Purchase`.)
- **Fase 2 dejó tres cosas que se usan al tocar código hoy** (el resto, en `specs/modulos-dominio.md`):
  `php scripts/module-deps.php [Clase…]` mide las dependencias INVISIBLES · *recibir* una entidad de
  otro módulo es costura de BD, *consultar* sus datos o *repetir* sus reglas exige contrato · las
  baselines del arch-test **solo encogen**.
- ✅ **El segundo objeto-dios está DESMONTADO** (`DECISIONES #119`, 2026-08-22). `Sidebar.vue` pasó de
  **614 líneas y 11 llamadas a la API** a **16 y 0**: el embudo vive en `sections/PurchaseSection.vue`
  y el estado, en `stores/` —la reorganización creó **nueve** y el área de cliente ha ido añadiendo
  los suyos, uno por dominio—. Lo siguen guardando `SidebarComponentBudgetTest`
  (techo por componente + excepción declarada que solo encoge) y una guarda de que **la raíz no vuelve
  a pintar pantallas**. ⚠️ **Las cifras vivas están en su `EXCEPTIONS`**, no aquí: copiarlas a este
  documento es drift en espera, y ya pasó una vez.
- ⚠️ **NOTA DE DESPLIEGUE permanente**: las migraciones corren **ANTES** de servir tráfico (el morphMap
  de Fase 2 es requisito) y hay que **drenar la cola + `queue:restart`** (los payloads serializados
  llevaban los FQCN viejos).

## ▶ Decisión de producto VIGENTE que enmarca todo lo demás

⚠️ **El cajón es el ÁREA DE CLIENTE, no el embudo de compra** (`DECISIONES #66`, owner). Toda la
gestión del cliente vive dentro del cajón: sus entradas y reservas, y las gestiones de cuenta.
✅ **De los tres sitios en que estaba repartido, quedan DOS**: las páginas `/mi-cuenta/…` se retiraron
(`#120(u)`) y el **modal de auth de la cabecera** sigue siendo la puerta de entrada de quien no tiene
sesión. Ése es el último trozo, y su ficha está en `DEUDA.md`.

- **El orden es dependencia, no preferencia**: **4.7** → **Turnstile** → **área de cliente**.
  ✅ **Turnstile ya no ata nada** (4.4b·2, 2026-08-20): el cajón monta su propio widget y la delegación
  en el modal de la cabecera **está retirada**. ⚠️ Pero el modal **no se retira aquí ni en `4.7·2b·3`**:
  vive en `layout.blade.php`, no en `purchase.blade.php`, y sigue siendo la puerta de auth de la web
  fuera del cajón. Retirarlo es trabajo del área de cliente.
- ✅ **El terreno ya está preparado** (`DECISIONES #119`, 2026-08-22), y lo que hay que saber es dónde
  NO meter la cuenta:
  · el grafo del embudo es `FUNNEL_TRANSITIONS` y está **cerrado con guarda**: colgar ahí una pantalla
    de cuenta pone el test en rojo. Un área de cliente **no es un embudo** — sus pantallas se navegan
    libremente— así que va con su propio modelo, no con el de la compra;
  · el embudo es una **sección** (`sections/PurchaseSection.vue`) y la raíz son 16 líneas que solo
    enrutan: **la cuenta entra al lado, no dentro**;
  · el estado de cada dominio ya tiene su store, así que una sección nueva pide el suyo y no necesita
    que la raíz le pase nada por props.
  ✅ **Y el modelo de navegación está DISEÑADO, VALIDADO por el owner y CONSTRUIDO** (`#120(d)`,
  `specs/area-cliente.md` §3.1): índice + zonas libres con pila de retorno, con su propio modelo y sin
  tocar el grafo del embudo. Añadir una zona es **una línea** en `ZONES` más su rótulo.
- ✅ **El servidor está COMPLETO para las cinco gestiones** (`#120(a)` lo midió endpoint por endpoint,
  y por eso el área fue en tandas): **leer** —`/auth/*`, `/me`, `/me/orders`, `/me/reservations`,
  `/me/reservation-eligibility` y el post-form por firma, desde Fase 3— y **gestionar**: contraseña,
  sesiones, perfil (`PUT /me/password`, `POST /me/sessions/revoke-others`, `PATCH /me` y los dos del
  correo pendiente) y, desde el paso 8, los **dos derechos RGPD** (`DELETE /me` y `GET /me/export`).
  ⚠️ **`DELETE /me` NO borra la fila**: llama a `anonymize()` (`RGPD-01`). El pedido y su historia
  contable se conservan sin PII, porque la FK es `RESTRICT` y la factura tiene que seguir vinculada.
  ⚠️ **`GET /me/export` es el cuerpo con más PII del producto** —lleva `event_data` en claro: nombre
  y alergias de un menor, art. 9— y por eso `RGPD-04` exige `no-store`, que en `/api/v1` va por
  defecto en toda respuesta autenticada.
- ✅ **`account-context` YA ES VUE** (`#123`, 2026-08-23): el bloque lo pinta
  `sidebar/account/AccountPanel.vue`, teletransportado al hueco que emite el layout, y la frontera
  Livewire↔Vue del cajón **desaparece**. ⚠️ **Pero Livewire NO se puede retirar**: sigue trayendo
  Alpine, así que `@livewireScripts` se queda — lo que cambia es que ahora es la **fuente única**, y
  su guarda por fin discrimina (medido: retirarla la pone roja; hasta hoy no).
- ✅ **La puerta de entrada de quien no tiene sesión es el CAJÓN** desde `#122` (2026-08-23): el modal
  de la cabecera se retiró y las tres pantallas de auth son zonas de la sección de cuenta.

## ▶ Próximo paso

# ❗ LO SIGUIENTE ES LA **TANDA A** DE `specs/landing-white-label.md` — EL TEMA, DE VERDAD

✅ **El desglose de dinero está CERRADO** (`L6` ejecutado el 2026-08-24, `DECISIONES #134` ·
`specs/desglose-dinero-cliente.md` §23). `L4` aparcado y `L5` retirado porque no era un defecto.

🟦 **Y el 2026-08-25 se DISEÑA la landing white-label** (`DECISIONES #136` ·
**`specs/landing-white-label.md`**), a raíz del mockup del **segundo cliente**. La decisión del owner:
**data-driven el DATO, no la PÁGINA** — todo lo que la landing enseña sale del dominio o del CMS, y la
composición de la página es un **paquete de tema por cliente, en código**. Maquetador visual,
descartado.
⚠️⚠️ **Léela por §1**, porque tres de los cinco problemas enunciados NO eran lo que parecían: el CMS
**solo necesita 2 modelos más** (los otros ya existen), el copy **vive en el REPO** —142 claves en
`lang/`, así que un cliente no puede cambiar su titular sin desplegar— y `/servicios` tiene **0
productos vinculados y 2 tablas de precios TECLEADAS a mano**.

**El orden acordado, y por qué:**

- **TANDA A · el tema, de verdad.** Es lo siguiente. Inyectar los tokens desde BD —la costura ya
  existe: **28 variables y 1.271 usos de `var()`**, y **nada las alimenta**—, inventariar los **76
  colores en crudo**, dejar el spinner rebrandeable y darle **icono propio a cada producto** (set
  curado, `#136`). No toca dominio y es lo que la plantilla del cliente nuevo necesita para pintar.
- **TANDA B · el contenido**: los dos modelos que faltan, el copy que baja de `lang/` al CMS y la
  landing del segundo cliente como primer paquete de tema.
- ⚠️⚠️ **TANDA C · los servicios como PRODUCTO REAL** (encargo explícito del owner: que estén en el
  sistema de reservas de verdad, no maquillados en la página). **Va la última y NECESITA SPEC PROPIA**:
  toca `AFORO-01/02/03` y las identidades de `PAY`, exige `VERIFY_CONC=1` y **no se puede verificar ni
  en SQLite ni en staging** (MariaDB).

✅ **Y la Fase 5 ya NO está bloqueada** (`DECISIONES #137`, 2026-08-25): **Redis, requisito DURO y solo
para CACHÉ**, activado y verificado en staging. Instancia propia por sitio dentro del contenedor PHP,
`127.0.0.1:6379` sin contraseña, y **Laravel conecta sin tocar una sola variable** —sus valores por
defecto ya coinciden—. Los **tags funcionan contra el Redis real**, también por el camino WEB.
✅ **Y sus DOS obligaciones están HECHAS** (2026-08-25, mismo `#137`):
- **el pase de la vuelta de Redsys SALIÓ de la caché** — `RedsysReturnController::handoff()` es el
  único sitio donde se decide el store, y lo leen el que escribe y el que consume. Guardado con un
  caso que **vacía la caché y comprueba que el pase sigue ahí**: es el único que caza la regresión,
  porque en los tests el store por defecto es `array` y nada lo vacía;
- **Redis está en el `compose.yaml` local**, clavado a **7.0.15** —la misma versión que staging, no
  `redis:alpine`— y la suite ejercita los tags **contra él**, no contra `array`.
  ⚠️ Y hay una **guarda que se activa sola**: hoy nadie usa `Cache::tags()` y duerme; el día que
  aparezca el primero, **exige que `deploy.sh` garantice un store que los soporte**. Sin ella, ese
  commit dejaría una instalación con `CACHE_STORE=database` sirviendo un 500.
▶ **Tres mutaciones, las tres muerden**: devolver el pase al store por defecto · apagar Redis (el
caso **falla**, no se salta) · y colar un `Cache::tags()` en `app/`.
⚠️ **Lo que la primera versión de esa guarda enseñó**: buscaba la cadena `CACHE_STORE` en
`deploy.sh` y **habría pasado siempre**, porque la plantilla del `.env` ya la nombra. Ahora exige la
línea `guard_errors+=(… CACHE_STORE …)`, que es la guarda y no la palabra.
⚠️ **Sesión y cola se quedan en BD**: Redis vive DENTRO del contenedor PHP y cada reinicio se lo lleva
—incluido el botón del panel—. Para una caché es un arranque en frío; para las sesiones sería echar a
todos a la vez, incluido quien esté pagando.
❗ **Y con `CACHE_STORE=redis`, si Redis no responde el sitio da 500** (medido: 0,14 s, falla rápido).
Es la consecuencia aceptada del requisito duro.
▶ Detalle de la máquina en `ENTORNOS.md` §4 · ⚠️ **el `redis.conf` sigue de fábrica a propósito**
(sin techo de memoria y para de escribir si falla un volcado): ficha abierta en `DEUDA.md`.

✅ **Staging ya está al día**: sirve `e551851` desde el 2026-08-25, con el desglose entero (`#123` a
`#134`) y verificado allí sobre datos reales. ⚠️ Pero **la distancia se mide, no se copia** —esta
línea ya mintió dos veces—: antes de creértelo, `git log --oneline e551851..HEAD` (ver «Dónde
estamos»).

---

🟩 **EL DESGLOSE DE DINERO DEL CLIENTE: tandas A y B EJECUTADAS el 2026-08-24** (`DECISIONES #127`
y sus apartados `(b)`–`(f)` · `specs/desglose-dinero-cliente.md` §15 y §16), **y con ellas los TRES
defectos de LECTURA** que destapó mirar un pedido real en pantalla (`DECISIONES #128` · §18).
**El dominio dice la verdad, el desglose se entiende — y ahora además se puede VERIFICAR.**

**Medido al cerrar, no afirmado:**
- la matriz de las **23 acciones reales del panel** deja **0 columnas ilegibles** (eran 18) y
  **0 rompen ninguna identidad** (eran 3);
- el **eje del valor cierra en 50 de 50** pedidos servidos por HTTP real;
- el **eje de caja se enseña en 37 de 58** pedidos —los 37 que han movido dinero—, cuando el cliente
  solo lo veía en **9 de 38** sanos; la divergencia panel↔cliente sobre ese número (**19 pedidos**)
  desaparece y el cambio de condición **no altera el panel en ninguno de los 58**;
- **20 mutaciones** verificadas entre las tres entregas (13 + 7): todas muerden.

✅ **LA TANDA C ESTÁ HECHA** (2026-08-24 · `DECISIONES #129` · spec §19): «Mis pedidos» es una zona
propia (`ZONES.PURCHASES`), entra en el índice y «Ver pedido» de una reserva **lleva a ella con ese
pedido desplegado**. El desglose se MUDÓ allí; la tarjeta de la reserva ya no lo despliega.
⚠️⚠️ **Y decía «es la única que NO toca dinero». No era cierto**: lo primero que encontró fue que
`GET /me/orders` **perdía pedidos** —solo ordenaba por `created_at`, y medido sobre los 57 reales
salían 55 distintos: dos repetidos y **dos invisibles para su dueño**—. Arreglado con desempate por
`id`, y guardado con una comprobación **estructural** porque la de conducta sale VERDE en SQLite.
▶ **La página la elige el SERVIDOR** (`?containing=`): medido, `R-L6UTIA` está en la **página 7 de
12**, así que abrir la primera habría incumplido la decisión del owner sin que nada fallara.

⚠️⚠️ **Lo que hay que saber antes de tocar el desglose, y no es negociable:**
- **Lo compone UN solo sitio**, `Booking\Services\OrderLedger`, y lo leen las **OCHO** superficies
  (panel: bloque, sub-tarjeta, calendario, lista y taquilla · PDF · correos · cliente). Componerlo
  otra vez en una superficie es el defecto que costó tres auditorías: `LedgerSingleSourceTest` lo
  tumba **aunque el resultado sea correcto hoy**.
- **Son DOS EJES y no se mezclan**: `valor = pagadoWeb + pendienteWeb + pagadoParque +
  pendienteParque + compensado`, y `retenido = pagadoWeb + pendienteDevolución`. Los guardan
  `PAY-16` y `PAY-17`; la tarifa al mover la fecha, `PAY-18`.
- ⚠️ **`online_amount_cents` NO es una dimensión del ledger**: es «cuánto se te cobrará si pagas
  ahora», lo que consume el reintento. Leerlo como «lo pagado» es el defecto original.
- ⚠️ **Y desde `#134` eso incluye «Importe al reservar»**: la frase (`invoiced_hint`) la compone el
  dominio con **dirección e importe**, y **su nulidad ES la condición de enseñar la línea**. Comparar
  `invoiced_cents` con `value.total_cents` en una superficie es re-derivarla otra vez.
- ⚠️⚠️ **Las CONDICIONES también las decide el dominio, no solo los importes** (`#128`). Cuándo se
  enseña el eje de caja (`has_cash`), cómo se cobró (`charged_method`) y cuándo (`charged_at_label`)
  **viajan publicados**. `hasCash()` existía y **ninguna superficie lo llamaba**: cada una re-derivaba
  la condición, y por eso el panel enseñaba el ancla en 28 de 38 pedidos sanos y el cliente en 9.
  Una condición re-derivada es una divergencia con retraso.
- ⚠️ **`charged_online_cents` NO es «lo cobrado por web»: es lo cobrado por ADELANTADO**, por el canal
  que sea —la taquilla también escribe `Payment`—. El rótulo sale de `charged_method`; quemarlo le
  dice al cliente que pagó por internet un dinero que entregó en mano.

❗ **Y si alguien te enseña un pedido cuyo desglose «no se entiende», mira PRIMERO la spec §17.**
Hay un caso canónico —`R-L6UTIA`— que parece un fallo del desglose y es un **dato roto**: dice
«Pagado por web 114,00 €» cuando el pago real fueron 30,00 €. La aritmética cierra porque cierra
sobre una mentira que está en la BD. ⚠️ **Regla: comprueba si el dato es real antes de buscar el
fallo en el código** (`grossPaidOnline` contra `pagadoOnline`).
✅ **Y los TRES defectos de LECTURA que ese caso destapó están ARREGLADOS** (2026-08-24,
`DECISIONES #128`, spec **§18**): el **ancla de caja se ve siempre que haya habido un cobro** —con su
método y su fecha, para que se pueda cotejar con el banco—, la cantidad va **con su sustantivo**
(«8 invitados · 216,00 €», no `8×216,00 €`) y la nota de la reserva **dejó de llamar «señal»** a lo
que no lo es. Medido: el eje de caja pasa de verse en **9 de 38** pedidos sanos a **37 de 58**, y la
divergencia panel↔cliente sobre ese número —**19 pedidos**, la mitad del corpus— desaparece.
▶ ⚠️ **Y hacerlo visible obligó a publicar el MÉTODO de cobro**: el eje de caja suma todos los pagos
sin mirar el `provider`, así que un pedido de **taquilla** habría dicho «Cobrado por web». Ahora el
dominio publica `charged_method` y cada superficie pone su voz.
▶ **Pendiente de veto del owner**: tres cadenas (§18.6), una palabra cada una.

⚠️ **Y una trampa que este trabajo pagó CINCO veces**: un fixture que no reproduce el flujo real
**inventa defectos tan bien como los oculta**. Los casos —un pedido `paid` sin `paid_at`, otros sin
ninguna fila `Payment` (el quinto, en la propia paridad del cajón), un reembolso que escribe la
columna sin la fila— aparecían como fallos del código y eran del fixture. Si una guarda de dinero se
pone roja, **mira primero si el dato es real**.

❗ **DOS COSAS PENDIENTES DEL OWNER** (fichas en `DEUDA.md`):
1. **Un `Ds_Response=0900` REAL de Redsys**: exige un pago de prueba con tarjeta en el sandbox desde
   el navegador (staging) y después `redsys:verify-sandbox --gateway-order=…`. Todo lo demás de la
   cadena está verificado con las credenciales del owner. ⚠️ Una medición anterior dio el sandbox por
   inalcanzable y **era un error**: se probó el puerto 443 y Redsys sirve el suyo en el **25443**.
2. ~~**Los 21 pedidos de auditoría** de la BD local~~ **HECHO el 2026-08-24**: el owner pidió borrarlos
   y reconstruir el corpus acción por acción (`DECISIONES #132`).

⚠️⚠️ **EL ESTADO DE LA BD DE DESARROLLO, que hay que saber antes de mirar nada:**
- **Los 58 pedidos anteriores YA NO EXISTEN.** Se borraron enteros —pedidos, líneas, ajustes, pagos,
  reembolsos y tickets— el 2026-08-24. **La copia de seguridad NO sobrevive a la sesión** (vivía en el
  scratchpad, fuera del repo): lo que hay es lo que hay.
- **Lo que hay son 25 pedidos**, uno por acción accionable, construidos por los **flujos REALES**
  (`OrderCreator` → vuelta de Redsys FIRMADA → acciones del panel por Livewire). Titular:
  `cliente.demo@jumpweb.test`. **Los 25 cuadran** — ya no queda ningún pedido con el desglose roto,
  así que el aviso de `#132` **no se puede ver en pantalla con estos datos**: para verlo hay que
  romper uno a mano (p. ej. cambiar el `amount` de su `Payment`).
- **El índice de los 25 casos, con su código y la acción que representa, está en `specs/desglose-dinero-cliente.md` §22.**
  ⚠️ Y §22.3 recoge **cuatro trampas para conducir el panel** desde un test: la peor, que el cambio de
  FECHA lo mueve el CALENDARIO y no el formulario —con `slot_date` solo, la acción se ejecuta, **no da
  error y no cambia nada**—.
- La sonda que los creó **no está en el repo** a propósito (instrumento de medida, no guarda: §12.6).
  Su receta corregida sí, en §22.3.

▶ **Y DESPUÉS, la Fase 5** — capa de contenido profesional. ⚠️ Su primer punto **no es implementable
tal como está escrito** (medido el 2026-08-23): pide caché **etiquetada** y el store es `database`,
que **lanza** `BadMethodCallException` al usar tags; no hay Redis en el stack local, ni en staging, ni
una línea en la doc que lo contemple. Empieza por una decisión de infraestructura del owner —Redis, o
invalidación por versión de clave—, no por código.

---

### El estado del cajón, para lo que venga

🟩 **EL CAJÓN ESTÁ COMPLETO, PULIDO Y VERIFICADO EN NAVEGADOR.** El bloque de cuenta es Vue (`#123`),
el layout no renderiza **ningún** componente Livewire, los nueve retoques que el owner pidió están
hechos (`#124`) y el guion de navegador que quedaba **se recorrió el 2026-08-23** (`#125`). Las cuatro
specs del cajón están ✅ EJECUTADAS.

⚠️⚠️ **LO QUE ESE TRABAJO ENCONTRÓ, y condiciona lo que venga. Léelo antes de tocar el cajón:**
- **El `no-store` de TODAS las páginas web lo ponía un accidente de Livewire** —un hook de componente
  encendía el flag que usaba un middleware global del paquete—, así que retirar el último componente
  lo habría borrado del sitio entero **con la suite en verde**: ninguna de sus 12 aserciones miraba
  una página del layout. Hoy lo pone `NoStoreWebResponses` (global, con puerta para `/api/v1`).
- **`route('logout')` aparece UNA sola vez en toda la aplicación**, y vive como **suelo servido dentro
  del hueco** del bloque: colapsado e invisible mientras todo va bien, a la vista si el motor no
  llega. Desmiente la premisa escrita de `#120(t)`, ya corregida.
- **Ocho clases se emitían sin una sola regla** —entre ellas la tarjeta de «Mis reservas»—, porque la
  transcripción a Vue **inventó nombres** en vez de reutilizar los de las páginas retiradas. Ahora lo
  vigila `SidebarStyleWiringTest`, que además dejó **seis huecos del EMBUDO declarados con nombre**:
  `cart__pending`, `catalog__per`, los tres de complementos y el motivo del pago denegado. **Están sin
  arreglar a propósito** —son pantallas ya validadas— y son el candidato natural a un pulido del
  embudo.
- **`SidebarIconParityTest` estaba ciego a 10 de los 32 `.vue`** (`**` no es recursivo en `glob()`).

⚠️ **Antes de añadir NADA al cajón, mira su presupuesto.** Es la holgura más estrecha de todo el
ledger, y **la cifra viva NO se copia aquí**: vive en `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB`
con su ledger al lado —ya envejeció una vez en esta tabla—. El techo subió dos veces el 2026-08-23
(`#125` y `#126`), las dos con su medición y su párrafo, y las dos por **corrección**, no por features:
ése es el único caso en que debe ceder.

🟩 **«MIS RESERVAS» SE LISTA POR RESERVA** (`DECISIONES #126`, `specs/mis-reservas-por-reserva.md` ✅):
una tarjeta por reserva con la referencia de su pedido, las vivas de la más próxima a la más lejana, el
historial en su propia zona tras un CTA y atenuado, **5 por página ordenadas en el SERVIDOR**
(`GET /api/v1/me/reservations/{scope}`).
⚠️⚠️ **Lo que no se puede no saber antes de tocarlo**: los dos ámbitos son **los dos lados de UN
predicado** (`where`/`whereNot` sobre la misma expresión), **no dos consultas**. Si alguien las separa,
una reserva puede **no salir en ninguna de las dos pantallas** — y eso no falla, no avisa y no se ve:
una lista a la que le falta una fila se lee perfectamente. Lo sostiene `Sales\CustomerReservationsPageTest`,
que asevera la PROPIEDAD y no una lista de casos.
⚠️ **El ledger NO viaja con la tarjeta**: es del pedido y se repetiría tantas veces como reservas tenga.
Se pide con `GET /orders/{code}` al desplegar «Ver pedido».

⚠️ Y sigue abierta la decisión aplazada de `specs/area-cliente.md` §3.4: **si la zona activa cambia la
URL**. Hoy el «atrás» del navegador no hace nada dentro del cajón y una zona no se puede enlazar.

✅ **EL GUION DE NAVEGADOR ESTÁ AL DÍA** (2026-08-23, `#125`): `V17`, `V18` y `V23` —los tres que
`#122` y `#124` dejaron sin recorrer— **están HECHOS y en 60/60** tras arreglar los dos fallos reales
que destaparon. El qué pasó, en el tracker; el guion, en `VERIFICACION-E2E-CAJON.md` §5.septies.
⚠️ **Lo único que queda pide un DISPOSITIVO**: `V23·3` en **móvil real** (el scroll que arrastraba la
página) y `V20·6` con «reducir movimiento». Los dos necesitan staging — que **no lleva `#123`–`#126`**.

### Dónde está hoy «Mi cuenta»

🟩 **ENTERA en el cajón.** `/mi-cuenta` y `/mi-cuenta/pedidos` ya no pintan nada: sirven la home y el
cajón se abre solo en su zona (`Http\Sidebar\AccountDoor`).

| Zona del cajón | Qué cubre |
|---|---|
| `ORDERS` | «Mis reservas»: historial, ledger financiero completo, reintento y **las respuestas del pack bajo demanda** |
| `PROFILE` · `PASSWORD` · `SESSIONS` | Tus datos con el ciclo del correo pendiente · contraseña · cerrar las demás sesiones |
| `PRIVACY` | Consentimientos · descargar mis datos (art. 20) · borrar la cuenta (art. 17) |
| `HOME` | El índice, con su próxima reserva |

⚠️ **«Cerrar sesión» NO está en el índice, y es una decisión** (`#120(t)`, owner): sigue vigente.
⚠️⚠️ **Pero su premisa era FALSA y se corrigió el 2026-08-23**: decía «ya existe dos veces fuera, en el
nav y en el bloque `.acct`». Medido: **existe UNA**, la del bloque — `route('logout')` sale una sola
vez en toda la aplicación, y con sesión el nav es un botón que solo abre el cajón. La decisión no
cambia; el riesgo sí, y lo resuelve `specs/account-context-vue.md` §4.8.

⚠️ **Lo único que sobrevive de la web**: `GET /mi-cuenta/exportar`, que es una **DESCARGA** y no una
vista. Sirve el MISMO documento que `GET /api/v1/me/export`, y hay un test que los compara campo a
campo — es lo que impide que vuelvan a divergir.

### Lo hecho, en una línea por tanda

- 🟩 **Tanda 1 (leer)**: cinco pasos, `V4`–`V7`. `#120(g)`–`(m)`.
- 🟩 **Tanda 2 (gestionar)**: contraseña y sesiones, perfil, y los dos derechos RGPD. `V8`–`V10`.
  `#120(n)`–`(s)`. ▶ **Su efecto de fondo**: de los **cuatro** sitios de la web que reconfirmaban
  contraseña **sin techo**, no queda ninguno — y no se escribió una línea de limitador en la web.
- 🟩 **Tanda 3 (retirar)**: primero se publicó lo que solo sabía la página —el desglose financiero y
  los consentimientos— y **después** se borró. `V11`–`V13`. `#120(t)`, `#120(u)`.
  ▶ **La auditoría fue la mitad del trabajo**: encontró **dos huecos reales** que nadie vigilaba —el
  reintento de la API sin techo comprobado y las líneas fantasma que solo la API publicaba— y ambos
  se cerraron antes de borrar nada.

### Cinco cosas que condicionan lo que toques aquí

| | |
|---|---|
| **Las PUERTAS** | `/mi-cuenta` y `/mi-cuenta/pedidos` abren el cajón en su zona. ⚠️ La zona se aplica en `bootSpaEngine()` **y no en `open()`**: el cajón que llega por una puerta **nace abierto**. Es el camino que ya dejó un hueco vacío en `#59(b)` y volvió a morder en `#120(u)` |
| ⚠️ **La red** | **NO es el diff de árbol** (`#120(e)`): es paridad de DATOS contra la API + navegador. `render-sidebar.mjs` no importa la raíz |
| ⚠️ **La cadena flex** | `.sidecart__body` → `#sidecart-spa` → `.purchase` → `.purchase__scroll` son **hijos DIRECTOS**: un envoltorio router la parte y **ningún test lo ve** (`specs/area-cliente.md` §4.9) |
| ⚠️ **Los presupuestos** | ⚠️⚠️ **NO se copian aquí los números: viven en su test y esta tabla ya envejeció una vez.** Decía 190/4.800 cuando el código llevaba un día en **199 KiB** y **5.720 B** —la sesión de la auth los subió y nadie refrescó esta fila (medido el 2026-08-23)—. Los VIVOS son `SidebarBundleBudgetTest::SIDEBAR_CHUNK_MAX_KB` y los dos techos de `SidebarMountTest` (anónimo y con sesión), cada uno con su ledger al lado. Lo siguiente que entre los sube **a propósito, con su medida y su párrafo**, y al cerrar **baja a lo medido** |
| ⚠️ **El techo de componentes** | 40 líneas por `.vue`. Ya obligó al rediseño correcto una vez (`#120(r)`): si vuelve a apretar, la pregunta es qué sobra ahí, no cuánto subirlo |

⚠️ **Y una regla de trabajo que esta fase dejó pagada con tres fallos**: en un refactor o una feature
del ORQUESTADOR, **el contrato de árbol no es red** —`render-sidebar.mjs` no importa la raíz y su
comentario dice por qué—. La red es el NAVEGADOR. Receta del andamio, con sus trampas medidas, en
`VERIFICACION-E2E-CAJON.md` §5.bis y §5.quater.

### ❗ Bloqueado, y lo desbloquea el owner

❗❗ **EL SCHEDULER NO CORRE EN STAGING** (`DECISIONES #115`). El crontab está instalado y correcto y
`schedule:run` funciona a mano, pero **no hay demonio cron en el contenedor del sitio**. Medido: 6
avisos con 24 h en `jobs` y `attempts = 0`, y un pedido 24 h sin caducar que `orders:expire` caducó al
instante al lanzarlo a mano.
⚠️ **RE-CONFIRMADO el 2026-08-23** en el despliegue de hoy: el propio `deploy.sh` reinstaló el crontab
—«1 entrada, sin duplicados»— y su verificación de salud volvió a avisar de que **no se ve ningún
demonio cron**. Las cinco tareas están REGISTRADAS en la app y no hay jobs varados, así que lo único
que falta es quien las dispare.
▶ **La entrada exacta que hay que poner en el panel de Enhance, y cómo comprobar que funciona, están
en `ENTORNOS.md` §4.** Mientras tanto se dispara a mano:
`ssh jumpweb-staging "cd ~/public_html && php artisan schedule:run"`.
⚠️ Obliga a matizar `#110`: sus cuatro caminos siguen valiendo —ninguno depende del cron— pero **allí
nunca se ha ejercitado la caducidad de pedidos ni el envío diferido de correo**.

▶ Y luego, `scripts/provision.sh` (`#102(f)`), que necesita un token nuevo del panel: el que se usó
para medir lo retiró el owner.

⚠️ **SIETE trampas MEDIDAS que condicionan lo que venga.** No se explican aquí —cada una tiene su
sitio y duplicarlas es lo que envejece esta foto—; se nombran para que no te pillen:
- **Un `assertSee` de un texto del grupo `tickets` contra una página completa NO PRUEBA NADA**: el
  montaje del cajón lo lleva entero en cada página. Receta y porqué: `TESTING.md` **§2.ter**.
- **Lo que un gate declara que NO mira es un hueco con nombre** — así se sirvieron 20 iconos vacíos:
  `TESTING.md` **§2.quater** y `DECISIONES #113`. ⚠️ **Y a veces el gate ni lo declara**: el contador
  de `SidebarComponentBudgetTest` miraba `api.get|post` y no los tres verbos que llegaron después
  (`#120(s)`). Al añadir una pieza, relee qué mide su guarda — no si sigue verde.
- **Un campo que NUNCA lleva valor se lee como un dato y no lo es**: el export publicaba
  `tickets[].code` con una columna que no existe, desde el commit fundacional (`#120(s)`).
- **Una comprobación que mide una cosa y se lee como otra es PEOR que no tenerla**: dos señales de
  salud en verde con el scheduler muerto (`#115`).
- **Que las piezas se llamen no significa que el valor LLEGUE**, y que los dos extremos estén probados
  no significa que el medio esté cableado: `#117`, `#118` y `#119(f)` son tres fallos vivos distintos
  de la misma familia, todos encontrados en un navegador y ninguno por la suite.
- ✅ **El modo `embedded` de `auth.login`/`auth.register` MURIÓ el 2026-08-23** (`#122`), como `#112(f)`
  anticipó: era la referencia de dos paridades de árbol y las dos se fueron con él. De sus 14 casos,
  **8 no comparaban superficies** y están mudados a `SidebarMountTest`, `Api\V1\AuthRegistrationTest`
  y `SidebarAntiBotTest` — uno de ellos llevaba dentro el techo del payload del montaje.

### Lo que NO depende de nosotros

- ✅ **Servidor de PRUEBAS**: `jumpweb.sites.aelium.app` (`#76`), **desplegado y sirviendo** (`#106`).
  **0 LIVE · 0 PRODUCCIÓN.** Las cuatro cosas que estaban atascadas por falta de URL pública
  —Turnstile, S2S, 3DS y móvil— **están verificadas** (`#110`).
  ⚠️ **Dos diferencias con local que siguen condicionando el trabajo** (`ENTORNOS.md` §4): la BD es
  **MariaDB 11.4, no MySQL 8.4** —«verificado en staging» **NO** equivale a «verificado en MySQL», y
  ninguna conclusión sobre concurrencia sale de ahí— y **no hay node/npm**, así que los assets se
  construyen fuera y se suben compilados.
  ⚠️ **El bucle de trabajo sigue siendo LOCAL**; staging se toca EN BLOQUE y con guion escrito
  (`VERIFICACION-E2E-CAJON.md` §5.ter).
- **Pendiente del owner** (❗), por gravedad:
  1. ❗❗ **ACTIVAR LAS TAREAS PROGRAMADAS** del sitio en el panel de Enhance — sin cron no hay envío de
     correo ni caducidad de pedidos (`#115`). La entrada exacta, en `ENTORNOS.md` §4.
  2. Un **token nuevo de la API del panel** para `scripts/provision.sh` (el de medir se retiró).
  3. ❗ **Una pasada por el SANDBOX de Redsys para el reembolso REST de punta a punta**: que
     `Redsys::executeRefund()` hable de verdad con la pasarela y su respuesta se parsee bien. La
     auditoría del desglose lo dobló a propósito —una auditoría de dinero no hace llamadas externas—
     y **local no lo puede probar**. Herramienta canónica: `php artisan redsys:verify-sandbox`
     (`PAY-08`). Es el único hueco de esa auditoría que no se cerró.
  3. 2FA del panel · backlog de producto de Fase 6.
  ✅ Resueltos: el acceso SSH del 2º puesto (2026-08-21) · **las dos comprobaciones de navegador que
  cerraban `4.7`** (2026-08-22) · **la spec del área de cliente**, validada el 2026-08-22 —modelo de
  navegación y modo `account`, `#120(d)`— · y las **cuatro decisiones de producto** que el área pidió
  sobre la marcha: publicar la entrada de verdad en el export (`#120(s)`), no llevar «Cerrar sesión»
  al índice, publicar los consentimientos y enseñar las respuestas del pack bajo demanda (`#120(t)`,
  `#120(u)`).

## ▶ Hasta dónde llega hoy el motor SPA, dicho sin optimismo

El cajón **recorre el embudo entero y vuelve**: catálogo → día → hora →
cantidad → complementos → carrito → identificarse (entrar o **crear cuenta** dentro del cajón) → pagar
→ auto-POST firmado a Redsys → y los **tres desenlaces** (reserva creada con su resumen, rechazo con su
motivo y reintento, y el sondeo cada 5 s del terminal *data-less*). Con las reservas pausadas sustituye
el flujo por el aviso de mantenimiento. La cesta sobrevive a la recarga.

⚠️ **Residual de la pausa**: el estado se relee al cargar la página, en cada apertura del cajón y al
pulsar «Ir a pagar». Un cajón ABIERTO y quieto no se entera del interruptor hasta cerrarlo, reabrirlo o
intentar pagar.

## ▶ El MAPA del cajón SPA (para no buscarlo a ciegas)

`resources/js/sidebar/` — **la lógica vive en módulos PLANOS sin Vue** (`CE-6`), y los componentes solo
pintan.

⚠️ **Desde la reorganización del 2026-08-22 hay TRES capas y conviene no confundirlas**: los módulos
planos (las reglas, probados con `node --test`), los **stores de Pinia** (`stores/`, el estado de cada
dominio) y los componentes (pintan). Y **el embudo ya no es la raíz**: `Sidebar.vue` son 16 líneas que
solo enrutan, y la compra vive en `sections/PurchaseSection.vue`. El ÁREA DE CLIENTE (`#66`) entra como
**otra sección**, al lado, no dentro. Esa separación es lo que hace que todo lo de abajo se pruebe con
`node --test` y se compare contra el servidor desde PHP ejecutándolo en Node.

    Sidebar.vue                16 líneas · 0 llamadas a la API   ← enruta y expone hacia fuera
    sections/PurchaseSection    438 líneas · 2 llamadas          ← el embudo entero
    stores/  (nueve)                                             ← el estado, por dominio
    steps/   (once)             3 a 33 líneas cada uno           ← pintan y solo pintan

| Módulo | De qué responde | Su red |
|---|---|---|
| `machine.js` | En qué paso está el cajón y a cuál puede ir. ⚠️ `FUNNEL_TRANSITIONS` está **cerrado**: una pantalla que no sea del embudo no va ahí (`#119`) | `machine.test.js` · `SidebarProgressParityTest` |
| `api.js` | El cliente HTTP y sus cuatro trampas medidas (cookie, `Accept`, CSRF url-decodificado, reintento del 419) | — ⚠️ **sin test propio** |
| `i18n.js` · `money.js` | Textos por CAMINO con plural de Laravel · importes que espejan `number_format` | `SidebarTextParityTest` · `SidebarMoneyParityTest` |
| `catalog.js` | El paso 1: agrupar el catálogo en secciones y renombrar campos | `catalog.test.js` · **el diff de árbol lo EJECUTA** (`#67`) |
| `calendar.js` | La rejilla del mes, los meses navegables y el mes en que abre | `calendar.test.js` (18 casos, `#68`) · el diff lo EJECUTA |
| `offer.js` | El paso 3: hora elegida, suelo y techo del selector, precio del día | `offer.test.js` (`#69`) · el diff lo EJECUTA |
| `progress.js` · `foot.js` | La banda de fases · el pie de cada paso | sus `*.test.js` · **el diff los EJECUTA desde `#71`** · `SidebarCartParityTest` |
| `cart.js` | Cesta: saneado, persistencia con su dueño, reconciliación y **qué respuestas faltan** | `cart.test.js` · `SidebarCartParityTest` · `SidebarPendingFieldsParityTest` |
| `paused.js` | El aviso de reservas en pausa y en qué pasos tapa | `paused.test.js` · `SidebarPausedParityTest` |
| `admission.js` | El paso del carrito al pago: identidad + elegibilidad + destino | `admission.test.js` · `SidebarAdmissionParityTest` |
| `login.js` · `register.js` · `forgot.js` | Identificarse, darse de alta y recuperar contraseña desde el cajón. ⚠️ `register.js` NO decide el contexto: lo manda quien llama (`#122`) | sus `*.test.js` · los literales los fijan `Api\V1\AuthSessionTest` y `Api\V1\AuthRegistrationTest` desde el servidor — las dos paridades de árbol murieron con el modal |
| `pay.js` | Crear el pedido y componer el formulario firmado de la pasarela | `pay.test.js` · `SidebarPayParityTest` |
| `outcome.js` | La VUELTA entera: resumen del 6, motivo del 10 con su reintento, sondeo del 11 | `outcome.test.js` · `SidebarOutcomeParityTest` |
| `stores/purchase.js` | El paso y las dos señales que el cajón publica hacia fuera | `stores/purchase.test.js` (nace tras `#59`) |
| `stores/date.js` | El estado del paso 2: días ofrecidos, día elegido, mes visible y lo que de ahí se deriva | `stores/date.test.js` (nace en la reorganización, `#119`) |
| `stores/time.js` | El estado del paso 3: horas ofrecidas, hora elegida y el tope del selector (**`max_quantity`, no `available`**) | `stores/time.test.js` |
| `stores/auth.js` | El paso 5 entero: los dos formularios, sus avisos, la pestaña, el bit del anti-bot y las dos peticiones | `stores/auth.test.js` |
| `stores/cart.js` | La cesta: líneas, dueño, presupuesto, avisos y persistencia (**las respuestas del evento NO se guardan**, RGPD) | `stores/cart.test.js` |
| `stores/outcome.js` | El desenlace: formulario de la pasarela, código del pedido, resumen, motivo del rechazo | `stores/outcome.test.js` |
| `stores/catalog.js` | El paso 1 y el producto elegido: secciones, fila del listado, ficha y etiquetas del evento | `stores/catalog.test.js` |
| `stores/selection.js` | La LÍNEA en construcción: cantidad, complementos y respuestas del evento (**nunca se persiste**) | `stores/selection.test.js` |
| `stores/booking.js` | Si las reservas están pausadas. Se PIDE, no se inyecta: la dueña acciona el interruptor con clientes dentro | `stores/booking.test.js` |

**El ÁREA DE CLIENTE tiene su propio juego**, con la misma separación en tres capas y sin una fila por
módulo aquí —cada uno lleva su `*.test.js` al lado, que es donde se lee su porqué—:
`account/navigation.js` (zonas, pila de retorno y rótulos; **el índice `HOME_ENTRIES` es la ÚNICA
puerta a una zona**, y hay guarda de que ninguna quede inalcanzable) · `orders.js` · `profile.js` ·
`privacy.js` (el fichero que el titular se descarga, con el DOM **por parámetro** para poder probarlo)
· `form-outcome.js` (traduce la respuesta de CUALQUIER formulario) · `form-run.js` (el guardián común:
limpia **antes** de llamar) · y los stores `section`, `account`, `orders`, `reservations`,
`credentials`, `profile` y `privacy`, uno por dominio. Las zonas viven en `account/zones/`.

Fuera de `sidebar/`: **`resources/js/ui/scroll-lock.js`**, el dueño ÚNICO de `body.no-scroll` con llaves
por superpuesto. Lo vigila `ScrollLockOwnerTest`; nadie más puede tocar esa clase (`#58`).

⚠️ **La regla que enseñaron las paridades**: cuando algo NO es atributo de contrato del normalizador
—`href`, `action`, `method`, los `name` de un formulario, **el texto**, y **el interior de un
`<svg>`**— el diff de árbol **lo da por bueno**. Si transcribes algo de esa clase necesita paridad
propia. Demostrado por mutación en el paso 9.
⚠️⚠️ **Y esa lista mordió DOS veces**: los 20 iconos se sirvieron VACÍOS (`#113`, hoy los cubre
`SidebarIconParityTest`) y los botones de mes **no emitían nada** desde 4.2·2 — un `@click` tampoco es
un atributo del DOM, así que los dos botones salían idénticos con y sin cableado. Hoy lo cubre
`SidebarEmitWiringTest` (`#119(f)`).

⚠️⚠️⚠️ **Y hay un límite MAYOR que ese, y conviene saberlo antes de confiar en el contrato**:
`scripts/render-sidebar.mjs` **NO importa la raíz del cajón** —no puede, lee `window.Alpine` y el
idioma del documento— y monta los ONCE componentes de paso con props que construyen los módulos
planos. O sea que el contrato prueba **los pasos y los módulos**, y **no ejerce el orquestador**.
▶ Para un cambio en el orquestador (`sections/PurchaseSection.vue`, `Sidebar.vue`) la red es el
NAVEGADOR: receta en `VERIFICACION-E2E-CAJON.md` §5.bis.

## ▶ Lo que NO hay que reimplementar (el terreno del dinero está entero)

- **Precio** → `Booking\Contracts\CartPricing`. `CartPricerTest` compara sus importes con el pedido
  REAL: es el espejo verificado de `OrderCreator`.
- **Admisión** → `Booking\Contracts\ReservationAdmission`: pausa, tope de pendientes, frecuencia y la
  extensión atómica del hold. `POST orders` llama a `admitReservation()`, que CONSUME ficha; el
  reintento, a `admitPaymentRetry()`.
- **Creación** → `OrderCreator` (`AFORO-01`: el lock con `zone_id` literal es la PRIMERA sentencia de la
  transacción; no metas ningún SELECT antes).
- **Ida del pago** → `Booking\Contracts\PaymentInitiation` (`open()`/`reopen()`), implementado por
  `Payments\Services\PaymentInitiator`. Lanza `PaymentInitiationException`, que vive en
  `Payments\Contracts` porque es lo que lanza el puerto.
- **LA SECUENCIA** → `Booking\Contracts\ReservationCheckout` sobre `CheckoutOrchestrator` (`#37`). **Es
  el sitio ÚNICO donde vive el orden**: admitir consumiendo ficha → crear con la ventana de retención
  (`AFORO-10`) → abrir el cobro sobre el pedido persistido → soltarlo **solo** si era el primer intento.
  ⚠️ **No lo reescribas en una superficie nueva**: pide `start()`/`retry()` y traduce el resultado
  (`CheckoutSequenceTest` lo prohíbe ejecutablemente fuera de `app/Domain`).
  ⚠️ **No envuelvas la secuencia en una transacción**: el rastro de incidencia haría rollback (`PAY-05`)
  y el lock de franjas quedaría sostenido durante la firma (`AFORO-01`).
- **Oferta de fechas/horas** → `Booking\Contracts\AvailabilityOffer` sobre `SlotOffer` (`AFORO-02`), con
  la cesta descontada. Publica DOS números: `available` para MOSTRAR y `max_quantity` para ACOTAR el
  selector — en un pack **no coinciden**.
- **Desenlace del pago** → `GET orders/{code}/payment-status`, con dos ejes (`order_status` ·
  `payment_status`) y el motivo del rechazo como código y como texto.
- **Errores de negocio** → `Http\Api\ReservationErrorMap`. Añadir un código es evolutivo; **partir uno
  existente rompe a todo cliente ramificado sobre él**.
- **La cesta que viaja por la API** → `Http\Api\CartPayload`, una sola forma para los tres endpoints.

⚠️ **Tres trampas de la API que la SPA pisa** (las **87** medidas están en `specs/api-v1.md` §10):
`Origin`/`Referer` hacen falta en TODAS las peticiones stateful, no solo en el login (§10.sexies 28) ·
la disponibilidad LLEVA la cesta y publica dos números (§10.nonies 46) · **la firma cubre la URL
EXACTA**, así que las URLs de API se firman aparte (§10.duodecies 64).

**Pendiente que hereda Fase 6** (`#35`): un cliente NATIVO averigua el desenlace del pago **solo
sondeando** `payment-status`, y eso exige `redsys_merchant_url` configurada — sin ella y con terminal
data-less, el pedido caducaría con la tarjeta ya cobrada (`PAY-02`).

## ▶ Índice de la Fase 4, paso a paso

El «qué se hizo y qué enseñó» de cada paso NO se repite aquí: vive en `00-REFACTOR.md` (checklist
verificable) y en `DECISIONES.md` (el porqué, con sus mediciones). Este índice es solo para llegar.

| Paso | Qué cerró | Decisión |
|---|---|---|
| 4.0a–4.0c | La costura, los seis huecos de API, la tokenización de `site.css` | `#39`–`#42` |
| 4.1 · 4.2 | Cimientos SPA · los tres primeros pasos con paridad de árbol | `#43`–`#46` |
| 4.3·1 → ·4 | Armazón y textos · pie y cesta · aviso de pausa · persistencia | `#47`–`#50` |
| 4.4a·1 · ·2 | El CTA de pagar decide · la pantalla de identificación | `#51`, `#52` |
| 4.4b·1 | El alta desde el cajón (y tres bugs de servidor que destapó) | `#53` |
| 4.5·1 · ·2 | La cesta pide lo que le falta · pagar y salir a la pasarela | `#54`, `#55` |
| 4.6·1 · ·2 | La reserva creada · denegado y verificando | `#56`, `#57` |
| §6 | Bloqueo de scroll con dueño único · **el extremo a extremo con navegador** | `#58`, `#59` |
| 4.7·1 · ·2a | El manifiesto congelado · el inventario deja de crecer | `#60`, `#61` |
| 4.7·2b·1 | El contador medía 25 de 32 · la suite dependía de la fecha | `#63`, `#64` |
| 4.7·2b·2 | Re-apuntar al servidor: `RedsysIdaTest`, `ModuleContractsTest`, `SidebarPayParityTest` | `#65`, `#75` |
| 4.7·2b·2·B | **El diff de árbol se alimenta del SERVIDOR** en los once pasos y el armazón | `#67`–`#73` |
| 4.7·2b·2·C | Los dependientes que no son paridades · el inventario queda CLASIFICADO | `#86`–`#98` |
| 4.4b·2 | **El widget del anti-bot en el cajón**, y dos centinelas que no mordían | `#108` |
| Staging | Despliegue (`deploy.sh`) · la guarda del dinero en verde falso · los CUATRO caminos verificados | `#105`–`#110` |
| 4.7·2b·3·0 | **Independizar el contrato de árbol ANTES de borrar** (el fixture salía del motor) | `#111` |
| **4.7·2b·3 + ·3** | 🟩 **`Purchase.php` RETIRADO y el flag con él** · y tres guardas que estaban en verde **sin medir nada** | **`#112`** |
| 4.7·2b·4 | **El cajón se servía SIN ICONOS**: 20 `<svg>` vacíos que el diff de árbol no podía ver | **`#113`** |
| Verificación | **A7 y los iconos, en navegador**: los enlaces profundos **nunca se cablearon** · el bloque de cuenta se oculta en la compra, y el «modo» del panel llevaba muerto | `#117`, `#118` |
| **Reorganización** | 🟩 **El cajón, en TRES capas y por secciones**: nueve stores, el embudo fuera de la raíz y el grafo cerrado — antes del área de cliente, a petición del owner | **`#119`** |
| **Área · tanda 1** | 🟩 **Leer**: el nivel sección, las zonas, el contrato de presentación, los datos, la puerta y la captura de huecos | `#120(g)`–`(m)` |
| **Área · tanda 2** | 🟩 **Gestionar**: contraseña y sesiones · el perfil y el ciclo del correo · **los dos derechos RGPD** — y la web heredó los cuatro limitadores que le faltaban | `#120(n)`–`(s)` |
| **Área · tanda 3** | 🟩 **Retirar**: primero se publicó lo que solo sabía la página (desglose financiero, consentimientos) y **después** se borró. La auditoría destapó **dos huecos reales** que nadie vigilaba | `#120(t)`, `#120(u)` |
| **Pulido** | 🟩 **Nueve retoques del owner, y CUATRO no eran cosméticos**: el modo del panel se saltaba el puente del motor, «Mis reservas» se servía sin tarjetas porque la transcripción inventó nombres de clase, el scroll arrastraba la página y el título de auth salía dos veces | **`#124`** |
| **Verificación** | 🟩 **El guion pendiente, recorrido**: `V17`, `V18` y `V23`. **Dos fallos reales** — el reenvío prometía correos que el servidor tiraba (4 reenvíos, 2 correos) y el velo de privacidad no se pintaba nunca | **`#125`** |
| **Mis reservas** | 🟩 **Se lista POR RESERVA, y el pasado a su propia pantalla**: endpoint por ámbito con los dos lados de UN predicado, el ledger bajo demanda y la tarjeta compartida. Lo que ENCONTRÓ: el escáner de CSS estaba ciego a los modificadores de `:class`, y la poda del payload dejó cuatro textos viajando vacíos | **`#126`** |
| **Bloque de cuenta** | 🟩 **El ÚLTIMO Livewire del layout, a Vue**: hueco con suelo servido, `<Teleport>`, endpoint `GET /me/account-context` y la semilla del montaje por el MISMO Resource. Lo que ENCONTRÓ: el `no-store` accidental de todo el sitio, la única salida de sesión de la app y un gate de iconos ciego a un tercio de sus componentes | **`#123`** |

⚠️ **Las lecciones transversales que más se repiten**, por si solo lees esto:
**una guarda con DOS fuentes redundantes no se puede medir mutando una sola** (`#112`: la aserción de
`livewire.js` llevaba tiempo inerte y la doc la daba por crítica) ·
**verde no es funciona** (`#59`: la fase entera transcrita y el motor no vendía) · **una foto que
incluye el tiempo hay que tomarla con el reloj parado** (`#64`) · **un test que compara contra un
artefacto tiene que comprobar que no está rancio** (`#69`) · **al re-apuntar un caso hay que volver a
mutarlo** (`#65`: pasó a ser inerte sin que nadie lo notara) · **el caso frontera se elige por el
MECANISMO del fallo, no por el síntoma** (`#68`) ·
**una guarda puede existir, cruzar las dos fuentes y estar bien razonada, y aun así leer el número
equivocado** (`#125`: al auditar una guarda la pregunta no es «¿mira algo?» sino «¿mira TODO lo que hay
ahí?») · **lo que un gate declara que no mira puede ser justo su caso principal** (`#126`: el escáner de
CSS se saltaba `:class`, que es donde vive un modificador) ·
⚠️⚠️ **y un FIXTURE IRREAL fabrica defectos igual de bien que los oculta** — la auditoría del desglose
de dinero lo pagó **tres veces** (`specs/desglose-dinero-cliente.md` §2, §4.bis.3, §4.ter): un
`extra_due` sin subir el precio, un pago sin sincronizar a `Σ itemCollectedCents`, y `unit_price`
tratado como total cuando es POR UNIDAD. **Antes de acusar al código, comprueba que el fixture
reproduce el flujo real.**

## Entorno (local)

- Docker (Sail) en WSL2, repo en `~/proyectos/jumpweb`. Web `localhost:8081` · MySQL `localhost:3308` ·
  Mailpit `localhost:8028`. **Siempre `-u sail` en `exec`** (como root deja ficheros de root en
  `storage/` → 500 por permisos).
- BD dev sembrada con SaltoPark: `admin@jumpweb.test` / `empleado@jumpweb.test`, contraseña `password`.
  ⚠️ La BD dev arrastra ADEMÁS el par `…@jumpingjump.test` del import, así que `User::first()` devuelve
  uno del origen — usa el email completo al probar a mano.
  ⚠️ **Eso es de la máquina donde se hizo el import, no del producto**: un clon nuevo sembrado con
  `migrate --seed` NO tiene ese par (medido el 2026-08-19 al montar el segundo puesto de trabajo).
  Si `User::first()` te devuelve un usuario limpio no es un fallo: es que estás en un clon nuevo.
- ⚠️ Si clonas de cero, comprueba que **`APP_URL` coincide con `APP_PORT`** en el `.env` (no versionado):
  con el puerto desalineado salen mal los enlaces absolutos de correo, las URLs firmadas y la derivación
  de CORS y de los dominios stateful de Sanctum.
- **El push exige `VERIFY_CONC=1`** —tras correr los dos comandos de `INVARIANTES §6`— si tocas el
  núcleo de dinero/aforo.

## Herencia

Base heredada del origen (2026-08-12): 30 modelos, 71 migraciones, 17 Filament Resources, Redsys en
sandbox y suite **2132** verde al importarla.
Recuento VIVO: 30 modelos · 73 migraciones · 17 Filament Resources. La migración añadida es
`personal_access_tokens` (Sanctum).
⚠️ **El contador de tests NO se repite aquí**: vive arriba, en «Dónde estamos», con su contexto.
`docs-check` vigila los tres números de esta línea y las invariantes —son sus cuatro patrones—, pero
**«N tests» no casa con ninguno**, así que repetirlo es drift en espera. Ya mordió: esta línea decía
**2715** mientras el cuerpo y la suite decían **2642** (medido el 2026-08-21, no ajustado). Misma
doctrina que se aplicó a las líneas de `Sidebar.vue`: una foto sin receta que la vigile, se retira.
Stack: Laravel **13.25** · Filament **5.7** · Livewire **4.4** · PHPUnit 12.5 · Sanctum **4.3** ·
Spectator **3.0** (dev) · Vite **8.2** · 0 avisos de seguridad (`composer audit` y `npm audit`).
