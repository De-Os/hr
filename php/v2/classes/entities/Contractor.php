<?php

class Contractor
{
    const TYPE_CUSTOMER = 0;
    public int $id;
    public string $type;
    public string $name;

    public static function getById(int $resellerId): self
    {
        return new self($resellerId); // fakes the getById method
    }

    public function getFullName(): string
    {
        return $this->name . ' ' . $this->id;
    }
}