<?php

$current_dir = __DIR__;
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayPersistenceDataHandler.php';
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayHelper.php';

class Samedayclasses
{
    public static function getSamedayPersistenceDataHandler($registry): SamedayPersistenceDataHandler
    {
        return new SamedayPersistenceDataHandler($registry);
    }

    public static function getSamedayHelper($configs, $registry, $prefix): SamedayHelper
    {
        return new SamedayHelper($configs, $registry, $prefix);
    }
}