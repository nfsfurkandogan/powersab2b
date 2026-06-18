<?php

return [
    'queue' => env('REPORTS_QUEUE', 'default'),
    'order_closed_statuses' => ['completed', 'cancelled', 'delivered'],
];
