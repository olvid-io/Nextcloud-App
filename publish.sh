#!/bin/bash

set -e
cd "$(dirname "$0")"

source ~/.nextcloud/token
if [ -z "$NEXTCLOUD_TOKEN" ]  ; then echo Specify your nextcloud API token as NEXTCLOUD_TOKEN ; exit 1 ; fi

# Version must match appinfo/info.xml
VERSION=$(grep -oP '(?<=<version>)[^<]+' appinfo/info.xml)
echo "Building version: $VERSION"

# Built from: https://docs.nextcloud.com/server/stable/developer_manual/app_publishing_maintenance/release_process.html

# Install dependencies (no dev packages in release)
composer install --no-dev
npm ci

# Build frontend assets
npm run build

# Prepare staging directory
rm -rf ./build
mkdir -p ./build/olvid

# Copy only production files
cp AUTHORS.md CHANGELOG.md LICENSE README.md ./build/olvid/
cp -r appinfo css img js l10n lib templates composer ./build/olvid/

# Package
(cd ./build && tar -czvf "olvid-${VERSION}.tar.gz" olvid)
echo "Archive: ./build/olvid-${VERSION}.tar.gz"

# Sign the archive (requires ~/.nextcloud/certificates/olvid.key)
SIGNATURE=$(openssl dgst -sha512 -sign ~/.nextcloud/certificates/olvid.key \
    "./build/olvid-${VERSION}.tar.gz" | openssl base64 | tr -d '\n')
echo ""
echo "Signature:"
echo "$SIGNATURE"
echo ""

# delete existing release if necessary
gh release delete -y "v${VERSION}" && echo deleted previoud release || true

# create release
gh release create -n "" -p -t "v${VERSION}" "v${VERSION}" "./build/olvid-${VERSION}.tar.gz"
DOWNLOAD_URL="https://github.com/olvid-io/Nextcloud-App/releases/download/v${VERSION}/olvid-${VERSION}.tar.gz"
echo "DOWNLOAD_URL=${DOWNLOAD_URL}"

curl -X POST \
   -H "Authorization: Token $NEXTCLOUD_TOKEN" \
   -H "Content-Type: application/json" \
    https://apps.nextcloud.com/api/v1/apps/releases \
    -d "{\"download\":\"${DOWNLOAD_URL}\",\"signature\":\"${SIGNATURE}\",\"nightly\":false}"
