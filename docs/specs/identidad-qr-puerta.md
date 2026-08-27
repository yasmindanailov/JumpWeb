# [SPEC] El carné QR del cliente y la pantalla de puerta

> Estado: ✅ **APROBADA por el owner el 2026-08-27** (`DECISIONES #208`) **con §8 incorporado**, y
> **en cola de ejecución** (carril A: va DESPUÉS del panel de menores D14, `menores-a-cargo.md` §9.10) ·
> **§8.2 DECIDIDO**: el carné es de **20 caracteres** (2 de prefijo + 17 aleatorios + 1 de control →
> `2⁸⁵`), medido: cabe en la misma versión 2 (25×25, ECC H) que los 13 de §4.3 — **§4.3 queda
> corregida por esta línea** · **§8.1 no es decisión sino construcción**: el token se lee siempre por
> un método que captura `DecryptException` y devuelve `null` ·
> ❗ **§8.3 AMPLÍA el alcance**: esta pantalla pasa a ser el sitio donde se **acredita la visita** de
> un cliente, y de ahí salen sus JumpPoints (`[DECIDIDO owner]`). No es un detalle: si el punto cae al
> **abrir** la ficha, el saldo depende de cuántas veces mire el empleado. Léelo antes de §4.6.
>
> Estado anterior: diseño 🟦 **REVISADO** (§8), pendiente del ✅ del owner · antes: 🟦 en revisión ·
> Última actualización: 2026-08-24 ·
> Verificado contra código: 2026-08-24 (ValidarRegistro, QrCode, TicketIssuer, User::revokeAllAccess, SecurityHeaders) ·
> Decisión asociada: `DECISIONES #142` ·
> Se invalida si: cambia el hardware de lectura del recinto, o el modo de waiver de `waiver-probatorio.md`.

Subsistema **A** de la visión de Fase 6. Va **después** de `waiver-probatorio.md` y
`menores-a-cargo.md`: la pantalla solo tiene sentido cuando hay waiver propio y menores que enseñar.

---

## 1. Contexto y problema

El cliente llega al recinto y el empleado tiene que resolver dos cosas en segundos: **¿está cubierto?**
y **¿qué le entrego?**. Hoy solo puede resolver la primera, y a medias.

**Lo que hay, verificado el 2026-08-24:**

- `Livewire\Admin\Puerta\ValidarRegistro` busca por email o teléfono y devuelve **solo el estado del
  waiver**. Su docblock lo dice con estas palabras: *«sin nombre, sin email/phone completo, sin
  historial»*. Re-autoriza el permiso en **cada** petición (`SEC-04`), limita a 100/min **por empleado**
  —no por IP, porque comparten red en el recinto— y audita con el identificador en **sha256**.
- La búsqueda por teléfono es de **coincidencia exacta** sobre el valor normalizado a dígitos: **no se
  puede pescar con fragmentos**. El input ya lleva `autocomplete="off"`, y la ruta es **una sola URL
  sin parámetro**, así que el dato buscado **nunca entra en el historial del navegador**.
- ⚠️⚠️ **`Ticket.qr_token` es una credencial MUERTA.** Se genera en `TicketIssuer`, es aleatoria e
  impredecible, y **no la lee ni la publica nadie** — medido: 0 consumidores; `CustomerOrderHistoryReader`
  y `openapi/v1.yaml` declaran expresamente que **no se publica**, describiéndola como «la credencial
  que canjea la entrada en la puerta»… y no hay puerta que canjee, porque el ciclo de canje se retiró.
- **Ningún correo lleva un QR.** El único QR del producto es el de la landing, que apunta al sistema de
  registro **externo**.

## 2. Objetivo

Que el cliente se identifique con **un** artefacto, y que el empleado vea de un vistazo lo que necesita
para atenderle — **sin que el artefacto por sí solo dé acceso a nada**.

**Criterios de éxito medibles:**
- Un escaneo resuelve la ficha completa en **una lectura compuesta**, con presupuesto de consultas.
- El carné entra en `User::revokeAllAccess()` — mutación: sacarlo pone el test rojo.
- El escaneo **nunca** devuelve el nombre de un menor.
- La ficha **caduca en servidor**: recargar o restaurar una pestaña dormida **no la resucita**.
- El QR escanea con el lector real del recinto, medido con el hardware, no con un móvil.

**FUERA de alcance:**
- Canje digital de entradas (el ciclo de `Ticket` sigue amputado; es otro trabajo).
- Modo sin conexión. §4.9 dice por qué y qué se hace en su lugar.
- Integración con el sistema de pulseras del fabricante (`OPERATIVA-SECTOR-ORIGEN.md` §5).

## 3. Opciones consideradas

**A · Identificador estable de la cuenta (ELEGIDA).** Un carné: opaco, rotable, que viaja por correo.

**B · Credencial rotatoria tipo TOTP.** DESCARTADA: **mata el requisito de que el QR viaje en el correo
de confirmación**, exige la app, añade sincronía de reloj — y resuelve una amenaza que aquí casi no
existe, porque el escaneo no autentica (§4.2).

**C · El QR contiene una URL.** DESCARTADA **con medida**. Generado con la librería ya vendorizada:

| Contenido | ECC | Módulos | Versión |
|---|---|---|---|
| Token de 13 caracteres en mayúsculas | **H** | 25×25 | **2** |
| El mismo token | M | 21×21 | 1 |
| URL de 33 caracteres | H | **33×33** | **4** |

Para el mismo tamaño impreso, cada módulo es un **~32 % más pequeño** → escanea peor justo en el
escenario malo (carné rayado, pantalla sucia, poca luz). Y un lector configurado para abrir URLs haría
cosas raras. **El payload es el token pelado.**

