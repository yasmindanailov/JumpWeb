# [SPEC] Menores a cargo

> Estado: diseño 🟦 en revisión (pendiente de revisión adversarial por otro agente) ·
> Última actualización: 2026-08-24 ·
> Verificado contra código: 2026-08-24 (ModuleBoundariesTest, machine.js, stores/selection.js, cart.js) ·
> Decisión asociada: `DECISIONES #142` ·
> Se invalida si: cambia el modelo de waiver de `waiver-probatorio.md`, o el embudo gana un paso.

Subsistema **C** de la visión de Fase 6. Va **después** de `waiver-probatorio.md`, porque el waiver de
un menor es el mismo mecanismo aplicado a otro sujeto.

---

## 1. Contexto y problema

Solo se registran adultos. Quien trae a un menor tiene que **hacerse responsable de él por escrito**,
y hoy no hay dónde: la web no conoce a los menores, solo los ve como texto libre dentro de un pedido.

**Lo que hay hoy, y por qué no sirve:** los datos de menores viven en `order_items.guest_data` y
`order_items.event_data` — JSON, sin FK, y **con alergias**, que son datos de salud (art. 9). Están
atados a **una reserva concreta** y se vacían al anonimizar. No son una relación de responsabilidad:
son el contenido de una fiesta.

## 2. Objetivo

Que un titular pueda declarar de quién es responsable, firmar el waiver por cada uno, y que la puerta
lo vea **sin conocer sus nombres**.

**Criterios de éxito medibles:**
- El titular añade y retira menores desde el cajón, con tope de servidor.
- Cada menor tiene su firma de waiver, con el mismo mecanismo del subsistema B.
- El escaneo en puerta devuelve **edad y estado del waiver, jamás el nombre** — mutación obligatoria.
- La edad **no existe como columna** en ninguna tabla.
- Retirar un menor con historial **no borra nada**.

**FUERA de alcance:**
- ⚠️ **Grupos escolares** — retirado por decisión del owner en `DECISIONES #142`: un profesor no
  ostenta la patria potestad y el registro que produciría puede no valer lo que aparenta. Se llevó
  cinco piezas: alta manual por el admin, estado «pendiente de waiver» de origen externo, permiso
  nuevo, correo de aviso y aceptación en bloque.
- Fusionar esto con `guest_data`/`event_data` del post-form. Se diseña **al lado**, no encima (§4.6).

## 3. Opciones consideradas

**A · Reutilizar `guest_data`.** DESCARTADA: ese JSON pertenece a una reserva, se vacía al anonimizar y
no puede colgar de él una firma que debe sobrevivir. `sistemas/POSTFORM-INVITADOS.md` declara además
cuál sería la condición para convertirlo en tabla, y no es ésta.

**B · Entidad propia bajo el titular (ELEGIDA).** Persistente, con su firma y su ciclo de vida propio.

**C · Registro global de menores compartido entre cuentas.** DESCARTADA: dos adultos con custodia
compartida **deben** poder declararse responsables por separado, y un registro global convertiría eso
en un conflicto de titularidad que no aporta nada.

## 4. Diseño elegido

### 4.1 El nombre de la entidad: `Dependent`, no `Minor`

⚠️ **Un menor añadido con 5 años tiene 18 dentro de trece.** Cuando eso pasa, el waiver del adulto
deja de cubrirlo, la pantalla de puerta diría «1 **menor** a cargo de 18 años» —una contradicción— y
**nadie lo va a notar**, porque ocurre en silencio y años después.

La fila **sobrevive a la minoría de edad**, así que la entidad se llama `Dependent` («persona a
cargo») con `isMinor()` **derivado**. En la interfaz se dice «menores a cargo», que es lo que entiende
el cliente. Es la misma doctrina que `ParkRule` → `VenueRule`: se generaliza el nombre que ata.

▶ **Y el día que cumple 18**: la lista lo marca («ya no está cubierto») y la pantalla de puerta
también. **No lo borra nadie automáticamente** — borrar datos por un cumpleaños es peor que enseñarlos
marcados.

### 4.2 El modelo

`user_id · name · born_on (date) · removed_at (nullable) · timestamps`

**Y nada más.** Cada columna que se añada aquí es dato personal de un menor.

- ⚠️ **La edad se DERIVA, nunca se persiste.** Cambia sola cada año; guardarla es una mentira con
  fecha de caducidad.
- ⚠️ **El nombre no es dato operativo, es una etiqueta del titular.** La puerta no lo enseña y el
  único que necesita distinguirlos es el propio adulto al asignar una entrada. La interfaz debe decir
  que puede poner **el nombre que use en casa**: menos PII sin perder nada.
  ▶ Contrapunto honesto: eso debilita algo el PDF del waiver. Aguanta —«Lucas, 12/03/2017» es
  identificable en la práctica— **siempre que el PDF diga que el dato lo declaró el titular y no está
  verificado** (`waiver-probatorio.md` §4.5).

