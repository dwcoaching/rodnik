<?php

declare(strict_types=1);

namespace App\Actions\Jetstream;

use App\Actions\DeleteAccountAction;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\DeletesUsers;
use League\Flysystem\FilesystemException;
use RuntimeException;

final class DeleteUser implements DeletesUsers
{
    public function __construct(private DeleteAccountAction $deleteAccount) {}

    public function delete(User $user): void
    {
        try {
            ($this->deleteAccount)($user);
        } catch (LockTimeoutException|RuntimeException|FilesystemException $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'accountDeletion' => [__('privacy.deletion.retry')],
            ])->errorBag('deleteUser');
        }
    }
}
