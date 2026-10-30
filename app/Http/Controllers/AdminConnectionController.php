<?php

namespace App\Http\Controllers;

use App\Services\ConnectionHealthService;
use App\Services\ConnectionSources;
use App\Services\ResearchSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminConnectionController extends Controller
{
    public function __construct(private ResearchSettings $settings, private ConnectionSources $sources, private ConnectionHealthService $health) {}

    public function index()
    {
        $sources = $this->sources->all($this->settings->all());
        $snapshots = [];
        foreach ($sources as $id => $source) {
            $snapshots[$id] = $this->health->snapshot($source);
        }
        return view('admin.connections', compact('sources', 'snapshots'));
    }

    public function check(string $source)
    {
        $this->health->check($this->source($source));
        return response()->json(['message' => 'Đã kiểm tra', 'state' => $this->health->snapshot($this->source($source))]);
    }

    public function verification(Request $request, string $source)
    {
        $data = $request->validate([
            'revision' => ['required', 'string', 'size:64'],
            'status' => ['required', Rule::in(['pending', 'verified', 'not_found', 'unverified'])],
            'confirmed' => ['accepted'],
        ]);
        $this->health->verify($this->source($source), $data['revision'], $data['status']);
        return redirect()->route('admin.connections')->with('ok', 'Đã ghi nhận kết quả đối chiếu thủ công.');
    }

    private function source(string $id): array
    {
        $sources = $this->sources->all($this->settings->all());
        abort_unless(isset($sources[$id]), 404);
        return $sources[$id];
    }
}
