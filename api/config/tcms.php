<?php

// Configuración del core de TornadoCMS.
return [
    // 'stripe' | 'manual' | 'auto' (stripe si hay credenciales, si no manual)
    'payment_gateway' => env('TCMS_PAYMENT_GATEWAY', 'auto'),

    // Minutos de reserva de stock durante el checkout.
    'stock_reservation_minutes' => env('TCMS_STOCK_RESERVATION_MINUTES', 15),

    // Prefijo de numeración de pedidos: TC-2026-000123.
    'order_number_prefix' => env('TCMS_ORDER_PREFIX', 'TC'),

    // Menú del panel que se sirve en /auth/menu.
    'admin_menu' => 'admin',
];
