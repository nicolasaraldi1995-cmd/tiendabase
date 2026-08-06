<?php

/*
 * Sin este archivo, el cliente que pedía recuperar su contraseña leía en
 * pantalla la clave cruda del mensaje: "passwords.sent". Laravel cae al nombre
 * de la clave cuando no encuentra la traducción, y `lang/es/` solo tenía
 * validation.php.
 */

return [
    'reset' => 'Listo, tu contraseña quedó cambiada.',
    'sent' => 'Te mandamos un mail con el link para cambiar tu contraseña.',
    'throttled' => 'Esperá unos minutos antes de volver a intentar.',
    'token' => 'Ese link para cambiar la contraseña ya no sirve. Pedí uno nuevo.',
    // A propósito no dice "ese mail no está registrado": eso permitiría probar
    // direcciones de a una y quedarse con la lista de clientes del negocio.
    'user' => 'Si ese mail tiene una cuenta, te va a llegar el link en un rato.',
];
