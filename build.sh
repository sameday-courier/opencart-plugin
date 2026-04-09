#!/bin/sh

if [ -z "$1" ]; then
    echo "Please specify version to build"
    exit 1
fi


VERSION=$1
if [ $VERSION -eq 2 ]; then
    rm sameday.$VERSION.ocmod.zip
    rm -rf upload
    mkdir upload

    cp -r --parents \
        admin/controller/extension/shipping/sameday.php \
        admin/language/en-gb/extension/shipping/sameday.php \
        admin/model/extension/shipping/sameday.php \
        admin/view/template/extension/shipping/sameday.tpl \
        admin/view/template/extension/shipping/sameday_add_awb.tpl \
        admin/view/template/extension/shipping/sameday_awb_history_status.tpl \
        admin/view/template/extension/shipping/sameday_awb_history_status_refresh.tpl \
        admin/view/template/extension/shipping/sameday_service.tpl \
        catalog/model/extension/shipping/sameday.php \
        catalog/view/javascript/sameday/assets/sameday-locker.js \
        catalog/view/javascript/sameday/assets/update-city.js \
        catalog/view/javascript/sameday/assets/update-payment.js \
        system/library/sameday-php-sdk/ \
        system/library/sameday-classes/ \
        system/library/samedayclasses.php \
        admin/view/javascript/select2/ \
        upload

    cp install.$VERSION.xml install.xml
    zip -r sameday.$VERSION.ocmod.zip upload install.xml
    rm install.xml
    rm -rf upload

    exit
elif [ $VERSION -eq 3 ]; then
    rm sameday.$VERSION.ocmod.zip
    rm -rf upload
    mkdir upload

    cp -r --parents \
        admin/controller/extension/shipping/sameday.php \
        admin/language/en-gb/extension/shipping/sameday.php \
        admin/model/extension/shipping/sameday.php \
        admin/view/template/extension/shipping/sameday.twig \
        admin/view/template/extension/shipping/sameday_add_awb.twig \
        admin/view/template/extension/shipping/sameday_awb_history_status.twig \
        admin/view/template/extension/shipping/sameday_awb_history_status_refresh.twig \
        admin/view/template/extension/shipping/sameday_service.twig \
        catalog/model/extension/shipping/sameday.php \
        catalog/view/javascript/sameday/assets/sameday-locker.js \
        catalog/view/javascript/sameday/assets/update-city.js \
        catalog/view/javascript/sameday/assets/update-payment.js \
        system/library/sameday-php-sdk/ \
        system/library/sameday-classes/ \
        system/library/samedayclasses.php \
        admin/view/javascript/select2/ \
        upload

    cp install.$VERSION.xml install.xml
    zip -r sameday.$VERSION.ocmod.zip upload install.xml
    rm install.xml
    rm -rf upload

    exit
elif [ $VERSION -eq 4 ]; then
    rm sameday.ocmod.zip

    mkdir -p upload
    mkdir -p upload/admin/controller/shipping
    mkdir -p upload/admin/model/shipping
    mkdir -p upload/admin/language/en-gb/shipping
    mkdir -p upload/admin/view/javascript/select2
    mkdir -p upload/admin/view/template/shipping

    mkdir -p upload/catalog/controller/shipping
    mkdir -p upload/catalog/model/shipping
    mkdir -p upload/catalog/language/en-gb/shipping
    mkdir -p upload/catalog/view/javascript/sameday/assets
    mkdir -p upload/system/library/sameday-classes
    mkdir -p upload/system/library/sameday-php-sdk

    cp admin/controller/extension/shipping/sameday_admin_controller.4.php upload/admin/controller/shipping/sameday.php
    cp admin/controller/extension/shipping/sameday_admin_controller.3.php upload/admin/controller/shipping/
    cp admin/controller/extension/shipping/sameday_admin_controller.4.php upload/admin/controller/shipping/
    cp admin/model/extension/shipping/sameday_admin_model.4.php upload/admin/model/shipping/sameday.php
    cp admin/model/extension/shipping/sameday_admin_model.3.php upload/admin/model/shipping/
    cp admin/model/extension/shipping/sameday_admin_model.4.php upload/admin/model/shipping/
    cp admin/language/en-gb/extension/shipping/sameday.php upload/admin/language/en-gb/shipping/
    cp -r admin/view/javascript/select2/. upload/admin/view/javascript/select2/
    cp -r admin/view/template/extension/shipping/. upload/admin/view/template/shipping/

    cp catalog/controller/extension/shipping/sameday_catalog_controller.4.php upload/catalog/controller/shipping/sameday.php
    cp catalog/controller/extension/shipping/sameday_catalog_controller.3.php upload/catalog/controller/shipping/
    cp catalog/controller/extension/shipping/sameday_catalog_controller.4.php upload/catalog/controller/shipping/
    cp catalog/model/extension/shipping/sameday_catalog_model.4.php upload/catalog/model/shipping/sameday.php
    cp catalog/language/en-gb/extension/sameday.php upload/catalog/language/en-gb/shipping/
    cp -r catalog/view/javascript/sameday/assets/. upload/catalog/view/javascript/sameday/assets/
    cp -r system/library/sameday-classes/. upload/system/library/sameday-classes/
    cp -r system/library/sameday-php-sdk/. upload/system/library/sameday-php-sdk/
    cp system/library/samedayclasses.php upload/system/library/
    cp install.json upload/

    (cd upload && zip -r ../sameday.ocmod.zip .)
    rm -rf upload
fi

echo "Unknown version $VERSION specified"
exit 1
