<?php

class CurrencyService {
    
    private const CACHE_DURATION = 86400; // 24 godziny
    private const NBP_API_URL = 'https://api.nbp.pl/api/exchangerates/rates/a/';
    
    private PDO $database;
    
    public function __construct() {
        $this->database = Database::getInstance()->connect();
    }
    
    /**
     * Pobiera kurs waluty względem PLN
     * Zwraca 1.0 dla PLN
     */
    public function getExchangeRate(string $currency): float {
        if ($currency === 'PLN') {
            return 1.0;
        }
        
        try {
            $cached = $this->getFromCache($currency);
            if ($cached !== null) {
                return $cached;
            }
        } catch (Exception $e) {
            // Cache error - continue to fetch from API
        }
        
        try {
            $rate = $this->fetchFromNBP($currency);
            
            if ($rate !== null) {
                $this->saveToCache($currency, $rate);
                return $rate;
            }
        } catch (Exception $e) {
            // API error - use fallback
        }
        
        return $this->getFallbackRate($currency);
    }
    
    /**
     * Przelicza kwotę z waluty obcej na PLN
     */
    public function convertToPLN(float $amount, string $fromCurrency): float {
        $rate = $this->getExchangeRate($fromCurrency);
        return $amount * $rate;
    }
    
    /**
     * Pobiera kurs z cache
     */
    private function getFromCache(string $currency): ?float {
        $stmt = $this->database->prepare('
            SELECT rate 
            FROM currency_cache 
            WHERE currency = ? 
              AND fetched_at > NOW() - INTERVAL \'24 hours\'
            ORDER BY fetched_at DESC 
            LIMIT 1
        ');
        $stmt->execute([$currency]);
        $result = $stmt->fetchColumn();
        
        return $result ? (float) $result : null;
    }
    
    /**
     * Zapisuje kurs do cache
     */
    private function saveToCache(string $currency, float $rate): void {
        $stmt = $this->database->prepare('
            INSERT INTO currency_cache (currency, rate, fetched_at)
            VALUES (?, ?, CURRENT_TIMESTAMP)
        ');
        $stmt->execute([$currency, $rate]);
    }
    
    /**
     * Pobiera kurs z NBP API
     */
    private function fetchFromNBP(string $currency): ?float {
        try {
            $url = self::NBP_API_URL . strtolower($currency) . '/?format=json';
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                return null;
            }
            
            $data = json_decode($response, true);
            
            if (isset($data['rates'][0]['mid'])) {
                return (float) $data['rates'][0]['mid'];
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Zwraca fallback rate jeśli NBP nie ma danej waluty
     */
    private function getFallbackRate(string $currency): float {
        $fallbackRates = [
            'USD' => 4.0,
            'EUR' => 4.3,
            'GBP' => 5.0,
            'CHF' => 4.6,
            'CZK' => 0.17,
            'SEK' => 0.38,
            'NOK' => 0.37,
            'DKK' => 0.58,
        ];
        
        return $fallbackRates[$currency] ?? 4.0;
    }
    
    /**
     * Pobiera kursy dla wielu walut naraz
     */
    public function getExchangeRates(array $currencies): array {
        $rates = [];
        
        foreach ($currencies as $currency) {
            $rates[$currency] = $this->getExchangeRate($currency);
        }
        
        return $rates;
    }
}