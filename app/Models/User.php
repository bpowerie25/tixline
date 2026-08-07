<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'team_id', 'tenant_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    const ROLE_ADMIN = 'admin';

    const ROLE_GROUP_MANAGER = 'group_manager';

    const ROLE_TEAM_LEAD = 'team_lead';

    const ROLE_AGENT = 'agent';

    const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_GROUP_MANAGER,
        self::ROLE_TEAM_LEAD,
        self::ROLE_AGENT,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    // Role checks
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isGroupManager(): bool
    {
        return $this->role === self::ROLE_GROUP_MANAGER;
    }

    public function isTeamLead(): bool
    {
        return $this->role === self::ROLE_TEAM_LEAD;
    }

    public function isAgent(): bool
    {
        return $this->role === self::ROLE_AGENT;
    }

    // Hierarchy checks
    public function isAtLeast(string $role): bool
    {
        $hierarchy = [
            self::ROLE_ADMIN => 4,
            self::ROLE_GROUP_MANAGER => 3,
            self::ROLE_TEAM_LEAD => 2,
            self::ROLE_AGENT => 1,
        ];

        return ($hierarchy[$this->role] ?? 0) >= ($hierarchy[$role] ?? 0);
    }

    public function department(): ?Department
    {
        return $this->team?->department;
    }

    // Ticket visibility — what tickets can this user see?
    public function canSeeTicket(Ticket $ticket): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        // Assigned to them
        if ($ticket->assigned_to === $this->id) {
            return true;
        }

        // Same team
        if ($this->team_id && $ticket->team_id === $this->team_id) {
            return true;
        }

        // Group manager — can see all tickets in their department's teams
        if ($this->isGroupManager() && $this->team?->department_id) {
            $departmentTeamIds = Team::where('department_id', $this->team->department_id)->pluck('id');

            return $departmentTeamIds->contains($ticket->team_id);
        }

        // Unassigned tickets (no team) — visible to everyone
        if (! $ticket->team_id) {
            return true;
        }

        return false;
    }

    // Get the ticket query scoped to this user's visibility
    public function visibleTicketsQuery()
    {
        if ($this->isAdmin() || ! config('support.multi_tenant')) {
            return Ticket::query();
        }

        $query = Ticket::where(function ($q) {
            // Own tickets
            $q->where('assigned_to', $this->id);

            // Unassigned
            $q->orWhereNull('team_id');

            // Same team
            if ($this->team_id) {
                $q->orWhere('team_id', $this->team_id);
            }

            // Group manager — all department teams
            if ($this->isGroupManager() && $this->team?->department_id) {
                $departmentTeamIds = Team::where('department_id', $this->team->department_id)->pluck('id');
                $q->orWhereIn('team_id', $departmentTeamIds);
            }
        });

        return $query;
    }
}
