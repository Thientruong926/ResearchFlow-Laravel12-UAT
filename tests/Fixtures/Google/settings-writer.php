<?php

// Child process for the settings locking test. Arguments point to a disposable test directory.
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['research.settings_file'=>$argv[1]]);
Illuminate\Support\Facades\DB::shouldReceive('connection')->never();
file_put_contents($argv[4], 'ready');
try {
    app(App\Services\SettingsFileStore::class)->update($argv[2], function ($data) use ($argv) {
        if ($argv[3] === 'research') $data['research']['title'] = 'Concurrent title';
        else $data['assignments'] = [];
        return $data;
    }, 'test-worker');
    echo 'OK';
} catch (Symfony\Component\HttpKernel\Exception\ConflictHttpException) {
    echo 'CONFLICT';
}
Mockery::close();
