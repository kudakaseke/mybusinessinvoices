<?php

return [
    'path' => storage_path('app/backups'),
    'keep' => 14,
    'dump_binary' => env('MYSQL_DUMP_BINARY', PHP_OS_FAMILY === 'Windows' ? dirname(PHP_BINARY, 2).'/mysql/bin/mysqldump.exe' : 'mysqldump'),
    'restore_binary' => env('MYSQL_RESTORE_BINARY', PHP_OS_FAMILY === 'Windows' ? dirname(PHP_BINARY, 2).'/mysql/bin/mysql.exe' : 'mysql'),
];
