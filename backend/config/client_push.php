<?php

return [
    'enabled' => (bool) env('CLIENT_PUSH_ENABLED', false),
    'timezone' => 'America/Cancun',
    'fcm_credentials' => env('CLIENT_PUSH_FCM_CREDENTIALS'),
    'apns_key' => env('CLIENT_PUSH_APNS_KEY'),
    'apns_key_id' => env('CLIENT_PUSH_APNS_KEY_ID'),
    'apns_team_id' => env('CLIENT_PUSH_APNS_TEAM_ID'),
    'apns_topic' => env('CLIENT_PUSH_APNS_TOPIC', 'com.creceimpulso.clientes'),
    'apns_staff_topic' => env('CLIENT_PUSH_APNS_STAFF_TOPIC', 'com.creceimpulso.colaboradores'),
    'apns_sandbox' => (bool) env('CLIENT_PUSH_APNS_SANDBOX', false),
];
