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
#   8. Las clases de test citadas por una invariante existen en tests/ (una mención
#      HISTÓRICA se escribe tachada: `~~ClaseTest~~`).
# Escape (CONVENCIONES §4): una línea con «(futuro)» o «(ejemplo)» queda exenta de los
# checks 2-4 y 8 — para specs de diseño y ejemplos pedagógicos. Una entrada que EXPLICA un
# ancla rota o un test retirado necesita escribirlo, y el gate no puede castigar eso.
# Alcance: docs/ + CLAUDE.md + README.md. No escanea código (los punteros fantasma de los
# comentarios heredados tienen tabla de equivalencias en docs/README.md).

set -uo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null)" \
    || { echo '✗ docs-check: fuera de un repo git' >&2; exit 1; }
[[ -f docs/00-REFACTOR.md && -f docs/CONVENCIONES.md && -f docs/INVARIANTES.md && -f docs/ESTADO.md ]] \
    || { echo '✗ docs-check: faltan los docs mínimos (¿repo incompleto?)' >&2; exit 1; }

shopt -s nullglob
DOCS=(CLAUDE.md README.md docs/*.md docs/sistemas/*.md docs/specs/*.md)
(( ${#DOCS[@]} >= 4 )) || { echo '✗ docs-check: corpus documental vacío' >&2; exit 1; }

FAIL=0
err() { printf '✗ docs-check: %s\n' "$*" >&2; FAIL=1; }

ESCAPE_RE='\((futuro|ejemplo)\)'

# ── 1 · Enlaces a docs citados (boundary-aware: ignora URLs externas …/docs/x.md) ─────
while IFS= read -r raw; do
    ref="$raw"
    [[ "$ref" == docs/* || "$ref" == sistemas/* || "$ref" == specs/* ]] || ref="${ref#?}"
    if [[ "$ref" == docs/* ]]; then
        [[ -f "$ref" ]] || err "enlace roto: «$ref» no existe (citado en la doc)"
    else
        [[ -f "docs/$ref" ]] || err "enlace roto: «$ref» no existe bajo docs/ (citado en la doc)"
    fi
done < <(grep -ohE '(^|[^/A-Za-z0-9_.-])(docs|sistemas|specs)/[A-Za-z0-9_/.-]+\.md' "${DOCS[@]}" | sort -u)

# ── 2 · Anclas §N hacia CONVENCIONES / INVARIANTES ───────────────────────────────────
# ⚠️ Cada «§N» se ata al documento que lo PRECEDE en la línea, no a la línea entera. Antes se
# miraba si la línea nombraba CONVENCIONES o INVARIANTES «en algún sitio», y una línea que
# citara dos documentos validaba el ancla contra el equivocado: un `REDSYS §14` legítimo salía
# como ancla rota en cuanto alguien mencionaba CONVENCIONES en la misma frase (`#158`). Un gate
# que da FALSOS POSITIVOS enseña a reescribir la doc para contentarlo, que es peor que no tenerlo.
# Si ningún documento precede al «§N», se cae al criterio viejo (la línea) para no perder cobertura.
anchor_errors=$(perl -e '
    my ($conv_path, $inv_path, @docs) = @ARGV;
    my %conv; open(my $c, "<", $conv_path) or die;
    while (<$c>) { $conv{$1} = 1 if /^## §([0-9]+(?:\.[a-z]+)?)(?:[^0-9]|$)/ }
    my %inv;  open(my $i, "<", $inv_path)  or die;
    while (<$i>)  { $inv{$1} = 1 if /^## ([0-9]+) ·/ }   # las secciones de INVARIANTES son numéricas
    for my $f (@docs) {
        open(my $fh, "<", $f) or next;
        my $n = 0;
        while (my $line = <$fh>) {
            $n++;
            next unless $line =~ /§[0-9]/;
            next unless $line =~ /CONVENCIONES|INVARIANTES/;
            next if $line =~ /\((?:futuro|ejemplo)\)/;   # escape de CONVENCIONES §4: ejemplos pedagógicos
            # Tokens de documento y de sección, con su posición en la línea.
            my @tok;
            while ($line =~ /(CONVENCIONES|INVARIANTES|[A-Za-z0-9_-]+\.md|`[A-Z][A-Za-z0-9_-]*`)/g) {
                push @tok, [pos($line), $1];
            }
            while ($line =~ /§([0-9]+(?:\.[a-z]+)?)/g) {
                my ($at, $sec) = (pos($line), $1);
                my $owner = "";
                for my $t (@tok) { $owner = $t->[1] if $t->[0] <= $at }
                my ($want_conv, $want_inv);
                if ($owner =~ /CONVENCIONES/) { $want_conv = 1 }
                elsif ($owner =~ /INVARIANTES/) { $want_inv = 1 }
                elsif ($owner eq "") {   # ningún documento delante: criterio viejo, por línea
                    $want_conv = ($line =~ /CONVENCIONES/) ? 1 : 0;
                    $want_inv  = ($line =~ /INVARIANTES/)  ? 1 : 0;
                } else { next }          # el «§N» es de OTRO documento: no es asunto de este check
                my $ok = 0;
                $ok = 1 if $want_conv && $conv{$sec};
                $ok = 1 if $want_inv  && $inv{(split /\./, $sec)[0]};
                next if $ok;
                my $dest = $want_conv && $want_inv ? "CONVENCIONES/INVARIANTES"
                         : $want_conv ? "CONVENCIONES" : "INVARIANTES";
                print "ancla rota en $f:$n — «§$sec» no existe en $dest\n";
            }
        }
    }
' docs/CONVENCIONES.md docs/INVARIANTES.md "${DOCS[@]}" | sort -u)
[[ -n "$anchor_errors" ]] && while IFS= read -r e; do err "$e"; done <<<"$anchor_errors"

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
# Modelos en la raíz heredada Y en los módulos de Fase 2 (app/Domain/<Ctx>/Models).
real_models=$(ls app/Models/*.php app/Domain/*/Models/*.php 2>/dev/null | wc -l)
real_migrations=$(ls database/migrations/*.php 2>/dev/null | wc -l)
real_invariants=$(grep -cE '^\| [A-Z]+-[0-9]+ \| \*\*' docs/INVARIANTES.md)
if grep -qE '^\| \*\*' docs/INVARIANTES.md; then
    grep -nE '^\| \*\*' docs/INVARIANTES.md | head -3 >&2
    err 'fila de invariante SIN ID (arriba): el formato es «| PAY-NN | **…» (DECISIONES #11)'
fi
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
check_count '~?[0-9]+ invariantes de no-regresión' "$real_invariants" "grep -cE '^\| [A-Z]+-[0-9]+ \| \*\*' docs/INVARIANTES.md"
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

# ── 8 · Las clases de test citadas por una invariante EXISTEN ─────────────────────────
# `INVARIANTES.md` es el mapa que lleva a la red: cada invariante dice en qué test se
# comprueba. Si esa cita apunta a una clase que ya no existe, la invariante se queda SIN
# RED SIN HACER RUIDO — la cobertura puede seguir viva con otro nombre, pero el siguiente
# agente no la encuentra. Ya pasó dos veces (`PAY-04` en `#121`, `SEC-06` al retirar el
# modal en `#122`): las dos se re-apuntaron a mano, y nada impedía la tercera.
#
# ⚠️ El motivo por el que este check no existía: una MENCIÓN histórica y una CITA viva se
# escriben igual. La convención que las separa —y que este check exige— es el TACHADO:
# un test retirado que se nombra como historia va `~~ClaseTest~~`. Se lee bien en la doc
# y es inequívoco para la máquina.
# Alcance: solo `INVARIANTES.md`. DECISIONES y las specs son narrativa histórica y están
# llenas de nombres retirados a propósito; exigirles el tachado sería ruido, no señal.
while IFS= read -r cls; do
    [[ -z "$cls" ]] && continue
    find tests -name "${cls}.php" -print -quit 2>/dev/null | grep -q . \
        || err "INVARIANTES.md cita \`${cls}\`, que no existe en tests/. Si la cobertura se mudó, re-apunta la cita; si el test se retiró y lo nombras como historia, escríbelo tachado: ~~${cls}~~"
done < <(perl -ne 'next if /\((?:futuro|ejemplo)\)/; while (/(~~)?`[A-Za-z0-9\\]*?([A-Z][A-Za-z0-9]*Test)`/g) { print "$2\n" unless $1 }' docs/INVARIANTES.md | sort -u)

# ── 9 · Ningún marcador de conflicto de merge sobrevive en la doc ────────────────────
# ❗❗ ESTO PASÓ (`#506`, 2026-09-10): el `git pull` que abrió una sesión dejó **tres marcadores
# de conflicto sin resolver dentro de `DECISIONES.md`** —493 líneas de dos carriles vivos
# separadas por un `=======`— y **este gate pasó en verde**, el hook `pre-push` con él.
#
# ⚠️ Y no era una contradicción: los dos lados eran decisiones de bandas distintas que
# tenían que convivir. El daño no es el contenido, es la FORMA — con dos agentes trabajando
# sobre `main` (`CONVENCIONES §10.6`), el registro que el siguiente agente lee de arriba
# abajo se queda partido, y quien busque «la última decisión» encuentra un `>>>>>>>`.
#
# Alcance: la doc, que es lo que este gate gobierna. Un conflicto en código lo caza el
# intérprete; en un `.md` no lo caza nadie.
while IFS= read -r hit; do
    [[ -z "$hit" ]] && continue
    err "marcador de conflicto de merge sin resolver: ${hit%%:*} (línea ${hit#*:}). Resuelve el merge conservando lo de los DOS carriles."
done < <(grep -rn -E '^(<<<<<<< |>>>>>>> )' docs/ CLAUDE.md 2>/dev/null | cut -d: -f1,2)

if [[ $FAIL -eq 0 ]]; then
    echo "✓ docs-check: doc coherente (${real_models} modelos · ${real_migrations} migraciones · ${real_invariants} invariantes · ${real_resources} Resources)."
else
    echo '✗ docs-check: la doc miente en algo (arriba). En un repo 100% agentes, doc rota = contexto que miente al siguiente agente.' >&2
    exit 1
fi
