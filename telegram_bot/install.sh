#!/bin/bash

# Telegram Bot Installation Script
# Run: bash install.sh

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🤖 Telegram Link Converter Bot - Installer"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Check if PHP is installed
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed!"
    echo "Please install PHP 7.4 or higher first."
    exit 1
fi

echo "✅ PHP version: $(php -v | head -n 1)"

# Check if curl extension is enabled
if ! php -m | grep -q curl; then
    echo "⚠️  cURL extension is not enabled!"
    echo "Please enable cURL extension in php.ini"
    exit 1
fi

echo "✅ cURL extension: Enabled"
echo ""

# Create necessary directories
echo "📁 Creating directories..."
mkdir -p cache logs
chmod -R 755 cache logs
echo "✅ Directories created"
echo ""

# Check if config is set
if grep -q "YOUR_BOT_TOKEN_HERE" config_bot.php; then
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "⚙️  CONFIGURATION REQUIRED"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "Please follow these steps:"
    echo ""
    echo "1️⃣  Create a Telegram bot:"
    echo "   • Open Telegram and search for @BotFather"
    echo "   • Send: /newbot"
    echo "   • Follow the instructions"
    echo "   • Copy your bot token"
    echo ""
    echo "2️⃣  Configure the bot:"
    echo "   • Open: config_bot.php"
    echo "   • Update line 12 with your bot token"
    echo "   • Save the file"
    echo ""
    echo "3️⃣  Run the installation again:"
    echo "   bash install.sh"
    echo ""
    exit 0
fi

# Test configuration
echo "🧪 Testing bot configuration..."
php test_bot.php

if [ $? -eq 0 ]; then
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "✅ Installation Complete!"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo ""
    echo "🚀 Start your bot:"
    echo ""
    echo "   Development (Manual):"
    echo "   → php polling.php"
    echo ""
    echo "   Background:"
    echo "   → nohup php polling.php > /dev/null 2>&1 &"
    echo ""
    echo "   Production (Systemd):"
    echo "   → sudo cp telegram-bot.service /etc/systemd/system/"
    echo "   → sudo systemctl enable telegram-bot"
    echo "   → sudo systemctl start telegram-bot"
    echo ""
    echo "📱 Use your bot:"
    echo "   • Open Telegram"
    echo "   • Search for your bot"
    echo "   • Send: /start"
    echo "   • Send any video link"
    echo ""
    echo "📖 Documentation: README.md"
    echo "🚀 Quick Start: QUICK_START.md"
    echo ""
else
    echo ""
    echo "❌ Configuration test failed!"
    echo "Please check the errors above and fix them."
    exit 1
fi
