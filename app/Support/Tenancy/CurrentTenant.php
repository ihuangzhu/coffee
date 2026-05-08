<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

class CurrentTenant
{
    private ?string $id = null;

    public function set(?string $id): void
    {
        $this->id = $id;
    }

    public function id(): ?string
    {
        return $this->id;
    }

    public function require(): string
    {
        if ($this->id === null) {
            throw new \RuntimeException('CurrentTenant 尚未设置，无法获取 tenant_id');
        }

        return $this->id;
    }
}
