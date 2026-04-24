<?php
declare(strict_types=1);

namespace MyFrancis\Core\Enums;

enum ContentType: string
{
    case HTML = 'text/html; charset=UTF-8';
    case JSON = 'application/json; charset=UTF-8';
    case TEXT = 'text/plain; charset=UTF-8';
}
