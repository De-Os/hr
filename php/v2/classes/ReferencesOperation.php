<?php

abstract class ReferencesOperation
{
    abstract public function doOperation(): array;

    public function getRequest(string $pName): mixed
    {
        return $_REQUEST[$pName];
    }
}