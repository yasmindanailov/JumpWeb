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
| `CONVENCIONES.md` | Reglas y protocolo de los agentes (DoD §3.bis, arranque/handoff §7, git §8, escalado al owner §9). |
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
| `specs/auth-en-cajon.md` | 🟦 Diseño **REVISADO** (adversarial, 2026-08-23) del **último trozo de `DECISIONES #66`**: entrar, darse de alta y recuperar contraseña dentro del cajón, y la **retirada del modal de la cabecera**. Mide que del servidor no falta nada, y destapa tres cosas que no son «pintar»: mueren dos paridades de árbol, `INVARIANTES` SEC-06 cita dos tests que se van, y el `noindex` de las tres URLs de auth **no lo asevera nadie**. |
| `../openapi/v1.yaml` | El **contrato** de la API v1 (OpenAPI 3.0.3, escrito a mano). No vive en `docs/` porque no es documentación: es el artefacto contra el que se validan los tests y, en Fase 6, la app móvil. Manda sobre el código (`DECISIONES #21`). |

## Base heredada (adaptada del proyecto origen el 2026-08-12)
> Describen la base tal como se heredó; el refactor puede haberlas cambiado.
> **Verificar contra el código antes de construir encima** (`CONVENCIONES §7`).

| Doc | Qué es |
|---|---|
| `INVARIANTES.md` | 55 invariantes de no-regresión (dinero · aforo · RGPD · seguridad · rendimiento · suite). **Leer antes de tocar esas áreas.** |
| `ARQUITECTURA.md` | Stack real, estructura (`app/Domain/<Contexto>/`), white-label 3 capas, composer global memoizado. |
| `MODELO-DATOS.md` | Mapa de BD **regenerado desde el código** (30 modelos · 72 migraciones), por dominios, con rarezas heredadas. |
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
