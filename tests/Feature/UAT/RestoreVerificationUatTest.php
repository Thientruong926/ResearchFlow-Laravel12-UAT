<?php

namespace Tests\Feature\UAT;

use App\Services\RestoreVerification;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RestoreVerificationUatTest extends TestCase
{
    public function test_b5_sqlite_backup_restore_matches_and_detects_equal_count_content_changes(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'b_restore_');
        // VACUUM INTO needs a new destination; this is a freshly created test-only temp file.
        unlink($path);
        config(['database.connections.b_backup_source' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'database.connections.b_backup_restored' => ['driver' => 'sqlite', 'database' => $path]]);
        try {
            $source = DB::connection('b_backup_source');
            $source->statement('CREATE TABLE b_restore_probe (id INTEGER PRIMARY KEY, answer TEXT, missing_code TEXT)');
            $source->table('b_restore_probe')->insert([
                ['id' => 1, 'answer' => 'Dữ liệu giả tiếng Việt', 'missing_code' => null],
                ['id' => 2, 'answer' => null, 'missing_code' => '97'],
                ['id' => 3, 'answer' => null, 'missing_code' => 'BLANK'],
            ]);
            $source->getPdo()->exec('VACUUM INTO '.$source->getPdo()->quote($path));
            $restored = DB::connection('b_backup_restored');
            $service = new RestoreVerification();
            $report = $service->compare($source, $restored);
            $this->assertTrue($report['passed']);
            $this->assertSame(3, $report['restored_tables']['b_restore_probe']['rows']);
            $restored->table('b_restore_probe')->where('id', 2)->update(['missing_code' => '98']);
            $report = $service->compare($source, $restored);
            $this->assertFalse($report['passed']);
            $this->assertSame($report['source_tables']['b_restore_probe']['rows'], $report['restored_tables']['b_restore_probe']['rows']);
        } finally {
            DB::purge('b_backup_source');
            DB::purge('b_backup_restored');
            if (file_exists($path)) unlink($path);
        }
    }
}
