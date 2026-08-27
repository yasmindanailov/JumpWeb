# Índice de la documentación — JumpWeb

> **No leas toda la doc.** Usa la tabla de enrutado de `../CLAUDE.md`: para cada tarea,
> solo su fila. Este índice es el mapa completo con el estado de cada documento.

## Vivos (se actualizan cada sesión)
| Doc | Qué es |
|---|---|
| `ESTADO.md` | Foto viva mínima: dónde estamos / qué sigue. **Carga obligatoria al arrancar.** Fuente única del recuento vivo de la suite. |
| `00-REFACTOR.md` | Tracker VIVO del refactor de generalización (fases + checklists). Sus marcadores de fase MANDAN sobre ESTADO. |
| `DECISIONES.md` | Cronológico de decisiones con su porqué. Buscar por número, no cargar entero. Revertida = «Sustituida por #N» en la antigua. |
| `ENTORNOS.md` | Los dos entornos (local y **staging**, que es 0 LIVE / 0 PRODUCCIÓN), sus seis guardas y el procedimiento de despliegue. |
| `CONVENCIONES.md` | Reglas y protocolo de los agentes (DoD §3.bis, arranque/handoff §7, git §8, escalado al owner §9, **dos agentes sobre `main` §10**). |
| `GLOSARIO.md` | Lenguaje ubicuo: término de dominio ↔ artefacto real de código/BD, con estado de generalización. |
| `DEUDA.md` | Registro único de deuda técnica con severidad, medida y fase que la retira. |
| `INSTALACION-CLIENTE.md` | Checklist de instanciar un cliente white-label (settings, tema, contenido, Redsys, cron). |
| `VERIFICACION-E2E-CAJON.md` | Guion OPERATIVO del extremo a extremo del cajón SPA con la pasarela en sandbox: la forma ejecutable de `specs/sidebar-spa.md` §6. Empieza diciendo qué NO hace falta mirar porque ya tiene red automática. Nació como lo único que separaba al motor SPA de poder desplegarse; retirado el flag en `#112`, es el guion con el que se comprueba el cajón en navegador tras cada despliegue. |
| `specs/PLANTILLA.md` | Plantilla de spec para diseños previos a implementación (`specs/`). |
| `specs/vocabulario-dominio.md` | 🟦 Diseño de generalización del vocabulario (entrada de Fase 2; decisión final con los módulos). |
| `specs/modulos-dominio.md` | ✅ Arquitectura de módulos de Fase 2 APROBADA (revisión multi-agente) — orden, contratos y checklists de mudanza. |
| `specs/api-v1.md` | 🟦 Diseño de la API v1 (Fase 3), v2 tras revisión adversarial. **EJECUTADO**: los 6 pasos del corte cerrados; §9 lleva el avance y §10 → §10.sexdecies lo que el código enseñó al implementar (83 puntos; los tres últimos lotes ya de Fase 4). |
| `specs/checkout-orquestado.md` | ✅ Diseño APROBADO (v2, revisión adversarial ×3) del cierre de Fase 3: la secuencia «admitir → crear → abrir cobro» baja al dominio tras los puertos `ReservationCheckout` y `PaymentInitiation`. El segundo driver de pasarela queda para Fase 6. |
| `specs/sidebar-spa.md` | 🟦 Diseño de Fase 4: el sidebar como SPA (Vue 3 + Pinia), primer consumidor real de la API v1. Alcance = solo el cajón; el tema es **tokens + hoja de estilos por instalación**, lo que convierte los nombres de clase del sidebar en un contrato público. |
| `specs/area-cliente.md` | 🟦 Diseño del **área de cliente dentro del cajón** (`DECISIONES #66`/`#120`), tanda 1 = **solo lectura**. Mide que «el servidor ya está» solo vale para leer, elige el modelo de navegación (índice + zonas libres, NO el grafo del embudo) y declara por qué aquí **la red no es el diff de árbol** sino la paridad de datos y el navegador. |
| `specs/auth-en-cajon.md` | ✅ **EJECUTADO** (`DECISIONES #122`, 2026-08-23) — el **último trozo de `#66`**: entrar, darse de alta y recuperar contraseña viven en el cajón y el **modal de la cabecera está retirado**. Se conserva porque su valor está en lo que MIDIÓ: la revisión adversarial encontró dos bloqueantes (los textos del área viajan solo con sesión; el contexto del alta estaba quemado) y la auditoría de tests destapó **un hueco de seguridad vivo** —el desenlace de pago de otra persona sobrevivía a un login en dispositivo compartido— más dos huecos de guardia. §8.bis es el método: clasificar mutando, no leyendo. |
| `specs/desglose-dinero-cliente.md` | ✅ **EJECUTADA Y CERRADA** (2026-08-24, `#127`→`#134`): las tres tandas y los cuatro defectos de lectura, hechos — el último, **`L6`** (§23): «Importe al reservar» dice **hacia dónde y cuánto**, y su frase `null` **es la condición de enseñar la línea**. `L4` aparcado, `L5` retirado (no era un defecto). El desglose de dinero que ve el cliente. ⚠️ **Contesta la pregunta del owner a favor del código**: NO hay dos fuentes de verdad — medido sobre **11 pedidos REALES creados por los flujos reales**, 8 de 9 identidades se cumplen en los once y panel y cliente enseñan el mismo número bajo el mismo rótulo. El riesgo está en la **proyección**: la API publica 2 de las 6 dimensiones. ⚠️⚠️ **§4.ter.1 es el hallazgo**: el descuadre solo aparece **cuando la franja ya pasó**, o sea justo cuando el cliente entra a repasar lo que pagó. **§4.quater** deja la receta reproducible y las cuatro puertas de validación de `OrderCreator`. |
| `specs/landing-white-label.md` | 🟦 **EN REVISIÓN** (`DECISIONES #136`, 2026-08-25) — **qué es DATO, qué es PÁGINA y qué es TEMA** en una landing multi-cliente. ⚠️ **Empieza por §1**: de los cinco problemas que enunció el owner, **uno era falso** (el CMS solo necesita 2 modelos más), **otro iba al revés** (el copy vive en el REPO) y **otro era peor** (`/servicios` tiene 0 productos vinculados y 2 tablas de precios TECLEADAS). La línea sale de una medida: landing actual y mockup del cliente nuevo **coinciden en 3 de 6 secciones, y son las 3 del motor de reservas**. ⚠️⚠️ **§4.5: el tema son TRES mecanismos, no uno** —y el compartido cajón↔landing que se temía imposible ya existe y ya tiene guarda—. ▶ **TANDA A CERRADA** (`#138`→`#143`); la **B** en curso. ⚠️ **Este documento acumula TRES afirmaciones PROPIAS desmentidas al medirlas** (§4.5.1 ×2 y §4.5.2): lee cada corrección antes que el texto que corrige. ⚠️ **La tanda C toca AFORO y PAY y NO se implementa desde aquí** (§7). |
| `specs/google-reviews.md` | ⬜ **BORRADOR — pendiente del owner** (2026-08-25). Traer a la landing las reseñas y la valoración de **Google**, por encargo del owner al dimensionar la tanda B. ⚠️⚠️ **Empieza por §1.3: CINCO restricciones DURAS verificadas contra la documentación oficial**, y tres chocan con invariantes ya endurecidos aquí —máximo **5 reseñas sin poder elegirlas** (el parque pierde el control de su portada, §1.4) · **prohibido cachear o almacenar** salvo el `place_id` · **atribución con avatar obligatoria** contra una CSP `img-src 'self' data:` · SKU más caro, **el coste escala con el tráfico de la home**—. **§3 son las TRES decisiones que necesita el owner** y sin las que §4 no se puede cerrar; una de ellas (la caché) es **cumplimiento, no ingeniería**. |
| `specs/mis-reservas-por-reserva.md` | ✅ **EJECUTADA** (`DECISIONES #126`, 2026-08-23) — «Mis reservas» se lista **por RESERVA, no por pedido**, con el pasado en un **historial aparte** tras un CTA. Se conserva por lo que MIDIÓ: ⚠️ **§3.4 es lo que hay que leer** —partir la pantalla en dos cambia un problema de ORDEN por uno de PÉRDIDA, y por eso los dos ámbitos son **un solo predicado con dos lados**—, más la lección de que dos `whereNotNull` redundantes **se tapan entre sí y ninguna se puede medir mutándola** (`#112` otra vez). La cabecera enumera las cuatro cosas que la ejecución cambió del diseño, todas a mejor. |
| `specs/account-context-vue.md` | ✅ **EJECUTADO** (`DECISIONES #123`, 2026-08-23) — el **último componente Livewire del layout** pasa a Vue. Se conserva porque su valor está en lo que MIDIÓ: la revisión adversarial ×3 lo declaró **INSUFICIENTE** y destapó dos cosas que nadie vigilaba —el **`no-store` de todas las páginas web lo ponía un accidente de Livewire**, y **`route('logout')` aparece UNA sola vez en toda la aplicación**— más un gate de iconos **ciego a 10 de sus 32 componentes**. §8.1 enumera las cuatro afirmaciones del autor que no salían del código. |
| `specs/waiver-probatorio.md` | 🟦 **Subsistema B de Fase 6 — CÓDIGO COMPLETO** (`DECISIONES #142`; ejecución `#160` · `#161` · `#163` · `#166`, spec **§9**; subsistema revisado de forma adversarial —spec §10, `#169`—; **la tanda 4 que exigió esa revisión, hecha** —§9.11, `#171`→`#180`— **y su propia revisión aplicada** —§9.12, `#183`—; guion `VERIFICACION-E2E-CAJON.md` §5.nonies recorrido en headless dos veces, la última con la conducta definitiva, 111/111 ✓). Sigue 🟦 por el owner: ✅ en navegador, texto definitivo, retención. |
| `specs/desmontar-view-order.md` | 🟦 **Diseño del desmontaje del god-class del panel**, pendiente de revisión adversarial y del ✅ del owner (2026-08-26). ⚠️ **La cifra de `DEUDA` estaba caducada**: son **5.280** líneas, no 5.029. ▶ **Lo que decide el diseño, medido**: solo **5 propiedades públicas** —4 del calendario—, así que el fichero es GRANDE pero **no enmarañado**, y hay red (**16 ficheros / ~285 casos**). Cuatro extracciones **por riesgo creciente** y el dinero la última. ❗ **§1.4: el panel tiene su PROPIA disponibilidad** —cero contratos de Booking, `Slot::query()` a mano— y §4.2 avisa de que sustituirla puede destapar una diferencia de CONDUCTA, que es un hallazgo y no un error de la mudanza. |
| `specs/menores-a-cargo.md` | 🟦 **Subsistema C de Fase 6 — TANDAS 1, 2 y 3 EN EL ÁRBOL** (`#191` el núcleo + la API · `#198` la FIRMA DEL MENOR con la cadena por (titular, sujeto) —`#197`, owner— · **`#199` la ZONA DEL CAJÓN**, medida y subida por feature, guion en headless 20/20). ▶ **Empieza por §9**: §9.1/§9.7/§9.8 qué existe, §9.5 las cinco decisiones TOMADAS, §9.8.4 lo que queda — la **tanda 4 (el embudo)** y el ✅ del owner. Nombre y fecha de nacimiento; la edad se **deriva y nunca se persiste**; «quitar» es desvincular si hay firma detrás. ⚠️ **§4.6**: Booking **no puede mirar a Identity**. §4.7: **dos puertas** para asignar. |
| `specs/identidad-qr-puerta.md` | 🟦 **Subsistema A de Fase 6**. El carné QR del cliente y la pantalla que ve el empleado. ⚠️ **Contiene la segunda reversión de `#142`**: la puerta deja de ser «privacy-by-design mínima». ⚠️⚠️ **§4.4 es el hallazgo**: `RGPD-06` nació porque una credencial nueva no entró en la purga; un carné es exactamente la siguiente, y entra en `revokeAllAccess()` desde el primer commit. §3 mide por qué el payload **no puede ser una URL** y §4.8 por qué **un temporizador de navegador no es una garantía**. |
| `specs/lealtad-jumppoints.md` | 🟦 **Subsistema D de Fase 6**. Ledger append-only con saldo derivado; vale **en especie** canjeado **en puerta**, que es la decisión que lo mantiene fuera del núcleo de dinero. ⚠️ **§4.2 es el corazón**: los puntos se abren **después de la visita**, lo que elimina por construcción el agujero comprar→canjear→reembolsar sin necesitar ninguna tarea programada. ⚠️ **§4.5**: no es dinero, **pero se protege como si lo fuera** — el canje entra en el `CRITICAL_RE` del `pre-push`. |
| `specs/tema-por-instalacion.md` | ⬜ **BORRADOR — pendiente del owner** (2026-08-27). Los **mecanismos** que al paquete de tema de un cliente le faltan para poder existir: los dos fondos de sección, el segundo gris, `--sheet`, la escala de radios y las fuentes por instalación. Hermana de `landing-white-label.md` §4.5, que decidió los tres mecanismos del tema y ejecutó la tanda A; ésta cubre lo que aquélla **dio por supuesto y no existe**. ⚠️⚠️ **Empieza por §1.7**: el CSS ya está tokenizado, así que **el 45 % de los usos de `var()` se invierten solos** y el trabajo real son **52 literales en 44 declaraciones**, en tres familias con rol distinto. ⚠️ **§1.3 no es una preferencia, es aritmética**: nuestro gris único da **3,28 sobre tinta** (falla AA) y **ningún gris puede pasar en los dos fondos**. ⚠️ **Corrige dos afirmaciones de la doc vigente**: las sombras **no** tienen «cero tokens» (60 de 68 llevan `var()`; lo que falta es la escala) y la tipografía del cliente **no** es un problema de CSP (Bunny sirve las cinco familias). ❗ Lo único pendiente del owner es la **escala de sombra**: es lo único que movería píxeles en el producto. |
| `../openapi/v1.yaml` | El **contrato** de la API v1 (OpenAPI 3.0.3, escrito a mano). No vive en `docs/` porque no es documentación: es el artefacto contra el que se validan los tests y, en Fase 6, la app móvil. Manda sobre el código (`DECISIONES #21`). |

