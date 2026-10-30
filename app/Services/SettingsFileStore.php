<?php

namespace App\Services;

use App\Exceptions\SettingsStorageException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/** One host, durable private filesystem. All settings writers use the same sidecar lock. */
class SettingsFileStore
{
    public function read(): array
    {
        return $this->locked(LOCK_SH, fn () => $this->load());
    }

    public function update(string $revision, callable $change, string $actor): array
    {
        return $this->locked(LOCK_EX, function () use ($revision, $change, $actor) {
            $before = $this->load();
            if (! hash_equals($before['_revision'], $revision)) throw new ConflictHttpException('Cấu hình đã được sửa ở nơi khác. Tải lại trang trước khi lưu.');
            $after = $change($before);
            unset($after['_revision']);
            $fields = [];
            foreach ($after as $key => $value) {
                if (str_starts_with($key, '_') || ($before[$key] ?? null) === $value) continue;
                if ($key === 'research' || $key === 'sheet_columns') {
                    foreach ($value as $field => $item) if (($before[$key][$field] ?? null) !== $item) $fields[] = $key.'.'.$field;
                } else $fields[] = $key;
            }
            if ($fields === []) return $before;
            $after['_version'] = ($before['_version'] ?? 0) + 1;
            $nextRevision = $this->revision($after);
            $after['_audit'] = array_slice([...($before['_audit'] ?? []), [
                'fields' => $fields, 'actor' => $actor, 'at' => now('UTC')->toIso8601String(), 'revision' => $nextRevision,
            ]], -100);
            $path = config('research.settings_file');
            $backupDirectory = $path.'.backups';
            // A backup is prepared before replacing the target; failures leave the old JSON intact.
            if (is_file($path)) {
                $this->directory($backupDirectory);
                $backup = $backupDirectory.'/'.sprintf('%020d', $before['_version'] ?? 0).'-'.bin2hex(random_bytes(8)).'.json';
                $this->atomicWrite($backup, $this->backup($before));
                $files = glob($backupDirectory.'/*.json') ?: [];
                rsort($files, SORT_STRING);
                foreach (array_slice($files, 5) as $file) if (! @unlink($file)) throw new SettingsStorageException('Không xoay vòng được backup. Kiểm tra quyền thư mục trước khi lưu.');
            }
            $this->atomicWrite($path, $after);
            return $this->load();
        });
    }

    private function locked(int $mode, callable $callback): array
    {
        $path = config('research.settings_file');
        $this->directory(dirname($path));
        $lock = @fopen($path.'.lock', 'c');
        if (! $lock) throw new SettingsStorageException('Không mở được khóa cấu hình. Kiểm tra quyền thư mục.');
        try {
            if (! flock($lock, $mode)) throw new SettingsStorageException('Không khóa được cấu hình.');
            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function load(): array
    {
        $defaults = config('research.default_settings', []);
        $path = config('research.settings_file');
        $saved = [];
        if (file_exists($path)) {
            $json = @file_get_contents($path);
            try {
                if ($json === false) throw new \RuntimeException();
                $object = json_decode($json, false, 512, JSON_THROW_ON_ERROR);
                if (! $object instanceof \stdClass) throw new \RuntimeException();
                $saved = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
                foreach (['research','schools','assignments','sheet_columns','source_defaults','_audit'] as $key) {
                    if (isset($saved[$key]) && ! is_array($saved[$key])) throw new \RuntimeException();
                    if (array_key_exists($key, $saved) && $saved[$key] === null) throw new \RuntimeException();
                }
                foreach (['schools','assignments','_audit'] as $key) if (isset($saved[$key]) && ! array_is_list($saved[$key])) throw new \RuntimeException();
                if (isset($saved['_version']) && (! is_int($saved['_version']) || $saved['_version'] < 0)) throw new \RuntimeException();
            } catch (\Throwable) {
                throw new SettingsStorageException('File cấu hình hỏng hoặc không đọc được. Cần kiểm tra và khôi phục bản sao hợp lệ.');
            }
        }
        $settings = array_replace_recursive($defaults, $saved);
        foreach (['schools','assignments'] as $list) if (array_key_exists($list, $saved)) $settings[$list] = $saved[$list];
        foreach (['title','introduction','consent','contact_name','contact_email'] as $field) {
            if (! is_string($settings['research'][$field] ?? null)) throw new SettingsStorageException('Cấu trúc nội dung nghiên cứu không hợp lệ. Cần khôi phục cấu hình.');
        }
        foreach (['contact_phone','contact_address'] as $field) {
            if (isset($settings['research'][$field]) && ! is_string($settings['research'][$field])) throw new SettingsStorageException('Cấu trúc liên hệ không hợp lệ.');
        }
        foreach (['timestamp','school','group'] as $field) {
            if (! is_string($settings['sheet_columns'][$field] ?? null)) throw new SettingsStorageException('Mapping cột không hợp lệ.');
        }
        foreach ($settings['schools'] as $school) {
            if (! is_array($school) || ! is_string($school['code'] ?? null) || ! is_string($school['name'] ?? null) || ! is_array($school['targets'] ?? [])) throw new SettingsStorageException('Danh sách trường không hợp lệ.');
        }
        foreach ($settings['assignments'] as $assignment) {
            if (! is_array($assignment)) throw new SettingsStorageException('Danh sách gán phiếu không hợp lệ.');
            foreach (['id','school','group'] as $field) if (! is_string($assignment[$field] ?? null)) throw new SettingsStorageException('Thông tin gán phiếu không hợp lệ.');
            foreach (['form_id','sheet_csv_url'] as $field) if (isset($assignment[$field]) && ! is_string($assignment[$field])) throw new SettingsStorageException('Thông tin nguồn không hợp lệ.');
            if (isset($assignment['entries']) && ! is_array($assignment['entries'])) throw new SettingsStorageException('Mapping Form không hợp lệ.');
        }
        $settings['_version'] ??= 0;
        $settings['_audit'] ??= [];
        $settings['_revision'] = $this->revision($settings);
        return $settings;
    }

    private function revision(array $settings): string
    {
        unset($settings['_revision'], $settings['_audit']);
        return hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR));
    }

