<?php
/**
 * Telegram Bot Class
 * Enhanced with user registration, link management, and statistics
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
            $response .= "• /mylinks - View your shortened links\n";
            $response .= "• /stats - View your statistics\n";
            $response .= "• Send links to shorten them\n\n";
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
            $response .= "Send me a link to get started!";
            
            return $this->sendMessage($chatId, $response);
        }
        
        $response = "🔗 <b>Your Shortened Links</b>\n";
        $response .= "Page {$pagination['current_page']} of {$pagination['total_pages']}\n";
        $response .= "Total: {$pagination['total']} links\n\n";
        
        foreach ($links as $index => $link) {
            $num = ($page - 1) * LINKS_PER_PAGE + $index + 1;
            $response .= "#{$num}. <b>{$link['short_code']}</b>\n";
            $response .= "🔗 " . SITE_URL . "/{$link['short_code']}\n";
            
            if (!empty($link['custom_alias'])) {
                $response .= "📝 Alias: {$link['custom_alias']}\n";
            }
            
            $response .= "👁 Views: {$link['total_views']}\n";
            $response .= "💰 Earned: $" . number_format($link['total_earnings'], 4) . "\n";
            $response .= "📅 " . date('d M Y', strtotime($link['created_at'])) . "\n";
            $response .= "━━━━━━━━━━━━━━\n";
        }
        
        // Create pagination keyboard
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
        
        $response .= "👤 <b>Account Info:</b>\n";
        $response .= "Name: " . ($user['first_name'] ?? 'N/A') . "\n";
        if (!empty($user['site_username'])) {
            $response .= "Username: @{$user['site_username']}\n";
        }
        $response .= "\n";
        
        $response .= "📈 <b>Overall Stats:</b>\n";
        $response .= "🔗 Total Links: " . number_format($stats['total_links']) . "\n";
        $response .= "👁 Total Views: " . number_format($stats['total_views']) . "\n";
        $response .= "💰 Total Earnings: $" . number_format($stats['total_earnings'], 2) . "\n";
        $response .= "\n";
        
        $response .= "📅 <b>Today's Stats:</b>\n";
        $response .= "👁 Views: " . number_format($stats['today_views']) . "\n";
        $response .= "💰 Earnings: $" . number_format($stats['today_earnings'], 2) . "\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '🔗 My Links', 'callback_data' => 'mylinks_1'],
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
        $user = $this->userManager->getUser($from['id']);
        
        if (!$user) {
            return $this->sendMessage($chatId, "❌ User not found. Please use /start first.");
        }
        
        $response = "👤 <b>Your Profile</b>\n\n";
        $response .= "Telegram ID: <code>{$from['id']}</code>\n";
        $response .= "First Name: " . ($user['first_name'] ?? 'N/A') . "\n";
        
        if (!empty($user['telegram_username'])) {
            $response .= "Username: @{$user['telegram_username']}\n";
        }
        
        $response .= "\n🔑 <b>API Status:</b> ";
        
        if (!empty($user['api_key'])) {
            $response .= "✅ Configured\n";
            $response .= "API Key: <code>" . substr($user['api_key'], 0, 20) . "...</code>\n";
            
            if (!empty($user['site_username'])) {
                $response .= "\n🌐 <b>Linked Account:</b>\n";
                $response .= "Username: @{$user['site_username']}\n";
                $response .= "Email: " . ($user['email'] ?? 'N/A') . "\n";
            }
        } else {
            $response .= "❌ Not configured\n";
            $response .= "\nUse <code>/setapi YOUR_API_KEY</code> to link your account.";
        }
        
        $response .= "\n\n📅 Registered: " . date('d M Y', strtotime($user['registration_date']));
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Statistics', 'callback_data' => 'stats'],
                    ['text' => '🔗 My Links', 'callback_data' => 'mylinks_1']
                ],
                [
                    ['text' => '🌐 Website', 'url' => SITE_URL]
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
        
        $response .= "📝 <b>How to Get Started:</b>\n";
        $response .= "1. Register at " . SITE_URL . "\n";
        $response .= "2. Get your API key from profile\n";
        $response .= "3. Use /setapi to link your account\n";
        $response .= "4. Start viewing your links and stats!\n\n";
        
        $response .= "🔗 <b>Features:</b>\n";
        $response .= "• View all your shortened links\n";
        $response .= "• Check statistics for each link\n";
        $response .= "• Track views and earnings\n";
        $response .= "• Pagination for easy navigation\n\n";
        
        $response .= "💡 <b>Tips:</b>\n";
        $response .= "• Links are displayed 15 per page\n";
        $response .= "• Use navigation buttons to browse pages\n";
        $response .= "• Refresh stats anytime with /stats\n\n";
        
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
            
            // Parse command and arguments
            $parts = explode(' ', $text, 2);
            $command = $parts[0];
            $args = $parts[1] ?? '';
            
            // Handle commands
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
                    // Unknown command
                    $response = "❓ Unknown command: $command\n\n";
                    $response .= "Use /help to see available commands.";
                    return $this->sendMessage($chatId, $response);
            }
            
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
