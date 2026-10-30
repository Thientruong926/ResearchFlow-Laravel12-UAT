<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ResearchSettings;
use App\Services\SettingsFileStore;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\Google\SprintZeroFixture;
use Tests\GoogleTestCase;

class ResearchSettingsTest extends GoogleTestCase
{
    private string $directory;
    private array $original;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = storage_path('framework/testing/settings-'.bin2hex(random_bytes(8)));
        mkdir($this->directory, 0700, true);
        config(['research.settings_file'=>$this->directory.'/settings.json']);
        $this->original = SprintZeroFixture::settings();
        $this->original['extension'] = ['unknown'=>'preserve'];
        $this->original['research']['future_field'] = 'preserve too';
        file_put_contents(config('research.settings_file'), json_encode($this->original));
        Http::fake();
        $this->withSession(['admin_authenticated'=>true, 'admin_email'=>'admin@example.test']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function current(): array { return app(ResearchSettings::class)->all(); }
    private function payload(): array
    {
        return ['title'=>'Nghiên cứu mới','introduction'=>"Giới thiệu mới\nDòng tiếp",'consent'=>'Đồng thuận mới','contact_name'=>'Nhóm thử nghiệm','contact_email'=>'test@example.test','contact_phone'=>'+84 (28) 1234-5678','contact_address'=>'Địa chỉ thử','revision'=>$this->current()['_revision']];
    }

    public function test_research_save_without_mapping_preserves_other_configuration_and_updates_public_content(): void
    {
        $data = $this->payload();
        $this->post('/admin/settings/research', $data + ['assignments'=>[], 'timestamp_column'=>'ATTACK'])->assertRedirect('/admin/settings/research');
        $saved = $this->current();
        foreach (['schools','assignments','sheet_columns','source_defaults','extension'] as $key) $this->assertSame($this->original[$key], $saved[$key]);
        $this->assertSame('preserve too', $saved['research']['future_field']);
        $this->assertNotSame($data['revision'], $saved['_revision']);
        $this->get('/')->assertOk()->assertSee('Nghiên cứu mới')->assertSee('test@example.test')->assertSee('Địa chỉ thử');
        $this->get('/survey?school=DEMO_01&group=student')->assertOk()->assertSee('Đồng thuận mới')->assertSee('test@example.test');
        Http::assertNothingSent();
    }

    public function test_preview_uses_public_partials_and_does_not_write(): void
    {
        $before = file_get_contents(config('research.settings_file'));
        $data = $this->payload(); $data['title'] = '<script>alert(1)</script>';
        $this->post('/admin/settings/research/preview', $data)->assertOk()->assertSee('chưa lưu thay đổi')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
        $this->assertSame($before, file_get_contents(config('research.settings_file')));
        $this->assertDirectoryDoesNotExist(config('research.settings_file').'.backups');
        $this->post('/admin/settings/research', $data)->assertRedirect();
        $this->get('/')->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>', false);
    }

    public static function invalidFields(): array { return [['contact_email','wrong'], ['contact_phone','<script>'], ['title',str_repeat('a',301)], ['introduction',''], ['contact_address',str_repeat('a',501)]]; }

    #[DataProvider('invalidFields')]
    public function test_validation_preserves_input_and_reports_field_errors(string $field, string $value): void
    {
        $data = $this->payload(); $data[$field] = $value;
        $before = file_get_contents(config('research.settings_file'));
        $this->post('/admin/settings/research', $data + ['private_key'=>'DO_NOT_FLASH'])->assertRedirect('/admin/settings/research')->assertSessionHasErrors($field);
        $this->assertSame($data['title'], session()->getOldInput('title'));
        $this->assertNull(session()->getOldInput('private_key'));
        $this->get('/admin/settings/research')->assertOk()->assertSee('error-'.$field, false);
        $this->assertSame($before, file_get_contents(config('research.settings_file')));
    }

    public function test_stale_research_tab_after_assignment_write_returns_409_and_keeps_input(): void
    {
        $data = $this->payload();
        app(ResearchSettings::class)->removeAssignment('DEMO_01-manager', $data['revision']);
        $before = file_get_contents(config('research.settings_file'));
        $this->post('/admin/settings/research', $data)->assertStatus(409)->assertSee('Nghiên cứu mới')->assertSee('Tải lại');
        $this->assertSame($before, file_get_contents(config('research.settings_file')));
    }

    public function test_all_integration_writers_require_current_revision(): void
    {
        $old = $this->payload();
        $this->post('/admin/settings/research', $old)->assertRedirect();
        $this->post('/admin/settings/schools', ['revision'=>$old['revision'],'schools'=>'DEMO_01|Trường thử|1|2|3'])->assertStatus(409);
        $this->post('/admin/settings/columns', ['revision'=>$old['revision'],'timestamp_column'=>'Timestamp','school_column'=>'School','group_column'=>'Group'])->assertStatus(409);
        $this->post('/admin/settings/assignments/DEMO_01-manager/delete', ['revision'=>$old['revision']])->assertStatus(409);
        $assignment = ['revision'=>$old['revision'],'school'=>'DEMO_01','group'=>'manager','form_id'=>'test-form','sheet_csv_url'=>'https://docs.google.com/spreadsheets/d/test/edit','active'=>'1'];
        foreach (['school','group','use','level','total','digital','note'] as $key) $assignment['entry_'.$key] = '123';
        $this->post('/admin/settings/assignments', $assignment)->assertStatus(409);
        $assignment['revision'] = $this->current()['_revision'];
        $this->post('/admin/settings/assignments', $assignment)->assertRedirect();
        $this->post('/admin/settings/columns', ['revision'=>$this->current()['_revision'],'timestamp_column'=>'Timestamp','school_column'=>'School','group_column'=>'Group'])->assertRedirect();
        $this->assertSame('Nghiên cứu mới', $this->current()['research']['title']);
        $this->assertSame('Timestamp', $this->current()['sheet_columns']['timestamp']);
    }

    public function test_empty_lists_replace_defaults_and_missing_file_initializes(): void
    {
        config(['research.default_settings.assignments'=>$this->original['assignments']]);
        file_put_contents(config('research.settings_file'), '{"schools":[],"assignments":[]}');
        $this->assertSame([], $this->current()['schools']); $this->assertSame([], $this->current()['assignments']);
        unlink(config('research.settings_file'));
        $defaults = $this->current();
        $this->assertSame(config('research.default_settings.schools'), $defaults['schools']);
        $this->post('/admin/settings/research', $this->payload())->assertRedirect();
        $this->assertFileExists(config('research.settings_file'));
    }

    public function test_corrupt_json_is_not_replaced_with_demo_defaults(): void
    {
        $data = $this->payload();
        file_put_contents(config('research.settings_file'), '{broken');
        $this->get('/admin/settings/research')->assertStatus(503)->assertSee('cấu hình hỏng');
        $this->get('/')->assertStatus(503);
        $this->post('/admin/settings/research', $data)->assertStatus(503)->assertSee('Nghiên cứu mới');
        $this->assertSame('{broken', file_get_contents(config('research.settings_file')));
    }

    public function test_invalid_json_structure_and_out_of_band_edit_are_detected(): void
    {
        $data=$this->payload();
        $this->original['research']['title']='Edited outside application';
        file_put_contents(config('research.settings_file'), json_encode($this->original));
        $this->post('/admin/settings/research',$data)->assertStatus(409);
        file_put_contents(config('research.settings_file'), '{"research":{"title":[]}}');
        $this->get('/admin/settings/research')->assertStatus(503);
        file_put_contents(config('research.settings_file'), '[]');
        $this->get('/')->assertStatus(503);
    }

    public function test_backups_are_bounded_and_exclude_credentials_and_audit_has_no_values(): void
    {
        $this->original['google_credentials'] = ['private_key'=>'VERY_SECRET'];
        $this->original['assignments'][0]['access_token'] = 'VERY_SECRET';
        file_put_contents(config('research.settings_file'), json_encode($this->original));
        for ($i=0; $i<7; $i++) { $data=$this->payload(); $data['title']='Changed '.$i; $this->post('/admin/settings/research',$data)->assertRedirect(); }
        $backups = glob(config('research.settings_file').'.backups/*.json');
        $this->assertCount(5, $backups);
        foreach ($backups as $file) { $raw=file_get_contents($file); $this->assertStringNotContainsString('VERY_SECRET',$raw); $this->assertIsArray(json_decode($raw,true,512,JSON_THROW_ON_ERROR)); }
        $saved=$this->current();
        $this->assertSame('VERY_SECRET',$saved['google_credentials']['private_key']);
        $audit=json_encode($saved['_audit']);
        $this->assertStringNotContainsString('Changed',$audit);
        $this->assertStringNotContainsString('VERY_SECRET',$audit);
        $this->assertStringContainsString('research.title',$audit);
        $this->assertSame($saved['_revision'],end($saved['_audit'])['revision']);
    }

    public function test_failed_atomic_replace_preserves_old_file_and_cleans_temporary_files(): void
    {
        $data=$this->payload(); $before=file_get_contents(config('research.settings_file'));
        $this->app->bind(SettingsFileStore::class, fn () => new class extends SettingsFileStore {
            protected function replace(string $temporary,string $target): bool { return $target === config('research.settings_file') ? false : parent::replace($temporary,$target); }
        });
        $this->post('/admin/settings/research',$data)->assertStatus(503)->assertSee('Nghiên cứu mới');
        $this->assertSame($before,file_get_contents(config('research.settings_file')));
        $this->assertSame([],glob(config('research.settings_file').'.*.tmp'));
        $this->assertCount(1,glob(config('research.settings_file').'.backups/*.json'));
    }

    public function test_unwritable_backup_destination_does_not_change_live_file(): void
    {
        $data=$this->payload(); $before=file_get_contents(config('research.settings_file'));
        file_put_contents(config('research.settings_file').'.backups','blocking file');
        $this->post('/admin/settings/research',$data)->assertStatus(503);
        $this->assertSame($before,file_get_contents(config('research.settings_file')));
    }

    public function test_csrf_and_login_are_required_for_preview_and_save(): void
    {
        $data=$this->payload();
        $this->withSession(['admin_authenticated'=>false]);
        $this->get('/admin/settings/research')->assertRedirect('/login');
        $this->post('/admin/settings/research/preview',$data)->assertRedirect('/login');
        $this->post('/admin/settings/research',$data)->assertRedirect('/login');
        $this->withSession(['admin_authenticated'=>true]);
        $this->app->instance('env','csrf-test');
        $this->post('/admin/settings/research/preview',$data)->assertStatus(419);
        $this->post('/admin/settings/research',$data)->assertStatus(419);
    }

    public function test_two_processes_use_one_transaction_lock_and_one_stale_revision_is_rejected(): void
    {
        $path=config('research.settings_file'); $revision=$this->current()['_revision'];
        $lock=fopen($path.'.lock','c'); flock($lock,LOCK_EX);
        $workers=[];
        try {
            foreach (['research','assignments'] as $type) {
                $ready=$this->directory.'/'.$type.'.ready';
                $process=proc_open([PHP_BINARY,base_path('tests/Fixtures/Google/settings-writer.php'),$path,$revision,$type,$ready],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,base_path());
                $this->assertIsResource($process); fclose($pipes[0]);
                $workers[]=[$process,$pipes,$ready];
            }
            $deadline=microtime(true)+15;
            while ((! is_file($workers[0][2]) || ! is_file($workers[1][2])) && microtime(true)<$deadline) usleep(10000);
            $this->assertFileExists($workers[0][2]); $this->assertFileExists($workers[1][2]);
        } finally { flock($lock,LOCK_UN); fclose($lock); }
        $results=[];
        foreach ($workers as [$process,$pipes]) {
            $results[]=trim(stream_get_contents($pipes[1])); $error=stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]); $this->assertSame(0,proc_close($process),$error);
        }
        sort($results); $this->assertSame(['CONFLICT','OK'],$results);
        $this->assertSame(1,$this->current()['_version']);
    }
}
