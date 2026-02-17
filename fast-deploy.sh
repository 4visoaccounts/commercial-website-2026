# full deploy
git pull origin master

# 3. Build production assets
echo "Building production assets..."
npm run production

# 8. Apply Craft CMS config & migrations
echo "Running Craft CMS setup..."
./craft migrate/all
./craft project-config/apply

echo "Deployment finished ✅"
