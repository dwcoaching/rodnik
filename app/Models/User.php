<?php

namespace App\Models;

use Filament\Panel;
use App\Models\Report;
use App\Models\Spring;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Jetstream\HasProfilePhoto;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;
use Filament\Models\Contracts\FilamentUser;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens;
    use HasFactory;
    use HasProfilePhoto;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'profile_photo_url',
    ];

    public function springs()
    {
        return $this->belongsToMany(Spring::class, 'reports', 'user_id', 'spring_id')
            ->whereNull('hidden_at');
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function getRatingAttribute()
    {
        if (is_null($this->cached_rating)) {
            $rating = $this->calculateRating();
            $this->cached_rating = $rating;
            $this->save();
        }

        return $this->cached_rating;
    }

    public function calculateRating()
    {
        return $this->reports()->whereNull('from_osm')->whereNull('hidden_at')->count();
    }

    public function updateRating()
    {
        $this->cached_rating = $this->calculateRating();
        $this->save();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_admin;
    }
}
