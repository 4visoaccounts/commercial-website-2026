# full deploy
git pull origin master

# 1. Install PHP dependencies
echo "Installing Composer dependencies..."
composer install

# 2. Install JS dependencies
echo "Installing Node dependencies..."
npm install

# 3. Build production assets
echo "Building production assets..."
npm run production

# 8. Apply Craft CMS config & migrations
echo "Running Craft CMS setup..."
composer install
./craft migrate/all
./craft project-config/apply

echo "Deployment finished ✅"
