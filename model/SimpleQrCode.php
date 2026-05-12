<?php
declare(strict_types=1);

final class SimpleQrCode
{
    private const VERSION = 6;
    private const SIZE = 41;
    private const DATA_CODEWORDS = 136;
    private const ECC_CODEWORDS = 18;
    private const BLOCKS = 2;

    private array $modules = [];
    private array $reserved = [];

    public static function svg(string $text, int $scale = 7, int $quiet = 4): string
    {
        $qr = new self();
        return $qr->renderSvg($text, $scale, $quiet);
    }

    private function renderSvg(string $text, int $scale, int $quiet): string
    {
        $data = $this->encodeData($text);
        $codewords = $this->interleaveBlocks($data);

        $this->initMatrix();
        $this->drawFunctionPatterns();
        $this->drawCodewords($codewords);

        $bestMask = 0;
        $bestScore = PHP_INT_MAX;
        $bestModules = $this->modules;

        for ($mask = 0; $mask < 8; $mask++) {
            $candidate = $this->modules;
            $this->applyMaskTo($candidate, $mask);
            $this->drawFormatBitsTo($candidate, $mask);
            $score = $this->penaltyScore($candidate);
            if ($score < $bestScore) {
                $bestScore = $score;
                $bestMask = $mask;
                $bestModules = $candidate;
            }
        }

        $this->modules = $bestModules;
        $box = (self::SIZE + ($quiet * 2)) * $scale;
        $rects = [];

        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if (!$this->modules[$y][$x]) {
                    continue;
                }
                $rects[] = '<rect x="' . (($x + $quiet) * $scale) . '" y="' . (($y + $quiet) * $scale) . '" width="' . $scale . '" height="' . $scale . '"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $box . '" height="' . $box . '" viewBox="0 0 ' . $box . ' ' . $box . '" role="img" aria-label="QR Code">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<g fill="#111827">' . implode('', $rects) . '</g>'
            . '</svg>';
    }

    private function encodeData(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        $maxBytes = self::DATA_CODEWORDS - 2;
        if (count($bytes) > $maxBytes) {
            $bytes = array_slice($bytes, 0, $maxBytes);
        }

        $bits = [0, 1, 0, 0];
        $length = count($bytes);
        for ($i = 7; $i >= 0; $i--) {
            $bits[] = ($length >> $i) & 1;
        }
        foreach ($bytes as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }

        $capacity = self::DATA_CODEWORDS * 8;
        $terminator = min(4, $capacity - count($bits));
        for ($i = 0; $i < $terminator; $i++) {
            $bits[] = 0;
        }
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        $data = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            $value = 0;
            foreach ($chunk as $bit) {
                $value = ($value << 1) | $bit;
            }
            $data[] = $value;
        }

        $pads = [0xEC, 0x11];
        $i = 0;
        while (count($data) < self::DATA_CODEWORDS) {
            $data[] = $pads[$i % 2];
            $i++;
        }

