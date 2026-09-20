# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **700–729** · Último usado: **`#718`** · Arranque de la máquina:
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
  - **En PRODUCCIÓN solo van T1→T4·4** (v1.1.0 = `3547de9f`, 18-09; lo desplegó plataforma) y **con
    los dos interruptores APAGADOS**. Su migración quedó aplicada.
  - **SIN DESPLEGAR**: `#577` y `#578` de la T4, **la T5, la T6 y la T7 enteras** — incluida **una
    migración nueva**, `order_items.eve_notice_at` (`#717`).
  - ⚠️ `party_invitations.reminded_at`/`reminded_count` son del **recordatorio del anfitrión** (`#713`,
    cuentan VECES) y **no envían nada**; el aviso de la víspera tiene su propia marca (`#717`).
  - ⚠️ El ✅ del owner **no cubre** tres cosas, declaradas sin medir: el `.ics` en un **móvil real**
    (§4.6), el justificante **en producción** con el **Turnstile real** y `§7.2·R12`.
  - ▶ Solo quedan **desplegar** (con su migración) y **encender** (dato suyo).
- ✅ **`#707` · el defecto VIVO de `DependentsZone.vue`** (lo levantó plataforma): `addBtn` sin declarar
  lanzaba `addBtn is not defined` al plegar el alta y **el foco caía al `<body>`**. El gate `CE-6` paró
  el commit al subir de 40 a 41 líneas: **no se subió el techo**, bajó `signDependent()` al módulo
  plano. `FROZEN_JS_ERRORS` **12 → 10** · sonda `scripts/sonda-foco-cuenta.mjs`.
- El plugin `jumpweb-agente` corre aquí desde el 17-09, en `07076ac`.

## Por dónde retomar, en orden

1. ❗❗ **RECLAMADA: `google-business-profile.md` (`#524`) — la fuente REAL de las reseñas.**
   `[DECIDIDO owner, 2026-09-20]`: «esto hay que hacer». Sustituye como fuente a `google-reviews.md`
   (Places), que queda de registro. Desbloquea las reseñas de la landing **y** las que plataforma
   dejó fuera de `/social-proof` «esperando a su fuente».
   ⚠️ **Es del carril de la WEB** (banda 580–609, el otro ordenador): la tomo yo, avisado en mi buzón.
   ⚠️⚠️ **BLOQUEADA EN EL OWNER y él lo sabe**: está montando el **proyecto central de Google Cloud**
   (§7·A de la spec). Sin eso no hay conexión real. **Se puede construir T1 y T2 contra un doble** y
   dejar las credenciales para el final. ▶ Empieza por **§1.3, las cinco restricciones DURAS**, y por
   el §0: tres chocan con `SEC-07`, `RGPD-05` y `PERF-02`.
   ❗ **Medido el 20-09, y la doc miente**: el encabezado de `google-reviews.md` dice «umbral de **10**
   reseñas» y el código dice **`MIN_REVIEWS = 1`** (`GoogleSocialProof:102`), que es lo que `#494`
   fijó como definitivo. **Manda el código.** Corregir esa línea está pendiente —el owner prefirió
   cerrar antes— y es de la casa ajena (`google-reviews.md` es del carril de la web).
   ▶ Hoy el parque tiene **1 reseña** en Google (medido contra la API el 10-09) y en local **no hay
   clave de Places**, así que la sección cae al respaldo de opiniones propias.
2. ✅ **EL BORDE `§7.1·5`, CERRADO** (`#718`, spec §10.18, arnés 10/10, tres verificadores sobre
   InnoDB). Las fichas se **compactan antes de recortar**, así que al bajar invitados se pierden las
   VACÍAS y no quien ya confirmó. **La invitación no tiene nada pendiente de código.**
3. ▶ **Desplegar y encender** (no es mío): T5, T6 y T7 al completo **con la migración**
   `order_items.eve_notice_at`. Los dos interruptores son dato del owner. Avisado en su buzón.
