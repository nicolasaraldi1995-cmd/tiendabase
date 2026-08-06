<?php

/*
 * Sin este archivo, quien erraba la contraseña leía en pantalla la clave cruda
 * del mensaje: "auth.failed". Laravel cae al nombre de la clave cuando no
 * encuentra la traducción, y como APP_FALLBACK_LOCALE también es "es", no hay
 * caída al inglés que disimule.
 *
 * El mensaje no distingue "ese mail no existe" de "la contraseña está mal": eso
 * permitiría averiguar qué direcciones tienen cuenta probándolas de a una.
 */

return [
    'failed' => 'El correo o la contraseña no son correctos.',
    'password' => 'La contraseña no es correcta.',
    'throttle' => 'Demasiados intentos. Probá de nuevo en :seconds segundos.',
];
