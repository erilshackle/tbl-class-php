<?php

// Definir apenas cores que serão usadas
define('COLOR_RESET', "\033[0m");
define('COLOR_RED', "\033[91m");      // Erros

// Autoload
$autoloadPaths = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../autoload.php',
    dirname(__DIR__, 3) . '/autoload.php',
];

foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

if (!class_exists('Eril\\TblClass\\Config')) {
    die(COLOR_RED . "✖ Autoload not found. Run 'composer install' first." . COLOR_RESET . "\n");
}
