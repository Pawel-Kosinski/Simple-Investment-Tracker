<?php

require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/CurrencyService.php';

class PriceService {
    
    private const CACHE_DURATION = 900; // 15 minut w sekundach
    private const YAHOO_API_URL = 'https://query1.finance.yahoo.com/v8/finance/chart/';
    
    private PDO $database;
    private CurrencyService $currencyService;
    
    public function __construct() {
        $this->database = Database::getInstance()->connect();
        $this->currencyService = new CurrencyService();
    }
    
    /**
     * SZYBKI START: Pobiera ceny z cache niezależnie od ich wieku.
     * Używane przy pierwszym renderowaniu strony HTML.
     */
    public function getPricesFromCacheOnly(array $yahooSymbols): array {
        $prices = [];
        
        foreach ($yahooSymbols as $symbol) {
            if (empty($symbol)) continue;
            
            // Pobieramy z cache (nawet stary), żeby strona załadowała się natychmiast
            $cached = $this->getFromCache($symbol, true); 
            if ($cached !== null) {
                $prices[$symbol] = $cached;
            }
        }
        
        return $prices;
    }
    
    public function getPricesForHoldingsCacheOnly(array $holdings): array {
        $symbols = array_filter(array_column($holdings, 'yahoo_symbol'));
        return $this->getPricesFromCacheOnly($symbols);
    }
    
    /**
     * SMART FETCH: Pobiera ceny. Jeśli cache jest świeży (< 15 min), zwraca cache.
     * Jeśli cache wygasł, pyta Yahoo i aktualizuje bazę.
     */
    public function getPrices(array $yahooSymbols): array {
        $prices = [];
        
        foreach ($yahooSymbols as $symbol) {
            if (empty($symbol)) continue;
            
            // 1. Sprawdź czy mamy świeży cache (false = sprawdź datę)
            $cached = $this->getFromCache($symbol, false);
            
            if ($cached !== null) {
                // Mamy świeże dane, nie pytamy API
                $prices[$symbol] = $cached;
            } else {
                // 2. Cache stary lub brak - pytamy Yahoo
                $priceData = $this->fetchFromYahoo($symbol);
                
                if ($priceData !== null) {
                    $prices[$symbol] = $priceData;
                    $this->saveToCache($symbol, $priceData);
                } else {
                    // 3. API nie odpowiada? Weź stary cache jako fallback
                    $oldCache = $this->getFromCache($symbol, true);
                    if ($oldCache !== null) {
                        $prices[$symbol] = $oldCache;
                    }
                }
            }
        }
        
        return $prices;
    }
    
    public function getPricesForHoldings(array $holdings): array {
        $symbols = array_filter(array_column($holdings, 'yahoo_symbol'));
        return $this->getPrices($symbols);
    }
    
    public function getCurrentPrice(string $yahooSymbol): ?array {
        $result = $this->getPrices([$yahooSymbol]);
        return $result[$yahooSymbol] ?? null;
    }

