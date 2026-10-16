<?php

namespace App\Domains\Intake\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class Curp implements ValidationRule
{
    public static function normalize(string $value): string
    {
        return mb_strtoupper(trim($value), 'UTF-8');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^[A-Z][AEIOUX][A-Z]{2}[0-9]{6}[HM](AS|BC|BS|CC|CL|CM|CS|CH|DF|DG|GT|GR|HG|JC|MC|MN|MS|NT|NL|OC|PL|QT|QR|SP|SL|SR|TC|TS|TL|VZ|YN|ZS|NE)[B-DF-HJ-NP-TV-Z]{3}[A-Z0-9][0-9]$/', $value)) {
            $fail('La CURP debe tener una estructura válida de 18 caracteres.');

            return;
        }
        $year = (ctype_digit($value[16]) ? 1900 : 2000) + (int) substr($value, 4, 2);
        if (! checkdate((int) substr($value, 6, 2), (int) substr($value, 8, 2), $year) || sprintf('%04d-%s-%s', $year, substr($value, 6, 2), substr($value, 8, 2)) > now()->toDateString()) {
            $fail('La fecha de nacimiento en la CURP no es válida.');

            return;
        }
        $alphabet = preg_split('//u', '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ', -1, PREG_SPLIT_NO_EMPTY);
        $sum = 0;
        for ($i = 0; $i < 17; $i++) {
            $sum += array_search($value[$i], $alphabet, true) * (18 - $i);
        }
        if ((10 - $sum % 10) % 10 !== (int) $value[17]) {
            $fail('El dígito verificador de la CURP no coincide.');
        }
    }
}