## Base heredada (adaptada del proyecto origen el 2026-08-12)
> Describen la base tal como se heredó; el refactor puede haberlas cambiado.
> **Verificar contra el código antes de construir encima** (`CONVENCIONES §7`).

| Doc | Qué es |
|---|---|
| `INVARIANTES.md` | 58 invariantes de no-regresión (dinero · aforo · RGPD · seguridad · rendimiento · suite). **Leer antes de tocar esas áreas.** |
| `ARQUITECTURA.md` | Stack real, estructura (`app/Domain/<Contexto>/`), white-label 3 capas, composer global memoizado. |
| `MODELO-DATOS.md` | Mapa de BD **regenerado desde el código** (33 modelos · 81 migraciones), por dominios, con rarezas heredadas. |
| `SEGURIDAD.md` | Estándar transversal nivel Reforzado (ASVS/NIST): 12 reglas + estado heredado. |
| `TESTING.md` | Suite (paralelo paratest), anti-red, fakes por proveedor, Unit/Feature, comandos de verificación con MySQL real. |
| `FLUJOS.md` | Los 6 recorridos de usuario heredados + reglas transversales. |
| `REQUISITOS.md` | Roles + inventario funcional de los 6 módulos heredados. |
| `MAPA-PAGINAS.md` | Rutas públicas/cuenta/admin con permisos (contrastar con `routes/`). |
| `PANEL-ADMIN.md` | Capacidades del panel Filament + inventario real de Resources/Pages. |
| `OPERATIVA-SECTOR-ORIGEN.md` | Sector origen (parque): sistemas físicos, canje en puerta, supuesto «cupo online ≠ aforo físico». Explica POR QUÉ el dominio es como es. |

