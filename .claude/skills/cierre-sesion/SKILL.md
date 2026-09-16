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
# si la tanda AÑADIÓ o TOCÓ fixtures con calendario (fechas, meses, tarifas por día):
bash scripts/audit-clock.sh   # ~10 pases de la suite; NO está en el pre-push (TESTING.md §2.septies)
```
⚠️ **Lo del reloj no es ceremonia**: un test que solo falla ciertos días **está rojo y aún no lo
sabes**, y con dos agentes sobre `main` un rojo que no es tuyo cuesta una sesión. La primera pasada
(`DECISIONES #162`) encontró un fixture que iba a tumbar el gate **seis días después**, sin que nadie
tocara nada.
- Suite NO verde → o lo arreglas ahora, o mueves el trabajo a rama `wip/…` y lo anotas en
  `ESTADO.md` como ❗ con el detalle del fallo. `main` nunca queda rojo (el gate de pre-push
  solo aplica a `main`; `wip/…` puede empujarse en rojo como backup).

## 2. Doc al día (en este orden)
1. `docs/00-REFACTOR.md` — marca checkboxes reales de la fase (recuerda el DoD §3.bis:
   sin prueba o sin verificación empírica NO es ✅). **El marcador de cabecera de cada fase
   es la fuente de verdad del estado** (DECISIONES #10): coherente con sus checkboxes —
   `docs-check` bloquea el push si no lo es. Sin narrativa: techo 16 KB.
2. `docs/decisiones/` — una entrada por decisión tomada en la sesión, al final del fichero de
   su centena, con el número de la BANDA de tu carril (CONVENCIONES §10.6) y ≤ 1,5 KB.
3. **Tu `docs/carriles/<carril>.md`** — REESCRIBE la foto y «por dónde retomar» para el
   SIGUIENTE agente (qué quedó hecho, qué a medias, por dónde seguir), actualiza «último usado»,
   retira del buzón lo que el otro carril ya anotó como atendido y anota como atendido lo suyo.
   Sé literal, no optimista. Solo tu fichero: los demás carriles no se tocan (DECISIONES #621).
4. Si añadiste/renombraste docs → `docs/README.md` + tabla de enrutado de `CLAUDE.md` (una
   línea por fila; las trampas van al §0 de la spec, nunca a la fila).

## 3. Git
```bash
git status --short                 # revisa qué hay; en main hay material del cliente ignorado
git add <ficheros por NOMBRE>      # nunca -A: puede haber ficheros del otro carril a medias
git commit    # convencional, español, cuerpo con el porqué
git pull --rebase && git push
```
- El cuerpo del commit de cierre termina con la evidencia (CONVENCIONES §8):
  `Verificación: suite N tests / M aserciones (Xs) · Pint ✓ · docs-check ✓ · build ✓/N-A`
  ⚠️ **Es la ÚNICA copia del contador de la suite** (DECISIONES #618): el `pre-push` busca ese
  trailer en los commits del push y lo compara con la suite que acaba de correr; sin trailer, o
  con la cifra de otra suite, el push se corta. Escribe el número que dio el run del paso 1.

## 4. Última comprobación
Relee tu fichero de carril: ¿un agente SIN acceso a esta conversación puede retomar el trabajo
solo con eso? Si no, complétalo. Ese es el criterio de éxito del cierre.
