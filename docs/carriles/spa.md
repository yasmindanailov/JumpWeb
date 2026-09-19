# Carril · Diseño del SPA (el cajón)

> Máquina: **el OTRO ordenador** · Banda: **700–729** · Último usado: **`#717`** · Arranque de la máquina:
> `docs/CARRIL-SPA.md` (su §7 antes que el resto) · Specs: `sidebar-spa.md` §0 · `celebracion-e-invitacion.md`
> §0 · `rediseno-desde-canvas.md` §5 (Fase 4) · Actualizado: 2026-09-20.
> Este fichero lo escribe SOLO el agente de este carril (`DECISIONES #621`). Techo 24 KB. El contador de la
> suite va en el trailer del commit (`#618`), no aquí.

## Foto (2026-09-20, cierre de la sesión)

- **Las 25 pantallas del cajón están construidas** (`#550`→`#568`): el armazón, el catálogo, el día y la
  hora, la cesta, pagar con sus cuatro desenlaces, las nueve de la cuenta y el suelo táctil.
- ▶▶ **`celebracion-e-invitacion.md` está COMPLETA EN CÓDIGO: las siete tandas cerradas** (`#569`→`#717`).
  **El detalle de cada unidad y lo que enseñó vive en su § de la spec** (§10.1–§10.17), que es donde no
  caduca; lo que de ahí vale para CUALQUIER tanda bajó aquí, a «Trampas vivas». Lo que hay que saber:
  - **En PRODUCCIÓN solo van T1→T4·4** (v1.1.0 = `3547de9f`, 18-09, parque cerrado; lo desplegó
    plataforma), y **con los dos interruptores APAGADOS**. Su migración quedó aplicada.
  - **SIN DESPLEGAR**: `#577` (RGPD) y `#578` (la API) de la T4, **toda la T5, toda la T6 y toda la
    T7** — incluida **una migración nueva**, `order_items.eve_notice_at` (`#717`).
  - Arneses de la feature: T4 9/9 · 13/13 · 11/11 · 12/12 · 5/5+1 · 14/14 · revisión adversarial 16/16 ·
    T5 8/8 y 7/7 · T6 8/8 · 6/6 · 8/8 · 9/9 · 7/7 · 13/13 · T7 8/8 · 10/10 · 12/12 (+1 declarado).
  - ⚠️ `party_invitations.reminded_at`/`reminded_count` son del **recordatorio del anfitrión** (`#713`,
    cuentan VECES) y **no envían nada**; el aviso de la víspera tiene su propia marca (`#717`).
  - **Falta el OJO del owner** (el bloque del anfitrión, el recibo de la T5, el justificante sin
    prerrelleno y **los tres correos, en Mailpit**), desplegar y **encender**, que es dato suyo.
- ✅ **`#707` · el defecto VIVO de `DependentsZone.vue`**, que levantó plataforma: `addBtn` sin declarar
  lanzaba `addBtn is not defined` al plegar el alta y **el foco caía al `<body>`**. Declararlo pasó el
  componente de 40 a 41 líneas y el gate `CE-6` paró el commit: **no se subió el techo**, bajó
  `signDependent()` al módulo plano. `FROZEN_JS_ERRORS` **12 → 10** · sonda `scripts/sonda-foco-cuenta.mjs`.
- La capa de agente: el plugin `jumpweb-agente` corre aquí desde el 17-09, actualizado a `07076ac`.

## Por dónde retomar, en orden

1. ❗ **EL OJO DEL OWNER sobre la T6 y la T5, en `localhost:8081`** — es lo único que separa la
   invitación de poder encenderse, y **ya no es código**:
   - El **bloque del anfitrión entero** en el post-form: compartir, las propuestas sobre las fichas,
     «no vienen», «no lo apuntes» y el **recordatorio**. Capturas de la T6·6 a 390 y 1280 en
     `storage/app/audit/sonda-t6/recordatorio/`; el pedido de la sonda es **`R-PRBT1A`** (pack 105).
   - El **recibo de la T5 en sus dos estados**, el bloque del calendario y la hoja del justificante
     **sin prerrelleno** (esta última se le enseñó en captura, **no en vivo**).
   - El **`.ics` en un TELÉFONO de verdad** (lo pide §4.6): medido por HTTP y en Chromium, nunca abierto
     con la app de calendario de un móvil. Si Android no lo abre bien, toca añadir el enlace de Google
     Calendar como segunda opción.
   - **El justificante EN PRODUCCIÓN, en ventana de teléfono** (v1.1.0): la barra de firmar arreglada y,
     allí sí, **el widget REAL de Turnstile** —en local no hay claves—.
   ⚠️ **Sin medir y declarado**: `§7.2·R12` pide contar los toques de Turnstile (solo en producción) · y
   `og:image` sale del logotipo del tema (1200×441): en una tarjeta 2:1 se ve con bandas.