    private function directory(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0700, true) && ! is_dir($directory)) throw new SettingsStorageException('Không tạo được thư mục cấu hình.');
    }

    private function atomicWrite(string $path, array $settings): void
    {
        $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            $json = json_encode($settings, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $handle = @fopen($temporary, 'xb');
            if (! $handle) throw new SettingsStorageException('Không tạo được file cấu hình tạm.');
            try {
                @chmod($temporary, 0600);
                if (fwrite($handle, $json) !== strlen($json) || ! fflush($handle) || ! fsync($handle)) throw new SettingsStorageException('Không ghi được cấu hình. Bản hiện tại được giữ nguyên.');
            } finally { fclose($handle); }
            if (! $this->replace($temporary, $path)) throw new SettingsStorageException('Không thay thế được cấu hình. Bản hiện tại được giữ nguyên.');
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    protected function replace(string $temporary, string $target): bool { return @rename($temporary, $target); }

    private function backup(array $settings): array
    {
        // Only known, non-credential configuration is backed up. Unknown fields remain in the live file.
        $safe = array_intersect_key($settings, array_flip(['research','schools','assignments','sheet_columns','source_defaults','_version']));
        $safe['research'] = array_intersect_key($safe['research'], array_flip(['title','introduction','consent','contact_name','contact_email','contact_phone','contact_address']));
        $safe['sheet_columns'] = array_intersect_key($safe['sheet_columns'], array_flip(['timestamp','school','group']));
        $safe['source_defaults'] = array_intersect_key($safe['source_defaults'] ?? [], array_flip(['timestamp_format','timezone']));
        $safe['schools'] = array_map(fn ($s) => ['code'=>$s['code'], 'name'=>$s['name'], 'targets'=>array_intersect_key($s['targets'] ?? [], config('research.groups'))], $safe['schools']);
        $safe['assignments'] = array_map(function ($a) {
            $a = array_intersect_key($a, array_flip(['id','school','group','active','form_id','sheet_csv_url','entries']));
            $form = $a['form_id'] ?? '';
            $a['form_id'] = preg_match('~/d/e/([A-Za-z0-9_-]+)~', $form, $match) ? $match[1] : preg_replace('/[^A-Za-z0-9_-]/', '', $form);
            try { $a['sheet_csv_url'] = app(GoogleSheetsService::class)->csvUrl($a['sheet_csv_url'] ?? ''); } catch (\InvalidArgumentException) { $a['sheet_csv_url'] = ''; }
            $a['entries'] = array_intersect_key($a['entries'] ?? [], array_flip(['school','group','use','level','total','digital','note']));
            return $a;
        }, $safe['assignments']);
        return $safe;
    }
}
