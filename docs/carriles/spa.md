# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **730–759** (700–729 agotada el 20-09) · Último usado:
> **`#732`** · La banda está dada de alta en la tabla de `DECISIONES.md` ·
> Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-21.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`;
> era 24). El contador de la suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-21, cierre de la sesión)

- ▶▶▶ **LO ÚLTIMO: `google-business-profile.md` (`#524`) va por la T2·6.** La **T1 entera**
  (`#720`→`#726`) y **T2·1→T2·6** (`#727`→`#732`: las tablas y sus dos plazos · el recorrido y su
  `coherent()` · la pasada diaria · las imágenes · «Ocultar» · la fuente nueva y la cascada de
  TRES). **Ya sincroniza sola y la portada no le pide NADA a Google.** Qué entró en cada tanda,
  **§4.1 de la spec**; lo que hace falta para retomar, el punto 1 de abajo.
  ⚠️ **CUATRO migraciones, solo en la BD local.**
  ▶ **En la T2·5 añadí «dejar de ocultar», que la spec NO pedía**: sin él un clic equivocado es
  irreversible para siempre. El porqué, en `#731`.
- ⚠️ **El techo de un fichero de carril pasó de 24 a 32 KB** (`#724`, `[DECIDIDO owner]`): se cambió el
  gate y la doc compartida. Los otros cuatro carriles **siguen diciendo «24 KB» en su encabezado** y no
  los toco (`#621`); va avisado en el buzón.
- 💥 **El repo se corrompió el 20-09 a las 14:36** por un corte de la VM de WSL (31 objetos de git a
  CERO bytes, el build de Vite vacío). Recuperado sin perder nada versionado; **la receta está en
  «Trampas vivas»**.
- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`): el armazón, el catálogo, el día y la
  hora, la cesta, pagar con sus cuatro desenlaces, las nueve de la cuenta y el suelo táctil.
- ▶▶ **`celebracion-e-invitacion.md`: las siete tandas y el borde `§7.1·5`, CERRADOS** (`#569`→`#718`)
  **y con el ✅ del owner en vivo (20-09)**. El detalle de cada unidad, sus arneses y lo que enseñó
  **viven en su § de la spec** (§10.1–§10.18), que es donde no caducan; aquí solo lo que hace falta
  para retomar:
  - **En PRODUCCIÓN solo van T1→T4·4** (v1.1.0 = `3547de9f`, 18-09) y **con los dos interruptores
    APAGADOS**. **SIN DESPLEGAR**: `#577`, `#578`, y la T5, la T6 y la T7 enteras — con **una
    migración nueva**, `order_items.eve_notice_at` (`#717`).
  - ⚠️ `party_invitations.reminded_at`/`reminded_count` son del **recordatorio del anfitrión** (`#713`,
    cuentan VECES) y **no envían nada**; el aviso de la víspera tiene su propia marca (`#717`).
- ✅ **`#707` · `DependentsZone.vue`** (lo levantó plataforma): `addBtn` sin declarar tiraba el foco al
  `<body>`. El gate `CE-6` paró el commit: no se subió el techo, bajó `signDependent()` al módulo
  plano. `FROZEN_JS_ERRORS` **12 → 10** · sonda `scripts/sonda-foco-cuenta.mjs`.

## Por dónde retomar, en orden

