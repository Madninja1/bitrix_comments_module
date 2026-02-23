<?php

namespace Ameton\Comments\Util;

final class Lang
{
    public static function plural(int $number, string $one, string $few, string $many): string
    {
        $n = abs($number) % 100;
        $n1 = $n % 10;

        if ($n > 10 && $n < 20) return $many;
        if ($n1 > 1 && $n1 < 5) return $few;
        if ($n1 == 1) return $one;
        return $many;
    }
}