<?php

declare(strict_types=1);

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;

final class User extends Authenticatable implements FilamentUser, HasLocalePreference
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

    public function springs(): BelongsToMany
    {
        return $this->belongsToMany(Spring::class, 'reports', 'user_id', 'spring_id')
            ->whereNull('reports.hidden_at');
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? config('localization.default');
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function springRevisions()
    {
        return $this->hasMany(SpringRevision::class);
    }

    public function photos()
    {
        return $this->hasManyThrough(Photo::class, Report::class);
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
        return $this->reports()
            ->select('reports.*')
            ->join('springs', 'springs.id', '=', 'reports.spring_id')
            ->whereNull('reports.hidden_at')
            ->whereNull('reports.from_osm')
            ->count();
    }

    public function updateRating()
    {
        $this->cached_rating = $this->calculateRating();
        $this->save();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->is_superadmin) {
            return true;
        }

        if ($this->is_admin) {
            return true;
        }

        return false;
    }

    protected function defaultProfilePhotoUrl()
    {
        $name = mb_trim(collect(explode(' ', $this->name))->map(function ($segment) {
            return mb_substr($segment, 0, 1);
        })->join(' '));

        return 'https://ui-avatars.com/api/?name='.urlencode($name).'&color=ffffff&background=1d4ed8';
    }
}
