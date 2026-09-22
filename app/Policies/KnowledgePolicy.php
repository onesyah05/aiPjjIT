<?php

namespace App\Policies;

use App\Models\Knowledge;
use App\Models\User;

class KnowledgePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Knowledge $knowledge): bool
    {
        return $knowledge->user_id === $user->id
            || ($knowledge->status === 'approved' && in_array($knowledge->visibility, ['course', 'community'], true));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Knowledge $knowledge): bool
    {
        return $knowledge->user_id === $user->id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Knowledge $knowledge): bool
    {
        return $knowledge->user_id === $user->id || in_array($user->role, ['reviewer', 'admin'], true);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Knowledge $knowledge): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Knowledge $knowledge): bool
    {
        return false;
    }
}
