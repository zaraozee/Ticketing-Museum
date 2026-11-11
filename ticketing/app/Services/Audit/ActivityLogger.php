<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $properties
     */
    public static function log(
        string $action,
        ?Model $subject = null,
        ?array $properties = null,
        ?int $actorId = null
    ): ActivityLog {
        return ActivityLog::create([
            'actor_id' => $actorId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject ? (int) $subject->getKey() : null,
            'properties' => $properties,
            'ip_address' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
