<?php

namespace App\Services;

use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class SamplingRoute
{
    public function resolve(string $token): object
    {
        $link = DB::table('sample_links')->where(function ($q) use ($token) {
            $q->where(fn ($q) => $q->where('token_hashed', true)->where('token', hash('sha256', $token)))
                ->orWhere(fn ($q) => $q->where('token_hashed', false)->where('token', $token));
        })->first();
        $allowed = $link && config('research.collection_open') && $link->active;
        if ($allowed && $link->token_hashed) {
            $allowed = $link->status === 'ACTIVE' && $link->valid_from && $link->valid_until
                && now()->gte($link->valid_from) && now()->lt($link->valid_until)
                && $link->survey_id && $link->approved_by;
        }
        if ($allowed) {
            $allowed = DB::table('sites')->where('id', $link->site_id)->where('active', true)->exists()
                && DB::table('waves')->where('id', $link->wave_id)->where('active', true)->exists();
            if ($allowed && $link->token_hashed) {
                $wave = DB::table('waves')->find($link->wave_id);
                $form = $this->survey($link);
                $allowed = $form && $wave->instrument_id === $form->instrument_id
                    && (!$wave->valid_from || now()->gte($wave->valid_from))
                    && (!$wave->valid_until || now()->lt($wave->valid_until));
            }
            if ($link->department_id) {
                $allowed = $allowed && DB::table('departments')->where('id', $link->department_id)
                    ->where('site_id', $link->site_id)->where('active', true)->exists();
            }
        }
        if (!$allowed) {
            Audit::log('sampling_route.denied', 'sample_links', $link?->id);
            abort(404, 'Liên kết chưa mở, đã dừng hoặc hết hạn.');
        }
        // The consent form must post to the original bearer URL, never to its database hash.
        $link->token = $token;
        return $link;
    }

    public function survey(object $link): ?object
    {
        $query = DB::table('surveys')->where('group_code', $link->group_code);
        if ($link->survey_id) {
            // Superseded versions remain immutable and usable by already issued links.
            return $query->where('id', $link->survey_id)->whereIn('status', ['published', 'superseded'])->first();
        }
        return $query->where('status', 'published')->orderByDesc('version')->first();
    }
}
