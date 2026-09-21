<?php

namespace App\Doctrine\Contract;

interface SoftDeletableInterface
{
    public function getDeletedAt(): ?\DateTimeImmutable;

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): static;

    public function isDeleted(): bool;
}
