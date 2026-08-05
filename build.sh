#!/bin/sh
# Package Sameday for OpenCart 2 / 3 / 4.
# Same VERSION from opencart-23, opencart-30, or opencart-40 must yield an identical zip
# (byte-identical when sources match). Layout is auto-detected.

set -e

if [ -z "$1" ]; then
    echo "Usage: $0 <2|3|4>"
    exit 1
fi

VERSION=$1

# ---------------------------------------------------------------------------
# Source layout
# ---------------------------------------------------------------------------
if [ -d "extension/sameday/system/library/sameday-classes" ]; then
    EXT="extension/sameday"
    LIB="$EXT/system/library"
    ADMIN_CTRL="$EXT/admin/controller/shipping"
    ADMIN_MODEL="$EXT/admin/model/shipping"
    ADMIN_LANG="$EXT/admin/language/en-gb/shipping"
    ADMIN_TPL="$EXT/admin/view/template/shipping"
    ADMIN_JS="$EXT/admin/view/javascript/select2"
    CAT_CTRL="$EXT/catalog/controller/shipping"
    CAT_MODEL="$EXT/catalog/model/shipping"
    CAT_LANG="$EXT/catalog/language/en-gb/shipping"
    CAT_JS="$EXT/catalog/view/javascript/sameday/assets"
    INSTALL_JSON="$EXT/install.json"

    # OC2/OC3 entrypoints are the *.3.php wrappers in the OC4 tree.
    OC23_ADMIN_CTRL="$ADMIN_CTRL/sameday_admin_controller.3.php"
    OC23_ADMIN_MODEL="$ADMIN_MODEL/sameday_admin_model.3.php"
    OC23_CAT_CTRL="$CAT_CTRL/sameday_catalog_controller.3.php"
    OC23_CAT_MODEL="$CAT_MODEL/sameday_catalog_model.3.php"
else
    LIB="system/library"
    ADMIN_CTRL="admin/controller/extension/shipping"
    ADMIN_MODEL="admin/model/extension/shipping"
    ADMIN_LANG="admin/language/en-gb/extension/shipping"
    ADMIN_TPL="admin/view/template/extension/shipping"
    ADMIN_JS="admin/view/javascript/select2"
    CAT_CTRL="catalog/controller/extension/shipping"
    CAT_MODEL="catalog/model/extension/shipping"
    CAT_LANG="catalog/language/en-gb/extension/shipping"
    CAT_JS="catalog/view/javascript/sameday/assets"
    INSTALL_JSON="install.json"

    OC23_ADMIN_CTRL="$ADMIN_CTRL/sameday.php"
    OC23_ADMIN_MODEL="$ADMIN_MODEL/sameday.php"
    OC23_CAT_CTRL="$CAT_CTRL/sameday.php"
    OC23_CAT_MODEL="$CAT_MODEL/sameday.php"
fi

OC4_ADMIN_CTRL="$ADMIN_CTRL/sameday_admin_controller.4.php"
OC4_ADMIN_MODEL="$ADMIN_MODEL/sameday_admin_model.4.php"
OC4_CAT_CTRL="$CAT_CTRL/sameday_catalog_controller.4.php"
OC4_CAT_MODEL="$CAT_MODEL/sameday_catalog_model.4.php"

# Sidecars packaged inside the OC4 zip (not used as the live entrypoint).
OC23_ADMIN_CTRL_FILE="$ADMIN_CTRL/sameday_admin_controller.3.php"
OC23_ADMIN_MODEL_FILE="$ADMIN_MODEL/sameday_admin_model.3.php"
OC23_CAT_CTRL_FILE="$CAT_CTRL/sameday_catalog_controller.3.php"
OC23_CAT_MODEL_FILE="$CAT_MODEL/sameday_catalog_model.3.php"

require_file() {
    if [ ! -f "$1" ]; then
        echo "ERROR: missing required source file: $1" >&2
        exit 1
    fi
}

copy_sdk() {
    dest="$1"
    mkdir -p "$dest"
    require_file "$LIB/sameday-php-sdk/src/Sameday/autoload.php"
    cp -R "$LIB/sameday-php-sdk/src" "$dest/"
}

copy_classes() {
    dest="$1"
    mkdir -p "$dest"
    require_file "$LIB/samedayclasses.php"
    cp "$LIB/samedayclasses.php" "$dest/"
    mkdir -p "$dest/sameday-classes"
    cp -R "$LIB/sameday-classes/." "$dest/sameday-classes/"
}

# Deterministic zip: fixed modes/mtimes + sorted member list + no extra attrs.
normalize_tree() {
    for path in "$@"; do
        if [ -e "$path" ]; then
            find "$path" -type d -exec chmod 755 {} +
            find "$path" -type f -exec chmod 644 {} +
            find "$path" -exec touch -d "@0" {} +
        fi
    done
}

