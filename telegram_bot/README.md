# 🤖 Telegram Link Converter Bot

Yeh bot aapki site ke API se connect hokar video links ko convert karta hai. Aap Telegram par koi bhi message send karo with text, images, aur links - bot automatically links extract karke convert karke wapas bhej dega.

## ✨ Features

- 🔗 **Link Conversion**: Terabox, StreamTape, FileMoon, GoFile, aur bahut saare platforms ke links convert karta hai
- 📸 **Image Support**: Images ke saath messages bhi handle karta hai
- 📝 **Text Preservation**: Aapka original text maintain karta hai aur sirf links ko replace karta hai
- ⚡ **Fast Processing**: Quick response time ke saath links convert karta hai
- 🔒 **Secure**: Safe aur secure API calls
- 📊 **Logging**: Complete activity logs

## 📋 Requirements

- PHP 7.4 ya usse upar
- cURL extension enabled
- Telegram Bot Token (BotFather se)
- Access to your site's API

## 🚀 Installation & Setup

### Step 1: Bot Create Karo

1. Telegram par **@BotFather** ko search karo
2. `/newbot` command send karo
3. Bot ka naam do (example: "LinkConverter Bot")
4. Bot ka username do (example: "linkconverter_bot")
5. BotFather aapko **Bot Token** dega - isse save kar lo

### Step 2: Configuration

1. **`config_bot.php`** file open karo
2. Bot token update karo:
```php
define('TELEGRAM_BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');
```

3. Webhook URL set karo (optional, for production):
```php
define('WEBHOOK_URL', 'https://teraboxurll.in/telegram_bot/webhook.php');
```

4. Admin IDs add karo:
```php
define('BOT_ADMIN_IDS', [123456789]); // Apna Telegram user ID yahan dalo
```

### Step 3: Testing

Bot ko test karne ke liye:
```bash
php test_bot.php
```

Yeh script check karega:
- ✅ Bot token valid hai ya nahi
- ✅ Telegram API connection
- ✅ Site API connection
- ✅ Directories writable hain ya nahi
- ✅ Link extraction kaam kar raha hai

### Step 4: Bot Chalao

Do tarike hain bot chalane ke:

#### Option A: Polling Mode (Development/Testing)

```bash
php polling.php
```

Yeh script continuously updates check karega aur messages process karega.

**Background me chalane ke liye:**
```bash
nohup php polling.php > /dev/null 2>&1 &
```

**Stop karne ke liye:**
```bash
pkill -f polling.php
```

#### Option B: Webhook Mode (Production - Recommended)

1. **Webhook URL config_bot.php me set karo**
2. **Webhook setup karo:**
```bash
php setup_webhook.php
```

3. **Web server configure karo** (webhook.php accessible hona chahiye)

Webhook automatically kaam karega jab bhi koi message aayega.

## 🎯 Usage

### Basic Usage

1. Bot ko Telegram par start karo: `/start`
2. Koi bhi message send karo with links:
```
Check out this amazing video!
https://terabox.com/s/1ABCdef123
https://streamtape.com/v/xyz123
```

3. Bot automatically links extract karke convert kar dega aur result send kar dega

### With Images

Photo ke saath caption me link send karo:
```
[Photo]
Caption: Download from https://terabox.com/s/example
```

Bot photo ke saath converted links return karega.

### Multiple Links

Ek message me multiple links bhi send kar sakte ho:
```
Video 1: https://terabox.com/s/abc123
Video 2: https://streamtape.com/v/def456
Video 3: https://filemoon.sx/e/xyz789
```

## 📱 Bot Commands

- `/start` - Bot ko start karo aur welcome message dekho
- `/help` - Help aur instructions dekho
- `/status` - Bot status check karo

## 🔧 Advanced Configuration

### Rate Limiting

`config_bot.php` me rate limit adjust kar sakte ho:
```php
define('BOT_RATE_LIMIT', 30); // Per user per minute
```

### Database Integration (Optional)

Database use karne ke liye:
```php
define('BOT_USE_DATABASE', true);
define('BOT_DB_HOST', 'localhost');
define('BOT_DB_NAME', 'telegram_bot');
define('BOT_DB_USER', 'root');
define('BOT_DB_PASS', 'your_password');
```

