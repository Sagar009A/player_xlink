<?php
/**
 * Telegram Bot Class
 * Enhanced with user registration, link management, statistics, and post conversion
 * Version: 2.0 with Post Converter
 */

require_once __DIR__ . '/config_bot.php';
require_once __DIR__ . '/BotUserManager.php';

class TelegramBot {
    private $token;
    private $apiUrl;
    private $siteApiUrl;
    private $userManager;
    
    public function __construct() {
        $this->token = TELEGRAM_BOT_TOKEN;
        $this->apiUrl = TELEGRAM_API_URL;
        $this->siteApiUrl = SITE_API_URL;
        $this->userManager = new BotUserManager();
        
        if ($this->token === 'YOUR_BOT_TOKEN_HERE') {
            throw new Exception('Please configure your bot token in config_bot.php');
        }
    }
    
    /**
     * Make API request to Telegram
     */
    private function apiRequest($method, $parameters = []) {
        $url = $this->apiUrl . $method;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $parameters);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            botLog("cURL Error: $error", 'ERROR');
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (!$decoded['ok']) {
            botLog("Telegram API Error: " . json_encode($decoded), 'ERROR');
        }
        
        return $decoded;
    }
    
    /**
     * Send message to Telegram
     */
    public function sendMessage($chatId, $text, $options = []) {
        $parameters = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => $options['disable_preview'] ?? false
        ];
        
        if (isset($options['reply_markup'])) {
            $parameters['reply_markup'] = json_encode($options['reply_markup']);
        }
        
        return $this->apiRequest('sendMessage', $parameters);
    }
    
    /**
     * Send photo with caption
     */
    public function sendPhoto($chatId, $photo, $caption = '', $options = []) {
        $parameters = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];
        
        if (isset($options['reply_markup'])) {
            $parameters['reply_markup'] = json_encode($options['reply_markup']);
        }
        
        return $this->apiRequest('sendPhoto', $parameters);
    }
    
    /**
     * Send video with caption
     */
    public function sendVideo($chatId, $video, $caption = '', $options = []) {
        $parameters = [
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];
        
        if (isset($options['reply_markup'])) {
            $parameters['reply_markup'] = json_encode($options['reply_markup']);
        }
        
        return $this->apiRequest('sendVideo', $parameters);
    }
    
    /**
     * Edit message text
     */
    public function editMessageText($chatId, $messageId, $text, $options = []) {
        $parameters = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => $options['parse_mode'] ?? 'HTML',
            'disable_web_page_preview' => $options['disable_preview'] ?? false
        ];
        
        if (isset($options['reply_markup'])) {
            $parameters['reply_markup'] = json_encode($options['reply_markup']);
        }
        
        return $this->apiRequest('editMessageText', $parameters);
    }
    
    /**
     * Answer callback query
     */
    public function answerCallbackQuery($callbackQueryId, $text = '', $showAlert = false) {
        return $this->apiRequest('answerCallbackQuery', [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert
        ]);
    }
    
    /**
     * Send typing action
     */
    public function sendChatAction($chatId, $action = 'typing') {
        return $this->apiRequest('sendChatAction', [
            'chat_id' => $chatId,
            'action' => $action
        ]);
    }
    
    /**
     * Handle /start command
     */
    public function handleStart($chatId, $from) {
        // Register user
        $this->userManager->registerUser($from['id'], $from);
        
        $user = $this->userManager->getUser($from['id']);
        $hasApiKey = !empty($user['api_key']);
        
        $response = "👋 <b>Welcome to " . BOT_NAME . "!</b>\n\n";
        
        if ($hasApiKey) {
            $response .= "✅ Your API key is already configured.\n\n";
            $response .= "📤 <b>How to convert posts:</b>\n";
            $response .= "1. Send me a photo/video with caption\n";
            $response .= "2. Include a supported link in caption\n";
            $response .= "3. Bot will convert and reply!\n\n";
        } else {
            $response .= "🔑 <b>To get started:</b>\n";
            $response .= "1. Visit " . SITE_URL . "\n";
            $response .= "2. Register or login to your account\n";
            $response .= "3. Go to Profile and copy your API key\n";
            $response .= "4. Send me: <code>/setapi YOUR_API_KEY</code>\n\n";
        }
        
        $response .= "📱 <b>Bot Commands:</b>\n";
        $response .= "/setapi - Set your API key\n";
        $response .= "/mylinks - View your shortened links\n";
        $response .= "/stats - View your statistics\n";
        $response .= "/help - Get help\n";
        $response .= "/profile - View your profile\n\n";
        $response .= "🚀 Let's get started!";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Visit Website', 'url' => SITE_URL],
                    ['text' => '📖 Help', 'callback_data' => 'help']
                ]
            ]
        ];
        
        $this->userManager->logCommand($from['id'], '/start');
        
        return $this->sendMessage($chatId, $response, ['reply_markup' => $keyboard]);
    }
    
    /**
     * Handle /setapi command
     */
    public function handleSetApi($chatId, $from, $args) {
        if (empty($args)) {
            $response = "❌ <b>Usage:</b> <code>/setapi YOUR_API_KEY</code>\n\n";
            $response .= "Get your API key from:\n";
            $response .= SITE_URL . "/user/profile.php";
            
            return $this->sendMessage($chatId, $response);
        }
        
        $apiKey = trim($args);
        
        $this->sendChatAction($chatId, 'typing');
        
        $result = $this->userManager->setApiKey($from['id'], $apiKey);
        
        if ($result['success']) {
            $response = "✅ <b>API Key Configured Successfully!</b>\n\n";
            $response .= "You can now use all bot features:\n";
            $response .= "• Send posts with links to convert them\n";
            $response .= "• /mylinks - View your shortened links\n";
            $response .= "• /stats - View your statistics\n\n";
            $response .= "🎉 Ready to go!";
            
            $this->userManager->logCommand($from['id'], '/setapi', ['status' => 'success']);
        } else {
            $response = "❌ <b>Error:</b> " . $result['error'] . "\n\n";
            $response .= "Please check:\n";
            $response .= "• API key is correct\n";
            $response .= "• Your account is active\n";
            $response .= "• You copied the full API key\n\n";
            $response .= "Get your API key from:\n" . SITE_URL . "/user/profile.php";
            
            $this->userManager->logCommand($from['id'], '/setapi', ['status' => 'failed', 'error' => $result['error']]);
        }
        
        return $this->sendMessage($chatId, $response);
    }
    
    /**
     * Handle /mylinks command
     */
    public function handleMyLinks($chatId, $from, $page = 1) {
        if (!$this->userManager->hasApiKey($from['id'])) {
            return $this->sendApiKeyRequiredMessage($chatId);
        }
        
        $this->sendChatAction($chatId, 'typing');
        
        $result = $this->userManager->getUserLinks($from['id'], $page, LINKS_PER_PAGE);
        
        if (!$result['success']) {
            return $this->sendMessage($chatId, "❌ Error: " . $result['error']);
        }
        
        $links = $result['links'];
        $pagination = $result['pagination'];
        
        if (empty($links)) {
            $response = "📭 <b>No links found</b>\n\n";
            $response .= "You haven't created any shortened links yet.\n";
            $response .= "Send me a post with a supported link to get started!";
            
            return $this->sendMessage($chatId, $response);
        }
        
        $response = "🔗 <b>Your Shortened Links</b>\n";
        $response .= "Page {$pagination['current_page']} of {$pagination['total_pages']}\n";
        $response .= "Total: {$pagination['total']} links\n\n";
        
        foreach ($links as $index => $link) {
            $num = ($page - 1) * LINKS_PER_PAGE + $index + 1;
            $response .= "#{$num}. <b>{$link['short_code']}</b>\n";
            $response .= "📊 Views: {$link['total_views']} | 💰 Earnings: $" . number_format($link['total_earnings'], 2) . "\n";
            $response .= "🔗 " . SITE_URL . "/{$link['short_code']}\n";
            $response .= "📅 " . date('M d, Y', strtotime($link['created_at'])) . "\n\n";
        }
        
        $keyboard = $this->createPaginationKeyboard($pagination);
        
        $this->userManager->logCommand($from['id'], '/mylinks', ['page' => $page]);
        
        return $this->sendMessage($chatId, $response, ['reply_markup' => $keyboard]);
    }
    
    /**
     * Handle /stats command
     */
    public function handleStats($chatId, $from) {
        if (!$this->userManager->hasApiKey($from['id'])) {
            return $this->sendApiKeyRequiredMessage($chatId);
        }
        
        $this->sendChatAction($chatId, 'typing');
        
        $result = $this->userManager->getUserStats($from['id']);
        
        if (!$result['success']) {
            return $this->sendMessage($chatId, "❌ Error: " . $result['error']);
        }
        
        $stats = $result['stats'];
        $user = $result['user'];
        
        $response = "📊 <b>Your Statistics</b>\n\n";
        
        $response .= "📈 <b>Overall Stats:</b>\n";
        $response .= "🔗 Total Links: " . $stats['total_links'] . "\n";
        $response .= "👁 Total Views: " . number_format($stats['total_views']) . "\n";
        $response .= "💰 Total Earnings: $" . number_format($stats['total_earnings'], 2) . "\n\n";
        
        $response .= "📅 <b>Today's Stats:</b>\n";
        $response .= "👁 Views: " . number_format($stats['today_views']) . "\n";
        $response .= "💰 Earnings: $" . number_format($stats['today_earnings'], 2) . "\n\n";
        
        $response .= "👤 <b>Account:</b>\n";
        $response .= "📧 " . ($user['email'] ?? 'N/A') . "\n";
        $response .= "📅 Member since: " . date('M d, Y', strtotime($user['registration_date']));
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔗 View Links', 'callback_data' => 'mylinks_1'],
                    ['text' => '🔄 Refresh', 'callback_data' => 'stats']
                ],
                [
                    ['text' => '🌐 Dashboard', 'url' => SITE_URL . '/user/dashboard.php']
                ]
            ]
        ];
        
        $this->userManager->logCommand($from['id'], '/stats');
        
        return $this->sendMessage($chatId, $response, ['reply_markup' => $keyboard]);
    }
    
    /**
     * Handle /profile command
     */
    public function handleProfile($chatId, $from) {
        if (!$this->userManager->hasApiKey($from['id'])) {
            return $this->sendApiKeyRequiredMessage($chatId);
        }
        
        $this->sendChatAction($chatId, 'typing');
        
        $user = $this->userManager->getUser($from['id']);
        
        if (!$user) {
            return $this->sendMessage($chatId, "❌ User not found. Please use /start first.");
        }
        
        $response = "👤 <b>Your Profile</b>\n\n";
        
        $response .= "📱 <b>Telegram Info:</b>\n";
        $response .= "👤 Name: " . ($user['first_name'] ?? 'N/A');
        if (!empty($user['last_name'])) {
            $response .= " " . $user['last_name'];
        }
        $response .= "\n";
        $response .= "🆔 Username: " . ($user['telegram_username'] ? '@' . $user['telegram_username'] : 'N/A') . "\n";
        $response .= "🔢 User ID: " . $user['telegram_user_id'] . "\n\n";
        
        $response .= "🌐 <b>Website Account:</b>\n";
        $response .= "📧 Email: " . ($user['email'] ?? 'N/A') . "\n";
        $response .= "👤 Username: " . ($user['site_username'] ?? 'N/A') . "\n";
        $response .= "🔑 API Key: " . (empty($user['api_key']) ? '❌ Not Set' : '✅ Configured') . "\n\n";
        
        $response .= "📅 <b>Activity:</b>\n";
        $response .= "📝 Registered: " . date('M d, Y', strtotime($user['registration_date'])) . "\n";
        $response .= "🕐 Last Active: " . date('M d, Y H:i', strtotime($user['last_activity']));
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Statistics', 'callback_data' => 'stats'],
                    ['text' => '🔗 My Links', 'callback_data' => 'mylinks_1']
                ],
                [
                    ['text' => '🌐 Website Profile', 'url' => SITE_URL . '/user/profile.php']
                ]
            ]
        ];
        
        $this->userManager->logCommand($from['id'], '/profile');
        
        return $this->sendMessage($chatId, $response, ['reply_markup' => $keyboard]);
    }
    
    /**
     * Handle /help command
     */
    public function handleHelp($chatId) {
        $response = "📖 <b>Help - " . BOT_NAME . "</b>\n\n";
        
        $response .= "🎯 <b>Available Commands:</b>\n\n";
        
        $response .= "/start - Start the bot and register\n";
        $response .= "/setapi - Configure your API key\n";
        $response .= "/mylinks - View your shortened links (15 per page)\n";
        $response .= "/stats - View your overall statistics\n";
        $response .= "/profile - View your profile information\n";
        $response .= "/help - Show this help message\n\n";
        
        $response .= "🔗 <b>Post Conversion:</b>\n";
        $response .= "1. Send a post with photo/video\n";
        $response .= "2. Add caption with supported link\n";
        $response .= "3. Bot converts and replies with template!\n\n";
        
        $response .= "📝 <b>Supported Domains:</b>\n";
        if (defined('SUPPORTED_DOMAINS') && is_array(SUPPORTED_DOMAINS)) {
            foreach (array_slice(SUPPORTED_DOMAINS, 0, 5) as $domain) {
                $response .= "• " . $domain . "\n";
            }
        } else {
            $response .= "• Terabox and other file sharing sites\n";
        }
        $response .= "\n";
        
        $response .= "📝 <b>How to Get Started:</b>\n";
        $response .= "1. Register at " . SITE_URL . "\n";
        $response .= "2. Get your API key from profile\n";
        $response .= "3. Use /setapi to link your account\n";
        $response .= "4. Start converting posts!\n\n";
        
        $response .= "Need help? Contact: " . BOT_USERNAME;
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Visit Website', 'url' => SITE_URL],
                    ['text' => '👤 Profile', 'callback_data' => 'profile']
                ]
            ]
        ];
        
        return $this->sendMessage($chatId, $response, ['reply_markup' => $keyboard]);
    }
    
    /**
     * Handle callback queries (button clicks)
     */
    public function handleCallbackQuery($callbackQuery) {
        $chatId = $callbackQuery['message']['chat']['id'];
        $messageId = $callbackQuery['message']['message_id'];
        $from = $callbackQuery['from'];
        $data = $callbackQuery['data'];
        
        // Answer callback query immediately
        $this->answerCallbackQuery($callbackQuery['id']);
        
        botLog("Callback query from {$from['id']}: $data", 'INFO');
        
        // Parse callback data
        if ($data === 'help') {
            return $this->handleHelp($chatId);
        }
        
        if ($data === 'profile') {
            return $this->handleProfile($chatId, $from);
        }
        
        if ($data === 'stats') {
            return $this->handleStats($chatId, $from);
        }
        
        if (strpos($data, 'mylinks_') === 0) {
            $page = intval(str_replace('mylinks_', '', $data));
            return $this->handleMyLinks($chatId, $from, $page);
        }
        
        // Unknown callback
        return $this->answerCallbackQuery($callbackQuery['id'], 'Unknown action', true);
    }
    
    /**
     * Create pagination keyboard
     */
    private function createPaginationKeyboard($pagination) {
        $buttons = [];
        
        // Navigation buttons
        $navRow = [];
        
        if ($pagination['current_page'] > 1) {
            $navRow[] = [
                'text' => '⬅️ Previous',
                'callback_data' => 'mylinks_' . ($pagination['current_page'] - 1)
            ];
        }
        
        if ($pagination['current_page'] < $pagination['total_pages']) {
            $navRow[] = [
                'text' => 'Next ➡️',
                'callback_data' => 'mylinks_' . ($pagination['current_page'] + 1)
            ];
        }
        
        if (!empty($navRow)) {
            $buttons[] = $navRow;
        }
        
        // Additional buttons
        $buttons[] = [
            ['text' => '📊 Statistics', 'callback_data' => 'stats'],
            ['text' => '🔄 Refresh', 'callback_data' => 'mylinks_' . $pagination['current_page']]
        ];
        
        $buttons[] = [
            ['text' => '🌐 Dashboard', 'url' => SITE_URL . '/user/dashboard.php']
        ];
        
        return ['inline_keyboard' => $buttons];
    }
    
    /**
     * Send API key required message
     */
    private function sendApiKeyRequiredMessage($chatId) {
        $response = "🔑 <b>API Key Required</b>\n\n";
        $response .= "Please configure your API key first:\n";
        $response .= "1. Visit " . SITE_URL . "\n";
        $response .= "2. Login to your account\n";
        $response .= "3. Go to Profile and copy your API key\n";
        $response .= "4. Send: <code>/setapi YOUR_API_KEY</code>";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🌐 Get API Key', 'url' => SITE_URL . '/user/profile.php']
                ]
            ]
        ];
        
        return $this->sendMessage($chatId, $response, ['reply_markup' => $keyboard]);
    }
    
    // ============================================
    // POST CONVERTER METHODS
    // ============================================
    
    /**
     * Check if domain is supported
     */
    private function isSupportedDomain($url) {
        if (!defined('SUPPORTED_DOMAINS') || !is_array(SUPPORTED_DOMAINS)) {
            return false;
        }
        
        $parsedUrl = parse_url($url);
        if (!isset($parsedUrl['host'])) {
            return false;
        }
        
        $host = strtolower($parsedUrl['host']);
        $host = preg_replace('/^www\./', '', $host);
        
        foreach (SUPPORTED_DOMAINS as $domain) {
            if ($host === $domain || strpos($host, '.' . $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extract supported links from text
     */
    private function extractSupportedLinks($text) {
        $links = [];
        
        preg_match_all('#https?://[^\s\)]+#i', $text, $matches);
        
        if (!empty($matches[0])) {
            foreach ($matches[0] as $url) {
                $url = rtrim($url, '.,!?;:');
                
                if ($this->isSupportedDomain($url)) {
                    $links[] = $url;
                }
            }
        }
        
        return $links;
    }
    
    /**
     * Shorten URL via API
     */
    private function shortenUrl($url, $apiKey) {
        try {
            $apiUrl = $this->siteApiUrl . 'shorten.php';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'api_key' => $apiKey,
                'url' => $url
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                botLog("Shorten URL error: $error", 'ERROR');
                return null;
            }
            
            $result = json_decode($response, true);
            
            botLog("Shorten API response: " . $response, 'DEBUG');
            
            if ($result && isset($result['success']) && $result['success']) {
                return $result['short_url'] ?? ($result['shortUrl'] ?? null);
            }
            
            if ($result && isset($result['error'])) {
                botLog("Shorten API error: " . $result['error'], 'ERROR');
            }
            
            return null;
            
        } catch (Exception $e) {
            botLog("Exception in shortenUrl: " . $e->getMessage(), 'ERROR');
            return null;
        }
    }
    
    /**
     * Handle post conversion (MAIN FUNCTION)
     */
    public function handlePostConversion($chatId, $from, $message) {
        if (!$this->userManager->hasApiKey($from['id'])) {
            return $this->sendApiKeyRequiredMessage($chatId);
        }
        
        $this->sendChatAction($chatId, 'typing');
        
        $user = $this->userManager->getUser($from['id']);
        
        if (!$user || !$user['api_key']) {
            return $this->sendApiKeyRequiredMessage($chatId);
        }
        
        $text = $message['caption'] ?? ($message['text'] ?? '');
        
        if (empty($text)) {
            $response = "❌ <b>No text found</b>\n\n";
            $response .= "Please send a post with text/caption containing supported links.";
            return $this->sendMessage($chatId, $response);
        }
        
        $supportedLinks = $this->extractSupportedLinks($text);
        
        if (empty($supportedLinks)) {
            $response = "❌ <b>No supported links found</b>\n\n";
            $response .= "<b>Supported domains:</b>\n";
            if (defined('SUPPORTED_DOMAINS') && is_array(SUPPORTED_DOMAINS)) {
                foreach (array_slice(SUPPORTED_DOMAINS, 0, 5) as $domain) {
                    $response .= "• " . $domain . "\n";
                }
            }
            return $this->sendMessage($chatId, $response);
        }
        
        $originalUrl = $supportedLinks[0];
        
        botLog("Attempting to shorten URL: $originalUrl", 'INFO');
        $shortUrl = $this->shortenUrl($originalUrl, $user['api_key']);
        
        if (!$shortUrl) {
            botLog("Failed to shorten URL for user {$from['id']}", 'ERROR');
            $response = "❌ <b>Failed to convert link</b>\n\n";
            $response .= "Please try again in a moment. If the problem persists:\n";
            $response .= "• Check your API key is valid\n";
            $response .= "• Make sure your account is approved\n";
            $response .= "• Contact support if issue continues";
            return $this->sendMessage($chatId, $response);
        }
        
        // Build template
        $templateHeader = defined('TEMPLATE_HEADER') ? TEMPLATE_HEADER : "👇 𝙁𝙐𝙇𝙇 𝙑𝙄𝘿𝙀𝙊 𝙇𝙄𝙉𝙆 👇 Ad Free";
        $templateFooter = defined('TEMPLATE_FOOTER') ? TEMPLATE_FOOTER : "HOW TO Watch Video👇\nhttps://t.me/yourchannel";
        
        $templateText = $templateHeader . "\n\n";
        $templateText .= $shortUrl . "\n\n";
        $templateText .= $templateFooter;
        
        $this->userManager->logCommand($from['id'], 'convert_post', [
            'original_url' => $originalUrl,
            'short_url' => $shortUrl
        ], 'success');
        
        if (isset($message['photo'])) {
            $photos = $message['photo'];
            $photo = end($photos);
            return $this->sendPhoto($chatId, $photo['file_id'], $templateText);
        }
        
        if (isset($message['video'])) {
            return $this->sendVideo($chatId, $message['video']['file_id'], $templateText);
        }
        
        if (isset($message['document'])) {
            $mimeType = $message['document']['mime_type'] ?? '';
            if (strpos($mimeType, 'video/') === 0) {
                return $this->sendVideo($chatId, $message['document']['file_id'], $templateText);
            }
        }
        
        return $this->sendMessage($chatId, $templateText);
    }
    
    /**
     * Handle incoming update
     */
    public function handleUpdate($update) {
        try {
            // Handle callback queries
            if (isset($update['callback_query'])) {
                return $this->handleCallbackQuery($update['callback_query']);
            }
            
            // Handle messages
            if (!isset($update['message'])) {
                return;
            }
            
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $from = $message['from'];
            $text = $message['text'] ?? '';
            
            botLog("Message from {$from['id']}: $text", 'INFO');
            
            // Check if it's a command (starts with /)
            if (strpos($text, '/') === 0) {
                $parts = explode(' ', $text, 2);
                $command = $parts[0];
                $args = $parts[1] ?? '';
                
                switch ($command) {
                    case '/start':
                        return $this->handleStart($chatId, $from);
                        
                    case '/setapi':
                        return $this->handleSetApi($chatId, $from, $args);
                        
                    case '/mylinks':
                        return $this->handleMyLinks($chatId, $from);
                        
                    case '/stats':
                        return $this->handleStats($chatId, $from);
                        
                    case '/profile':
                        return $this->handleProfile($chatId, $from);
                        
                    case '/help':
                        return $this->handleHelp($chatId);
                        
                    default:
                        $response = "❓ Unknown command: $command\n\n";
                        $response .= "Use /help to see available commands.";
                        return $this->sendMessage($chatId, $response);
                }
            }
            
            // Not a command - handle post conversion
            if (!empty($text) || isset($message['caption']) || isset($message['photo']) || isset($message['video'])) {
                return $this->handlePostConversion($chatId, $from, $message);
            }
            
            // Unknown message type
            $response = "💡 <b>How to use:</b>\n\n";
            $response .= "📝 <b>Send me a post with:</b>\n";
            $response .= "• Photo/Video (optional)\n";
            $response .= "• Text with supported link\n\n";
            $response .= "Use /help for more info!";
            
            return $this->sendMessage($chatId, $response);
            
        } catch (Exception $e) {
            botLog("Error handling update: " . $e->getMessage(), 'ERROR');
            if (isset($chatId)) {
                $this->sendMessage($chatId, 
                    "❌ Sorry, an error occurred. Please try again later."
                );
            }
        }
    }
    
    /**
     * Set webhook
     */
    public function setWebhook($url) {
        return $this->apiRequest('setWebhook', ['url' => $url]);
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook() {
        return $this->apiRequest('deleteWebhook');
    }
    
    /**
     * Get updates (for polling mode)
     */
    public function getUpdates($offset = 0, $limit = 100) {
        return $this->apiRequest('getUpdates', [
            'offset' => $offset,
            'limit' => $limit,
            'timeout' => 30
        ]);
    }
}