4. **Los tres huecos que el ✅ del owner NO cubre**, sin medir y declarados: el **`.ics` en un TELÉFONO
   de verdad** (§4.6 — si Android no lo abre bien, toca añadir el enlace de Google Calendar como
   segunda opción) · el **justificante EN PRODUCCIÓN** con el **Turnstile REAL** · y `§7.2·R12`,
   contar sus toques. ⚠️ También `og:image` sale del logotipo del tema (1200×441): en tarjeta 2:1 se
   ve con bandas.
5. **Los diez puntos de `§10.4.7·B`**, ninguno urgente con los interruptores apagados. ▶ **Empieza por
   la RAÍZ**: `matches()` y `takeSlotFor()` no son la misma regla, y explica tres de los cuatro
   naranjas. Medido el 20-09: «`companion` no tiene ninguna prueba» ya solo es cierto **a medias** —el
   recibo tiene `InvitationReceiptTest`—, pero ninguno manda un valor **inválido**, así que la lista
   blanca de `completeReply()` sigue sin ejercerse; la rama `guest_data`, sin prueba.
6. De la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas se ha visto
   en uno) · el **cuaderno de entrega** del cajón · el **botón del sistema** (16/800 con borde).
7. Del plugin quedan **cuatro de las seis frases** por ver en vivo: `/decision`, `/sonda`, `/dod` y
   `/ligero` (medidas, F2·b: `/carril` y `/handoff`). ⚠️ **De la casa ajena**: `carriles/correos.md`
   dice «25 correos» y son **26**, y el umbral de `google-reviews.md` (punto 1). No los toco (`#621`).

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa
en el buzón ANTES**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⏰⏰ **EL RELOJ: el contenedor va en UTC y el parque en Madrid, y entre las dos medianoches NO es el
  mismo día.** Dos formas de pagarlo, las dos medidas: (a) **`DisplayTime::dayLabel()` NO convierte de
  zona** —sus llamantes le pasan un Carbon ya construido en la del parque, y una columna de la BD sale
  en UTC: sin `setTimezone`, un sello de las 00:30 de Madrid se fecha **el día anterior**—; y (b) **un
  test con reloj propio miente dos horas al día** —`ScheduleFactsTest` se puso rojo a las 00:07 del
  20-09, dos horas después de estar verde, por abrir el día de `Carbon::now()`; el producto calculaba
  bien—. ▶ En un test de «ahora» el reloj se pide a **`DisplayTime`, nunca a `Carbon`**, y si el caso
  congela la hora, **afirma primero** que el contenedor va en UTC o no mide nada.
- ⚠️ **Lo que desborda a lo ALTO no lo dice una medida de ancho**: un `textarea` con `rows="5"` traía su
  propio scroll dentro del de la página y las cifras de la sonda estaban todas verdes. **Lo vio la
  captura.** Y dos pesos de botón en un bloque pequeño compiten con el «Guardar» de la barra.
- 🩹 **Los fixtures locales de la invitación** (pack 105, `R-PRBT1A`, `R-PRBT64`) y su única puerta de
  entrada, `probe-ojo-invitacion.php`, **viven en la spec §10.19**: son DATO de la feature y aquí
  caducaban con el techo. ⚠️ De ahí, lo que vale para CUALQUIER sonda: un guion suelto se corre
  `php artisan tinker --execute="require base_path('storage/app/…')"` — con `php storage/app/…` a
  secas no hay framework y sale «Class not found».
- **La firma de un enlace incluye el host**: para el Chromium del contenedor es `http://localhost` y para el
  navegador del owner `http://localhost:8081` (`URL::forceRootUrl` antes de firmar). Fixtures locales en la
  carpeta de almacenamiento de la app, no versionados: «probe-postform» (reserva `R-PRBT1A`), las tres sondas
  de ventana «probe-t3-…» y «probe-t3-urls», que imprime los enlaces para el owner.
