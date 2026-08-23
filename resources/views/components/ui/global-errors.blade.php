{{--
    Banner de errores SIN campo asociado (`_global`).

    Los cuatro formularios de «Mi cuenta» que reconfirman la contraseña mandan el aviso del
    limitador a la clave `_global` —para que no se lea como «esta contraseña está mal» debajo del
    input, que es la convención del login (L-02)— y hasta el paso 8 **tres de ellos no lo pintaban
    en ninguna parte**: al agotar los cinco intentos, el formulario no hacía nada y no decía nada.
    Es la familia de `DECISIONES #117`: «no falla, no hace nada».

    ⚠️ El bag se pasa por PROP (`:bag="$errors"`) y no se lee del ámbito: dentro de un componente
    Blade, `$errors` de un componente Livewire no es una variable compartida con la que se pueda
    contar. Explícito es una palabra más y una duda menos.

    ⚠️ **Aquí decía que `login.blade.php` y `register.blade.php` conservaban su bloque a mano porque
    eran la REFERENCIA de dos paridades de árbol** (`DECISIONES #112(f)`). Las tres plantillas del
    modal —y las dos paridades con ellas— **se retiraron el 2026-08-23** al traer la auth al cajón
    (`DECISIONES #122`), así que esa excepción ya no existe: quien escriba un formulario nuevo puede
    usar este componente sin mirar a nadie.
    ▶ Del cajón no hay nada que temer aquí: sus pantallas de auth son Vue y no pasan por este Blade.
--}}
@props(['bag'])

@php($messages = $bag->get('_global'))

@if ($messages !== [])
    <div class="auth__errors" role="alert">
        @foreach ($messages as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif
