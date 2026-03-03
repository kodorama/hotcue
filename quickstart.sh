#!/bin/bash

echo "🚀 OBS Controller - Quick Start"
echo "================================"
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    php artisan key:generate
    echo "✅ .env created and app key generated"
else
    echo "✅ .env file exists"
fi

# Check if node_modules exists
if [ ! -d node_modules ]; then
    echo "📦 Installing npm dependencies..."
    npm install
    echo "✅ npm dependencies installed"
else
    echo "✅ npm dependencies already installed"
fi

# Check if vendor exists
if [ ! -d vendor ]; then
    echo "📦 Installing composer dependencies..."
    composer install
    echo "✅ composer dependencies installed"
else
    echo "✅ composer dependencies already installed"
fi

# Run migrations
echo "🗄️  Running database migrations..."
php artisan migrate --force
echo "✅ Database ready"

# Build assets
echo "🎨 Building frontend assets..."
npm run build
echo "✅ Assets built"

echo ""
echo "✅ Setup complete!"
echo ""
echo "Next steps:"
echo "1. Make sure OBS Studio (v28+) is running"
echo "2. Enable WebSocket server in OBS (Tools → WebSocket Server Settings)"
echo "3. Start the app:"
echo ""
echo "   Web mode:    php artisan serve"
echo "   Native app:  php artisan native:serve"
echo ""
echo "4. Configure settings at http://localhost:8000/settings"
echo ""
echo "📚 See SETUP.md for detailed instructions"