2. ✅ **La T7 quedó CERRADA** (`#714`→`#717`, spec §10.14–§10.17) y con ella la feature entera. Los
   tres correos para el ojo del owner están en **Mailpit** (`:8028`): el del post-form con invitación
   y sin ella, y el **aviso de la víspera**. Las sondas que los mandan, sin versionar:
   `probe-t7-correo.php` y `probe-t7-vispera.php`.
   ⚠️ **Queda una cosa de la casa ajena**: el fichero `docs/carriles/correos.md` sigue diciendo «25
   correos» y son **26**. No lo toco (`#621`); está avisado en su buzón.
3. Lo que queda de la Fase 4: el **ojo del owner en un teléfono de verdad** (ninguna de las 25 pantallas
   se ha visto en uno) · el **cuaderno de entrega** del cajón · el **botón del sistema** (16/800 con borde).
4. Del plugin quedan **cuatro de las seis frases** por ver en vivo: `/decision`, `/sonda`, `/dod` y
   `/ligero`. Las dos medidas (F2·b, 19-09) son `/carril` y `/handoff`. Anótalo en el buzón de plataforma.

## Ficheros de este carril

`resources/js/sidebar/**` · `resources/js/ui/*` que solo use el cajón · `lang/*/tickets.php` y
`lang/*/account.php` · `lang/*/guestform.php` y `lang/*/guardian.php` · `resources/views/reservation/**` y las
clases `.gf-*` y `.guardian__*` · `tests/Feature/Sidebar/**`, `tests/Feature/Architecture/Sidebar*` y
`tests/Feature/Reservation/*SkinTest` · `scripts/sonda-cajon.mjs`, `sonda-enlace-firmado.mjs` · en
`public/css/site.css`, los bloques del cajón por su TÍTULO y el de la «HOJA ENFOCADA». **Compartido, se avisa
en el buzón ANTES**: `CARRIL-SPA.md` §5. Lo del cliente va también en la rama `cliente/playjump`, nunca a `main`.

## Trampas vivas

- ⚠️ **El molde `.gf-*` es de TRES páginas** (post-form, justificante e invitación): tras tocarlo, sonda
  Y captura de VENTANA de las tres. La de página entera cose lo pegado y no vio una barra de 401 px.
- ⚠️⚠️ **`DisplayTime::dayLabel()` NO convierte de zona** (T6·6): sus llamantes le pasan un Carbon ya
  construido en la del parque, y una columna de la BD sale en **UTC**. Sin `setTimezone` un sello
  escrito a las 00:30 de Madrid se fecha **el día anterior**. El caso que lo vigila **congela la hora
  ahí** y afirma primero que el contenedor va en UTC, o no mediría nada.
- ⚠️ **Lo que desborda a lo ALTO no lo dice una medida de ancho**: un `textarea` con `rows="5"` traía su
  propio scroll dentro del de la página y las cifras de la sonda estaban todas verdes. **Lo vio la
  captura.** Y dos pesos de botón en un bloque pequeño compiten con el «Guardar» de la barra.
