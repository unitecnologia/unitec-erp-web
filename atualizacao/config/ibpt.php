<?php

return [
    'base_url' => rtrim((string) env('IBPT_BASE_URL', 'https://apidoni.ibpt.org.br/api/v1'), '/'),
    'token' => (string) env('IBPT_TOKEN', ''),
    'timeout' => (int) env('IBPT_TIMEOUT', 30),
];