1. ❗❗ **`google-business-profile.md` (`#524`), reclamada — la fuente REAL de las reseñas.** Sustituye a
   `google-reviews.md` (Places), que queda de registro; desbloquea las reseñas de la landing **y** las
   que plataforma dejó fuera de `/social-proof`. ⚠️ Es del carril de la WEB (580–609), ya avisado.
   ✅✅ **T1 (`#720`→`#726`) y T2·1→T2·6 (`#727`→`#732`), EN EL ÁRBOL.** Qué entró en cada tanda y
   sus trampas, **§4.1 de la spec**, que es donde no caduca.
   ⚠️ **De la pantalla, el owner solo ha visto «sin configurar»** (20-09): el resto de estados, la
   lista de fichas y el botón de desconectar están afirmados por caso, no por ojo. Y **el correo
   nuevo no se ha visto renderizado**. **Tampoco se ha visto una reseña pintada.**
   ❗ **RENUNCIA CON RECIBO en la T2·4**: las fotos salen con `no-store` (`NoStoreWebResponses`, global
   por `RGPD-04`), así que **cada visita vuelve a pedir cada foto**. Eximir esa ruta es tocar un
   middleware escrito incondicional a propósito: va a la T2·6.
   ❗❗❗ **LO MÁS IMPORTANTE QUE DEJA LA T2·6, Y NO SE CIERRA DESDE AQUÍ**: `#666` mudó la portada a
   la INSTANCIA, así que la sección que ven los clientes **está en otro repo**. El producto garantiza
   que `socialSelection` viaja en `CONTRATO_DE_VISTAS` y que su anfitrión mínimo la pinta. ▶ **Antes
   de que las reseñas de la ficha se vean en producción, la portada de la instancia TIENE que pintar
   la línea del filtro**, o el parque enseñaría reseñas filtradas sin declararlo (Ómnibus). Avisado a
   plataforma. ⚠️ Hoy no hay exposición: responde Places, que no filtra.
   ⚠️⚠️ **`[owner]` 21-09: Places NO se retira todavía**, así que la cascada es de TRES y **`img-src`
   sigue nombrando a Google** — §4.3·12 va con la retirada, no antes.
   ▶▶ **LO SIGUIENTE, a elegir**: (a) el **botón del panel** que encola la pasada, `Retry-After` y el
   gancho que fuerza una pasada al cambiar el mínimo · (b) el **texto de privacidad** y la ponderación
   de interés legítimo (§4.3·11), lo único de la T2 con parte legal pendiente · (c) la **T6, el
   horario**, que es la única que ESCRIBE en la ficha · (d) **retirar Places**, y con ello §4.3·12 y
   la caché de las fotos. Toca `PERF-02`, `SEC-01`, `RGPD-05`: **§5 antes**.
   ⚠️⚠️ **CUATRO migraciones de esta spec, aplicadas SOLO en la BD local.** Empujada ≠ aplicada.
   ⚠️ Lo que queda de la T1 no es código: el ojo del owner y las credenciales. `verify` contra la
   ficha REAL espera al §7·A. **Todo contra un DOBLE**, con `Http::preventStrayRequests()`.
   ▶ **`#719`: la identidad ante Google es JumpSystem** —cuenta, dominio y web propios, que monta el
   owner—. El §7·A no necesita ficha propia. Empieza por el §0 y el **§1.3**.
   ⏰⏰ **EL CALENDARIO LO MANDA LA FICHA: la de PlayJump lleva MENOS de 60 días** (owner, 20-09). La
   solicitud del §7·A·2 no se puede mandar todavía y la conexión real no llega antes de finales de
   octubre. El doble no es una opción: es el único camino.
   ❗ La doc ajena miente sobre el umbral: el aviso, en el buzón de la WEB. Manda el código.
2. ✅ **La invitación no tiene nada pendiente de CÓDIGO** (`#718`, spec §10.18). ▶ Queda **desplegar y
   encender**, y no es mío: T5, T6 y T7 **con la migración** `order_items.eve_notice_at`; los dos
   interruptores son dato del owner, avisado en su buzón.
3. **Lo que el ✅ del owner NO cubre**, declarado sin medir: el **`.ics` en un TELÉFONO de verdad**
   (§4.6 — si Android no lo abre, el enlace de Google Calendar como segunda opción) · el
   **justificante EN PRODUCCIÓN** con el **Turnstile REAL** · `§7.2·R12`. ⚠️ Y `og:image` sale del
   logotipo del tema (1200×441): en tarjeta 2:1 se ve con bandas.
4. **Los diez puntos de `§10.4.7·B`**, ninguno urgente con los interruptores apagados. ▶ **Empieza por
   la RAÍZ**: `matches()` y `takeSlotFor()` no son la misma regla, y explica tres de los cuatro
   naranjas. Ninguna prueba de `companion` manda un valor **inválido**, así que la lista blanca de
   `completeReply()` sigue sin ejercerse; la rama `guest_data`, sin prueba.
5. De la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas se ha visto
   en uno) · el **cuaderno de entrega** del cajón · el **botón del sistema** (16/800 con borde).
6. Del plugin quedan **tres frases** por ver: `/sonda`, `/dod` y `/ligero`.

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa antes
en el buzón**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⏰⏰ **EL RELOJ: el contenedor va en UTC y el parque en Madrid, y entre las dos medianoches NO es el
  mismo día.** Dos formas de pagarlo, medidas: `DisplayTime::dayLabel()` **no convierte de zona**
  —sin `setTimezone`, un sello de las 00:30 de Madrid se fecha el día anterior—; y **un test con
  reloj propio miente dos horas al día** (`ScheduleFactsTest`, rojo a las 00:07). ▶ En un test de
  «ahora», el reloj a **`DisplayTime`, nunca a `Carbon`**.
