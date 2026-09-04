<?php

return [

    /*
     * =========================================================
     * PROTECCIÓN DEL LOGIN
     * =========================================================
     *
     * No bloqueamos globalmente una cuenta únicamente porque
     * alguien falle varias veces contra su correo. Eso permitiría
     * provocar un DoS deliberado contra usuarios legítimos.
     *
     * La defensa combina:
     *
     * 1. IP + identificador
     * 2. actividad total de una IP
     */

    'login_rate_limit' => [

        /*
         * Ventana dentro de la cual se cuentan los fallos.
         */
        'window_minutes' => 15,

        /*
         * Máximo de fallos contra la misma cuenta
         * desde la misma IP.
         */
        'pair_max_failures' => 5,

        /*
         * Máximo de fallos totales permitidos desde una IP,
         * incluso utilizando diferentes correos.
         */
        'ip_max_failures' => 20,

    ],

];