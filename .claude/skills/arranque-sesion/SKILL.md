---
name: arranque-sesion
description: Protocolo de arranque de sesión de JumpWeb — verificar base sana (árbol, push pendiente, ramas wip, hook, stack, gates) ANTES de tocar nada. Usar SIEMPRE al empezar una sesión de trabajo, y obligatorio tras un cierre abrupto (crash, apagón, sesión cortada).
---

# Arranque de sesión

Espejo de `/cierre-sesion`: garantiza que construyes sobre base verde y no sobre los restos
de una sesión que murió a medias. Ejecuta EN ORDEN; cualquier anomalía se resuelve o se
anota en `ESTADO.md` ANTES de empezar la tarea.

## 1. Estado del repo
```bash
git status --short                      # ¿árbol limpio? Si hay restos: ¿de qué sesión son?
git log --oneline @{u}.. 2>/dev/null || echo 'sin upstream'   # commits sin push (¿cierre incompleto?)
git branch --list 'wip/*'               # trabajo aparcado — cruzar con ESTADO.md
git config core.hooksPath               # debe decir `.githooks`; si no: actívalo
git fsck --no-dangling                  # tras un crash: integridad del repo
```
- Árbol sucio o commits sin push que `ESTADO.md` no explique → **PARA**: reconcilia primero
  y deja `ESTADO.md` fiel antes de seguir. Un arranque sobre base desconocida contamina
  todo el diagnóstico posterior.

## 2. Entorno y gate documental
```bash
docker compose up -d && docker compose ps
bash scripts/docs-check.sh                                        # gate documental verde
curl -s -o /dev/null -w '%{http_code}\n' http://localhost:8081/   # web → 200
```

## 3. Suite (si procede)
Si `ESTADO.md` no acredita suite verde con fecha, o hubo crash/árbol sucio/commits huérfanos:
```bash
docker compose exec -u sail -T laravel.test php artisan test --parallel
```

## 4. Contexto (CONVENCIONES §7)
1. `docs/ESTADO.md` → 2. `docs/00-REFACTOR.md` → 3. SOLO la fila de tu tarea en la tabla
de enrutado de `CLAUDE.md`. Con base verde y contexto cargado: a trabajar.