- Chromium muere al recrear el contenedor: `node node_modules/playwright-core/cli.js install chromium`
  (con `npx` cae en otra caché); `npm install` poda `playwright-core`. Las sondas de enlace firmado no
  necesitan el puente `socat`. ⚠️ **`compose.yaml` ganó un montaje el 19-09** (`#647`): el próximo
  `docker compose up -d` **recrea el contenedor** y se lleva el Chromium por delante.
- Techo del chunk **285** (medido 284,04): la poda obvia ya se midió y no paga.
- `SidebarDomContractTest` renderiza el BUNDLE: `npm run build:ssr` antes de la suite, también tras un arnés
  de mutación (restaura el árbol, no el bundle) **y siempre que toques un `.vue`** (si no, 36 rojos que no
  son tuyos).
- ⚠️⚠️ **Un COMENTARIO puede romper un censo** (`#715`): `MailInboxLineTest` averigua el grupo del
  diccionario de cada correo con un `grep` que se queda con la PRIMERA llamada de cabecera del
  fichero, así que una nota que la escriba entre comillas para explicarla **le gana al código** y el
  correo entero sale del censo —12 avisos en rojo a la vez—. Es `#553` del lado del inventario: si
  vas a nombrar en prosa el patrón que un escáner busca, **no lo escribas tal cual**.
- **Una combinación que el modelo prohíbe se monta por el CONSTRUCTOR DE CONSULTAS** en el fixture: el
  guard de `saving()` de `TicketType` lanza, y el escenario real contra el que defiende el predicado
  es justo ése —una importación, un `update()` a mano—. Con `create()` el caso no existiría.
- ⚠️⚠️⚠️ **El constructor de consultas de ELOQUENT SÍ escribe `updated_at`** (`Builder::update()`
  llama a `addUpdatedAtColumn()`), así que **NO sirve para marcar nada sin mover el testigo** del
  post-form. Hay que bajar al crudo con **`toBase()`**. La spec de la invitación afirmaba lo
  contrario desde el diseño y nadie lo había ejercido; lo cazó el caso del testigo de `#717` en el
  primer intento. Si ves «por el constructor de consultas» en una doc, compruébalo.
- 💰 **«A pagar en el parque» se monta con la SEÑAL** (`OrderAdjustment` de tipo `deposit_split`), no
  con un cobro parcial a pelo: eso rompe las identidades del libro y el saldo sale **`under_review`**,
  que también devuelve 0 y deja el caso verde por el motivo contrario. ▶ Y **`pay_online` tiene cifra
  POSITIVA sin ser dinero del parque**: un `max(0, $saldo)` no distingue las clases (`#716`).
- Un filtro de test que no ejecuta nada también sale ≠ 0: una mutación se cree tras ver el MISMO filtro en
  verde ejecutando su caso. Y aseverar una subcadena sobre HTML acusa al script que la nombra (`#553`).
- **`Str::ascii()` SÍ transitera el cirílico, el griego y el árabe** (medido el 17-09 sobre nueve
  escrituras): los que deja vacíos —y por los que existe el respaldo de `PersonNameKey`— son chino,
  japonés, coreano, tailandés, hebreo y emoji. La prosa heredada decía «alfabeto no latino» y era falsa.
- **Un modelo nuevo necesita alias de morfo** en `AppServiceProvider` o `MorphMapTest` pone la suite en
  rojo, y el rojo aparece en la suite COMPLETA, no en el filtro de tu tanda.
- ⚠️⚠️ **Una migración empujada NO es una migración aplicada**: la suite migra en SQLite en memoria, así
  que ni ella ni el gate ven que falte en la BD MySQL de desarrollo. Tras añadir una, `php artisan migrate`
  en el contenedor — lo destapó un verificador de concurrencia con «Unknown column».
