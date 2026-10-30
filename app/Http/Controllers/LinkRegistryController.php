<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Services\ResearchId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LinkRegistryController extends Controller
{
    public function wave(Request $request, ResearchId $ids)
    {
        $data = $request->validate([
            'code' => ['nullable', 'regex:/^W-[A-Z2-7]{10}$/D', 'unique:waves,code'],
            'instrument_id' => 'required|in:OBS2,OBS4', 'valid_from' => 'required|date',
            'valid_until' => 'required|date|after:valid_from',
            'academic_year' => 'nullable|string|max:32', 'semester' => 'nullable|string|max:32',
        ]);
        $data['code'] = $data['code'] ?? $ids->registryCode('W');
        $id = DB::table('waves')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        Audit::log('wave.created', 'waves', $id);
        return back()->with('ok', 'Đã tạo đợt và bộ phiếu áp dụng.');
    }

    public function formMetadata(Request $request, int $id)
    {
        $survey = DB::table('surveys')->find($id);
        abort_unless($survey && $survey->status === 'draft', 422);
        $data = $request->validate(['instrument_id' => 'required|in:OBS2,OBS4',
            'form_version' => ['required', 'string', 'max:64', Rule::unique('surveys', 'form_version')
                ->where('instrument_id', $request->input('instrument_id'))->where('group_code', $survey->group_code)->ignore($id)]]);
        abort_if($data['instrument_id'] === 'OBS2' && !in_array($survey->group_code, ['BGH', 'KHOA'], true), 422);
        DB::table('surveys')->where('id', $id)->update($data + ['updated_at' => now()]);
        Audit::log('survey.metadata_updated', 'surveys', $id);
        return back()->with('ok', 'Đã cập nhật metadata bản nháp.');
    }

    public function department(Request $request, ResearchId $ids)
    {
        $data = $request->validate(['site_id' => 'required|exists:sites,id',
            'code' => ['nullable', 'regex:/^D-[A-Z2-7]{10}$/D', 'unique:departments,code'],
            'private_label' => 'nullable|string|max:255']);
        $data['code'] = $data['code'] ?? $ids->registryCode('D');
        $id = DB::table('departments')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]);
        Audit::log('department.created', 'departments', $id);
        return back()->with('ok', 'Đã tạo mã khoa/ngành.');
    }

    public function create(Request $request)
    {
        $data = $request->validate([
            'site_id' => 'required|exists:sites,id', 'wave_id' => 'required|exists:waves,id',
            'group_code' => ['required', Rule::in(['BGH', 'KHOA', 'GV', 'SV'])],
            'department_id' => 'nullable|exists:departments,id',
            'survey_id' => 'required|exists:surveys,id',
            'point_code' => ['required', 'regex:/^[A-Z0-9_-]{2,32}$/D'],
            'class_code' => ['nullable', 'regex:/^[A-Z0-9_-]{2,32}$/D'],
            'owner_id' => ['required', Rule::exists('users', 'id')->where('active', true)->whereIn('role', ['owner', 'researcher', 'IT_ADMIN', 'NCS'])],
            'valid_from' => 'required|date', 'valid_until' => 'required|date|after:valid_from',
            'existing_session_policy' => 'required|in:continue,stop',
        ]);
        $survey = DB::table('surveys')->find($data['survey_id']);
        $wave = DB::table('waves')->find($data['wave_id']);
        if ($wave->instrument_id !== $survey->instrument_id || !$wave->active
            || !DB::table('sites')->where('id', $data['site_id'])->where('active', true)->exists()
            || ($wave->valid_from && \Carbon\Carbon::parse($data['valid_from'])->lt($wave->valid_from))
            || ($wave->valid_until && \Carbon\Carbon::parse($data['valid_until'])->gt($wave->valid_until))) {
            throw ValidationException::withMessages(['wave_id' => 'Đợt phải mở, đúng bộ phiếu và bao phủ thời hạn link.']);
        }
        $allowed = $survey->instrument_id === 'OBS2' ? ['BGH', 'KHOA'] : ['BGH', 'KHOA', 'GV', 'SV'];
        if ($survey->status !== 'published' || $survey->group_code !== $data['group_code']
            || !in_array($survey->instrument_id, ['OBS2', 'OBS4'], true) || !$survey->form_version
            || !in_array($data['group_code'], $allowed, true)) {
            throw ValidationException::withMessages(['survey_id' => 'Cần phiếu đã công bố đúng nhóm, bộ OBS2/OBS4 và phiên bản.']);
        }
        if (!empty($data['department_id']) && !DB::table('departments')->where('id', $data['department_id'])
            ->where('site_id', $data['site_id'])->where('active', true)->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Khoa/ngành không thuộc cơ sở hoặc đã dừng.']);
        }
        $token = bin2hex(random_bytes(24));
        $id = DB::transaction(function () use ($data, $token) {
            $id = DB::table('sample_links')->insertGetId($data + [
                'token' => hash('sha256', $token), 'token_hashed' => true, 'status' => 'DRAFT',
                'active' => false, 'created_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            Audit::log('link.created', 'sample_links', $id);
            return $id;
        });
        $url = route('portal.intro', $token);
        $qr = (new \Endroid\QrCode\Writer\SvgWriter())->write(new \Endroid\QrCode\QrCode($url))->getString();
        // One-time issue response; neither the URL nor token enters persisted session flashes.
        return response()->view('admin.link_issued', compact('url', 'qr', 'id'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function toggle(int $id)
    {
        return DB::transaction(function () use ($id) {
            $link = DB::table('sample_links')->where('id', $id)->lockForUpdate()->first();
            abort_unless($link, 404);
            // Legacy invitation mappings lack a fixed instrument; require replacement before reopening.
            abort_unless($link->token_hashed || $link->active, 422, 'Tạo link theo thiết kế mới trước khi mở lại.');
            abort_if($link->status === 'REVOKED', 422, 'Link đã thu hồi không thể mở lại.');
            $active = !$link->active;
            abort_unless(auth()->user()->hasRole('owner', 'researcher', $active ? 'NCS' : 'IT_ADMIN'), 403);
            if ($active) {
                abort_unless(config('research.collection_open'), 422, 'Chưa mở thu thập.');
                abort_unless($link->valid_from && $link->valid_until && now()->gte($link->valid_from)
                    && now()->lt($link->valid_until), 422, 'Ngoài thời hạn link.');
                abort_unless(DB::table('sites')->where('id', $link->site_id)->where('active', true)->exists()
                    && DB::table('waves')->where('id', $link->wave_id)->where('active', true)->exists(), 422);
                if ($link->department_id) {
                    abort_unless(DB::table('departments')->where('id', $link->department_id)
                        ->where('site_id', $link->site_id)->where('active', true)->exists(), 422);
                }
                abort_unless(app(\App\Services\SamplingRoute::class)->survey($link), 422, 'Phiếu không khả dụng.');
                abort_unless(DB::table('information_versions')->where('status', 'published')->exists(), 422, 'Chưa có consent.');
                abort_unless(DB::table('quota_configs')->where('site_id', $link->site_id)->where('wave_id', $link->wave_id)
                    ->where('group_code', $link->group_code)->where('status', 'approved')->exists(), 422, 'Chưa có quota đã duyệt.');
            }
            $changes = ['active' => $active, 'status' => $active ? 'ACTIVE' : 'PAUSED', 'updated_at' => now()];
            if ($active) {
                $changes += ['approved_by' => auth()->id(), 'approved_at' => now()];
            }
            DB::table('sample_links')->where('id', $id)->update($changes);
            Audit::log($active ? 'link.activated' : 'link.paused', 'sample_links', $id);
            return back()->with('ok', $active ? 'Đã duyệt và mở link.' : 'Đã tạm dừng link.');
        });
    }

    public function revoke(Request $request, int $id)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000',
            'replaced_by' => ['nullable', 'integer', Rule::exists('sample_links', 'id')->where('token_hashed', true), Rule::notIn([$id])]]);
        DB::transaction(function () use ($id, $data) {
            $link = DB::table('sample_links')->where('id', $id)->lockForUpdate()->first();
            abort_unless($link, 404);
            abort_if($link->status === 'REVOKED', 422, 'Link đã thu hồi.');
            DB::table('sample_links')->where('id', $id)->update(['active' => false, 'status' => 'REVOKED',
                'replaced_by' => $data['replaced_by'] ?? null, 'updated_at' => now()]);
            Audit::log('link.revoked', 'sample_links', $id, $data);
        });
        return back()->with('ok', 'Đã thu hồi link; dữ liệu đã thu được giữ nguyên.');
    }

    public function export()
    {
        // Invitation registry only: no private site labels, participant IDs or resume tokens.
        $links = DB::table('sample_links as l')
            ->join('sites as s', 'l.site_id', '=', 's.id')
            ->join('waves as w', 'l.wave_id', '=', 'w.id')
            ->leftJoin('departments as d', 'l.department_id', '=', 'd.id')
            ->leftJoin('surveys as f', 'l.survey_id', '=', 'f.id')
            ->select('l.id', 's.code as site_code', 'w.code as wave_code', 'd.code as department_code',
                'f.instrument_id', 'f.form_version', 'l.point_code', 'l.token_hashed', 'l.status',
                'l.valid_from', 'l.valid_until', 'l.owner_id', 'l.approved_by', 'l.replaced_by',
                'l.faculty_code', 'l.class_code', 'l.group_code', 'l.token',
                'l.active', 's.active as site_active', 'w.active as wave_active', 'l.created_at')
            ->orderBy('l.id')->get();

        Audit::log('link_registry.exported', 'sample_links', null, ['rows' => $links->count()]);

        return response()->streamDownload(function () use ($links) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['LINK_ID', 'SITE_CODE', 'WAVE_CODE', 'DEPARTMENT_CODE',
                'CLASS_CODE', 'GROUP', 'URL', 'LINK_ACTIVE', 'SITE_ACTIVE', 'WAVE_ACTIVE',
                'COLLECTION_OPEN', 'CREATED_AT', 'POINT_CODE', 'INSTRUMENT_ID', 'FORM_VERSION',
                'STATUS', 'VALID_FROM', 'VALID_UNTIL', 'OWNER_ID', 'APPROVED_BY', 'REPLACED_BY'], ',', '"', '');
            foreach ($links as $link) {
                $row = [$link->id, $link->site_code, $link->wave_code, $link->department_code,
                    $link->class_code, $link->group_code, $link->token_hashed ? '' : route('portal.intro', $link->token),
                    (int) $link->active, (int) $link->site_active, (int) $link->wave_active,
                    (int) config('research.collection_open'), $link->created_at, $link->point_code,
                    $link->instrument_id, $link->form_version,
                    $link->valid_until && now()->gte($link->valid_until) && $link->status !== 'REVOKED' ? 'EXPIRED' : $link->status,
                    $link->valid_from, $link->valid_until, $link->owner_id, $link->approved_by, $link->replaced_by];
                // Legacy imported codes may contain spreadsheet formula prefixes.
                $row = array_map(static function ($value) {
                    $value = (string) ($value ?? '');
                    return preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }, $row);
                fputcsv($out, $row, ',', '"', '');
            }
            fclose($out);
        }, 'sampling_link_registry.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function integrity()
    {
        $flags = DB::table('technical_duplicate_flags as f')
            ->join('survey_sessions as ss', 'f.survey_session_id', '=', 'ss.id')
            ->join('participants as p', 'ss.participant_id', '=', 'p.id')
            ->select('f.*', 'p.research_id')->orderByDesc('f.id')->paginate(50);
        return view('admin.research_integrity', compact('flags'));
    }

    public function reviewFlag(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:OPEN,REVIEWED', 'review_note' => 'required|string|max:2000']);
        DB::transaction(function () use ($data, $id) {
            abort_unless(DB::table('technical_duplicate_flags')->where('id', $id)->exists(), 404);
            DB::table('technical_duplicate_flags')->where('id', $id)->update($data + [
                'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            Audit::log('technical_duplicate.reviewed', 'technical_duplicate_flags', $id, $data);
        });
        return back()->with('ok', 'Đã ghi nhận rà soát kỹ thuật. Quyết định Valid/Review/Exclude thực hiện ở QC.');
    }

    public function longExport()
    {
        $rows = DB::table('answers as a')->join('survey_sessions as ss', 'a.survey_session_id', '=', 'ss.id')
            ->join('participants as p', 'ss.participant_id', '=', 'p.id')
            ->join('surveys as f', 'ss.survey_id', '=', 'f.id')
            ->join('questions as q', 'a.question_id', '=', 'q.id')
            ->join('sites as s', 'p.site_id', '=', 's.id')->join('waves as w', 'p.wave_id', '=', 'w.id')
            ->leftJoin('sample_links as l', 'p.sample_link_id', '=', 'l.id')
            ->leftJoin('departments as d', 'l.department_id', '=', 'd.id')
            ->where('ss.status', 'complete')->whereColumn('q.survey_id', 'ss.survey_id')
            ->select('p.research_id', 'p.group_code', 's.code as site_code', 'd.code as department_code',
                'w.code as wave_code', 'ss.id as session_id', 'f.instrument_id', 'f.form_version',
                'q.source_item_code', 'q.code', 'q.construct_name', 'q.scale', 'a.value', 'a.missing_code')
            ->orderBy('ss.id')->orderBy('q.position');
        $analyst = auth()->user()->hasRole('ANALYST');
        if ($analyst) {
            // Analysts receive approved analysis records, never unrestricted raw responses.
            $rows->whereNotIn('p.state', ['withdrawal_requested', 'withdrawn'])
                ->whereRaw("(SELECT decision FROM qc_decisions WHERE survey_session_id=ss.id ORDER BY id DESC LIMIT 1) = 'valid'")
                ->whereNotExists(function ($q) {
                    $q->selectRaw('1')->from('participant_requests as r')->whereColumn('r.participant_id', 'p.id')
                        ->where('r.type', 'withdrawal')->where('r.status', '!=', 'resolved');
                });
        }
        $rows = $rows->get();
        $flagged = DB::table('technical_duplicate_flags')->distinct()->pluck('survey_session_id')->flip();
        Audit::log($analyst ? 'data.long_analysis_exported' : 'data.long_raw_exported', 'answers', null, ['rows' => $rows->count()]);
        return response()->streamDownload(function () use ($rows, $flagged) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['research_id', 'group_code', 'site_code', 'department_code', 'wave_code',
                'position_code', 'seniority_years', 'training_experience', 'student_year', 'practicum_experience',
                'session_id', 'instrument_id', 'form_version', 'duplicate_flag', 'item_code', 'construct_name',
                'scale_code', 'answer_value', 'missing_code'], ',', '"', '');
            foreach ($rows as $row) {
                $value = $row->missing_code ? null : json_decode($row->value ?? 'null', true);
                $cells = [$row->research_id, $row->group_code, $row->site_code, $row->department_code, $row->wave_code,
                    '', '', '', '', '', $row->session_id, $row->instrument_id, $row->form_version,
                    (int) $flagged->has($row->session_id), $row->source_item_code ?? $row->code,
                    $row->construct_name, $row->scale, is_scalar($value) ? $value : '', $row->missing_code];
                $cells = array_map(static function ($v) {
                    $v = (string) ($v ?? '');
                    return !is_numeric($v) && preg_match('/^[\s]*[=+@-]/u', $v) ? "'".$v : $v;
                }, $cells);
                fputcsv($out, $cells, ',', '"', '');
            }
            fclose($out);
        }, $analyst ? 'research_analysis_long.csv' : 'research_raw_long.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function siteStatus()
    {
        $siteId = auth()->user()->assigned_site_id;
        abort_unless($siteId, 403, 'Chưa được phân công điểm thu mẫu.');
        $links = DB::table('sample_links as l')->join('sites as s', 'l.site_id', '=', 's.id')
            ->join('waves as w', 'l.wave_id', '=', 'w.id')->where('l.site_id', $siteId)
            ->select('l.id', 'l.point_code', 'l.group_code', 'l.status', 'l.valid_until', 's.code as site_code', 'w.code as wave_code')
            ->orderBy('l.id')->get();
        return view('admin.site_link_status', compact('links'));
    }
}
