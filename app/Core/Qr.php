<?php

declare(strict_types=1);

namespace Bestandio\Core;

use InvalidArgumentException;

/**
 * QR code encoder following ISO/IEC 18004 (model 2, versions 1–40).
 * Byte and alphanumeric mode, error correction L/M/Q/H, output as matrix or SVG.
 */
final class Qr
{
    public const ECC_L = 'L';
    public const ECC_M = 'M';
    public const ECC_Q = 'Q';
    public const ECC_H = 'H';

    private const ALNUM = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:';

    /** Format-information bits for each error correction level. */
    private const ECC_FORMAT = ['L' => 1, 'M' => 0, 'Q' => 3, 'H' => 2];

    /** Error correction codewords per block, index = version (ISO/IEC 18004 table 9). */
    private const ECC_PER_BLOCK = [
        'L' => [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'M' => [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
        'Q' => [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
        'H' => [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
    ];

    /** Number of error correction blocks, index = version. */
    private const ECC_BLOCKS = [
        'L' => [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
        'M' => [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
        'Q' => [-1, 1, 2, 2, 4, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
        'H' => [-1, 1, 2, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81],
    ];

    private int $version;
    private int $size;
    /** @var array<int,array<int,bool>> [y][x] */
    private array $modules = [];
    /** @var array<int,array<int,bool>> [y][x] */
    private array $isFunction = [];

    private function __construct(string $text, string $ecc, int $minVersion)
    {
        $alnum = $text !== '' && strspn($text, self::ALNUM) === strlen($text);
        $data = $alnum ? self::alnumBits($text) : self::byteBits($text);

        // Smallest version that fits.
        for ($v = max(1, $minVersion); ; $v++) {
            if ($v > 40) {
                throw new InvalidArgumentException('Data too long for a QR code.');
            }
            $countBits = self::countBits($alnum, $v);
            $capacity = self::dataCodewords($v, $ecc) * 8;
            $used = 4 + $countBits + strlen($data);
            if ($used <= $capacity) {
                break;
            }
        }
        $this->version = $v;
        $this->size = $v * 4 + 17;

        $bits = ($alnum ? '0010' : '0100') . self::pad(decbin(strlen($text)), $countBits) . $data;
        $bits .= str_repeat('0', min(4, $capacity - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $codewords = array_map('bindec', str_split($bits, 8));
        for ($pad = 0xEC; count($codewords) < $capacity / 8; $pad ^= 0xEC ^ 0x11) {
            $codewords[] = $pad;
        }

        $fill = array_fill(0, $this->size, array_fill(0, $this->size, false));
        $this->modules = $fill;
        $this->isFunction = $fill;
        $this->drawFunctionPatterns();
        $all = $this->addEccAndInterleave($codewords, $ecc);
        $this->drawCodewords($all);

        // Pick the mask with the lowest penalty.
        $best = 0;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($ecc, $mask);
            $score = $this->penalty();
            if ($score < $bestScore) {
                $best = $mask;
                $bestScore = $score;
            }
            $this->applyMask($mask); // undo (XOR)
        }
        $this->applyMask($best);
        $this->drawFormatBits($ecc, $best);
    }

    public static function encode(string $text, string $ecc = self::ECC_Q, int $minVersion = 1): self
    {
        if (!isset(self::ECC_FORMAT[$ecc])) {
            throw new InvalidArgumentException('Unknown error correction level.');
        }
        return new self($text, $ecc, $minVersion);
    }

    public function version(): int
    {
        return $this->version;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function dark(int $x, int $y): bool
    {
        return $this->modules[$y][$x];
    }

    /** SVG with a 4-module quiet zone; one path, crisp edges. */
    public function svg(int $border = 4): string
    {
        $dim = $this->size + 2 * $border;
        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $path .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
                }
            }
        }
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<svg xmlns="http://www.w3.org/2000/svg" version="1.1" viewBox="0 0 ' . $dim . ' ' . $dim . '"'
            . ' shape-rendering="crispEdges">'
            . '<rect width="100%" height="100%" fill="#ffffff"/>'
            . '<path d="' . $path . '" fill="#000000"/></svg>' . "\n";
    }

    // ------------------------------------------------------------ data

    private static function byteBits(string $text): string
    {
        $bits = '';
        $n = strlen($text);
        for ($i = 0; $i < $n; $i++) {
            $bits .= self::pad(decbin(ord($text[$i])), 8);
        }
        return $bits;
    }

    private static function alnumBits(string $text): string
    {
        $bits = '';
        $n = strlen($text);
        for ($i = 0; $i + 1 < $n; $i += 2) {
            $v = strpos(self::ALNUM, $text[$i]) * 45 + strpos(self::ALNUM, $text[$i + 1]);
            $bits .= self::pad(decbin($v), 11);
        }
        if ($n % 2 === 1) {
            $bits .= self::pad(decbin(strpos(self::ALNUM, $text[$n - 1])), 6);
        }
        return $bits;
    }

    private static function countBits(bool $alnum, int $version): int
    {
        $idx = $version <= 9 ? 0 : ($version <= 26 ? 1 : 2);
        return $alnum ? [9, 11, 13][$idx] : [8, 16, 16][$idx];
    }

    private static function pad(string $bin, int $len): string
    {
        return str_pad($bin, $len, '0', STR_PAD_LEFT);
    }

    /** Number of data modules after removing all function patterns. */
    private static function rawDataModules(int $v): int
    {
        $result = (16 * $v + 128) * $v + 64;
        if ($v >= 2) {
            $align = intdiv($v, 7) + 2;
            $result -= (25 * $align - 10) * $align - 55;
            if ($v >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    private static function dataCodewords(int $v, string $ecc): int
    {
        return intdiv(self::rawDataModules($v), 8) - self::ECC_PER_BLOCK[$ecc][$v] * self::ECC_BLOCKS[$ecc][$v];
    }

    /** @param list<int> $data */
    private function addEccAndInterleave(array $data, string $ecc): array
    {
        $v = $this->version;
        $numBlocks = self::ECC_BLOCKS[$ecc][$v];
        $eccLen = self::ECC_PER_BLOCK[$ecc][$v];
        $raw = intdiv(self::rawDataModules($v), 8);
        $numShort = $numBlocks - $raw % $numBlocks;
        $shortLen = intdiv($raw, $numBlocks);

        $divisor = self::rsDivisor($eccLen);
        $blocks = [];
        $k = 0;
        for ($i = 0; $i < $numBlocks; $i++) {
            $len = $shortLen - $eccLen + ($i < $numShort ? 0 : 1);
            $dat = array_slice($data, $k, $len);
            $k += $len;
            $eccBytes = self::rsRemainder($dat, $divisor);
            if ($i < $numShort) {
                $dat[] = -1; // placeholder so all blocks have equal length
            }
            $blocks[] = array_merge($dat, $eccBytes);
        }

        $out = [];
        $blockLen = count($blocks[0]);
        for ($i = 0; $i < $blockLen; $i++) {
            foreach ($blocks as $j => $block) {
                if ($i !== $shortLen - $eccLen || $j >= $numShort) {
                    $out[] = $block[$i];
                }
            }
        }
        return $out;
    }

    private static function gfMul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMul($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMul($root, 0x02);
        }
        return $result;
    }

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coef) {
                $result[$i] ^= self::gfMul($coef, $factor);
            }
        }
        return $result;
    }

    // -------------------------------------------------------- drawing

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        $s = $this->size;
        for ($i = 0; $i < $s; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($s - 4, 3);
        $this->drawFinder(3, $s - 4);

        $pos = $this->alignmentPositions();
        $n = count($pos);
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $corner = ($i === 0 && $j === 0) || ($i === 0 && $j === $n - 1) || ($i === $n - 1 && $j === 0);
                if (!$corner) {
                    $this->drawAlignment($pos[$i], $pos[$j]);
                }
            }
        }
        $this->drawFormatBits(self::ECC_L, 0); // reserve area, real bits drawn later
        $this->drawVersion();
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $d = max(abs($dx), abs($dy));
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $this->setFunction($x, $y, $d !== 2 && $d !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return list<int> */
    private function alignmentPositions(): array
    {
        $v = $this->version;
        if ($v === 1) {
            return [];
        }
        $num = intdiv($v, 7) + 2;
        $step = $v === 32 ? 26 : (int) ceil(($v * 4 + 4) / ($num * 2 - 2)) * 2;
        $result = [];
        for ($pos = $this->size - 7; count($result) < $num - 1; $pos -= $step) {
            array_unshift($result, $pos);
        }
        array_unshift($result, 6);
        return $result;
    }

    private function drawFormatBits(string $ecc, int $mask): void
    {
        $data = (self::ECC_FORMAT[$ecc] << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;
        $bit = static fn (int $i): bool => (($bits >> $i) & 1) === 1;
        $s = $this->size;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, $bit($i));
        }
        $this->setFunction(8, 7, $bit(6));
        $this->setFunction(8, 8, $bit(7));
        $this->setFunction(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($s - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $s - 15 + $i, $bit($i));
        }
        $this->setFunction(8, $s - 8, true); // dark module
    }

    private function drawVersion(): void
    {
        $v = $this->version;
        if ($v < 7) {
            return;
        }
        $rem = $v;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($v << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $dark);
            $this->setFunction($b, $a, $dark);
        }
    }

    /** @param list<int> $data */
    private function drawCodewords(array $data): void
    {
        $s = $this->size;
        $total = count($data) * 8;
        $i = 0;
        for ($right = $s - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $s; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $s - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($data[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        $s = $this->size;
        for ($y = 0; $y < $s; $y++) {
            for ($x = 0; $x < $s; $x++) {
                if ($this->isFunction[$y][$x]) {
                    continue;
                }
                $invert = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    7 => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($invert) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** Penalty score per ISO/IEC 18004 section 7.8.3 (rules N1–N4). */
    private function penalty(): int
    {
        $s = $this->size;
        $score = 0;
        $dark = 0;
        $lines = [];
        for ($y = 0; $y < $s; $y++) {
            $row = '';
            $col = '';
            for ($x = 0; $x < $s; $x++) {
                $row .= $this->modules[$y][$x] ? '1' : '0';
                $col .= $this->modules[$x][$y] ? '1' : '0';
            }
            $lines[] = $row;
            $lines[] = $col;
            $dark += substr_count($row, '1');
        }
        foreach ($lines as $line) {
            // N1: runs of 5 or more same-colour modules.
            if (preg_match_all('/0{5,}|1{5,}/', $line, $m)) {
                foreach ($m[0] as $run) {
                    $score += 3 + strlen($run) - 5;
                }
            }
            // N3: finder-like pattern with 4 light modules on one side (border counts as light).
            $padded = '0000' . $line . '0000';
            $score += 40 * preg_match_all('/(?=10111010000|00001011101)/', $padded);
        }
        // N2: 2x2 blocks of the same colour.
        for ($y = 0; $y < $s - 1; $y++) {
            for ($x = 0; $x < $s - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }
        // N4: balance of dark and light modules.
        $total = $s * $s;
        $k = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $score + max(0, $k) * 10;
    }
}
