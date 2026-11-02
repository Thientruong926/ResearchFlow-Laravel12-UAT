<?php

namespace Tests\Feature\AdminGoogle;

use App\Services\ResearchSettings;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\GoogleTestCase;

class GoogleFormQuestionSyncTest extends GoogleTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/form-sync-'.bin2hex(random_bytes(5)));
        mkdir($this->directory, 0700, true);
        config([
            'research.settings_file' => $this->directory.'/settings.json',
            'research.connections.metadata_directory' => $this->directory.'/health',
            'research.google_sync_enabled' => true,
            'research.google_forms_access_token' => '',
        ]);
        file_put_contents(config('research.settings_file'), json_encode($this->settings(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_admin_syncs_questions_then_participant_submits_dynamic_form(): void
    {
        Http::fake([
            'https://docs.google.com/forms/d/e/1FAIpQLDemoForm/viewform' => Http::response($this->publishedFormHtml(), 200),
            'https://docs.google.com/forms/d/e/1FAIpQLDemoForm/formResponse' => Http::response('', 200),
        ]);

        $settings = app(ResearchSettings::class);
        $session = ['admin_authenticated' => true, 'admin_email' => 'admin@example.test'];

        $this->withSession($session)
            ->post('/admin/settings/assignments/SITE_01-student/sync', [
                'revision' => $settings->all()['_revision'],
            ])
            ->assertRedirect()
            ->assertSessionHas('ok');

        $assignment = $settings->assignmentById('SITE_01-student');
        $this->assertCount(2, $assignment['questions']);
        $this->assertSame('Mức độ hài lòng', $assignment['questions'][0]['title']);
        $this->assertSame('published_form', $assignment['form_sync']['source']);

        $this->get('/survey?school=SITE_01&group=student')
            ->assertOk()
            ->assertSee('Phiếu khảo sát minh họa')
            ->assertSee('Mức độ hài lòng')
            ->assertSee('Góp ý thêm');

        $this->post('/survey', [
            'school' => 'SITE_01',
            'group' => 'student',
            'consent' => '1',
            'answer' => [
                '201' => 'Hài lòng',
                '202' => 'Dữ liệu demo',
            ],
        ])->assertRedirect('/thank-you');

        Http::assertSent(function (Request $request) {
            return str_ends_with($request->url(), '/formResponse')
                && $request['entry.101'] === 'SITE_01'
                && $request['entry.102'] === 'student'
                && $request['entry.201'] === 'Hài lòng'
                && $request['entry.202'] === 'Dữ liệu demo';
        });
    }

    public function test_required_imported_question_is_validated_on_server(): void
    {
        Http::fake([
            'https://docs.google.com/forms/d/e/1FAIpQLDemoForm/viewform' => Http::response($this->publishedFormHtml(), 200),
        ]);

        $settings = app(ResearchSettings::class);
        $this->withSession(['admin_authenticated' => true, 'admin_email' => 'admin@example.test'])
            ->post('/admin/settings/assignments/SITE_01-student/sync', ['revision' => $settings->all()['_revision']]);

        $this->post('/survey', [
            'school' => 'SITE_01',
            'group' => 'student',
            'consent' => '1',
            'answer' => ['202' => 'Chỉ nhập câu không bắt buộc'],
        ])->assertSessionHasErrors('answer.201');
    }

    private function publishedFormHtml(): string
    {
        $items = [
            [1, 'Mã trường', null, 0, [[101, null, 1]]],
            [2, 'Nhóm đối tượng', null, 0, [[102, null, 1]]],
            [3, 'Mức độ hài lòng', null, 2, [[201, [['Hài lòng', null, null, null, 0], ['Chưa hài lòng', null, null, null, 0]], 1]]],
            [4, 'Góp ý thêm', 'Không nhập thông tin nhận dạng', 1, [[202, null, 0]]],
        ];
        $form = ['Mô tả phiếu dùng để demo', $items, null, null, null, null, null, null, 'Phiếu khảo sát minh họa'];
        $payload = [null, $form];

        return '<!doctype html><html><script>var FB_PUBLIC_LOAD_DATA_ = '
            .json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            .';</script></html>';
    }

    private function settings(): array
    {
        return [
            'research' => [
                'title' => 'Khảo sát demo',
                'introduction' => 'Giới thiệu.',
                'contact_name' => 'Nhóm nghiên cứu',
                'contact_email' => 'research@example.test',
                'consent' => 'Tự nguyện tham gia.',
            ],
            'schools' => [[
                'code' => 'SITE_01',
                'name' => 'Trường kiểm thử',
                'targets' => ['manager' => 10, 'lecturer' => 20, 'student' => 70],
            ]],
            'assignments' => [[
                'id' => 'SITE_01-student',
                'school' => 'SITE_01',
                'group' => 'student',
                'active' => true,
                'form_id' => '1FAIpQLDemoForm',
                'sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/demo-sheet/edit?gid=0',
                'entries' => ['school' => '101', 'group' => '102'],
            ]],
            'sheet_columns' => [
                'timestamp' => 'Dấu thời gian',
                'school' => 'Mã trường',
                'group' => 'Nhóm đối tượng',
            ],
        ];
    }
}
