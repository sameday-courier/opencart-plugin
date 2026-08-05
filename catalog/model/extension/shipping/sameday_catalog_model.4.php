<?php

namespace Opencart\Catalog\Model\Extension\Sameday\Shipping;

require_once DIR_EXTENSION . 'sameday/system/library/sameday-php-sdk/src/Sameday/autoload.php';
require_once DIR_EXTENSION . 'sameday/system/library/samedayclasses.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayHelper.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayPersistenceDataHandler.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayTraitCatalogModel.php';

class Sameday extends \Opencart\System\Engine\Model
{
    use \SamedayTraitCatalogModel;
}
