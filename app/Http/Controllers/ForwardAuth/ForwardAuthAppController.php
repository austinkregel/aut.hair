<?php

namespace App\Http\Controllers\ForwardAuth;

use App\Http\Controllers\Controller;
use App\Models\ProxyApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Registration + approval lifecycle for forward-auth protected apps.
 *
 *  - store():   Option A "push" registration. Called by a trusted machine (e.g.
 *               homelab-in-a-box at deploy time) with a client_credentials token
 *               carrying the `forward-auth` scope. Upserts by host.
 *  - index():   list apps (optionally filtered by status) for the approval queue.
 *  - approve()/reject(): the human decision, gated by OnlyHost (admin emails).
 *
 * First-contact discovery (Option B) lives in ForwardAuthController::verify, which
 * auto-creates pending apps; approve() is how those get turned on.
 */
class ForwardAuthAppController extends Controller
{
    /**
     * Deploy-time upsert (machine token). The scope check is the trust signal, so a
     * caller may land the app straight in `approved`; default is `pending` so a
     * misconfigured pipeline can never silently expose an app.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'owner_team_id' => ['required', 'integer', 'exists:teams,id'],
            'allow_team_ids' => ['sometimes', 'array'],
            'allow_team_ids.*' => ['integer', 'exists:teams,id'],
            'status' => ['sometimes', Rule::in([ProxyApp::STATUS_PENDING, ProxyApp::STATUS_APPROVED])],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $status = $data['status'] ?? ProxyApp::STATUS_PENDING;

        $app = ProxyApp::updateOrCreate(
            ['host' => $data['host']],
            [
                'name' => $data['name'],
                'team_id' => $data['owner_team_id'],
                'status' => $status,
                'enabled' => $data['enabled'] ?? ($status === ProxyApp::STATUS_APPROVED),
            ]
        );

        if (array_key_exists('allow_team_ids', $data)) {
            $app->teams()->sync($data['allow_team_ids']);
        }

        return response()->json($app->load('teams'), $app->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * List apps for the approval queue. `?status=pending` filters.
     */
    public function index(Request $request): JsonResponse
    {
        $apps = ProxyApp::query()
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->with(['ownerTeam', 'teams', 'requestedBy:id,name,email'])
            ->orderByDesc('created_at')
            ->get();

        return response()->json($apps);
    }

    /**
     * Approve a (usually pending) app: assign owner + allow-list and switch it on.
     */
    public function approve(Request $request, ProxyApp $proxyApp): JsonResponse
    {
        $data = $request->validate([
            'owner_team_id' => ['required', 'integer', 'exists:teams,id'],
            'allow_team_ids' => ['sometimes', 'array'],
            'allow_team_ids.*' => ['integer', 'exists:teams,id'],
        ]);

        $proxyApp->update([
            'team_id' => $data['owner_team_id'],
            'status' => ProxyApp::STATUS_APPROVED,
            'enabled' => true,
        ]);

        $proxyApp->teams()->sync($data['allow_team_ids'] ?? []);

        return response()->json($proxyApp->load('teams'));
    }

    /**
     * Reject an app: it stays on record (so it is not re-discovered every request)
     * but fails closed forever until an admin approves it.
     */
    public function reject(ProxyApp $proxyApp): JsonResponse
    {
        $proxyApp->update([
            'status' => ProxyApp::STATUS_REJECTED,
            'enabled' => false,
        ]);

        return response()->json($proxyApp);
    }
}
