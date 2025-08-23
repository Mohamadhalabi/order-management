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

    protected $fillable = ['name','email','password'];
    protected $hidden   = ['password','remember_token'];
    protected $casts    = ['email_verified_at' => 'datetime', 'password' => 'hashed'];

    // ✅ Filament access gate
    public function canAccessPanel(Panel $panel): bool
    {
        $ok = $this->hasAnyRole(['admin','seller']);
        Log::info('canAccessPanel', ['user' => $this->id, 'ok' => $ok, 'roles' => $this->getRoleNames()]);
        return $ok;
    }
}
