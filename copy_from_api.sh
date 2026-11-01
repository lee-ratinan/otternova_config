#!/bin/bash
set -e

cd "$(dirname "$0")"
REPOS=~/Documents/Otternova-Repositories
SRC="$REPOS/otternova_api/root/app"

# copy all contents of a source folder into a destination folder (skipping .DS_Store)
copy_dir() {
    mkdir -p "$2"
    rsync -a --exclude='.DS_Store' "$1/" "$2/"
}

# otternova_api -> this repository: models, languages, and list_helper only
copy_dir "$SRC/Models"   Models
copy_dir "$SRC/Language" Languages
cp "$SRC/Helpers/list_helper.php" helper/list_helper.php
