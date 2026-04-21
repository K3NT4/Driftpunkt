<?php

declare(strict_types=1);

namespace App\Module\System\Locale;

final class AppLocale
{
    public const DEFAULT = 'sv';

    public static function normalize(?string $locale): string
    {
        $locale = str_replace('_', '-', mb_strtolower(trim((string) $locale)));

        if ('' === $locale || preg_match('/^[a-z]{2,3}(?:-[a-z0-9]{2,8})*$/', $locale) !== 1) {
            return self::DEFAULT;
        }

        return $locale;
    }
}