### 4.3 El waiver de un menor

⚠️ **Lo firma el ADULTO, no el menor.** Lo que se registra es «X, como responsable de Y, aceptó la
versión V el día D» → la firma cuelga del **par (titular, dependiente)**, que es exactamente lo que
cubre el campo `sujeto` del registro de firma (`waiver-probatorio.md` §4.3). **Sin mecanismo nuevo.**

Consecuencia que hay que aceptar y está bien: **el mismo niño puede existir dos veces**, una por cada
progenitor con cuenta. Es correcto — cada uno se hace responsable por su cuenta. Por eso esto **no es
un registro de niños**, es «personas a cargo de esta cuenta», y el nombre importa para no confundirse.

### 4.4 Retirar: desvincular, no borrar

⚠️ **No se puede borrar la fila** si detrás hay un waiver firmado (que se conserva bajo régimen
restringido) o una reserva pasada que le asignó una entrada.

Es la misma tensión que el repo ya resolvió una vez: `DELETE /me` **no borra al usuario**, llama a
`anonymize()`, «porque la FK es `RESTRICT` y la factura tiene que seguir vinculada» (`RGPD-01`).

**Regla, derivada y no arbitraria:**
- Con waiver firmado **o** con reservas que lo referencian → `removed_at`. Desaparece de la lista del
  titular y de la pantalla de puerta; el waiver y el histórico siguen apuntando ahí.
- Sin waiver y sin ninguna referencia → **se borra de verdad**. No hay nada que conservar; se añadió
  por error y se quita.

▶ Si lo retira y lo vuelve a añadir son **dos filas**, y la segunda **necesita su propia firma**. Es lo
correcto: volver a hacerse responsable es un acto nuevo.

### 4.5 El tope: 20, y en el servidor

«Cuantos quiera» no puede ser literal: es una superficie de escritura barata que crea PII de terceros,
y una cuenta con miles de dependientes revienta la pantalla de puerta y el contexto de cuenta.

**Tope 20 por cuenta, configurable por instalación** (ajuste, no constante), **aplicado en servidor**.
Misma doctrina que `PAY-12`: el tope de líneas del carrito es invariante de servidor, no de interfaz.
Y **limitador de creación en el servicio de dominio**, no en la ruta — que es donde este proyecto los
pone siempre (`SelfSignup`, `PasswordLogin`, `AccountCredentials`).

### 4.6 Dónde vive: Identity, y la flecha que la arquitectura PROHÍBE

`Dependent` es de **Identity**. Eso es directo. Lo que no lo es:

⚠️ Si `order_items` guardara «esta entrada es para el dependiente X», eso es una flecha
**Booking → Identity**, y el grafo de `ModuleBoundariesTest` **no la permite**: Booking solo ve
`Platform` y `Payments\Contracts`, y únicamente `User` está exento por kernel compartido. Una FK a
dependientes en `order_items` **pone el arch-test en rojo, y con razón**.

**La salida ya la inventó este repo.** `specs/modulos-dominio.md` §4.bis, hallazgo 4: *«Los contratos
de Booking reciben `int $userId`, no `User`… así Booking no importa un modelo de Identity y el grafo
queda sin esa flecha.»*

Aplicado al revés: **la asignación la posee Identity y referencia el ítem por su id ENTERO**. Identity
sí puede mirar a `Booking\Contracts`. Cero flechas nuevas, cero excepciones en el arch-test.

### 4.7 Asignar una entrada a un menor

**Dónde cae, medido.** La cantidad no tiene paso propio: se elige dentro de `TimeStep.vue`, que su
propio docblock describe como *«el paso más denso del embudo y el que más dinero enseña»*. Y el mapa
real de `machine.js` es `CATALOG(1) → DATE(2) → TIME(3) → CART(4) → IDENTIFY(5)`.

⚠️ **Identificarse es el paso 5.** Cuando se elige la cantidad **no hay sesión garantizada** — el
embudo deja mirar, elegir y llenar la cesta como invitado. Sin sesión no hay menores que listar.

**Dos puertas, un solo módulo, CERO pasos nuevos:**
- **Con sesión** → el selector aparece en el paso 3, al elegir la cantidad.
- **Sin sesión** → aparece en el paso 5, justo después de identificarse, que es el primer instante en
  que el sistema sabe quién es. Esa pantalla ya existe.

▶ `FUNNEL_TRANSITIONS` **no se toca**. Es el grafo cerrado con guarda que dejó `#119`, y añadir un paso
sería el cambio caro.

