<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class ResearchId
{
    public function generate(): string
    {
        return 'R'.$this->base32(random_bytes(16));
    }

    public function registryCode(string $kind): string
    {
        if (!in_array($kind, ['S', 'D', 'W'], true)) {
            throw new \InvalidArgumentException('Unsupported registry code kind.');
        }
        return $kind.'-'.substr($this->base32(random_bytes(7)), 0, 10);
    }

    public function createParticipant(array $attributes): int
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $id = $this->generate();
            try {
                return DB::table('participants')->insertGetId(array_merge($attributes, ['research_id' => $id]));
            } catch (UniqueConstraintViolationException $exception) {
                // Retry only collisions of this ID, not other integrity failures.
                if (!DB::table('participants')->where('research_id', $id)->exists()) {
                    throw $exception;
                }
                \App\Support\Audit::log('research_id.collision', 'participants');
            }
        }
        throw new \RuntimeException('Cannot allocate a unique Research ID.');
    }

    private function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $result = '';
        foreach (unpack('C*', $bytes) as $byte) {
            $buffer = ($buffer << 8) | $byte;
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $result .= $alphabet[($buffer << (5 - $bits)) & 31];
        }
        return $result;
    }
}
