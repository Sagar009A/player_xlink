defined('TELEGRAM_BOT_TOKEN') || define('TELEGRAM_BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');
defined('BOT_USERNAME') || define('BOT_USERNAME', getenv('BOT_USERNAME') ?: '@your_bot_username');
defined('BOT_NAME') || define('BOT_NAME', getenv('BOT_NAME') ?: (defined('SITE_NAME') ? SITE_NAME . ' Bot' : 'Telegram Bot'));
defined('WEBHOOK_URL') || define('WEBHOOK_URL', getenv('WEBHOOK_URL') ?: '');
