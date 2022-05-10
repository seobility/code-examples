<?php

namespace Vnet\Entity;


class Category_Query extends Entity_Query
{

    /**
     * - Устанавливается в классе Settings
     */
    static $settingsKey;


    function __construct()
    {
        parent::__construct(self::$settingsKey);
    }
}
