# [SPEC] El TELÉFONO del cliente — de dónde sale y quién lo pide

> Estado: 🟦 revisada (adversarial de 7 lentes, 2026-09-06) · Última actualización: 2026-09-06 ·
> Decisión asociada: `DECISIONES #440` 
> Banda de numeración reservada para este carril: 440–449.
>
> Encargo del owner (2026-09-06), literal: *«1. Al registrarse un usuario con google no tenemos su
> numero, hay manera de intentar conseguir con google? 2. Si no tiene numero, en la creacion de
> pedido manual desde el panel de admin debemos pedirlo al operador para que se lo pida al cliente
> y escribirlo.»*
>
> ❗❗❗ **RESPUESTA A LA PRIMERA PREGUNTA: SÍ SE PUEDE, Y SE DESCARTA.** `[DECIDIDO owner,
> 2026-09-06]` tras ver el coste medido (§3). **Esta spec construye solo el MOSTRADOR** (§4). El §3
> se conserva entero porque *la opción no elegida es información*: mide lo que Google da y lo que
> cuesta, para que nadie lo vuelva a levantar sin los números.

---

## 1. Contexto y problema

### 1.1 · El teléfono es OPERATIVO, no decorativo

| Quién | Dónde | Para qué |
|---|---|---|
| Hoja de sala | `Booking\Services\ReservationSlip:354` (`customerPhone()`) | el monitor tiene a quién llamar |
| Resumen del día | `Booking\Services\DailyReservationsSummary:137` | la lista con la que se abre el turno |
| Tabla de pedidos | `Filament/.../OrdersTable:53-54` | identifica al cliente **cuando no hay correo** |
| Puerta | búsqueda del cliente | se le identifica por su número |
| Deduplicación de mostrador | `CustomerRegistrar::customersMatchingPhone()` | evita crear dos veces al mismo |

`[owner]` en el docblock de `Identity\Services\CheckoutDuties`: *«imprescindible para las reservas»*.

⚠️ **Ninguno ROMPE con `null`: todos degradan.** Eso importa porque separa *«esto es urgente porque
se cae»* de *«esto es operativo y molesta»*, que es lo que de verdad es.

### 1.2 · Hoy una cuenta puede nacer sin él — y es a propósito

`GoogleSignup::register()` crea la cuenta **sin teléfono**, con el porqué escrito en el código
(`GoogleSignup:75-78`, `[DECIDIDO owner, 2026-09-02]`, `#350`): lo pide el checkout, mediante
`CheckoutDuties::pendingFor()` / `settle()` (`#349`). **Ese mecanismo funciona y no se toca.**

### 1.3 · ❗❗ El hueco del MOSTRADOR, medido

1. **Al CREAR un cliente el teléfono ya es obligatorio** (`CreateManualOrderPage:514-520`): es el
   identificador cuando no hay correo. Ese camino está bien.
2. **Al ELEGIR uno existente no se comprueba nada**, y `customerDisplay()` (`:1253-1259`) pinta el
   correo cuando lo hay → **un cliente sin teléfono se ve igual que uno completo**.
3. ⚠️⚠️ **No hay NINGUNA forma en el panel de ponérselo después.** `UserResource::getPages()`
   (`:85-88`) registra solo `index` y `view` —**no existe `EditUser`**— y sus cinco acciones son
   carné, prueba de waiver, roles, reset de contraseña y anonimizar. Además el recurso es
   **admin-only** (`users.manage`, `:93-95`), así que el `staff` de mostrador **no tiene ninguna
   vía**. Ficha en `DEUDA.md`.

⚠️ **Las cifras, corregidas por la revisión**: en la BD local son **3 de 22 cuentas de CLIENTE** sin
teléfono (**2 de ellas de Google**); el «5 de 25» de la primera versión mezclaba admin y staff. Hay
una instancia real: el pedido pagado `R-OXQJWM` es de un cliente con identidad de Google y sin
teléfono.

*El hueco no es que el asistente no lo pida: es que el panel no tiene por dónde arreglarlo.*

---

## 2. Objetivo

1. Un operador que elige un cliente sin teléfono **lo ve, y el asistente le pide que lo escriba**.
2. Lo escrito **se guarda en la cuenta**, no solo en el pedido.
3. Nunca se **pisa** un teléfono existente.
4. ⚠️ **Una venta de mostrador NUNCA se bloquea por falta de teléfono** (`[DECIDIDO owner]`, §4.4).

**Fuera de alcance:** el checkout (no se toca); el carril de Google (§3, descartado); `EditUser`
(ficha de deuda).