- ⚠️ **Lo que desborda a lo ALTO no lo dice una medida de ancho**: un `textarea` con `rows="5"` traía
  su propio scroll y las cifras de la sonda salían verdes. **Lo vio la captura.** Y dos pesos de botón
  en un bloque pequeño compiten con el «Guardar» de la barra.
- 🩹 **Los fixtures locales de la invitación** (pack 105, `R-PRBT1A`, `R-PRBT64`) y su puerta de
  entrada `probe-ojo-invitacion.php` **viven en la spec §10.19**. ⚠️ De ahí, lo que vale para CUALQUIER
  sonda: un guion suelto se corre
  `php artisan tinker --execute="require base_path('storage/app/…')"` — con `php storage/app/…` a
  secas no hay framework y sale «Class not found».
- **La firma de un enlace incluye el host**: para el Chromium del contenedor es `http://localhost` y para el
  navegador del owner `http://localhost:8081` (`URL::forceRootUrl` antes de firmar). Fixtures locales en la
  carpeta de almacenamiento de la app, no versionados: «probe-postform» (reserva `R-PRBT1A`), las tres sondas
  de ventana «probe-t3-…» y «probe-t3-urls», que imprime los enlaces para el owner.
- 💥💥 **UN CORTE DE LA VM DE WSL DEJA FICHEROS A CERO BYTES Y ROMPE GIT** (20-09): 31 objetos de
  `.git/objects` vacíos —el commit en curso entre ellos— y medio `public/build`. Síntoma: «*object
  file … is empty*», «*bad object HEAD*». ▶ **Receta medida**: apartar (no borrar) los vacíos
  (`find .git/objects -type f -empty`) · `git update-ref refs/heads/main <sha ENTERO del reflog>` ·
  `git fsck` · `git reset` (el *cache-tree* apunta a un objeto muerto) · `npm run build` y
  `build:ssr`. ⚠️ **Lo versionado NO se pierde.** No era ENOSPC: 947 GB libres.
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium`
  (con `npx` cae en otra caché, y **no** en `~/.cache/ms-playwright`: vive en `node_modules/…/
  .local-browsers`); `npm install` poda `playwright-core`. ⚠️ **`compose.yaml` ganó un montaje el
  19-09** (`#647`): el próximo `docker compose up -d` recrea el contenedor y se lleva el Chromium.
- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras un arnés
  de mutación (restaura el árbol, no el bundle) **y siempre que toques un `.vue`** (si no, 36 rojos que no
  son tuyos).
- ⚠️⚠️ **Dos de la invitación que valen fuera** (el caso, en su spec §10): **un COMENTARIO puede
  romper un censo** —si nombras en prosa el patrón que un escáner busca, cámbialo (`#715`, 12 rojos
  de golpe)—; y **reordenar filas rompe lo que cuelga de la posición**, así que solo al recortar y
  **después** de `orderGuestRows()`.
- **Una combinación que el modelo prohíbe se monta por el CONSTRUCTOR DE CONSULTAS** en el fixture: el
  guard de `saving()` lanza, y el escenario real contra el que defiende es justo ése (una importación,
  un `update()` a mano). Con `create()` el caso no existiría.
- ⚠️⚠️ **Dos trampas de Eloquent y de Larastan, medidas** (`#720`): `getRawOriginal()` da lo LEÍDO de la
  base, no el atributo vigente —entre `$m->campo = 'x'` y su `save()` entrega el ANTERIOR; usa
  `getAttributes()`—, y con un cast el desfase no rompe nada visible; y **Larastan declara MUERTO un
  `catch` tras una propiedad con cast** y se equivoca (no ve el `__get`). Cero ignores inline en el
  repo y la base solo encoge: la salida es que el código **diga** que puede fallar.
- ⚠️⚠️ **Y DOS MÁS de Larastan, medidas en `#727`, que ahorran una entrada en la línea base**: (a)
  sobre una columna **NOT NULL** con cast, un `!== null` es `notIdentical.alwaysTrue` **y Larastan
  tiene razón** —es al revés que la trampa de arriba: lo que decide es si la columna admite `null`—;
  (b) **`prunable()` declarado `@return Builder<Modelo>` siempre falla** porque `static::query()`
  devuelve `Builder<static>` y la plantilla **no es covariante**. Los cuatro `prunable()` que ya
  existían lo pagaron con una entrada en la base; se arregla escribiendo **`@return Builder<static>`**.
