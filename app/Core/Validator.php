<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    public static function required(array $data, array $fields): void
    {
        foreach ($fields as $field) {
            if (!array_key_exists($field, $data)) {
                throw new HttpException(422, "Campo richiesto: {$field}.");
            }

            if (!is_array($data[$field]) && trim((string)$data[$field]) === '') {
                throw new HttpException(422, "Campo richiesto: {$field}.");
            }
        }
    }

    public static function email(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, 'Email non valida.');
        }
    }
}
