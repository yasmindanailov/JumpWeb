<?php

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Fase 4 · paso 4.1 — **el peso del JS público tiene techo, y Vue no viaja con la landing**
 * (`docs/specs/sidebar-spa.md` §4.7, criterio CE-7).
 *
 * Antes de este paso el repo **no tenía ningún gate de tamaño de bundle**, y la fase que empieza
 * mete Vue, Pinia y once pasos de asistente. El riesgo no es teórico: la landing sirve hoy ~16 kB de
 * JS propio, y montar el motor nuevo en el bundle de todas las páginas públicas es un orden de
 * magnitud más — **con el flag activo se enviarían los DOS motores a la vez**.
 *
 * Es un test-presupuesto, hermano de `ApiOverheadTest` y de `SidebarTokenBudgetTest`: no persigue un
 * ideal, impide que empeore sin que nadie lo vea.
 *
 * ⚠️ Depende de `public/build`, que **no está versionado**. El `pre-push` corre `npm run build`
 * ANTES de la suite justo por esto (`PrePushGateTest`), así que en el gate siempre existe; si un
 * agente corre la suite a mano sin haber construido, el test lo dice en vez de fallar en falso.
 */
class SidebarBundleBudgetTest extends TestCase
{
    /**
     * Techo del entry que SÍ carga toda página pública. Medido tras enganchar el motor SPA:
     * 16,27 kB — el coste del enganche fue **medio kB**, porque lo único que entra es el `import()`
     * diferido y su manejo de errores.
     *
     * El margen es corto a propósito: este número solo debe subir cuando alguien decida que la
     * landing haga algo más, no por arrastre de una dependencia que se coló.
     */
    private const LANDING_ENTRY_MAX_KB = 20;

