<?php

declare(strict_types=1);

namespace App\Actions;

use App\Library\Export\ExportLock;
use App\Models\Photo;
use App\Models\Report;
use App\Models\SpringRevision;
use App\Models\TrackPolygon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;

final class DeleteAccountAction
{
    public function __invoke(User $user): void
    {
        $this->authorize($user);
        $this->validate($user);
        $this->execute($user);
    }

    public function authorize(User $user): void
    {
        Gate::allowIf(fn (User $actor): bool => $actor->is($user));
    }

    public function validate(User $user): void
    {
        Validator::make(['user_id' => $user->getKey()], [
            'user_id' => ['required', 'integer', Rule::exists(User::class, 'id')],
        ])->validate();
    }

    public function execute(User $user): void
    {
        ExportLock::run(function () use ($user): void {
            DB::transaction(function () use ($user): void {
                $account = User::query()->lockForUpdate()->findOrFail($user->getKey());

                Photo::withoutTimestamps(fn (): int => Photo::query()
                    ->whereIn('report_id', $account->reports()->select('id'))
                    ->update(['original_filename' => DB::raw("CONCAT(id, '.', extension)")]));

                SpringRevision::withoutTimestamps(fn (): int => SpringRevision::query()
                    ->where('user_id', $account->id)->update(['user_id' => null]));

                Report::withoutTimestamps(function () use ($account): void {
                    $account->reports()->update(['user_id' => null]);

                    foreach (['hidden_by_author_id', 'hidden_by_moderator_id'] as $column) {
                        Report::query()->where($column, $account->id)->update([$column => null]);
                    }
                });

                TrackPolygon::query()->where('user_id', $account->id)->delete();
                $account->tokens()->delete();
                Password::broker(config('fortify.passwords'))->deleteToken($account);

                DB::connection(config('session.connection'))
                    ->table(config('session.table'))
                    ->where('user_id', $account->id)
                    ->delete();

                $account->delete();

                $this->deleteStoredFiles($account);
            });
        });
    }

    private function deleteStoredFiles(User $user): void
    {
        $public = Storage::disk('public');
        $exports = $public->allFiles('exports');

        if ($exports !== [] && ! $public->delete($exports)) {
            throw new RuntimeException('Unable to remove stored contribution exports.');
        }

        if ($user->profile_photo_path !== null
            && ! Storage::disk(config('jetstream.profile_photo_disk'))->delete($user->profile_photo_path)) {
            throw new RuntimeException('Unable to remove the account profile photo.');
        }
    }
}
