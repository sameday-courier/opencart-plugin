<?php
namespace Opencart\Admin\Controller\Extension\Sameday\Shipping;

require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayShippingMethod.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-php-sdk/src/Sameday/autoload.php';
require_once DIR_EXTENSION . 'sameday/system/library/samedayclasses.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayTraitAdminController.php';

class Sameday extends \Opencart\System\Engine\Controller
{
    use \SamedayTraitAdminController;
}
