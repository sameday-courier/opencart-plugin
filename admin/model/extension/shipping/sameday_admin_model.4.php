<?php

namespace Opencart\Admin\Model\Extension\Sameday\Shipping;

require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayShippingMethod.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-php-sdk/src/Sameday/autoload.php';
require_once DIR_EXTENSION . 'sameday/system/library/samedayclasses.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayTraitAdminModel.php';

class Sameday extends \Opencart\System\Engine\Model
{
    use \SamedayTraitAdminModel;
}