<?php

$current_dir = dirname(__FILE__);
require_once $current_dir . DIRECTORY_SEPARATOR . 'sameday-classes/SamedayPersistenceDataHandler.php';

class Samedayclasses
{
    public static function get_object($registry, $prefix)
    {
        return new SamedayPersistenceDataHandler($registry, $prefix);
    }
}