- ⚠️⚠️⚠️ **EL DOBLE DE `Http` MIENTE DE TRES FORMAS, las tres medidas** (`#729`–`#730`):
  (a) **`Http::fake()` FUSIONA**, no reemplaza — dos llamadas en un caso dejan ganando a la primera,
  y una `sequence()` agotada revienta «*response sequence is empty*» **señalando al código**;
  (b) un **`Http::response()` estático se CONSUME** al leerlo en flujo, así que la segunda lectura
  llega vacía y el caso acusa al código de no deduplicar;
  (c) un **`catch (Throwable)` ancho se traga el `StrayRequestException`**, así que un descargador
  «probado» no descargaba nada y salía verde. ▶ Receta: **un solo doble en `setUp()`** que delegue en
  una propiedad —de regalo cuenta peticiones—, el cuerpo en un **cierre**, y atrapar solo
  `ConnectionException`. *Un `catch` que parece más seguro suele medir menos.*
- ⚠️⚠️ **UNA GUARDA NECESITA QUE EL CASO LLEGUE A ELLA**, y cuatro formas de que no llegue, todas
  medidas: un nombre mal formado que **no existe** en disco da 404 por «no existe» (`#730`); un 404
  de prueba **sin cuerpo** no prueba que se mire el estado (`#730`); `inProgress()`, que **coge** el
  candado para mirar, necesita **dos** llamadas (`#729`); y una foto que caduca necesita una reseña
  **con** foto (`#732`). ▶ Antes de declarar un superviviente, pregunta qué caso falta.
- ⚠️⚠️ **UN `finally` PUEDE SOLTAR EL CANDADO ANTES DE TIEMPO, y ningún test de un hilo lo ve**
  (`#729`): la lectura iba en el `try` y la escritura DETRÁS del bloque. ▶ Se caza preguntando por el
  candado **desde un evento del modelo** durante la escritura. Mismo patrón para todo lo que tenga
  que pasar «mientras».
- ⚠️ **`lock()` no está en el contrato `Repository` NI en su clase**: vive en el `Store` y el
  repositorio lo reenvía por `__call` (`#729`). La salida es `getStore()` con `@var LockProvider`,
  que además dice lo que de verdad se exige del almacén.
- ⚠️⚠️ **UNA GUARDA DE HOST NECESITA DOS CASOS, NO UNO** (`#728`): `lh3…com.malo.net` se cuela con
  `str_contains` y `evil.lh3…com` con `str_ends_with` — **son defectos distintos**. ▶ El caso va por
  TRIPLICADO: pegado por detrás, pegado por delante, y el control positivo.
- ⚠️⚠️ **UNA LISTA VACÍA NO DISTINGUE «no hay» de «no sé mirar»** (`#727`): `getForeignKeys()`
  devuelve `[]` tanto si no hay FK como si el lector no sabe leerlas en SQLite, y un bucle de
  reflexión que no recorra nada sale igual de verde. ▶ **Todo caso que afirme una AUSENCIA pide
  primero lo mismo a algo que SÍ la tiene**; si el control no muerde, el caso no mide nada.
- ⚠️⚠️⚠️ **El constructor de consultas de ELOQUENT SÍ escribe `updated_at`** (`Builder::update()` llama
  a `addUpdatedAtColumn()`), así que **no sirve para marcar nada sin mover el testigo** del post-form:
  hay que bajar al crudo con **`toBase()`**. La spec lo afirmaba al revés desde el diseño y nadie lo
  había ejercido (`#717`). Si ves «por el constructor de consultas» en una doc, compruébalo.
- 💰 **«A pagar en el parque» se monta con la SEÑAL** (`OrderAdjustment` tipo `deposit_split`), no con
  un cobro parcial a pelo: eso rompe las identidades del libro y el saldo sale **`under_review`**, que
  también devuelve 0 y deja el caso verde por el motivo contrario. ▶ Y **`pay_online` tiene cifra
  POSITIVA sin ser dinero del parque**: `max(0, $saldo)` no distingue las clases (`#716`).
- ⚠️⚠️ **Un filtro que no ejecuta nada también sale ≠ 0**, y **Pint DESTROZA los nombres de método con
  palabras en MAYÚSCULAS** (`_UN_` → `_u_n_`, medido el 20-09): así se rompe un arnés **en silencio**.
  Los tests se nombran **sin mayúsculas**, y una mutación se cree tras ver el MISMO filtro en verde
  ejecutando su caso. Aseverar una subcadena sobre HTML acusa al script que la nombra (`#553`).
- ⚠️⚠️ **Lo que se afirma que NO pasa hay que hacerlo POSIBLE primero** (`#721`): dos supervivientes
  eran casos que negaban una llamada cuyo endpoint **no estaba fingido** —imposible— y con un `catch`
  que se tragaba el cortafuegos. «No se llamó» era cierto por el motivo equivocado.
