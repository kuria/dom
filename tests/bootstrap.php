<?php

spl_autoload_register(function ($class) {
    $prefix = 'Kuria\\Dom\\';
    $prefixLength = strlen($prefix);

    if (0 === strncmp($prefix, $class, $prefixLength)) {
        $file = __DIR__
            . '/../src/'
            . str_replace('\\', '/', substr($class, $prefixLength))
            . '.php'
        ;

        if (is_file($file)) {
            include $file;
        }
    }
});