**D · Reutilizar `Ticket.qr_token`.** DESCARTADA: identifica una **admisión**, no a una **persona**. Son
objetos distintos con ciclos de vida distintos.

## 4. Diseño elegido

### 4.1 Un carné estable, opaco y rotable

Uno por titular. **Sin número de socio visible** (decisión del owner): mezclar «identificador legible
que se dice por teléfono» con «token opaco rotable» produce un identificador que no se puede rotar
porque está impreso, o uno que nadie puede leer en voz alta.

### 4.2 Escanear NO autentica: busca

**Es la clave que relaja todo el diseño.** El escaneo no inicia sesión: es una **búsqueda**. La
autoridad la pone **la sesión del empleado** con su permiso, exactamente como hoy. **Poseer el QR no da
acceso a nada.** Por eso puede ser estable, viajar por correo e imprimirse.

⚠️ El reverso es igual de importante: como el escaneo revela waiver, reservas, menores y vales, **una
foto del QR de otro es una fuga de datos personales de terceros**. Las tres defensas que ya existen
—permiso, límite por empleado, auditoría con hash— **no son opcionales**, y §4.6 las endurece.

### 4.3 La forma del código

**13 caracteres: 2 de prefijo + 10 aleatorios + 1 de control.**

- **Mayúsculas y alfabeto reducido** (Crockford Base32: sin `I`, `L`, `O`, `U`). Tres razones distintas:
  entra en el **modo alfanumérico** del QR, que es el más compacto; **A-Z y 0-9 son las teclas que no
  cambian entre distribuciones de teclado** —y el escáner del recinto es un *keyboard wedge*, así que
  con el lector en US y el equipo en ES un guion o un subrayado salen mal—; y no hay ambigüedad visual
  si alguien lo dicta o lo teclea como plan B.
- **Entropía**: 32¹⁰ ≈ 1,1×10¹⁵.
- **Carácter de control**: un escaneo defectuoso falla en el navegador, no contra la base de datos.
- **ECC = H.** Medido arriba: con 13 caracteres la redundancia máxima cuesta pasar de versión 1 a 2.
  Es el mejor cambio de relación calidad/precio del diseño.
- ⚠️ **Zona de silencio ≥ 4 módulos.** `Platform\Services\QrCode::svg()` usa `quietzoneSize = 0` **a
  propósito** —el marco de la tarjeta de la landing hace de margen visual—, y eso vale para un adorno
  que se escanea con el móvil. Para un lector de mostrador es un riesgo real: **el carné necesita su
  propio perfil de generación, no reutilizar ese método.**

### 4.4 Dónde vive el token, y la trampa que este repo ya pagó

⚠️⚠️ **`RGPD-06` es la invariante que decide esto.** Dice que invalidar el acceso de un titular tiene
**un solo sitio** (`User::revokeAllAccess()`), y cuenta por qué nació: la purga de sesiones estaba
**copiada en cuatro ficheros** y **ninguna revocaba tokens de Sanctum, porque Sanctum llegó después**.

**Un carné QR es exactamente la siguiente credencial que llega después.** Si nace como columna suelta
en `users` y no entra en `revokeAllAccess()`, se repite ese bug punto por punto.

**Por eso: tabla propia, con `revoked_at`, y entra en `revokeAllAccess()` en el primer commit, con su
caso de prueba.** La tabla propia da además dos cosas que una columna no: **historial de rotación**
—una auditoría de «se escaneó el carné X» tiene que resolverse aunque el carné se haya rotado— y la
puerta abierta a un carné físico impreso conviviendo con el digital.

### 4.5 Cómo se guarda

Hacen falta dos cosas que se contradicen: **buscar por él** (escaneo) y **volver a pintarlo** (cada
correo de confirmación). Hashearlo como Sanctum impide lo segundo; guardarlo en claro entrega todos los
carnés en un volcado.

**Se tienen las dos**: `token_hash` (sha256, único, indexado) para buscar, y el token con el cast
`encrypted` para repintar. Coste en ejecución: cero. Y degrada bien: si rota `APP_KEY`, **el escaneo
sigue funcionando** —el hash sobrevive— y solo se pierde el repintado; se rota el carné.

En logs, nunca: se audita con el patrón `logSensitive` que la puerta ya usa.

**Rotación**: la dispara el titular desde su cuenta, el admin desde el panel, y `revokeAllAccess()`.
⚠️ **Mata el carné viejo en el acto** (decisión del owner). Ante uno revocado, la pantalla dice «carné
caducado — busca por email», que es un camino que **ya existe**. Una ventana de gracia suena amable y
en realidad es una credencial revocada que sigue valiendo.

### 4.6 La ficha, y los contrapesos que exige

**Qué muestra**, ordenado por lo que el empleado necesita primero:

| | Bloque | Contenido |
|---|---|---|
| 1 | **Identidad** | Nombre del titular · waiver: fecha, y marca si es de una versión antigua (**deja pasar igual**, `waiver-probatorio.md` §4.8) |
| 2 | **HOY** | Reservas de hoy —**en plural**— con tipo, franja, cantidad y complementos · **pendiente de cobrar en puerta** · importe pagado · cuándo la hizo y cuándo pagó |
| 3 | **Menores a cargo** | **Solo edad**, nunca el nombre · estado del waiver de cada uno |
| 4 | **JumpPoints** | Vales activos, con acción de **marcarlos usados** · saldo |
| 5 | **Ventana ±N días** | En segundo plano, configurable. Cubre al que llega un día tarde **y al que llega un día antes**, que es el caso más frecuente |

