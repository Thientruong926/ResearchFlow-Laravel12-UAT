<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class DesignCodebookImport
{
    /** Import the supplied prototype banks as DRAFT only; never publish a scientific instrument. */
    public function import(string $itemsFile, string $scalesFile): int
    {
        $items = $this->readCsv($itemsFile);
        $scales = [];
        foreach ($this->readCsv($scalesFile) as $scale) {
            $choices = [];
            foreach (explode(';', $scale['levels_text']) as $level) {
                if (!preg_match('/^\s*([1-5])\s+(.+)$/u', $level, $match)) {
                    throw new \InvalidArgumentException('Invalid scale level.');
                }
                $choices[$match[1]] = trim($match[2]);
            }
            if (count($choices) !== 5) throw new \InvalidArgumentException('Scale must have five levels.');
            $scales[$scale['instrument_id'].'|'.$scale['form_version'].'|'.$scale['scale_code']] = $choices;
        }
        return DB::transaction(function () use ($items, $scales) {
            $forms = [];
            foreach ($items as $item) {
                if (!in_array($item['instrument_id'], ['OBS2', 'OBS4'], true)
                    || !in_array($item['group_code'], $item['instrument_id'] === 'OBS2' ? ['BGH', 'KHOA'] : ['BGH', 'KHOA', 'GV', 'SV'], true)) {
                    throw new \InvalidArgumentException('Invalid instrument/group mapping.');
                }
                $key = $item['instrument_id'].'|'.$item['form_version'].'|'.$item['group_code'];
                if (!isset($forms[$key])) {
                    if (DB::table('surveys')->where('instrument_id', $item['instrument_id'])
                        ->where('form_version', $item['form_version'])->where('group_code', $item['group_code'])->exists()) {
                        throw new \InvalidArgumentException('This form version already exists; no records changed.');
                    }
                    $forms[$key] = DB::table('surveys')->insertGetId([
                        'instrument_id' => $item['instrument_id'], 'form_version' => $item['form_version'],
                        'group_code' => $item['group_code'],
                        'version' => ((int) DB::table('surveys')->where('group_code', $item['group_code'])->max('version')) + 1,
                        'title' => '[DỮ LIỆU THỬ] '.$key, 'status' => 'draft', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
                $choices = $scales[$item['instrument_id'].'|'.$item['form_version'].'|'.$item['scale_code']] ?? null;
                if (!$choices) throw new \InvalidArgumentException('Missing matching scale definition.');
                // Analysis variable uses safe ASCII; original item code is preserved separately.
                $code = str_replace('-', '_', $item['item_code']);
                if (!preg_match('/^[A-Za-z][A-Za-z0-9_]{0,30}$/D', $code)) throw new \InvalidArgumentException('Invalid item code.');
                DB::table('questions')->insert([
                    'survey_id' => $forms[$key], 'code' => $code, 'source_item_code' => $item['item_code'],
                    'construct_name' => $item['construct_name'], 'source_ref' => $item['source_ref'],
                    'label' => $item['question_text'], 'variable_label' => mb_substr($item['question_text'], 0, 255),
                    'type' => 'likert', 'choices' => json_encode($choices + ['97' => 'Không biết', '98' => 'Không áp dụng'], JSON_UNESCAPED_UNICODE),
                    'required' => false, 'position' => (int) $item['item_order'], 'scale' => $item['scale_code'],
                    'missing_code' => '97,98,BLANK', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            \App\Support\Audit::log('design_codebook.imported_draft', 'surveys', null, ['forms' => count($forms), 'items' => count($items)]);
            return count($items);
        });
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if (!$handle) throw new \InvalidArgumentException('Cannot open codebook CSV.');
        try {
            $headers = fgetcsv($handle, 0, ',', '"', '');
            if (!$headers) throw new \InvalidArgumentException('Missing CSV header.');
            $headers[0] = ltrim($headers[0], "\xEF\xBB\xBF");
            $rows = [];
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if (count($row) !== count($headers)) throw new \InvalidArgumentException('Malformed CSV row.');
                $rows[] = array_combine($headers, $row);
            }
            if (!$rows) throw new \InvalidArgumentException('Empty codebook.');
            return $rows;
        } finally {
            fclose($handle);
        }
    }
}
