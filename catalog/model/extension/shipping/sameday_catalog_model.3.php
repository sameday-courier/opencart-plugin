<?php

require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';
require_once DIR_SYSTEM . 'library/samedayclasses.php';
require_once DIR_SYSTEM . 'library/sameday-classes/SamedayHelper.php';
require_once DIR_SYSTEM . 'library/sameday-classes/SamedayPersistenceDataHandler.php';
require_once DIR_SYSTEM . 'library/sameday-classes/SamedayTraitCatalogModel.php';

class ModelExtensionShippingSameday extends \Model{
    use \SamedayTraitCatalogModel;
}
class_alias(__NAMESPACE__ . '\\ModelExtensionShippingSameday', 'ModelExtensionShippingSameday');