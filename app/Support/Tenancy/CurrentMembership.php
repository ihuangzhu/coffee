<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Modules\Identity\Models\UserOrgRel;

class CurrentMembership
{
    private ?UserOrgRel $membership = null;

    public function set(?UserOrgRel $membership): void
    {
        $this->membership = $membership;
    }

    public function get(): ?UserOrgRel
    {
        return $this->membership;
    }
}
