<?php

spl_autoload_register(function ($class) {
    $prefix = 'Kuria\\Dom\\';
    $prefixLength = strlen($prefix);
    $paths = array('../src', '.');

    if (0 === strncmp($prefix, $class, $prefixLength)) {
        foreach ($paths as $path) {
            $file = __DIR__
                . '/' . $path . '/'
                . str_replace('\\', '/', substr($class, $prefixLength))
                . '.php'
            ;

            if (is_file($file)) {
                include $file;

                break;
            }
        }
    }
});
