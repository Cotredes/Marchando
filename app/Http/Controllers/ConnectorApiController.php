<?php

namespace App\Http\Controllers;

use App\ConnectorService;
use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorApiController extends Controller
{
    private function connector(Request $request)
    {
        $token = (string) ($request->bearerToken() ?: $request->input('api_token'));

        return app(ConnectorService::class)->authenticate($token);
    }

    public function link(Request $request): JsonResponse
    {
        $data = $request->validate(['pairing_code' => ['required', 'string', 'max:16'], 'agent_version' => ['nullable', 'string', 'max:40']]);
        try {
            $connector = app(ConnectorService::class)->link($data['pairing_code'], $data['agent_version'] ?? null);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['connector_id' => $connector->id, 'restaurant_id' => $connector->restaurant_id, 'status' => $connector->status]);
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $connector = app(ConnectorService::class)->heartbeat($this->connector($request), (string) $request->input('agent_version'));

        return response()->json(['status' => $connector->status, 'online' => $connector->isOnline(), 'server_time' => now()->toISOString()]);
    }

    public function jobs(Request $request): JsonResponse
    {
        $connector = $this->connector($request);
        app(ConnectorService::class)->heartbeat($connector);
        $jobs = app(ConnectorService::class)->pendingJobs($connector);

        return response()->json(['jobs' => $jobs->map(fn ($job) => ['id' => $job->id, 'kind' => $job->kind, 'reference' => $job->reference, 'payload' => $job->payload, 'is_reprint' => $job->is_reprint, 'printer_id' => $job->printer_id])->values()->all()]);
    }

    public function ack(Request $request, int $job): JsonResponse
    {
        $connector = $this->connector($request);
        $record = PrintJob::query()->findOrFail($job);
        $data = $request->validate(['printed' => ['required', 'boolean'], 'error' => ['nullable', 'string', 'max:500']]);
        try {
            $result = app(ConnectorService::class)->acknowledge($connector, $record, (bool) $data['printed'], $data['error'] ?? null);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }

        return response()->json(['status' => $result->status]);
    }
}
