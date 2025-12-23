<?php

require_once __DIR__ . '/../repository/AssetRepository.php';

class PriceService {
    
    private const CACHE_DURATION = 900; // 15 minut w sekundach
    private const YAHOO_API_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';
    
    private PDO $database;
    
    public function __construct() {
        $this->database = Database::getInstance()->connect();
    }
    
    /**
     * Pobiera aktualną cenę dla pojedynczego symbolu
     */
    public function getCurrentPrice(string $yahooSymbol): ?array {
        // Sprawdź cache
        $cached = $this->getFromCache($yahooSymbol);
        if ($cached !== null) {
            return $cached;
        }
        
        // Pobierz z API
        $price = $this->fetchFromYahoo($yahooSymbol);
        
        if ($price !== null) {
            $this->saveToCache($yahooSymbol, $price['price'], $price['currency']);
        }
        
        return $price;
    }
    
    /**
     * Pobiera ceny dla wielu symboli naraz
     */
    public function getPrices(array $yahooSymbols): array {
        $prices = [];
        
        foreach ($yahooSymbols as $symbol) {
            if (empty($symbol)) continue;
            
            $price = $this->getCurrentPrice($symbol);
            if ($price !== null) {
                $prices[$symbol] = $price;
            }
        }
        
        return $prices;
    }
    
    /**
     * Pobiera ceny dla wszystkich aktywów użytkownika
     */
    public function getPricesForHoldings(array $holdings): array {
        $symbols = array_filter(array_column($holdings, 'yahoo_symbol'));
        return $this->getPrices($symbols);
    }
    