**Qué NUNCA muestra**: email o teléfono completos, dirección, historial de importes, **nombres de
menores**, y ⚠️ **alergias** — están en `guest_data`, son art. 9, y las necesita **la cocina**, no la
puerta. La hoja de reserva ya las imprime para quien las necesita.

**Los tres estados que tiene que decir en voz alta**, porque son los que rompen la operativa:
1. **Sin reserva hoy** → dicho con claridad. Con el modelo de cupo online (`OPERATIVA-SECTOR-ORIGEN.md`
   §4) esa persona **puede comprar en puerta**, y la pantalla no puede parecer un error.
2. **Tiene reserva, pero otro día** → distinto de «no tiene nada»; evita una discusión en el mostrador.
3. **Adulto con menores no asignados** → «0 menores a cargo» es una respuesta **válida**. El empleado
   resuelve fuera del sistema, como hoy.

⚠️ **La búsqueda por email/teléfono TAMBIÉN abre la ficha completa** — decisión del owner, tomada sobre
la alternativa de reservarla al QR escaneado. **El riesgo es real y hay que nombrarlo**: convierte la
puerta en un oráculo, porque tecleando correos se obtienen perfiles. Va con **cuatro contrapesos
obligatorios**:

1. ⚠️ **Dos acciones, dos limitadores.** Escanear un QR es alto volumen y legítimo (una cola entera);
   teclear un correo debería ser raro («me he dejado el móvil»). **La búsqueda tecleada lleva su propio
   límite, mucho más bajo**, del orden de decenas por hora. Un empleado que teclea cincuenta correos en
   una hora no está atendiendo.
2. **La coincidencia exacta se conserva.** Ya lo es hoy; el riesgo es romperla al reescribir.
3. **Se audita la DIVULGACIÓN, no solo la búsqueda** — acción propia, para poder distinguir en el visor
   una consulta de una extracción.
4. **El aviso al operador ya está construido**: `AuditLog::CRITICAL_ACTIONS` **ya incluye**
   `registrations.validate_rate_limited` con el comentario «abuso: rate-limit en la puerta», y las
   acciones críticas ya van al visor de incidencias **y al correo del operador**. Con el limitador
   nuevo, el aviso se hereda gratis.

**Y un permiso propio**, separado de `registrations.validate`: eso significaba «¿está registrado?»,
y esto es otra cosa. El catálogo ya distingue matices así (existe `users.search_minimal`).

### 4.7 El dinero de la ficha

Al enseñar importes, **la pantalla de puerta pasa a ser una superficie más del ledger**:

- Los importes salen de **`Booking\Services\OrderLedger`** (`pagadoOnline`, `pendientePuerta`) y
  **nunca se recomponen**. `LedgerSingleSourceTest` lo tumba **aunque el resultado sea correcto hoy**,
  y lleva la lista de superficies: añadir la puerta es parte del cambio.
- ⚠️ **Y hay un aviso vivo que aquí importa más que en ningún sitio.** Existe un caso canónico —
  `R-L6UTIA`, en `specs/desglose-dinero-cliente.md` — donde el desglose dice «Pagado por web 114,00 €»
  y el pago real fueron 30: es un **dato roto en la base de datos**, no un fallo del código. Hasta
  ahora ese número lo veía un administrador; a partir de aquí **lo ve un empleado con un cliente
  delante**. La regla —*comprueba si el dato es real antes de buscar el fallo en el código*— tiene que
  estar también aquí.
- **El pendiente en puerta no es opcional**: el producto tiene sistema de señal (`sistemas/DEPOSITO.md`),
  y si el empleado no ve cuánto queda por cobrar, **el negocio no cobra**.

### 4.8 Sin historial, dos relojes, y por qué el navegador no basta

**Sin historial propio de búsquedas**, y hay tres cosas que lo hacen verdad o mentira:

- **`autocomplete="off"` ya está** en el input. Sin él, el autocompletado del navegador **es** el
  historial de búsquedas, y en una tablet compartida cualquiera lo despliega.
- ⚠️ **La ficha se pinta sin cambiar de URL.** Hoy la ruta no lleva parámetro. Si la pantalla nueva
  usara una URL por cliente, **el historial del navegador pasaría a ser el registro de a quién se ha
  mirado**.
- ⚠️ **«Sin historial» ≠ «sin rastro».** El empleado no puede consultar a quién ha mirado; **el
  operador sí**, por `audit_logs`. Que quede con estas palabras: si no, alguien leerá «no guarda
  historial» y concluirá que no hay que auditar.

**Dos relojes, no uno.** El riesgo no es el empleado, es **la cola**: una pantalla de mostrador se lee
de reojo, y ahora lleva nombre, edades de menores e importes.

| Reloj | Cuándo | Qué hace |
|---|---|---|
| **Velo por inactividad** | ~60 s | Tapa el contenido; un toque lo devuelve. No se pierde el contexto |
| **Cierre** | **5 min** | Descarta la ficha y vuelve a la búsqueda |

Cualquier interacción **reinicia los dos** — si no, el cierre corta al empleado justo cuando marca un
vale como usado. Y el CTA de «volver a buscar» es el `clear()` que ya existe y ya re-autoriza.

⚠️⚠️ **Un temporizador en el navegador NO es una garantía.** Si la pestaña se va a segundo plano, el
equipo se suspende o el JS se pausa, puede no dispararse — y el dato ya está en el DOM. Lo que lo hace
real es que **la ficha caduque en el SERVIDOR**: se re-valida su frescura **en cada ida y vuelta** y
devuelve nada si expiró. Es literalmente `SEC-04` aplicado al tiempo en vez de al permiso.
▶ Lo comprobable: **recargar la página o restaurar una pestaña dormida no resucita la ficha.** Eso es
lo que hay que verificar por mutación, no que el temporizador exista.

