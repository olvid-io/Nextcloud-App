rsync -avzh --exclude node_modules ~/Desktop/olvid/nextcloud/workspace/server/apps-extra/olvid next.jmartel.fr:nextcloud/data/apps
ssh next.jmartel.fr docker compose -f ./nextcloud/docker-compose.yaml restart
