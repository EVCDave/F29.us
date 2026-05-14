<?php
declare(strict_types=1);

class UserService
{
    /**
     * Return the best available display label for a user row.
     * Preference: display_name → first + last → email.
     */
    public static function displayName(array $user): string
    {
        if (!empty($user['display_name'])) {
            return $user['display_name'];
        }

        $first = trim($user['first_name'] ?? '');
        $last  = trim($user['last_name']  ?? '');
        if ($first !== '' || $last !== '') {
            return trim("{$first} {$last}");
        }

        return $user['email'] ?? '';
    }
}
