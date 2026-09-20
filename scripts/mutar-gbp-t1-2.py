#!/usr/bin/env python3
"""Arnés de mutación de la T1·2: la ida y la vuelta de OAuth de la ficha de Google
(`docs/specs/google-business-profile.md` §4.2·2; `DECISIONES #524`).

Cada mutación rompe UNA guarda del viaje y comprueba que su caso se pone en rojo:

  · la ida pide acceso **offline** y **consentimiento nuevo**, o no hay token de refresco;
  · a Google viaja el **hash** del `code_verifier`, nunca el secreto, y el canje lo **presenta**;
  · el **ámbito** concedido se comprueba, y **elemento a elemento**;
  · **sin token de refresco no se guarda nada**;
  · el reto es de **un solo uso**, **caduca** y se compara con `hash_equals`;
  · vuelve **el mismo usuario** y con **`settings.manage`** todavía;
  · reconectar con el **mismo** token no lo revoca, y **fuera de producción no se revoca**;
  · y la pantalla **no ofrece el botón** cuando pulsarlo no haría nada.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t1-2.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

OAUTH = 'app/Domain/Platform/Services/GoogleBusinessOAuth.php'
SESSION = 'app/Http/Auth/GoogleBusinessOAuthSession.php'
CONTROLLER = 'app/Http/Controllers/Admin/GoogleBusinessConnectController.php'
CONNECTOR = 'app/Domain/Platform/Services/GoogleBusinessConnector.php'
PAGE = 'app/Filament/Pages/GoogleBusinessProfilePage.php'

T = 'GoogleBusinessOAuthTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        'la ida pide acceso OFFLINE',
        OAUTH,
        "            'access_type' => 'offline',",
        "            'access_type' => 'online',",
        php(T + 'test_la_ida_manda_a_google_con_pkce_y_consentimiento'),
    ),
    (
        # Sin `consent`, una cuenta que ya había autorizado vuelve SIN token de refresco y la
        # conexión muere en una hora, hoy verde.
        'la ida fuerza un CONSENTIMIENTO nuevo',
        OAUTH,
        "            'prompt' => 'consent select_account',",
        "            'prompt' => 'select_account',",
        php(T + 'test_la_ida_manda_a_google_con_pkce_y_consentimiento'),
    ),
    (
        'a Google viaja el HASH, no el code_verifier',
        OAUTH,
        "            'code_challenge' => self::challengeFor($verifier),",
        "            'code_challenge' => $verifier,",
        php(T + 'test_el_code_verifier_no_viaja_a_google'),
    ),
    (
        'el canje PRESENTA el code_verifier',
        OAUTH,
        "                'code_verifier' => $verifier,\n",
        '',
        php(T + 'test_el_canje_presenta_el_code_verifier'),
    ),
    (
        'el ámbito concedido se comprueba',
        OAUTH,
        "        if (! self::grants($response->json('scope'))) {\n"
        '            throw GoogleBusinessException::because(GoogleBusinessException::SCOPE_NOT_GRANTED);\n'
        '        }\n\n',
        '',
        php(T + 'test_sin_el_ambito_concedido_no_se_guarda_nada'),
    ),
    (
        # `str_contains` daría por bueno `…/business.manage.readonly`.
        'el ámbito se compara ELEMENTO A ELEMENTO',
        OAUTH,
        "        return in_array(self::SCOPE, preg_split('/\\s+/', trim($granted)) ?: [], true);",
        '        return str_contains($granted, self::SCOPE);',
        php(T + 'test_un_ambito_que_solo_empieza_igual_no_cuenta'),
    ),
    (
        'sin token de refresco NO se guarda nada',
        OAUTH,
        "        if (! is_string($refreshToken) || $refreshToken === '') {\n"
        '            throw GoogleBusinessException::because(GoogleBusinessException::MISSING_REFRESH_TOKEN);\n'
        '        }\n\n',
        '',
        php(T + 'test_sin_token_de_refresco_no_se_guarda_nada'),
    ),
    (
        'el reto es de UN SOLO USO',
        SESSION,
        '        session()->forget(self::CHALLENGE_KEY);',
        '',
        php(T + 'test_el_reto_solo_vale_una_vez'),
    ),
    (
        'el reto CADUCA',
        SESSION,
        '        if ($at + self::CHALLENGE_TTL_SECONDS < now()->getTimestamp()) {\n'
        '            return null;\n'
        '        }\n\n',
        '',
        php(T + 'test_un_reto_caducado_no_vale'),
    ),
    (
        'el state de la vuelta tiene que ser el NUESTRO',
        SESSION,
        '        if (! hash_equals($stored, $state)) {\n'
        '            return null;\n'
        '        }\n\n',
        '',
        php(T + 'test_un_state_que_no_es_el_nuestro_no_hace_nada'),
    ),
    (
        'vuelve EL MISMO usuario que fue',
        CONTROLLER,
        "        if ($challenge['holder'] !== (int) Auth::id()) {",
        '        if (false) {',
        php(T + 'test_si_vuelve_otro_usuario_no_se_conecta_nada'),
    ),
    (
        # `panel_role` deja pasar a `staff`: la ruta sola no basta.
        'settings.manage se comprueba, no basta panel_role',
        CONTROLLER,
        "        abort_unless(Auth::user()?->hasPermission('settings.manage') === true, 403);",
        '        abort_unless(Auth::check(), 403);',
        php(T + 'test_un_staff_no_puede_conectar_la_ficha'),
    ),
    (
        # El modo de fallo más silencioso: revocar «el anterior» cuando es el mismo mata el nuevo.
        'reconectar con el MISMO token no lo revoca',
        CONNECTOR,
        '            return ($previous !== null && ! hash_equals($previous, $refreshToken)) ? $previous : null;',
        '            return $previous;',
        php(T + 'test_reconectar_con_el_mismo_token_no_lo_revoca'),
    ),
    (
        'fuera de PRODUCCIÓN no se revoca nada en Google',
        CONNECTOR,
        '        if (! app()->isProduction()) {\n'
        "            Log::info('google_business.revoke_skipped', ['reason' => 'no es producción']);\n"
        '\n'
        '            return false;\n'
        '        }\n\n',
        '',
        php(T + 'test_fuera_de_produccion_no_se_revoca_en_google'),
    ),
    (
        'la pantalla no ofrece un botón que no haría nada',
        PAGE,
        '        return $this->estado() !== GoogleBusinessStatus::Unconfigured;',
        '        return true;',
        php(T + 'test_sin_credenciales_la_pantalla_no_ofrece_el_boton'),
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
        file.write_text(original.replace(old, new, 1), encoding='utf-8')
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
