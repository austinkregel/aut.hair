<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ChromeosDevice;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Read-only admin view of openFyde/ChromeOS devices that have signed in via the
 * GAIA flow, with their captured token references. Gated by the OnlyHost
 * middleware (config('auth.admin_emails')) — same as the rest of /user/admin.
 */
class ChromeosDeviceController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/ChromeosDevices', [
            'devices' => QueryBuilder::for(ChromeosDevice::class)
                ->allowedSorts(['id', 'last_seen_at', 'approved'])
                ->with(['user:id,name,email', 'team:id,name', 'tokens'])
                ->defaultSort('-last_seen_at')
                ->paginate()
                ->appends(request()->query()),
        ]);
    }
}
