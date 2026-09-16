<?php

namespace App\Exceptions;

use DomainException;

class ProductNotReadyForPublication extends DomainException
{
    /**
     * @param  array<string, string>  $blockers
     */
    public function __construct(private readonly array $blockers)
    {
        parent::__construct('Produk belum memenuhi syarat untuk dipublikasikan.');
    }

    /**
     * @return array<string, string>
     */
    public function blockers(): array
    {
        return $this->blockers;
    }
}
