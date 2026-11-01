<?php

namespace App\Support;

class Money
{
    public static function centavos(string $pesos): int
    {
        if (! preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D', $pesos)) {
            throw new \InvalidArgumentException('Use a PHP amount with up to two decimal places.');
        }
        $parts = explode('.', $pesos);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