## Sistemas implementados (`sistemas/`)
| Doc | Sistema |
|---|---|
| `sistemas/COMPRA-PRODUCTOS.md` | Catálogo unificado («todo es producto») + flujo de compra tipo ROLLER. |
| `sistemas/REDSYS.md` | Integración de pagos: firma, retorno/notificación idempotente, reembolso REST. |
| `sistemas/DEPOSITO.md` | Señal/depósito (pago parcial online, resto presencial). |
| `sistemas/POSTFORM-INVITADOS.md` | Datos por invitado post-reserva (signed URL, PDF, RGPD menores). |
| `sistemas/SERVICIOS-CMS.md` | Entidad CMS `LandingService` (clasificación de superficie, modelo A). |
| `sistemas/OFERTAS-WIDGET.md` | Widget de ofertas informativo + CMS `Offer` (primer FileUpload real). |
| `sistemas/COOKIES.md` | Consentimiento de cookies (banner 2 capas + bloqueo previo). ✅ implementado. |
| `sistemas/UI-SPINNER.md` | Sistema de feedback de carga. |

## Qué NO hay aquí (y dónde está)
Los trackers del ciclo de vida del cliente origen (producción, handoffs, audits de copys,
datos reales) viven SOLO en el repo origen — no se portaron a propósito (`DECISIONES #8`).