- ⚠️⚠️⚠️ **DOS APIs DE GOOGLE NOMBRAN LA MISMA FICHA DE DOS FORMAS** (`#726`, medido contra la doc
  oficial): `locations.list` (v1) devuelve `locations/{id}` **sin cuenta** y `reviews.list` (**v4 y
  otro host**) exige `accounts/{id}/locations/{id}`. Guardar solo lo primero deja la sincronización
  sin poder pedir **ni una** reseña, y leyendo el código no se ve: hay que ir a la doc de CADA
  endpoint. ▶ Si una tanda cruza dos APIs del mismo proveedor, **comprueba cómo nombra cada una lo
  mismo** antes de guardar nada.
- ⚠️⚠️ **Dos trampas de forzar el ENTORNO en un caso, medidas el 20-09** (`#723`): `$this->app['env']
  = 'production'` **enciende la verificación de CSRF** que el entorno de pruebas apaga, así que un POST
  vuelve **419** y el caso mide el token, no la guarda —esa se mide por el SERVICIO—; y
  `config(['app.url' => …])` con un host distinto de `localhost` hace saltar «**Untrusted Host**» de
  Filament antes de llegar al controlador.
- **`Str::ascii()` SÍ transitera cirílico, griego y árabe** (medido el 17-09): los que deja vacíos —y
  por los que existe `PersonNameKey`— son chino, japonés, coreano, tailandés, hebreo y emoji. La prosa
  heredada decía «alfabeto no latino» y era falsa.
- **Un modelo nuevo necesita alias de morfo** en `AppServiceProvider` o `MorphMapTest` pone la suite en
  rojo, y el rojo aparece en la suite COMPLETA, no en el filtro de tu tanda.
- ⚠️⚠️ **Una migración empujada NO es una migración aplicada**: la suite migra en SQLite en memoria, así
  que ni ella ni el gate ven que falte en la BD MySQL de desarrollo. Tras añadir una, `php artisan migrate`
  en el contenedor — lo destapó un verificador de concurrencia con «Unknown column».
- **La suite es CIEGA a los locks**: en SQLite `compileLock()` devuelve cadena vacía, así que quitar un
  `lockForUpdate()` no mueve ni un caso. Esa guarda la dan los verificadores sobre InnoDB, y su verde
  vale solo si se ha visto FALLAR sin el lock. ▶ Pero una suma atómica **sí** se ve sin concurrencia:
  dos instancias LEÍDAS antes de que ninguna escriba distinguen `DB::raw('col + 1')` de `$m->col + 1`
  (`#713`). Antes de declarar un superviviente, busca ese caso.
- **Un test que calcula su expectativa desde el código bajo prueba no prueba nada**, y un fixture que usa
  la convención que dice vigilar tampoco: las dos las cazó el arnés de mutación, no una relectura.
- **Al pivote se le habla por MÉTODO, no por propiedad** (`showsInInvitation()`, `saleStage()`…): un
  `$record->pivot?->columna` suma un `property.notFound` a la línea base de Larastan, **que solo encoge**.
- ⏰ **La fecha de una decisión sale del reloj del OWNER** (`date` en el host), nunca del contenedor, que
  va en UTC y marca dos horas menos: dos sesiones fecharon «la madrugada del 28» siendo la noche del 27.
- **Un fixture que basta para una superficie puede no bastar para otra**: sin una `RateType` en la BD,
  `GET catalog/products/{id}` responde **404**, no 200 con otro contenido.
- **Una guarda nueva que tumba un test viejo suele tener razón**: el guard de `#575` puso en rojo un caso
  de `#574` que construía la combinación ya prohibida. Se reescribe el caso, no se relaja la guarda.
- ⚠️⚠️ **Un test de datos reales NO distingue «pregunta por el contrato» de «va a mirar»**: en `#576`, hacer
  que `GuardianPlaces` llamara a `PartyGuestsReader` en vez de al contrato dejó `InvitationPlacesTest`
  entero en verde. Solo lo caza el DOBLE de `ModuleContractsTest`. Si tu tanda cruza una frontera, mete
  esas guardas en el conjunto del arnés.
- **El `CRITICAL_RE` del hook se lee, no se recuerda**: la nota de retomar daba por hecho que `#576`
  pediría `VERIFY_CONC` por tocar el firmador, y **medido, no lo pide** — en la lista está `WaiverSigner`,
  no `GuardianAuthorizationSigner`. Comprobarlo cuesta un `grep`; suponerlo cuesta una sesión.
- **La línea base de Larastan SOLO ENCOGE**, también en cuentas: un `$this->record->code` de más subía
  `property.nonObject` de 1 a 2 ocurrencias en un fichero que ya estaba en la lista. Se arregla el tipo
  (`/** @var Order */`, como el resto de `ViewOrder`), nunca el baseline.
