<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class DateParser
{
    public function parse(string $value): \DateTimeImmutable
    {
        foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'd.m.Y', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $value, new \DateTimeZone('UTC'));
            if (false !== $date && false === \DateTimeImmutable::getLastErrors() && $date->format($format) === $value) {
                return $date;
            }
        }

        throw new \InvalidArgumentException(sprintf('Invalid date "%s". Use YYYY-MM-DD, YYYY/MM/DD, DD/MM/YYYY, DD.MM.YYYY or DD-MM-YYYY.', $value));
    }
}
