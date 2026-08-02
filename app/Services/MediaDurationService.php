<?php

declare(strict_types=1);

namespace App\Services;

final class MediaDurationService
{
    public function seconds(string $path, string $mimeType): ?float
    {
        if (in_array($mimeType, ['video/mp4', 'video/quicktime'], true)) {
            return $this->mp4Duration($path);
        }
        if ($mimeType === 'video/webm') {
            return $this->webmDuration($path);
        }
        return null;
    }

    private function mp4Duration(string $path): ?float
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) return null;
        $size = filesize($path);
        $position = 0;
        while ($position + 8 <= $size) {
            fseek($handle, $position);
            $header = fread($handle, 8);
            if (strlen($header) !== 8) break;
            $atomSize = unpack('N', substr($header, 0, 4))[1];
            $type = substr($header, 4, 4);
            if ($atomSize < 8) break;
            if ($type === 'moov') {
                $duration = $this->mvhdDuration($handle, $position + 8, $atomSize - 8);
                fclose($handle);
                return $duration;
            }
            $position += $atomSize;
        }
        fclose($handle);
        return null;
    }

    private function mvhdDuration($handle, int $start, int $length): ?float
    {
        $position = $start;
        $end = $start + $length;
        while ($position + 8 <= $end) {
            fseek($handle, $position);
            $header = fread($handle, 8);
            if (strlen($header) !== 8) return null;
            $atomSize = unpack('N', substr($header, 0, 4))[1];
            if ($atomSize < 8) return null;
            if (substr($header, 4, 4) === 'mvhd') {
                $data = fread($handle, min($atomSize - 8, 32));
                $version = ord($data[0] ?? "\0");
                $timeOffset = $version === 1 ? 20 : 12;
                $durationOffset = $version === 1 ? 24 : 16;
                $timescale = unpack('N', substr($data, $timeOffset, 4))[1] ?? 0;
                if ($timescale <= 0) return null;
                if ($version === 1) {
                    $parts = unpack('Nhigh/Nlow', substr($data, $durationOffset, 8));
                    $duration = ($parts['high'] * 4294967296) + $parts['low'];
                } else {
                    $duration = unpack('N', substr($data, $durationOffset, 4))[1] ?? 0;
                }
                return $duration / $timescale;
            }
            $position += $atomSize;
        }
        return null;
    }

    private function webmDuration(string $path): ?float
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) return null;
        $data = fread($handle, 2097152);
        fclose($handle);
        $scale = $this->ebmlInteger($data, "\x2A\xD7\xB1") ?? 1000000;
        $duration = $this->ebmlFloat($data, "\x44\x89");
        return $duration === null ? null : ($duration * $scale) / 1000000000;
    }

    private function ebmlInteger(string $data, string $id): ?int
    {
        $value = $this->ebmlValue($data, $id);
        if ($value === null || strlen($value) > 8) return null;
        $number = 0;
        foreach (str_split($value) as $byte) $number = ($number * 256) + ord($byte);
        return $number;
    }

    private function ebmlFloat(string $data, string $id): ?float
    {
        $value = $this->ebmlValue($data, $id);
        if ($value === null) return null;
        if (strlen($value) === 4) return unpack('G', $value)[1];
        if (strlen($value) === 8) return unpack('E', $value)[1];
        return null;
    }

    private function ebmlValue(string $data, string $id): ?string
    {
        $position = strpos($data, $id);
        if ($position === false) return null;
        $offset = $position + strlen($id);
        $first = ord($data[$offset] ?? "\0");
        if ($first === 0) return null;
        $lengthBytes = 1;
        $mask = 0x80;
        while (($first & $mask) === 0 && $lengthBytes < 8) {
            $mask >>= 1;
            $lengthBytes++;
        }
        $length = $first & ($mask - 1);
        for ($index = 1; $index < $lengthBytes; $index++) {
            $length = ($length * 256) + ord($data[$offset + $index] ?? "\0");
        }
        return substr($data, $offset + $lengthBytes, $length);
    }
}
