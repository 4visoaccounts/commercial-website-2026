# full deploy
git pull origin main

# 3. Build production assets
echo "Building production assets..."
npm run production

# 8. Apply Craft CMS config & migrations
echo "Running Craft CMS setup..."
./craft migrate/all
./craft project-config/apply

# Clear Craft's data cache so {% cache %} blocks (e.g. legal pages) pick up template changes
./craft clear-caches/data

echo "Deployment finished ✅"
