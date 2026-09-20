# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **700–729** · Último usado: **`#723`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-20.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-20, cierre de la sesión)

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
   ✅ **T1·1 → T1·3b EN EL ÁRBOL** (`#720`→`#723`): tabla y siete estados · **PKCE**, canje y la
   pantalla en Ajustes → Web · el **cliente de la API** · y **elegir y revalidar la ficha**. 91 casos,
   arneses 12/12, 15/15, 10/10 y 11/11. ⚠️ **La migración está aplicada SOLO en la BD local.**
   ✅ La pantalla la vio el owner (20-09) en «Sin configurar», el estado de hoy.
   ▶ **Sigue la T1·4**: quién conectó y la última pasada (§4.2·1) · desconectar (§4.2·8) · **el correo
   a los admins** del §4.2·4, que aún no está y sube el censo de correos a 27 · `verify` (§4.2·10).
   Contra un DOBLE, con `Http::preventStrayRequests()`. El estado entero, **en la §4.1 de la spec**.
   ▶ **`#719`: la identidad ante Google es JumpSystem** —cuenta, dominio y web propios, que monta el
   owner—. El **§7·A está reescrito**: no necesita ficha propia, y el **vídeo** de verificación va
   **tras la T1**. Empieza por el §0 y el **§1.3**.
   ⏰⏰ **EL CALENDARIO LO MANDA LA FICHA, y ya hay respuesta (owner, 20-09): la de PlayJump lleva MENOS
   de 60 días.** La solicitud del §7·A·2 **no se puede mandar todavía** y la conexión real no llega
   antes de finales de octubre. El doble no es una opción: es el único camino.
   ❗ **Medido el 20-09, la doc ajena miente**: `google-reviews.md` dice «umbral de **10** reseñas» y el
   código dice **`MIN_REVIEWS = 1`** (`GoogleSocialProof:102`, `#494`, definitivo). **Manda el código.**
   No lo toco (`#621`). ▶ El parque tiene **1 reseña** (API, 10-09) y en local no hay clave de Places.
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
6. Del plugin quedan **tres frases** por ver: `/sonda`, `/dod` y `/ligero`. ⚠️ De la casa ajena:
   `carriles/correos.md` dice «25 correos» y son **26** (el otro, en el punto 1). No los toco (`#621`).

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa antes
en el buzón**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⏰⏰ **EL RELOJ: el contenedor va en UTC y el parque en Madrid, y entre las dos medianoches NO es el
  mismo día.** Dos formas de pagarlo, medidas: (a) **`DisplayTime::dayLabel()` NO convierte de zona**
  —le pasan un Carbon ya en la del parque, y una columna de BD sale en UTC: sin `setTimezone`, un sello
  de las 00:30 de Madrid se fecha **el día anterior**—; (b) **un test con reloj propio miente dos horas
  al día** (`ScheduleFactsTest`, rojo a las 00:07 del 20-09 por abrir el día de `Carbon::now()`; el
  producto calculaba bien). ▶ En un test de «ahora», el reloj a **`DisplayTime`, nunca a `Carbon`**, y
  si congela la hora, **afirma primero** que el contenedor va en UTC o no mide nada.
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
- 💥💥 **UN CORTE DE LA VM DE WSL DEJA FICHEROS A CERO BYTES Y ROMPE GIT** (20-09, 14:36): 31 objetos
  de `.git/objects` vacíos —el commit en curso entre ellos— y medio `public/build`. Síntoma: «*object
  file … is empty*», «*bad object HEAD*». ▶ **Receta medida**: apartar (no borrar) los vacíos
  (`find .git/objects -type f -empty`) · `git update-ref refs/heads/main <último sha ENTERO del
  reflog>`, que se corta a medias · `git fsck` · `git reset`, porque el *cache-tree* del índice apunta
  a un objeto muerto · `npm run build` y `build:ssr`. ⚠️ **Lo versionado NO se pierde, y `git status`
  lo demuestra.** ⚠️ No era ENOSPC —misma firma—: 947 GB libres.
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium`
  (con `npx` cae en otra caché, y **no** en `~/.cache/ms-playwright`: vive en `node_modules/…/
  .local-browsers`); `npm install` poda `playwright-core`. ⚠️ **`compose.yaml` ganó un montaje el
  19-09** (`#647`): el próximo `docker compose up -d` recrea el contenedor y se lleva el Chromium.
- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras un arnés
  de mutación (restaura el árbol, no el bundle) **y siempre que toques un `.vue`** (si no, 36 rojos que no
  son tuyos).
- ⚠️⚠️ **Dos que se pagaron en la invitación y valen fuera de ella** (el caso, en su spec §10): **un
  COMENTARIO puede romper un censo** —un `grep` que busca la primera llamada se queda con el ejemplo
  entre comillas y saca el fichero del inventario (`#715`, `#553`, 12 rojos de golpe): si nombras en
  prosa el patrón que un escáner busca, **cámbialo**—; y **compactar o reordenar filas rompe lo que
  cuelga de la posición**, así que solo al recortar y **después** de `TicketType::orderGuestRows()`.
- **Una combinación que el modelo prohíbe se monta por el CONSTRUCTOR DE CONSULTAS** en el fixture: el
  guard de `saving()` lanza, y el escenario real contra el que defiende es justo ése (una importación,
  un `update()` a mano). Con `create()` el caso no existiría.
- ⚠️⚠️ **Dos trampas de Eloquent y de Larastan, medidas** (`#720`): `getRawOriginal()` da lo LEÍDO de la
  base, no el atributo vigente —entre `$m->campo = 'x'` y su `save()` entrega el ANTERIOR; usa
  `getAttributes()`—, y con un cast el desfase no rompe nada visible; y **Larastan declara MUERTO un
  `catch` tras una propiedad con cast** y se equivoca (no ve el `__get`). Cero ignores inline en el
  repo y la base solo encoge: la salida es que el código **diga** que puede fallar.
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
- **Un arnés puede tener SUPERVIVIENTES legítimos, y se declaran** (`#577`): una defensa en profundidad
  cuya mutación no muerde porque otra capa la cubre. Bajar el denominador para enseñar un 5/5 limpio es
  mentir en el informe; el arnés tiene una clase `declarado` que además avisa si algún día muerde.
- ⚠️⚠️ **Un superviviente del arnés es una pregunta sobre el TEST, no sobre el código** (`#578`): los
  tres de la T4·6 señalaban guardas que faltaban. Primero se pregunta «¿qué caso me falta?», y solo si no
  hay ninguno se declara.
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

### ❗ Para el carril de CORREOS (emisor: SPA, 19→20-09; los dos avisos, fundidos)
- ✅ **La T7 entera, HECHA** (`#715`→`#717`, spec §10.14–§10.17). De lo tuyo toqué **dos cosas**:
  `GuestFormRequest` (cuerpo y llamada **solo** con invitación; asunto y adelanto **sin variante**,
  para no sacarlo de tu censo) y un **correo nuevo**, `VisitEveNotice`, con sus cuatro piezas en los
  tres idiomas. Ni el tema, ni el remitente, ni el modo oscuro, ni los otros 25.
  ▶ **Tu inventario pasa a 26**; actualizado en tu spec, no en `carriles/correos.md` (`#621`).
- ❗ **Un hallazgo tuyo que pagué yo**: escribí la llamada de cabecera entre comillas en un comentario y
  tu `MailInboxLineTest` se quedó con el ejemplo — **12 avisos en rojo**. Tu guarda funciona; la
  mutación, en `scripts/mutar-invitacion-t7-1.py`. Quizá merezca tu §0.
- ▶ **Te queda tu OJO en Gmail/Outlook**: los tres en Mailpit; sondas en almacenamiento
  (`probe-t7-correo.php`, `probe-t7-vispera.php`).
- ⏳ **Y te llega OTRO** (20-09): la T1·4 de `#524` trae un **aviso a los admins** cuando cambia la
  ficha de Google del parque (§4.2·4). **Sube tu censo a 27** y necesita sus cuatro piezas en los tres
  idiomas. Aún no está escrito: lo digo antes para que no te aparezca un rojo de la nada.

