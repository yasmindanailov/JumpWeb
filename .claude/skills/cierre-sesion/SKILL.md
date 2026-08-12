---
name: cierre-sesion
description: Protocolo de cierre de sesión de JumpWeb — verificación completa (suite, Pint, build), doc al día (ESTADO, 00-REFACTOR, DECISIONES) y commit+push. Usar SIEMPRE antes de terminar una sesión de trabajo, cuando el usuario diga «cerramos», «termina la sesión», «haz handoff» o equivalente.
---

# Cierre de sesión

Ejecuta EN ORDEN. No omitas pasos; si uno falla, arréglalo o documenta el estado real.

## 1. Verificación técnica
```bash
bash scripts/docs-check.sh   # gate documental (DECISIONES #10) — rápido, sin Docker
docker compose exec -u sail -T laravel.test php artisan test --parallel
docker compose exec -u sail -T laravel.test ./vendor/bin/pint --dirty
# solo si tocaste JS/CSS/vistas con assets:
docker compose exec -u sail -T laravel.test npm run build
# si tocaste OrderCreator/RedsysReturnHandler/SlotGenerator (INVARIANTES §6):
#   php artisan redsys:verify-concurrency / purchase:verify-oversell (el pre-push lo exigirá)
```
- Suite NO verde → o lo arreglas ahora, o mueves el trabajo a rama `wip/…` y lo anotas en
  `ESTADO.md` como ❗ con el detalle del fallo. `main` nunca queda rojo (el gate de pre-push
  solo aplica a `main`; `wip/…` puede empujarse en rojo como backup).

## 2. Doc al día (en este orden)
1. `docs/00-REFACTOR.md` — marca checkboxes reales de la fase (recuerda el DoD §3.bis:
   sin prueba o sin verificación empírica NO es ✅). **El marcador de cabecera de cada fase
   es la fuente de verdad del estado** (DECISIONES #10): coherente con sus checkboxes y con
   `ESTADO.md` — `docs-check` bloquea el push si no lo es.
2. `docs/DECISIONES.md` — una línea por decisión tomada en la sesión (si hubo).
3. `docs/ESTADO.md` — reescribe «Dónde estamos» y «Próximo paso» para el SIGUIENTE agente:
   qué quedó hecho, qué quedó a medias y POR DÓNDE retomar. Sé literal, no optimista.
4. Si añadiste/renombraste docs → `docs/README.md` + tabla de enrutado de `CLAUDE.md`.

## 3. Git
```bash
git add -A && git status --short   # revisa que no entra basura
git commit    # convencional, español, cuerpo con el porqué
git push
```
- El cuerpo del commit de cierre termina con la evidencia (CONVENCIONES §8):
  `Verificación: suite N tests / M aserciones (Xs) · Pint ✓ · docs-check ✓ · build ✓/N-A`

## 4. Última comprobación
Relee tu `ESTADO.md`: ¿un agente SIN acceso a esta conversación puede retomar el trabajo
solo con eso? Si no, complétalo. Ese es el criterio de éxito del cierre.
