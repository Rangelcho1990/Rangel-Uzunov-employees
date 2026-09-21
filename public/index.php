<?php

declare(strict_types=1);

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $environment = is_string($context['APP_ENV'] ?? null) ? $context['APP_ENV'] : 'prod';
    $debug = isset($context['APP_DEBUG']) ? (bool) $context['APP_DEBUG'] : false;

    return new Kernel($environment, $debug);
};