    /**
     * Techo del chunk del cajón, que se descarga en la PRIMERA apertura. Los once pasos llegan a
     * partir de 4.2, así que este número sube; lo que no puede es subir **sin que nadie lo decida**.
     *
     * Consumo medido, para que el margen se lea de un vistazo y no haya que reconstruir para saberlo:
     *   · 4.1 (Vue 3 + Pinia + andamio, sin negocio) ....... 69,13 kB
     *   · 4.2 (catálogo, calendario, hora y complementos) ... 90,29 kB
     *   · 4.3·1 (armazón + módulos de texto e importes) ..... 95,54 kB
     *   · 4.3·2 (pie + cesta en memoria + paso 4) ........... 105,72 kB
     *   · 4.3·3 (aviso de reservas en pausa) ................ 107,29 kB
     *   · 4.3·4 (persistencia de la cesta) .................. 111,01 kB
     *   · 4.4a·1 (elegibilidad al pasar al pago) ............ 112,59 kB
     *   · 4.4a·2 (paso de identificación + login) ........... 122,56 kB
     *
     * ⚠️ **El techo sube a 135 en 4.4a·2, y es una decisión, no un arrastre.** El paso costó **9,97 kB**
     * (3,13 kB comprimidos) y se midió aislado construyendo con y sin él: casi todo es runtime de Vue
     * que hasta ahora no entraba —`vModelText`, `vModelCheckbox` y `withDirectives`—, porque este es el
     * **primer formulario** del cajón. Es coste de una vez: el registro de 4.4b y el pago de 4.5
     * reutilizan ese runtime. Con 120 el margen quedaba en 0,30 kB, que no es un presupuesto sino un
     * accidente esperando.
     *
     *   · 4.4b·1 (alta embebida + paso 7) .................. 131,70 kB
     *   · 4.5·1 (la cesta pide lo que le falta) ............ 133,12 kB
     *   · 4.5·2 (pasos 8 y 9: pagar y salir) ............... 139,36 kB
     *
     * ⚠️ **CUIDADO AL RESTAR: las dos cifras no están en la misma unidad.** Vite imprime en base 1000
     * («139,36 kB») y este assert divide entre 1024, así que el chunk mide **136,09 KiB**. Restar el
     * número de Vite del techo da un margen bastante menor del real; se hizo al cerrar 4.5·1 y salió
     * menos de la mitad del verdadero.
     *
     * ⚠️ **El techo sube a 150 en 4.5·2, y otra vez es una decisión medida.** Los pasos 8 y 9 costaron
     * **5,51 KiB**, aislados construyendo con y sin ellos — mucho menos que los 9,97 del primer
     * formulario, porque el runtime que aquel trajo ya estaba dentro. Con 135 el chunk se pasaba por
     * 1,09 KiB, así que subirlo era inevitable; lo que se decide es CUÁNTO: 150 deja **13,9 KiB** para
     * las tres pantallas de desenlace (4.6) y el widget de Turnstile (4.4b·2), que es lo único que
     * queda de la fase. Un techo más holgado dejaría de vigilar.
     *
     *   · 4.6·1 (paso 6: la reserva creada) ............... 144,17 kB = **140,79 KiB**
     *   · 4.6·2 (pasos 10 y 11: denegado y verificando) ... 149,34 kB = **145,85 KiB**
     *   · ⚠️ **el ledger se quedó ahí y siguió creciendo sin anotarse** durante 4.7·2b·2·B/C: medido
     *     el 2026-08-19, ANTES de tocar nada, el chunk estaba en **146,48 KiB** — o sea que el margen
     *     real no eran los 4,15 de abajo sino **3,52**. Que una cifra de presupuesto envejezca en
     *     silencio es exactamente lo que un presupuesto no puede permitirse.
     *   · 4.4b·2 (el widget de Turnstile: `turnstile.js` + el nodo y sus bindings) ... **148,24 KiB**,
     *     o sea **1,76 KiB** de coste real. Margen que queda: **1,76 KiB**.
     *
     * ⚠️ **El techo NO sube en 4.6, y el margen es hoy el dato que importa.** El primer tramo costó
     * **4,70 KiB** —menos de lo que ocupa su marcado, porque la fila del resumen se EXTRAJO a
     * `SummaryLine.vue` y la pantalla de pagar dejó de tener la suya— y el segundo **5,06 KiB**, con lo
     * que quedan **1,76 KiB** de los 150 (re-medido en 4.4b·2; ver el ledger de arriba).
     *   · 4.7·2b·4 (**los DIBUJOS de los 20 iconos**) ..... **155,50 KiB**
     *
     * ⚠️⚠️ **El techo sube a 160 aquí, y no es un arrastre: es que el cajón se estaba sirviendo SIN
     * ICONOS.** Los 20 `<svg>` del cajón eran envoltorios VACÍOS —la transcripción de Fase 4 replicó
     * el árbol y no el dibujo— y ningún gate podía verlo, porque el diff de árbol no desciende dentro
     * de un `<svg>` (`DECISIONES #113`). Esto no es una función nueva que haya que presupuestar: es
     * la que ya se creía entregada.
     *
     * Coste MEDIDO construyendo con y sin ellos, con el reloj parado —no restando del ledger—:
     * **148,24 KiB → 155,50 KiB = 7,26 KiB**. Y el ledger de arriba resultó exacto: el «sin iconos»
     * cayó clavado en los 148,24 que dejó anotados 4.4b·2.
     *
     * ⚠️ **De esos 7,26 KiB, 2,36 son DUPLICACIÓN literal** (medido): el taco de entradas y el pack
     * viajan tres veces cada uno, la flecha de «Volver» tres, y los dos ojos del campo de contraseña
     * dos. Extraerlos a componentes de icono propios del cajón los dejaría en una sola copia. **No se
     * hizo aquí a propósito**: la regla escrita de este fichero es subir el techo con su motivo y no
     * adelgazar a ciegas, y el trabajo de esta sesión era que los iconos SE VEAN. Queda anotado como
     * oportunidad medida, no como sospecha — y quien la tome se lleva 2,36 KiB seguros.
     * ⚠️ La copia múltiple **no puede derivar en silencio**: `SidebarIconParityTest` exige que cada
     * dibujo del cajón sea, byte a byte, el de su `<x-icons.*>`; si una copia se retoca y las otras no,
     * cae.
     *
     * **160 deja 4,50 KiB de margen.** Es corto a propósito y no arbitrario: la fase del cajón está
     * cerrada, así que lo que viene —el ÁREA DE CLIENTE dentro del cajón (`DECISIONES #66`)— es una
     * fase nueva que tendrá que decidir su propio presupuesto, no colarse por el margen de esta.
     */
    /**
     * ⚠️ **160 → 168 el 2026-08-22, por la reorganización del SPA en `stores/`, y con su coste MEDIDO.**
     *
     * Los **ocho** stores suman ~8,5 KB de fuente y el chunk pasó de **156,4 a 160,3 KiB**: unos
     * **+3,9 KiB**, es decir **~0,5 KiB minificados por store**. Ese es el precio de que el estado del
     * cajón tenga sitio propio, probable sin DOM y compartible con el ÁREA DE CLIENTE, que es lo que
     * `DECISIONES #38c` compró al elegir Pinia y nunca se llegó a construir.
     *
     * ✅ **Y BAJA a 164 el mismo día, al cerrar la reorganización.** El 168 se puso con margen para las
     * secuencias transversales que faltaban; se decidió dejarlas (`DEUDA.md`: tocan solo el embudo, su
     * coste no crece), así que ese margen ya no tiene destino. Medido al cerrar: **161,6 KiB**, con lo
     * que 164 deja ~2,4 de holgura — la de un paso nuevo legítimo, no la de una fase entera.
     * ▶ La regla que deja esto escrito para la próxima: **un techo con margen sobrante deja de
     * apretar**. Se sube con su motivo Y se vuelve a bajar cuando el motivo se agota.
     */
    /**
     * ⚠️ **164 → 166 el 2026-08-22, y lo paga el ÁREA DE CLIENTE** (`specs/area-cliente.md`), que es
     * exactamente lo que el bloque de arriba anticipaba: *«una fase nueva que tendrá que decidir su
     * propio presupuesto, no colarse por el margen de ésta»*.
     *
     * Medido paso a paso, que es lo que hace de esto un presupuesto y no un número:
     * · cierre de la reorganización (`#119`): **161,6 KiB**;
     * · **paso 1** — el nivel sección (`section.js`, su store, la raíz enrutando, el armazón):
     *   **163,2 KiB**, +1,6. ⚠️ Habrían sido **165,5** con `<KeepAlive>`: se midió, no aportaba nada
     *   que su store no resuelva mejor, y se retiró (`DECISIONES #120(g)`);
     * · **paso 2** — la navegación de zonas (`account/navigation.js`, su store, dos zonas y el
     *   enrutado): **165,2 KiB**, +2,0.
     *
     * · **paso 3b** — los datos en el cliente (`account/orders.js`, dos stores, las dos zonas
     *   pintando): **172,7 KiB**, +7,5. Es el salto grande de la tanda, y era de esperar: aquí entra
     *   la pantalla con más marcado del área (el historial con su detalle, su paginación y su
     *   reintento) más la composición que la alimenta.
     *
     * ✅ **Y BAJA a 173 al CERRAR la tanda 1** (paso 5, 2026-08-22), que es la otra mitad de la regla y
     * la que casi nunca se cumple: *un techo con margen sobrante deja de apretar*. El 174 se puso con
     * holgura para terminar los pasos que faltaban; medido al cerrar, el chunk son **172,7 KiB**, así
     * que 173 deja ~0,3 — la de un retoque, no la de una tanda.
     * ⚠️ **173 → 178 el 2026-08-22, y lo paga la TANDA 2** (paso 6b: las pantallas de contraseña y
     * sesiones). Medido: **177,5 KiB**, +4,8 sobre el cierre de la tanda 1.
     * ▶ Y con un descuento que conviene apuntar: **el campo de contraseña con su botón de mostrar
     * estaba escrito DOS veces** (`LoginForm`, `RegisterForm`) y la tanda 2 iba a añadir dos copias
     * más. Extraerlo a `steps/PasswordInput.vue` hizo que las cuatro compartieran un solo bloque —con
     * sus dos `<svg>` dentro—, así que el +4,8 ya viene neto de esa limpieza.
     *
     * ⚠️ **178 → 183 el 2026-08-22** (paso 7b: la pantalla del perfil, con su formulario de cinco
     * campos y el bloque del correo pendiente). Medido: **182,7 KiB**, +5,2.
     * ▶ Y con un descuento apuntado: en este paso **la sección de cuenta ADELGAZÓ de 38 a 20 líneas**
     * —cada zona pide lo suyo y compone lo suyo— y `account/credentials.js` se renombró a
     * `form-outcome.js` en vez de duplicarse para el perfil. El +5,2 ya viene neto de las dos cosas.
     *
     * ⚠️⚠️ **183 → 186 el 2026-08-22, y es el CIERRE de la tanda 2** (paso 8: la zona de privacidad,
     * con el export y el borrado de cuenta). Medido: **185,3 KiB**, **+2,6** sobre el paso 7b — el
     * salto más pequeño de la tanda, y tiene explicación: la pantalla es corta y todo lo que la
     * sostiene ya estaba dentro (`PasswordInput`, `form-outcome`, el armazón de zona).
     * ▶ Y con dos descuentos apuntados, que es lo que hace legible un `+2,6`: el guardián de los
     * formularios se extrajo a `account/form-run.js` —era el mismo cuerpo en `credentials.js` y en
     * `profile.js`, y el paso 8 iba a ser la tercera copia— y la zona nueva no trae ni un `<svg>`
     * propio. El +2,6 ya viene neto de la extracción.
     *
     * ▶ **Y aquí es donde toca aplicar la otra mitad de la regla**: 186 deja **0,7 KiB** de holgura,
     * la de un retoque y no la de una tanda. Con la tanda 2 cerrada, lo siguiente —la tanda 3, o
     * traer la auth al cajón— vuelve a subirlo con su motivo. *Sube con motivo, baja al agotarse el
     * motivo*: es la tercera vez que este fichero lo cumple, y por eso sigue apretando.
     *
     * ⚠️ **186 → 190 el 2026-08-22, y lo paga la TANDA 3** — exactamente lo que la línea de arriba
     * anticipaba. Paso 10: el **ledger financiero** de «Mis reservas» dentro del cajón (subtotal,
     * señal pagada, desglose de puerta plegable, devuelto, pendiente de devolver y total final).
     * Medido: **187,8 KiB**, **+2,5** sobre el cierre de la tanda 2.
     * ▶ Y con un descuento apuntado: el ledger **no calcula ni un importe**. Los seis los publica el
     * servidor ya resueltos (paso 9), así que lo que entra aquí es marcado y la decisión de qué línea
     * se enseña — no una segunda contabilidad. Un cliente que recompusiera esos números habría
     * costado bastante más que 2,5 KiB, y habría podido divergir.
     * ▶ 190 deja **2,2 KiB** para lo que queda de tanda: los consentimientos y las puertas de
     * entrada. Al cerrarla, se vuelve a bajar.
     *
     * ✅ **Y al CERRAR la tanda 3 el techo se queda en 190, con lo medido pegado a él**: el chunk son
     * **189,5 KiB** —los consentimientos, las puertas y el despliegue de las respuestas del pack
     * cupieron en esos 2,2— así que quedan **0,5 KiB** de holgura. No hay nada que bajar: el número
     * ya apreta. Lo siguiente que entre tendrá que subirlo **a propósito y con su medida**, que es
     * exactamente el trabajo que un presupuesto hace bien.
     * ▶ Y una referencia para quien lo suba: el área de cliente ENTERA —tres tandas, siete pantallas,
     * once endpoints— ha costado **27,9 KiB** sobre el cierre de la reorganización (161,6 → 189,5).
     *
     * ⚠️ **190 → 191 el 2026-08-23, y lo paga la AUTH dentro del cajón** (`specs/auth-en-cajon.md`
     * §8·A3): el módulo `forgot.js` —las reglas de recuperar contraseña— más el tercer formulario en
     * `stores/auth.js`. Medido: **190,5 KiB**, **+1,0** sobre el cierre de la tanda 3.
     * ▶ **La subida es de UN KiB a propósito, y el criterio es nuevo**: hasta aquí este fichero subía
     * por adelantado para que una tanda entera cupiera, y luego bajaba al cerrarla. Eso funcionó tres
     * veces, pero deja un tramo largo en el que el presupuesto **no aprieta**. Este trabajo lo sube
     * paso a paso, con la medida de cada uno: las tres zonas de auth (A4) volverán a subirlo con la
     * suya. Cuesta un rojo más por paso y a cambio ningún tramo queda sin guardia.
     *
     * ⚠️ **191 → 194 el 2026-08-23 (A4): las dos primeras ZONAS de auth.** Entran `LoginZone` y
     * `ForgotZone`, el módulo del aterrizaje (`account/after-auth.js`) y el enlace de recuperar en
     * `LoginForm`. Medido: **193,9 KiB**, **+3,4** sobre A3.
     * ▶ **Y el descuento vale la pena decirlo**: de las dos pantallas, la de entrar **no añade
     * formulario** —reutiliza el `LoginForm` del paso 5, que ya estaba en el chunk—, así que esos 3,4
     * KiB son casi todo la de recuperar, que sí es marcado nuevo. Copiar el formulario habría costado
     * el doble y habría abierto la puerta a que las dos versiones divergieran.
     *
     * ⚠️ **194 → 199 el 2026-08-23 (A5): la zona de ALTA con su «revisa tu correo».** Medido: **198,3
     * KiB**, **+4,4** sobre A4. Entra la zona, sus dos caras —formulario y pantalla de verificación
     * con reenvío—, las dos pestañas y las reglas de `account/verify.js`.
     * ▶ **Con un descuento medido**: la mitad del coste esperado no llegó a existir porque el
     * formulario de alta se REUTILIZA del paso 5, igual que el de entrar en A4. Lo que sí es marcado
     * nuevo es la segunda cara, que la web no tenía en el cajón.
     * ▶ **199 deja 0,7 KiB.** El siguiente paso —las puertas— es cableado, no pantallas, así que
     * debería caber; si no cabe, sube con su medida como los tres anteriores.
     *
     * ⚠️⚠️ **199 → 208 el 2026-08-23: EL BLOQUE DE CUENTA DEL PANEL**
     * (`specs/account-context-vue.md` §4.12). Entra `account/AccountPanel.vue` con sus dos caras y sus
     * dos iconos, más cuatro módulos: `account/panel.js` (las reglas), `stores/accountContext.js`,
     * `account/sign-out.js` y `ui/account-host.js`.
     *
     * ▶ **Medido construyendo, no estimando: 198,77 → 207,02 KiB, o sea +8,25.** Es el salto más
     * grande del ledger —la tanda más cara del área de cliente fueron +7,5— y conviene decir por qué
     * no se recorta: lo que entra **no es una pantalla nueva, es una que se retira de otro sitio**.
     * A cambio, el HTML de **cada página pública** adelgaza: medido sobre la home anónima real,
     * **170.753 → 169.341 B, −1.412 B netos por visita**, y eso se paga en CADA visita mientras que
     * el chunk se paga una vez y solo si el cliente abre el cajón.
     *
     * ⚠️ **Y el punto de partida iba 0,47 KiB optimista**: la entrada de A5 dice 198,3 y el build real
     * daba **198,77**. Se corrigió al medir este salto, para que el delta sea honesto.
     *
     * ▶ **208 deja 0,98 KiB**, que es la holgura estrecha de siempre. Lo que quede de este trabajo
     * —retirar `logged-in`, `followAccountLink` y el evento— solo puede BAJARLO.
     *
     * ⚠️ **208 → 209 el 2026-08-23, en el pulido**: entra `account/ZoneLoading.vue` —el spinner que
     * las cuatro zonas que piden datos pintan mientras llegan, porque hasta ahora salían **en
     * blanco**— y `navigation.js::bringsOwnHeading()`, la regla que impide que el título de las
     * pantallas de auth se pinte DOS veces. Medido: **207,27 → 208,24 KiB, +0,97**.
     * ▶ **209 deja 0,76 KiB.** Y conviene decir de dónde NO sale este coste: el spinner **no trae CSS
     * nuevo** —reutiliza `.jj-spinner`, que la página ya carga como hoja estática— ni marcado propio
     * más allá del que el velo del armazón ya usaba.
     *
     * ⚠️ **209 → 211 el 2026-08-23: LOS ICONOS.** Entran `account/ZoneIcon.vue` con los **cinco**
     * dibujos del índice de «Mi cuenta» y el tercer botón del bloque. Medido: **208,33 → 210,83 KiB,
     * +2,50**, que encaja con el coste conocido de un icono en este chunk —`DECISIONES #113` lo midió
     * en ~0,36 KiB— más el marcado de la fila de botones.
     * ▶ **Y los dibujos NO se escribieron a mano**: se generaron desde el render real de los
     * `<x-icons.*>`, que es lo que permite a `SidebarIconParityTest` compararlos con su fuente. Cuatro
     * de los cinco son componentes NUEVOS del sistema de diseño (`calendar`, `lock`, `devices`,
     * `shield`): no había iconos pequeños para esas tres zonas, y `ticket-tear-off` es la ilustración
     * del CTA de compra —viewBox 60×36 y con texto— que a 18 px no se lee.
     * ▶ **211 deja 0,17 KiB**, que es lo más estrecho que ha estado. Lo siguiente que entre lo mide.
     *
     * ⚠️ **211 → 212 el 2026-08-23, y los 0,17 KiB de holgura se agotaron con DOS arreglos**
     * (`DECISIONES #125`). Medido: **210,83 → 211,02 KiB, +0,19 (190 bytes exactos)**, repartidos
     * entre `navigation.js::replace()` —conmutar de pestaña deja de apilar, para que «Volver» salga
     * del área en vez de cambiar de pestaña— y la bandera `consentsLoading` de `stores/privacy.js`,
     * que es la que hace que el velo de la zona de privacidad se pinte de una vez.
     * ▶ **Ninguno de los dos es una feature**: son el precio de dos fallos encontrados en NAVEGADOR
     * (`V23·6` y la revisión de UX del owner), y ese es exactamente el caso en que este techo debe
     * ceder — no cede por «una pantalla más», cede por corrección medida.
     * ▶ **212 deja 0,98 KiB.** Sigue siendo la holgura más estrecha del ledger: lo siguiente que entre
     * lo mide, y al cerrar se baja a lo medido.
     *
     * ⚠️ **212 → 214,5 el 2026-08-23: «Mis reservas» pasa a listarse POR RESERVA**
     * (`specs/mis-reservas-por-reserva.md`). Medido: **211,02 → 213,57 KiB, +2,55**. Lo que entra es
     * `ReservationCard.vue` —la tarjeta que comparten las DOS pantallas— más `cardRow()`, el estado
     * por ámbito y el pedido bajo demanda en `stores/orders.js`.
     * ▶ **Y conviene decir de dónde NO sale este coste**, porque podría haber sido mucho mayor: el
     * historial **no trae componente propio** —es la misma zona con un `scope` por prop—, el ledger
     * **no se duplicó** —se reutiliza `orderRow()` sobre `GET /orders/{code}`, que ya existía— y la
     * composición de la reserva **es la misma `lineRow()`** que ya pintaba la línea dentro del
     * pedido. Una segunda zona con su propia tarjeta y su propio ledger habría costado el doble.
     * ▶ **214,5 deja 0,93 KiB**, otra vez la holgura más estrecha del ledger.
     *
     * ⚠️ **214,5 → 215,5 el 2026-08-24: los tres defectos de LECTURA del desglose** (`L1`, `L2`, `L3`
     * de `specs/desglose-dinero-cliente.md` §17.1, `DECISIONES #128`). Medido: **213,57 → 214,53 KiB,
     * +1,01**. Lo que entra es el ancla de caja con su método, su fecha y su marca de neutralidad
     * (`chargedLine()` y su caption), el rótulo por método en los dos ejes y la etiqueta de cantidad
     * en la línea y en el complemento.
     * ▶ **Y es exactamente el caso en que este techo debe ceder**, según lo que él mismo dice arriba:
     * no cede por «una pantalla más», cede por **corrección medida**. Los tres salieron de mirar un
     * pedido REAL en pantalla —`R-L6UTIA`— y los tres eran afirmaciones falsas o ilegibles sobre el
     * dinero del cliente. El más caro, `L1`, es el que convierte el desglose en algo que el cliente
     * puede **verificar** contra su banco en vez de solo leer.
     * ▶ **Y conviene decir de dónde NO sale este coste**: ni un solo importe ni una sola condición se
     * calculan en el cliente. La condición del ancla (`has_cash`), el método, la fecha y la etiqueta
     * de cantidad **llegan compuestos por el dominio**; la zona solo elige qué pinta. Recomponer
     * cualquiera de los cuatro aquí habría costado menos bytes y una divergencia.
     * ▶ **215,5 deja 0,92 KiB**, la holgura habitual: lo siguiente que entre lo mide.
     *
     * ⚠️ **215,5 → 219,5 el 2026-08-24: «MIS PEDIDOS» como pantalla propia**, la tanda C
     * (`specs/desglose-dinero-cliente.md` §19, `DECISIONES #129`). Medido: **214,58 → 218,68 KiB,
     * +4,10**. Es la subida más grande desde que existe este techo, y es una PANTALLA entera: zona
     * con su lista, su paginación, su vacío y su spinner (`PurchasesZone`), la tarjeta de un pedido
     * con los dos ejes del desglose (`PurchaseCard`), las acciones del store y el icono de la entrada.
     * ▶ **Y de dónde NO sale**: el desglose no se ha duplicado, se ha **MUDADO** desde
     * `ReservationCard` —que adelgaza—, y la composición es `orderRow()` tal cual, la misma que ya
     * componía ese pedido cuando se desplegaba dentro de una reserva. Una segunda forma de pintar los
     * dos ejes habría costado el doble y una divergencia.
     * ▶ **219,5 dejaba 0,82 KiB.** Lo siguiente que entre lo mide.
     *
     * ⚠️ **219,5 → 221,5 el 2026-08-25: el ICONO POR PRODUCTO** (`DECISIONES #140`). Medido:
     * **218,68 → 220,78 KiB, +2,10**, y el desglose importa porque no es todo coste:
     *  · **SALEN dos geometrías DUPLICADAS**: la tarta y la entrada estaban escritas a mano, enteras,
     *    en `CartStep.vue` **y** en `SummaryLine.vue` —cuatro copias de dos dibujos—. En fuente son
     *    −1.458 y −1.314 bytes.
     *  · **ENTRAN cuatro dibujos que el cajón no sabía pintar** (confeti, par de entradas, taco de
     *    entradas y calcetines). Ése es el coste real, y es la funcionalidad: sin ellos, el panel
     *    ofrecería iconos que la cesta serviría como un ticket genérico.
     * ▶ **Se podó ANTES de subir el techo**, como en `#129`: la subida neta es de cuatro iconos, no
     * de seis. **221,5 deja 0,72 KiB.**
     *
     * ⚠️⚠️ **221,5 → 226 el 2026-08-26: el WAIVER en el cajón** (Fase 6 · tanda 3b, `DECISIONES
     * #166`). Medido: **220,78 → 225,72 KiB, +4,94**, más que «Mis pedidos» (`#129`, +4,10). Y es una
     * FEATURE, no una corrección: `ESTADO.md` decía que este techo solo cede por correcciones, así
     * que **la subida la decidió el owner el 2026-08-26 con el número en la mano** —se le dieron las
     * tres salidas: subir, partir el waiver en un chunk aparte (el alta seguiría costando ~1,5 KiB en
     * éste) o aparcar la tanda— y eligió subir. Lo que cuesta, en tres piezas:
     *  · la casilla «acepto el waiver» con el texto completo PLEGADO en el formulario de ALTA
     *    (`RegisterForm`), y `register.js` mandando `accept_waiver` + `waiver_document_id`;
     *  · la tarjeta de Privacidad —estado, firmar o re-firmar, y la lista de firmas con su PDF—
     *    (`PrivacyZone`) más el aviso del índice (`AccountHomeZone`);
     *  · el store (`stores/waiver.js`: documento vigente, estado, y aceptar RE-LEYENDO los dos si el
     *    texto cambió bajo los pies —el 409 `waiver_document_stale`—) y el módulo puro
     *    (`account/waiver.js`) que decide qué frase se pinta y si hay algo pendiente.
     * ▶ **De dónde NO sale**: la lectura del estado es UNA (`waiverStatusKey`) y la comparten la
     * tarjeta y el aviso; y el TEXTO del waiver no viaja ni en el chunk ni en el arranque — lo
     * publica `GET /legal/waiver` cuando hace falta. **226 deja 0,28 KiB**: lo siguiente que entre
     * lo mide, y no hay margen para un arrastre.
     * ▶ **2026-08-26 (noche, `#171`): 225,72 → 225,85, +0,13 por una CORRECCIÓN** —`mountTurnstile`
     * acepta funciones para el nodo y la clave y espera a que existan, que es lo que faltaba para
     * que el alta suelta de `/registro` funcionara con el anti-bot encendido—. El techo no se toca:
     * **quedan 0,15 KiB.**
     * ▶ **2026-08-26 (noche, `#175`): 225,85 → 226,21, +0,36 por CORRECCIONES, y el techo sube a
     * 226,5** por la regla escrita (cede por correcciones, con su medida): el store del waiver acepta
     * el id del texto ENSEÑADO y relee cuando el estado dice otro (CAJ-1), el 422 del alta relee y
     * desmarca (CAJ-2), la casilla se desmarca tras el 409 (CAJ-3) y el contador de reservas del
     * índice vuelve a pintarse (CAJ-5). **Quedan 0,29 KiB.** Lo siguiente que entre lo mide.
     *
     * ⚠️⚠️ **226,5 → 235 el 2026-08-27 por la noche, y lo paga «MENORES A CARGO» (Fase 6 · C, tanda 3,
     * `DECISIONES #199`) — subida por FEATURE, `[DECIDIDO owner]` (`#197`·2: «construir, medir y subir
     * por feature con su párrafo», ni podar antes ni chunk diferido).** Medido construyendo con y sin
     * la zona: **226,34 → 234,41 KiB, +8,07**. Entran `DependentsZone` (lista + alta), `DependentCard`
     * (edad, cobertura, la exención del menor con su casilla y su PDF, quitar), `stores/dependents.js`
     * (las cuatro llamadas) y `account/dependents.js` (las frases), más una entrada del índice con su
     * icono. Es del tamaño del bloque de cuenta (+8,25) y mayor que Privacidad (+2,6) porque la
     * tarjeta repite el formulario de firma de aquélla —casilla, texto plegado, botón— por cada menor,
     * y porque el store tiene cuatro escrituras y no una. **235 deja 0,59 KiB**: la holgura estrecha
     * de siempre, a propósito, para que lo siguiente que entre —la asignación en el embudo, tanda 4—
     * tenga que medirse y decidirse igual.
     *
     * ⚠️ **235 → 243 el 2026-08-27 por la noche, y lo paga la TANDA 4 de «MENORES A CARGO» —la
     * asignación de entradas en el embudo (`DECISIONES #202`)— subida por FEATURE (`#197`·2).** Dos
     * subidas dentro: U1 puso la paridad mínima de `cart.js` (`dependent_ids` en el saneador y en el
     * almacén: 234,41 → 234,70, +0,29) y U2 el resto, medido construyendo con y sin: `assignment.js`
     * (las reglas del selector, la reconciliación y la puerta 2), `DependentPicker.vue` pintado en los
     * pasos 3 y 4, `toCheckoutItems()`, el «Para:» del resumen y del paso 6, el despliegue de la
     * tarjeta de «Mis reservas», y `line-problems.js` —que salió del orquestador para que éste siguiera
     * encogiendo—: **234,70 → 242,19 KiB, +7,49**. Es del tamaño de la zona de menores (+8,07) por lo
     * mismo: cada casilla del selector es un bloque con su motivo, y se pinta en dos pasos. **243 deja
     * 0,81 KiB**: la holgura estrecha de siempre, a propósito — lo siguiente se mide.
     *
     * ⚠️ **242,18 → 242,64 el 2026-08-28 (`DECISIONES #210`), y NO sube el techo: cabe.** Es el
     * «Volver» del carrito (botón + svg + rótulo: 247.990 → 248.460 B, **+470 B = +0,46 KiB**), el
     * único paso del embudo con paso anterior y sin salida hacia atrás. El resto del `#210` —dos
     * renombrados y mover un `watch`— no pesa. ⚠️ La cifra de partida NO es la 242,19 de la entrada
     * anterior: el arreglo visual del selector (`748030a`, la misma noche) dejó el chunk en 247.990 B
     * = 242,18 y no re-anotó el ledger; la revisión adversarial de `#210` lo midió reconstruyendo
     * HEAD. **243 deja 0,36 KiB**: lo siguiente que entre en el cajón se mide y decide, como siempre.
     */
    private const SIDEBAR_CHUNK_MAX_KB = 243;

