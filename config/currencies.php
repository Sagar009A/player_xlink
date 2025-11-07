<?php
// Supported Currencies with Symbols
$supported_currencies = [
    'USD' => ['name' => 'US Dollar', 'symbol' => '$', 'countries' => ['US']],
    'EUR' => ['name' => 'Euro', 'symbol' => '€', 'countries' => ['DE', 'FR', 'IT', 'ES', 'NL']],
    'GBP' => ['name' => 'British Pound', 'symbol' => '£', 'countries' => ['GB']],
    'INR' => ['name' => 'Indian Rupee', 'symbol' => '₹', 'countries' => ['IN']],
    'IDR' => ['name' => 'Indonesian Rupiah', 'symbol' => 'Rp', 'countries' => ['ID']],
    'BRL' => ['name' => 'Brazilian Real', 'symbol' => 'R$', 'countries' => ['BR']],
    'AUD' => ['name' => 'Australian Dollar', 'symbol' => 'A$', 'countries' => ['AU']],
    'CAD' => ['name' => 'Canadian Dollar', 'symbol' => 'C$', 'countries' => ['CA']],
    'JPY' => ['name' => 'Japanese Yen', 'symbol' => '¥', 'countries' => ['JP']],
    'CNY' => ['name' => 'Chinese Yuan', 'symbol' => '¥', 'countries' => ['CN']],
    'MXN' => ['name' => 'Mexican Peso', 'symbol' => '$', 'countries' => ['MX']],
    'AED' => ['name' => 'UAE Dirham', 'symbol' => 'د.إ', 'countries' => ['AE']],
];

// Country to Currency Mapping
$country_currency_map = [
    'US' => 'USD', 'CA' => 'CAD', 'GB' => 'GBP', 'AU' => 'AUD', 'IN' => 'INR',
    'ID' => 'IDR', 'BR' => 'BRL', 'DE' => 'EUR', 'FR' => 'EUR', 'IT' => 'EUR',
    'ES' => 'EUR', 'NL' => 'EUR', 'BE' => 'EUR', 'AT' => 'EUR', 'PT' => 'EUR',
    'JP' => 'JPY', 'CN' => 'CNY', 'MX' => 'MXN', 'AE' => 'AED', 'SA' => 'SAR',
];

// Function to update currency rates from API
function updateCurrencyRates() {
    global $pdo;
    
    $apiKey = CURRENCY_API_KEY;
    $url = CURRENCY_API_URL . $apiKey . '/latest/USD';
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['conversion_rates'])) {
            foreach ($data['conversion_rates'] as $currency => $rate) {
                $stmt = $pdo->prepare("
                    INSERT INTO currency_rates (currency_code, rate_to_usd) 
                    VALUES (?, ?) 
                    ON DUPLICATE KEY UPDATE rate_to_usd = ?, updated_at = NOW()
                ");
                $stmt->execute([$currency, $rate, $rate]);
            }
            return true;
        }
    }
    return false;
}

// Function to convert amount between currencies
function convertCurrency($amount, $fromCurrency, $toCurrency) {
    global $pdo;
    
    if ($fromCurrency === $toCurrency) {
        return $amount;
    }
    
    // Get rates
    $stmt = $pdo->prepare("SELECT rate_to_usd FROM currency_rates WHERE currency_code IN (?, ?)");
    $stmt->execute([$fromCurrency, $toCurrency]);
    $rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    if (count($rates) < 2) {
        return $amount; // Return original if rates not found
    }
    
    // Convert: amount → USD → target currency
    $amountInUSD = $amount / $rates[$fromCurrency];
    $convertedAmount = $amountInUSD * $rates[$toCurrency];
    
    return round($convertedAmount, 2);
}

// Function to format currency
function formatCurrency($amount, $currency = 'USD') {
    global $supported_currencies;
    $symbol = $supported_currencies[$currency]['symbol'] ?? '$';
    return $symbol . number_format($amount, 2);
}

// Auto-update currency rates (runs once per day)
function autoUpdateCurrencyRates() {
    global $pdo;
    
    $lastUpdate = $pdo->query("SELECT MAX(updated_at) as last_update FROM currency_rates")->fetch();
    $hoursSinceUpdate = (time() - strtotime($lastUpdate['last_update'])) / 3600;
    
    $updateInterval = getSetting('currency_update_interval', 24);
    
    if ($hoursSinceUpdate >= $updateInterval) {
        return updateCurrencyRates();
    }
    return false;
}