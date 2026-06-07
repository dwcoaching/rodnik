<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class PhotoPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function viewAny(User $user)
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Photo $photo
     * @return Response|bool
     */
    public function view(User $user, Photo $photo)
    {
        //
    }

    /**
     * Determine whether the user can create models.
     *
     * @param User $user
     * @return Response|bool
     */
    public function create(User $user)
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Photo $photo
     * @return Response|bool
     */
    public function update(User $user, Photo $photo)
    {
        return $photo->report->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Photo $photo
     * @return Response|bool
     */
    public function delete(User $user, Photo $photo): bool
    {
        if ($user->is_superadmin) {
            return true;
        }

        // Regular users never delete unattached photos; a background job prunes them.
        if ($photo->report_id === null) {
            return false;
        }

        return $photo->report?->user_id === $user->id;
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param Photo $photo
     * @return Response|bool
     */
    public function restore(User $user, Photo $photo)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param Photo $photo
     * @return Response|bool
     */
    public function forceDelete(User $user, Photo $photo)
    {
        //
    }
}
