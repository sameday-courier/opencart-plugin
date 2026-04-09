<?php

namespace Opencart\Catalog\Controller\Extension\Sameday\Shipping;

require_once DIR_EXTENSION . 'sameday/system/library/samedayclasses.php';
require_once DIR_EXTENSION . 'sameday/system/library/sameday-classes/SamedayTraitCatalogController.php';

class Sameday extends \Opencart\System\Engine\Controller {
    use \SamedayTraitCatalogController;
}