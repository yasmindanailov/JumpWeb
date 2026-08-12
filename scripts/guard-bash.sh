#!/usr/bin/env bash
# Guard PreToolUse de Bash (DECISIONES #11.d) — la barrera que los deny de prefijo no dan:
#  · el repo del cliente origen (~/proyectos/jumpingjump) NO se toca desde JumpWeb (regla 7),
#    tampoco en lectura vía grep/rg/find/ls (los deny Read/Edit/Write no cubren Bash);
#  · migrate:fresh / db:wipe quedan bloqueados en CUALQUIER forma (bash -c, sh -lc, etc.).
# Recibe el tool_input JSON por stdin; exit 2 = bloquear con mensaje. Fail-open si no hay
# python3 o el JSON no parsea (el allowlist/deny normal sigue aplicando).
set -uo pipefail

input=$(cat)
cmd=$(python3 -c 'import json,sys; print(json.load(sys.stdin).get("tool_input",{}).get("command",""))' <<<"$input" 2>/dev/null) || exit 0
[[ -z "$cmd" ]] && exit 0

# Excepción: un `git commit` PURO (sin encadenar ni sustituir) solo transporta texto —
# su mensaje puede citar comandos peligrosos sin ejecutarlos (aprendido en vivo: el guard
# bloqueó el commit que documentaba al propio guard).
if [[ "$cmd" == git\ commit* ]] && ! grep -qE '[;&|<>`]|\$\(' <<<"$cmd"; then
    exit 0
fi

if grep -qi 'proyectos/jumpingjump' <<<"$cmd"; then
    echo 'BLOQUEADO (guard-bash): el repo del cliente origen no se toca desde sesiones de JumpWeb — ni en lectura (CLAUDE.md regla 7, DECISIONES #11.d).' >&2
    exit 2
fi
if grep -qE 'migrate:fresh|db:wipe' <<<"$cmd"; then
    echo 'BLOQUEADO (guard-bash): comando destructivo de BD (DECISIONES #11.d). Si es intencional de verdad, es decisión del owner (CONVENCIONES §9.4).' >&2
    exit 2
fi
exit 0