### 4.9 El plan B (caída de red)

⚠️ **No se construye software para esto todavía**, y es una decisión con doctrina detrás: `PERF-07`
dice *«no añadir keep-warm de OPcache ni tuning especulativo de servidor sin medir primero»*. Aquí
igual — **¿cuántas veces al año se cae la red del recinto y cuánto dura?**

- **Descartado: un programa local de escritorio.** Sería un segundo código base, con su propio canal de
  actualización en cada máquina y **un segundo sitio donde viven datos personales de menores**. Rompe
  el modelo del producto (`DECISIONES #2`) y **sigue teniendo que resolver la misma sincronización**.
- **Recomendado: redundancia de conexión + papel.** Si el problema es que se cae la red, la solución es
  que no se caiga.
- **Lo que sí se hace ahora y cuesta horas**: que la pantalla **degrade con dignidad** — si no hay red,
  lo dice y recuerda el camino de papel, en vez de quedarse girando.
- Si algún día se midiera que hace falta, sería una PWA, no un ejecutable. ⚠️ Y arrastraría su propia
  decisión de RGPD: cachear «quién tiene waiver» es **una copia de datos personales en un dispositivo
  del recinto**.

### 4.10 El QR en el correo de confirmación

- **Medido el 2026-08-24**: el contenedor trae **GD e Imagick**, y `chillerlan/php-qrcode` ya
  vendorizada incluye salida PNG. Se puede generar PNG **sin dependencia nueva** (`CONVENCIONES` §9 no
  se dispara).
- **Y hace falta**: el QR actual es SVG inline, y los clientes de correo mayoritarios no lo renderizan.
  ⚠️ **Esto no está medido en este repo** — es conocimiento general y hay que comprobarlo con Mailpit y
  con cuentas reales antes de decidir. El diseño seguro es **PNG adjunto en línea**, no imagen remota:
  los clientes bloquean imágenes remotas por defecto y un adjunto se ve sin permiso y sin conexión.
- ⚠️ **Pendiente de verificar**: si staging tiene GD. No tiene node/npm, y las extensiones de PHP no se
  han inventariado (`ENTORNOS.md` §4).
- ⚠️ El correo con carné **es PII**: hay que decidir si se puede reenviar desde el panel y con qué
  permiso.

### 4.11 Arquitectura

**Un servicio de dominio que compone la ficha entera en una lectura**, con su **presupuesto de
consultas** en un test. Es la forma que este repo ya resolvió una vez: `GET /me/account-context` nació
porque *«`me/orders` PAGINA, así que contar pendientes sobre una página cuenta mal»*, y
`CustomerAccountContext` memoiza por petición. Un escaneo pide siete u ocho cosas y en hora punta se
escanean decenas.

**Y ese servicio es lo que abre el futuro**: si la ficha la ensambla el componente, cuando llegue una
app nativa de escaneo hay que reescribirla; si la compone un servicio, el endpoint de API lo envuelve.

**Tecnología**: para el primer corte se queda **server-rendered como hoy** (Livewire con su layout
propio de puerta). Es una pantalla de staff, de un solo golpe, que tiene que ser rápida y aburrida — y
Livewire se queda en el stack de todas formas porque es quien trae Alpine (`DECISIONES #123`).

## 5. Impacto en invariantes

| ID | Impacto |
|---|---|
| **RGPD-06** | ⚠️ **Se AMPLÍA**: el carné es una credencial y entra en `revokeAllAccess()`. Es el motivo por el que la invariante existe |
| **RGPD-04** | Se AMPLÍA: la ficha y el correo con carné llevan PII y van con `no-store` |
| **SEC-04** | Se APLICA **al tiempo**: la ficha re-valida su frescura en cada petición, no solo al abrirse |
| **SEC-05** | Se conserva y se endurece: el rechazo por límite se sigue auditando, y ahora hay dos limitadores |
| **PERF-01/02** | Se CITAN: la ficha se compone en una lectura, con presupuesto de consultas |
| — | **Ninguna se relaja.** Lo que cambia es una decisión de producto (`DECISIONES #142`, reversión 2), no una invariante |

## 6. Plan de verificación empírica

**Guardas ejecutables:**
1. **El carné entra en `revokeAllAccess()`** — mutación: sacarlo pone el test rojo.
2. **El escaneo no devuelve nunca el nombre de un menor.**
3. **La ficha caduca en servidor** — recargar tras el plazo no la resucita.
4. **La búsqueda tecleada tiene su propio limitador**, distinto del escaneo.
5. **Los importes salen del ledger** — `LedgerSingleSourceTest` lo cubre al añadir la superficie.
6. **Presupuesto de consultas** de la ficha compuesta.

**Comprobación empírica (nada de esto lo ve la suite):**
- ⚠️ **Escanear con el LECTOR REAL del recinto**, no con un móvil: es la única forma de saber si la
  distribución de teclado, la zona de silencio y el tamaño impreso funcionan.
- Abrir el correo de confirmación en **Gmail, Outlook y un cliente de móvil** y comprobar que el QR
  se ve. Es donde el diseño tiene su supuesto no medido.
- Dejar la ficha abierta y comprobar el velo y el cierre **con reloj de verdad**, y después **recargar**
  para comprobar que no resucita.
- Rotar un carné y comprobar que **un correo antiguo deja de valer en el acto**.

## 7. Revisión y decisión

Diseñado en sesión de arquitectura con el owner el 2026-08-24 (`DECISIONES #142`).