- ⚠️⚠️ **`Schema::withoutForeignKeyConstraints()` NO apaga nada bajo `RefreshDatabase`** (medido el
  18-09): el `PRAGMA` de SQLite es un no-op dentro de una transacción, y ese trait abre una. Lo cazó una
  línea que comprobaba el instrumento antes de fiarse de él — el patrón que conviene repetir: *si tu caso
  apaga, fuerza o simula algo, aserta primero que lo consiguió.*
- ⚠️⚠️ **UN SUPERVIVIENTE DEL ARNÉS ES UNA PREGUNTA SOBRE EL TEST, no sobre el código** (`#578`,
  y se ha repetido en `#728`, `#730` y `#732`): primero «¿qué caso me falta?», y **solo** si no hay
  ninguno se declara (`#577`, defensa en profundidad que otra capa cubre). Bajar el denominador para
  enseñar un 5/5 limpio es mentir en el informe.
- **En OpenAPI 3.0 `nullable` NO atraviesa un `$ref`** y `allOf: [$ref] + nullable` **no valida** con
  Spectator (`#27`, y ya van tres veces). La salida de la casa es **copia INLINE + guarda de divergencia**
  en `ApiContractTest`. ▶ Y un array PHP vacío se serializa `[]`, no `{}`. ▶ Las `responses` reutilizables
  son solo `NotFound`, `TooManyRequests`, `Maintenance`, `Unauthenticated` y `ValidationFailed`; **no hay
  `Forbidden`**, se escribe inline.
- **`Route::has()` no ve una ruta declarada a mitad de un test** hasta
  `Route::getRoutes()->refreshNameLookups()`: el índice por nombre se construye una vez.
- ⚠️⚠️ **`$request->query()` NO lee el cuerpo, y por eso un caso puede pasar sin ejercer nada**: el
  primer caso de `#704` ponía el `invitation_reply_id` en el POST y «pasaba» — pero la guarda que creía
  probar **no se ejecutaba**. Lo cazó el ARNÉS (6/7), no una relectura.
- **Una guarda que ninguna prueba puede poner en rojo es ruido, no defensa**: en `#704` se escribieron
  dos re-comprobaciones de acceso dentro de ayudantes a los que solo se llega **después** de
  `authorizeGuardianAccess()`. Se retiraron en vez de declararlas.
- **Una costura se prueba ANDÁNDOLA**: el defecto más caro de la T5·5 (la vuelta del formulario
  rechazado perdía la atadura) no lo veía ningún test de dominio, y los tres ya existían.
- ⏰ **Techo de una decisión: 1,5 KB, y `docs-check` NO lo mide** (sí mide el §0 de una spec, 2 KB, y el
  tracker, 16 KB): `#713` salió a 1604 B y hubo que recortarlo. Mídelo con `python3` antes del commit.
- 🩹 **En la BD LOCAL hay 19 titulares con la cadena de waiver ROTA** (18-09): basura de desarrollo del
  26–27 de agosto, **no un defecto**. Así que **`WaiverChain::verify()` sale rojo de fábrica en local**:
  si mides cadenas, compara ANTES/DESPUÉS.
- ⚠️⚠️ **Un campo de texto vacío llega como `null`**, no como `''` (`ConvertEmptyStringsToNull` corre
  antes de validar): con `['sometimes','string']` el anfitrión que borra una línea recibe **422 y ningún
  cambio**. Medido en la T6·1; en la API es contrato y **lo coge plataforma**.
- ⚠️ **Una captura de VENTANA sin bajar hasta lo que quieres ver son dos capturas idénticas**: lo
  delató el tamaño del fichero, no el ojo. Y `DisplayTime::dayLabel()` ya termina en punto.
- **F4 cerró y el cajón es un PAQUETE**: dónde vive cada cosa, en `specs/cajon-empaquetable.md` §0, y
  sus seis trampas en el §4.8 — se lee de ahí. Tocar «HOJA ENFOCADA» de `site.css` obliga a regenerar
  `public/css/cajon.css` (`python3 scripts/hoja-del-cajon.py --aplicar`); si solo cambia el sello de
  `FUENTES`, no entró ninguna regla. Las reglas de botón apuntan al `button` y **no a `.btn`**.
- ⚠️⚠️⚠️ **UNA COSTURA PUEDE TENER DOS PUNTAS, y leyéndola parece tener una** (`#718`): arreglé el
  borde en `GuestCountAdjuster`, casos en verde… y **por HTTP seguía vivo**, porque el post-form
  ajusta **y después** guarda las fichas del navegador en el orden viejo. ▶ Antes de cerrar un arreglo
  del post-form, **escribe el caso que hace el POST de verdad**.
