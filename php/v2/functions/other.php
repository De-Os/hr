<?php

function getResellerEmailFrom(): string
{
    return 'contractor@example.com';
}

function getEmailsByPermit(int $resellerId, mixed $event): array
{
    // fakes the method
    return ['someemeil@example.com', 'someemeil2@example.com'];
}