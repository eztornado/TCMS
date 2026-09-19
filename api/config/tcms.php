<?php

// Configuración del core de TornadoCMS.
return [
    // Contexto de ejecución: 'web' (Docker/servidor), 'native-desktop' o
    // 'native-mobile' (binario NativePHP). Decidir SIEMPRE vía App\Support\Runtime.
    'runtime' => env('TCMS_RUNTIME', 'web'),

    // Sincronización de la app nativa contra el API central (solo en runtime
    // nativo; en web el propio servidor ES el central).
    'sync' => [
        // URL base del API central, p. ej. https://mi-tcms.com
        'central_url' => env('TCMS_SYNC_CENTRAL_URL'),

        // Nombre con el que el device se registra (p. ej. "Portátil de Ana").
        'device_label' => env('TCMS_DEVICE_LABEL'),

        // Credenciales de device para el pull/push (en el binario las guarda
        // la app al enlazarlo con su usuario; aquí sirven para sync:run).
        'device_uuid' => env('TCMS_SYNC_DEVICE_UUID'),
        'token' => env('TCMS_SYNC_TOKEN'),
    ],

    // 'stripe' | 'manual' | 'auto' (stripe si hay credenciales, si no manual)
    'payment_gateway' => env('TCMS_PAYMENT_GATEWAY', 'auto'),

    // Minutos de reserva de stock durante el checkout.
    'stock_reservation_minutes' => env('TCMS_STOCK_RESERVATION_MINUTES', 15),

    // Prefijo de numeración de pedidos: TC-2026-000123.
    'order_number_prefix' => env('TCMS_ORDER_PREFIX', 'TC'),

    // Menú del panel que se sirve en /auth/menu.
    'admin_menu' => 'admin',
];