make_zip() {
    zipfile="$1"
    shift
    rm -f "$zipfile"
    normalize_tree "$@"
    find "$@" -type f 2>/dev/null | LC_ALL=C sort | zip -X -q "$zipfile" -@
}

# ---------------------------------------------------------------------------
# OC 2
# ---------------------------------------------------------------------------
if [ "$VERSION" -eq 2 ]; then
    require_file "$OC23_ADMIN_CTRL"
    require_file "$OC23_ADMIN_MODEL"
    require_file "$OC23_CAT_CTRL"
    require_file "$OC23_CAT_MODEL"
    require_file "$ADMIN_LANG/sameday.php"
    require_file install.2.xml
    for tpl in \
        sameday.tpl \
        sameday_add_awb.tpl \
        sameday_add_parcel.tpl \
        sameday_awb_history_status.tpl \
        sameday_awb_history_status_refresh.tpl \
        sameday_service.tpl
    do
        require_file "$ADMIN_TPL/$tpl"
    done

    rm -rf upload
    mkdir -p \
        upload/admin/controller/extension/shipping \
        upload/admin/model/extension/shipping \
        upload/admin/language/en-gb/extension/shipping \
        upload/admin/view/javascript/select2 \
        upload/admin/view/template/extension/shipping \
        upload/catalog/controller/extension/shipping \
        upload/catalog/model/extension/shipping \
        upload/catalog/view/javascript/sameday/assets \
        upload/system/library/sameday-php-sdk

    cp "$OC23_ADMIN_CTRL" upload/admin/controller/extension/shipping/sameday.php
    cp "$OC23_ADMIN_MODEL" upload/admin/model/extension/shipping/sameday.php
    cp "$ADMIN_LANG/sameday.php" upload/admin/language/en-gb/extension/shipping/
    cp -R "$ADMIN_JS/." upload/admin/view/javascript/select2/
    cp "$ADMIN_TPL/sameday.tpl" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_add_awb.tpl" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_add_parcel.tpl" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_awb_history_status.tpl" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_awb_history_status_refresh.tpl" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_service.tpl" upload/admin/view/template/extension/shipping/
    cp "$OC23_CAT_CTRL" upload/catalog/controller/extension/shipping/sameday.php
    cp "$OC23_CAT_MODEL" upload/catalog/model/extension/shipping/sameday.php
    cp -R "$CAT_JS/." upload/catalog/view/javascript/sameday/assets/
    copy_sdk upload/system/library/sameday-php-sdk
    copy_classes upload/system/library

    cp install.2.xml install.xml
    make_zip sameday.2.ocmod.zip upload install.xml
    rm -f install.xml
    rm -rf upload
    exit 0
fi

# ---------------------------------------------------------------------------
# OC 3
# ---------------------------------------------------------------------------
if [ "$VERSION" -eq 3 ]; then
    require_file "$OC23_ADMIN_CTRL"
    require_file "$OC23_ADMIN_MODEL"
    require_file "$OC23_CAT_CTRL"
    require_file "$OC23_CAT_MODEL"
    require_file "$ADMIN_LANG/sameday.php"
    require_file install.3.xml
    for tpl in \
        sameday.twig \
        sameday_add_awb.twig \
        sameday_add_parcel.twig \
        sameday_awb_history_status.twig \
        sameday_awb_history_status_refresh.twig \
        sameday_service.twig
    do
        require_file "$ADMIN_TPL/$tpl"
    done

    rm -rf upload
    mkdir -p \
        upload/admin/controller/extension/shipping \
        upload/admin/model/extension/shipping \
        upload/admin/language/en-gb/extension/shipping \
        upload/admin/view/javascript/select2 \
        upload/admin/view/template/extension/shipping \
        upload/catalog/controller/extension/shipping \
        upload/catalog/model/extension/shipping \
        upload/catalog/view/javascript/sameday/assets \
        upload/system/library/sameday-php-sdk

    cp "$OC23_ADMIN_CTRL" upload/admin/controller/extension/shipping/sameday.php
    cp "$OC23_ADMIN_MODEL" upload/admin/model/extension/shipping/sameday.php
    cp "$ADMIN_LANG/sameday.php" upload/admin/language/en-gb/extension/shipping/
    cp -R "$ADMIN_JS/." upload/admin/view/javascript/select2/
    cp "$ADMIN_TPL/sameday.twig" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_add_awb.twig" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_add_parcel.twig" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_awb_history_status.twig" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_awb_history_status_refresh.twig" upload/admin/view/template/extension/shipping/
    cp "$ADMIN_TPL/sameday_service.twig" upload/admin/view/template/extension/shipping/
    cp "$OC23_CAT_CTRL" upload/catalog/controller/extension/shipping/sameday.php
    cp "$OC23_CAT_MODEL" upload/catalog/model/extension/shipping/sameday.php
    cp -R "$CAT_JS/." upload/catalog/view/javascript/sameday/assets/
    copy_sdk upload/system/library/sameday-php-sdk
    copy_classes upload/system/library

    cp install.3.xml install.xml
    make_zip sameday.3.ocmod.zip upload install.xml
    rm -f install.xml
    rm -rf upload
    exit 0
