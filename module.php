<?php

declare(strict_types=1);

namespace Vytux\WebtreesModules\VytuxCousins;

// webtrees loads this file directly, so register a PSR-4 autoloader for src/.
spl_autoload_register(static function (string $class): void {
    $prefix = __NAMESPACE__ . '\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

return new VytuxCousinsTabModule();
