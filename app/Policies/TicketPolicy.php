<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tickets.view');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->canSeeTicket($ticket);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tickets.create');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if (! $user->hasPermission('tickets.update')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        // Team lead can update any ticket in their team
        if ($user->role?->name === Role::TEAM_LEAD && $ticket->team_id && in_array($ticket->team_id, $user->teamIds())) {
            return true;
        }

        // Group manager can update any ticket in their department
        if ($user->role?->name === Role::GROUP_MANAGER && $user->canSeeTicket($ticket)) {
            return true;
        }

        // Agent can only update tickets assigned to them
        return $ticket->assigned_to === $user->id;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission('tickets.delete');
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        if (! $user->hasPermission('tickets.assign')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $teamIds = $user->teamIds();

        // Team lead can assign within their team
        if ($user->role?->name === Role::TEAM_LEAD && $ticket->team_id && in_array($ticket->team_id, $teamIds)) {
            return true;
        }

        // Group manager can assign within department
        if ($user->role?->name === Role::GROUP_MANAGER && $user->canSeeTicket($ticket)) {
            return true;
        }

        return false;
    }
}
