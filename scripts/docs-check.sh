#!/usr/bin/env bash
# docs-check — gate documental (DECISIONES #10). Estático y rápido (<1 s), sin Docker.
# La doc es la memoria del equipo (100% agentes IA): este script comprueba que no miente.
#   1. Los enlaces `docs/…`/`sistemas/…` citados en la doc resuelven a ficheros reales.
#   2. Las citas «§N» en líneas que mencionan CONVENCIONES/INVARIANTES apuntan a secciones
#      reales (cualquier forma: «§7», «(§7 y §5)», «§3.bis/§3.ter»…).
#   3. Las rutas de código `.php` citadas en la doc existen en el árbol.
#   4. Prohibido citar `fichero.php:línea` (deriva en silencio; cita por símbolo — §4).
#   5. Los recuentos canónicos declarados cuadran con el código. Formas verificadas
#      (CONVENCIONES §4): «N modelos ·» · «· N migraciones» · «N Filament Resources» ·
#      «N invariantes de no-regresión». Los aproximados llevan `~` y no se verifican.
#   6. Las citas «DECISIONES #N» apuntan a entradas que existen.
#   7. Los marcadores de fase de 00-REFACTOR.md son coherentes con sus checkboxes
#      y con ESTADO.md (precedencia: el tracker manda).
# Escape (CONVENCIONES §4): una línea con «(futuro)» o «(ejemplo)» queda exenta de los
# checks 3-4 — para specs de diseño y ejemplos pedagógicos.
# Alcance: docs/ + CLAUDE.md + README.md. No escanea código (los punteros fantasma de los
# comentarios heredados tienen tabla de equivalencias en docs/README.md).

set -uo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null)" \
    || { echo '✗ docs-check: fuera de un repo git' >&2; exit 1; }
[[ -f docs/00-REFACTOR.md && -f docs/CONVENCIONES.md && -f docs/INVARIANTES.md && -f docs/ESTADO.md ]] \
    || { echo '✗ docs-check: faltan los docs mínimos (¿repo incompleto?)' >&2; exit 1; }

shopt -s nullglob
DOCS=(CLAUDE.md README.md docs/*.md docs/sistemas/*.md)
(( ${#DOCS[@]} >= 4 )) || { echo '✗ docs-check: corpus documental vacío' >&2; exit 1; }

FAIL=0
err() { printf '✗ docs-check: %s\n' "$*" >&2; FAIL=1; }

ESCAPE_RE='\((futuro|ejemplo)\)'

# ── 1 · Enlaces a docs citados (boundary-aware: ignora URLs externas …/docs/x.md) ─────
while IFS= read -r raw; do
    ref="$raw"
    [[ "$ref" == docs/* || "$ref" == sistemas/* ]] || ref="${ref#?}"
    if [[ "$ref" == docs/* ]]; then
        [[ -f "$ref" ]] || err "enlace roto: «$ref» no existe (citado en la doc)"
    else
        [[ -f "docs/$ref" ]] || err "enlace roto: «$ref» no existe bajo docs/ (citado en la doc)"
    fi
done < <(grep -ohE '(^|[^/A-Za-z0-9_.-])(docs|sistemas)/[A-Za-z0-9_.-]+\.md' "${DOCS[@]}" | sort -u)

# ── 2 · Anclas §N hacia CONVENCIONES / INVARIANTES (por línea, cualquier forma) ───────
conv_ok() { grep -qE "^## §${1//./\\.}([^0-9]|$)" docs/CONVENCIONES.md; }
inv_ok()  { grep -qE "^## ${1%%.*} ·" docs/INVARIANTES.md; }

while IFS= read -r hit; do
    file="${hit%%:*}"; rest="${hit#*:}"; ln="${rest%%:*}"; line="${rest#*:}"
    has_conv=0; has_inv=0
    [[ "$line" == *CONVENCIONES* ]] && has_conv=1
    [[ "$line" == *INVARIANTES* ]] && has_inv=1
    while IFS= read -r sec; do
        [[ -z "$sec" ]] && continue
        ok=0
        [[ $has_conv -eq 1 ]] && conv_ok "$sec" && ok=1
        [[ $ok -eq 0 && $has_inv -eq 1 ]] && inv_ok "$sec" && ok=1
        if [[ $ok -eq 0 ]]; then
            dest='CONVENCIONES/INVARIANTES'
            [[ $has_conv -eq 1 && $has_inv -eq 0 ]] && dest='CONVENCIONES'
            [[ $has_conv -eq 0 && $has_inv -eq 1 ]] && dest='INVARIANTES'
            err "ancla rota en $file:$ln — «§${sec}» no existe en $dest"
        fi
    done < <(grep -oE '§[0-9]+(\.[a-z]+)?' <<<"$line" | sed 's/§//g' | sort -u)
done < <(grep -Hn -e CONVENCIONES -e INVARIANTES "${DOCS[@]}" | grep '§[0-9]' || true)

if grep -qr '§Verificación' docs/ CLAUDE.md README.md 2>/dev/null; then
    err 'ancla prohibida: «§Verificación» (usar «CONVENCIONES §7»)'
fi

# ── 3 · Rutas de código citadas existen (escape: líneas «(futuro)»/«(ejemplo)») ───────
while IFS= read -r path; do
    [[ -f "$path" ]] || err "código citado inexistente: «$path» (si es diseño, marca la línea con «(futuro)»)"
done < <(grep -h . "${DOCS[@]}" | grep -vE "$ESCAPE_RE" \
         | grep -oE '(app|database|routes|tests|config)/[A-Za-z0-9_/.-]+\.php' \
         | grep -v '\*' | grep -v '\.\.\.' | sort -u)

# ── 4 · Sin citas fichero:línea (mismo escape) ────────────────────────────────────────
if grep -rnE '\.php:[0-9]+' docs/ CLAUDE.md README.md 2>/dev/null | grep -vE "$ESCAPE_RE" | grep -q .; then
    grep -rnE '\.php:[0-9]+' docs/ CLAUDE.md README.md | grep -vE "$ESCAPE_RE" | head -5 >&2
    err 'citas por número de línea (arriba): cita por símbolo/método (CONVENCIONES §4)'
fi

# ── 5 · Recuentos canónicos ───────────────────────────────────────────────────────────
real_models=$(ls app/Models/*.php 2>/dev/null | wc -l)
real_migrations=$(ls database/migrations/*.php 2>/dev/null | wc -l)
real_invariants=$(grep -c '^| \*\*' docs/INVARIANTES.md)
real_resources=$(ls -d app/Filament/Resources/*/ 2>/dev/null | wc -l)

