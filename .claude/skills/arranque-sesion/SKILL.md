---
name: arranque-sesion
description: Protocolo de arranque de sesión de JumpWeb — verificar base sana (árbol, push pendiente, ramas wip, hook, stack, gates) ANTES de tocar nada. Usar SIEMPRE al empezar una sesión de trabajo, y obligatorio tras un cierre abrupto (crash, apagón, sesión cortada).
---

# Arranque de sesión

Espejo de `/cierre-sesion`: garantiza que construyes sobre base verde y no sobre los restos
de una sesión que murió a medias. Ejecuta EN ORDEN; cualquier anomalía se resuelve o se
anota en tu fichero de carril (`docs/carriles/<carril>.md`) ANTES de empezar la tarea.

## 1. Estado del repo
```bash
git fetch --quiet origin && git log --oneline HEAD..origin/main   # ¿el otro carril empujó? → git pull --rebase
git status --short                      # ¿árbol limpio? Si hay restos: ¿de qué sesión son?
git log --oneline @{u}.. 2>/dev/null || echo 'sin upstream'   # commits sin push (¿cierre incompleto?)
git branch --list 'wip/*'               # trabajo aparcado — cruzar con tu carril
git config core.hooksPath               # debe decir `.githooks`; si no: actívalo
git fsck --no-dangling                  # tras un crash: integridad del repo
```
- Árbol sucio o commits sin push que tu carril no explique → **PARA**: reconcilia primero
  y deja el carril fiel antes de seguir. Un arranque sobre base desconocida contamina
  todo el diagnóstico posterior.
- Si el `pull` trae commits del carril del SPA: `npm run build:ssr` ANTES de medir la suite.

## 2. Entorno y gate documental
```bash
docker compose up -d && docker compose ps
bash scripts/docs-check.sh                                        # gate documental verde
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8081/   # web → 200
```

## 3. Suite (si procede)
Si el trailer del último commit (`git log -1 --format=%B`, «Verificación: suite N tests…») no
acredita la suite sobre ESTE árbol, o hubo crash/árbol sucio/commits huérfanos:
```bash
docker compose exec -u sail -T laravel.test php artisan test --parallel
```

## 4. Contexto (CONVENCIONES §7)
1. `docs/ESTADO.md` (índice) → **tu `docs/carriles/<carril>.md`** y el buzón de los otros →
2. `docs/00-REFACTOR.md` → 3. SOLO la fila de tu tarea en `CLAUDE.md` → el **§0** de esa spec.
Con base verde y contexto cargado: a trabajar.
