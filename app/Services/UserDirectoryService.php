<?php

declare(strict_types=1);

namespace App\Services;

use CodeIgniter\Shield\Models\UserModel;

final class UserDirectoryService
{
    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        $labels = array_map(static fn (array $group): string => $group['title'], config('AuthGroups')->groups);
        $users = model(UserModel::class)->orderBy('username')->findAll();

        return array_map(static function ($user) use ($labels): array {
            $groups = $user->getGroups() ?? [];
            return [
                'id' => (int) $user->id,
                'username' => (string) $user->username,
                'email' => (string) ($user->email ?? ''),
                'active' => (bool) $user->active && !$user->isBanned(),
                'groups' => array_map(static fn (string $group): string => $labels[$group] ?? ucfirst($group), $groups),
                'lastActive' => self::localDate($user->last_active),
            ];
        }, $users);
    }

    private static function localDate($value): string
    {
        if ($value === null || (string) $value === '') { return 'Nunca'; }
        return (new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('America/Guayaquil'))->format('d/m/Y H:i');
    }

    /** @return array<string, mixed> */
    public function mine(int $id): array
    {
        $user = model(UserModel::class)->findById($id);
        if (!$user) { throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(); }
        $labels = array_map(static fn (array $group): string => $group['title'], config('AuthGroups')->groups);

        return [
            'id' => (int) $user->id,
            'username' => (string) $user->username,
            'email' => (string) ($user->email ?? ''),
            'active' => (bool) $user->active && !$user->isBanned(),
            'groups' => array_map(static fn (string $group): string => $labels[$group] ?? ucfirst($group), $user->getGroups() ?? []),
            'permissions' => array_values($user->getPermissions() ?? []),
        ];
    }
}
