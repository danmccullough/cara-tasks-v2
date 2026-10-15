<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use NotificationChannels\WebPush\HasPushSubscriptions;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasPushSubscriptions, Notifiable;

    /** Channel keys a user can opt into. Phase 2 adds sms, mail, kiosk. */
    public const CHANNELS = ['database', 'webpush'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'avatar_emoji',
        'color',
        'pin',
        'notify_channels',
    ];

    protected $attributes = [
        'points_balance' => 0,
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'pin',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'pin' => 'hashed',
            'role' => Role::class,
            'notify_channels' => 'array',
            'points_balance' => 'integer',
        ];
    }

    public function isParent(): bool
    {
        return $this->role === Role::Parent;
    }

    public function isKid(): bool
    {
        return $this->role === Role::Kid;
    }

    /**
     * Channel keys this user receives notifications on.
     *
     * @return list<string>
     */
    public function notificationChannels(): array
    {
        return $this->notify_channels ?? self::CHANNELS;
    }

    public function scopeKids(Builder $query): void
    {
        $query->where('role', Role::Kid);
    }

    public function scopeParents(Builder $query): void
    {
        $query->where('role', Role::Parent);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class, 'kid_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(Occurrence::class, 'kid_id');
    }

    public function pointTransactions(): HasMany
    {
        return $this->hasMany(PointTransaction::class, 'kid_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'kid_id');
    }
}
