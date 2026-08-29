<?php

namespace App\Http\Controllers\ForwardAuth;

use App\Http\Controllers\Controller;
use App\Models\ProxyApp;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
     *
     * TRUST BOUNDARY: `owner_team_id` is not bound to the calling client — any
     * client holding the `forward-auth` scope may register any host for any team.
     * That is intentional: the scope is issued only to fully-trusted deploy machines
     * (e.g. homelab-in-a-box). Do not hand this scope to a client you would not
     * trust to grant arbitrary teams access to arbitrary hosts.
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

        $app = ProxyApp::firstOrNew(['host' => $data['host']]);
        $app->name = $data['name'];
        $app->team_id = $data['owner_team_id'];

        if (! $app->exists) {
            // New app: default to pending unless the caller explicitly approves it.
            $app->status = $data['status'] ?? ProxyApp::STATUS_PENDING;
            $app->enabled = $data['enabled'] ?? ($app->status === ProxyApp::STATUS_APPROVED);
        } else {
            // Existing app: a redeploy that omits status/enabled must NOT demote a
            // live app back to pending. Only change them when explicitly provided.
            if (array_key_exists('status', $data)) {
                $app->status = $data['status'];
            }
            if (array_key_exists('enabled', $data)) {
                $app->enabled = $data['enabled'];
            }
        }

        $app->save();

        if (array_key_exists('allow_team_ids', $data)) {
            $app->teams()->sync($data['allow_team_ids']);
        }

        return response()->json($app->load('teams'), $app->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * The admin Forward Auth screen: the approval queue + configured apps.
     */
    public function page(): Response
    {
        return Inertia::render('ForwardAuth/Index', [
            'apps' => ProxyApp::query()
                ->with(['ownerTeam:id,name', 'teams:id,name', 'requestedBy:id,name,email'])
                ->orderBy('host')
                ->get(),
            'teams' => Team::orderBy('name')->get(['id', 'name']),
        ]);
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
