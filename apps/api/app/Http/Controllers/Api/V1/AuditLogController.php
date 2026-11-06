<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $logs = DB::table('audit_logs')
            ->leftJoin('users', 'users.id', '=', 'audit_logs.actor_id')
            ->select([
                'audit_logs.id', 'audit_logs.actor_id', 'users.name as actor_name',
                'audit_logs.action', 'audit_logs.resource_type', 'audit_logs.resource_id',
                'audit_logs.request_id', 'audit_logs.metadata', 'audit_logs.created_at',
            ])
            ->orderByDesc('audit_logs.id')
            ->paginate(50);

        return response()->json(['data' => $logs]);
    }
}
