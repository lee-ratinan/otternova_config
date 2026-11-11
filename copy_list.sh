#!/bin/bash
set -e

cd "$(dirname "$0")"
REPOS=~/Documents/Otternova-Repositories

for repo in otternova_api otternova_client otternova_admin otternova_mart; do
    cp helper/list_helper.php    "$REPOS/$repo/root/app/Helpers/list_helper.php"
done