<?php
declare(strict_types=1);

namespace App;

final class Validators
{
    public const ALLOWED_STATUS = ['open','in_progress','closed'];

    /**
     * @param array{title:string,status:string,description:string} $data
     * @return array<string,string>
     */
    public static function validateTicket(array $data): array
    {
        $errors = [];
        $title = $data['title'] ?? '';
        $status = $data['status'] ?? '';
        $description = $data['description'] ?? '';
        if ($title === '' || trim($title) === '') $errors['title'] = 'Title is required.';
        if ($status === '' || !in_array($status, self::ALLOWED_STATUS, true)) {
            $errors['status'] = 'Status must be one of: ' . implode(', ', self::ALLOWED_STATUS) . '.';
        }
        if ($description !== '' && strlen($description) > 1000) $errors['description'] = 'Description too long (max 1000).';
        return $errors;
    }
}