✅ **REVISADA el 2026-08-25** por un segundo agente (`CONVENCIONES` §5) — **§8**. Veredicto: sólida;
dos hallazgos acotados (§8.1 la rotación de `APP_KEY`, §8.2 la entropía del carné) y **una
responsabilidad NUEVA** que le llega desde JumpPoints: **esta pantalla es donde se acredita la
visita** (§8.3), y eso exige idempotencia y auditoría desde el primer commit.

⚠️ Esta spec contiene la
**segunda reversión** de `#142` —la puerta deja de ser privacy-by-design mínima— y es la que más
merece un revisor hostil.

---

## 8. Revisión adversarial — 2026-08-25

> Segundo agente, `CONVENCIONES` §5. Esta spec pedía «un revisor hostil» por ser la segunda reversión
> de `#142`; lo que sigue es el resultado. **Solo hallazgos**: lo que no aparece se verificó y es
> cierto.

### 8.0 Lo que aguantó — y es casi todo

Verificado ejecutando el 2026-08-25: `ValidarRegistro` tiene sus tres defensas tal cual las describe
(permiso re-autorizado en cada petición, límite por `user_id` del staff y auditoría con sha256) ·
**`Ticket.qr_token` está muerta de verdad**: se escribe en `TicketIssuer`, se comprueba su unicidad,
y **no la lee ni la publica nadie** —`CustomerOrderHistoryReader` y `openapi/v1.yaml` declaran
expresamente que no se publica— · `Platform\Services\QrCode::svg()` usa `quietzoneSize = 0`, así que
§4.3 acierta al exigir perfil propio para el carné · `AuditLog::CRITICAL_ACTIONS` **ya incluye**
`registrations.validate_rate_limited` · el permiso `users.search_minimal` existe en
`PermissionSeeder`, así que el catálogo ya distingue matices como pide §4.6 ·
`LedgerSingleSourceTest` existe · y **re-medido hoy en el contenedor**: **GD e Imagick presentes** y
`chillerlan/php-qrcode` vendorizada. §4.10 no dispara `CONVENCIONES` §9.

### 8.1 «Si rota `APP_KEY`… solo se pierde el repintado» no sale gratis

§4.5 dice que el diseño **degrada bien**: el hash sobrevive, el escaneo sigue, solo se pierde volver a
pintar el carné.

⚠️ Con el cast `encrypted` de Laravel, leer el atributo con la clave rotada **lanza
`DecryptException`** — no devuelve `null`. Así que cualquier superficie que **toque** el token
(un listado del panel, un recurso que serialice el modelo, un `toArray()`) responde **500**, no
degradado. La degradación elegante que la spec promete **hay que construirla**: acceso al token
siempre por un método que capture y devuelva `null`, y ninguna superficie leyendo el atributo directo.

▶ Es barato, pero si no se escribe aquí se descubre el día que se rote la clave — que es exactamente
el día en que nadie quiere descubrir nada.

### 8.2 La entropía del carné es fina para lo que ese carné dura

§4.3 fija **10 caracteres aleatorios** de Crockford Base32 → `32¹⁰ ≈ 1,1×10¹⁵ ≈ 2⁵⁰`, y §4.5 los
guarda como **sha256 sin sal** (rápido por diseño: tiene que servir para buscar).

⚠️ **2⁵⁰ con un hash rápido y sin sal es un espacio enumerable.** Ante un volcado de base de datos, se
recorre entero en el orden de un día de GPU — y entonces se tienen **todos** los carnés del parque, no
uno. Compárese con Sanctum, cuyo modelo se cita como referencia: sus tokens son de 40 caracteres.

▶ **Lo que salva el diseño hoy es §4.2**, y hay que decirlo: como escanear **no autentica**, poseer un
carné no da acceso a nada sin la sesión de un empleado. Esa decisión es la que convierte esto en un
riesgo acotado en vez de una brecha.
▶ **Pero el carné se imprime y vive años**, y §4.6 hace que un escaneo abra la ficha completa. La
recomendación no es rediseñar: es **decidirlo explícitamente**. O se sube la longitud —con la misma
tabla de módulos que §3 ya usó para descartar la URL, midiendo si 16 o 20 caracteres siguen cabiendo
en versión 2 con ECC H— o **se escribe en la spec por qué 2⁵⁰ basta**. Lo que no puede quedarse es sin
decidir, porque parece decidido y no lo está.

### 8.3 🆕 Esta pantalla gana una responsabilidad nueva: es donde se ganan puntos

✅ **[DECIDIDO owner, 2026-08-25]**, al revisar `lealtad-jumppoints.md`: **abrir la ficha del cliente
tras escanear su QR es el acto que le acredita la visita**, y de ahí salen sus puntos.

Eso convierte esta pantalla en el **único observador de la visita** que el producto tendrá (el ciclo
de canje de `Ticket` sigue amputado, §1). Consecuencias que esta spec tiene que absorber:

1. ⚠️⚠️ **Ganar puntos NO puede colgar de «se abrió la ficha».** La ficha se abre por muchas razones
   —comprobar un waiver, buscar una reserva, mirar un vale, y **§4.6 la abre también tecleando un
   correo**—, y varias veces por el mismo cliente. Si el punto cae al abrir, **el saldo depende de
   cuántas veces mire el empleado**. Tiene que ser un **acto explícito y acotado** (un botón
   «registrar visita», idempotente **por cliente y día**), no un efecto secundario de pintar.
2. La acreditación de la visita es un **evento auditable** y entra en el mismo régimen que el resto de
   §4.8: se audita la acción, no solo la búsqueda.