- 🩹 **LA BD LOCAL se tocó el 19-09 para poder ver la T6** (es DATO, no código): el pack **105** tiene
  `guest_invitation = true` y `guardian_authorization = optional` (estaba en `none`, y con `none` los
  estados de puerta **no existen** por diseño); el pedido **`R-PRBT1A`** tiene tres fichas con nombre,
  tres respuestas y su invitación —es el de las sondas del post-form—, y **`R-PRBT64`** es la fiesta de
  HOY con 12 invitados y una firma atada, para la puerta. Los guiones que lo montan, en la carpeta de
  almacenamiento y sin versionar: `probe-t6-invitacion.php` (enciende el pack e imprime los enlaces),
  `probe-t6-respuestas.php`, `probe-t6-puerta.php` y las sondas `probe-t6-ventana.mjs`,
  `probe-t6-ficha.mjs` y `probe-t6-recordatorio.mjs`. ⚠️ Correr la del recordatorio **sube
  `reminded_count`**: si el owner ve «lo escribiste 7 veces», es la sonda, no un defecto.
  ⚠️ Un guion suelto se corre `php artisan tinker --execute="require base_path('storage/app/…')"`: con
  `php storage/app/…` a secas no hay framework y sale «Class not found».
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
- **La escala de la hoja `.gf-*` son TRES radios** (`--r-md`, `--r`, `--r-pill`) y ocho tallas:
  `GuestFormSkinTest` pone en rojo cualquier otro. La guarda tiene razón; se cambia el valor, no la guarda.
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
- ⏰ **Un test con reloj propio miente DOS HORAS AL DÍA**: `ScheduleFactsTest` se puso en rojo a las
  00:07 del 20-09 —dos horas después de estar verde— porque abría el día de la semana de
  `Carbon::now()` (contenedor, **UTC**) y el servicio pregunta por el del **parque**. El producto
  calculaba bien. Entre las dos medianoches **no es el mismo día**: en un test de «ahora», el reloj
  se pide a `DisplayTime`, nunca a `Carbon`.
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
- Commit por NOMBRE de fichero, nunca `git add -A`. La decisión, al final de `docs/decisiones/700-799.md`.

## Buzón

### ❗ Para el carril de CORREOS (emisor: SPA, 2026-09-19) — AVISO ANTES DE TOCAR
- ▶ **Voy a entrar en tus ficheros para la T7 de la invitación** (`celebracion-e-invitacion.md` §4.9 y
  §10.14, plan en `#714`): `app/Notifications/GuestFormRequest.php`, una **notificación nueva** para el
  aviso de la víspera con sus dos plantillas (`html/` y `text/`), `lang/{es,en,fr}/emails.php` y sus
  tests; y en tu spec, el inventario **25 → 26**.
- ✅ **HECHO Y CERRADO, la T7 entera** (`#715`→`#717`). De tus ficheros toqué **dos cosas y nada más**:
  `GuestFormRequest` (cuatro claves por idioma; cambia cuerpo y llamada **solo** con invitación, y el
  asunto y la línea de adelanto **no tienen variante**, para no sacarlo de tu censo) y un **correo
  nuevo**, `VisitEveNotice` —el aviso de la víspera—, con sus cuatro piezas de bandeja en los tres
  idiomas. **No toqué el tema, el remitente, el modo oscuro ni ningún otro de los 25.**
- ▶ **Tu inventario pasa a 26.** Lo actualicé en tu **spec** (§0 y cabecera); **`carriles/correos.md`
  sigue diciendo 25 y no lo toco yo** (`#621`) — es una línea tuya.
- ❗ **Un hallazgo tuyo que pagué yo**: escribí en un comentario la llamada de cabecera entre comillas
  para explicar el escáner, y tu `MailInboxLineTest` se quedó con el ejemplo en vez de con el código —
  **los 12 avisos en rojo de golpe**. Tu guarda funciona; la mutación que lo reproduce está en
  `scripts/mutar-invitacion-t7-1.py`. Quizá merezca una línea en tu §0.
- ▶ **Para tu OJO en Gmail/Outlook**, que es lo que te queda: los tres correos están en Mailpit y las
  sondas que los mandan, en la carpeta de almacenamiento (`probe-t7-correo.php`, `probe-t7-vispera.php`).
- **Por qué la hago yo y no tú**: la tanda depende del dominio de la invitación (T4 y T6) y del libro
  del pedido; **el molde no se toca, se usa** (`BrandedMailMessage`, línea de adelanto, botón de tinta).
  Si prefieres cogerla tú, dilo y te la paso entera con el plan hecho.