check_count() { # $1=patrón grep · $2=valor real · $3=descripción del comando
    while IFS= read -r m; do
        [[ "$m" == *'~'* ]] && continue
        n=$(grep -oE '[0-9]+' <<<"$m" | head -1)
        [[ "$n" == "$2" ]] || err "la doc declara «$m» y hay $2 ($3)"
    done < <(grep -ohE "$1" "${DOCS[@]}" | sort -u)
}
check_count '~?[0-9]+ modelos ·' "$real_models" 'ls app/Models/*.php | wc -l'
check_count '· ~?[0-9]+ migraciones' "$real_migrations" 'ls database/migrations | wc -l'
check_count '~?[0-9]+ invariantes de no-regresión' "$real_invariants" "grep -c '^| **' docs/INVARIANTES.md"
check_count '~?[0-9]+ Filament Resources' "$real_resources" 'ls -d app/Filament/Resources/*/ | wc -l'

# ── 6 · Citas «DECISIONES #N» apuntan a entradas reales ───────────────────────────────
while IFS= read -r n; do
    grep -qE "^## #${n} ·" docs/DECISIONES.md \
        || err "cita rota: «DECISIONES #${n}» — esa entrada no existe en docs/DECISIONES.md"
done < <(grep -h 'DECISIONES' "${DOCS[@]}" | grep -oE '#[0-9]+' | tr -d '#' | sort -un)

# ── 7 · Coherencia de marcadores de fase (00-REFACTOR.md manda) ───────────────────────
# Regla: todo [x] y ningún [ ] ⇒ ✅ · mezcla ⇒ 🟦 · todo [ ] ⇒ ⬜ (❗ siempre válido).
coherence=$(awk '
    function flush() {
        if (fase == "") return
        if (marker == "") { printf "«Fase %s»: cabecera SIN marcador (⬜/🟦/✅/❗)\n", fase; return }
        if (marker == "❗") return
        verdict = (open == 0 && done > 0) ? "✅" : (done > 0 ? "🟦" : "⬜")
        if (marker != verdict)
            printf "«Fase %s» marcada %s pero sus checkboxes ([x]=%d, [ ]=%d) piden %s\n", fase, marker, done, open, verdict
    }
    /^### Fase/ {
        flush()
        fase = $3; open = 0; done = 0
        marker = ""
        for (i = 1; i <= NF; i++) if ($i ~ /^(✅|🟦|⬜|❗)$/) marker = $i
        next
    }
    /^## / { flush(); fase = "" }
    /^[[:space:]]*- \[ \]/ { open++ }
    /^[[:space:]]*- \[[xX]\]/ { done++ }
    END { flush() }
' docs/00-REFACTOR.md)
[[ -n "$coherence" ]] && while IFS= read -r line; do err "00-REFACTOR.md: $line"; done <<<"$coherence"

# ESTADO.md no puede contradecir al tracker: «Fase N … COMPLETA» exige cabecera ✅.
while IFS= read -r m; do
    [[ -z "$m" ]] && continue
    grep -qiE '\b(no|aún|todavía)\b' <<<"$m" && continue   # negaciones veraces no cuentan
    n=$(sed -E 's/^[Ff]ase ([0-9]+).*/\1/' <<<"$m")
    grep -qE "^### Fase ${n}[^0-9].*✅" docs/00-REFACTOR.md \
        || err "ESTADO.md dice «${m}» pero la cabecera de esa fase en 00-REFACTOR.md no es ✅ (el tracker manda — DECISIONES #10)"
done < <(grep -oiE 'Fase [0-9]+[^.]{0,60}COMPLETA' docs/ESTADO.md | sort -u || true)

if [[ $FAIL -eq 0 ]]; then
    echo "✓ docs-check: doc coherente (${real_models} modelos · ${real_migrations} migraciones · ${real_invariants} invariantes · ${real_resources} Resources)."
else
    echo '✗ docs-check: la doc miente en algo (arriba). En un repo 100% agentes, doc rota = contexto que miente al siguiente agente.' >&2
    exit 1
fi
