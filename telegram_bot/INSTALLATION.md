# 📦 Quick Installation Guide

## 🚀 5-Minute Setup (Hindi/English)

### Prerequisites / पहले से चाहिए:
- ✅ PHP 7.4+
- ✅ MySQL Database
- ✅ Telegram Bot Token (@BotFather से)
- ✅ Website with API key system

---

## Step-by-Step Installation / स्टेप बाय स्टेप इंस्टॉलेशन

### Step 1: Bot Token लें (2 minutes)

1. Telegram खोलें
2. `@BotFather` search करें
3. Send: `/newbot`
4. Bot का नाम दें: `MyLinkBot`
5. Username दें: `mylink_bot`
6. Token कॉपी करें: `123456789:ABCdefGHI...`

---

### Step 2: Configuration (1 minute)

`config_bot.php` file open करें:

```php
// Line 16: Bot token यहाँ paste करें
define('TELEGRAM_BOT_TOKEN', '123456789:ABCdefGHI...');

// Line 20: Your website URL
define('SITE_URL', 'https://your-domain.com');

// Line 24: Bot username
define('BOT_USERNAME', '@mylink_bot');
```

Save करें!

---

### Step 3: Database Setup (30 seconds)

Terminal में:
```bash
cd /workspace/telegram_bot
php install_bot_db.php
```

आप देखेंगे:
```
✅ Table 'bot_users' created
✅ Table 'bot_sessions' created
✅ Table 'bot_command_logs' created
🎉 All tables created successfully!
```

---

### Step 4: Test करें (30 seconds)

```bash
php test_bot_features.php
```

All tests ✅ होनी चाहिए।

---

### Step 5: Bot Start करें (10 seconds)

```bash
php polling.php
```

आप देखेंगे:
```
🤖 Starting Telegram Bot in polling mode...
✅ Webhook cleared
📨 Processing updates...
```

---

## 🎯 अब Use करें!

### Telegram पर bot test करें:

1. **Bot खोलें:**
   - Telegram में अपना bot search करें
   - या direct link: `t.me/mylink_bot`

2. **Registration:**
   ```
   You: /start
   Bot: Welcome message with instructions
   ```

3. **API Key Set करें:**
   ```
   You: /setapi YOUR_API_KEY_HERE
   Bot: ✅ API Key configured successfully!
   ```

4. **Links देखें:**
   ```
   You: /mylinks
   Bot: Shows your links (15 per page)
   ```

5. **Stats देखें:**
   ```
   You: /stats
   Bot: Shows your statistics
   ```

---

## 🔧 Background में चलाना (Production)

### Option 1: nohup
```bash
nohup php polling.php > /dev/null 2>&1 &
```

Stop करने के लिए:
```bash
pkill -f polling.php
```

### Option 2: systemd (Recommended)

1. Service file बनाएं:
```bash
sudo nano /etc/systemd/system/telegram-bot.service
```

2. Paste करें:
```ini
[Unit]
Description=Telegram Link Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/workspace/telegram_bot
ExecStart=/usr/bin/php /workspace/telegram_bot/polling.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

3. Enable और start करें:
```bash
sudo systemctl daemon-reload
sudo systemctl enable telegram-bot
sudo systemctl start telegram-bot
sudo systemctl status telegram-bot
```

---

## 🐛 Common Issues / आम समस्याएं

### Issue 1: Bot respond नहीं कर रहा

**Check:**
```bash
# Is bot running?
ps aux | grep polling.php

# Check logs
tail -f logs/bot.log
```

**Fix:**
```bash
pkill -f polling.php
php polling.php
```

---

### Issue 2: Database error

**Check:**
```bash
php -r "require 'config_bot.php'; var_dump(getBotDB());"
```

**Fix:**
- `config/database.php` में credentials check करें
- Database access verify करें

---

### Issue 3: API key invalid

**Fix:**
1. Website पर login करें
2. Profile page खोलें
3. नई API key generate करें
4. `/setapi` से फिर से configure करें

---

### Issue 4: Permissions

**Fix:**
```bash
chmod -R 755 /workspace/telegram_bot
chmod -R 777 /workspace/telegram_bot/logs
```

---

## ✅ Verification Checklist

Installation successful है अगर:

- [x] `php test_bot_features.php` - All tests pass
- [x] Bot responds to `/start` on Telegram
- [x] API key sets successfully with `/setapi`
- [x] `/mylinks` shows your links
- [x] `/stats` displays statistics
- [x] Pagination buttons work
- [x] No errors in `logs/bot.log`

---

## 📚 Important Files

```
telegram_bot/
├── config_bot.php          ← Configure this first
├── install_bot_db.php      ← Run this to setup DB
├── polling.php             ← Run this to start bot
├── TelegramBot.php         ← Main bot logic
├── BotUserManager.php      ← User management
├── test_bot_features.php   ← Test script
└── logs/
    └── bot.log             ← Check errors here
```

---

## 🎉 आप तैयार हैं!

Bot अब fully functional है:
- ✅ User registration working
- ✅ API key integration working
- ✅ Links pagination (15 per page)
- ✅ Statistics display
- ✅ All commands functional

अब users को invite करें और enjoy करें! 🚀

---

## 📞 Need Help?

1. Logs check करें: `tail -f logs/bot.log`
2. Test script run करें: `php test_bot_features.php`
3. Guide पढ़ें: `BOT_SETUP_GUIDE.md`
4. Feature list देखें: `FEATURE_SUMMARY.md`

---

**Installation Time: ~5 minutes**
**Difficulty: Easy**
**Status: Production Ready** ✅
