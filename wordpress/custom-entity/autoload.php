<?php

use Vnet\Entity\Admin;
use Vnet\Entity\Main;

/**
 * - Массив сущностей для подключения
 * - значение соответствует названию папки после префикса "entity-" в данной директории
 * - будет подключен файл autoload.php из соответствующей директории
 */
$entities = ['news'];

define('ENTITY_PATH', __DIR__);

require __DIR__ . '/core/class-admin.php';
require __DIR__ . '/core/class-column.php';
require __DIR__ . '/core/class-entity.php';
require __DIR__ . '/core/class-entity-query.php';
require __DIR__ . '/core/class-labels.php';
require __DIR__ . '/core/class-settings.php';
require __DIR__ . '/core/class-main.php';

foreach ($entities as $name) {
    if (file_exists(__DIR__ . '/entity-' . $name . '/autoload.php')) {
        require __DIR__ . '/entity-' . $name . '/autoload.php';
    }
}

Admin::setup();

unset($entities);
unset($name);
