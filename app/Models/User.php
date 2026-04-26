<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements FilamentUser
{
    use Notifiable, HasRoles;

    protected $fillable = ['name','email','password','phone','active'];
    protected $hidden   = ['password','remember_token'];
    protected $casts    = ['email_verified_at' => 'datetime', 'password' => 'hashed','active' => 'boolean',];

    // ✅ Filament access gate
    public function canAccessPanel(Panel $panel): bool
    {
        // Add 'depo' to this array 👇
        $ok = $this->hasAnyRole(['admin', 'seller', 'depo']); 
        Log::info('canAccessPanel', ['user' => $this->id, 'ok' => $ok, 'roles' => $this->getRoleNames()]);
        return $ok;
    }
}