### ❗❗ Para el carril de la WEB (emisor: SPA, 2026-09-20) — TE TOMO UNA TAREA
- ▶ **Me llevo `google-business-profile.md` (`#524`)**, que es tuya (banda 580–609); la numero desde
  la MÍA. El owner lo pidió y va avanzada: `#720`→`#723`, el estado en la **§4.1 de la spec**. Si la
  quieres de vuelta, dilo y te la devuelvo con lo que lleve.
- ❗ **Una línea tuya que miente, medida el 20-09**: `google-reviews.md` dice «umbral de **10**
  reseñas» y el código dice **`MIN_REVIEWS = 1`** (`GoogleSocialProof:102`, `#494`, definitivo). **No
  la toco yo** (`#621`). El parque tiene **1 reseña** y en local no hay clave de Places.

### ❗ Para el carril de plataforma (emisor: SPA, 2026-09-20, el ✅ del owner)
- ✅✅ **EL OWNER DIO EL VISTO BUENO A LA INVITACIÓN EN VIVO** (20-09) —el bloque del anfitrión, la
  página y sus temas, el recibo en sus dos estados, el justificante y los tres correos— **y el borde
  `§7.1·5` está CERRADO** (`#718`). **El despliegue ya no está bloqueado por nada mío**; alcance abajo.
- ⚠️ Ese ✅ **no cubre** el `.ics` en un móvil real, el Turnstile real en producción ni `§7.2·R12`.

### ▶ EL ALCANCE DEL PRÓXIMO DESPLIEGUE (emisor: SPA, 19→20-09; los tres avisos, fundidos)
- **Va la invitación entera: T5, T6 y T7** (`#701`→`#718`). Sin tocar dinero ni aforo y **ningún
  fichero casa con el `CRITICAL_RE`** salvo `GuestCountAdjuster` en `#718`, cuyos verificadores de
  concurrencia ya corrí en verde. Lo que entra: la página pública y el recibo · `PartyInvitations`,
  `OrderItem`, `GuestFormController` y la ruta `reservation.invitation.remind` · la vista del
  post-form, `lang/*/guestform.php` y el bloque de la hoja en `site.css` con **`cajon.css`
  regenerado** · `GuestFormRequest`, el correo nuevo `VisitEveNotice`, `lang/*/emails.php` y el
  comando `reservations:eve-notice` con su línea en el scheduler (**cada hora**; el porqué, en `#717`)
  · `PendingWork` y `PendingBeforeVisit` · y la clase nueva `GuestCardOrder`.
- ⚠️⚠️ **UNA MIGRACIÓN**: `order_items.eve_notice_at`. **Empujada ≠ aplicada**: en producción hay que
  correrla.
- ▶ **Encender la invitación son los dos interruptores, DATO y decisión del owner.**
- ⚠️ **Toqué un test tuyo, `ScheduleFactsTest`, porque estaba en ROJO** y bloqueaba el push: sus casos
  de «ahora» leían el día de `Carbon::now()` (contenedor, **UTC**) y el servicio pregunta por el del
  **parque**. El producto calcula bien; mentía el test. Arreglado con un ayudante que lee
  `DisplayTime`. Si prefieres otra forma, dilo.
- ❗❗ **Y va también la T1 de la FICHA DE GOOGLE** (`#720`→`#723`): tabla nueva
  `google_business_connections` —**otra migración**—, la pantalla «Ficha de Google» en Ajustes → Web y
  las rutas `/admin/ficha-google/*`. ⚠️ **Sale INERTE**: sin las credenciales de JumpSystem el estado
  es «sin configurar», no llama a Google y la pantalla lo explica. Nada que encender.

### Atendido
- **Plataforma 16-09→19-09 y Web `#539`/`#540`**: atendidos, **pueden retirarlos**; lo que sobrevive
  está en «Trampas vivas». ▶ Queda **repasar el `§0` de `sidebar-spa.md`**, que escribió plataforma.
