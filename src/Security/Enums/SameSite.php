<?php
declare(strict_types=1);

namespace MyFrancis\Security\Enums;

enum SameSite: string
{
    case Lax = 'Lax';
    case Strict = 'Strict';
    case None = 'None';
}
