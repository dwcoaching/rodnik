<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\Spring;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SpringPolicy
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
        return $user->is_admin;
    }

    /**
     * Determine whether the user can view the model.
     *
     * @param User $user
     * @param Spring $spring
     * @return Response|bool
     */
    public function view(User $user, Spring $spring)
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
        return true;
    }

    /**
     * Determine whether the user can update the model.
     *
     * @param User $user
     * @param Spring $spring
     * @return Response|bool
     */
    public function update(User $user, Spring $spring)
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     *
     * @param User $user
     * @param Spring $spring
     * @return Response|bool
     */
    public function delete(User $user, Spring $spring)
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     *
     * @param User $user
     * @param Spring $spring
     * @return Response|bool
     */
    public function restore(User $user, Spring $spring)
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     *
     * @param User $user
     * @param Spring $spring
     * @return Response|bool
     */
    public function forceDelete(User $user, Spring $spring)
    {
        //
    }
}
