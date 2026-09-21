#!/usr/bin/env python3
"""Arnés de mutación de la T2·7: lo que vio el ojo del owner en el panel, y el corte de red
(`docs/specs/google-business-profile.md` §4.1; `DECISIONES #524`, `#733`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · **sin red es un fallo PASAJERO**, traducido en el envoltorio único —la petición, su reintento
    tras un 401 y la del token, cada una con su protección— y en el canje de OAuth; se registra y
    `verify` lo dice y sale en rojo;
  · **toda hora del panel es la del PARQUE** (`DisplayTime`), no la del servidor (UTC);
  · el estado dice lo que QUEDA: con la ficha elegida no pide elegirla, y sin ella sí;
  · la última pasada completa, su aviso de vieja y el «todavía ninguna»;
  · «:count EN GOOGLE», no «publicadas»;
  · «en la web» / «de reserva» con la MISMA regla que las tarjetas de la portada;
  · la respuesta, las fotos y la cara se ven en el panel, y solo si el nombre es nuestro;
  · ninguna clave de la ficha falta en chino, y los motivos salen del catálogo.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t2-7.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

API = 'app/Domain/Platform/Services/GoogleBusinessApi.php'
EXC = 'app/Domain/Platform/Exceptions/GoogleBusinessApiException.php'
OAUTH = 'app/Domain/Platform/Services/GoogleBusinessOAuth.php'
VERIFY = 'app/Console/Commands/VerifyGoogleBusiness.php'
PAGE = 'app/Filament/Pages/GoogleBusinessProfilePage.php'
VIEW = 'resources/views/filament/pages/google-business-profile.blade.php'
SRC = 'app/Domain/Content/Services/BusinessProfileSocialProof.php'
REASON = 'app/Domain/Content/Enums/GoogleReviewSuppressionReason.php'
ES = 'lang/es/admin.php'
ZH = 'lang/zh_CN/admin.php'

A = 'GoogleBusinessApiTest::'
P = 'GoogleBusinessProfilePageTest::'
DT = r'\App\Domain\Platform\Services\DisplayTime::format('
IMG = r'\App\Domain\Content\Services\GoogleReviewImages::isOwnName('


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    # ── Sin red (el envoltorio único, §4.2·6) ───────────────────────────────────────────────────
    (
        # ❗❗ El corazón de la tanda: sin esto un corte sale como `ConnectionException` y nadie arriba
        # la atrapa — la pantalla da un 500 y la pasada revienta.
        'send() atrapa el corte',
        API,
        '        } catch (ConnectionException) {\n            $error = GoogleBusinessApiException::unreachable();',
        '        } catch (\\LogicException) {\n            $error = GoogleBusinessApiException::unreachable();',
        php(A + 'test_sin_red_al_pedir_las_cuentas_es_un_fallo_pasajero_y_se_registra_sin_el_token'),
    ),
    (
        # Un corte en NUESTRA red no dice nada del permiso del parque: apagarla la dejaría caída.
        'un corte es PASAJERO, no apaga la conexión',
        EXC,
        '        return new self(null, 0, self::UNREACHABLE,',
        '        return new self(GoogleBusinessStatus::Expired, 0, self::UNREACHABLE,',
        php('GoogleBusinessSyncTest::test_sin_red_la_pasada_falla_sin_apagar_la_conexion_y_suelta_el_candado'),
    ),
    (
        'el corte se REGISTRA',
        API,
        "            Log::warning('google_business.api_failed', [\n                'http' => $error->httpStatus,\n                'reason' => $error->reason,\n                'estado' => null,\n            ]);\n",
        '',
        php(A + 'test_sin_red_al_pedir_las_cuentas_es_un_fallo_pasajero_y_se_registra_sin_el_token'),
    ),
    (
        'la petición principal pasa por send()',
        API,
        '        $response = $this->send(fn (): Response => $this->client($acceso)->get($url, $query));\n\n        if ($response->status() === 401) {',
        '        $response = $this->client($acceso)->get($url, $query);\n\n        if ($response->status() === 401) {',
        php(A + 'test_sin_red_al_pedir_las_cuentas_es_un_fallo_pasajero_y_se_registra_sin_el_token'),
    ),
    (
        'el reintento del 401 pasa por send()',
        API,
        '            $acceso = $this->accessTokenFor($refreshToken);\n            $response = $this->send(fn (): Response => $this->client($acceso)->get($url, $query));',
        '            $acceso = $this->accessTokenFor($refreshToken);\n            $response = $this->client($acceso)->get($url, $query);',
        php(A + 'test_sin_red_en_el_reintento_tras_un_401_es_un_fallo_pasajero'),
    ),
    (
        'la petición del token pasa por send()',
        API,
        "        $response = $this->send(fn (): Response => Http::asForm()\n            ->timeout(self::TIMEOUT_SECONDS)\n            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)\n            ->post(GoogleBusinessOAuth::TOKEN_ENDPOINT, [\n                'client_id' => $credentials->clientId,\n                'client_secret' => $credentials->secret(),\n                'refresh_token' => $refreshToken,\n                'grant_type' => 'refresh_token',\n            ]));",
        "        $response = Http::asForm()\n            ->timeout(self::TIMEOUT_SECONDS)\n            ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)\n            ->post(GoogleBusinessOAuth::TOKEN_ENDPOINT, [\n                'client_id' => $credentials->clientId,\n                'client_secret' => $credentials->secret(),\n                'refresh_token' => $refreshToken,\n                'grant_type' => 'refresh_token',\n            ]);",
        php(A + 'test_sin_red_al_pedir_el_token_es_un_fallo_pasajero'),
    ),
    (
        'el canje de OAuth atrapa el corte',
        OAUTH,
        '        } catch (ConnectionException) {\n            // ⚠️ Sin esto la vuelta daba un 500',
        '        } catch (\\LogicException) {\n            // ⚠️ Sin esto la vuelta daba un 500',
        php('GoogleBusinessOAuthTest::test_sin_red_en_el_canje_se_dice_y_no_se_guarda_nada'),
    ),
    (
        '`verify` dice que no llegó a Google',
        VERIFY,
        '        if ($e->reason === GoogleBusinessApiException::UNREACHABLE) {',
        '        if (false) {',
        php('GoogleBusinessVerifyTest::test_sin_red_lo_dice_y_sale_distinto_de_cero'),
    ),
    (
        '`verify` sale en rojo sin red',
        VERIFY,
        "No cambia el estado de la conexión.');\n\n            return self::FAILURE;",
        "No cambia el estado de la conexión.');\n\n            return self::SUCCESS;",
        php('GoogleBusinessVerifyTest::test_sin_red_lo_dice_y_sale_distinto_de_cero'),
    ),
    # ── La hora es la del PARQUE ────────────────────────────────────────────────────────────────
    (
        'quién conectó, con la hora del parque',
        VIEW,
        DT + '$conexion->connected_at)',
        "$conexion->connected_at->format('d/m/Y H:i')",
        php(P + 'test_quien_conecto_sale_con_la_hora_del_parque'),
    ),
    (
        'la última pasada, con la hora del parque',
        VIEW,
        DT + '$pasada)',
        "$pasada->format('d/m/Y H:i')",
        php(P + 'test_la_ultima_sincronizacion_completa_se_dice_con_la_hora_del_parque'),
    ),
    (
        'la fecha de la cifra, la del parque',
        VIEW,
        DT + "$resumen->fetched_at, 'd/m/Y')",
        "$resumen->fetched_at->format('d/m/Y')",
        php(P + 'test_la_cifra_dice_que_es_de_google_y_no_que_la_publicamos'),
    ),
    (
        'la fecha de una reseña, la del parque',
        VIEW,
        DT + "$resena->review_created_at, 'd/m/Y')",
        "$resena->review_created_at->format('d/m/Y')",
        php(P + 'test_la_fecha_de_una_resena_es_la_del_parque'),
    ),
    (
        'la fecha de una oculta, la del parque',
        VIEW,
        DT + "$oculta->created_at, 'd/m/Y')",
        "$oculta->created_at->format('d/m/Y')",
        php(P + 'test_la_fecha_de_una_oculta_es_la_del_parque'),
    ),
    # ── El estado dice lo que QUEDA ─────────────────────────────────────────────────────────────
    (
        'con la ficha elegida, el texto de «enlazada»',
        VIEW,
        "            {{ $this->fichaEnlazada()\n                ? __('admin.google_business.states.connected.linked')",
        "            {{ false\n                ? __('admin.google_business.states.connected.linked')",
        php(P + 'test_con_la_ficha_elegida_el_estado_no_pide_elegirla'),
    ),
    (
        '«enlazada» exige ficha elegida',
        PAGE,
        '        return $this->estado() === GoogleBusinessStatus::Connected && $this->fichaElegida() !== null;',
        '        return $this->estado() === GoogleBusinessStatus::Connected;',
        php(P + 'test_sin_ficha_elegida_el_estado_sigue_pidiendola'),
    ),
    (
        'una pasada vieja SE AVISA',
        PAGE,
        '        return $pasada !== null && $pasada->lt(now()->subDays(GoogleBusinessReviewSummary::FRESH_DAYS));',
        '        return false;',
        php(P + 'test_una_sincronizacion_de_hace_mas_de_tres_dias_se_avisa'),
    ),
    (
        'una pasada reciente NO se avisa',
        PAGE,
        '        return $pasada !== null && $pasada->lt(now()->subDays(GoogleBusinessReviewSummary::FRESH_DAYS));',
        '        return $pasada !== null;',
        php(P + 'test_la_ultima_sincronizacion_completa_se_dice_con_la_hora_del_parque'),
    ),
    (
        'sin pasada, se dice en vez de callar',
        VIEW,
        "                    : __('admin.google_business.last_sync_never') }}",
        "                    : '' }}",
        php(P + 'test_sin_ninguna_sincronizacion_se_dice_en_vez_de_callar'),
    ),
    # ── La cifra es de Google ───────────────────────────────────────────────────────────────────
    (
        '«en Google», no «publicadas»',
        ES,
        "'reviews_count' => ':count en Google · media :rating · a fecha de :date',",
        "'reviews_count' => ':count publicadas · media :rating · a fecha de :date',",
        php(P + 'test_la_cifra_dice_que_es_de_google_y_no_que_la_publicamos'),
    ),
    # ── En la web / de reserva ──────────────────────────────────────────────────────────────────
    (
        'la marca sale de la lista de la portada',
        VIEW,
        '@php($visible = in_array($resena->id, $enLaWeb, true))',
        '@php($visible = true)',
        php(P + 'test_el_panel_dice_cuales_se_ven_en_la_web_y_cuales_son_de_reserva'),
    ),
    (
        # Una copia de la regla sin el recorte diría «en la web» de las doce.
        'la lista es la MISMA consulta que las tarjetas',
        SRC,
        '        return $this->reviews()->map(fn (GoogleBusinessReview $r): int => $r->id)->values()->all();',
        '        return GoogleBusinessReview::query()->withinRetention()->get()->map(fn (GoogleBusinessReview $r): int => $r->id)->values()->all();',
        php(P + 'test_el_panel_dice_cuales_se_ven_en_la_web_y_cuales_son_de_reserva'),
    ),
    # ── Lo que hay que ver para decidir si se oculta ────────────────────────────────────────────
    (
        'la respuesta del parque se ve',
        VIEW,
        'dir="auto">{{ $resena->reply_comment }}</p>',
        'dir="auto"></p>',
        php(P + 'test_el_panel_ensena_la_respuesta_y_las_fotos_de_cada_resena'),
    ),
    (
        'las fotos se ven',
        VIEW,
        '@if (! empty($resena->photos))',
        '@if (false)',
        php(P + 'test_el_panel_ensena_la_respuesta_y_las_fotos_de_cada_resena'),
    ),
    (
        'la cara del autor se ve',
        VIEW,
        '@if ($cara && ' + IMG + '$cara))',
        '@if (false)',
        php(P + 'test_el_panel_ensena_la_respuesta_y_las_fotos_de_cada_resena'),
    ),
    (
        'una cara que no es nuestra no llega al src',
        VIEW,
        '@if ($cara && ' + IMG + '$cara))',
        '@if ($cara)',
        php(P + 'test_el_panel_no_pinta_una_imagen_que_no_tiene_nuestro_nombre'),
    ),
    (
        'una foto que no es nuestra no llega al src',
        VIEW,
        '@if (is_string($foto) && ' + IMG + '$foto))',
        '@if (is_string($foto))',
        php(P + 'test_el_panel_no_pinta_una_imagen_que_no_tiene_nuestro_nombre'),
    ),
    # ── El panel en chino ───────────────────────────────────────────────────────────────────────
    (
        'ninguna clave de la ficha falta en chino',
        ZH,
        "        'hide' => '隐藏',\n",
        '',
        php(P + 'test_ninguna_clave_de_la_ficha_falta_en_chino'),
    ),
    (
        'los motivos salen del catálogo',
        REASON,
        "        return __('admin.google_business.reasons.'.$this->value);",
        '        return $this->value;',
        php(P + 'test_los_motivos_de_ocultar_salen_en_el_idioma_del_panel'),
    ),
]


def green(cmd: list[str]) -> bool:
    return subprocess.run(cmd, cwd=ROOT, capture_output=True, text=True).returncode == 0


def main() -> int:
    for name, _path, _old, _new, cmd in MUTATIONS:
        if not green(cmd):
            print(f'CONTROL en ROJO antes de mutar («{name}»): el arnés no puede medir nada')
            return 2

    bitten = 0
    for name, path, old, new, cmd in MUTATIONS:
        file = ROOT / path
        original = file.read_text(encoding='utf-8')
        if original.count(old) != 1:
            print(f'la mutación «{name}» no encuentra su sitio EXACTO en {path}: el arnés ha caducado')
            return 2
        mutated = original.replace(old, new, 1)
        if mutated == original:
            print(f'la mutación «{name}» no cambia nada en {path}')
            return 2
        file.write_text(mutated, encoding='utf-8')
        try:
            red = not green(cmd)
        finally:
            file.write_text(original, encoding='utf-8')
        print(('MUERDE     ' if red else 'NO MUERDE  ') + name)
        bitten += int(red)

    print(f'{bitten}/{len(MUTATIONS)}')
    return 0 if bitten == len(MUTATIONS) else 1


if __name__ == '__main__':
    sys.exit(main())
