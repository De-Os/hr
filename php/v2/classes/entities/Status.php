<?php

class Status
{
    private const NAMES = [
        0 => 'Completed',
        1 => 'Pending',
        2 => 'Rejected'
    ];

    public static function getName(int $id): string
    {
        // если есть кейсы, что id может быть неверным, то кидать эксепшн
        return self::NAMES[$id];
    }
}