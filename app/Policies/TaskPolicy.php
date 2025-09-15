<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    public function update(User $user, Task $task)
    {
        return $user->isAdmin() || $user->id === $task->created_by_user_id;
    }

    public function assign(User $user, Task $task)
    {
        return $user->isAdmin() || $user->id === $task->created_by_user_id;
    }

    public function unassign(User $user, Task $task)
    {
        return $user->isAdmin() || 
               $user->id === $task->created_by_user_id || 
               $user->id === $task->assigned_to_user_id;
    }

    public function approve(User $user, Task $task)
    {
        return $user->isAdmin();
    }
}