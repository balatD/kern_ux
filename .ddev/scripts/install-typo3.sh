#!/bin/bash
# Installs one TYPO3 major into /var/www/html/<version> with EXT:kern_ux linked
# from the repo root via a Composer path repository.
set -euo pipefail

V="$1"           # v13 | v14
CONSTRAINT="$2"  # ^13.4 | ^14.3
DIR="/var/www/html/$V"

echo "==> Resetting $DIR"
rm -rf "$DIR"/* "$DIR"/.[!.]* 2>/dev/null || true
mkdir -p "$DIR"
echo '{}' > "$DIR/composer.json"

composer config extra.typo3/cms.web-dir public -d "$DIR"
composer config repositories.kern_ux path ../../kern_ux -d "$DIR"
composer config --no-plugins allow-plugins.typo3/cms-composer-installers true -d "$DIR"
composer config --no-plugins allow-plugins.typo3/class-alias-loader true -d "$DIR"
composer config prefer-stable true -d "$DIR"
composer config minimum-stability dev -d "$DIR"

echo "==> Requiring TYPO3 $CONSTRAINT + EXT:kern_ux"
composer require -d "$DIR" --no-progress -n \
    "typo3/minimal:$CONSTRAINT" \
    "typo3/cms-install:$CONSTRAINT" \
    "typo3/cms-form:$CONSTRAINT" \
    "typo3/cms-rte-ckeditor:$CONSTRAINT" \
    "typo3/cms-tstemplate:$CONSTRAINT" \
    "typo3/cms-lowlevel:$CONSTRAINT" \
    "typo3/cms-extensionmanager:$CONSTRAINT" \
    "balatd/kern-ux:*@dev"

composer require -d "$DIR" --dev --no-progress -n "typo3/cms-styleguide" || \
    echo "!! styleguide unavailable for $V - continuing"

cd "$DIR"

echo "==> Recreating database $V"
mysql -h db -u root -proot -e "DROP DATABASE IF EXISTS \`$V\`; CREATE DATABASE \`$V\`;"

echo "==> Running TYPO3 setup"
vendor/bin/typo3 setup -n \
    --dbname="$V" \
    --password="${TYPO3_DB_PASSWORD}" \
    --create-site="https://$V.kern-ux.ddev.site/" \
    --admin-user-password="${TYPO3_SETUP_ADMIN_PASSWORD}"

echo "==> Dev-friendly configuration"
# Copied rather than generated: `configuration:set` does not exist in v13,
# and additional.php behaves identically in both majors.
mkdir -p config/system
cp /mnt/ddev_config/scripts/additional.php config/system/additional.php

echo "==> Clearing the welcome page that \`typo3 setup\` leaves behind"
# `typo3 setup` installs a starter page whose TypoScript overrides anything a site
# set provides - and it does so differently per major:
#   v13 writes a sys_template record with clear=3, wiping set constants AND setup;
#   v14 writes config/sites/<id>/setup.typoscript, which loads after the sets.
# Either one silently defeats a sitepackage's page rendering, so both go.
rm -f config/sites/main/setup.typoscript
mysql -h db -u root -proot "$V" -e "DELETE FROM sys_template WHERE pid > 0;" 2>/dev/null || true

echo "==> Creating the default file storage"
# `typo3 setup` does not create one, so every FAL path - including
# ext:form's 1:/form_definitions/ - would be unresolvable without this.
mysql -h db -u root -proot "$V" -e "INSERT INTO sys_file_storage (uid,pid,name,description,driver,configuration,is_default,is_browsable,is_public,is_writable,is_online,deleted) VALUES (1,0,'fileadmin','Default storage','Local','<?xml version=\"1.0\" encoding=\"utf-8\" standalone=\"yes\" ?><T3FlexForms><data><sheet index=\"sDEF\"><language index=\"lDEF\"><field index=\"basePath\"><value index=\"vDEF\">fileadmin/</value></field><field index=\"pathType\"><value index=\"vDEF\">relative</value></field><field index=\"caseSensitive\"><value index=\"vDEF\">1</value></field></language></sheet></data></T3FlexForms>',1,1,1,1,1,0) ON DUPLICATE KEY UPDATE is_online=1;" 2>/dev/null || true
mkdir -p public/fileadmin/form_definitions

echo "==> Activating the kern-ux/kern-ux site set"
if ! grep -q 'kern-ux/kern-ux' config/sites/main/config.yaml; then
    sed -i '/^dependencies: {  }$/d' config/sites/main/config.yaml
    printf 'dependencies:\n  - kern-ux/kern-ux\n' >> config/sites/main/config.yaml
fi

echo "==> Fetching the KERN distribution"
vendor/bin/typo3 kern-ux:assets:install

vendor/bin/typo3 extension:setup
vendor/bin/typo3 cache:flush

echo ""
echo "OK  TYPO3 $V ready"
echo "    Frontend  https://$V.kern-ux.ddev.site/"
echo "    Backend   https://$V.kern-ux.ddev.site/typo3/  (admin / Joh316!!)"
