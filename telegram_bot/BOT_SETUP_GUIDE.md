# Telegram Bot Setup Guide - Hindi/English

## 🎯 Overview / सारांश

यह Telegram bot आपको अपने shortened links और statistics देखने देता है।
This Telegram bot allows you to view your shortened links and statistics.

## ✨ Features / विशेषताएं

- ✅ User Registration with API Key / API Key के साथ यूजर रजिस्ट्रेशन
- 📊 View All Your Links (15 per page) / अपने सभी लिंक देखें (15 प्रति पेज)
- 📈 Detailed Statistics / विस्तृत आंकड़े
- 💰 Earnings Tracking / कमाई ट्रैकिंग
- 🔄 Pagination Support / पेजिनेशन सपोर्ट
- 👤 Profile Management / प्रोफाइल प्रबंधन

## 📋 Prerequisites / आवश्यकताएं

1. Telegram bot token (@BotFather से मिलेगा)
2. Your website with API key system
3. PHP 7.4 या उससे ऊपर
4. MySQL database

## 🚀 Quick Setup / त्वरित सेटअप

### Step 1: Bot Token प्राप्त करें

1. Telegram पर @BotFather खोलें
2. `/newbot` command भेजें
3. Bot का नाम और username चुनें
4. Token कॉपी करें (जैसे: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

### Step 2: Configuration

1. `config_bot.php` फ़ाइल खोलें
2. अपना bot token डालें:
```php
define('TELEGRAM_BOT_TOKEN', 'YOUR_TOKEN_HERE');
```

3. Site URL और settings update करें:
```php
define('SITE_URL', 'https://your-domain.com');
define('BOT_USERNAME', '@YourBotUsername');
```

### Step 3: Database Setup

Terminal में run करें:
```bash
cd /workspace/telegram_bot
php install_bot_db.php
```

यह command bot के लिए tables create करेगा:
- `bot_users` - Bot users की information
- `bot_sessions` - Pagination के लिए sessions
- `bot_command_logs` - Commands की logs

### Step 4: Bot को Start करें

#### Option A: Polling Mode (सबसे आसान)

```bash
php polling.php
```

Background में चलाने के लिए:
```bash
nohup php polling.php > /dev/null 2>&1 &
```

#### Option B: Webhook Mode (Production के लिए बेहतर)

1. `setup_webhook.php` file में webhook URL update करें
2. Run करें:
```bash
php setup_webhook.php
```

## 📱 Bot Commands / बॉट कमांड्स

### Basic Commands:

- `/start` - Bot को शुरू करें और register करें
- `/setapi YOUR_API_KEY` - अपनी API key configure करें
- `/mylinks` - अपने shortened links देखें (15 per page)
- `/stats` - अपने overall statistics देखें
- `/profile` - अपनी profile information देखें
- `/help` - Help message देखें

## 🔧 How to Use / उपयोग कैसे करें

### 1. पहली बार Setup:

```
User: /start
Bot: Welcome message + Instructions

User: /setapi abc123def456...
Bot: ✅ API Key configured successfully!
```

### 2. Links देखें:

```
User: /mylinks
Bot: Shows 15 links with:
     - Short code
     - Views count
     - Earnings
     - Creation date
     - Navigation buttons (Previous/Next)
```

### 3. Statistics देखें:

```
User: /stats
Bot: Shows:
     - Total links
     - Total views
     - Total earnings
     - Today's views
     - Today's earnings
```

## 🎨 Features Detail / विशेषताओं का विवरण

### 1. API Key Integration

- हर user अपनी API key से login करता है
- API key website के users table से verify होती है
- Automatic account linking

### 2. Pagination System

- 15 links per page दिखाता है
- Previous/Next buttons
- Page numbers display
- Total links count

### 3. Statistics

**Overall Stats:**
- कुल links
- कुल views
- कुल earnings

**Today's Stats:**
- आज के views
- आज की earnings

### 4. Interactive Buttons

Bot में inline keyboard buttons हैं:
- Navigation (Previous/Next)
- Refresh stats
- Visit dashboard
- View profile

## 📊 Database Structure

### bot_users Table:
```sql
- id
- telegram_user_id (unique)
- telegram_username
- first_name
- last_name
- user_id (linked to main users table)
- api_key
- is_active
- registration_date
- last_activity
```

### bot_sessions Table:
```sql
- id
- telegram_user_id
- session_key
- session_data
- created_at
- expires_at
```

## 🔒 Security Features

1. API key validation
2. User verification
3. Session management
4. Command logging
5. Error handling

## 🐛 Troubleshooting

### Bot respond नहीं कर रहा:

```bash
# Logs check करें
tail -f logs/bot.log

# Polling restart करें
pkill -f polling.php
php polling.php
```

### Database error:

```bash
# Database connection check करें
php -r "require 'config_bot.php'; var_dump(getBotDB());"
```

### API key काम नहीं कर रही:

1. Website पर login करें
2. Profile से नई API key generate करें
3. `/setapi` command से फिर से configure करें

## 📝 Examples / उदाहरण

### Example 1: नया user registration

```
User: /start
Bot: Welcome! Please set your API key using /setapi

User: /setapi 1234567890abcdef...
Bot: ✅ API Key configured successfully!

User: /mylinks
Bot: [Shows list of 15 links with pagination]
```

### Example 2: Links के through navigate करना

```
User: /mylinks
Bot: Page 1 of 5 (showing 15 links)
     [Previous] [Next] buttons

User: [Clicks Next]
Bot: Page 2 of 5 (next 15 links)
```

### Example 3: Statistics देखना

```
User: /stats
Bot: 
📊 Your Statistics

👤 Account Info:
Name: John Doe

📈 Overall Stats:
🔗 Total Links: 150
👁 Total Views: 5,432
💰 Total Earnings: $54.32

📅 Today's Stats:
👁 Views: 123
💰 Earnings: $1.23
```

## 🎯 Advanced Features

### Command Logging

सभी commands automatically log होती हैं:
- Command name
- User ID
- Parameters
- Status (success/failed)
- Timestamp

### Session Management

Pagination के लिए sessions:
- 30 minutes expiry
- Automatic cleanup
- User-specific data

## 🚀 Production Deployment

### Using systemd (Recommended):

1. Service file edit करें:
```bash
sudo nano /etc/systemd/system/telegram-bot.service
```

2. Bot को enable और start करें:
```bash
sudo systemctl enable telegram-bot
sudo systemctl start telegram-bot
sudo systemctl status telegram-bot
```

### Using PM2:

```bash
pm2 start polling.php --name telegram-bot --interpreter php
pm2 save
pm2 startup
```

## 📞 Support

Problems होने पर:
1. Logs check करें: `logs/bot.log`
2. Database verify करें
3. API key validate करें
4. Bot token check करें

## 🎉 आप तैयार हैं!

अब आपका Telegram bot fully functional है:
- Users अपनी API key से register कर सकते हैं
- अपने links देख सकते हैं (15 per page)
- Statistics track कर सकते हैं
- Pagination के साथ navigate कर सकते हैं

Happy Coding! 🚀
