<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Admin\Services\AdminAnalyticsService;
use App\Domain\Identity\Models\SecurityAuditLog;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(AdminAnalyticsService $analytics): Response
    {
        $recentAuditLogs = SecurityAuditLog::query()
            ->with(['user:id,name,email', 'actor:id,name,email'])
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(static function (SecurityAuditLog $log): array {
                $targetUser = $log->user;
                $actorUser = $log->actor;

                return [
                    'id' => $log->id,
                    'event' => $log->event,
                    'label' => $log->eventLabel(),
                    'user_name' => $targetUser !== null ? $targetUser->name : 'System',
                    'user_email' => $targetUser !== null ? $targetUser->email : '',
                    'actor_name' => $actorUser !== null ? $actorUser->name : 'System',
                    'ip_address' => $log->ip_address ?? '—',
                    'metadata' => $log->metadata,
                    'created_at_diff' => $log->created_at->diffForHumans(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/Operations', [
            'analytics' => $analytics->summary(),
            'recentAuditLogs' => $recentAuditLogs,
        ]);
    }
}