### Logging

Logs automatically save ho rahe hain:
- **Bot logs**: `telegram_bot/bot.log`
- **Activity logs**: `telegram_bot/logs/`

## 🔄 Production Deployment

### Using Systemd (Linux)

1. **Service file banao**: `/etc/systemd/system/telegram-bot.service`
```ini
[Unit]
Description=Telegram Link Converter Bot
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/html/telegram_bot
ExecStart=/usr/bin/php /var/www/html/telegram_bot/polling.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

2. **Service enable aur start karo:**
```bash
sudo systemctl daemon-reload
sudo systemctl enable telegram-bot
sudo systemctl start telegram-bot
```

3. **Status check karo:**
```bash
sudo systemctl status telegram-bot
```

4. **Logs dekho:**
```bash
sudo journalctl -u telegram-bot -f
```

### Using Supervisor

1. **Config file banao**: `/etc/supervisor/conf.d/telegram-bot.conf`
```ini
[program:telegram-bot]
command=php /var/www/html/telegram_bot/polling.php
directory=/var/www/html/telegram_bot
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/telegram-bot.log
stderr_logfile=/var/log/telegram-bot-error.log
```

2. **Supervisor reload karo:**
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start telegram-bot
```

## 🐛 Troubleshooting

### Bot respond nahi kar raha

1. Bot token check karo
2. Logs dekho: `tail -f telegram_bot/bot.log`
3. Test script chalo: `php test_bot.php`
4. Polling script check karo: `ps aux | grep polling.php`

### Links convert nahi ho rahe

1. Site API check karo: Test script me API connection status dekho
2. Supported platforms check karo: `/api/extract.php?platforms=true`
3. Logs me errors dekho

### Webhook kaam nahi kar raha

1. HTTPS required hai webhook ke liye
2. Webhook URL accessible hona chahiye
3. SSL certificate valid hona chahiye
4. Webhook info dekho: `curl -X GET 'https://api.telegram.org/bot<TOKEN>/getWebhookInfo'`

### Permission errors

```bash
chmod -R 755 telegram_bot/
chmod -R 777 telegram_bot/cache/
chmod -R 777 telegram_bot/logs/
```

## 📊 Monitoring

### Logs Check Karo

```bash
# Bot logs
tail -f telegram_bot/bot.log

# Last 100 lines
tail -n 100 telegram_bot/bot.log

# Search for errors
grep "ERROR" telegram_bot/bot.log
```

### Bot Status

Telegram API se:
```bash
curl https://api.telegram.org/bot<YOUR_TOKEN>/getMe
```

### Webhook Status

```bash
curl https://api.telegram.org/bot<YOUR_TOKEN>/getWebhookInfo
```

## 🔐 Security Tips

1. **Bot token ko secure rakho** - Publicly share mat karo
2. **Admin IDs set karo** - Important commands sirf admins use kar sake
3. **Rate limiting enable karo** - Abuse prevent karne ke liye
4. **HTTPS use karo** - Webhook ke liye required hai
5. **Regular updates** - PHP aur dependencies updated rakho

## 📝 Supported Platforms

Bot ye platforms ke links convert kar sakta hai:
- ✅ Terabox
- ✅ StreamTape
- ✅ FileMoon
- ✅ GoFile
- ✅ Diskwala
- ✅ StreamNet
- ✅ VividCast
- ✅ NowPlayToc
- ✅ Direct Video URLs

## 💡 Tips

1. **Multiple links**: Ek message me multiple links send kar sakte ho
2. **Images**: Photo ke saath caption me links send kar sakte ho
3. **Text formatting**: HTML formatting support hai response me
4. **Quick replies**: Bot fast respond karta hai with typing indicator

## 🆘 Support

Agar koi problem ho ya help chahiye:
1. Logs check karo
2. Test script run karo
3. Documentation padho
4. GitHub issues create karo (if applicable)

## 📄 License

Educational purposes only. Responsibly use karo.

## 🎉 Success!

Agar sab kuch setup ho gaya hai, toh:
1. Bot ko Telegram par message karo: `/start`
2. Ek link send karo
3. Converted link receive karo
4. Enjoy! 🚀

---

Made with ❤️ for LinkStreamX Users
