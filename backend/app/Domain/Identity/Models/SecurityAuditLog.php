<?php

namespace App\Domain\Identity\Models;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $actor_id
 * @property string $event
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 * @property-read User|null $actor
 */
#[Fillable([
    'user_id',
    'actor_id',
    'event',
    'ip_address',
    'user_agent',
    'metadata',
    'created_at',
])]
class SecurityAuditLog extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function eventLabel(): string
    {
        $resolved = SecurityAuditEvent::tryFrom($this->event);

        return $resolved?->label() ?? $this->event;
    }
}