fi

# ---------------------------------------------------------------------------
# OC 4
# ---------------------------------------------------------------------------
if [ "$VERSION" -eq 4 ]; then
    require_file "$OC4_ADMIN_CTRL"
    require_file "$OC4_ADMIN_MODEL"
    require_file "$OC4_CAT_CTRL"
    require_file "$OC4_CAT_MODEL"
    require_file "$OC23_ADMIN_CTRL_FILE"
    require_file "$OC23_ADMIN_MODEL_FILE"
    require_file "$OC23_CAT_CTRL_FILE"
    require_file "$OC23_CAT_MODEL_FILE"
    require_file "$ADMIN_LANG/sameday.php"
    require_file "$CAT_LANG/sameday.php"
    require_file "$INSTALL_JSON"

    rm -rf upload
    mkdir -p \
        upload/admin/controller/shipping \
        upload/admin/model/shipping \
        upload/admin/language/en-gb/shipping \
        upload/admin/view/javascript/select2 \
        upload/admin/view/template/shipping \
        upload/catalog/controller/shipping \
        upload/catalog/model/shipping \
        upload/catalog/language/en-gb/shipping \
        upload/catalog/view/javascript/sameday/assets \
        upload/system/library/sameday-php-sdk

    # Live OC4 entrypoints must be the *.4.php wrappers (DIR_EXTENSION).
    cp "$OC4_ADMIN_CTRL" upload/admin/controller/shipping/sameday.php
    cp "$OC23_ADMIN_CTRL_FILE" upload/admin/controller/shipping/
    cp "$OC4_ADMIN_CTRL" upload/admin/controller/shipping/
    cp "$OC4_ADMIN_MODEL" upload/admin/model/shipping/sameday.php
    cp "$OC23_ADMIN_MODEL_FILE" upload/admin/model/shipping/
    cp "$OC4_ADMIN_MODEL" upload/admin/model/shipping/
    cp "$ADMIN_LANG/sameday.php" upload/admin/language/en-gb/shipping/
    cp -R "$ADMIN_JS/." upload/admin/view/javascript/select2/
    cp "$ADMIN_TPL"/sameday*.twig upload/admin/view/template/shipping/
    cp "$ADMIN_TPL"/sameday*.tpl upload/admin/view/template/shipping/ 2>/dev/null || true

    cp "$OC4_CAT_CTRL" upload/catalog/controller/shipping/sameday.php
    cp "$OC23_CAT_CTRL_FILE" upload/catalog/controller/shipping/
    cp "$OC4_CAT_CTRL" upload/catalog/controller/shipping/
    cp "$OC4_CAT_MODEL" upload/catalog/model/shipping/sameday.php
    cp "$OC23_CAT_MODEL_FILE" upload/catalog/model/shipping/
    cp "$OC4_CAT_MODEL" upload/catalog/model/shipping/
    cp "$CAT_LANG/sameday.php" upload/catalog/language/en-gb/shipping/
    cp -R "$CAT_JS/." upload/catalog/view/javascript/sameday/assets/
    copy_sdk upload/system/library/sameday-php-sdk
    copy_classes upload/system/library
    cp "$INSTALL_JSON" upload/install.json

    for entry in \
        upload/admin/controller/shipping/sameday.php \
        upload/admin/model/shipping/sameday.php \
        upload/catalog/controller/shipping/sameday.php \
        upload/catalog/model/shipping/sameday.php
    do
        if grep -Eq 'require(_once)?[[:space:]]+DIR_SYSTEM' "$entry"; then
            echo "ERROR: $entry must not require DIR_SYSTEM (OC4 entrypoints are gated to DIR_EXTENSION)" >&2
            rm -rf upload
            exit 1
        fi
        if ! grep -Eq 'DIR_EXTENSION' "$entry"; then
            echo "ERROR: $entry is missing DIR_EXTENSION bootstrap" >&2
            rm -rf upload
            exit 1
        fi
        if ! grep -Eq 'namespace Opencart\\' "$entry"; then
            echo "ERROR: $entry is missing OC4 namespace (wrong wrapper packaged as sameday.php)" >&2
            rm -rf upload
            exit 1
        fi
    done

    # OC4 zip root is the upload contents (no upload/ prefix).
    rm -f sameday.ocmod.zip
    normalize_tree upload
    (cd upload && find . -type f | LC_ALL=C sort | zip -X -q ../sameday.ocmod.zip -@)
    rm -rf upload
    exit 0
fi

echo "Unknown version $VERSION specified" >&2
exit 1
