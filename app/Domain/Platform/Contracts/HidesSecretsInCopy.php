<?php

namespace App\Domain\Platform\Contracts;

/**
 * **Un correo que lleva una CREDENCIAL en el texto** (A1 de `docs/specs/acceso-con-codigo.md`, `DECISIONES #853`): el
 * código de un solo uso para entrar. El registro de correos salientes (`RecordEmailSend`, `#794`) guarda la COPIA de cada
 * correo al cliente para enseñarla en el panel tal como salió; con este correo, esa copia sería el código en claro en la
 * base de datos y a la vista de quien tenga el permiso del registro, mientras siga vivo.
 *
 * Quien lo implementa dice QUÉ cadenas son el secreto; el registro las tapa (`•`) en el asunto y en la copia, y lo
 * demás sale tal cual. El correo que recibe el cliente no cambia.
 */
interface HidesSecretsInCopy
{
    /**
     * Las cadenas exactas que no pueden quedar en la copia, tal como aparecen en el correo.
     *
     * @return list<string>
     */
    public function secretsInCopy(): array;
}
