Démarrage quotidien :
    docker compose up -d
    docker compose up --wait

Permission bloquantes sur le dossier image qui est possédé par root : 
    sudo chown -R 1000:1000 public/assets/images
    sudo chmod -R 775 public/assets/images

Ou via le conteneur en root :
docker compose run --rm --user root php sh -lc 'chown -R 1000:1000 /app/public/assets/images && chmod -R 775 /app/public/assets/images'
