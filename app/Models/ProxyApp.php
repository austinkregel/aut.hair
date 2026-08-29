<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Laravel\Passport\Client;

/**
 * A containerized app protected by forward auth (Authentik-outpost compatible).
 *
 * Identified by hostname (the X-Forwarded-Host Traefik sends), not by an OAuth
 * client_id. Access is granted to the owning team plus any team attached via the
 * proxy_app_team pivot — the same owns-or-invited shape as OAuth clients, keyed on
 * host instead of client. See App\Http\Controllers\ForwardAuth\ForwardAuthController.
 */
class ProxyApp extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'host',
        'name',
        'team_id',
        'oauth_client_id',
        'enabled',
        'status',
        'discovered_at',
        'requested_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'discovered_at' => 'datetime',
    ];

    /**
     * Whether the verify endpoint may ever return 200 for this app: it has been
     * approved AND is switched on. Pending, rejected, and disabled apps all fail
     * closed with a 403.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_APPROVED && $this->enabled;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * The user whose request first surfaced this host via first-contact discovery.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * The owning team.
     */
    public function ownerTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * Additional teams granted access to this app.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'proxy_app_team')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * The Passport client this app is also registered as, if any.
     */
    public function oauthClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'oauth_client_id');
    }

    /**
     * Whether the user may access this app: one of their teams (owned or member)
     * is either the owner team or an explicitly granted team.
     */
    public function allowsUser(User $user): bool
    {
        $allowedTeamIds = $this->grantedTeamIds();

        return $user->allTeams()
            ->contains(fn (Team $team) => $allowedTeamIds->contains($team->id));
    }

    /**
     * The set of team ids allowed into this app: the owner plus all granted teams.
     */
    protected function grantedTeamIds(): Collection
    {
        return collect([$this->team_id])
            ->merge($this->teams()->pluck('teams.id'))
            ->filter()
            ->unique()
            ->values();
    }
}
