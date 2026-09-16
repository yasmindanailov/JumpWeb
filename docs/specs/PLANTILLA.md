# [SPEC] «nombre del diseño» — PLANTILLA

> Estado: diseño (⬜ borrador · 🟦 en revisión · ✅ aprobado → a implementar) ·
> Última actualización: AAAA-MM-DD · Decisión asociada: DECISIONES «#N» al aprobarse.

Copia este fichero a `docs/specs/` con nombre propio para TODO diseño previo a
implementación (CONVENCIONES §5). El spec es artefacto de primera clase: otro agente lo
revisa ANTES de escribir código, y quien implementa trabaja CONTRA él, no contra su memoria.

## §0 · Antes de tocar
Obligatorio y **≤ 2 KB** (lo mide `docs-check`, check 10). Es lo ÚNICO que carga el arranque de una
sesión: el resto de la spec se lee por secciones. En viñetas: la regla que ordena todo · por dónde empezar
(§) · las trampas que aplican ANTES de tocar (con su `#N`) · estado y qué queda · invariantes y ficheros del
`CRITICAL_RE` si toca dinero, aforo, RGPD o seguridad. Una trampa nueva se escribe aquí o en el cuerpo, nunca
en la fila de `CLAUDE.md`, que es una línea.

## 1. Contexto y problema
Qué duele hoy, con evidencia (comandos, medidas, docs).

## 2. Objetivo
Criterios de éxito medibles. Qué queda explícitamente FUERA de alcance.

## 3. Opciones consideradas
Mínimo dos, con la descartada y su porqué (la opción no elegida es información).

## 4. Diseño elegido
Componentes, contratos y datos. Las rutas de código que aún no existen se marcan
con «(futuro)» en su línea para el gate documental (ejemplo).

## 5. Impacto en invariantes
IDs de `INVARIANTES.md` afectados (PAY-/AFORO-/RGPD-/SEC-/PERF-/SUITE-NN) o «ninguno».
Si un invariante cambia, la decisión va a `DECISIONES.md` (CONVENCIONES §9.1-2: eso es
del owner).

## 6. Plan de verificación empírica
Tests que se escribirán y comandos con los que se demostrará (DoD §3.bis: sin esto,
el diseño no puede llegar a ✅).

## 7. Revisión y decisión
Quién lo revisó (agente/owner), qué cambió, y la entrada final en `DECISIONES.md`.
