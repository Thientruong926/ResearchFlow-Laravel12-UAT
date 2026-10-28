<?php

namespace App\Services;

use Illuminate\Support\Arr;
use RuntimeException;

class ResearchSettings
{
    public function all(): array
    {
        $defaults = config('research.default_settings', []);
        $path = config('research.settings_file');

        if (! is_file($path)) {
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? array_replace_recursive($defaults, $decoded) : $defaults;
    }

    public function research(): array
    {
        return $this->all()['research'];
    }

    public function schools(): array
    {
        return array_values($this->all()['schools']);
    }

    public function assignments(): array
    {
        return array_values($this->all()['assignments']);
    }

    public function assignment(string $school, string $group): ?array
    {
        return collect($this->assignments())->first(
            fn (array $assignment) => $assignment['school'] === $school
                && $assignment['group'] === $group
                && ($assignment['active'] ?? false)
        );
    }

    public function school(string $code): ?array
    {
        return collect($this->schools())->firstWhere('code', $code);
    }

    public function updateResearch(array $research, array $columns): void
    {
        $settings = $this->all();
        $settings['research'] = $research;
        $settings['sheet_columns'] = $columns;
        $this->write($settings);
    }

    public function updateSchools(string $lines): void
    {
        $schools = [];

        foreach (preg_split('/\r?\n/', trim($lines)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) !== 5 || ! preg_match('/^[A-Z0-9_]{2,32}$/', $parts[0])) {
                throw new RuntimeException('Mỗi trường phải theo mẫu MÃ|Tên trường|Chỉ tiêu CBQL|Chỉ tiêu GV|Chỉ tiêu SV.');
            }

            foreach (array_slice($parts, 2) as $target) {
                if (filter_var($target, FILTER_VALIDATE_INT) === false || (int) $target < 0) {
                    throw new RuntimeException('Chỉ tiêu của mỗi nhóm phải là số nguyên không âm.');
                }
            }

            $schools[] = [
                'code' => $parts[0],
                'name' => $parts[1],
                'targets' => [
                    'manager' => (int) $parts[2],
                    'lecturer' => (int) $parts[3],
                    'student' => (int) $parts[4],
                ],
            ];
        }

        if ($schools === []) {
            throw new RuntimeException('Cần cấu hình ít nhất một trường.');
        }

        $settings = $this->all();
        $settings['schools'] = $schools;
        $validCodes = array_column($schools, 'code');
        $settings['assignments'] = array_values(array_filter(
            $settings['assignments'],
            fn (array $assignment) => in_array($assignment['school'], $validCodes, true)
        ));
        $this->write($settings);
    }

    public function upsertAssignment(array $data): void
    {
        $settings = $this->all();
        $data['id'] = $data['school'].'-'.$data['group'];
        $data['active'] = (bool) ($data['active'] ?? false);
        $data['entries'] = Arr::only($data['entries'], [
            'school', 'group', 'use', 'level', 'total', 'digital', 'note',
        ]);

        $settings['assignments'] = array_values(array_filter(
            $settings['assignments'],
            fn (array $assignment) => ($assignment['id'] ?? '') !== $data['id']
        ));
        $settings['assignments'][] = $data;
        $this->write($settings);
    }

    public function removeAssignment(string $id): void
    {
        $settings = $this->all();
        $settings['assignments'] = array_values(array_filter(
            $settings['assignments'],
            fn (array $assignment) => ($assignment['id'] ?? '') !== $id
        ));
        $this->write($settings);
    }

    public function schoolsAsLines(): string
    {
        return collect($this->schools())->map(function (array $school) {
            return implode('|', [
                $school['code'],
                $school['name'],
                $school['targets']['manager'] ?? 0,
                $school['targets']['lecturer'] ?? 0,
                $school['targets']['student'] ?? 0,
            ]);
        })->implode(PHP_EOL);
    }

    private function write(array $settings): void
    {
        $path = config('research.settings_file');
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Không thể tạo thư mục cấu hình.');
        }

        $json = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false || file_put_contents($path, $json, LOCK_EX) === false) {
            throw new RuntimeException('Không thể lưu cấu hình nghiên cứu.');
        }
    }
}
