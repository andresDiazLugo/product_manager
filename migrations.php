<?php

return [
    'table_storage' => [
        'table_name' => 'doctrine_migration_versions',
    ],

    'migrations_paths' => [
        'DoctrineMigrations' => __DIR__ . '/migrations',
    ],

    'all_or_nothing' => true,
];