    /**
     * Pobiera dane z Yahoo Finance API
     */
    private function fetchFromYahoo(string $symbol): ?array {
        // Używamy &range=1d, aby API zwróciło poprawne dane o poprzednim zamknięciu
        $url = self::YAHOO_API_URL . urlencode($symbol) . '?interval=1d&range=2d';
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5, // Krótki timeout, żeby nie blokować strony
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; InvestmentTracker/1.0)',
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false || $httpCode !== 200) {
            return null;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['chart']['result'][0]['meta'])) {
            return null;
        }
        
        $meta = $data['chart']['result'][0]['meta'];
        
        $price = $meta['regularMarketPrice'] ?? null;
        // Pobieramy previousClose z meta danych
        $previousClose = $meta['previousClose'] ?? $meta['chartPreviousClose'] ?? null;
        $currency = $meta['currency'] ?? 'USD';
        
        if ($price === null) {
            return null;
        }
        
        // Obliczamy zmianę
        $change = 0;
        $changePercent = 0;
        
        if ($previousClose && $previousClose > 0) {
            $change = $price - $previousClose;
            $changePercent = ($change / $previousClose) * 100;
        }
        
        return [
            'symbol' => $symbol,
            'price' => (float) $price,
            'currency' => $currency,
            'change' => (float) $change,
            'change_percent' => (float) $changePercent,
            'previous_close' => (float) $previousClose,
            'fetched_at' => date('Y-m-d H:i:s'),
            'from_cache' => false
        ];
    }
    
    /**
     * Pobiera cenę z cache
     */
    private function getFromCache(string $yahooSymbol, bool $ignoreTime = false): ?array {
        $sql = '
            SELECT pc.*, a.yahoo_symbol 
            FROM price_cache pc
            INNER JOIN assets a ON pc.asset_id = a.id
            WHERE a.yahoo_symbol = :symbol';
        
        if (!$ignoreTime) {
            // Sprawdza czy cache jest młodszy niż 15 min (CACHE_DURATION)
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
            // Teraz pobieramy też zapisane zmiany z bazy
            'change' => (float) ($row['change_amount'] ?? 0),
            'change_percent' => (float) ($row['change_percent'] ?? 0),
            'previous_close' => (float) ($row['previous_close'] ?? 0),
            'fetched_at' => $row['fetched_at'],
            'from_cache' => true
        ];
    }
    
    /**
     * Zapisuje cenę ORAZ zmiany dzienne do cache
     */
    private function saveToCache(string $yahooSymbol, array $data): void {
        // Znajdź asset_id
        $stmt = $this->database->prepare('SELECT id FROM assets WHERE yahoo_symbol = :symbol');
        $stmt->execute(['symbol' => $yahooSymbol]);
        $assetId = $stmt->fetchColumn();
        
        if (!$assetId) {
            return;
        }
        
        // Usuń stare wpisy
        $stmt = $this->database->prepare('DELETE FROM price_cache WHERE asset_id = :asset_id');
        $stmt->execute(['asset_id' => $assetId]);
        
        // Zapisz nowe dane (rozbudowane o change)
        $stmt = $this->database->prepare('
            INSERT INTO price_cache (
                asset_id, price, currency, 
                change_amount, change_percent, previous_close, 
                fetched_at
            )
            VALUES (
                :asset_id, :price, :currency, 
                :change_amount, :change_percent, :previous_close, 
                NOW()
            )
        ');
        
        $stmt->execute([
            'asset_id' => $assetId,
            'price' => $data['price'],
            'currency' => $data['currency'],
            'change_amount' => $data['change'] ?? 0,
            'change_percent' => $data['change_percent'] ?? 0,
            'previous_close' => $data['previous_close'] ?? 0
        ]);
    }
    
    public function clearCache(): void {
        $this->database->exec('DELETE FROM price_cache');
    }
    
    // ... tutaj metoda calculatePortfolioValue (bez zmian) ...
    // ... metoda calculateAccruedInterest (bez zmian) ...
    
    public function calculateAccruedInterest(array $holding): float
    {
        // ... (Twoja istniejąca logika obligacji) ...
        if (empty($holding['first_purchase_date']) || 
            (empty($holding['first_year_rate']) && empty($holding['interest_rate']))) {
            return 0.0;
        }
        
        $purchaseDate = new DateTime($holding['first_purchase_date']);
        $today = new DateTime();
        $daysHeld = $purchaseDate->diff($today)->days;
        
        if (!empty($holding['interest_rate'])) {
            $annualRate = $holding['interest_rate'];
            $accruedInterest = ($daysHeld / 365.0) * $annualRate;
            return round($accruedInterest, 2);
        }
        
        if (!empty($holding['first_year_rate'])) {
            $firstYearRate = $holding['first_year_rate'];
            if ($daysHeld <= 365) {
                $accruedInterest = ($daysHeld / 365.0) * $firstYearRate;
            } else {
                $yearsHeld = $daysHeld / 365.0;
                $margin = $holding['interest_margin'] ?? 0;
                $estimatedRate = $firstYearRate;
                if ($margin > 0) {
                    $accruedInterest = $yearsHeld * $estimatedRate;
                } else {
                    $accruedInterest = $yearsHeld * $firstYearRate;
                }
            }
            return round($accruedInterest, 2);
        }
        return 0.0;
    }

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
            
            if ($quantity <= 0) continue;
            
            $totalBought = (float) ($holding['total_bought'] ?? 0);
            $avgBuyPrice = $totalBought > 0 ? $cost / $totalBought : 0;
            $investedValue = $quantity * $avgBuyPrice;
            
            $currentPrice = null;
            $dailyChange = 0;
            $priceSource = 'none';
            
            // Obligacje
            if (isset($holding['asset_type']) && $holding['asset_type'] === 'bond') {
                $accruedInterest = $this->calculateAccruedInterest($holding);
                $currentPrice = 100.00 + $accruedInterest;
                $priceSource = 'bond_accrued';
            }
            // Akcje/ETF z ceną
            elseif ($yahooSymbol && isset($prices[$yahooSymbol])) {
                $priceData = $prices[$yahooSymbol];
                $currentPrice = $priceData['price'];
                // TERAZ: daily change pochodzi poprawnie z cache lub API
                $dailyChange = ($priceData['change'] ?? 0) * $quantity;
                $priceSource = $priceData['from_cache'] ? 'cache' : 'api';
            }
            
            // Fallback: stara cena zakupu
            if ($currentPrice === null) {
                $currentPrice = $avgBuyPrice;
                $priceSource = 'purchase_price';
            }
            
            $currentValue = $quantity * $currentPrice;
            $currentProfit = $currentValue - $investedValue;
            $holdingTotalProfit = $currentValue + $revenue - $cost;
            $profitPercent = $cost > 0 ? ($holdingTotalProfit / $cost) * 100 : 0;
            
            // Przelicz na PLN
            $currency = $holding['currency'] ?? 'PLN';
            try {
                $exchangeRate = $this->currencyService->getExchangeRate($currency);
            } catch (Exception $e) {
                $exchangeRate = $currency === 'PLN' ? 1.0 : 4.0;
            }
            
            $currentValuePLN = $currentValue * $exchangeRate;
            $investedValuePLN = $investedValue * $exchangeRate;
            $currentProfitPLN = $currentProfit * $exchangeRate;
            $holdingTotalProfitPLN = $holdingTotalProfit * $exchangeRate;
            $dailyChangePLN = $dailyChange * $exchangeRate;
            
            $totalValue += $currentValuePLN;
            $investedTotal += $investedValuePLN;
            $totalCost += $cost * $exchangeRate;
            $totalRevenue += $revenue * $exchangeRate;
            $totalDailyChange += $dailyChangePLN;

            $holdingsWithPrices[] = array_merge($holding, [
                'quantity' => $quantity,
                'avg_buy_price' => $avgBuyPrice,
                'current_price' => $currentPrice,
                'current_value_pln' => $currentValuePLN,
                'current_profit_pln' => $currentProfitPLN,
                'total_profit_pln' => $holdingTotalProfitPLN,
                'profit_percent' => $profitPercent,
                'daily_change_pln' => $dailyChangePLN,
                'price_source' => $priceSource,
                'currency' => $currency
            ]);
        }
        
        $currentProfit = $totalValue - $investedTotal;
        $totalProfit = $totalValue + $totalRevenue - $totalCost;
        $totalProfitPercent = $totalCost > 0 ? ($totalProfit / $totalCost) * 100 : 0;
        
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