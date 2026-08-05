<?php

namespace Opencart\Catalog\Model\Extension\Sameday\Shipping;

/**
 * OC4-only entrypoint. Packaged as catalog/model/shipping/sameday.php by build.sh 4.
 * Do not use for OC2/OC3 (those use DIR_SYSTEM wrappers in sameday.php / *_model.3.php).
 */
$samedaySystem = DIR_EXTENSION . 'sameday/system/';
if (!is_file($samedaySystem . 'library/samedayclasses.php')) {
    throw new \RuntimeException(
        'Sameday OC4 libraries not found under DIR_EXTENSION/sameday/system/library. '
        . 'Ensure the extension was installed into extension/sameday/ and that build.sh 4 packaged the OC4 wrappers.'
    );
}

require_once $samedaySystem . 'library/sameday-php-sdk/src/Sameday/autoload.php';
require_once $samedaySystem . 'library/samedayclasses.php';
require_once $samedaySystem . 'library/sameday-classes/SamedayHelper.php';
require_once $samedaySystem . 'library/sameday-classes/SamedayPersistenceDataHandler.php';
require_once $samedaySystem . 'library/sameday-classes/SamedayTraitCatalogModel.php';

class Sameday extends \Opencart\System\Engine\Model
{
    use \SamedayTraitCatalogModel;
}
