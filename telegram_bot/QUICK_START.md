# 🚀 Quick Start Guide - Telegram Bot

5 minutes me apna bot setup karo!

## ⚡ Fast Setup

### 1. Bot Token Lao (2 min)

1. Telegram open karo
2. **@BotFather** search karo
3. Type karo: `/newbot`
4. Bot ka naam do: `My Link Converter`
5. Username do: `mylink_converter_bot`
6. Token copy karo (ye format me hoga: `1234567890:ABCdef...`)

### 2. Config Karo (1 min)

File kholo: `telegram_bot/config_bot.php`

Line 12 par apna token paste karo:
```php
define('TELEGRAM_BOT_TOKEN', '1234567890:ABCdef...');
```

Save karo!

### 3. Test Karo (1 min)

Terminal me run karo:
```bash
cd telegram_bot
php test_bot.php
```

Sab green ticks ✅ hone chahiye!

### 4. Bot Start Karo (1 min)

```bash
php polling.php
```

Screen par dikhega:
```
🤖 Starting Telegram Bot in polling mode...
⏳ Press Ctrl+C to stop

✅ Webhook cleared
```

### 5. Use Karo! (30 sec)

1. Telegram par apne bot ko search karo
2. `/start` command send karo
3. Koi link send karo:
```
https://terabox.com/s/1ABCdef
```

4. Bot converted link dega! 🎉

## 🎯 That's It!

Ab aap bot use kar sakte ho!

## 💡 Tips

- **Background me chalao**: `nohup php polling.php &`
- **Stop karo**: `Ctrl+C` ya `pkill -f polling.php`
- **Logs dekho**: `tail -f bot.log`

## 🆘 Problem?

```bash
php test_bot.php
```

Yeh script problem batayega!

## 📱 Production Setup

Production ke liye `README.md` dekho for:
- Webhook setup
- Systemd service
- Security tips
- Monitoring

---

**Need Help?** README.md me detailed instructions hain!