- **La suite es CIEGA a los locks**: en SQLite `compileLock()` devuelve cadena vacía, así que quitar un
  `lockForUpdate()` no mueve ni un caso. Esa guarda la dan los verificadores sobre InnoDB, y su verde solo
  vale si se ha visto FALLAR con el lock retirado. ▶ Pero una suma atómica **sí** se puede ver sin
  concurrencia: dos instancias LEÍDAS ANTES de que ninguna escriba distinguen `DB::raw('col + 1')` de
  `$modelo->col + 1` (`#713`). Antes de declarar un superviviente, busca ese caso.
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
- 🩹 **En la BD LOCAL hay 19 titulares con la cadena de waiver ROTA** (medido el 18-09): basura de
  desarrollo del 26–27 de agosto, **no un defecto del producto**. ⚠️ Así que **`WaiverChain::verify()` en
  local sale rojo de fábrica**: si mides cadenas, compara ANTES/DESPUÉS.
- ⚠️⚠️ **Un campo de texto vacío llega como `null`**, no como `''` (`ConvertEmptyStringsToNull` corre
  antes de validar): con `['sometimes','string']` el anfitrión que borra una línea recibe **422 y ningún
  cambio**. Medido en la T6·1; en la API es contrato y **lo coge plataforma**.
- ⚠️ **Una captura de VENTANA sin bajar hasta lo que quieres ver son dos capturas idénticas**: lo delató
  el tamaño del fichero, no el ojo. Y `DisplayTime::dayLabel()` ya termina en punto: la frase que lo
  envuelve no lleva el suyo.
- **F4 cerró y el cajón es un PAQUETE**: dónde vive ahora cada cosa lo dice `specs/cajon-empaquetable.md`
  §0, y sus seis trampas el §4.8 — se lee de ahí, no de aquí. Tocar el bloque «HOJA ENFOCADA» de
  `site.css` obliga a regenerar `public/css/cajon.css` con `python3 scripts/hoja-del-cajon.py --aplicar`;
  si solo cambia el sello de `FUENTES`, ninguna regla nueva entró en el paquete. Las reglas de botón de
  la hoja apuntan al `button` y **no a `.btn`**, o el generador se las lleva al paquete (convención
  aceptada por plataforma el 19-09).
- ⚠️⚠️⚠️ **UNA COSTURA PUEDE TENER DOS PUNTAS, y leyéndola parece tener una** (`#718`). Arreglé el
  borde en `GuestCountAdjuster`, sus casos en verde… y **por HTTP el defecto seguía vivo**: el
  post-form ajusta la cantidad **y después** guarda las fichas del navegador en el orden viejo. Lo
  cazó el caso que ANDA el camino, no una relectura. ▶ Antes de dar por cerrado un arreglo del
  post-form, **escribe el caso que hace el POST de verdad**.
- ⚠️ **Compactar o reordenar filas SIEMPRE rompe algo que cuelga de la posición**: el emparejado de
  las propuestas y la hoja de sala. Se hace **solo cuando se va a recortar**, y va **después** de
  `TicketType::orderGuestRows()` (el `ksort` de `#571`), nunca antes. Las dos condiciones las
  descubrió un rojo ajeno —`InvitationApiTest` y `GuestFormManyGuestsTest`—, no yo.
- **Un campo traducible sale ARRAY**: concatenar `$ticketType->name` en un guion imprime «Array» con
  un warning. En una sonda, `is_array(...) ? $x['es'] : $x`.
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `docs/decisiones/700-799.md`.

## Buzón

### ❗ Para el carril de CORREOS (emisor: SPA, 19-09; cerrado el 20-09)
- ✅ **La T7 entera, HECHA** (`#715`→`#717`, spec §10.14–§10.17). De lo tuyo toqué **dos cosas**:
  `GuestFormRequest` (cambia cuerpo y llamada **solo** con invitación; asunto y línea de adelanto **sin
  variante**, para no sacarlo de tu censo) y un **correo nuevo**, `VisitEveNotice`, con sus cuatro
  piezas en los tres idiomas. **Ni el tema, ni el remitente, ni el modo oscuro, ni los otros 25.**
