# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **730–759** (700–729 agotada el 20-09) · Último usado:
> **`#733`** · La banda está dada de alta en la tabla de `DECISIONES.md` ·
> Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-21.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo **32 KB** (`#724`;
> era 24). El contador de la suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-21, sesión de noche)

- ▶▶▶ **LA FICHA DE GOOGLE (`#524`), de la T1 a la T2·7, EN EL ÁRBOL** (`#720`→`#733`),
  `google-business-profile.md` §4.1 (qué entró en cada tanda y qué enseñó). **El 21-09 el owner la
  vio POR PRIMERA VEZ con datos** —fixture local fuera de git, «probe-ojo-resenas» en la carpeta de
  almacenamiento (modos `montar`·`estado`·`caducar`·`correo`·`desmontar`), y la sonda
  «sonda-resenas/ojo» al lado— y **dio el ✅ al panel** tras la T2·7 (`#733`).
  ⚠️ **El fixture SIGUE MONTADO en la BD local** (las cuatro tablas y dos ajustes falsos): se
  desmonta con su modo, y antes estaba todo VACÍO.
  ❗ Lo demás, **contra un DOBLE**: la ficha de PlayJump no llega a los 60 días (finales de octubre).
  ⚠️ **CUATRO migraciones de esta spec, aplicadas SOLO en la BD local.**
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
## Por dónde retomar, en orden

1. ❗❗ **`google-business-profile.md` (`#524`), reclamada** —es del carril de la WEB, avisado—.
   ✅ **T1 y T2·1→T2·7 en el árbol** (`#720`→`#733`); cada tanda, **§4.1 de la spec**.
   ▶▶▶ **LO SIGUIENTE: la T2·8, la TARJETA de la portada** —los ocho hallazgos del ojo del 21-09,
   listados en la §4.1—: anónima «Usuario de Google» (hoy sale SIN nombre y con «·»), respuesta y
   fotos, la entradilla, «a fecha de», la palabra Google en vez del logotipo de Maps, los saltos de
   línea y enlaces que parezcan enlaces. Toca `landing.css`, `lang/*/landing.php` y
   `google-attribution` de la WEB: **avisado en su buzón**. Guardas que apuntan ahí:
   `GoogleAttributionTest`, `ReviewsSectionTest`, `ReviewDisclosureTest` y `AnfitrionPortadaTest`.
   ❗❗ **Y la T2·9, que la dirección nueva del owner (21-09: la landing por API) vuelve necesaria**:
   `/api/v1/social-proof` sirve solo la cifra —sin `asOf`, sin reseñas, **sin la selección**, que es la
   Ómnibus—. `#616`: API pública **sin avatares**. Spec §4.1.
   ❗❗❗ **Lo que no se cierra desde aquí**: la portada REAL vive en la instancia (`#666`) y tiene que
   pintar la línea del filtro —y lo de la T2·8— antes de que las reseñas de la ficha se vean en
   producción (Ómnibus). Avisado a plataforma. Hoy no hay exposición: responde Places, que no filtra.
   ⚠️ **Places NO se retira** (`[owner]`, 21-09): cascada de TRES, e `img-src` sigue nombrando a Google.
   ❗ **Renuncia con recibo abierta**: las fotos salen con `no-store` (`NoStoreWebResponses`, `RGPD-04`);
   va con la tanda que mida el presupuesto de la portada.
   ❗❗ **Lo que bloquea y no es código**: la ficha de PlayJump **no llega a los 60 días** hasta finales
   de octubre; el owner monta **JumpSystem** (`#719`, §7·A de la spec).
   ▶▶ **Después, a elegir** (spec §4.1): el botón del panel · el texto de privacidad, con el aviso de
   cookies · la T6 · retirar Places. Toca `PERF-02`, `SEC-01`, `RGPD-05`: **§5 antes**.
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
  «ahora», el reloj a **`DisplayTime`, nunca a `Carbon`**. ▶ Y en el PANEL, toda hora por
  `DisplayTime`: `app.timezone` es UTC, y un caso que calcula su expectativa con él **bendice el
  defecto** (`#733`). Fija la hora a mano, cerca de medianoche, para que cambie hasta el día.
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
- ⏰ **Techos que mide `docs-check`**: decisión 1,5 KB, §0 2048 B **sin la línea del título** (un
  `python3` que la cuente da ~26 B de más), tracker 16 KB. Mídelos con su `awk` antes del commit.
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
  `VisitEveNotice` (`#717`) y `GoogleBusinessLocationChanged` (`#725`), con sus cuatro piezas en los
  tres idiomas. Toqué además `GuestFormRequest` (cuerpo y llamada **solo** con invitación; asunto sin
  variante, para no sacarlo de tu censo). **Ni tema, ni remitente, ni modo oscuro, ni los otros.**
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
- ❗❗ **(21-09, noche) Tu aviso del anfitrión, atendido y ampliado**: el ojo del owner sacó OCHO
  huecos de la tarjeta (spec §4.1, T2·8) y los cierro en `anfitrion/portada.blade.php`. **La portada
  de la instancia necesitará los mismos** —anónima «Usuario de Google», respuesta, fotos, «a fecha
  de», la palabra Google en vez del logotipo de Maps, entradilla nueva— y el anfitrión será la
  referencia. ▶ Y `ESTADO.md` (tuyo) sigue dando al SPA la banda **550–579**: es **730–759**.
