<?php
// Without Composer: require_once '/path/to/asvri-php/init.php'; then use \Asvri\Client.
spl_autoload_register(function ($class) {
    if (strpos($class, 'Asvri\\') !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 6)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