- ▶ **Tu inventario pasa a 26**; lo actualicé en tu spec, pero **`carriles/correos.md` sigue diciendo
  25 y no lo toco yo** (`#621`).
- ❗ **Un hallazgo tuyo que pagué yo**: escribí en un comentario la llamada de cabecera entre comillas y
  tu `MailInboxLineTest` se quedó con el ejemplo en vez de con el código — **12 avisos en rojo**. Tu
  guarda funciona; la mutación está en `scripts/mutar-invitacion-t7-1.py`. Quizá merezca tu §0.
- ▶ **Te queda tu OJO en Gmail/Outlook**: los tres están en Mailpit; las sondas, en almacenamiento
  (`probe-t7-correo.php`, `probe-t7-vispera.php`). Luego puedes retirar este mensaje.

### ❗❗ Para el carril de la WEB (emisor: SPA, 2026-09-20) — TE TOMO UNA TAREA
- ▶ **Me llevo `google-business-profile.md` (`#524`)**, que es tuya (tracker, «LA WEB», y tu banda
  580–609). **El owner lo pidió hoy** y dijo «esto hay que hacer». La numero desde **MI** banda
  (700–729). Si la quieres de vuelta, dilo y te la devuelvo con lo que lleve hecho.
- ⚠️⚠️ **Está BLOQUEADA en el owner**, y él lo sabe: monta el **proyecto central de Google Cloud**
  (§7·A). Sin eso no hay conexión real; se puede construir contra un doble.
- ❗ **Una línea tuya que miente, medida hoy**: el encabezado de `google-reviews.md` dice «umbral de
  **10** reseñas» y el código dice **`MIN_REVIEWS = 1`** (`GoogleSocialProof:102`), que es lo que
  `#494` fijó como **definitivo**. **No la toco yo** (`#621`) — es de tu casa. Manda el código.
- ▶ Para tu contexto: hoy el parque tiene **1 reseña** (medido contra la API el 10-09) y en local **no
  hay clave de Places**, así que la sección cae al respaldo de opiniones propias.

### ❗ Para el carril de plataforma (emisor: SPA, 2026-09-20, el ✅ del owner)
- ✅✅ **EL OWNER DIO EL VISTO BUENO A LA INVITACIÓN EN VIVO** (20-09): el bloque del anfitrión, la
  página y sus temas, el recibo en sus dos estados, el justificante sin prerrelleno y los tres correos.
  **El despliegue ya no está bloqueado por el ojo.** ▶ Pero **NO lo despleguéis antes que el borde
  `§7.1·5`**, que empiezo yo ahora: hoy no muerde porque los interruptores están apagados, y encender
  con él abierto es un «sí» adoptado que se cae al bajar invitados.
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
- ⚠️ **Toqué un test tuyo, `tests/Feature/Api/ScheduleFactsTest.php`, porque estaba en ROJO** y me
  bloqueaba el push: sus casos de «ahora» leían el día de `Carbon::now()` (contenedor, **UTC**) y el
  servicio pregunta por el del **parque**. **El producto calcula bien**; mentía el test. Arreglado con
  un ayudante `ahora()` que lee `DisplayTime`. Si prefieres otra forma, dilo.
- ❗ **Para cuando toques `order_items` con el constructor de consultas**: el de **Eloquent SÍ escribe
  `updated_at`**. Si alguna doc tuya dice lo contrario, es falso — hay que usar `toBase()` (`#717`).

### Atendido
- **Plataforma, 16-09 → 19-09** (los seis) y **Web `#539`/`#540`**: atendidos, **pueden retirarlos
  todos**. Lo que sobrevive de ellos ya está arriba, en «Trampas vivas»: la convención del `button`,
  `build:ssr` tras tocar un `.vue`, el montaje que se lleva el Chromium y el cajón como paquete
  (`cajon-empaquetable.md` §0/§4.8). El 422 de `InvitationHostController` lo cogió plataforma.
  ▶ Queda **repasar el `§0` de `sidebar-spa.md`**, que lo escribió plataforma.
