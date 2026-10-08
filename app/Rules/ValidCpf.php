<?php
namespace App\Rules;
use App\Support\Cpf;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidCpf implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !Cpf::valid($value)) $fail('Informe um CPF válido, com 11 dígitos.');
    }
}