3. Refuerza §4.11: si la ficha la ensambla el componente y no un servicio, este acto se queda dentro
   de Livewire y la app nativa no lo puede disparar.

▶ **El detalle del modelo de puntos vive en `lealtad-jumppoints.md` §8.1.** Aquí queda escrito porque
es **esta** pantalla la que lo tiene que ofrecer, y porque la dependencia va en el orden correcto:
**A antes que D**, como ya fijaba el tracker.

### 8.4 Veredicto

**La spec es sólida y su autoconciencia es real** — nombra sus propios riesgos, incluido el que la
convierte en un oráculo (§4.6), y trae los contrapesos. Los dos hallazgos técnicos (§8.1, §8.2) son
acotados y no tocan la arquitectura. El cambio de fondo es §8.3: la pantalla deja de ser solo consulta
y pasa a **escribir** algo que vale puntos, y eso exige idempotencia y auditoría desde el primer
commit.

---

## 9. Ejecución (2026-08-27 noche, carril A, `DECISIONES #208`) — el diseño de ejecución, MEDIDO antes de escribir

> La spec está aprobada (§7, `#208`) y las dos decisiones de §8 tomadas (20 caracteres; la rotación de
> `APP_KEY` se construye). Esto es cómo baja al código, leído en el árbol `8ab0f5c` (tras la tanda 5 de
> menores) el 2026-08-27 por la noche.

### 9.1 Lo que el código enseñó — y lo que corrige o precisa al cuerpo

1. **`ValidarRegistro` (225 líneas) tiene las tres defensas tal cual** (permiso re-autorizado en cada
   petición, límite por `user_id` del staff, auditoría con sha256) y **23 tests** en
   `ValidarRegistroTest`. `detectInputType()` devuelve `email | phone | null`; la ruta es una sola URL
   sin parámetro; el input ya lleva `autocomplete="off"`. ▶ La pantalla **crece**, no se reescribe: los
   estados de hoy siguen valiendo para quien no tenga el permiso nuevo (A·5).
2. **`Ticket.qr_token` sigue muerta** (0 lectores). No se toca (§3·D).
3. **`Platform\Services\QrCode::svg()` es un perfil de adorno** (`eccLevel M`, `quietzoneSize 0`,
   SVG para CSS). El carné necesita **PNG, ECC H y zona de silencio 4** (§4.3): `QrCode::png()` con
   perfil propio; GD e Imagick están en el contenedor (§8.0).
4. **`User::revokeAllAccess()` borra sesiones y tokens** y **`AccessRevocationTest` escanea `app/` por
   LITERALES de tabla** (`sessions`, `personal_access_tokens`) con dos ficheros permitidos: `User.php`
   y `PurgeCustomerData`. La tabla del carné entra en esa lista: Eloquent no necesita el literal
   (convención de nombre), así que **solo `User.php` podrá nombrarla** — y la purga de go-live no la
   necesita porque la FK es CASCADE (`personal_access_tokens` se borra a mano porque NO tiene FK).
5. **`anonymize()` SÍ termina en `revokeAllAccess()`** (A2 de la auditoría de Fase 1: «centralizado
   aquí → lo garantizan AMBAS vías»), además de borrar `password_reset_tokens` por su cuenta. ⚠️ Este
   punto se escribió primero al revés («NO llama») leyendo solo la mitad del método: **medido, llama.**
   Así que el carné entra UNA vez, en `revokeAllAccess()`, y `anonymize()` solo le pasa el motivo
   (`anonymized`) — `RGPD-06` ampliada (§5) sin un segundo sitio.
6. **Booking ↔ Identity, otra vez**: la ficha compone reservas y dinero (Booking) con waiver, menores y
   carné (Identity). `Booking\Contracts\CustomerReservations::upcomingFor()` devuelve
   `UpcomingReservation{date, timeWindow, productName}` — **sin cantidad, sin dinero, sin pedido ni ítem**:
   no sirve para la puerta. ▶ Contrato NUEVO `Booking\Contracts\GateReservations` (A·3), con el dinero
   por **`OrderLedger::forReservation()`** (existe) y el reader **entra en
   `LedgerSingleSourceTest::SURFACES`** («si nace una nueva, entra aquí», §4.7).
7. **El permiso propio de §4.6 cuesta lo que costó medir en `menores-a-cargo.md` §9.10.1·3**: seeder +
   `PermissionCatalog::GROUPS` + migración idempotente para instalaciones desplegadas (plantilla
   `2026_05_28_000006_…`) + etiqueta es **y zh_CN** + el test de paridad. Aquí SÍ se paga: la spec lo
   exige y «ver la ficha completa» no es «¿está registrado?».
8. **`AuditLogger::assertKnownAction()` lanza fuera de producción** con una acción que no esté en
   `AuditLog::ACTIONS`; `CRITICAL_ACTIONS` ya lleva `registrations.validate_rate_limited` («abuso») y
   las críticas van al visor de incidencias y al correo del operador (§4.6·4): el limitador nuevo se
   audita con su acción propia y **entra en `CRITICAL_ACTIONS`** para heredar el aviso.
9. **Los ajustes de puerta viven en `Settings::KEYS` (grupo `puerta`) y en la sección «Puerta»**, con
   `PuertaSettings` como lector defensivo (valor inválido → por defecto). Los tres nuevos siguen ese
   patrón exacto.
10. **`GET /me/*` valida contra `openapi/v1.yaml` con Spectator** (`ApiTestCase::assertValidResponse`):
    los dos endpoints del carné llevan sus rutas y su esquema en el contrato ANTES que el código.
