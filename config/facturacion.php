<?php

return [
    'base_url'      => env('NANIVA_API_URL', 'https://fe.naniva.cloud/api/v1'),
    'token'         => env('NANIVA_TOKEN', ''),
    'ruc_emisor'    => env('NANIVA_RUC_EMISOR', ''),
    'serie_boleta'  => env('NANIVA_SERIE_BOLETA', 'B001'),
    'serie_factura' => env('NANIVA_SERIE_FACTURA', 'F001'),
];
