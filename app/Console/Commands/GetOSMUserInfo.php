<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OSMTokenService;
use Illuminate\Console\Command;

final class GetOSMUserInfo extends Command
{
    protected $signature = 'osm:user-info {user_id : ID пользователя}';

    protected $description = 'Получить информацию о пользователе OSM по user_id';

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

        $token = $this->osmTokenService->getToken($user);

        if (!$token) {
            $this->error("Не удалось получить токен OSM для пользователя {$user->name}");

            return 1;
        }

        $this->info("Получение информации о пользователе OSM для {$user->name}...");

        $osmUser = $this->osmTokenService->getUserInfo($user);

        if (!$osmUser) {
            $this->error('Не удалось получить информацию о пользователе OSM');

            return 1;
        }

        $this->info('✅ Информация о пользователе OSM получена успешно!');
        $this->newLine();

        $this->table(
            ['Поле', 'Значение'],
            [
                ['ID', $osmUser['id'] ?? 'N/A'],
                ['Имя пользователя', $osmUser['display_name'] ?? 'N/A'],
                ['Дата регистрации', $osmUser['account_created'] ?? 'N/A'],
                ['Описание', $osmUser['description'] ?? 'N/A'],
                ['Изменения', $osmUser['changesets']['count'] ?? 'N/A'],
                ['GPS-треки', $osmUser['traces']['count'] ?? 'N/A'],
            ]
        );

        if (isset($osmUser['img'])) {
            $this->info('🖼️  Аватар: '.$osmUser['img']['href']);
        }

        return 0;
    }
}