11. **`OrderConfirmation::toMail()` es un `MailMessage` de líneas** (sin plantilla propia): el QR viaja
    como **adjunto PNG** (`attachData`) con una línea que lo dice; incrustarlo *inline* (CID) exige un
    Mailable con plantilla y queda para el ojo del owner sobre clientes reales (§4.10, no medido).
12. **`CheckoutLinesReader` ordena por `id` los ítems principales** y `DependentAssigner::forOrderItems()`
    + `WaiverStatus::forDependents()` ya dan, por ítem, los menores con su firma en dos consultas (tanda
    5): la puerta los reutiliza **sin el nombre** (A·6).
13. **`docs-check` cuenta modelos y migraciones**: dos modelos (carné, visita) y tres migraciones (dos
    tablas, un permiso) → **34 → 36 · 82 → 85** en las docs que los citan.

### 9.2 Decisiones `[DECIDIDO agente]` — reversibles, cada una con su porqué

- **A·1 · El carné**: tabla `customer_cards` (`user_id` FK CASCADE · `token` con cast `encrypted` ·
  `token_hash` sha256 ÚNICO · `issued_at` · `revoked_at` · `revoked_reason` ∈ `rotated | revoked |
  anonymized`), **uno ACTIVO por titular** —lo garantiza `Identity\Services\CustomerCards` bajo el lock
  de la fila del titular, no un índice parcial (no es portable)—; alias morph `customer_card`. **Formato
  `#208`**: `JW` + 17 de Crockford Base32 (sin `I L O U`) + 1 de control (suma ponderada por posición
  **módulo 31**, primo, en el mismo alfabeto — ⚠️ se escribió primero «mod 32» y un test aleatorio cayó
  1 de ~8 veces: con módulo 32 las posiciones pares comparten factor y una sustitución ahí puede pasar;
  con 31 se caza toda sustitución simple y toda transposición adyacente salvo el par `0↔Z`, probado
  de forma EXHAUSTIVA, no por azar) = **20 caracteres** (`2⁸⁵`, versión 2 del QR con ECC H, medido). `CardToken` es un
  objeto de valor: `generate()`, `normalize()` (mayúsculas; `I/L → 1`, `O → 0`: lo que Crockford permite
  dictar), `isWellFormed()` (longitud, alfabeto y control — un escaneo defectuoso falla en el navegador,
  §4.3). **`CustomerCard::plainToken()` captura `DecryptException` y devuelve `null`** (§8.1): ninguna
  superficie lee el atributo directo, y hay test con la clave rotada.
- **A·2 · Revocación (`RGPD-06`)**: `revokeAllAccess()` revoca los carnés activos (motivo `revoked`);
  `anonymize()` también (`anonymized`). **`revokeOtherAccess()` NO**: un cambio de contraseña no debe
  matar el carné impreso en casa —escanear no autentica (§4.2)—; rotarlo es un acto explícito del
  titular. `customer_cards` entra en `AccessRevocationTest::CREDENTIAL_TABLES`.
- **A·3 · La ficha es un servicio con presupuesto**: `Identity\Services\GateProfile::for(User, hoy)` →
  `GateProfileData` (DTO de solo lectura) con: titular (nombre), waiver (`WaiverStatus::for`), carné
  (activo / revocado / sin carné), **HOY** (reservas del día por `GateReservations`, con tipo, franja,
  cantidad, complementos, `paidOnlineCents`, `pendingGateCents`, cuándo se hizo y cuándo se pagó, y los
  menores asignados a esa línea como **edad + estado de la exención**), **VENTANA ±N** (mismo DTO, sin
  hoy), **menores a cargo** (activos: edad + exención, NUNCA el nombre), y `visitRegisteredToday`.
  Presupuesto constante, con test. `Booking\Contracts\GateReservations::forHolder(userId, from, to)`
  devuelve `GateReservation{orderId, orderCode, orderItemId, date, timeWindow, productName, isEntry,
  quantity, addons, paidOnlineCents, pendingGateCents, paidAt, createdAt}`; lo implementa
  `Booking\Services\GateReservationsReader` con `OrderLedger::forReservation()`, y entra en
  `LedgerSingleSourceTest::SURFACES`. Identity → `Booking\Contracts` está en `ALLOWED`: cero flechas.
- **A·4 · La visita es un HECHO con tabla**: `customer_visits` (`user_id` FK CASCADE · `visited_on` DATE
  · `registered_by` FK `users` nullOnDelete · `created_at`; ÚNICO `(user_id, visited_on)`). Es el hecho
  observable que `lealtad-jumppoints.md` §8.1 no tenía. `Identity\Services\GateVisits::register(User
  $customer, User $by, hoy)` es **idempotente por (cliente, día)** —`insertOrIgnore` sobre el único— y
  audita `puerta.visit_registered` (target el cliente, `by` el operador) **solo cuando escribe**. Un
  botón explícito, jamás un efecto de abrir la ficha (§8.3).
- **A·5 · Quién ve qué**: permiso nuevo **`puerta.profile`** («Ver la ficha de puerta del cliente y
  registrar su visita»; staff por defecto: es la operativa diaria). Sin él la pantalla es la de hoy (los
  estados). Con él, **tanto el escaneo como la búsqueda tecleada abren la ficha** (`[DECIDIDO owner]`,
  §4.6) con los cuatro contrapesos: (1) **dos limitadores por empleado** —escaneo: el de hoy,
  `puerta.validate_rate_limit_per_minute`; tecleado: **`puerta.lookup_rate_limit_per_hour`**, por
  defecto 30—; (2) la coincidencia exacta se conserva (mismo código); (3) se audita la **DIVULGACIÓN**
  (`puerta.profile_viewed`, target el cliente) además de la búsqueda (`registrations.validated` con hash,
  como hoy; y `puerta.card_scanned` con el hash del token); (4) el rechazo del limitador tecleado se
  audita como `puerta.lookup_rate_limited`, **crítica** (aviso al operador heredado). El escaneo entra por
  el MISMO input (el lector es *keyboard wedge*): `detectInputType()` gana `card` cuando
  `CardToken::isWellFormed(normalize(raw))`.
