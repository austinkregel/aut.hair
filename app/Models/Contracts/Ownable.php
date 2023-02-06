<?php

namespace App\Models\Contracts;

interface Ownable
{
    public function ownable();

    public function owner();

    public function getOwner();

    public function defaultOwner();

    public function withDefaultOwner(Owner $owner = null);

    public function withoutDefaultOwner();

    public function isDefaultOwnerOnCreateRequired();

    public function resolveDefaultOwner();

    public function changeOwnerTo(Owner $owner);

    public function abandonOwner();

    public function hasOwner();

    public function isOwnedBy(Owner $owner);

    public function isNotOwnedBy(Owner $owner);
}
