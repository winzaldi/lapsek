<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogger
{
    public function record(Request $request, string $action, Model|string $resource, ?int $resourceId = null, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $request->user()?->id,
            'action' => $action,
            'resource_type' => is_string($resource) ? $resource : $resource::class,
            'resource_id' => is_string($resource) ? $resourceId : $resource->getKey(),
            'request_id' => $request->attributes->get('request_id'),
            'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }
}
