#!/usr/bin/env python3
"""Arnés de mutación de la T1·3b: elegir y revalidar la ficha de Google
(`docs/specs/google-business-profile.md` §4.2·4 y §5·SEC-07; `DECISIONES #524`).

Cada mutación rompe UNA guarda y comprueba que su caso se pone en rojo:

  · la ficha **se revalida** contra el listado de ESE token, y por **nombre exacto**;
  · en producción, la **web de la ficha** tiene que ser la del sitio; fuera **solo avisa**;
  · **cambiar de ficha se pregunta**, y volver a elegir la misma **no**;
  · una URL que no es de Google **no se publica**: `https`, host en lista y **coincidencia exacta**;
  · `www.` **no** cuenta como otro host;
  · sin nombre de recurso **no hay ficha**;
  · y pintar la pantalla **no llama** a Google sin permiso.

⚠️ Exige VERDE antes de mutar y restaura cada fichero desde su copia en memoria.

Uso: python3 scripts/mutar-gbp-t1-3b.py
"""
import pathlib
import subprocess
import sys

ROOT = pathlib.Path(__file__).resolve().parent.parent
EXEC = ['docker', 'compose', 'exec', '-T', '-u', 'sail', 'laravel.test']

VO = 'app/Domain/Platform/Services/GoogleBusinessLocation.php'
LOCS = 'app/Domain/Platform/Services/GoogleBusinessLocations.php'
CONN = 'app/Domain/Platform/Services/GoogleBusinessConnector.php'
PAGE = 'app/Filament/Pages/GoogleBusinessProfilePage.php'

T = 'GoogleBusinessLocationTest::'


def php(test: str) -> list[str]:
    return EXEC + ['php', 'artisan', 'test', '--filter', test]


MUTATIONS = [
    (
        # El identificador viaja por el navegador: sin revalidar, cambiarlo a mano apuntaría la
        # portada del parque a la ficha de otro negocio.
        'la ficha se REVALIDA contra el listado del token',
        CONN,
        '        $ficha = $this->locations->revalidate($refreshToken, $name);\n\n'
        '        if ($ficha === null) {\n'
        '            throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_NOT_YOURS);\n'
        '        }',
        '        $ficha = $this->locations->revalidate($refreshToken, $name)\n'
        "            ?? GoogleBusinessLocation::fromApi(['name' => $name]);",
        php(T + 'test_una_ficha_que_no_esta_en_el_listado_se_rechaza'),
    ),
    (
        'la revalidación compara el nombre de recurso ENTERO',
        LOCS,
        '            if (hash_equals($ficha->name, $name)) {',
        '            if (str_starts_with($ficha->name, $name)) {',
        php(T + 'test_un_nombre_que_es_prefijo_de_otro_no_cuela'),
    ),
    (
        'en producción, la web de la ficha manda',
        CONN,
        '        if (app()->isProduction()) {\n'
        '            throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_HOST_MISMATCH);\n'
        '        }\n\n',
        '',
        php(T + 'test_en_produccion_una_ficha_con_otra_web_se_rechaza'),
    ),
    (
        # Lo contrario: dura en todas partes obligaría a saltársela para poder trabajar, y una
        # guarda que se salta a diario acaba desactivada donde sí importa.
        'fuera de producción la web distinta SOLO avisa',
        CONN,
        '        if (app()->isProduction()) {\n'
        '            throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_HOST_MISMATCH);\n'
        '        }',
        '        throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_HOST_MISMATCH);',
        php(T + 'test_fuera_de_produccion_la_web_distinta_solo_avisa'),
    ),
    (
        'cambiar de ficha SE PREGUNTA',
        CONN,
        "            if (is_string($anterior) && $anterior !== '' && $anterior !== $ficha->placeId && ! $confirmed) {\n"
        '                throw GoogleBusinessException::because(GoogleBusinessException::LOCATION_CHANGED);\n'
        '            }\n\n',
        '',
        php(T + 'test_cambiar_de_ficha_se_pregunta_antes'),
    ),
    (
        # Y lo contrario: preguntar por la MISMA ficha sería ruido, y el admin aprendería a
        # confirmar sin leer — que es como se cuela el cambio de verdad.
        'volver a elegir LA MISMA ficha no pregunta',
        CONN,
        "            if (is_string($anterior) && $anterior !== '' && $anterior !== $ficha->placeId && ! $confirmed) {",
        "            if (is_string($anterior) && $anterior !== '' && ! $confirmed) {",
        php(T + 'test_volver_a_elegir_la_misma_ficha_no_pregunta_nada'),
    ),
    (
        'una URL de la ficha tiene que ser https',
        VO,
        "        if (! is_array($parts) || mb_strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {\n"
        '            return null;\n'
        '        }\n\n',
        '',
        php(T + 'test_una_url_que_no_es_de_google_no_se_publica'),
    ),
    (
        # `google.com.lo-que-sea.net` termina en algo que un `str_ends_with` daría por bueno: es el
        # truco más viejo del oficio y por eso la comparación es EXACTA.
        'el host se compara EXACTO, no por sufijo',
        VO,
        '        return in_array($host, self::GOOGLE_HOSTS, true) ? $url : null;',
        '        foreach (self::GOOGLE_HOSTS as $permitido) {\n'
        '            if (str_ends_with($host, $permitido)) {\n'
        '                return $url;\n'
        '            }\n'
        '        }\n\n'
        '        return null;',
        php(T + 'test_una_url_que_no_es_de_google_no_se_publica'),
    ),
    (
        'sin nombre de recurso NO hay ficha',
        VO,
        "        if (! is_string($name) || trim($name) === '') {\n"
        '            return null;\n'
        '        }\n\n',
        '',
        php(T + 'test_sin_nombre_de_recurso_no_hay_ficha'),
    ),
    (
        # Una ficha con `www.parque.es` y un sitio en `parque.es` son el mismo negocio: rechazarlo
        # sería un falso positivo garantizado el primer día.
        'el www. no cuenta como otro host',
        VO,
        "        return str_starts_with($host, 'www.') ? mb_substr($host, 4) : $host;",
        '        return $host;',
        php(T + 'test_el_host_de_la_ficha_se_compara_con_el_del_sitio'),
    ),
    (
        # Sin permiso, llamar sería un 401 garantizado por cada render de la pantalla.
        'sin permiso la pantalla NO llama a Google',
        PAGE,
        '        $token = $this->estado() === GoogleBusinessStatus::Connected\n'
        '            ? $this->conexion()?->readToken()\n'
        '            : null;',
        '        $token = $this->conexion()?->readToken() ?? \'\';',
        php(T + 'test_sin_permiso_la_pantalla_no_llama_a_google'),
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
