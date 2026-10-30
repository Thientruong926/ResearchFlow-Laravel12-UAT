<?php

namespace App\Services;

use App\Support\Audit;
use Illuminate\Support\Facades\DB;

class TechnicalDuplicates
{
    public function flag(int $sessionId, string $rule, string $evidence): void
    {
        // Never persist raw bearer tokens, IP addresses or answers as evidence.
        $created = DB::table('technical_duplicate_flags')->insertOrIgnore([
            'survey_session_id' => $sessionId, 'rule_code' => $rule,
            'evidence_hash' => hash('sha256', $evidence), 'status' => 'OPEN',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        if ($created) {
            Audit::log('technical_duplicate.flagged', 'survey_sessions', $sessionId, ['rule_code' => $rule]);
        }
    }

    public function scan(): int
    {
        $sessions = DB::table('survey_sessions as ss')->join('participants as p', 'ss.participant_id', '=', 'p.id')
            ->join('surveys as s', 'ss.survey_id', '=', 's.id')
            ->select('ss.id', 'p.research_id', 'p.wave_id', 's.instrument_id')->orderBy('ss.id')->get();
        $repeated = $sessions->groupBy(fn ($s) => $s->research_id.'|'.$s->wave_id.'|'.($s->instrument_id ?? 'legacy'))
            ->filter(fn ($group) => $group->count() > 1);
        $count = 0;
        foreach ($repeated as $key => $group) {
            foreach ($group as $session) {
                $this->flag($session->id, 'RESEARCH_REPEAT', $key);
                $count++;
            }
        }
        return $count;
    }
}