    /**
     * Firmas del runtime que NO pueden aparecer en el entry de la landing. Es la guarda de verdad: un
     * techo en kB se puede satisfacer por casualidad, pero encontrar el runtime de Vue dentro del
     * bundle que carga la home significa que el `import()` dejó de ser dinámico —basta un `import`
     * estático en `app.js` para que Rollup lo funda— y eso no se ve en el diff.
     *
     * ⚠️ **Son marcadores INTERNOS de Vue y Pinia, no rutas de `node_modules`.** La primera versión
     * de esta guarda buscaba `node_modules/vue/` y pasaba **sin mirar nada**: en un build de
     * producción Vite no conserva las rutas de origen, así que la cadena no está ni en el chunk que
     * sí lleva Vue. Se comprobó contra el bundle real antes de fijarlas — un test verde que no puede
     * fallar es peor que no tenerlo.
     *
     * @var list<string>
     */
    private const NEVER_IN_LANDING = ['__v_isRef', '__v_skip', '__vue_app__', 'pinia'];

    /** @return array<string, mixed> */
    private function manifest(): array
    {
        $path = public_path('build/manifest.json');

        $this->assertFileExists(
            $path,
            'No hay `public/build`: corre `npm run build` antes de la suite. El `pre-push` ya lo hace.'
        );

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function sizeKb(string $file): float
    {
        $path = public_path('build/'.$file);
        $this->assertFileExists($path, "el manifiesto declara «{$file}» y no está construido");

        return filesize($path) / 1024;
    }

    public function test_the_landing_entry_stays_under_its_budget(): void
    {
        $manifest = $this->manifest();
        $entry = $manifest['resources/js/app.js']['file'] ?? null;

        $this->assertNotNull($entry, 'el entry de la landing no está en el manifiesto');

        $kb = $this->sizeKb($entry);

        $this->assertLessThanOrEqual(
            self::LANDING_ENTRY_MAX_KB, $kb,
            sprintf(
                "El JS que carga TODA página pública pesa %.1f kB (techo: %d kB).\n".
                'Si es por el cajón, el `import()` ha dejado de ser diferido. Si es trabajo nuevo de '.
                'la landing, sube el techo a propósito — es un presupuesto, no un objetivo.',
                $kb, self::LANDING_ENTRY_MAX_KB
            )
        );
    }

    /**
     * **La guarda que sostiene la decisión de §4.7.** El chunk existe ⇔ el entry lo importa de forma
     * DINÁMICA. En cuanto alguien escriba `import Sidebar from './sidebar'` arriba de `app.js`,
     * Rollup lo funde con el entry, este chunk desaparece del manifiesto y la landing engorda un
     * orden de magnitud sin que el diff lo enseñe.
     */
    public function test_the_sidebar_engine_is_a_separate_chunk_loaded_on_demand(): void
    {
        $manifest = $this->manifest();

        $this->assertArrayHasKey(
            'resources/js/sidebar/index.js', $manifest,
            'El motor SPA ya no es un chunk propio: alguien lo ha importado de forma ESTÁTICA en el '.
            'entry, así que Vue y Pinia viajan ahora con todas las páginas públicas.'
        );

        $this->assertContains(
            'resources/js/sidebar/index.js',
            $manifest['resources/js/app.js']['dynamicImports'] ?? [],
            'El entry de la landing ya no declara el motor como importación dinámica.'
        );

        $kb = $this->sizeKb($manifest['resources/js/sidebar/index.js']['file']);

        $this->assertLessThanOrEqual(
            self::SIDEBAR_CHUNK_MAX_KB, $kb,
            sprintf(
                'El chunk del cajón pesa %.2f kB (techo: %s kB). Se descarga en la primera apertura, '.
                'así que su peso es tiempo de espera del cliente justo cuando quiere comprar.',
                $kb, self::SIDEBAR_CHUNK_MAX_KB
            )
        );
    }

    public function test_vue_never_travels_with_the_landing(): void
    {
        $manifest = $this->manifest();
        $entry = (string) $manifest['resources/js/app.js']['file'];
        $js = (string) file_get_contents(public_path('build/'.$entry));

        foreach (self::NEVER_IN_LANDING as $signature) {
            $this->assertStringNotContainsString(
                $signature, $js,
                "La firma «{$signature}» ha acabado dentro del JS que carga toda página pública: el ".
                'motor del cajón ha dejado de traerse con `import()` en la primera apertura (§4.7).'
            );
        }
    }

    /**
     * **Lo que el chunk que se SIRVE tiene que saber hacer** (Fase 4 · paso 4.3·3).
     *
     * ⚠️ Nace de un hueco real: `Sidebar.vue` es el único sitio que pide `GET /booking/status`, y
     * **ningún test lo ejecuta**. No puede: lee `window.Alpine` y el idioma del documento, así que el
     * renderizador SSR del gate monta `Shell` directamente y nunca pasa por él. Se verificó por
     * mutación que borrar esa llamada dejaba la suite ENTERA en verde — y con ella el cajón volvía a
     * vender durante la pausa, que es justo la divergencia que 4.3·3 cierra.
     *
     * Es una guarda barata y del mismo tipo que la de arriba: mirar el artefacto construido. No dice
     * que el cableado sea correcto —eso lo dicen las paridades—, dice que **está**.
     *
     * @var array<string, string>
     */
    private const ENGINE_MUST_KNOW = [
        'jw.cart.v1' => 'persistir la cesta con su clave versionada',
        '/booking/status' => 'preguntar si las reservas están en pausa',
        'purchase__maint' => 'pintar el aviso de pausa',
        '/orders/quote' => 'presupuestar la cesta',
        '/cart/validate-line' => 'preguntar si una línea entra en la cesta',
        // ⚠️ 4.4a·1: el aviso TEMPRANO de admisión. Sin esta llamada el cajón lleva a la pantalla de
        // pago a quien el servidor va a rechazar —tope de pendientes, frecuencia— y, con las reservas
        // pausadas, no se entera de que ya no puede vender. `runCheckout()` tiene su propia red en
        // `node --test`; lo que esto dice es que sigue CABLEADO al clic de «Ir a pagar».
        '/me/reservation-eligibility' => 'preguntar si el titular puede reservar antes de llevarlo a pagar',
        // ⚠️ 4.4a·2: identificarse sin salir del cajón. La comprobación de credenciales y los dos
        // limitadores de `SEC-06` viven en `Identity\Services\PasswordLogin`, que es el MISMO servicio
        // que usa el modal de la web: el cajón no puede tener su propia copia de nada de eso.
        '/auth/login' => 'identificar al cliente contra el servicio que también usa la web',
        // ⚠️⚠️ **Aquí el centinela era `logged-in`, y CAMBIÓ el 2026-08-23**
        // (`specs/account-context-vue.md` §4.6): ese evento murió con el componente Livewire que lo
        // escuchaba. El motor ya no avisa por un bus — pide el contexto al servidor y repinta el
        // bloque él mismo.
        //
        // ⚠️ **Y este centinela dice MENOS de lo que decía aquél, así que conviene no leerlo de más**:
        // prueba que el motor SABE pedir el contexto —si el store dejara de importarse, Rollup lo
        // podaría y la cadena desaparecería—, pero **no** que se pida al conseguir sesión. Eso no lo
        // puede decir ninguna cadena: `refresh()` es una acción de Pinia dentro de `defineStore` y no
        // se poda por no usarse. Es la misma frontera que `turnstile_token` documenta aquí abajo.
        // ▶ Que exista un consumidor en PRODUCCIÓN lo cubre `SidebarIntentWiringTest`; que el efecto
        // se vea, solo el navegador (`V21`).
        '/me/account-context' => 'traer el contexto de cuenta para repintar el bloque del panel',
        // ⚠️ 4.4b·1: el alta embebida. `context` es lo que activa el **pay-first** en el servidor —sin
        // él manda un correo de verificación y no abre sesión—, así que su ausencia no rompería nada
        // visible: dejaría al cliente esperando un correo en mitad de una compra.
        '/auth/register' => 'dar de alta desde el cajón',
        'purchase' => 'declarar el contexto de compra, que es lo que activa el pay-first',
        // ⚠️ 4.4b·2: el widget anti-bot. Va UNO, y el porqué de que no vayan dos está medido.
        // Verificado mutando: quitar el montaje del widget y reconstruir deja esta cadena en CERO y
        // pone rojo este caso. Discrimina de verdad.
        'challenges.cloudflare.com' => 'cargar el widget del anti-bot en el propio cajón',
        // ⚠️ **`turnstile_token` NO sirve de centinela, y probarlo costó una reconstrucción.** Parece
        // el candidato natural para «el token viaja al servidor», pero al quitarlo del payload de
        // `runRegister()` las ocurrencias bajan de 6 a **4**, no a 0: el mismo identificador vive en
        // el cableado del formulario (`emptyForm`, el `v-model` del paso, el vaciado tras un fallo),
        // así que el caso seguía VERDE con el token sin mandarse. Es la misma trampa que `/payment`
        // vs `/payment-status` documentada abajo, y no hay substring que distinga «está en el
        // payload» de «está en el formulario». Ese cableado lo cubre `register.test.js` («manda el
        // token del anti-bot»), que sí muere al quitar la línea.
        // ⚠️ 4.5·2: el paso 9. `purchase__redirecting` es la clase de la pantalla que monta el
        // formulario firmado; sin ella el cajón crearía el pedido —reteniendo aforo— y no llevaría a
        // ninguna parte. El `/orders` del checkout no sirve de centinela: `/orders/quote` ya lo contiene.
        'purchase__redirecting' => 'sacar al cliente hacia la pasarela con el formulario firmado',
        // ⚠️ 4.6·1: la vuelta. `purchase__confirm` es la clase de la pantalla de reserva creada, y
        // `/event-data` la SEGUNDA petición que la sostiene: las respuestas del pack no viajan en
        // `GET orders/{code}` —son datos de un menor y del art. 9— así que sin esta llamada el resumen
        // saldría sin lo que el cliente contestó, con el resto de la pantalla intacto.
        'purchase__confirm' => 'pintar la reserva creada al volver de la pasarela',
        '/event-data' => 'traer las respuestas del pack que el pedido no lleva',
        // ⚠️ 4.6·2: los otros dos desenlaces. Las dos primeras son las clases de sus pantallas; las dos
        // últimas son DISCRIMINANTES y por eso están elegidas así:
        //  · `/payment-status` solo lo escribe `loadPaymentStatus()`, así que si el sondeo del paso 11
        //    dejara de llamarse, Rollup podaría el import y la cadena desaparecería del bundle;
        //  · `order_not_retryable` solo vive en `RETRY_ERROR_KEYS`, que solo alcanza `runRetry()`. Un
        //    `/payment` a secas NO valdría: es subcadena de `/payment-status` y estaría igual.
        'purchase__failed' => 'pintar el pago denegado con su motivo',
        'purchase__verifying' => 'pintar la espera del desenlace',
        '/payment-status' => 'preguntar en qué ha quedado el pago',
        'order_not_retryable' => 'saber cuándo ya no hay nada que reintentar y hay que rehacer la reserva',
        // ⚠️ **`/payment-status` NO basta para el SONDEO, y está medido**: el paso 10 pide ese mismo
        // endpoint para su motivo de rechazo, así que la cadena sigue en el bundle aunque el bucle del
        // paso 11 no se arranque nunca — y el cliente se quedaría mirando «verificando» para siempre.
        // `setInterval` sí discrimina: se midió que aparece **una sola vez** en el chunk y que
        // desaparece al desconectar `startPolling()`. Ni Vue ni Pinia lo usan hoy.
        'setInterval' => 'sondear EN BUCLE mientras espera la notificación de la pasarela',
    ];

    public function test_the_engine_chunk_asks_the_server_what_it_must_not_decide(): void
    {
        $manifest = $this->manifest();
        $chunk = (string) $manifest['resources/js/sidebar/index.js']['file'];
        $js = (string) file_get_contents(public_path('build/'.$chunk));

        foreach (self::ENGINE_MUST_KNOW as $needle => $what) {
            $this->assertStringContainsString(
                $needle, $js,
                "El chunk del cajón ya no contiene «{$needle}», así que ha dejado de {$what}.
".
                'Es la única red de ese cableado: `Sidebar.vue` no lo ejecuta ningún test —lee '.
                '`window.Alpine`, así que el renderizador del gate monta `Shell` directamente—, y '.
                'borrar una de estas llamadas dejaba la suite entera en verde.'
            );
        }
    }

    /**
     * **La guarda de la guarda.** El test de arriba solo significa algo si esas firmas están de
     * verdad en el bundle del motor: si Vue cambiara sus marcadores internos, aquélla seguiría verde
     * para siempre sin mirar nada — que es exactamente lo que hacía su primera versión.
     */
    public function test_the_runtime_signatures_actually_exist_in_the_engine_chunk(): void
    {
        $manifest = $this->manifest();
        $chunk = (string) $manifest['resources/js/sidebar/index.js']['file'];
        $js = (string) file_get_contents(public_path('build/'.$chunk));

        foreach (self::NEVER_IN_LANDING as $signature) {
            $this->assertStringContainsString(
                $signature, $js,
                "«{$signature}» ya no aparece en el chunk del motor, así que buscarla en el entry de ".
                'la landing no demuestra nada. Vuelve a medirla contra el bundle real.'
            );
        }
    }
}
