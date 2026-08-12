# Índice de la documentación — JumpWeb

> **No leas toda la doc.** Usa la tabla de enrutado de `../CLAUDE.md`: para cada tarea,
> solo su fila. Este índice es el mapa completo con el estado de cada documento.

## Vivos (se actualizan cada sesión)
| Doc | Qué es |
|---|---|
| `ESTADO.md` | Foto viva mínima: dónde estamos / qué sigue. **Carga obligatoria al arrancar.** |
| `00-REFACTOR.md` | Tracker VIVO del refactor de generalización (fases + checklists). |
| `DECISIONES.md` | Cronológico de decisiones con su porqué. Buscar por número, no cargar entero. |
| `CONVENCIONES.md` | Reglas y protocolo de los agentes (DoD §3.bis, handoff §7, git §8). |

## Base heredada (adaptada del proyecto origen el 2026-08-12)
> Describen la base tal como se heredó; el refactor puede haberlas cambiado.
> **Verificar contra el código antes de construir encima** (`CONVENCIONES §7`).

| Doc | Qué es |
|---|---|
| `INVARIANTES.md` | ~45 invariantes de no-regresión (dinero · aforo · RGPD · seguridad · rendimiento · suite). **Leer antes de tocar esas áreas.** |
| `ARQUITECTURA.md` | Stack real, estructura (`app/Support`), white-label 3 capas, composer global memoizado. |
| `MODELO-DATOS.md` | Mapa de BD **regenerado desde el código** (31 modelos · 70 migraciones), por dominios, con rarezas heredadas. |
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
