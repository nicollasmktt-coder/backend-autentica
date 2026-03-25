<?php
declare(strict_types=1);
namespace App\Support;

final class Validator
{
    public static function requireFields(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                Response::error('Campo obrigatório: ' . $field, 422);
            }
            $value = $data[$field];
            if (is_string($value) && trim($value) === '') {
                Response::error('Campo obrigatório: ' . $field, 422);
            }
            if ($value === null) {
                Response::error('Campo obrigatório: ' . $field, 422);
            }
        }
    }

    public static function email(string $value, string $field = 'email'): void
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            Response::error('Campo inválido: ' . $field, 422);
        }
    }

    public static function oneOf(string $value, array $allowed, string $field): void
    {
        if (!in_array($value, $allowed, true)) {
            Response::error('Valor inválido para ' . $field, 422, ['allowed' => $allowed]);
        }
    }
}
