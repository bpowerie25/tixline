<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
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

    public function teamIds(): array
    {
        return $this->teams()->pluck('teams.id')->toArray();
    }

    // Ticket visibility — what tickets can this user see?
    public function canSeeTicket(Ticket $ticket): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($ticket->assigned_to === $this->id) {
            return true;
        }

        $teamIds = $this->teamIds();
        if ($ticket->team_id && in_array($ticket->team_id, $teamIds)) {
            return true;
        }

        // Group manager — can see all tickets in their departments' teams
        if ($this->isGroupManager()) {
            $departmentIds = Team::whereIn('id', $teamIds)->whereNotNull('department_id')->pluck('department_id');
            if ($departmentIds->isNotEmpty()) {
                $departmentTeamIds = Team::whereIn('department_id', $departmentIds)->pluck('id');
                if ($departmentTeamIds->contains($ticket->team_id)) {
                    return true;
                }
            }
        }

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

        $teamIds = $this->teamIds();

        $query = Ticket::where(function ($q) use ($teamIds) {
            $q->where('assigned_to', $this->id);
            $q->orWhereNull('team_id');

            if (! empty($teamIds)) {
                $q->orWhereIn('team_id', $teamIds);
            }

            if ($this->isGroupManager()) {
                $departmentIds = Team::whereIn('id', $teamIds)->whereNotNull('department_id')->pluck('department_id');
                if ($departmentIds->isNotEmpty()) {
                    $departmentTeamIds = Team::whereIn('department_id', $departmentIds)->pluck('id');
                    $q->orWhereIn('team_id', $departmentTeamIds);
                }
            }
        });

        return $query;
    }
}