**La forma del dato**: la línea tiene `quantity`, así que la asignación es **una lista de hasta
`quantity` huecos**, cada uno con un dependiente o vacío (= adulto). Mismo patrón que `guest_data`.
**Solo para entradas**: los packs ya piden el homenajeado y los invitados por `event_fields`/
`guest_fields`, y duplicarlo crearía las dos fuentes de verdad que este spec evita.

⚠️ **Y el valor real de esto NO es la etiqueta.** Es que **si asignas una entrada a un menor sin
waiver firmado, te enteras comprando y no en el mostrador con tres niños detrás**. Eso convierte una
conveniencia en una razón, y hay que dejarlo escrito: sin este párrafo, el siguiente lector lo lee como
un adorno y lo recorta.

### 4.8 Persistencia en el navegador: se puede, con dos condiciones

Lo que `#38(d)` prohíbe persistir son **las respuestas del evento** —nombre, edad y alergias de un
menor— y viven en `stores/selection.js`, que **no se persiste nunca**. La cesta sí se persiste, y
**ya guarda el `owner`** (el id del titular) en el almacén del navegador.

Un id de dependiente es la misma clase de cosa: **un puntero opaco, sin nombre ni fecha**, resoluble
solo por la sesión de su dueño. Y el caso del dispositivo compartido **ya está resuelto**: la cesta
purga cuando cambia el dueño.

**Se puede persistir, con dos condiciones:**
1. Viaja **el id y nunca el nombre**.
2. La reconciliación de la cesta sabe qué hacer si el dependiente se retiró entretanto: **la línea se
   queda sin asignar, no se rompe**.

### 4.9 El servidor re-valida: el riesgo de IDOR

⚠️ El id llega desde el navegador. Misma doctrina que `PAY-12`: **el servidor comprueba que cada
dependiente pertenece al titular autenticado**, y un id ajeno se rechaza.

Sin esa guarda, cualquiera enumera los dependientes de otras cuentas metiendo ids en el carrito. **Es
la guarda más importante de este spec** y va con su mutación.

### 4.10 Dónde se persiste la asignación, sin tocar el dinero

- La escribe **Identity**, después de que `OrderCreator` devuelva, y **fuera de la transacción que
  sostiene los locks de franja**: `AFORO-01` prohíbe meter nada antes del lock, y
  `specs/checkout-orquestado.md` prohíbe envolver la secuencia en una transacción. Un paso posterior e
  idempotente no toca ninguna de las dos.
- Si ese paso falla, **el pedido sigue en pie** y la asignación falta → recuperable desde «Mis
  reservas». Es una etiqueta, no dinero.

⚠️ **Pero toca el camino del checkout**, que es el código más protegido del producto. No es gratis y
este spec no finge que lo sea.

## 5. Impacto en invariantes

| ID | Impacto |
|---|---|
| **RGPD-01** | Se AMPLÍA: `anonymize()` tiene que saber qué hacer con los dependientes. **Regla derivada**: el dependiente sigue el régimen de su waiver — con firma, se conserva restringido con su plazo; sin firma ni reservas, se borra |
| **RGPD-04** | Se AMPLÍA: los dependientes entran en `GET /me/export` (art. 20), que ya sale con `no-store` |
| **AFORO-01** | Se CITA y no se toca: la asignación se escribe **después** del lock, nunca antes |
| **PAY-12** | Se APLICA dos veces: el tope es de servidor, y la pertenencia del dependiente se re-valida en servidor |
| **SEC-06** | Sin cambio |

## 6. Plan de verificación empírica

**Guardas ejecutables:**
1. **Un dependiente de otro titular se rechaza en servidor** — mutación obligatoria.
2. **El escaneo en puerta no devuelve nunca el nombre de un dependiente.**
3. **La edad no se persiste en ninguna columna** — solo se deriva.
4. **El tope se aplica en servidor**, no solo en la interfaz — mutación: quitarlo pone el test rojo.
5. **Un dependiente con waiver firmado no se borra**, ni por el titular ni por `anonymize()`.
6. **Un dependiente retirado deja la línea sin asignar, no la rompe.**
7. **Los packs no admiten asignación.**

**Comprobación empírica:**
- Recorrer el embudo en navegador **por las dos puertas** (con sesión desde el paso 3, y anónimo
  identificándose en el paso 5). ⚠️ El contrato de árbol **no ejerce el orquestador**
  (`VERIFICACION-E2E-CAJON.md` §5.bis): la red aquí es el navegador.
- Comprobar el caso del cumpleaños 18 **con el reloj congelado** (`SUITE-03`).
- Medir el chunk del cajón **antes y después**, y subir el techo con su párrafo.

## 7. Revisión y decisión

Diseñado en sesión de arquitectura con el owner el 2026-08-24 (`DECISIONES #142`).

❗ **PENDIENTE de revisión adversarial por otro agente** (`CONVENCIONES` §5).