- **A·6 · La ficha caduca en el SERVIDOR** (`SEC-04` al tiempo): el resultado lleva `expires_at`
  (`now + puerta.profile_ttl_minutes`, por defecto 5) y **cada método público y `render()` pasan por
  `ensureFresh()`**, que descarta la ficha vencida antes de hacer nada. Los dos relojes del navegador
  (velo a 60 s, cierre al TTL) son Alpine y llaman a `clear()`; cualquier interacción los reinicia. Lo
  verificable por mutación: **recargar tras el plazo no la resucita**, con o sin temporizador.
- **A·7 · «Nunca el nombre de un menor» es ESTRUCTURAL, no una omisión**: el DTO de la ficha no tiene
  campo para él, el compositor nunca lo lee, y una guarda renderiza la pantalla con un nombre único y
  afirma que no está ni en el HTML ni en el estado público de Livewire — **con mutación**.
- **A·8 · El carné nace cuando hace falta** (`CustomerCards::ensureFor()`): al componer el correo de
  confirmación y en `GET /me/card`. **`POST /me/card/rotate`** lo rota (201 con el nuevo; el viejo muere
  en el acto, §4.5). El correo lleva el PNG adjunto (`carne-qr.png`) y una línea que lo nombra. La
  rotación desde el PANEL y una zona «Mi carné» en el cajón no entran: quedan escritas en «Lo que queda».
- **A·9 · Ajustes** (`Settings` → «Puerta», `PuertaSettings` defensivo): `puerta.lookup_rate_limit_per_hour`
  (30) · `puerta.profile_ttl_minutes` (5) · `puerta.window_days` (1, la ventana ±N de §4.6·5).
- **A·10 · Auditoría** (`AuditLog::ACTIONS`): `cards.issued` · `cards.rotated` · `cards.revoked` (target
  el titular; `reason`; nunca el token) · `puerta.card_scanned` (sensible: hash del token, target el
  titular si existe) · `puerta.profile_viewed` · `puerta.visit_registered` · `puerta.lookup_rate_limited`
  (crítica). Con etiqueta en el visor (es/zh_CN).

### 9.3 Las unidades, en orden — cada una verde y EMPUJADA antes de la siguiente

| U | Qué | Ficheros | Red |
|---|---|---|---|
| **A1** | El CARNÉ y la VISITA en el dominio: migraciones (`customer_cards`, `customer_visits`, el permiso), modelos, `CardToken`, `CustomerCards`, `GateVisits`, `PuertaSettings` +3, `revokeAllAccess()`/`anonymize()`, `AuditLog::ACTIONS` +7, `QrCode::png()`, permiso + catálogo + i18n | `database/migrations/**` · `app/Domain/Identity/**` · `app/Domain/Platform/**` · `database/seeders/PermissionSeeder.php` · `lang/{es,zh_CN}/admin.php` | `CustomerCardTest` (formato/control/normalización · uno activo bajo el lock, 16 procesos → 1 · rotar mata el viejo · `revokeAllAccess()` y `anonymize()` revocan, con mutación · `plainToken()` con `APP_KEY` rotada → `null`, sin excepción · `findByToken()` distingue revocado) · `GateVisitsTest` (idempotente por día, auditoría solo al escribir) · `AccessRevocationTest` con la tabla · paridad de permisos |
| **A2** | La FICHA: `Booking\Contracts\GateReservations` + `GateReservation` + reader (ledger) · `GateProfile` + `GateProfileData` · superficie en `LedgerSingleSourceTest` | `app/Domain/Booking/Contracts/**` · `app/Domain/Booking/Services/GateReservationsReader.php` (futuro) · `app/Domain/Identity/Services/GateProfile*.php` | `GateProfileTest` (hoy vs. ventana vs. nada · dinero del ledger · menores por edad y exención, sin nombre · presupuesto constante · `ModuleContractsTest` doble) |
| **A3** | La PANTALLA: `ValidarRegistro` (+`card`, dos limitadores, ficha, `ensureFresh()`, `registerVisit()`), la vista, los rótulos, Ajustes | `app/Livewire/Admin/Puerta/**` · `resources/views/livewire/admin/puerta/**` · `lang/es/admin.php` · `app/Filament/Pages/Settings.php` | `ValidarRegistroProfileTest` (permiso · escaneo abre la ficha · tecleado abre la ficha con su limitador y su auditoría crítica · carné revocado · caducidad en servidor con mutación · visita idempotente · **nunca el nombre**, con mutación) + los 23 de hoy en verde |
| **A4** | El CORREO y la API: `OrderConfirmation` con el PNG · `GET /me/card` · `POST /me/card/rotate` · contrato | `app/Notifications/OrderConfirmation.php` · `app/Http/Controllers/Api/V1/MeCardController.php` (futuro) · `openapi/v1.yaml` · `routes/api.php` | `MeCardTest` (contrato · rota y mata el viejo · `no-store`) · `OrderConfirmationCardTest` (adjunto PNG, el carné se emite si no existe) |
| **A5** | Verificación: suite · Pint · docs-check (36 · 85) · mutaciones · pasada headless de la puerta con capturas · docs | — | esta sección, §9.4 |
