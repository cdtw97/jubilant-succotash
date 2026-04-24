<?php
declare(strict_types=1);

namespace MyFrancis\Config;

enum Environment: string
{
    case Local = 'local';
    case Testing = 'testing';
    case Staging = 'staging';
    case Production = 'production';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::Production;
    }
}
