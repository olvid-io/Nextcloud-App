# Olvid

## Development

```shell
npm install
npm build
```

For development, use watch mode for automatic rebuilds.
```shell
npm run watch
```

### Android dev
Setup https with mkcert: https://juliusknorr.github.io/nextcloud-docker-dev/basics/ssl/

Listen on every interface: 
- .env add: IP_BIND=0.0.0.0
- docker compose up -d

Add hosts for emulator (need a system that can be mounted as writable)
```shell
#!/usr/bin/env bash
AVD="Pixel-6-no-google"
ADB="/opt/sdk-android-studio/platform-tools/adb"
EMU="/opt/sdk-android-studio/emulator/emulator"

echo "Starting emulator with writable system"
"${EMU}" -avd "${AVD}" -writable-system 1>/dev/null &
sleep 5

echo "Setting ADB to root mode"
"${ADB}" root

echo "Disabling verification"
"${ADB}" disable-verity

echo "Rebooting emulator"
"${ADB}" reboot

echo "Waiting for the emulator"
until "${ADB}" shell whoami; do
	sleep 2
done

echo "Setting ADB to root mode"
"${ADB}" root

echo "Remounting"
"${ADB}" remount

echo "Pushing our hosts file"
"${ADB}" push /etc/hosts etc/hosts

echo "Done"
```

Add mkcert root certificate on android emulator:
- find local root certificate
- push on emulaor: `avd push $HOME/.local/share/mkcert/rootCA.pem storage/emulated/0/Download`
- install in android settings: 

### Documentation for developers:

- General documentation and tutorials: https://nextcloud.com/developer
- Technical documentation: https://docs.nextcloud.com/server/latest/developer_manual

### Help for developers:

- Official community chat: https://cloud.nextcloud.com/call/xs25tz5y
- Official community forum: https://help.nextcloud.com/c/dev/11
