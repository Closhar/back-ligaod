<?php

// Проверка GD расширения
echo "GD extension loaded: " . (extension_loaded('gd') ? 'YES' : 'NO') . "\n";

if (extension_loaded('gd')) {
    echo "GD version: " . gd_info()['GD Version'] . "\n";
    echo "Supported formats:\n";
    $info = gd_info();
    foreach ($info as $key => $value) {
        if (strpos($key, 'Support') !== false && $value) {
            echo "  - $key: " . ($value ? 'YES' : 'NO') . "\n";
        }
    }
} else {
    echo "GD extension is not available. Logo overlay will not work.\n";
}
