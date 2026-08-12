<?php

// Solo SOBREESCRIBE el mensaje de "contraseña filtrada" (HIBP) para hacerlo más claro y amable.
// El resto de mensajes de validación en inglés los aporta el framework (Laravel fusiona por ruta,
// así que este fichero parcial no rompe los demás). Ver DECISIONES #80.
return [
    'password' => [
        'uncompromised' => 'That password is too common (it appears in known data breaches). Please choose a stronger one, for example a long, memorable phrase.',
    ],
];
