<?php

$current_dir = dirname(__FILE__);
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayPersistenceDataHandler.php';
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayHelper.php';

class Samedayclasses
{
    public static function getSamedayPersistenceDataHandler($registry, $prefix): SamedayPersistenceDataHandler
    {
        return new SamedayPersistenceDataHandler($registry, $prefix);
    }

    public static function getSamedayHelper($configs, $registry, $prefix): SamedayHelper
    {
        return new SamedayHelper($configs, $registry, $prefix);
    }
}