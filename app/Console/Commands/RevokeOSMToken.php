<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OSMTokenService;
use Illuminate\Console\Command;

final class RevokeOSMToken extends Command
{
    protected $signature = 'osm:revoke-token {user_id : ID пользователя}';

    protected $description = 'Отозвать токен OSM для пользователя по user_id';

    public function __construct(private OSMTokenService $osmTokenService)
    {
        parent::__construct();
    }

    public function handle()
    {
        $userId = $this->argument('user_id');

        $user = User::find($userId);

        if (!$user) {
            $this->error("Пользователь с ID {$userId} не найден");

            return 1;
        }

        if (!$this->osmTokenService->hasToken($user)) {
            $this->error("У пользователя {$user->name} (ID: {$userId}) нет токена OSM");

            return 1;
        }

        $this->info("Отзыв токена OSM для пользователя {$user->name}...");

        $success = $this->osmTokenService->revokeToken($user);

        if (!$success) {
            $this->error('Не удалось отозвать токен OSM');

            return 1;
        }

        $this->info('✅ Токен OSM успешно отозван!');

        return 0;
    }
}