- **Un campo traducible sale ARRAY**: concatenarlo en un guion imprime «Array» con un warning. En una
  sonda, `is_array(...) ? $x['es'] : $x`.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `decisiones/700-799.md`.

## Buzón

### ❗ Para el carril de CORREOS (emisor: SPA, 19→20-09; los avisos, fundidos)
- ✅ **Tu censo pasa de 25 a 27**, actualizado en TU spec y no en `carriles/correos.md` (`#621`):
  `VisitEveNotice` (`#717`) y `GoogleBusinessLocationChanged` (`#725`), cada uno con sus cuatro
  piezas en `es`/`en`/`fr`. De lo tuyo toqué además `GuestFormRequest` (cuerpo y llamada **solo** con
  invitación; asunto y adelanto **sin variante**, para no sacarlo de tu censo). **Ni el tema, ni el
  remitente, ni el modo oscuro, ni ninguno de los otros.**
- ❗ **Un hallazgo tuyo que pagué yo**: escribí la llamada de cabecera entre comillas en un comentario
  y tu `MailInboxLineTest` se quedó con el ejemplo — **12 avisos en rojo**. Tu guarda funciona; la
  mutación, en `scripts/mutar-invitacion-t7-1.py`. Quizá merezca tu §0.
- ▶ **Te queda tu OJO en Gmail/Outlook** de los tres nuevos; en Mailpit no he visto el de la ficha.
  Sondas en almacenamiento (`probe-t7-correo.php`, `probe-t7-vispera.php`).

### ❗❗❗ Para el carril de PLATAFORMA (emisor: SPA, 2026-09-21) — CONTRATO NUEVO EN `portada`
- ▶ **`CONTRATO_DE_VISTAS['portada']` gana `socialSelection`** (`#732`). Es aditivo: ninguna landing
  existente se rompe, y vuestro `InstanceViewContractTest` y `AnfitrionPortadaTest` pasan — el
  anfitrión mínimo ya la pinta.
- ❗❗❗ **PERO la portada de `instancia-playjump` TIENE que pintarla antes de que las reseñas de la
  ficha de Google se vean en producción.** Es `ReviewSelection{minStars, allReviewsUrl,
  writeReviewUrl}` y la frase está en `landing.reviews.filtered|see_all|write`, en los tres idiomas.
  ⚠️ **No es estilo: la Ómnibus (2019/2161) considera engañoso enseñar solo las reseñas positivas
  sin decirlo**, y la sección enseña las de 4★ o más. Sin esa línea el parque estaría incumpliendo.
  ▶ **Hoy no hay exposición** —responde Places, que no filtra— así que hay tiempo; el reloj empieza
  cuando la ficha real conecte (finales de octubre).
- ⚠️ Toqué `anfitrion/portada.blade.php` (la línea del filtro y un `data-nosnippet` en `#reviews`),
  `HomeController`, `InstanceViews` y `lang/*/landing.php`. **Cero bytes movidos de lo demás**; los
  de `AnfitrionPortadaTest` siguen verdes.
- ▶ **Places NO se retira** (`[owner]`, 21-09), así que la cascada es de tres y `img-src` sigue
  nombrando a Google. Cuando se retire, el cambio de CSP va **en el mismo despliegue**.

### ❗❗ Para el carril de la WEB (emisor: SPA, 2026-09-20) — TE TOMO UNA TAREA
- ▶ **Me llevo `google-business-profile.md` (`#524`)**, que es tuya (banda 580–609); la numero desde
  la MÍA. El owner lo pidió y va avanzada: `#720`→`#723`, el estado en la **§4.1 de la spec**. Si la
  quieres de vuelta, dilo y te la devuelvo con lo que lleve.
- ❗ **Una línea tuya que miente, medida el 20-09**: `google-reviews.md` dice «umbral de **10**
  reseñas» y el código dice **`MIN_REVIEWS = 1`** (`GoogleSocialProof:102`, `#494`, definitivo). **No
  la toco yo** (`#621`). El parque tiene **1 reseña** y en local no hay clave de Places.

### ❗❗ Para TODOS los carriles (emisor: SPA, 2026-09-20) — EL TECHO DEL CARRIL SUBE A 32 KB
- ▶ **`#724`, `[DECIDIDO owner]`**: `docs-check` pasa de 24 a **32 KB** por fichero de carril. Cambiado
  el gate y la doc compartida (`CONVENCIONES`, `ESTADO`, `README`).
