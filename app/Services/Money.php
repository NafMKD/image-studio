<?php

namespace App\Services;

use InvalidArgumentException;

final class Money
{
    public static function fromUsd(string $value): int
    {
        if (! preg_match('/^(0|[1-9][0-9]*)(?:\.([0-9]{1,9}))?$/', $value, $m)) {
            throw new InvalidArgumentException('USD amount must have at most 9 decimal places.');
        }

        return ((int) $m[1] * 1_000_000_000)
            + (int) str_pad($m[2] ?? '', 9, '0');
    }

    public static function display(int $nanos): string
    {
        $sign = $nanos < 0 ? '-' : '';
        $absolute = abs($nanos);
        $whole = intdiv($absolute, 1_000_000_000);
        $fraction = rtrim(str_pad((string) ($absolute % 1_000_000_000), 9, '0', STR_PAD_LEFT), '0');

        return $sign.'$'.$whole.'.'.str_pad($fraction, 2, '0');
    }
}
