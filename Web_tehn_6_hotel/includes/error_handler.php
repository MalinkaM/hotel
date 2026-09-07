<?php
function baseErrorHandler($errno, $errstr, $errfile, $errline) {
    if ($errno === E_USER_WARNING) {
        $type = 'ПРЕДУПРЕЖДЕНИЕ';
        $class = 'warning';
    } elseif ($errno === E_USER_ERROR) {
        $type = 'ФАТАЛЬНАЯ ОШИБКА';
        $class = 'fatal';
    } else {
        $type = 'ОШИБКА';
        $class = 'warning';
    }
    echo "<div class='$class'>";
    echo "<strong>$type:</strong> " . htmlspecialchars($errstr) . "<br>";
    echo "<small>Файл: " . htmlspecialchars(basename($errfile)) . " | Строка: $errline</small>";
    echo "</div>";
    if ($errno === E_USER_ERROR) {
        exit;}
    return true;}
set_error_handler(
    "baseErrorHandler",
    E_USER_WARNING | E_USER_ERROR
);?>