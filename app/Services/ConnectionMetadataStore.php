<?php

namespace App\Services;

use RuntimeException;

class ConnectionMetadataStore
{
    private function path(string $id): string
    {
        if (! preg_match('/^src_[a-f0-9]{24}$/', $id)) {
            throw new RuntimeException('Invalid source ID');
        }
        $dir = config('research.connections.metadata_directory');
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Không thể tạo thư mục metadata.');
        }
        return $dir.DIRECTORY_SEPARATOR.$id.'.json';
    }

    public function read(string $id): array
    {
        $path = $this->path($id);
        $lock = fopen($path.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_SH)) {
            throw new RuntimeException('Không thể đọc metadata.');
        }
        try {
            return $this->decode($path);
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function update(string $id, callable $callback): array
    {
        $path = $this->path($id);
        $lock = fopen($path.'.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) fclose($lock);
            abort(429, 'Nguồn đang được kiểm tra. Vui lòng chờ.');
        }
        $temporary = null;
        try {
            $data = $callback($this->decode($path));
            $temporary = $path.'.'.bin2hex(random_bytes(8)).'.tmp';
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            if (file_put_contents($temporary, $json) !== strlen($json) || ! rename($temporary, $path)) {
                throw new RuntimeException('Không thể lưu metadata.');
            }
            return $data;
        } finally {
            if ($temporary && is_file($temporary)) unlink($temporary);
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function decode(string $path): array
    {
        if (! is_file($path)) return [];
        try {
            $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data)) throw new RuntimeException('Invalid metadata');
            return $data;
        } catch (\Throwable) {
            throw new RuntimeException('Metadata bị lỗi. Cần phục hồi hoặc kiểm tra file vận hành.');
        }
    }
}