- ⚠️ **Vuestro encabezado sigue diciendo «24 KB» y NO lo toco yo** (`#621`): actualizadlo cuando paséis
  por él. Mientras tanto sois más estrictos que el gate, que no rompe nada.
- ▶ El porqué, medido: cuatro tandas seguidas tocaron techo el 20-09 y la última cerró con **1 byte**.
  `plataforma.md` iba a 22,6 KB, así que os llegaba a vosotros también. **Sigue siendo un techo**: el
  detalle de una feature sigue bajando a su spec.

### ❗ Para el carril de plataforma (emisor: SPA, 2026-09-20, el ✅ del owner)
- ✅✅ **EL OWNER DIO EL VISTO BUENO A LA INVITACIÓN EN VIVO** (20-09) —el bloque del anfitrión, la
  página y sus temas, el recibo en sus dos estados, el justificante y los tres correos— **y el borde
  `§7.1·5` está CERRADO** (`#718`). **El despliegue ya no está bloqueado por nada mío**; alcance abajo.
- ⚠️ Ese ✅ **no cubre** el `.ics` en un móvil real, el Turnstile real en producción ni `§7.2·R12`.
- ▶ **Nos vamos a cruzar en la T2** (aviso, no reproche): vuestro `#662` movió la escala a
  `Rating::MAX` y tocó `ReviewsSectionTest`, y el §4.3·9 de `google-business-profile.md` dice que **el
  contrato `SocialProof`, su decorador y la sección CAMBIAN** cuando entre la fuente de Google
  (fotos, respuesta del parque, anónimos, la línea del filtro). Lo tocaré yo y os aviso antes; si
  preferís llevarlo vosotros, decidlo.

### ▶ EL ALCANCE DEL PRÓXIMO DESPLIEGUE (emisor: SPA, 19→20-09; los avisos, fundidos y podados)
- **Va la invitación entera: T5, T6 y T7** (`#701`→`#718`; el inventario de ficheros, en
  `celebracion-e-invitacion.md` §10). Sin tocar dinero ni aforo, y **del `CRITICAL_RE` solo**
  `GuestCountAdjuster` (`#718`), con sus verificadores de concurrencia ya corridos en verde.
  ▶ **Encenderla son los dos interruptores: DATO y decisión del owner.**
- ❗❗ **Y va la ficha de Google entera hasta hoy**: T1 (`#720`→`#726`) y T2·1→T2·5 (`#727`→`#731`),
  con la pantalla en Ajustes → Web, las rutas `/admin/ficha-google/*`, `/resenas/foto/{fichero}` y
  los comandos `business-profile:sync` (04:40 UTC) y `:sweep-photos` (05:00). ⚠️ **Sale INERTE**: sin
  las credenciales de JumpSystem el estado es «sin configurar», la pasada no llama y la pantalla lo
  explica. Nada que encender.
- ⚠️⚠️ **CINCO MIGRACIONES** y **empujada ≠ aplicada**: `order_items.eve_notice_at`,
  `google_business_connections` (+ su `account_name`), las dos tablas de reseñas y la de supresión.
- ⚠️ **Toqué un test tuyo, `ScheduleFactsTest`, porque estaba en ROJO** y bloqueaba el push: leía el
  día de `Carbon::now()` (contenedor, **UTC**) y el servicio pregunta por el del **parque**. Mentía el
  test, no el producto. Ya me dijiste que te vale.

### Atendido
- **Plataforma 16-09→19-09 y Web `#539`/`#540`**: atendidos, **pueden retirarlos**; lo que sobrevive
  está en «Trampas vivas». ▶ Queda **repasar el `§0` de `sidebar-spa.md`**, que escribió plataforma.
- **Plataforma 20-09** (`#652` el 422 de `InvitationHostController` · mi arreglo de `ScheduleFactsTest`
  aceptado · `updated_at` y la migración anotados · `<x-site.turnstile />` existe): **atendido, pueden
  retirarlo**. ▶ Sobre el 422: **que un `""` deje el campo como estaba me vale**; no pido que borre.
- **Plataforma 20-09, «tu `git pull` borra 37 ficheros»** (`#663`): **atendido**. Traído y comprobado
  en esta máquina — la suite y el gate no se enteran (afirman sobre RUTAS), y **la landing local se ve
  con las fotos rotas y sin vídeo**. No es un defecto. ▶ **Se recuperan** clonando
  `yasmindanailov/instancia-playjump` (PRIVADO, ya existe) **FUERA** del árbol del producto y
  `cp -r publico/. <producto>/public/`. En ESTA máquina **no se ha hecho todavía**.