### Punteros fantasma en comentarios del código (equivalencias)
El código heredado cita ~71 veces docs y decisiones del **repo origen** que aquí no existen.
(Convención: en la doc, el corpus del origen se menciona **sin** prefijo `docs/`, para que
`docs-check` no lo confunda con un enlace de este repo.) Si un comentario te manda a uno de
estos, su equivalente actual es:

| Referencia en el código | Equivalente en este repo |
|---|---|
| `PLAN-REDSYS.md` | `sistemas/REDSYS.md` |
| `PLAN-COMPRA-PRODUCTOS.md` | `sistemas/COMPRA-PRODUCTOS.md` |
| `PLAN-FASE-7-PANEL.md` | `PANEL-ADMIN.md` |
| `PLAN-COOKIES.md` | `sistemas/COOKIES.md` |
| `04-MODELO-DATOS.md` | `MODELO-DATOS.md` (regenerado; el del origen estaba desfasado) |
| `UI-SPINNER.md` (sin ruta) | `sistemas/UI-SPINNER.md` |
| `10-DESPLIEGUE.md`, `08-OPERATIVA-FISICA.md`, `02-CUESTIONARIO.md` | no portados (ciclo de vida del cliente) |
| `DECISIONES.md #N` en comentario HEREDADO (fichero no tocado desde la portación 2026-08-12) | ledger del origen, no portado — NO resolver contra el `DECISIONES.md` local aunque el número exista; el porqué relevante suele estar inline en el comentario |

Los punteros se reescriben al equivalente actual **al tocar cada fichero** (no en barrido).