        return $data;
    }

    private function initMatrix(): void
    {
        $this->modules = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
        $this->reserved = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
    }

    private function setModule(int $x, int $y, bool $dark, bool $reserve = true): void
    {
        if ($x < 0 || $y < 0 || $x >= self::SIZE || $y >= self::SIZE) {
            return;
        }
        $this->modules[$y][$x] = $dark;
        if ($reserve) {
            $this->reserved[$y][$x] = true;
        }
    }

    private function drawFunctionPatterns(): void
    {
        $this->drawFinder(0, 0);
        $this->drawFinder(self::SIZE - 7, 0);
        $this->drawFinder(0, self::SIZE - 7);
        $this->drawAlignment(34, 34);

        for ($i = 8; $i < self::SIZE - 8; $i++) {
            $dark = $i % 2 === 0;
            $this->setModule($i, 6, $dark);
            $this->setModule(6, $i, $dark);
        }

        $this->setModule(8, self::SIZE - 8, true);

        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $this->reserved[8][$i] = true;
                $this->reserved[$i][8] = true;
            }
        }
        for ($i = self::SIZE - 8; $i < self::SIZE; $i++) {
            $this->reserved[8][$i] = true;
            $this->reserved[$i][8] = true;
        }
    }

    private function drawFinder(int $left, int $top): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $x = $left + $dx;
                $y = $top + $dy;
                if ($x < 0 || $y < 0 || $x >= self::SIZE || $y >= self::SIZE) {
                    continue;
                }
                $dark = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6)
                    && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                $this->setModule($x, $y, $dark);
            }
        }
    }

    private function drawAlignment(int $centerX, int $centerY): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $dist = max(abs($dx), abs($dy));
                $this->setModule($centerX + $dx, $centerY + $dy, $dist !== 1);
            }
        }
    }

    private function drawCodewords(array $codewords): void
    {
        $bits = [];
        foreach ($codewords as $byte) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($byte >> $i) & 1;
            }
        }

        $bitIndex = 0;
        $upward = true;
        for ($right = self::SIZE - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vert = 0; $vert < self::SIZE; $vert++) {
                $y = $upward ? self::SIZE - 1 - $vert : $vert;
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    if ($this->reserved[$y][$x]) {
                        continue;
                    }
                    $this->modules[$y][$x] = (($bits[$bitIndex] ?? 0) === 1);
                    $bitIndex++;
                }
            }

            $upward = !$upward;
        }
    }

    private function interleaveBlocks(array $data): array
    {
        $blockSize = intdiv(self::DATA_CODEWORDS, self::BLOCKS);
        $dataBlocks = array_chunk($data, $blockSize);
        $eccBlocks = array_map(fn(array $block): array => $this->reedSolomon($block, self::ECC_CODEWORDS), $dataBlocks);
        $codewords = [];

        for ($i = 0; $i < $blockSize; $i++) {
            foreach ($dataBlocks as $block) {
                $codewords[] = $block[$i];
            }
        }

        for ($i = 0; $i < self::ECC_CODEWORDS; $i++) {
            foreach ($eccBlocks as $block) {
                $codewords[] = $block[$i];
            }
        }

        return $codewords;
    }

    private function applyMaskTo(array &$matrix, int $mask): void
    {
        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if ($this->reserved[$y][$x]) {
                    continue;
                }
                if ($this->maskBit($mask, $x, $y)) {
                    $matrix[$y][$x] = !$matrix[$y][$x];
                }
            }
        }
    }

    private function maskBit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => (($x + $y) % 2) === 0,
            1 => ($y % 2) === 0,
            2 => ($x % 3) === 0,
            3 => (($x + $y) % 3) === 0,
            4 => ((intdiv($y, 2) + intdiv($x, 3)) % 2) === 0,
            5 => ((($x * $y) % 2) + (($x * $y) % 3)) === 0,
            6 => (((($x * $y) % 2) + (($x * $y) % 3)) % 2) === 0,
            default => (((($x + $y) % 2) + (($x * $y) % 3)) % 2) === 0,
        };
    }

    private function drawFormatBitsTo(array &$matrix, int $mask): void
    {
        $data = (1 << 3) | $mask; // Error correction L.
        $bits = (($data << 10) | $this->bchRemainder($data << 10, 0x537)) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $matrix[8][$i] = (($bits >> $i) & 1) === 1;
        }
        $matrix[8][7] = (($bits >> 6) & 1) === 1;
        $matrix[8][8] = (($bits >> 7) & 1) === 1;
        $matrix[7][8] = (($bits >> 8) & 1) === 1;
        for ($i = 9; $i < 15; $i++) {
            $matrix[14 - $i][8] = (($bits >> $i) & 1) === 1;
        }

        for ($i = 0; $i < 8; $i++) {
            $matrix[self::SIZE - 1 - $i][8] = (($bits >> $i) & 1) === 1;
        }
        for ($i = 8; $i < 15; $i++) {
            $matrix[8][self::SIZE - 15 + $i] = (($bits >> $i) & 1) === 1;
        }
    }

    private function bchRemainder(int $value, int $poly): int
    {
        $polyDegree = $this->bitLength($poly) - 1;
        while ($this->bitLength($value) - 1 >= $polyDegree) {
            $value ^= $poly << (($this->bitLength($value) - 1) - $polyDegree);
        }
        return $value;
    }

    private function bitLength(int $value): int
    {
        $length = 0;
        while ($value > 0) {
            $length++;
            $value >>= 1;
        }
        return $length;
    }

    private function penaltyScore(array $matrix): int
    {
        $score = 0;
        for ($y = 0; $y < self::SIZE; $y++) {
            $runColor = $matrix[$y][0];
            $run = 1;
            for ($x = 1; $x < self::SIZE; $x++) {
                if ($matrix[$y][$x] === $runColor) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
                $runColor = $matrix[$y][$x];
                $run = 1;
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }

        for ($x = 0; $x < self::SIZE; $x++) {
            $runColor = $matrix[0][$x];
            $run = 1;
            for ($y = 1; $y < self::SIZE; $y++) {
                if ($matrix[$y][$x] === $runColor) {
                    $run++;
                    continue;
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
                $runColor = $matrix[$y][$x];
                $run = 1;
            }
            if ($run >= 5) {
                $score += 3 + ($run - 5);
            }
        }

        $dark = 0;
        for ($y = 0; $y < self::SIZE; $y++) {
            for ($x = 0; $x < self::SIZE; $x++) {
                if ($matrix[$y][$x]) {
                    $dark++;
                }
                if ($x < self::SIZE - 1 && $y < self::SIZE - 1) {
                    $same = $matrix[$y][$x] === $matrix[$y][$x + 1]
                        && $matrix[$y][$x] === $matrix[$y + 1][$x]
                        && $matrix[$y][$x] === $matrix[$y + 1][$x + 1];
                    if ($same) {
                        $score += 3;
                    }
                }
            }
        }

        $percent = $dark * 100 / (self::SIZE * self::SIZE);
        $score += ((int) (abs($percent - 50) / 5)) * 10;
        return $score;
    }

    private function reedSolomon(array $data, int $degree): array
    {
        $generator = [1];
        for ($i = 0; $i < $degree; $i++) {
            $generator = $this->polyMultiply($generator, [1, $this->gfPow(2, $i)]);
        }

        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;
            for ($i = 0; $i < $degree; $i++) {
                $result[$i] ^= $this->gfMultiply($generator[$i + 1], $factor);
            }
        }

        return $result;
    }

    private function polyMultiply(array $a, array $b): array
    {
        $result = array_fill(0, count($a) + count($b) - 1, 0);
        foreach ($a as $i => $av) {
            foreach ($b as $j => $bv) {
                $result[$i + $j] ^= $this->gfMultiply($av, $bv);
            }
        }
        return $result;
    }

    private function gfPow(int $x, int $power): int
    {
        $result = 1;
        for ($i = 0; $i < $power; $i++) {
            $result = $this->gfMultiply($result, $x);
        }
        return $result;
    }

    private function gfMultiply(int $x, int $y): int
    {
        $result = 0;
        while ($y > 0) {
            if (($y & 1) !== 0) {
                $result ^= $x;
            }
            $x <<= 1;
            if (($x & 0x100) !== 0) {
                $x ^= 0x11D;
            }
            $y >>= 1;
        }
        return $result & 0xFF;
    }
}
