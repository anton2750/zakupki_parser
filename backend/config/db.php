<?php

return [
    'class' => 'yii\db\Connection',
    'dsn' => 'pgsql:host=database;dbname=nestjs_db;port=5432',
    'username' => 'user',
    'password' => 'password',
    'charset' => 'utf8',

    // Schema cache options (for production environment)
    //'enableSchemaCache' => true,
    //'schemaCacheDuration' => 60,
    //'schemaCache' => 'cache',
];
