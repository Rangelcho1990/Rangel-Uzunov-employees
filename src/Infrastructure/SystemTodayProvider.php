<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class SystemTodayProvider
{
    public function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
    }
}
