<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view (scoped by visibleTicketsQuery)
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $user->canSeeTicket($ticket);
    }

    public function create(User $user): bool
    {
        return true; // All authenticated users can create tickets
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Team lead can update any ticket in their team
        if ($user->isTeamLead() && $ticket->team_id === $user->team_id) {
            return true;
        }

        // Group manager can update any ticket in their department
        if ($user->isGroupManager() && $user->canSeeTicket($ticket)) {
            return true;
        }

        // Agent can only update tickets assigned to them
        return $ticket->assigned_to === $user->id;
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->isAtLeast(User::ROLE_TEAM_LEAD);
    }

    public function assign(User $user, Ticket $ticket): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        // Team lead can assign within their team
        if ($user->isTeamLead() && $ticket->team_id === $user->team_id) {
            return true;
        }

        // Group manager can assign within department
        if ($user->isGroupManager() && $user->canSeeTicket($ticket)) {
            return true;
        }

        return false;
    }
}
