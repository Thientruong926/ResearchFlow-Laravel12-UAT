<?php

return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    // Failed jobs must not create a database or retain survey payloads.
    'failed' => ['driver' => 'null'],
];
