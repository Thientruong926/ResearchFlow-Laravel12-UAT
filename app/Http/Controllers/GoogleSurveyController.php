<?php

namespace App\Http\Controllers;

use App\Services\GoogleFormsService;
use App\Services\ResearchSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class GoogleSurveyController extends Controller
{
    public function home(ResearchSettings $settings)
    {
        return view('portal.google_home', [
            'research' => $settings->research(),
            'schools' => $settings->schools(),
            'groups' => config('research.groups'),
            'syncEnabled' => config('research.google_sync_enabled'),
        ]);
    }

    public function start(Request $request, ResearchSettings $settings)
    {
        $data = $request->validate([
            'school' => ['required', Rule::in(array_column($settings->schools(), 'code'))],
            'group' => ['required', Rule::in(array_keys(config('research.groups')))],
        ]);

        if (! $settings->assignment($data['school'], $data['group'])) {
            return back()->withInput()->withErrors([
                'group' => 'Trường và nhóm này chưa được gán phiếu khảo sát đang hoạt động.',
            ]);
        }

        return redirect()->route('survey.show', $data);
    }

    public function show(Request $request, ResearchSettings $settings)
    {
        $schoolCode = (string) $request->query('school');
        $group = (string) $request->query('group');
        $school = $settings->school($schoolCode);
        $assignment = $settings->assignment($schoolCode, $group);

        abort_unless($school && $assignment && isset(config('research.groups')[$group]), 404);

        return view('survey.google', [
            'research' => $settings->research(),
            'school' => $school,
            'group' => $group,
            'groupName' => config('research.groups')[$group],
            'assignment' => $assignment,
            'questions' => array_values($assignment['questions'] ?? []),
        ]);
    }

    public function submit(
        Request $request,
        ResearchSettings $settings,
        GoogleFormsService $forms
    ) {
        $school = (string) $request->input('school');
        $group = (string) $request->input('group');
        $assignment = $settings->assignment($school, $group);

        if ($assignment && ! empty($assignment['questions'])) {
            return $this->submitImported($request, $settings, $forms, $assignment);
        }

        $data = $request->validate([
            'school' => ['required', Rule::in(array_column($settings->schools(), 'code'))],
            'group' => ['required', Rule::in(array_keys(config('research.groups')))],
            'consent' => ['accepted'],
            'use' => ['required', Rule::in(['yes', 'no'])],
            'level' => ['nullable', 'required_if:use,yes', Rule::in(['1', '2', '3', '4', '5'])],
            'total' => ['required', 'integer', 'min:0', 'max:100'],
            'digital' => ['nullable', 'required_if:use,yes', 'integer', 'min:0', 'max:100', 'lte:total'],
            'note' => ['nullable', 'string', 'max:1000'],
        ], [
            'consent.accepted' => 'Bạn cần xác nhận đồng ý trước khi gửi khảo sát.',
            'digital.lte' => 'Số buổi sử dụng công cụ số không được lớn hơn tổng số buổi.',
        ]);

        $assignment = $settings->assignment($data['school'], $data['group']);
        abort_unless($assignment, 404);

        try {
            $forms->submit($assignment, [
                'school' => $data['school'],
                'group' => $data['group'],
                'use' => $data['use'],
                'level' => $data['use'] === 'yes' ? $data['level'] : null,
                'total' => $data['total'],
                'digital' => $data['use'] === 'yes' ? $data['digital'] : null,
                'note' => $data['note'] ?? null,
            ]);
        } catch (RuntimeException $exception) {
            return back()->withInput($request->only(['school', 'group']))->withErrors(['submit' => $exception->getMessage()]);
        }

        $request->session()->flash('completed_draft_key', 'researchflow-draft-'.$data['school'].'-'.$data['group']);
        return redirect()->route('survey.thanks');
    }

    public function thanks(Request $request)
    {
        return view('survey.thanks', [
            'completedDraftKey' => (string) $request->session()->get('completed_draft_key', ''),
        ]);
    }

    private function submitImported(
        Request $request,
        ResearchSettings $settings,
        GoogleFormsService $forms,
        array $assignment
    ) {
        $rules = [
            'school' => ['required', Rule::in(array_column($settings->schools(), 'code'))],
            'group' => ['required', Rule::in(array_keys(config('research.groups')))],
            'consent' => ['accepted'],
            'answer' => ['nullable', 'array'],
        ];

        foreach ($assignment['questions'] as $question) {
            $id = (string) $question['id'];
            $key = 'answer.'.$id;
            $required = (bool) ($question['required'] ?? false);
            $options = array_column($question['options'] ?? [], 'value');

            $rules[$key] = match ($question['type']) {
                'checkbox' => [$required ? 'required' : 'nullable', 'array', 'max:20'],
                'radio', 'dropdown', 'scale' => [$required ? 'required' : 'nullable', 'string', Rule::in($options)],
                'paragraph' => [$required ? 'required' : 'nullable', 'string', 'max:5000'],
                default => [$required ? 'required' : 'nullable', 'string', 'max:1000'],
            };

            if ($question['type'] === 'checkbox') {
                $rules[$key.'.*'] = ['string', Rule::in($options)];
            }
        }

        $data = $request->validate($rules, [
            'consent.accepted' => 'Bạn cần xác nhận đồng ý trước khi gửi khảo sát.',
            'answer.*.required' => 'Vui lòng trả lời đầy đủ các câu hỏi bắt buộc.',
        ]);

        try {
            $forms->submitImported(
                $assignment,
                $data['school'],
                $data['group'],
                $data['answer'] ?? []
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['submit' => $exception->getMessage()]);
        }

        $request->session()->flash('completed_draft_key', 'researchflow-draft-'.$data['school'].'-'.$data['group']);
        return redirect()->route('survey.thanks');
    }
}
