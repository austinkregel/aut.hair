<?php

namespace App\Models\Traits;

use Cog\Contracts\Ownership\CanBeOwner;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use App\Models\Contracts\Owner;
use Illuminate\Database\Eloquent\Builder;

trait HasOwner
{
    public function ownable(): MorphTo
    {
        return $this->morphTo('ownable');
    }

    /**
     * Get the model owner. Alias for `ownedBy()` method.
     */
    public function owner(): MorphTo
    {
        return $this->ownable();
    }

    /**
     * Get the model owner.
     */
    public function getOwner(): CanBeOwner
    {
        return $this->ownedBy;
    }

    public function defaultOwner()
    {
        return $this->defaultOwner;
    }

    public function withDefaultOwner(?Owner $owner = null)
    {
        $this->defaultOwner = $owner ?: $this->resolveDefaultOwner();
        if (isset($this->withDefaultOwnerOnCreate)) {
            $this->withDefaultOwnerOnCreate = false;
        }

        return $this;
    }

    public function withoutDefaultOwner()
    {
        $this->defaultOwner = null;
        if (isset($this->withDefaultOwnerOnCreate)) {
            $this->withDefaultOwnerOnCreate = false;
        }

        return $this;
    }

    public function isDefaultOwnerOnCreateRequired()
    {
        return isset($this->withDefaultOwnerOnCreate) ? (bool) $this->withDefaultOwnerOnCreate : false;
    }

    public function resolveDefaultOwner()
    {
        return \Auth::user();
    }

    public function changeOwnerTo(Owner $owner)
    {
        return $this->ownable()->associate($owner);
    }

    public function abandonOwner()
    {
        $model = $this->ownable()->dissociate();
        $this->load('ownedBy');

        return $model;
    }

    public function hasOwner()
    {
        return ! is_null($this->getOwner());
    }

    public function isOwnedBy(Owner $owner)
    {
        if (! $this->hasOwner()) {
            return false;
        }

        return $owner->getKey() === $this->getOwner()->getKey() && $owner->getMorphClass() === $this->getOwner()->getMorphClass();
    }

    public function isNotOwnedBy(Owner $owner)
    {
        return ! $this->isOwnedBy($owner);
    }

    public function isOwnedByDefaultOwner()
    {
        $owner = $this->resolveDefaultOwner();
        if (! $owner) {
            throw InvalidDefaultOwner::isNull($this);
        }

        return $this->isOwnedBy($owner);
    }

    public static function ownedBy(Owner $owner): Builder
    {
        return static::query()
            ->where('ownable_type', get_class($owner))
            ->where('ownable_id', $owner->{$owner->getKeyName()});
    }
}
