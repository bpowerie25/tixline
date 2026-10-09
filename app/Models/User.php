<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Concerns\BelongsToTenant;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role_id', 'team_id', 'tenant_id', 'is_external', 'display_name'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use BelongsToTenant, HasApiTokens, HasFactory, Notifiable;

    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_external' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->permissionCache === null) {
            if (! $this->relationLoaded('role') || ! $this->role?->relationLoaded('permissions')) {
                $this->load('role.permissions');
            }

            $this->permissionCache = $this->role?->permissions->pluck('name')->toArray() ?? [];
        }

        return in_array($permission, $this->permissionCache);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === Role::ADMIN;
    }

    private ?array $teamIdsCache = null;

    public function teamIds(): array
    {
        return $this->teamIdsCache ??= $this->teams()->pluck('teams.id')->toArray();
    }

    // Ticket visibility -- what tickets can this user see?
    public function canSeeTicket(Ticket $ticket): bool
    {
        // Assigned agent always sees their ticket
        if ($ticket->assigned_to == $this->id && $ticket->assigned_to !== null) {
            return true;
        }

        // Restricted team gate: only explicit members can see these tickets
        if ($ticket->team_id) {
            $isRestricted = $ticket->relationLoaded('team')
                ? $ticket->team?->is_restricted
                : Team::where('id', $ticket->team_id)->value('is_restricted');

            if ($isRestricted) {
                return in_array($ticket->team_id, $this->teamIds());
            }
        }

        // Linked via duplicate relationship to an assigned ticket
        if ($this->isAssignedToLinkedTicket($ticket)) {
            return true;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ($this->role?->name === Role::TEAM_LEAD) {
            return true;
        }

        // Custom (non-system) roles with tickets.view can see all tickets
        if (! $this->role?->is_system && $this->hasPermission('tickets.view')) {
            return true;
        }

        $teamIds = $this->teamIds();

        // Internal agents with no teams assigned can see all tickets
        if (empty($teamIds) && ! $this->is_external) {
            return true;
        }

        if ($ticket->team_id && in_array($ticket->team_id, $teamIds)) {
            return true;
        }

        // Group manager -- can see all tickets in their departments' teams
        if ($this->role?->name === Role::GROUP_MANAGER) {
            $departmentIds = Team::whereIn('id', $teamIds)->whereNotNull('department_id')->pluck('department_id');
            if ($departmentIds->isNotEmpty()) {
                $departmentTeamIds = Team::whereIn('department_id', $departmentIds)->pluck('id');
                if ($departmentTeamIds->contains($ticket->team_id)) {
                    return true;
                }
            }
        }

        if (! $ticket->team_id && ! $this->is_external) {
            return true;
        }

        return false;
    }

    // Get the ticket query scoped to this user's visibility
    public function visibleTicketsQuery()
    {
        if ($this->isAdmin()) {
            return Ticket::query();
        }

        if ($this->role?->name === Role::TEAM_LEAD) {
            return $this->excludeRestrictedTeams(Ticket::query());
        }

        // Custom (non-system) roles with tickets.view can see all tickets
        if (! $this->role?->is_system && $this->hasPermission('tickets.view')) {
            return $this->excludeRestrictedTeams(Ticket::query());
        }

        $teamIds = $this->teamIds();

        // Internal agents with no teams assigned can see all tickets
        if (empty($teamIds) && ! $this->is_external) {
            return $this->excludeRestrictedTeams(Ticket::query());
        }

        $linkedIds = $this->linkedTicketIds();

        $query = Ticket::where(function ($q) use ($teamIds, $linkedIds) {
            $q->where('assigned_to', $this->id);

            if (! empty($linkedIds)) {
                $q->orWhereIn('id', $linkedIds);
            }

            if (! $this->is_external) {
                $q->orWhereNull('team_id');
            }

            if (! empty($teamIds)) {
                $q->orWhereIn('team_id', $teamIds);
            }

            if ($this->role?->name === Role::GROUP_MANAGER) {
                $departmentIds = Team::whereIn('id', $teamIds)->whereNotNull('department_id')->pluck('department_id');
                if ($departmentIds->isNotEmpty()) {
                    $departmentTeamIds = Team::whereIn('department_id', $departmentIds)
                        ->where(function ($q) use ($teamIds) {
                            $q->where('is_restricted', false)
                              ->orWhereIn('id', $teamIds);
                        })
                        ->pluck('id');
                    $q->orWhereIn('team_id', $departmentTeamIds);
                }
            }
        });

        return $query;
    }

    /**
     * Check if this user is assigned to a ticket linked via duplicate_of.
     */
    private function isAssignedToLinkedTicket(Ticket $ticket): bool
    {
        // This ticket is a duplicate — check if user is assigned to the original
        if ($ticket->duplicate_of) {
            $original = Ticket::where('id', $ticket->duplicate_of)
                ->where('assigned_to', $this->id)
                ->exists();
            if ($original) {
                return true;
            }
        }

        // Check if user is assigned to any duplicate of this ticket
        return Ticket::where('duplicate_of', $ticket->id)
            ->where('assigned_to', $this->id)
            ->exists();
    }

    /**
     * Get ticket IDs linked via duplicate_of to this user's assigned tickets.
     */
    private function linkedTicketIds(): array
    {
        $assignedIds = Ticket::where('assigned_to', $this->id)->pluck('id');

        // Originals that my assigned tickets are duplicates of
        $originals = Ticket::where('assigned_to', $this->id)
            ->whereNotNull('duplicate_of')
            ->pluck('duplicate_of');

        // Duplicates of my assigned tickets
        $duplicates = Ticket::whereIn('duplicate_of', $assignedIds)->pluck('id');

        return $originals->merge($duplicates)->unique()->values()->toArray();
    }

    private function excludeRestrictedTeams($query)
    {
        $restrictedExcluded = Team::restrictedTeamIdsExcluding($this->teamIds());

        if (! empty($restrictedExcluded)) {
            $query->where(function ($q) use ($restrictedExcluded) {
                $q->whereNotIn('team_id', $restrictedExcluded)
                  ->orWhereNull('team_id')
                  ->orWhere('assigned_to', $this->id);
            });
        }

        return $query;
    }
}
