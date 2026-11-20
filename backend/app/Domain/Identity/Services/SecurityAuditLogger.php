<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Enums\SecurityAuditEvent;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;

final class SecurityAuditLogger
{
    /**
     * @var list<string>
     */
    private const array REDACTED_KEY_FRAGMENTS = [
        'password',
        'token',
        'secret',
        'code',
        'credential',
        'recovery',
        'cookie',
        'authorization',
    ];

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        SecurityAuditEvent|string $event,
        ?User $user = null,
        ?User $actor = null,
        array $metadata = [],
        ?Request $request = null,
    ): SecurityAuditLog {
        $resolvedRequest = $request ?? (app()->bound('request') ? request() : null);
        $rawUserAgent = $resolvedRequest instanceof Request ? (string) $resolvedRequest->userAgent() : '';
        $userAgent = $rawUserAgent !== '' ? mb_substr($rawUserAgent, 0, 512) : null;

        $actorId = $actor !== null ? $actor->id : ($user !== null ? $user->id : null);

        return SecurityAuditLog::query()->create([
            'user_id' => $user?->id,
            'actor_id' => $actorId,
            'event' => $event instanceof SecurityAuditEvent ? $event->value : $event,
            'ip_address' => $resolvedRequest instanceof Request ? $resolvedRequest->ip() : null,
            'user_agent' => $userAgent,
            'metadata' => $metadata !== [] ? self::sanitizeMetadata($metadata) : null,
            'created_at' => Date::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private static function sanitizeMetadata(array $metadata): array
    {
        $sanitized = [];

        foreach ($metadata as $key => $value) {
            $lowerKey = mb_strtolower((string) $key);
            $isSensitive = false;

            foreach (self::REDACTED_KEY_FRAGMENTS as $fragment) {
                if (str_contains($lowerKey, $fragment)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $sanitized[$key] = '[redacted]';

                continue;
            }

            $sanitized[$key] = is_array($value) ? self::sanitizeMetadata($value) : $value;
        }

        return $sanitized;
    }
}