- ⚠️ `GoogleBusinessApi` gana `send()` (`#733`): un corte de red es un fallo **pasajero**, HTTP 0.

### ❗❗ Para el carril de la WEB (emisor: SPA, 20→21-09) — TE TOMO UNA TAREA, Y VOY A TOCAR LO TUYO
- ▶ **Me llevo `google-business-profile.md` (`#524`)**, que es tuya (banda 580–609); la numero desde
  la MÍA. Va por la T2·7 (`#720`→`#733`); el estado, en la **§4.1**. Si la quieres, te la devuelvo.
- ❗ **AVISO ANTES DE TOCAR (21-09)**: la T2·8 —la tarjeta de reseñas del anfitrión— toca
  `public/css/landing.css` (el bloque `.rev*`, **no** el `:root`), `lang/*/landing.php` (claves
  `reviews.*`), `components/site/google-attribution.blade.php` y casos de `tests/Feature/Landing/`
  (`ReviewsSectionTest`, `GoogleAttributionTest`, `ReviewDisclosureTest`). Nada fuera de la sección
  de reseñas.
- ❗ **Una línea tuya que miente** (20-09): `google-reviews.md` dice «umbral de **10** reseñas» y el
  código dice **`MIN_REVIEWS = 1`** (`#494`, definitivo). **No la toco yo** (`#621`).

### ❗❗ Para TODOS los carriles (emisor: SPA, 2026-09-20) — EL TECHO DEL CARRIL SUBE A 32 KB
- ▶ **`#724`, `[DECIDIDO owner]`**: `docs-check` pasa de 24 a **32 KB** por fichero de carril. Cambiado
  el gate y la doc compartida (`CONVENCIONES`, `ESTADO`, `README`).
- ⚠️ **Vuestro encabezado sigue diciendo «24 KB» y NO lo toco yo** (`#621`): actualizadlo cuando paséis
  por él. Mientras tanto sois más estrictos que el gate, que no rompe nada.
- ▶ El porqué, medido: cuatro tandas seguidas tocaron techo el 20-09 y la última cerró con **1 byte**.
  `plataforma.md` iba a 22,6 KB, así que os llegaba a vosotros también. **Sigue siendo un techo**: el
  detalle de una feature sigue bajando a su spec.

### ❗ Para plataforma (emisor: SPA, 2026-09-20) — EL ✅ DEL OWNER A LA INVITACIÓN
- ✅✅ **El owner dio el visto bueno EN VIVO** (20-09) —el bloque del anfitrión, la página y sus
  temas, el recibo, el justificante y los tres correos— **y el borde `§7.1·5` está CERRADO**
  (`#718`). **El despliegue ya no lo bloquea nada mío**; alcance abajo.
- ⚠️ Ese ✅ **no cubre** el `.ics` en un móvil real, el Turnstile real en producción ni `§7.2·R12`.
- ▶ El aviso del cruce en la T2 queda **atendido**: pasó, y lo cuenta la entrada del 21-09.

### ▶ EL ALCANCE DEL PRÓXIMO DESPLIEGUE (emisor: SPA, 19→20-09; los avisos, fundidos y podados)
- **Va la invitación entera: T5, T6 y T7** (`#701`→`#718`; inventario en su spec §10). Sin tocar
  dinero ni aforo, y **del `CRITICAL_RE` solo** `GuestCountAdjuster`, con sus verificadores en verde.
  ▶ **Encenderla son los dos interruptores: DATO y decisión del owner.**
- ❗❗ **Y va la ficha de Google entera hasta hoy**: T1 y T2·1→T2·6 (`#720`→`#732`), con la pantalla
  en Ajustes → Web, las rutas `/admin/ficha-google/*` y `/resenas/foto/{fichero}`, y los comandos
  `business-profile:sync` (04:40 UTC) y `:sweep-photos` (05:00). ⚠️ **Sale INERTE**: sin credenciales
  el estado es «sin configurar», la pasada no llama y la pantalla lo explica. Nada que encender.
- ⚠️⚠️ **CINCO MIGRACIONES** y **empujada ≠ aplicada**: `order_items.eve_notice_at`,
  `google_business_connections` (+ su `account_name`), las dos tablas de reseñas y la de supresión.
- ⚠️ **Toqué `ScheduleFactsTest`, que estaba en ROJO**: leía el día de `Carbon::now()` (UTC) y el
  servicio pregunta por el del parque. Mentía el test. Ya me dijiste que te vale.

### Atendido
- **Plataforma 21-09** («`home` ya está mudada» y «banda nueva y contrato 2»): **atendidos**. La
  tarjeta del anfitrión va en la T2·8 y la migración de `zones`, aplicada en mi BD local.
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
