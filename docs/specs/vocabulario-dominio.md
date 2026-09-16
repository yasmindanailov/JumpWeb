# [SPEC] Generalización del vocabulario de dominio

> Estado: diseño 🟦 en revisión (entrada de la Fase 2; la decisión final va con el diseño de
> módulos) · Última actualización: 2026-08-12 · Decisión asociada: DECISIONES #12.d.

## §0 · Antes de tocar

- **Diseño de entrada de la Fase 2** (`#12.d`): cómo generalizar el vocabulario del sector origen (`ParkSchedule`,
  `ParkRule`, `Puerta`, acentos de zona `jump`/`kids`). La decisión final fue con los módulos
  (`modulos-dominio.md`, `#13`), y el vocabulario VIVO —término de dominio ↔ artefacto de código/BD— se
  mantiene en `GLOSARIO.md`, no aquí.
- Lo que sigue valiendo: el criterio de qué se renombra y qué se deja con tabla de equivalencias
  (`docs/README.md`, punteros fantasma); los supuestos del sector origen están en `OPERATIVA-SECTOR-ORIGEN.md`
  y no se rompen sin decisión explícita (`CONVENCIONES §6`).
- `zone: 'jump'`, el slug del primer cliente, se ha encontrado quemado en el producto más de una vez
  (`#302`): la zona se identifica por `slug` y la primera la dice el DOM, nunca el código.

## 1. Contexto y problema
El código heredado usa vocabulario del sector origen (parque de saltos): `ParkSchedule`,
`ParkRule`, namespace `Puerta`, copys de «cumpleaños/niños», accents de zona `jump`/`kids`.
El owner fijó el sector objetivo (#12.d): **ocio con aforo y franjas** (parques, escape
rooms, karting, bolos…). El mapeo término↔código completo, con estado de generalización por
fila, vive en `GLOSARIO.md` (columna Gen.) — este spec decide QUÉ se hace con cada 🏷️/❓.

## 2. Objetivo
Que un cliente nuevo del sector objetivo pueda instalarse sin que ninguna clase, tabla o
copy le hable de un negocio que no es el suyo — sin sobre-diseñar para sectores hipotéticos.
FUERA de alcance: multi-tenant, traducción de slugs de rutas (Fase 4, #12.a).

## 3. Opciones consideradas
- **A. Renombrado profundo** (Zone→Resource, Attraction→Activity, tablas incluidas):
  DESCARTADA — el sector elegido encaja con zona/atracción/franja tal cual; renombrar
  modelos exige morphMap + migración de datos (DEUDA §Alta) y no aporta al sector objetivo.
- **B. Conservadora (elegida)**: se conservan los conceptos que el sector comparte; se
  generalizan solo los nombres que atan a UN negocio concreto o a su operativa física.

## 4. Diseño elegido (propuesta para Fase 2)
| Artefacto | Decisión propuesta |
|---|---|
| `Zone`, `Attraction`, `Slot`, `TicketType` (entry/pack/addon), `Price`, `RateType`, `Season`, `SpecialDate` | SE QUEDAN — vocabulario compartido del sector objetivo. |
| `ParkSchedule` → `OperatingSchedule` (paso 6) · ~~`ParkRule` → `VenueRule`~~ **HECHO** (paso 3, 2026-08-12) | Renombrar EN Fase 2, con la modularización y DESPUÉS del morphMap. ⚠️ **Rectificado al ejecutar**: la tabla NO se renombra (`$table='park_rules'` fijo) ni el alias morph (`'park_rule'`) — son DATOS de instalaciones vivas y renombrarlos exigiría migración y riesgo a cambio de nada. Manda `modulos-dominio.md` §4. |
| Namespace `Admin\Puerta` + `PuertaSettings` | Se queda «puerta» como término de producto (ya es genérico en el sector: control de acceso presencial); solo se documenta en GLOSARIO. |
| Copys «cumpleaños/niños/invitados» (lang + `DEFAULT_GUEST_FIELDS`) | Ya son data-driven por producto (`event_fields`/`guest_fields`) o i18n editable: cada instalación redacta los suyos. Sin cambio de código. |
| Accents de zona `jump`/`kids` (tokens CSS + `ThemeSettings::ZONE_DEFAULTS`) | Fase 2/5: pasar los tokens por-zona a derivados 100% de `zones.color` (hoy ya existe el mecanismo; retirar los defaults con nombre). |
| «waiver» (sello + página + toggle puerta) | Se queda como feature OPCIONAL por instalación (`registration.*` vacío = sin sistema externo); término estándar del sector. |
| `Room` (sin lógica) | No generalizar algo sin uso: decidir drop o implementación cuando un cliente lo pida (DEUDA §Baja). |

## 5. Impacto en invariantes
Renombrar `ParkSchedule`/`ParkRule` toca AFORO-03 (generador de franjas) y el CMS de normas:
las filas de `INVARIANTES.md` viajan con el renombre (regla de «Cómo usar este documento»).
morphMap ANTES de cualquier renombre de modelos (prerequisito de Fase 2 en el tracker).

## 6. Plan de verificación empírica
La suite completa como red del renombre (2136 tests); `docs-check` verifica que la doc
acompaña; grep post-renombre de `Park` en `app/` (objetivo: 0 fuera de compat); la landing
y el panel renderizando con una instalación de ejemplo no-trampolín (escape room de prueba).

## 7. Revisión y decisión
Pendiente de revisión por el agente que diseñe los módulos de Fase 2; la decisión final se
registrará como entrada nueva en `DECISIONES.md`.
