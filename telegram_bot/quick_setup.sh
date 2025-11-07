#!/bin/bash

# Telegram Bot Quick Setup Script
# यह script bot को quickly setup करने के लिए है

echo "🤖 Telegram Bot Quick Setup"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed. Please install PHP first."
    exit 1
fi

echo "✅ PHP is installed"

# Check PHP version
PHP_VERSION=$(php -r "echo PHP_VERSION;" | cut -d. -f1,2)
echo "📦 PHP Version: $PHP_VERSION"

# Create logs directory
if [ ! -d "logs" ]; then
    mkdir -p logs
    echo "✅ Created logs directory"
fi

# Check if config_bot.php exists
if [ ! -f "config_bot.php" ]; then
    echo "❌ config_bot.php not found!"
    exit 1
fi

echo "✅ config_bot.php found"

# Check if bot token is configured
BOT_TOKEN=$(php -r "require 'config_bot.php'; echo TELEGRAM_BOT_TOKEN;")

if [ "$BOT_TOKEN" == "YOUR_BOT_TOKEN_HERE" ]; then
    echo ""
    echo "⚠️  Bot token is not configured!"
    echo ""
    echo "Please follow these steps:"
    echo "1. Open Telegram and search for @BotFather"
    echo "2. Send /newbot command"
    echo "3. Follow instructions to create a bot"
    echo "4. Copy the bot token"
    echo "5. Edit config_bot.php and replace YOUR_BOT_TOKEN_HERE with your token"
    echo ""
    exit 1
fi

echo "✅ Bot token is configured"

# Check database connection
echo ""
echo "🔍 Checking database connection..."
php -r "
require 'config_bot.php';
try {
    \$pdo = getBotDB();
    echo '✅ Database connection successful\n';
} catch (Exception \$e) {
    echo '❌ Database connection failed: ' . \$e->getMessage() . '\n';
    exit(1);
}
"

if [ $? -ne 0 ]; then
    echo ""
    echo "Please check your database configuration in config/database.php"
    exit 1
fi

# Install database tables
echo ""
echo "📊 Installing database tables..."
php install_bot_db.php

if [ $? -ne 0 ]; then
    echo "❌ Database installation failed"
    exit 1
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✅ Setup Complete!"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "🚀 To start the bot, run:"
echo "   php polling.php"
echo ""
echo "Or run in background:"
echo "   nohup php polling.php > /dev/null 2>&1 &"
echo ""
echo "📖 For detailed guide, read: BOT_SETUP_GUIDE.md"
echo ""
