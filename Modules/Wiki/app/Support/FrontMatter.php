<?php

namespace Modules\Wiki\Support;

/**
 * Minimal front matter parser: flat `key: value` scalars only. The wiki's
 * docs only ever need a string title and an integer order, so pulling in a
 * real YAML parser (not currently a dependency of this project) for that
 * would be overkill.
 */
final class FrontMatter
{
    /**
     * @return array{attributes: array<string, string|int>, body: string}
     */
    public static function parse(string $raw): array
    {
        if (preg_match('/^---\r?\n(.*?)\r?\n---\r?\n?/s', $raw, $matches) !== 1) {
            return ['attributes' => [], 'body' => $raw];
        }

        $attributes = [];

        foreach (preg_split('/\r?\n/', trim($matches[1])) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");

            $attributes[$key] = ctype_digit($value) ? (int) $value : $value;
        }

        return [
            'attributes' => $attributes,
            'body' => substr($raw, strlen($matches[0])),
        ];
    }
}
