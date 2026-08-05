<?php

require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';
require_once DIR_SYSTEM . 'library/samedayclasses.php';
require_once DIR_SYSTEM . 'library/sameday-classes/SamedayTraitCatalogController.php';

class ControllerExtensionShippingSameday extends Controller
{
    use \SamedayTraitCatalogController;
}
