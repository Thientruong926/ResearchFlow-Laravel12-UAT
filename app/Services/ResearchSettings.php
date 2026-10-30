<?php

namespace App\Services;

use Illuminate\Support\Arr;
use RuntimeException;

class ResearchSettings
{
    public function __construct(private SettingsFileStore $store) {}
    public function all(): array
    {
        return $this->store->read();
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

    public function updateResearch(array $research, string $revision, string $actor = 'admin'): void
    {
        $this->store->update($revision, function ($settings) use ($research) {
            $settings['research'] = array_replace($settings['research'], Arr::only($research, ['title','introduction','consent','contact_name','contact_email','contact_phone','contact_address']));
            return $settings;
        }, $actor);
    }

    public function updateColumns(array $columns, string $revision, string $actor = 'admin'): void
    {
        $this->store->update($revision, function ($settings) use ($columns) {
            $settings['sheet_columns'] = array_replace($settings['sheet_columns'], Arr::only($columns, ['timestamp','school','group']));
            return $settings;
        }, $actor);
    }

    public function updateSchools(string $lines, string $revision, string $actor = 'admin'): void
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

        $this->store->update($revision, function ($settings) use ($schools) {
        $settings['schools'] = $schools;
        $validCodes = array_column($schools, 'code');
        $settings['assignments'] = array_values(array_filter(
            $settings['assignments'],
            fn (array $assignment) => in_array($assignment['school'], $validCodes, true)
        ));
        return $settings;
        }, $actor);
    }

    public function upsertAssignment(array $data, string $revision, string $actor = 'admin'): void
    {
        $data['id'] = $data['school'].'-'.$data['group'];
        $data['active'] = (bool) ($data['active'] ?? false);
        $data['entries'] = Arr::only($data['entries'], [
            'school', 'group', 'use', 'level', 'total', 'digital', 'note',
        ]);

        $this->store->update($revision, function ($settings) use ($data) {
        if (! in_array($data['school'], array_column($settings['schools'], 'code'), true)) throw new RuntimeException('Trường không còn trong cấu hình.');
        $settings['assignments'] = array_values(array_filter(
            $settings['assignments'],
            fn (array $assignment) => ($assignment['id'] ?? '') !== $data['id']
        ));
        $settings['assignments'][] = $data;
        return $settings;
        }, $actor);
    }

    public function removeAssignment(string $id, string $revision, string $actor = 'admin'): void
    {
        $this->store->update($revision, function ($settings) use ($id) {
        $settings['assignments'] = array_values(array_filter(
            $settings['assignments'],
            fn (array $assignment) => ($assignment['id'] ?? '') !== $id
        ));
        return $settings;
        }, $actor);
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

}
