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
     *
     * ⚠️ **De 20 a 22 el 2026-08-28** (`#231`), y es del primer caso: la landing hace más. Entran
     * la coreografía del hero del cierre (`#229`) y el estado del minijuego (`#231`), y son
     * **2,3 kB** medidos. Este techo hizo exactamente su trabajo: saltó en el `pre-push`, con el
     * mensaje correcto, antes de que nadie lo notara en producción.
     * ▶ **Y lo que NO entra son los otros 12 kB del juego**, que van en su propio trozo. Sin el
     * `import()` dinámico este número habría tenido que subir a **32**: es la diferencia entre
     * «la landing hace algo más» y «la landing carga un juego que casi nadie va a abrir».
     *
     * ⚠️ **De 22 a 23 en `#233`**, y el motivo es tan importante como el número: con 22 el margen
     * real era de **0,07 KiB** —medido 21,93—, y un techo con siete centésimas de margen no es una
     * guarda: es un cable trampa que salta con el siguiente cambio trivial y enseña a subirlo sin
     * mirar. Lo que entra es la coreografía del cierre rehecha (`#233`): dejó de ser un pegajoso y
     * pasó a fijarse a la ventana, que es lo que hace el mockup, y eso son dos condiciones de
     * anclaje y tres medidas publicadas.
     *
     * ⚠️ **De 23 a 26 en `#252`**, y con el mismo criterio: lo medido son **24,5 KiB**, así que el
     * techo deja ~1,5 de margen (6 %) en vez de repetir el cable trampa. Lo que entra es el **imán
     * de los dos puntos estáticos** (`ui/scroll-magnet.js`, `[DECIDIDO owner]`): la decisión pura,
     * el rumbo por movimiento neto, el viaje con su curva y el enfriamiento. Son ~1,6 KiB y se los
     * cobra TODA página pública, aunque solo la portada tenga heroes — el módulo se instala
     * siempre y sale por `null` en las once vistas restantes. Cabe, y se sabe lo que cuesta.
     *
     * ⚠️ Y ojo con la unidad al leer la salida de Vite: **Vite cuenta en kB decimales y esto en
     * KiB**. «22,46 kB» son 21,93 KiB, y esa diferencia ya despistó una vez en esta misma tanda.
     */
    private const LANDING_ENTRY_MAX_KB = 26;

    /** Techo del CARGADOR DEL PAQUETE con sus chunks estáticos. Medido al nacer (T5): **6,10 KiB**. */
    private const PACKAGE_LOADER_MAX_KB = 8;

    /**
     * Techo del trozo del minijuego (`#231`). Medido al construirlo: **12,08 kB**.
     *
     * ⚠️ **Un trozo diferido también engorda, y engorda MÁS callado**: no lo paga quien entra en la
     * portada, así que ningún número lo delata — pero sí lo paga quien llega al final, y es
     * justamente el visitante que ya ha demostrado interés. El margen es corto por el mismo motivo
     * que el de arriba.
     */
    private const SALTA_CHUNK_MAX_KB = 14;

    /**
     * Techo del TRACKER (`cajon/track.js`, T1b de `specs/analitica.md` §4.2, `#678`), y es el ÚNICO de este
     * fichero que se mide **min+gzip**: la spec lo fijó así (≤ 3 KiB) porque es lo que viaja, y un trozo que
     * es casi todo cadenas y estructura comprime al 40 %. Se trae con `import()` tras `load`, desde el chunk
     * compartido de `cajon/**`, así que llega a las DOS entradas sin pesar en ninguna — y por eso mismo nadie
     * lo notaría si engordara: lo paga cada visita, en silencio.
     *
     * Medido al nacer (2026-09-23): **2,31 KiB** gzip (4,53 KiB en crudo). El margen es corto a propósito.
     */
    private const TRACK_CHUNK_MAX_GZIP_KB = 3;

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
     *
     * ⚠️ **243 → 247 el 2026-08-28 por la mañana, y lo paga «MI CARNÉ» (Fase 6 · A,
     * `specs/identidad-qr-puerta.md` §9.6, `DECISIONES #212`) — subida por FEATURE (`#197`·2).**
     * Medido construyendo: **242,64 → 246,29 KiB, +3,65**. Entran `zones/CardZone.vue`, `stores/card.js`
     * (una lectura, una escritura por `runForm`), `account/card.js` (los grupos del token y la URL con
     * versión) y la copia del icono `qr` en `ZoneIcon.vue`. ▶ **Y lo que NO entra es lo que hace que
     * sean 3,65 y no 12**: el QR lo dibuja el SERVIDOR (`GET /me/card/png`, los mismos bytes que el
     * adjunto del correo) — un codificador de QR en el navegador habría costado ≥ 8 KiB minificados para
     * repetir un dibujo que ya existe. **247 deja 0,71 KiB**: la holgura estrecha de siempre.
     *
     * ⚠️⚠️ **247 → 248 el 2026-08-28 por la tarde, y lo paga el PULIDO DEL CAJÓN tras la prueba del
     * owner en staging** (`specs/identidad-qr-puerta.md` §9.7 C·1/C·2/C·4 y `menores-a-cargo.md`
     * §9.11 D·2, `DECISIONES #217`) — subida por FEATURE (`#197`·2). **Medido reconstruyendo la base
     * y añadiendo las cuatro piezas una a una**, cinco builds, y la primera cifra confirma que el
     * ledger no había envejecido: la base da **252.200 B = 246,29 KiB**, exactamente la entrada
     * anterior.
     *   · **C·1, el índice en TARJETAS: 246,29 → 245,99, −0,30 KiB.** ▶ **BAJA**, y es la poda que
     *     este presupuesto pide antes de subir nada: la rejilla se lleva por delante el `catalog__go`
     *     y su flecha SVG en línea, que se pintaba OCHO veces y no señalaba nada en una tarjeta
     *     centrada. Las clases nuevas son CSS, y el CSS no viaja en este chunk.
     *   · **C·2, «Mi QR» junto al nombre: 245,99 → 246,72, +0,73.** Casi todo es la copia byte a byte
     *     del icono `qr` (siete `<rect>`), que no se puede compartir con la de `ZoneIcon` sin que la
     *     paridad deje de comparar dos copias independientes. El rótulo no pesa: reutiliza
     *     `account.card.title`.
     *   · **C·4, renovar con aviso y confirmación propias: 246,72 → 247,44, +0,72.** Es el marcado
     *     que sustituye a `window.confirm`: el párrafo permanente, la caja con su pregunta y los dos
     *     botones, más `nextTick` para llevar el foco al que confirma.
     *   · **D·2, el selector de menores rediseñado: 247,44 → 247,88, +0,44.** La fila pasa de un
     *     `<label>` con un `<span>` a un `<li>` con nombre, edad, estado y motivo en elementos
     *     propios; en `assignment.js` entra `statusFor()` y sale la concatenación del `label`.
     * **Total +1,59 KiB brutos +1,89 / −0,30 de poda. 248 deja 0,12 KiB (123 B)**, la holgura más
     * estrecha que ha tenido este techo — a propósito: lo siguiente que entre en el cajón no puede
     * entrar sin medirse.
     *
     * ⚠️⚠️ **248 → 251 el 2026-08-28 por la tarde, y lo pagan las DOS pantallas que el owner mandó
     * rehacer** —«esa presentación del QR la quiero más profesional… no el QR así suelto» y «la página
     * de menores a cargo hay que mejorarla: en vez del formulario completo, un botón que lo saque, y
     * paginación de ser necesario»— subida por FEATURE (`#197`·2). **Medido reconstruyendo la base y
     * añadiendo las dos piezas por separado**, tres builds; la primera cifra confirma que el ledger de
     * arriba no había envejecido: la base da **253.828 B = 247,88 KiB**, la entrada anterior.
     *   · **El QR como CREDENCIAL: 247,88 → 248,40, +0,53 KiB (538 B).** Es solo plantilla: el marco
     *     `qr-frame`/`qr-tile`/`qr-slot` + las cuatro esquinas, el bloque del código y la separación de
     *     las dos acciones. ▶ **Y es barato porque el marco YA EXISTÍA**: son las clases con las que
     *     `<x-site.registration-qr>` dibuja un QR en la landing desde `#268`, así que lo único que
     *     entra aquí son cinco nodos y sus reglas viven en el CSS, que no viaja en este chunk.
     *   · **Menores: el alta desplegable y la lista paginada: 248,40 → 250,67, +2,26 KiB (2.315 B).**
     *     Aquí sí hay lógica: `account/dependents.js` gana `lastPageOf`, `clampPage`, `pageSlice`,
     *     `dependentsPager` y el `dependentsView()` con sus siete transiciones (todo con `node --test`),
     *     el store gana `forget()`, y la plantilla gana el disparador con `aria-expanded`, la fila de
     *     guardar/cancelar y el `<nav class="pagination">`. ⚠️ **Lo que NO pesa** es el paginador
     *     visible: es el `.pagination` del sitio, el mismo que «Mis pedidos» — cero CSS y cero
     *     componentes nuevos.
     * **Total +2,79 KiB (253.828 → 256.681 B). 251 deja 0,33 KiB (343 B)**: la holgura de siempre.
     */
    /**
     * ⚠️ **251 → 252 el 2026-08-28, y lo paga la REVISIÓN de `#217`, no una feature.** Medido:
     * 250,67 → **251,02 KiB** (+0,35). Son cuatro arreglos de conducta que la revisión adversarial
     * confirmó y que no se podían dejar fuera: el `aria-describedby` que vuelve a unir el motivo con
     * la casilla del menor (más el prefijo por línea, sin el cual dos líneas de la cesta apuntarían al
     * mismo `id`), el foco que regresa al disparador al plegar los dos formularios, y la invalidación
     * del QR al cambiar de titular. **252 deja 1,0 KiB**, la holgura más ancha que ha tenido este
     * techo en toda la fase — a propósito: lo siguiente que entre vuelve a medirse contra un número
     * que aprieta, no contra el susto de los 0,02 KiB con que este se pasó.
     *
     * ⚠️ **252 → 253 el 2026-08-28, y esta vez SÍ lo paga una feature** (`#236`, `[DECIDIDO owner]`).
     * Medido: 251,02 → **252,27 KiB** (+1,25). Entran dos campos en el alta de un menor —**apellidos**
     * y **relación con el titular**—, y el segundo es un desplegable con cinco opciones traducidas,
     * que es de donde sale casi todo el kilobyte. No hay forma barata de tenerlo: la lista cerrada es
     * justamente lo que impide que «madre» acabe escrito de veinte maneras, y lo que sostiene que
     * este adulto pueda firmar la exención en nombre del menor.
     * ▶ **253 deja 0,73 KiB**, otra vez una holgura que aprieta. Es lo correcto: la holgura ancha del
     * apunte anterior existía para que lo siguiente se midiera de verdad, y se ha medido.
     */
    /**
     * ⚠️ **253 → 256 el 2026-08-28, y lo paga el REDISEÑO de los pasos 2 y 3** (`#239`,
     * `[DECIDIDO owner]`). Medido construyendo con y sin los cambios, no estimando: **252,27 →
     * 255,13 KiB, +2,86**.
     *
     * Qué entra, y por qué ninguna de las tres piezas era opcional:
     *   · **La tira de días** — `calendar.js::buildStrip()` (agrupa por mes, nombra el día con `Intl`
     *     en horario LOCAL y mete el año en el rótulo solo cuando cambia) más el carril, el separador
     *     de mes y el chip de tres líneas en `DateStep.vue`. Es la pieza grande, y sustituye a leer
     *     una rejilla de 42 celdas de las que a 28 de agosto solo 4 eran reservables.
     *   · **El calendario plegable** — el disparador con su `aria-expanded` y el estado en el store.
     *     El calendario NO se retira: es la única vía al salto largo (`#237`, medido: 182 días
     *     ofrecidos, seis meses de horizonte).
     *   · **El aviso «casi llena»** — `offer.js::isAlmostFull()`, el umbral en el store y el rótulo en
     *     el chip. Es lo más barato de los tres: una comparación y un `<span>`.
     *
     * ▶ **256 deja 0,87 KiB.** Holgura que aprieta, como la de las dos entradas anteriores y por el
     * mismo motivo: lo siguiente que entre se mide contra un número, no contra un susto.
     */
    /**
     * ⚠️ **256 → 257 el 2026-08-28, y lo paga que las tiras se puedan usar con RATÓN** (`#241`,
     * `[OWNER]`: «en escritorio no hay manera de deslizar, y no hay flechas»). Medido construyendo:
     * **255,13 → 256,67 KiB, +1,54**.
     *
     * Entran `strip.js` (la aritmética del recorrido, con sus casos en `node --test`), `useStrip.js`
     * (el ciclo de vida —oyente de scroll y `ResizeObserver`, porque **el contenido de la tira cambia
     * sin que nadie la desplace**) y dos botones por tira con su nombre accesible.
     * ▶ **No es un adorno**: sin ellas, en escritorio la única salida del paso de fecha era desplegar
     * el calendario, que es exactamente lo que la tira venía a evitar.
     *
     * ▶ **257 dejaba 0,33 KiB**, la holgura más estrecha que ha tenido este techo. A propósito: lo
     * siguiente que entrara tendría que justificarse o podar.
     *
     * ▶ **260 (`#258`), y aquí está la justificación que el párrafo de arriba pedía.** El cajón pasa
     * a pintar el set de iconos del artboard, y **una MASA pesa más que un TRAZO**: un icono de
     * línea son dos o tres primitivas cortas (`<line>`, `<polyline>`), y su equivalente de masa es
     * un `<path>` con el contorno entero recortado.
     *
     * Medido sobre este chunk, **257,00 → 259,64 kB (+2,64)**, y el reparto importa porque no es
     * todo lo mismo:
     *  · **+0,04** los catorce dibujos que ya existían y cambian de idioma, más los dos ojos.
     *  · **+2,60** las CINCO ramas nuevas de `ProductIcon.vue` (`ticket` · `gift` · `pack` ·
     *    `party` · `school-trip`), que son marcadores de catálogo que antes no se podían elegir.
     *
     * ⚠️ **La segunda mitad es capacidad nueva, no engorde**: sin esas ramas, un producto marcado
     * con una de las claves nuevas se pintaría con el respaldo y nadie se enteraría —lo impide
     * `ProductIconSingleSourceTest`, que exige una rama por clave ofrecida—.
     * ⚠️ **Se sube a 260 y no a 265.** Queda **0,36 kB** de holgura, que es la misma estrechez que
     * tenía antes: lo siguiente que entre vuelve a tener que justificarse o podar.
     *
     * ▶ **261 (`#329`), y esta es su justificación.** El área de cuenta gana el estado «aceptaste la
     * exención al registrarte y falta que verifiques tu correo», que antes NO existía en pantalla: se
     * le decía al cliente que no la había firmado y se le ofrecía un botón de firmar que solo podía
     * devolver 409 (`waiver_email_unverified`).
     *
     * **Medido con las dos ramas del árbol por separado**, que es lo que permite atribuirlo: sobre el
     * árbol del otro agente el chunk pesa **259,34 KiB** y con esto **260,32 (+0,98)**. Lo que compra:
     *  · la decisión del aviso en `account/waiver.js` —dos estados donde había un booleano, porque los
     *    dos avisos dicen cosas distintas y ofrecen botones distintos—;
     *  · el ARMADO del reenvío en el store, con su pestillo: sin él, entrar y salir del índice
     *    devolvería los reenvíos gastados y el tope de 4 dejaría de existir;
     *  · y la rama del índice con su cuenta atrás.
     *
     * ⚠️ **La poda ya se hizo, y fue en los RÓTULOS, no aquí**: el botón reutiliza `verify.resend` y
     * `verify.resend_in`, que ya viajaban en el montaje (−190 B de payload, `SidebarMountTest`). *El
     * rótulo más barato es el que ya está.*
     * ⚠️ **Se sube a 261 y no a 265.** Queda **0,68 KiB**: la misma estrechez de siempre.
     *
     * ▶ **262 (`#331`/`#332`).** Medido con las dos ramas por separado: **260,47 → 261,12 KiB
     * (+0,65)**. Lo que compra, y las tres cosas son capacidad que antes no existía:
     *  · el aviso de verificar el correo **dentro del cajón**, con su cuenta atrás, sus reenvíos
     *    restantes y su aviso de límite — la misma puerta que la pantalla del alta (`resendGate`), no
     *    un `disabled` escrito a mano;
     *  · el estado `email_verified` en la decisión del aviso, que es lo que permite que sea UNO en vez
     *    de dos apilados;
     *  · **el cierre de sesión desde el índice de la cuenta**, que faltaba de verdad: el botón del
     *    bloque `.acct` existe pero **se colapsa dentro de esta sección** (modo `account`, altura 0
     *    medida en `VERIFICACION-E2E-CAJON` V4), así que el cliente entraba y se quedaba sin salida a
     *    la vista. Reutiliza `account/sign-out.js` entero.
     * ⚠️ **Se sube a 262 y no a 270.** Queda **0,88 KiB**: lo siguiente que entre vuelve a tener que
     * justificarse o podar.
     *
     * ▶ **263 (`#337`, la T3 del justificante de un menor invitado).** Medido con la rama sola:
     * **261,08 → 262,53 KiB (+1,45)**. Lo que compra es **la ÚNICA pantalla que el responsable de la
     * reserva tiene** de esta feature: quién ha firmado ya el justificante de cada menor invitado y
     * **el enlace para repartir a los padres que faltan**. Sin ella, el que reserva —el profesor de
     * una excursión de cien niños— depende de llamar al parque para conseguir el enlace de su propia
     * reserva.
     *
     * ⚠️ **El enlace no podía viajar en el contexto de cuenta**, que es lo que habría salido gratis:
     * es una credencial portadora y esa respuesta se siembra en el HTML de cada página con sesión
     * (la prohibición que `AccountContextResource` documenta). De ahí que haya un componente que lo
     * pide bajo demanda, y de ahí el coste.
     *
     * ⚠️ **La poda se hizo ANTES de subir el techo, y se midió**: fuera el botón de portapapeles con
     * su respaldo y sus dos rótulos de estado —el `input` de solo lectura ya se autoselecciona al
     * enfocarlo—, y dos refs de estado fundidas en una. **−0,46 KiB**, de 262,99 a 262,53.
     * ⚠️ **Se sube a 263 y no a 268.** Queda **0,47 KiB**: la estrechez de siempre.
     *
     * ▶ **267 (`#343`, la T2 de entrar con Google).** Medido: **262,95 → 265,98 KiB (+3,03)**, y es la
     * subida más grande de este presupuesto — porque lo que entra es **un método de identificación
     * entero**, no una pantalla más: el botón en las tres superficies de auth (las dos zonas del área
     * y el paso 5 del embudo) y el cableado de la pantalla que completa el alta.
     *
     * ⚠️⚠️ **La poda se hizo ANTES y son DOS, las dos medidas** —la pregunta de `#120(r)` es qué sobra,
     * no cuánto subir—:
     *  · **La pantalla se carga en DIFERIDO** (`defineAsyncComponent` en `sections/AccountSection.vue`,
     *    el único del cajón): **−1,88 KiB** del chunk, a cambio de una petición de 5,1 kB para quien
     *    se registra con Google. Se la ve UNA vez en la vida y se llega a ella por una PUERTA —o sea,
     *    con una carga de página por delante—, así que esa petición va donde no se nota; el chunk del
     *    cajón, en cambio, lo descarga cualquiera que abra el cajón para comprar.
     *  · **Su estado NO vive en el store global**: lo consume una sola pantalla, así que se bajó al
     *    propio componente y viaja en el chunk diferido. **−1,32 KiB** más.
     * ▶ Lo que NO se difiere, a propósito: el botón y su rótulo. Los pinta cualquiera que abra las
     * pantallas de auth, y separarlos costaría una petición para ahorrar unos cientos de bytes.
     *
     * ⚠️ **Se sube a 267 y no a 270**: queda **1,02 KiB**. Lo siguiente que entre vuelve a tener que
     * justificarse o podar.
     *
     * ▶ **269 (`#344`, la T3 de Google: lo irreversible y el marketing).** Medido: **265,98 → 268,26
     * KiB (+2,28)**, y lo que compra son **tres derechos que hasta hoy no se podían ejercer**:
     *  · el interruptor que permite **RETIRAR** el consentimiento de marketing (art. 7.3), que no
     *    existía por ninguna superficie;
     *  · **desvincular** una cuenta de Google, que es el contrapeso del aviso de vinculación — sin él,
     *    la única salida de un vínculo no pedido era borrar la cuenta;
     *  · y el aviso, en las CUATRO pantallas que exigen contraseña, de que quien entró con Google
     *    puede crear una desde «he olvidado mi contraseña» (art. 12.2, `[DECIDIDO owner]`).
     * ⚠️ **La poda que se hizo**: el aviso es UN componente reutilizado en las cuatro pantallas —no
     * cuatro copias— y su botón reutiliza `forgot.title`, que ya viajaba. El interruptor de marketing
     * se metió DENTRO de la tarjeta de consentimientos, que ya existía, en vez de estrenar una.
     * ⚠️ **Se sube a 269 y no a 272**: queda **0,74 KiB**.
     * ▶ **265 (la T6 del justificante, `specs/waiver-por-reserva.md` §12.2).** Medido con la rama
     * sola: **262,95 → 264,53 KiB (+1,58)**. Lo que compra es **la PUERTA por la que se entra a la
     * feature**, que hasta ahora no existía: la casilla «viene un menor que no está a mi cargo» en el
     * paso de la cantidad —y la nota equivalente en un producto que lo exige, como una excursión de
     * colegio—. Sin ella, el subsistema entero seguía sin que nadie pudiera activarlo: el enlace tenía
     * tres consumidores en todo el repo y ninguno lo OFRECÍA (§12.1).
     *
     * ⚠️ **Aquí NO hubo poda que valiera, y se midió antes de decirlo.** La única disponible dentro de
     * la rama era pasar el modo como una cadena en vez de dos booleanos resueltos: **270,88 → 270,78
     * kB, o sea 0,10 KiB**, a cambio de meter en el componente la lectura del enum que hoy resuelve
     * quien tiene el catálogo delante. *Una poda que no llega al 7 % de lo que ahorra el techo no es
     * una poda: es empeorar el diseño y seguir necesitando el techo.*
     * ⚠️ **Se sube a 265 y no a 270.** Queda **0,47 KiB**: la misma estrechez, a propósito.
     *
     * ▶ **267 (`#401`, el justificante cuelga de la RESERVA).** Medido con la rama sola: **264,40 →
     * 265,87 KiB (+1,47)**. Lo que compra son las TRES cosas que el owner echó en falta probándolo:
     *  · el enlace **en «Mis reservas»**, que es donde lo buscó —*«sigo sin ver el enlace para copiar
     *    en mis reservas, ni en ningún lado»*— y donde tiene sentido desde que cuelga de la visita;
     *  · el bloque «¿quiénes vienen?» **plegado** (`[DECIDIDO owner]`: *«de manera más sutil, es
     *    demasiado centrada en el proceso»*), con su rótulo diciendo lo que hay dentro;
     *  · **la casilla que ya no se puede marcar sin plazas libres** — compró una entrada, se la asignó
     *    a su hija y aun así pudo pedir un justificante que la puerta iba a rechazar.
     *
     * ⚠️ **El desplegable NO trae JavaScript**: es un `<details>` nativo. Lo que pesa es el bloque de
     * plazas libres, el filtro por reserva del panel y los rótulos del resumen.
     * ⚠️ **Se sube a 267 y no a 275.** Queda **1,13 KiB**: lo siguiente que entre vuelve a justificarse.
     *
     * ▶ **268 (`#403`, compartir o copiar el enlace).** Medido con la rama sola: **265,97 → 267,23 KiB
     * (+1,26)**. Lo que compra es el gesto que el owner pidió —*«añade un icono de copiar o compartir
     * el enlace»*— y **no es un botón de portapapeles**: en un teléfono abre la hoja del sistema, que
     * es donde está WhatsApp, y en un escritorio copia. Ese enlace se reparte a los padres uno a uno,
     * así que el gesto ES la feature.
     *
     * ⚠️ **La poda se hizo antes y se midió**: el acuse pasó de un mapa por reserva a UN par
     * `{id, estado}` —solo se pulsa un botón a la vez— y los rótulos se acortaron. Lo que NO se podó
     * es tener DOS acuses (`shared` y `copied`): decir «copiado» cuando el sistema acaba de abrir
     * WhatsApp sería mentir sobre lo que pasó.
     * ⚠️ **Se sube a 268 y no a 272.** Queda **0,77 KiB**: la estrechez de siempre.
     *
     * ▶ **273 (la FUSIÓN de los dos carriles, 2026-09-02).** Los tres techos anteriores —**267** y
     * **269** del carril de Google (T1+T2 y T3) y **268** de éste— se midieron **cada uno con su rama
     * sola y desde la misma base** (262,95 KiB), así que **ninguno describe el árbol conjunto y
     * `max()` tampoco**: el chunk lleva las features de los dos.
     * ⚠️⚠️ **Un techo heredado de una medición en solitario no es un techo: es una coincidencia.**
     * Aquí la guarda hizo su trabajo — se puso ROJA al fusionar, en vez de dejar pasar un número que
     * ya no describía nada. *Si dos ramas suben el mismo presupuesto, al juntarlas hay que volver a
     * medir: no se elige entre los dos números.*
     * ▶ **Medido sobre el árbol fusionado: 279.251 B = 272,71 KiB.** Y la aritmética CIERRA, que es lo
     * que demuestra que no se coló nada por el camino: 262,95 + 3,03 (Google T1+T2) + 2,28 (Google T3)
     * + 4,28 (justificante) = **272,54 esperados** contra **272,71 medidos** — **0,17 KiB** de desvío,
     * o sea que los tres costes se SUMAN y apenas comparten código.
     * ⚠️ **Se sube a 273 y quedan 0,29 KiB**, la holgura más estrecha que ha tenido este techo: la
     * fusión se comió el margen que cada carril creía tener por separado. Lo siguiente que entre,
     * de cualquiera de los dos, tiene que podar o justificarse — y volver a medir AQUÍ, no en su rama.
     *
     * ▶ **274 (`#345` + `#346`, el pulido del ojo del owner).** Medido: **273,14 KiB**, o sea **0,14
     * por encima** — la holgura de 0,29 que dejó la fusión se agotó, como aquella nota anticipaba.
     * Lo que compra: el botón OFICIAL de Google (su marca, no la del cliente) y que el marketing sea
     * un INTERRUPTOR con la lista de consentimientos que ya no desborda el cajón.
     * ⚠️⚠️ **Se INTENTÓ podar primero, se midió y NO sirvió — y eso también es un resultado.** Pasar
     * los dos trozos de la meta y el rótulo del interruptor a selectores por TIPO DE ELEMENTO
     * (`.account__consent-meta > span`) ahorró **0,06 KiB**: seguía por encima del techo, así que el
     * único efecto de la poda habría sido dejar el marcado menos explícito **sin evitar esta subida**.
     * Se revirtió. *Una poda que no evita subir el techo no es una poda: es solo peor código.*
     * ⚠️ Se sube a **274** y quedan **0,86 KiB**.
     *
     * ▶ **276 (`#349`, lo que el comprador debe antes de pagar).** Medido: **276,00 KiB**, o sea
     * **2,00** por encima. Es la subida más grande del día y compra una pantalla entera: el bloque del
     * paso de pagar (teléfono cuando falta, casilla de condiciones cuando cambian, y el enlace legal
     * que **antes no estaba en ningún sitio del embudo**), su módulo de decisión y la rama que impide
     * que un «no» sobre esos campos devuelva al carrito.
     * ⚠️ **La poda que sí se hizo fue de CÓDIGO, no de bytes**: la decisión salió del componente a
     * `buyer-due.js` porque `SidebarComponentBudgetTest` se puso rojo. Eso no baja el chunk —el módulo
     * viaja igual— pero es la razón por la que estas dos KiB son marcado y cableado, y no lógica
     * duplicada.
     * ⚠️⚠️ **Se puso a 276 con una medición de 276,00 y salió ROJO en el push con 276,03**, que son
     * **30 bytes**. No lo trajo el otro carril —sus dos commits de esa tanda son solo doc, comprobado—:
     * la medición se tomó **antes del último retoque de la plantilla** (el `v-if` que quita la línea
     * legal cuando hay casilla, que la captura pidió). *Un techo medido antes del último cambio no
     * describe el árbol que se empuja: se remide DESPUÉS de tocar la última línea.*
     * ⚠️ Se sube a **277** y quedan **0,97 KiB**.
     *
     * ▶ **275 (`#350`, la T8·c y la T8·d): BAJA, que es la primera vez en este contador.** Medido
     * **274,47 KiB**: la T8·c retira de las dos altas tres casillas con su marcado, sus tres `v-model`
     * en dos consumidores y tres campos del cuerpo de la petición; la T8·d suma el separador y el
     * cableado del botón en los dos formularios. El saldo es **−1,56 KiB** contra los 276,03 de `#349`.
     * ⚠️⚠️ **Se baja el techo a propósito y no se deja en 277.** Un trinquete que solo sube deja de
     * vigilar en cuanto alguien retira código: con 277 quedaría **2,53 KiB** de margen regalado, o sea
     * que las dos próximas subidas entrarían sin que nadie las decidiera. *Un presupuesto que no se
     * ajusta cuando el gasto baja no es un presupuesto, es un techo histórico.* Quedan **0,53 KiB**.
     * ⚠️ Y se mide **después del último cambio**, que es la lección de `#349` de aquí arriba: la
     * cifra sale de reconstruir los dos bundles con el árbol tal y como se empuja.
     *
     * ▶ **276 (`#441`, la T0 del waiver del menor)**: medido **275,27 KiB**. Lo que entra es el
     * estado que faltaba en la tarjeta de un menor —`dependentWaiverAction()` con sus dos
     * constantes, el getter `emailVerified` del contexto y el aviso— para dejar de ofrecer un botón
     * que **solo podía devolver 409**.
     * ⚠️⚠️ **Se PODÓ antes de subir, y la poda la exigió otro gate**: `SidebarComponentBudgetTest`
     * puso la zona en **41 líneas sobre un techo de 40**, y la respuesta no fue subir aquel techo
     * sino mudar el `rereadToken` —que es estado de PANTALLA— al `dependentsView()` del módulo
     * plano, donde ya viven la página y el formulario desplegado. *Subir un techo después de extraer
     * no es lo mismo que subirlo en vez de extraer* (`#349`). Quedan **0,73 KiB**.
     *
     * ▶ **277 (`#441`, la T1)**: medido **276,15 KiB**. Entra la CASILLA de la exención en el alta —el
     * `<details>` con el texto servido, el `.check` y su error por campo—, que es lo que convierte
     * «declarar» y «aceptar» en un solo gesto.
     * ⚠️⚠️ **Y otra vez se PODÓ antes de subir, con el mismo gate de por medio**: `#441` dejó el
     * componente en **42 líneas sobre 40**, y en lugar de subir aquel techo el `document_id` se mudó
     * al CONTEXTO —es del mismo tipo que `messages` y `auth`: lo que la pantalla sabe y el store
     * necesita—, así que la acción volvió a ser una línea. Quedan **0,85 KiB**.
     *
     * ▶ **279 (`#475`, los cinco marcadores de complemento)**: medido **278,58 KiB**, y el coste
     * está aislado — **276,48 antes y 278,58 después**, o sea **+2,10 KiB** por cinco dibujos, unos
     * **430 B cada uno**. Entran `cake`, `ice-bucket`, `snacks`, `drink` y `clock-plus`: los
     * complementos que el catálogo vende de verdad y que hasta ahora se marcaban con la entrada
     * genérica. Quedan **0,42 KiB**.
     * ⚠️⚠️ **Aquí NO se podó antes de subir, y conviene decir por qué en vez de callarlo.** Las dos
     * subidas anteriores podaron porque tenían dónde: un estado de pantalla que vivía en el sitio
     * equivocado. Esto es geometría, y **la geometría es el consumidor mismo de la tanda** — si se
     * quita, el cajón deja de saber dibujar lo que el panel ofrece y `ProductIconSingleSourceTest`
     * muerde con razón. La poda que sí existiría es estructural (la geometría vive DUPLICADA en
     * Blade y en Vue, que es el precio del mecanismo de paridad de `#140`), y rediseñar eso a ciegas
     * —sin navegador instalado— para ganar 2 KiB sería cambiar una certeza por un ahorro.
     *
     * ▶ **280 (`#553`, la puerta de categoría)**: medido **279,23 KiB**. Entra el acordeón del paso 1
     * —las dos reglas del plegado en `catalog.js`, el `<button>` con su `aria-expanded`/`aria-controls`
     * y la clase de estado—, que es lo que convierte el catálogo en **dos puertas** en vez de una lista
     * de 1.033 px. Quedan **0,77 KiB**.
     * ⚠️⚠️ **Se intentó podar ANTES de subir, como manda la norma, y la poda NO podó**: se cambiaron
     * los argumentos de `cuerpoVisible()` de objeto a posicionales creyendo que ahorraban bytes y
     * medido salió **al revés** (279,17 → 279,23). *Una poda que no se mide no es una poda.*
     * ⚠️ Y el barrido de símbolos exportados sin importador **dio 29 falsos positivos**: excluía el
     * fichero que define cada símbolo, así que marcaba como huérfano todo lo que se usa dentro de su
     * propio módulo. No se actuó sobre él — es el instrumento, no el código.
     * ▶ Lo que SÍ podó esta banda está en `#552`: la maquinaria de plegado muerta, el chevron sin
     * consumidor y cuatro reglas de hover escritas para un árbol que ya no existe.
     * ▶ **281 (`#561`, la parada 03)**: medido **280,17 KiB**. Entra la pestaña del SISTEMA en el paso 5
     * y en la barra del área, la pista del teléfono y el texto nuevo de la cabecera.
     * ⚠️⚠️ **Se intentó podar antes de subirlo, como manda la norma, y la poda NO podó — otra vez.** La
     * barra de auth estaba escrita DOS veces (el paso 5 y `AuthTabs.vue`) y hubo que sincronizarla a
     * mano al cambiar la pieza; extraerla a `AuthTabset.vue` parecía poda evidente y **medido subió**:
     * 280,08 → 280,17. ▶ *Un componente de Vue trae su propio envoltorio —props, emits, su función de
     * render— y eso pesa más que las quince líneas de marcado que deja de repetir.* Es la misma
     * medición que `#553` hizo con otro sujeto.
     * ▶ La extracción **se queda igual**, y no por el peso: dos copias del mismo control no divergen el
     * día que se escriben, sino el día que alguien arregla una. El techo paga eso.
     * ▶ **283 (`#563`, la parada 04)**: medido **282,09 KiB**. Entran la hora de retención del pago
     * denegado (`holdUntilLabel` + su sitio en el store), la puerta al carné desde la reserva creada
     * —que trae el store de cuenta y su mapa de zonas a esta sección—, la pegatina de espera del paso
     * 11 y el azulejo del 07.
     * ⚠️⚠️ **Y la poda NO podó, por TERCERA vez seguida** (`#553`, `#561`, ésta): se retiró
     * `setDeclinedReason()` del store, que era código muerto de verdad —su único consumidor era su
     * propio test—, y el chunk bajó **50 bytes** de los 1.140 que hacían falta. ▶ *Retirar un método
     * muerto de un store no devuelve peso: lo que pesa en un bundle son las dependencias que entran,
     * no las líneas que salen.* La poda se queda igual, porque un método que nadie llama es una
     * respuesta a una pregunta que ya no existe.
     * ▶ **284 (`#565`, la parada 05)**: medido **283,56 KiB**. Entran `ConfirmInline` —la pregunta que
     * saca los dos últimos `window.confirm` del navegador— y el rótulo del bloque de justificantes.
     * ⚠️⚠️ **Y aquí la extracción SÍ ahorró, que es la excepción al patrón de `#553`/`#561`/`#563`.**
     * La primera versión de la pieza dejaba fuera el disparador y las tres zonas volvían a escribir el
     * estado, el foco al abrir y el foco al volver; con el gesto entero dentro —el disparador llega
     * por slot— el bundle bajó **290,65 → 290,36 kB** y `PrivacyZone` volvió bajo su techo de líneas.
     * ▶ *Extraer un componente sube el peso cuando el envoltorio cuesta más que lo que deja de
     * repetirse; con TRES consumidores y lógica de verdad dentro, deja de ser así.*
     *
     * ❗❗❗ **EL TECHO NO SUBE EN `#566`, Y EL MARGEN QUE QUEDA SON 0,01 KiB.** Medido **283,99**: la
     * parada 06 entra casi entera por extracción —`WaiverDoc` recoge los CINCO `<details>` que estaban
     * escritos idénticos— y lo que pesa son las dos filas de privacidad con su `<svg>` en línea.
     * ⚠️⚠️ **La poda obvia se intentó y se MIDIÓ, y no paga**: extraer esa fila a un `LegalRow.vue`
     * compartido por las dos altas subió el chunk de **283,99 a 284,17** —dos copias de diez líneas de
     * marcado cuestan menos que el envoltorio de un componente—, así que se REVIRTIÓ. Es el patrón de
     * `#553`/`#561`/`#563`, no la excepción de `#565`: aquélla traía lógica dentro y tres consumidores.
     * ▶ **Para quien venga**: con 0,01 KiB de margen, lo siguiente que entre pone esto en rojo. No es
     * un fallo, es el trinquete haciendo su trabajo — y la poda de la fila **ya está descartada con su
     * número**, así que no vuelvas a intentarla: busca otra o sube el techo con su medición.
     *
     * ▶ **285 (`#567`)**: medido **284,04 KiB**, y fue lo siguiente que entró, como estaba avisado.
     * Entran el quinto rótulo de «¿Quiénes vienen?» —el bloque que trae SOLO el justificante decía
     * «menores», y con los rótulos cortos eso habría sido falso— y la salida «Ir a mi cuenta» de la
     * reserva creada.
     * ⚠️ **Se podó antes de subir, y esta vez la poda SÍ podó, pero no llegó**: la condición «hay
     * menores que ofrecer» estaba escrita TRES veces en `TimeStep` (abrir el bloque, pintar el
     * selector y ahora elegir el rótulo) y pasó a un solo `computed` —**284,08 → 284,04**, 41 B—. Se
     * queda por lo mismo que `#561`: tres copias de una condición divergen el día que alguien arregla
     * una, y aquí la divergencia era un rótulo prometiendo menores que el bloque no enseña.
     *
     * ▶ **286 (`#568`)**: medido **285,29 KiB**. Entran abrir el cajón EN un producto desde la landing
     * (`openProduct()` y la intención `product`), la invitación a abrir cada tarjeta del catálogo y el
     * `ui/pack` vigente del set, que dibuja la entrada dos veces. Sale el botón de cerrar sesión del
     * bloque de cuenta con su lógica, que no llega a compensarlo.
     *
     * ▶ **287 (`#588`)**: medido **286,19 KiB**. Entran la edad del cumpleañero —el tipo `celebrant_age`
     * en los dos pasos que pintan campos y la frase del tramo con su recomendación en
     * `line-problems.js`— y las tres líneas de texto de la T5 de contenido (política y pago seguro al
     * pagar, siguiente paso al confirmar, la pista del descargo). No hay poda que lo compense: son
     * frases que el cliente tiene que leer.
     *
     * ▶ **288 (`#589`)**: medido **287,10 KiB**. Entran los regalos —`GiftList.vue`, que reutiliza el
     * dibujo de `ProductIcon`, dentro del «Más info» de los complementos—, la pista de invitados del pack
     * y `addon-info.js`, el módulo plano que decide qué enseña ese «Más info». Sin el módulo medía
     * 287,00, pero con las condiciones dentro de `TimeStep.vue` (CE-6, `SidebarComponentBudgetTest`):
     * se paga el centenar de bytes para que la regla tenga su caso de `node --test`. Con los regalos
     * también en la tarjeta del catálogo medía 287,14; el owner los retiró de ahí.
     */
    // T3a·3 de la analítica (24-09): el segundo interruptor de «Privacidad» (vincular la navegación a la cuenta)
    // y su acción en el store suman 0,69 kB; medido 288,28 KiB (HEAD sin ellos: 287,60). Es un presupuesto, no un
    // objetivo: se sube con su medida y con un margen mínimo.
    // T3d de la landing nueva (`#691`, 24-09): la secuencia de compra se muda a `usePurchaseFlow.js` para que la
    // compartan el cajón y la isla. Medido 290,09 KiB (HEAD `cb2adb1a` sin la mudanza: 288,86; +1.256 B): son los ~40
    // nombres que el composable devuelve y la sección desestructura, porque las claves de un objeto no se minifican. Se
    // midió la poda obvia —que la sección tome sus diez stores ella misma en vez de recibirlos— y ahorra 0,20 KiB por
    // diez `import` duplicados, sin bajar del techo. No se queda.
    // T3e·2 (`#692`): la carcasa elegible. Desde aquí se mide la DESCARGA del motor (`descargaDelMotor()`): la compra de
    // la isla es un `import()` que comparte con él stores y secuencia, y Rollup los sacó a un chunk común —el fichero
    // del motor pasó a 211 KiB y el común a 80—. Medido 291,69 KiB (HEAD `c7cf521d`: 290,09; +1,60): el pegamento de
    // importaciones entre los dos chunks y la raíz que elige carcasa. Un `manualChunks` no lo quita (el pegamento se
    // queda) y rompería el `import()` del alta con Google que el carril SPA separó a propósito. No se fuerza.
    // T4c de la analítica (24-09): la casilla del opt-in de comunicaciones en la pantalla de «reserva creada» —la regla
    // en `account/marketing-offer.js`, el interruptor con los rótulos de «Privacidad» que ya viajan, y el `watch` que
    // pide el perfil y los consentimientos solo cuando pueden cambiar la respuesta—. Medida sola sobre `54aafbc0`:
    // +2,46 kB (290,09 → 291,27); las dos tandas del mismo día (T3e·2 + T4c) se encontraron en el rebase y el techo se
    // pone con la medida de las dos juntas, la que dice este test: 292,96 KiB. Lo paga un DERECHO en su momento (art. 7
    // RGPD: el consentimiento se pide, no se presupone) y no hay poda: los rótulos se reutilizan y la regla vive fuera
    // del componente a propósito (CE-6).
    // T5a de la analítica (24-09): los experimentos en el motor —`sidebar/experiments.js` (leer la variante, contar la
    // exposición una vez) y su `provide` en `index.js`—. Medido 293,39 KiB (HEAD `3c54fc78`: 292,96; +0,43 kB). Es el
    // mecanismo entero del lado del cliente: la asignación vive en el servidor a propósito (cookie `HttpOnly`).
    // T3e·3 (`#694`): «Tus datos», «Pagar» y los desenlaces de la isla usan dos módulos del motor que antes solo usaba
    // él —la URL del carné (`account/card.js`) y el anti-bot (`turnstile.js`)—, que pasan al chunk común con su
    // pegamento; y el alta y el acceso DEVUELVEN su resultado. Medido sobre `3c54fc78`: 292,96 → 293,41 KiB (+0,45); las
    // dos tandas juntas, sobre `a0670c33`: 293,84 KiB, dentro del techo. Copiarlos en la isla para no moverlos sería la
    // segunda copia de dos reglas (la versión de la imagen, el montaje del widget); no se hace.
    // T3e·4 (`#695`): la cuenta nueva con Google que salió de una compra VUELVE a ella (`account/after-auth.js` lee la
    // marca de la pestaña: `sidebar/marca-compra.js`, solo la mitad de LEER; escribirla y la vuelta van en el trozo de
    // la isla) y el motor dice cuándo terminó de montarse (`ready`). Medido sobre `77a4b3fb`: 293,84 → 294,42 (+0,58).
    // Mirar la vuelta en el NAVEGADOR costaba 0,57 KiB a la entrada de toda página pública: la decide el servidor
    // (`Http\Sidebar\PurchaseResume`) y la entrada sube solo 0,07 (el motivo `resume`).
    // T3e·5 (`#696`): el motor deja a la vista la configuración que ya pide al montarse (`configuracion`: el plazo de
    // ajuste de las fiestas, sin otra petición) y la línea confirmada lleva el formulario, su plazo y la invitación
    // (`outcome.js`). Medido sobre `efbeb18c`: 294,42 → 294,70 (+0,28), dentro del techo.
    private const SIDEBAR_CHUNK_MAX_KB = 295;

    // T3e·2: la compra de la isla, chunk diferido del motor que solo trae una instalación con la isla. Medido 93,36 KiB
    // (la sección, la pantalla 0, la isla y sus piezas); su hoja va aparte (7,2 KiB).
    // T3e·3 (`#694`): la secuencia de «Tus datos», «Pagar» y los desenlaces (sus `use*` y sus módulos puros), que corre
    // desde el montaje —la vuelta del banco aterriza en un desenlace—. Medido 110,35 KiB. Sus PANTALLAS no: van en su
    // propio trozo (abajo), para que quien abre la compra vea la pantalla 0 sin esperarlas.
    // T3e·4 (`#695`): «Entra», la ida a Google y reanudar la compra a la vuelta (`sidebar/reanudar.js`). Medido 113,65
    // (110,35 en `77a4b3fb`).
    // T3e·5 (`#696`): las FIESTAS en la pantalla 0 (`usePantallaCero.js`, `fiesta.js` y `PantallaCuandoFiesta`) y su
    // recibo y su «Listo». Medido 123,46 como DESCARGA (desde aquí se mide la descarga: ver `descargaDe()`). Diferir la
    // pantalla de la fiesta a su trozo ahorraba solo 1,6 KiB a quien compra entradas y costaba una petición más a quien
    // abre un cumpleaños: Rollup sacó sus piezas comunes a un trozo compartido. Va en la compra.
    // `#697`: el sistema de diseño nuevo rehízo el selector de plan (el destacado con su foto, [Hoy] y el pie con la
    // garantía), portado 1:1 con sus estilos en línea: +5.838 B medidos con el selector de antes y el de ahora (123,96 →
    // 129,66); el resto, el `opcional` del campo y un icono. Medido 129,66.
    // T3e·6 (`#698`): la hora que se llena al pagar —recargar las horas del día, las cercanas (`vista.js::horasCercanas`)
    // y «Elegir esta hora», que rehace la línea—. Medido 130,90.
    private const ISLA_COMPRA_CHUNK_MAX_KB = 131;

    // T3e·3 (`#694`): las pantallas de después de la pantalla 0, en su trozo (`isla/compra/pasos-diferidos.js`), que la
    // compra pide al montarse. Medido 36,92 KiB. T3e·4 (`#695`): «Entra» con sus eventos y la «G» de Google, 37,66.
    // T3e·6 (`#698`): «Esa hora ya no está libre» (`PantallaPerdida`, con su selector de horas), 38,56.
    private const ISLA_PASOS_CHUNK_MAX_KB = 39;

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

    /**
     * Lo que la página descarga DE VERDAD por una entrada: su fichero **y los chunks estáticos que
     * importa**, en transitivo.
     *
     * ⚠️⚠️ **Sin esto el presupuesto se relaja solo, y pasó el 2026-09-19.** Este test medía el fichero
     * de la entrada a secas. Al nacer la entrada del paquete (T5), Vite vio que `cajon/**` lo usaban DOS
     * entradas y lo sacó a un chunk compartido: la landing seguía descargando los mismos 25,3 KiB, pero
     * la guarda pasó a ver **19,3** y se habría tragado 6 KiB nuevos sin decir nada. *Un presupuesto que
     * mide una parte del gasto no es un presupuesto.*
     *
     * Los `dynamicImports` NO entran, y eso es el diseño: el chunk del motor es diferido a propósito y
     * tiene su propio techo.
     */
    private function pesoConImportesKb(string $clave): float
    {
        $manifest = $this->manifest();
        $vistos = [];
        $suma = 0.0;

        $recorrer = function (string $k) use (&$recorrer, &$vistos, &$suma, $manifest): void {
            if (isset($vistos[$k]) || ! isset($manifest[$k])) {
                return;
            }

            $vistos[$k] = true;
            $suma += $this->sizeKb((string) $manifest[$k]['file']);

            foreach ($manifest[$k]['imports'] ?? [] as $importado) {
                $recorrer((string) $importado);
            }
        };

        $recorrer($clave);

        return $suma;
    }

    /** Las claves del manifiesto que una entrada trae de forma ESTÁTICA, ella incluida, en transitivo. */
    private function alcanceEstatico(string $clave): array
    {
        $manifest = $this->manifest();
        $vistos = [];
        $pendientes = [$clave];

        while ($pendientes !== []) {
            $k = array_pop($pendientes);

            if (isset($vistos[$k]) || ! isset($manifest[$k])) {
                continue;
            }

            $vistos[$k] = true;
            array_push($pendientes, ...array_map('strval', $manifest[$k]['imports'] ?? []));
        }

        return array_keys($vistos);
    }

    /**
     * **Lo que se descarga al ABRIR el cajón por primera vez**: el chunk del motor y los que importa de forma
     * estática, MENOS los que la landing ya trajo (el paquete, que comparte `sidebar/carcasa.js`).
     *
     * ⚠️⚠️ **Desde la T3e·2 (`#692`) el fichero del motor ya no es todo el motor.** La compra de la isla es un
     * `import()` del motor que comparte con él sus stores y su secuencia, y Rollup los sacó a un chunk COMÚN: el
     * fichero pasó de 290 a 211 KiB sin que el cajón descargara un byte menos (292 entre los dos). Medir el
     * fichero a secas habría aprobado solo —es la trampa que `pesoConImportesKb()` ya documenta para la landing—,
     * y las firmas de `ENGINE_MUST_KNOW` se habrían ido al chunk común sin que el caso las encontrara.
     *
     * @return list<string>
     */
    private function descargaDelMotor(): array
    {
        return array_values(array_diff($this->alcanceEstatico('resources/js/sidebar/index.js'), $this->alcanceEstatico('resources/js/app.js')));
    }

    /** El JS de esa descarga, junto: donde se buscan las firmas del motor. */
    private function jsDelMotor(): string
    {
        $manifest = $this->manifest();

        return implode("\n", array_map(fn (string $k): string => (string) file_get_contents(public_path('build/'.$manifest[$k]['file'])), $this->descargaDelMotor()));
    }

    /**
     * ¿El objetivo se alcanza con un `import()` desde la entrada o desde alguno de los chunks que ésta
     * importa de forma estática? Es la pregunta de «llega diferido», y no cambia porque Rollup reparta
     * el código entre más chunks.
     */
    private function llegaPorImportDinamico(string $entrada, string $objetivo): bool
    {
        $manifest = $this->manifest();
        $pendientes = [$entrada];
        $vistos = [];

        while ($pendientes !== []) {
            $clave = array_pop($pendientes);

            if (isset($vistos[$clave]) || ! isset($manifest[$clave])) {
                continue;
            }

            $vistos[$clave] = true;

            if (in_array($objetivo, $manifest[$clave]['dynamicImports'] ?? [], true)) {
                return true;
            }

            foreach ($manifest[$clave]['imports'] ?? [] as $importado) {
                $pendientes[] = (string) $importado;
            }
        }

        return false;
    }

    public function test_the_landing_entry_stays_under_its_budget(): void
    {
        $manifest = $this->manifest();
        $entry = $manifest['resources/js/app.js']['file'] ?? null;

        $this->assertNotNull($entry, 'el entry de la landing no está en el manifiesto');

        $kb = $this->pesoConImportesKb('resources/js/app.js');

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
     * **El CARGADOR DEL PAQUETE pesa lo que pesa un cargador** (F4 · T5, `specs/cajon-empaquetable.md` §4.1).
     *
     * Hasta la T5, una landing que no era del producto tenía que cargar `app.js` para abrir el cajón: 25,3
     * KiB de los que la mayoría son la coreografía del nav, el hero, los raíles y el imán de scroll de la
     * landing de JumpWeb — código que esa página no ejecuta nunca. Con su entrada propia son **6,1 KiB**.
     *
     * ▶ Este techo es lo que impide que el paquete se vuelva a llenar de landing sin que nadie lo vea: si
     * alguien importa desde `cajon/paquete.js` algo que arrastre `ui/**` o el motor, el número salta aquí.
     * El margen (8 contra 6,1) es el de siempre: holgado para no ser un cable trampa, corto para avisar.
     */
    public function test_the_package_loader_stays_under_its_budget(): void
    {
        $manifest = $this->manifest();

        $this->assertArrayHasKey(
            'resources/js/cajon/paquete.js', $manifest,
            'el cargador del paquete no está en el manifiesto: falta su entrada en `vite.config.js`',
        );

        $kb = $this->pesoConImportesKb('resources/js/cajon/paquete.js');

        $this->assertLessThanOrEqual(
            self::PACKAGE_LOADER_MAX_KB, $kb,
            sprintf(
                "El cargador del paquete pesa %.1f KiB (techo: %d).\nUna página ajena lo descarga entero ".
                'para poder abrir el cajón. Si ha crecido, mira qué le ha entrado de `ui/**` o del motor: '.
                'lo diferido va con `import()`, como el resto.',
                $kb, self::PACKAGE_LOADER_MAX_KB
            )
        );

        // Y el simétrico, que es el sentido de la tanda: cargar el paquete NO puede costar como cargar la
        // landing. Sin esta línea el techo de arriba se podría satisfacer volviendo a fundir las dos.
        $this->assertLessThan(
            $this->pesoConImportesKb('resources/js/app.js') / 2, $kb,
            'el cargador del paquete pesa más de la mitad que la entrada del producto: ha dejado de ser un '.
            'cargador y se está llevando la landing dentro',
        );
    }

    /**
     * **El minijuego pesa lo que dijo pesar** (`#231`).
     *
     * Es una guarda de peso como las de arriba, pero de un trozo DIFERIDO, y por eso hace falta
     * decir por qué existe: nadie la echaría de menos. Un trozo diferido que engorda no lo paga
     * quien entra en la portada —así que no mueve ningún otro número— pero sí lo paga quien llega
     * al final, que es el visitante que ya ha demostrado interés. Sin techo, el juego podría
     * triplicarse sin que ninguna medida se enterara.
     */
    public function test_the_game_chunk_stays_under_its_budget(): void
    {
        $manifest = $this->manifest();

        $entrada = collect($manifest)->first(
            fn (array $v, string $k): bool => str_contains($k, 'site/salta.js'),
        );

        $this->assertNotNull(
            $entrada,
            "el trozo del minijuego no está en el manifiesto.\n".
            '▶ O se ha retirado el juego, o su `import()` dejó de ser dinámico y Rollup lo ha '.
            'fundido con el entry de la landing — que es lo que el techo de arriba mide.',
        );

        $kb = $this->sizeKb($entrada['file']);

        $this->assertLessThanOrEqual(
            self::SALTA_CHUNK_MAX_KB, $kb,
            sprintf(
                "El trozo del minijuego pesa %.1f kB (techo: %d kB).\n".
                'Lo descarga quien llega al final de la portada. Si el juego ha crecido a '.
                'propósito, sube el techo — es un presupuesto, no un objetivo.',
                $kb, self::SALTA_CHUNK_MAX_KB,
            ),
        );
    }

    /**
     * **El tracker es un trozo DIFERIDO que llega a las dos entradas, y pesa lo que dijo pesar** (T1b).
     *
     * Las tres cosas a la vez, porque cada una se puede romper sola: que exista como chunk (un `import`
     * estático lo fundiría con `cajon/**` y lo pagaría toda página pública, dentro del techo de arriba pero sin
     * que nadie lo decidiera); que lo declare como dinámico el grafo estático de LAS DOS entradas (una landing
     * ajena que monte el paquete tiene que medir igual que el producto); y su peso min+gzip.
     */
    public function test_the_tracker_is_a_deferred_chunk_for_both_entries_under_its_budget(): void
    {
        $manifest = $this->manifest();

        $clave = collect(array_keys($manifest))->first(fn (string $k): bool => str_contains($k, 'cajon/track.js'));

        $this->assertNotNull($clave, 'el tracker no está en el manifiesto: o se retiró, o su `import()` dejó de ser dinámico y Rollup lo fundió con el chunk del cajón');

        foreach (['resources/js/app.js', 'resources/js/cajon/paquete.js'] as $entrada) {
            $this->assertTrue(
                $this->llegaPorImportDinamico($entrada, $clave),
                "«{$entrada}» ya no trae el tracker con `import()`: la analítica no llega a esa entrada, o llega estática",
            );
        }

        $gzipKb = strlen((string) gzencode((string) file_get_contents(public_path('build/'.$manifest[$clave]['file'])), 9)) / 1024;

        $this->assertLessThanOrEqual(
            self::TRACK_CHUNK_MAX_GZIP_KB, $gzipKb,
            sprintf(
                "El tracker pesa %.2f KiB min+gzip (techo: %d).\nLo paga CADA visita, tras `load`. Si ha crecido a ".
                'propósito, sube el techo con su medida — es un presupuesto, no un objetivo.',
                $gzipKb, self::TRACK_CHUNK_MAX_GZIP_KB,
            ),
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

        // ⚠️ **Se mira el GRAFO ESTÁTICO de la entrada, no solo su fichero.** Desde la T5 hay dos entradas
        // que montan el cajón —la landing y el cargador del paquete—, así que Rollup sacó `cajon/**` a un
        // chunk compartido y es ÉL quien declara ahora el `import()` del motor. La pregunta que importa
        // sigue siendo la misma —«¿llega el motor a la landing sin pedirlo?»— y se responde igual de bien;
        // preguntársela solo a `app.js` habría puesto esta guarda en rojo sin que nada hubiera empeorado.
        $this->assertTrue(
            $this->llegaPorImportDinamico('resources/js/app.js', 'resources/js/sidebar/index.js'),
            'El entry de la landing ya no declara el motor como importación dinámica, ni él ni ninguno de '.
            'los chunks que importa de forma estática.'
        );

        // ⚠️ La DESCARGA del motor, no su fichero (T3e·2): ver `descargaDelMotor()`.
        $kb = array_sum(array_map(fn (string $k): float => $this->sizeKb((string) $manifest[$k]['file']), $this->descargaDelMotor()));

        $this->assertLessThanOrEqual(
            self::SIDEBAR_CHUNK_MAX_KB, $kb,
            sprintf(
                'El chunk del cajón pesa %.2f kB (techo: %s kB). Se descarga en la primera apertura, '.
                'así que su peso es tiempo de espera del cliente justo cuando quiere comprar.',
                $kb, self::SIDEBAR_CHUNK_MAX_KB
            )
        );
    }

    /**
     * **La compra de la ISLA es un chunk DIFERIDO del motor, con su propio techo** (T3e·2, `DECISIONES #682`).
     *
     * Solo la trae una instalación con la isla como carcasa, en la primera apertura: ni la landing ni el cajón la
     * descargan. Si un día se importara de forma estática —en la raíz, en `index.js`—, el cajón de TODAS las
     * instalaciones pagaría sus ~93 KiB sin enseñarla nunca.
     */
    public function test_the_isla_purchase_is_a_deferred_chunk_of_the_engine_under_its_budget(): void
    {
        $manifest = $this->manifest();
        $clave = 'resources/js/isla/SeccionCompra.vue';

        $this->assertArrayHasKey($clave, $manifest, 'La compra de la isla ya no es un chunk propio.');
        $this->assertTrue(
            $this->llegaPorImportDinamico('resources/js/sidebar/index.js', $clave),
            'El motor ya no trae la compra de la isla con `import()`.'
        );
        $this->assertNotContains($clave, $this->descargaDelMotor(), 'La compra de la isla viaja con el motor: la paga cada cajón.');
        $this->assertNotContains($clave, $this->alcanceEstatico('resources/js/app.js'), 'La compra de la isla viaja con la landing.');

        $kb = $this->descargaDe($clave, $this->alcanceEstatico('resources/js/sidebar/index.js'));
        $this->assertLessThanOrEqual(self::ISLA_COMPRA_CHUNK_MAX_KB, $kb, sprintf(
            'La compra de la isla pesa %.2f kB (techo: %s kB).', $kb, self::ISLA_COMPRA_CHUNK_MAX_KB
        ));
    }

    /**
     * Lo que se DESCARGA al llegar a una entrada diferida: su fichero y los chunks estáticos que importa, menos lo que
     * ya se tenía (T3e·5). ⚠️ Medir el fichero a secas dejaba pasar una partición de Rollup: al diferir una pantalla de
     * la isla, sacó sus piezas comunes a un chunk COMPARTIDO de 25,9 KiB y el fichero de la compra «bajó» de 123 a 96
     * KiB sin que quien la abre descargara un byte menos (medido). Es la trampa que este test ya pagó con la landing.
     *
     * @param  list<string>  $yaDescargado
     */
    private function descargaDe(string $clave, array $yaDescargado): float
    {
        $manifest = $this->manifest();
        $propio = array_diff($this->alcanceEstatico($clave), $yaDescargado);

        return array_sum(array_map(fn (string $k): float => $this->sizeKb((string) $manifest[$k]['file']), $propio));
    }

    /**
     * **Las pantallas de después de la pantalla 0 son OTRO trozo, pedido aparte** (T3e·3, `#694`).
     *
     * Quien abre la compra quiere elegir día y hora: «Tus datos», «Pagar» y los desenlaces no le hacen falta hasta
     * que pulse «Continuar», y la sección los pide al montarse. Si se importaran de forma estática, cada apertura de
     * la isla esperaría sus ~37 KiB antes de pintar la pantalla 0.
     */
    public function test_the_isla_steps_after_the_first_screen_are_their_own_deferred_chunk(): void
    {
        $manifest = $this->manifest();
        $clave = 'resources/js/isla/compra/pasos-diferidos.js';

        $this->assertArrayHasKey($clave, $manifest, 'Los pasos de la compra de la isla ya no son un trozo propio.');
        $this->assertTrue(
            $this->llegaPorImportDinamico('resources/js/isla/SeccionCompra.vue', $clave),
            'La compra de la isla ya no pide sus pasos con `import()`.'
        );
        $this->assertNotContains($clave, $this->alcanceEstatico('resources/js/isla/SeccionCompra.vue'), 'Los pasos viajan con la pantalla 0.');

        $kb = $this->descargaDe($clave, [
            ...$this->alcanceEstatico('resources/js/sidebar/index.js'),
            ...$this->alcanceEstatico('resources/js/isla/SeccionCompra.vue'),
        ]);
        $this->assertLessThanOrEqual(self::ISLA_PASOS_CHUNK_MAX_KB, $kb, sprintf(
            'Los pasos de la compra de la isla pesan %.2f kB (techo: %s kB).', $kb, self::ISLA_PASOS_CHUNK_MAX_KB
        ));
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
        // La DESCARGA del motor (T3e·2): sus firmas pueden vivir en el chunk que comparte con la isla.
        $js = $this->jsDelMotor();

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
        $js = $this->jsDelMotor();

        foreach (self::NEVER_IN_LANDING as $signature) {
            $this->assertStringContainsString(
                $signature, $js,
                "«{$signature}» ya no aparece en el chunk del motor, así que buscarla en el entry de ".
                'la landing no demuestra nada. Vuelve a medirla contra el bundle real.'
            );
        }
    }
}