- **Lo que NO voy a tocar**: el tema (`vendor/mail/html/themes/brand.css`), el remitente
  (`ApplyBusinessSender`), el modo oscuro ni ninguno de los 25 correos existentes salvo
  `GuestFormRequest`, que gana un botón y una frase.
- ⚠️ Anotado de tu §0: **un componente sin su gemelo en `text/` renderiza bien y REVIENTA al enviar**;
  `Mail::fake()` intercepta antes de construir, así que un caso del remitente sale verde desconectado;
  y un `*/` dentro de un docblock lo cierra y el render devuelve el HTML anterior sin avisar.

### Para el carril de plataforma (emisor: SPA, 2026-09-20)
- ⚠️ **Toqué un test tuyo, `tests/Feature/Api/ScheduleFactsTest.php`, porque estaba en ROJO** y me
  bloqueaba el push. No era mío ni de mi tanda: sus casos de «ahora» abrían el día de la semana de
  `Carbon::now()` —el contenedor, **UTC**— mientras el servicio pregunta por el del **parque**, así
  que entre la medianoche de Madrid y la de UTC **no era el mismo día**. Se puso rojo a las **00:07
  del 20-09**, dos horas después de estar verde. **El producto calcula bien**; lo que mentía era el
  test. Arreglado con un ayudante `ahora()` que lee `DisplayTime`. Si prefieres otra forma, dilo.
- ▶ **Lo empujado de la T7** (`#715`→`#717`), para el próximo despliegue: `GuestFormRequest`, un
  **correo nuevo** (`VisitEveNotice`), un **comando** (`reservations:eve-notice`) con su línea en el
  scheduler —**cada hora**, y la hora la decide el comando: ver `#717` para el porqué—, `PendingWork`
  y `PendingBeforeVisit` en Booking, `lang/*/emails.php` y **UNA MIGRACIÓN**:
  `order_items.eve_notice_at`. ⚠️ **Migración empujada ≠ aplicada**: en producción hay que correrla.
  Sin tocar dinero ni aforo; ninguno casa con el `CRITICAL_RE` (comprobado con `grep`).
- ❗ **Para cuando toques `order_items` con el constructor de consultas**: el de **Eloquent SÍ escribe
  `updated_at`**. Si alguna doc tuya dice que «por el constructor de consultas no se toca el
  testigo», es falso — hay que usar `toBase()`. Lo pagué en `#717`.

### Para el carril de plataforma (emisor: SPA, 2026-09-19, cierre)
- ▶ **La T6 cerró con `#713`**, y suma al próximo despliegue sobre lo ya anunciado (`#708`→`#712`): la
  ruta `reservation.invitation.remind`, `PartyInvitations` (tres métodos), `OrderItem`,
  `GuestFormController::writeReminder()`, la vista del post-form, `lang/*/guestform.php` y el bloque de
  la hoja en `site.css` — con `cajon.css` **regenerado** (solo cambia el sello de `FUENTES`). Sin
  migraciones ni contrato de API; ninguno casa con el `CRITICAL_RE`.
- ▶ **La invitación está lista para encenderse**: los dos interruptores son **DATO** y decisión del owner.

### Atendido
- **Plataforma, 19-09**: leídas sus cuatro respuestas. La convención del `button` **me la quedo** · el
  alcance de `#708`→`#711` va al próximo despliegue · `FROZEN_ERRORS` a 458, visto · y el **422 de
  `InvitationHostController` lo coge plataforma**, retirado de mi lista. Puede retirar los cuatro.
- **Plataforma, 19-09 (F4 y `compose.yaml`)**: anotado. El cajón como paquete se lee de
  `cajon-empaquetable.md` §0/§4.8 · `npm run build:ssr` tras tocar un `.vue` · y el montaje nuevo recrea
  el contenedor y se lleva el Chromium: las dos cosas están arriba, en «Trampas vivas».
- **Plataforma, 16-09, 17-09 y 18-09**: todos atendidos y retirados. Queda **repasar el `§0` de
  `sidebar-spa.md`**, que lo escribió plataforma.
- **Web · `#539`/`#540`** (botones al secundario, ninguno en negro): atendidos. Las dos hojas de enlace
  firmado usan `.btn--ink`, que lee `--secondary` (`#570`, `#572`). Puede retirarlos.
