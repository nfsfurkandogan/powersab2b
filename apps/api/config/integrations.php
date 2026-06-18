<?php

return [
    'logo' => [
        'customer_sync_key' => env('LOGO_CUSTOMER_SYNC_KEY', ''),
        'product_sync_key' => env('LOGO_PRODUCT_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', '')),
        'ledger_sync_key' => env('LOGO_LEDGER_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', '')),
        'collection_sync_key' => env('LOGO_COLLECTION_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', '')),
        'pos_sale_sync_key' => env('LOGO_POS_SALE_SYNC_KEY', env('LOGO_COLLECTION_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', ''))),
        'pos_expense_sync_key' => env('LOGO_POS_EXPENSE_SYNC_KEY', env('LOGO_COLLECTION_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', ''))),
        'order_sync_key' => env('LOGO_ORDER_SYNC_KEY', env('LOGO_COLLECTION_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', ''))),
        'shipment_sync_key' => env('LOGO_SHIPMENT_SYNC_KEY', env('LOGO_ORDER_SYNC_KEY', env('LOGO_COLLECTION_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', '')))),
        'return_sync_key' => env('LOGO_RETURN_SYNC_KEY', env('LOGO_ORDER_SYNC_KEY', env('LOGO_COLLECTION_SYNC_KEY', env('LOGO_CUSTOMER_SYNC_KEY', '')))),
        'write' => [
            'enabled' => env('LOGO_WRITE_ENABLED', false),
            'transport' => env('LOGO_WRITE_TRANSPORT', 'bridge'),
            'exchange' => env('LOGO_WRITE_EXCHANGE', 'powersa.logo'),
            'rabbitmq' => [
                'host' => env('LOGO_WRITE_RABBITMQ_HOST', '127.0.0.1'),
                'port' => (int) env('LOGO_WRITE_RABBITMQ_PORT', 5672),
                'user' => env('LOGO_WRITE_RABBITMQ_USER', 'guest'),
                'password' => env('LOGO_WRITE_RABBITMQ_PASSWORD', 'guest'),
                'vhost' => env('LOGO_WRITE_RABBITMQ_VHOST', '/'),
                'heartbeat' => (int) env('LOGO_WRITE_RABBITMQ_HEARTBEAT', 30),
                'connection_timeout' => (float) env('LOGO_WRITE_RABBITMQ_CONNECTION_TIMEOUT', 3.0),
                'read_write_timeout' => (float) env('LOGO_WRITE_RABBITMQ_READ_WRITE_TIMEOUT', 3.0),
            ],
        ],
    ],
    'pos' => [
        'point_cashbox_code' => env('POS_POINT_CASHBOX_CODE', '100.01.007'),
        'point_cashbox_name' => env('POS_POINT_CASHBOX_NAME', 'ERZURUM POINT KASASI'),
    ],
    'ownership' => [
        'customers' => [
            'master' => 'hybrid',
            'logo_to_b2b' => true,
            'b2b_to_logo' => true,
        ],
        'products' => [
            'master' => 'logo',
            'logo_to_b2b' => true,
            'b2b_to_logo' => false,
        ],
        'prices' => [
            'master' => 'logo',
            'logo_to_b2b' => true,
            'b2b_to_logo' => false,
        ],
        'stock' => [
            'master' => 'logo',
            'logo_to_b2b' => true,
            'b2b_to_logo' => false,
        ],
        'ledger' => [
            'master' => 'logo',
            'logo_to_b2b' => true,
            'b2b_to_logo' => false,
        ],
        'collections' => [
            'master' => 'hybrid',
            'logo_to_b2b' => true,
            'b2b_to_logo' => true,
        ],
        'orders' => [
            'master' => 'b2b',
            'logo_to_b2b' => false,
            'b2b_to_logo' => true,
        ],
    ],
];
