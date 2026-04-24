<?php
declare(strict_types=1);

namespace MyFrancis\Security;

use JsonException;
use Stringable;

final class Escaper
{
    public function html(mixed $value): string
    {
        return htmlspecialchars($this->stringValue($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
    }

    public function attr(mixed $value): string
    {
        return $this->html($value);
    }

    public function url(string $value): string
    {
        $sanitized = filter_var($value, FILTER_SANITIZE_URL);

        return $this->attr($sanitized === false ? '' : $sanitized);
    }

    public function js(mixed $value): string
    {
        try {
            $encoded = json_encode(
                $this->stringValue($value),
                JSON_THROW_ON_ERROR
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT,
            );
        } catch (JsonException) {
            return '';
        }

        return substr($encoded, 1, -1);
    }

    private function stringValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }
}
