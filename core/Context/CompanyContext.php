<?php

namespace NovaSysCore\Context;

class CompanyContext
{
    public function __construct(
        private int $userId,
        private int $companyId,
        private ?int $branchId = null
    ) {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException(
                'El ID del usuario no es válido.'
            );
        }

        if ($this->companyId <= 0) {
            throw new \InvalidArgumentException(
                'El ID de la empresa no es válido.'
            );
        }

        if (
            $this->branchId !== null
            && $this->branchId <= 0
        ) {
            throw new \InvalidArgumentException(
                'El ID de la sucursal no es válido.'
            );
        }
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function companyId(): int
    {
        return $this->companyId;
    }

    public function branchId(): ?int
    {
        return $this->branchId;
    }

    public function hasBranch(): bool
    {
        return $this->branchId !== null;
    }
}