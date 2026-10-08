<?php
namespace App\Support;

final class Cpf
{
    public static function normalize(?string $value): ?string
    {
        if ($value === null || trim($value) === '') return null;
        return preg_replace('/[.\-\s]/u', '', trim($value));
    }
    public static function valid(?string $value): bool
    {
        $value = self::normalize($value);
        if ($value === null || !preg_match('/^\d{11}$/D', $value) || preg_match('/^(\d)\1{10}$/D', $value)) return false;
        for ($position = 9; $position < 11; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) $sum += (int) $value[$i] * ($position + 1 - $i);
            $digit = ($sum * 10) % 11;
            if (($digit === 10 ? 0 : $digit) !== (int) $value[$position]) return false;
        }
        return true;
    }
    public static function fingerprint(?string $value): ?string
    {
        $value = self::normalize($value);
        return $value === null ? null : hash_hmac('sha256', $value, config('app.key'));
    }
    public static function masked(?string $value): string
    {
        $value = self::normalize($value);
        return $value === null ? 'Não informado' : '***.***.'.substr($value, 6, 3).'-**';
    }
    public static function formatted(?string $value): string
    {
        $value = self::normalize($value);
        return $value === null ? '' : substr($value, 0, 3).'.'.substr($value, 3, 3).'.'.substr($value, 6, 3).'-'.substr($value, 9, 2);
    }
}
