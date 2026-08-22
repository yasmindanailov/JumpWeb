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

    ⚠️ `login.blade.php` y `register.blade.php` conservan su bloque escrito a mano **a propósito**:
    son la REFERENCIA de `SidebarLoginParityTest`/`SidebarRegisterParityTest`, que comparan el árbol
    renderizado nodo a nodo, y no se tocan hasta que el área de cliente rehaga la auth dentro del
    cajón (`DECISIONES #112(f)`). El de `register` es además otro bloque: resumen de TODOS los
    errores para un formulario largo, no solo los globales.
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
