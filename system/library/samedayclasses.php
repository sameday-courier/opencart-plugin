<?php

$current_dir = __DIR__;
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-php-sdk/src/Sameday/autoload.php';
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayPersistenceDataHandler.php';
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayHelper.php';
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayVersionValidator.php';

class Samedayclasses
{
    public static function getSamedayPersistenceDataHandler($registry): SamedayPersistenceDataHandler
    {
        return new SamedayPersistenceDataHandler($registry, self::getSamedayVersionValidator());
    }

    public static function getSamedayHelper($configs, $registry, $prefix): SamedayHelper
    {
        return new SamedayHelper($configs, $registry, $prefix);
    }

    public static function getSamedayVersionValidator(): SamedayVersionValidator{
        return new SamedayVersionValidator();
    }
}