#!/bin/sh
set -e

echo "🔍 Vérification des dépendances Composer..."

# Installer les dépendances si vendor n'existe pas ou si composer.lock a changé
if [ ! -d "vendor" ] || [ "composer.lock" -nt "vendor" ]; then
    echo "📦 Installation des dépendances Composer..."
    composer install \
        --prefer-dist \
        --no-progress \
        --no-interaction \
        --ignore-platform-reqs
    echo "✅ Dépendances installées"
else
    echo "✅ Dépendances déjà à jour"
fi

# Exécuter la commande passée au conteneur
exec "$@"