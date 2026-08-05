<?php

namespace Opencart\Catalog\Controller\Extension\Sameday\Shipping;

/**
 * OC4-only entrypoint. Packaged as catalog/controller/shipping/sameday.php by build.sh 4.
 * OC2/OC3 catalog controller is a no-op stub (sameday.php / *_controller.3.php).
 */
$samedaySystem = DIR_EXTENSION . 'sameday/system/';
if (!is_file($samedaySystem . 'library/samedayclasses.php')) {
    throw new \RuntimeException(
        'Sameday OC4 libraries not found under DIR_EXTENSION/sameday/system/library. '
        . 'Ensure the extension was installed into extension/sameday/ and that build.sh 4 packaged the OC4 wrappers.'
    );
}

require_once $samedaySystem . 'library/samedayclasses.php';
require_once $samedaySystem . 'library/sameday-classes/SamedayTraitCatalogController.php';

class Sameday extends \Opencart\System\Engine\Controller
{
    use \SamedayTraitCatalogController;
}
