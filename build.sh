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
        system/library/sameday-php-sdk/ \
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
        system/library/sameday-php-sdk/ \
        upload

    cp install.$VERSION.xml install.xml
    zip -r sameday.$VERSION.ocmod.zip upload install.xml
    rm install.xml
    rm -rf upload

    exit
fi

echo "Unknown version $VERSION specified"
exit 1
