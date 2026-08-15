<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Photo;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class DeletePhotoAction
{
    public function __invoke(User $user, Photo $photo): void
    {
        $this->authorize($user, $photo);
        $this->validate($user, $photo);
        $this->execute($photo);
    }

    public function authorize(User $user, Photo $photo): void
    {
        Gate::forUser($user)->authorize('delete', $photo);
    }

    public function validate(User $user, Photo $photo): void
    {
        abort_if($photo->report_id === null || $photo->report()->first()?->user_id !== $user->id, 403);
    }

    public function execute(Photo $photo): void
    {
        Storage::disk('photos')->delete($photo->filename);
        $photo->delete();
    }
}
