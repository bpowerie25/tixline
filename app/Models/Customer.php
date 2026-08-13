<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Customer extends Authenticatable
{
    use BelongsToTenant;

    protected $fillable = ['name', 'email', 'password', 'organization', 'tenant_id'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function tickets()
    {
        return Ticket::where('requester_email', $this->email);
    }
}
