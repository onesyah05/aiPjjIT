<?php

namespace App\Policies;

use App\Models\AiCredential;
use App\Models\User;

class AiCredentialPolicy
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
    public function view(User $user, AiCredential $aiCredential): bool
    {
        return $aiCredential->user_id === $user->id || $user->role === 'admin';
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
    public function update(User $user, AiCredential $aiCredential): bool
    {
        return $aiCredential->user_id === $user->id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AiCredential $aiCredential): bool
    {
        return $aiCredential->user_id === $user->id || $user->role === 'admin';
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AiCredential $aiCredential): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AiCredential $aiCredential): bool
    {
        return false;
    }
}