⚠️ **El criterio «no se puede llegar al carrito sin teléfono» de la primera versión SE RETIRA**: la
revisión midió que es incompatible con la decisión de no bloquear la venta, y afirmarlo sin
cumplirlo es peor que no afirmarlo.

---

## 3. La opción DESCARTADA: conseguirlo de Google

`[DECIDIDO owner, 2026-09-06]` **NO se construye.** Lo medido, para que no se relitigue:

### 3.1 · Lo que Google da y no da (documentación oficial, verificada)

| Pregunta | Respuesta | Fuente |
|---|---|---|
| ¿El `id_token` trae teléfono? | **No.** Ni un claim. Los ámbitos OIDC que Google soporta son `openid`, `email` y `profile`. | [OpenID Connect · Google](https://developers.google.com/identity/openid-connect/openid-connect) |
| ¿Qué ámbito lo daría? | `user.phonenumbers.read`, vía People API `people.get` con `personFields=phoneNumbers`. | [people.get](https://developers.google.com/people/api/rest/v1/people/get) |
| ¿Cuánto cuesta? | Ámbito **sensible**: verificación con justificación y **vídeo**, 3-5 días hábiles. | [Sensitive scope verification](https://developers.google.com/identity/protocols/oauth2/production-readiness/sensitive-scope-verification) |
| ¿Y mientras? | **Pantalla de «app no verificada» y tope de 100 usuarios nuevos.** | [Unverified apps](https://support.google.com/cloud/answer/7454865) |
| ¿Estará el dato? | Solo si esa persona lo añadió a **su perfil**. El de recuperación **no se expone**. | ibíd. |

### 3.2 · ❗❗ Y el mecanismo no era implementable como se escribió

La primera versión decía «el ámbito viaja solo en el camino de alta». **Falso, y la revisión lo
midió**: hay **UNA sola** ida a Google (la ruta `auth.google.redirect`), y `LoginForm.vue` y
`RegisterForm.vue` pintan **el mismo botón con la misma URL** (es `#350`), el ámbito es una
**constante** (`GoogleOAuth:58`) que `authorizationUrl()` no recibe por parámetro, y alta-vs-entrada
solo se sabe **después del canje**.

⚠️⚠️ **Y el atajo aparente no funciona y no avisa**: `GoogleAuthSession::consumeChallenge():129`
colapsa **en silencio** toda intención que no sea `INTENT_LINK` a `INTENT_ENTER`.

⚠️ **Segunda mitad**: el `access_token` tampoco tenía por dónde llegar — `profileFromCode()` solo
extrae `id_token` (`:133`) y `SocialProfile` es `final readonly` con cinco props.

### 3.3 · Las tres salidas reales, con su coste (por si alguna vez se retoma)

- **(i)** Segunda ruta de ida solo para registro → parte el botón que `#350` unificó, y quien pulsa
  «Entrar» sin tener cuenta se registra **sin que se le pida nunca** el teléfono.
- **(ii)** Segundo salto dentro del alta (autorización incremental) → la única que cubre las dos
  puertas y respeta «solo en el alta»; cuesta un viaje más a Google **en mitad del registro**, que
  es lo contrario del encargo que abrió `auth-con-google.md` (*«0 fricción al registrarse»*).
- **(iii)** Pedirlo siempre → pone la pantalla de ámbito sensible delante de **cada** cliente actual
  de `playjump.es`.

### 3.4 · Por qué se descarta

`[owner]`, con la comparación delante: las tres salidas cuestan verificación de semanas, código
nuevo en la raíz de confianza del login, y **el dato puede venir vacío igualmente**. La alternativa
—un campo en la pantalla de alta que ya existe— cuesta una tarde y lo obtiene siempre… **y también
se descarta**, porque revertiría la T8·c de `#350` (de cuatro días antes) y **el checkout ya lo pide**.

▶ **Conclusión del owner**: quien se registre con Google sigue dando el teléfono en el checkout
(`#349`), y **lo que se arregla es el mostrador**, que es donde hoy no hay ninguna vía.

---

## 4. Diseño elegido — el pedido manual pide el teléfono

### 4.1 · Quién decide que falta

**`CheckoutDuties::pendingFor($user)['phone']`, y no una segunda copia.** Su docblock dice por qué
existe: la pregunta se respondía en dos sitios *«cada uno con su `trim($user->phone) === ''`»*, y
divergir aquí significa **pedir un campo que el servidor no pide, o al revés**.

⚠️ **Y hay una TERCERA redacción ya en el árbol**, que la revisión encontró: `customerDisplay()`
(`:1255`) usa `filled($u->email)` / comparación con cadena vacía. Con un teléfono **de espacios**,
el rótulo del cliente y el aviso del paso 1 se contradicen. `customerDisplay()` pasa a preguntar a
la misma autoridad.

### 4.2 · La conducta del paso 1

- Al elegir un cliente al que le falta, **`advanceAfterChoice()` no avanza** (`:437-442`): el paso 1
  se queda, con el campo y el aviso de que se lo pida al cliente.
- Al **crear** uno nuevo no cambia nada: ahí ya era obligatorio.

### 4.3 · ❗❗ Dónde van las puertas — son CUATRO, no dos

La primera versión las ponía en `visible()` + `canAdvance()`. **La revisión midió que el paso 1 no
es precondición de nada aguas abajo**, con tres bypasses reproducidos:

- `public int $step` (`:145`) — la lección de `#464` (`$calMonth`) literal;
- ⚠️⚠️ **`addLineToCart()` (`:777`) es público y NO mira el paso del cliente**: desde
  `STEP_CUSTOMER`, sin tocar `step`, la línea aterriza en el carrito y **el propio método mueve
  `step` a `STEP_CART`** (`:885`);
- `addMoreProducts()` (`:385`) fija `$step` sin guarda.

▶ **Las cuatro capas y para qué sirve cada una:**

| Capa | Qué hace | Bloquea la venta |
|---|---|---|
| `visible()` | pinta el campo | no |
| `canAdvance()` | usabilidad: no deja pasar al paso 2 | no |
| `addLineToCart()` | **la línea no entra al carrito** sin teléfono | no |
| `create()` | **avisa y deja constancia** | ⚠️ **NO** — ver §4.4 |

⚠️ El actor realista no es un atacante: es **una pestaña vieja, un estado desincronizado o un
refactor** que llame a `addLineToCart()` desde otra puerta.

### 4.4 · ⚠️⚠️ Una venta NUNCA se bloquea por el teléfono

`[DECIDIDO owner, 2026-09-06]`, con la consecuencia delante: *avisar y dejar constancia, pero
cobrar*.

Con el cliente delante y el carrito montado, negarse a cobrar por un número es peor que vender sin
él. Así que `create()` **no rechaza**: si llega sin teléfono —lo que solo puede pasar por uno de los
bypasses de §4.3— crea el pedido, **avisa al operador** y **lo deja en la auditoría**.

⚠️ **Esto es deliberado y va escrito en el código**, o el siguiente agente «terminará el trabajo»
convirtiéndolo en un rechazo: sería **lo primero en la historia del panel capaz de tumbar una venta
de mostrador por un teléfono**.

### 4.5 · Quién escribe

⚠️⚠️ **No el componente de Filament.** `ApiBoundariesTest` ya puso en rojo exactamente esto en el
checkout: el `save()` del teléfono era *«una escritura de dominio en la capa HTTP»*, y de ahí nació
`CheckoutDuties`. Se reutiliza **su `settle()`**, que además ya cumple *«solo escribe lo que de
verdad faltaba»* — nunca pisa.

### 4.6 · El formato

⚠️⚠️ **`CustomerRegistrar::normalizePhone()` NO valida**: su propio docblock dice que es *«para
COMPARAR, no para mostrar»* y **quita el `+`**. La revisión lo midió.

Aquí el número lo teclea **el operador con el cliente delante**, así que no hace falta inventar
validación: se guarda **tal cual, recortado**, igual que hace hoy el alta de mostrador. `normalizePhone()`
se usa **solo** para deduplicar, que es para lo que existe.

---

## 5. Impacto en invariantes

| Invariante | Impacto |
|---|---|
| `RGPD-01` | **Ninguno.** `anonymize()` ya pone `phone` a `null`. |
| `SEC-04` | **Aplica.** Las puertas de §4.3 re-validan en el servidor; el `visible()` no es defensa. |
| `PAY-*` / `AFORO-*` | **Ninguno**, y **verificado leyendo el hook**: ni `CreateManualOrderPage` ni `CheckoutDuties` están en el `CRITICAL_RE`, así que esto **NO** dispara `VERIFY_CONC=1`. ⚠️ La primera versión de esta spec afirmaba lo contrario. |

---

## 6. Plan de verificación empírica

`CreateManualOrderCustomerPhoneTest`:

1. Elegir un cliente **sin** teléfono **no avanza**; **con** teléfono **sí** (control).
2. Escribirlo avanza **y queda guardado en la cuenta** (no solo en el pedido).
3. ⚠️ **El caso del bypass mide el MOVIMIENTO y el EFECTO, no el predicado** (la lección de `#464`):
   `->set('step', STEP_PRODUCT)` + `addLineToCart()` → **la línea no entra en el carrito**; y
   `addLineToCart()` por sí solo desde el paso 1, tampoco (control).
4. ⚠️ **`create()` SÍ crea el pedido sin teléfono** —es la conducta decidida— **y deja el aviso y la
   fila de auditoría**. Este caso es el que impide que alguien lo convierta en rechazo.
5. Un teléfono **de espacios** no cuenta como teléfono, y el rótulo del cliente dice lo mismo que el
   aviso (§4.1).
6. Una cuenta que ya tiene teléfono **no se lo pisa**.

⚠️⚠️ **Lo que la revisión retiró del plan anterior**: *«el atajo por `wire:click` al paso 2 se
rechaza en el servidor»* era **verde eterno** — `goToStep(2)` desde el paso 1 ya se rechaza siempre,
con puerta o sin ella. *La cláusula escrita para vigilar el atajo vigilaba la única puerta que nunca
fue el agujero.*

**Mutación**: `scripts/mutar-telefono-mostrador.sh`, con CONTROL y por **código de salida** (`#317`).

---

## 7. Al desplegar

- **Cero migraciones.** Nada que rellenar.
- ⚠️ **NO dispara el gate de concurrencia**: ninguna de las dos piezas está en el `CRITICAL_RE`
  (verificado leyendo `.githooks/pre-push`), y es correcto — esto no es dinero ni aforo.
- **Dos acciones de auditoría nuevas** (`orders.manual_customer_phone_added` y
  `orders.manual_created_without_phone`), catalogadas y con etiqueta: lo exige `AuditActionCatalogTest`,
  que las puso en rojo al escribirlas sin registrar.
- ⚠️ **No cierra el hueco de `EditUser`**: una cuenta sin teléfono que no vuelva por el mostrador
  sigue sin vía. Ficha en `DEUDA.md`.

---

## 8. Revisión y decisión

- **2026-09-06** · Contexto medido; decisiones del owner en §3.4 (descartar Google) y §4.4 (no
  bloquear la venta).
- **2026-09-06** · **Revisión adversarial de 7 lentes** (29 agentes). Cambió: el carril de Google se
  descarta con su medición, las puertas pasan de 2 a 4, se retira un criterio de éxito incumplible,
  se corrige el uso de `normalizePhone()`, las cifras de §1.3 y dos casos vacuos del plan.
- ▶ **2026-09-06 · EJECUTADO Y EN EL ÁRBOL.** Suite **4.368** (27.127 aserciones) ·
  `CreateManualOrderCustomerPhoneTest` **10 casos** · `scripts/mutar-telefono-mostrador.sh`
  **11/11 mutaciones muerden** · Pint ✓ · `docs-check` ✓.

### 8.1 · Lo que enseñó la ejecución

⚠️⚠️ **DOS casos del plan nacieron sin morder, y lo dijo la MUTACIÓN, no la lectura:**

1. *«retiene aunque el operador ya lo haya escrito»* no mordía porque el caso llamaba a `next()`,
   **que guarda el teléfono antes de comprobar nada**. El defecto que esa mitad de `canAdvance()`
   evita es **el botón muerto** —escribes el teléfono y «Siguiente» sigue deshabilitado, así que no
   hay forma de llegar a `next()`—, y eso solo se ve midiendo `canAdvance()` **antes** de avanzar.
2. *«`recordPhone()` pisa un teléfono existente»* no mordía porque un cliente **con** teléfono
   **auto-avanza al elegirlo**, así que conduciendo el asistente `saveMissingPhone()` ni siquiera
   corre: el caso **pasaba por el motivo equivocado**. La regla vive en `CheckoutDuties` y ahí se
   mide.

▶ *Los dos son la misma familia: un caso que ejercita el camino feliz no vigila la guarda que hace
falta cuando el camino feliz no ocurre.*

⚠️ **Y una afirmación de esta spec era falsa**: `CreateManualOrderPage` **no** está en el
`CRITICAL_RE` (§5, corregido leyendo el hook).

⚠️ **`AuditActionCatalogTest` puso en rojo las dos acciones nuevas** antes de que llegaran a ninguna
parte: en este repo una acción de auditoría sin catalogar **lanza**. Es la guarda haciendo lo que
existe para hacer.
