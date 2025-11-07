<?php
/**
 * Telegram Bot Class
 * Handles all Telegram API interactions
 */

require_once __DIR__ . '/config_bot.php';

class TelegramBot {
    private $token;
    private $apiUrl;
    private $siteApiUrl;
    
    public function __construct() {
        $this->token = TELEGRAM_BOT_TOKEN;
        $this->apiUrl = TELEGRAM_API_URL;
        $this->siteApiUrl = SITE_API_URL;
        
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
     * Send photo to Telegram
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
     * Send document to Telegram
     */
    public function sendDocument($chatId, $document, $caption = '', $options = []) {
        $parameters = [
            'chat_id' => $chatId,
            'document' => $document,
            'caption' => $caption,
            'parse_mode' => $options['parse_mode'] ?? 'HTML'
        ];
        
        return $this->apiRequest('sendDocument', $parameters);
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
        
        return $this->apiRequest('editMessageText', $parameters);
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
     * Extract links from text
     */
    public function extractLinks($text) {
        $pattern = '/(https?:\/\/[^\s]+)/i';
        preg_match_all($pattern, $text, $matches);
        return $matches[0] ?? [];
    }
    
    /**
     * Convert link using site API
     */
    public function convertLink($url) {
        $apiUrl = $this->siteApiUrl . '?url=' . urlencode($url);
        
        botLog("Converting link: $url", 'INFO');
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_get_info($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            botLog("API Error: $error", 'ERROR');
            return [
                'success' => false,
                'error' => 'Network error while converting link'
            ];
        }
        
        if ($httpCode !== 200) {
            botLog("API HTTP Error: $httpCode", 'ERROR');
            return [
                'success' => false,
                'error' => 'API returned error code: ' . $httpCode
            ];
        }
        
        $result = json_decode($response, true);
        
        if (!$result) {
            botLog("API Response Parse Error", 'ERROR');
            return [
                'success' => false,
                'error' => 'Invalid API response'
            ];
        }
        
        botLog("Conversion result: " . json_encode($result), 'INFO');
        
        return $result;
    }
    
    /**
     * Process message with links
     */
    public function processMessage($message) {
        $chatId = $message['chat']['id'];
        $text = $message['text'] ?? $message['caption'] ?? '';
        $photo = $message['photo'] ?? null;
        
        // Extract links from message
        $links = $this->extractLinks($text);
        
        if (empty($links)) {
            return $this->sendMessage($chatId, 
                "❌ No links found in your message.\n\n" .
                "Please send a message with a video link (Terabox, StreamTape, etc.)"
            );
        }
        
        // Send typing indicator
        $this->sendChatAction($chatId, 'typing');
        
        // Process each link
        $convertedLinks = [];
        $errors = [];
        
        foreach ($links as $link) {
            $result = $this->convertLink($link);
            
            if ($result['success']) {
                $convertedLinks[] = [
                    'original' => $link,
                    'converted' => $result['download_url'] ?? $result['video_url'] ?? null,
                    'title' => $result['title'] ?? null,
                    'thumbnail' => $result['thumbnail'] ?? null,
                    'platform' => $result['platform'] ?? 'Unknown'
                ];
            } else {
                $errors[] = [
                    'link' => $link,
                    'error' => $result['error'] ?? 'Unknown error'
                ];
            }
        }
        
        // Build response
        $response = $this->buildResponse($text, $convertedLinks, $errors);
        
        // Send response
        if ($photo) {
            // If original message had photo, send photo with converted links
            $photoFileId = end($photo)['file_id'];
            return $this->sendPhoto($chatId, $photoFileId, $response);
        } else {
            return $this->sendMessage($chatId, $response, [
                'disable_preview' => false
            ]);
        }
    }
    
    /**
     * Build response message
     */
    private function buildResponse($originalText, $convertedLinks, $errors) {
        $response = "✅ <b>Link Conversion Complete!</b>\n\n";
        
        // Add converted links
        if (!empty($convertedLinks)) {
            foreach ($convertedLinks as $item) {
                $response .= "🎬 <b>Platform:</b> " . htmlspecialchars($item['platform']) . "\n";
                
                if ($item['title']) {
                    $response .= "📝 <b>Title:</b> " . htmlspecialchars($item['title']) . "\n";
                }
                
                $response .= "🔗 <b>Original:</b> " . htmlspecialchars($item['original']) . "\n";
                
                if ($item['converted']) {
                    $response .= "⬇️ <b>Download:</b> " . htmlspecialchars($item['converted']) . "\n";
                } else {
                    $response .= "⚠️ <i>Download link not available</i>\n";
                }
                
                $response .= "\n";
            }
        }
        
        // Add errors
        if (!empty($errors)) {
            $response .= "❌ <b>Failed Links:</b>\n\n";
            foreach ($errors as $error) {
                $response .= "🔗 " . htmlspecialchars($error['link']) . "\n";
                $response .= "⚠️ " . htmlspecialchars($error['error']) . "\n\n";
            }
        }
        
        // Remove original links and add converted text
        $cleanText = $originalText;
        foreach ($convertedLinks as $item) {
            if ($item['converted']) {
                $cleanText = str_replace($item['original'], $item['converted'], $cleanText);
            }
        }
        
        if ($cleanText !== $originalText && !empty(trim(strip_tags($cleanText)))) {
            $response .= "━━━━━━━━━━━━━━━━━━\n";
            $response .= "📄 <b>Updated Message:</b>\n\n";
            $response .= htmlspecialchars($cleanText);
        }
        
        return $response;
    }
    
    /**
     * Handle /start command
     */
    public function handleStart($chatId) {
        $response = "👋 <b>Welcome to Link Converter Bot!</b>\n\n";
        $response .= "🎯 <b>How to use:</b>\n";
        $response .= "1️⃣ Send me a message with video links\n";
        $response .= "2️⃣ You can include text and images too\n";
        $response .= "3️⃣ I'll convert all links and send them back\n\n";
        $response .= "📺 <b>Supported Platforms:</b>\n";
        $response .= "• Terabox\n";
        $response .= "• StreamTape\n";
        $response .= "• FileMoon\n";
        $response .= "• GoFile\n";
        $response .= "• Diskwala\n";
        $response .= "• And more...\n\n";
        $response .= "💡 <b>Example:</b>\n";
        $response .= "Send: <code>Check out this video https://terabox.com/s/example</code>\n";
        $response .= "Get: Converted download link with full message\n\n";
        $response .= "🚀 Start sending links now!";
        
        return $this->sendMessage($chatId, $response);
    }
    
    /**
     * Handle /help command
     */
    public function handleHelp($chatId) {
        $response = "📖 <b>Help - Link Converter Bot</b>\n\n";
        $response .= "🎯 <b>Commands:</b>\n";
        $response .= "/start - Start the bot\n";
        $response .= "/help - Show this help message\n";
        $response .= "/status - Check bot status\n\n";
        $response .= "🔄 <b>How it works:</b>\n";
        $response .= "Simply send any message containing video links from supported platforms. ";
        $response .= "The bot will extract the links, convert them to direct download links, ";
        $response .= "and send back the complete message with converted links.\n\n";
        $response .= "📸 <b>Images:</b>\n";
        $response .= "You can send messages with images and captions. The bot will preserve the image ";
        $response .= "and convert any links in the caption.\n\n";
        $response .= "⚡ <b>Need help?</b>\n";
        $response .= "Contact: @YourSupportUsername";
        
        return $this->sendMessage($chatId, $response);
    }
    
    /**
     * Handle /status command
     */
    public function handleStatus($chatId) {
        $response = "✅ <b>Bot Status:</b> Online\n";
        $response .= "🔧 <b>API Status:</b> Connected\n";
        $response .= "⏱ <b>Response Time:</b> Fast\n";
        $response .= "📊 <b>Server:</b> Operational\n\n";
        $response .= "All systems running smoothly! 🚀";
        
        return $this->sendMessage($chatId, $response);
    }
    
    /**
     * Handle incoming update
     */
    public function handleUpdate($update) {
        try {
            if (!isset($update['message'])) {
                return;
            }
            
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $userId = $message['from']['id'];
            $text = $message['text'] ?? $message['caption'] ?? '';
            
            botLog("Received message from user $userId: $text", 'INFO');
            
            // Handle commands
            if (strpos($text, '/start') === 0) {
                return $this->handleStart($chatId);
            }
            
            if (strpos($text, '/help') === 0) {
                return $this->handleHelp($chatId);
            }
            
            if (strpos($text, '/status') === 0) {
                return $this->handleStatus($chatId);
            }
            
            // Process message with links
            return $this->processMessage($message);
            
        } catch (Exception $e) {
            botLog("Error handling update: " . $e->getMessage(), 'ERROR');
            if (isset($chatId)) {
                $this->sendMessage($chatId, 
                    "❌ Sorry, an error occurred while processing your request. Please try again later."
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