    /**
     * Pobiera dane z Yahoo Finance API
     */
    private function fetchFromYahoo(string $symbol): ?array {
        $url = self::YAHOO_API_URL . urlencode($symbol) . '?interval=1d&range=1d';
        
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    'Accept: application/json'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("PriceService: Failed to fetch price for $symbol");
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['chart']['result'][0])) {
            error_log("PriceService: Invalid response for $symbol");
            return null;
        }
        
        $result = $data['chart']['result'][0];
        $meta = $result['meta'] ?? [];
        
        $price = $meta['regularMarketPrice'] ?? null;
        $previousClose = $meta['previousClose'] ?? null;
        $currency = $meta['currency'] ?? 'USD';
        
        if ($price === null) {
            return null;
        }
        
        $change = $previousClose ? ($price - $previousClose) : 0;
        $changePercent = $previousClose ? (($change / $previousClose) * 100) : 0;
        
        return [
            'symbol' => $symbol,
            'price' => (float) $price,
            'previous_close' => (float) $previousClose,
            'change' => (float) $change,
            'change_percent' => (float) $changePercent,
            'currency' => $currency,
            'fetched_at' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Pobiera cenę z cache
     */
    private function getFromCache(string $yahooSymbol, bool $anyAge = false): ?array {
        $sql = '
            SELECT pc.*, a.yahoo_symbol 
            FROM price_cache pc
            INNER JOIN assets a ON pc.asset_id = a.id
            WHERE a.yahoo_symbol = :symbol';
        
        if (!$anyAge) {
            $sql .= ' AND pc.fetched_at > NOW() - INTERVAL \'' . self::CACHE_DURATION . ' seconds\'';
        }
        
        $sql .= ' ORDER BY pc.fetched_at DESC LIMIT 1';
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute(['symbol' => $yahooSymbol]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) {
            return null;
        }
        
        return [
            'symbol' => $yahooSymbol,
            'price' => (float) $row['price'],
            'currency' => $row['currency'],
            'fetched_at' => $row['fetched_at'],
            'from_cache' => true
        ];
    }
    
    /**
     * Zapisuje cenę do cache
     */
    private function saveToCache(string $yahooSymbol, float $price, string $currency): void {
        // Znajdź asset_id
        $stmt = $this->database->prepare('SELECT id FROM assets WHERE yahoo_symbol = :symbol');
        $stmt->execute(['symbol' => $yahooSymbol]);
        $assetId = $stmt->fetchColumn();
        
        if (!$assetId) {
            return;
        }
        
        // Usuń stare wpisy cache dla tego aktywa
        $stmt = $this->database->prepare('DELETE FROM price_cache WHERE asset_id = :asset_id');
        $stmt->execute(['asset_id' => $assetId]);
        
        // Dodaj nowy wpis
        $stmt = $this->database->prepare('
            INSERT INTO price_cache (asset_id, price, currency, fetched_at)
            VALUES (:asset_id, :price, :currency, NOW())
        ');
        $stmt->execute([
            'asset_id' => $assetId,
            'price' => $price,
            'currency' => $currency
        ]);
    }
    
    /**
     * Czyści cały cache
     */
    public function clearCache(): void {
        $this->database->exec('DELETE FROM price_cache');
    }
    
    /**
     * Oblicza wartość portfela na podstawie aktualnych cen
     */
    public function calculatePortfolioValue(array $holdings, array $prices): array {
        $totalValue = 0;
        $totalCost = 0;
        $totalRevenue = 0;
        $investedTotal = 0;
        $totalDailyChange = 0;
        $holdingsWithPrices = [];
        
        foreach ($holdings as $holding) {
            $yahooSymbol = $holding['yahoo_symbol'] ?? null;
            $quantity = (float) ($holding['total_bought'] ?? 0) - (float) ($holding['total_sold'] ?? 0);
            $cost = (float) ($holding['total_cost'] ?? 0);
            $revenue = (float) ($holding['total_revenue'] ?? 0);
            
            // Pomiń jeśli brak ilości
            if ($quantity <= 0) {
                continue;
            }
            
            // Oblicz średnią cenę zakupu
            $totalBought = (float) ($holding['total_bought'] ?? 0);
            $avgBuyPrice = $totalBought > 0 ? $cost / $totalBought : 0;
            $investedValue = $quantity * $avgBuyPrice;
            
            $currentPrice = null;
            $currentValue = null;
            $currentProfit = null;
            $holdingTotalProfit = null;
            $profitPercent = null;
            $dailyChange = 0;
            $priceSource = 'none';
            
            // Próba 1: cena z API
            if ($yahooSymbol && isset($prices[$yahooSymbol])) {
                $priceData = $prices[$yahooSymbol];
                $currentPrice = $priceData['price'];
                $dailyChange = ($priceData['change'] ?? 0) * $quantity;
                $priceSource = 'api';
            }
            
            // Próba 2: stary cache
            if ($currentPrice === null && $yahooSymbol) {
                $oldCache = $this->getFromCache($yahooSymbol, true);
                if ($oldCache) {
                    $currentPrice = $oldCache['price'];
                    $priceSource = 'old_cache';
                }
            }
            
            // Próba 3: cena zakupu jako fallback
            if ($currentPrice === null) {
                $currentPrice = $avgBuyPrice;
                $priceSource = 'purchase_price';
            }
            
            // Oblicz wartości
            $currentValue = $quantity * $currentPrice;
            $currentProfit = $currentValue - $investedValue;
            $holdingTotalProfit = $currentValue + $revenue - $cost;
            $profitPercent = $cost > 0 ? ($holdingTotalProfit / $cost) * 100 : 0;
            
            $totalValue += $currentValue;
            $investedTotal += $investedValue;
            $totalCost += $cost;
            $totalRevenue += $revenue;
            $totalDailyChange += $dailyChange;

            $holdingsWithPrices[] = array_merge($holding, [
                'quantity' => $quantity,
                'avg_buy_price' => $avgBuyPrice,
                'invested_value' => $investedValue,
                'current_price' => $currentPrice,
                'current_value' => $currentValue,
                'current_profit' => $currentProfit,
                'total_profit' => $holdingTotalProfit,
                'profit_percent' => $profitPercent,
                'daily_change' => $dailyChange,
                'price_source' => $priceSource,
                'price_data' => $prices[$yahooSymbol] ?? null
            ]);
        }
        
        // Zysk bieżący = obecna wartość posiadanych akcji - ich koszt zakupu
        $currentProfit = $totalValue - $investedTotal;

        // Zysk całkowity = obecna wartość + przychody ze sprzedaży - całkowity koszt zakupów  
        $totalProfit = $totalValue + $totalRevenue - $totalCost;
        $totalProfitPercent = $totalCost > 0 ? ($totalProfit / $totalCost) * 100 : 0;
        
        // Zmiana dzienna w procentach
        $previousTotalValue = $totalValue - $totalDailyChange;
        $dailyChangePercent = $previousTotalValue > 0 ? ($totalDailyChange / $previousTotalValue) * 100 : 0;
        
        return [
            'holdings' => $holdingsWithPrices,
            'summary' => [
                'total_value' => $totalValue,
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'current_profit' => $currentProfit,
                'total_profit' => $totalProfit,
                'total_profit_percent' => $totalProfitPercent,
                'daily_change' => $totalDailyChange,
                'daily_change_percent' => $dailyChangePercent
            ]
        ];
    }
}
