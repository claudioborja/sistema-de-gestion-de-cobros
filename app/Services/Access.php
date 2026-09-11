<?php
declare(strict_types=1);
namespace App\Services;
use CodeIgniter\Shield\Models\UserModel;
final class Access
{
    public static function can(int $userId, string $permission): bool
    {
        $user=(new UserModel())->findById($userId);
        return $user !== null && $user->active && !$user->isBanned()
            && $user->inGroup('administrador','cajera') && $user->can($permission);
    }
    public static function require(int $userId, string $permission): void
    {
        if (!self::can($userId,$permission)) { throw new \RuntimeException('No tienes permiso para esta operación.',403); }
    }
}
