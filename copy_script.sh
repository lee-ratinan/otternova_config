#!/bin/bash
set -e

cd "$(dirname "$0")"
REPOS=~/Documents/Otternova-Repositories

# copy all contents of a source folder into a destination folder (skipping .DS_Store)
copy_dir() {
    mkdir -p "$2"
    rsync -a --exclude='.DS_Store' "$1/" "$2/"
}

# otternova_client, otternova_admin, otternova_mart: all helpers, models, and languages
for repo in otternova_client otternova_admin otternova_mart; do
    copy_dir helper    "$REPOS/$repo/root/app/Helpers"
    copy_dir Models    "$REPOS/$repo/root/app/Models"
    copy_dir Languages "$REPOS/$repo/root/app/Language"
done

# otternova_api: models, languages, and list_helper only
copy_dir Models    "$REPOS/otternova_api/root/app/Models"
copy_dir Languages "$REPOS/otternova_api/root/app/Language"
cp helper/list_helper.php "$REPOS/otternova_api/root/app/Helpers/list_helper.php"

# otternova_www: list_helper only
cp helper/list_helper.php "$REPOS/otternova_www/root/app/Helpers/list_helper.php"
