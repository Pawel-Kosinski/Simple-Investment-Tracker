<?php

require_once __DIR__ . '/../../Database.php';
require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/../repository/TransactionRepository.php';

class XtbImportService {
    
    private PDO $database;
    private AssetRepository $assetRepository;
    private TransactionRepository $transactionRepository;
    
    // Mapowanie sufiksów XTB na Yahoo Finance
    private const SUFFIX_MAP = [
        'PL' => 'WA',      // Polska GPW
        'US' => '',        // USA - bez sufiksu
        'UK' => 'L',       // Londyn
        'DE' => 'DE',      // Niemcy
        'FR' => 'PA',      // Francja
        'NL' => 'AS',      // Holandia
        'ES' => 'MC',      // Hiszpania
        'IT' => 'MI',      // Włochy
    ];
    
    // Typy operacji do importu
    private const IMPORT_TYPES = [
        'Stock purchase' => 'buy',
        'Stock sale' => 'sell',
        'Dividend' => 'dividend',
        'Dividends' => 'dividend',
    ];
    
    public function __construct() {
        $this->database = Database::getInstance()->connect();
        $this->assetRepository = new AssetRepository();
        $this->transactionRepository = new TransactionRepository();
    }
    
    /**
     * Parsuje plik Excel z XTB
     */
    public function parseFile(string $filePath): array {
        if (!file_exists($filePath)) {
            throw new Exception('Plik nie istnieje');
        }
        
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($extension === 'xlsx' || $extension === 'xls') {
            return $this->parseExcel($filePath);
        } elseif ($extension === 'csv') {
            return $this->parseCsv($filePath);
        }
        
        throw new Exception('Nieobsługiwany format pliku. Użyj Excel (.xlsx, .xls) lub CSV.');
    }
    
    /**
     * Parsuje plik Excel
     */
    private function parseExcel(string $filePath): array {
        // Użyj Python do parsowania Excel (PhpSpreadsheet jest ciężki)
        $pythonScript = <<<'PYTHON'
import sys
import json
import pandas as pd

file_path = sys.argv[1]

try:
    # Wczytaj arkusz CASH OPERATION HISTORY
    df = pd.read_excel(file_path, sheet_name='CASH OPERATION HISTORY', header=10)
    
    # Usuń puste kolumny i wiersze
    df = df.dropna(axis=1, how='all')
    df = df.dropna(subset=['ID'])
    
    # Filtruj tylko operacje stockowe
    stock_types = ['Stock purchase', 'Stock sale', 'Dividend', 'Dividends']
    df = df[df['Type'].isin(stock_types)]
    
    # Konwertuj do listy słowników
    records = []
    for _, row in df.iterrows():
        records.append({
            'id': int(row['ID']) if pd.notna(row['ID']) else None,
            'type': str(row['Type']) if pd.notna(row['Type']) else None,
            'time': row['Time'].isoformat() if pd.notna(row['Time']) else None,
            'comment': str(row['Comment']) if pd.notna(row['Comment']) else None,
            'symbol': str(row['Symbol']) if pd.notna(row['Symbol']) else None,
            'amount': float(row['Amount']) if pd.notna(row['Amount']) else 0,
        })
    
    print(json.dumps({'success': True, 'data': records}))
    
except Exception as e:
    print(json.dumps({'success': False, 'error': str(e)}))
PYTHON;
        
        $tempScript = tempnam(sys_get_temp_dir(), 'xtb_parser_') . '.py';
        file_put_contents($tempScript, $pythonScript);
        
        $output = shell_exec("python3 " . escapeshellarg($tempScript) . " " . escapeshellarg($filePath) . " 2>&1");
        unlink($tempScript);
        
        $result = json_decode($output, true);
        
        if (!$result || !$result['success']) {
            throw new Exception('Błąd parsowania pliku: ' . ($result['error'] ?? $output));
        }
        
        return $this->processOperations($result['data']);
    }
    
    /**
     * Parsuje plik CSV
     */
    private function parseCsv(string $filePath): array {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new Exception('Nie można otworzyć pliku CSV');
        }
        
        $headers = null;
        $operations = [];
        $headerRow = 0;
        
        // Szukaj wiersza z nagłówkami
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            $headerRow++;
            if (in_array('ID', $row) && in_array('Type', $row)) {
                $headers = $row;
                break;
            }
        }
        
        if (!$headers) {
            fclose($handle);
            throw new Exception('Nie znaleziono nagłówków w pliku CSV');
        }
        
        // Parsuj dane
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (count($row) < count($headers)) continue;
            
            $record = array_combine($headers, $row);
            
            // Filtruj tylko operacje stockowe
            $type = $record['Type'] ?? '';
            if (!in_array($type, ['Stock purchase', 'Stock sale', 'Dividend', 'Dividends'])) {
                continue;
            }
            
            $operations[] = [
                'id' => (int) ($record['ID'] ?? 0),
                'type' => $type,
                'time' => $record['Time'] ?? null,
                'comment' => $record['Comment'] ?? null,
                'symbol' => $record['Symbol'] ?? null,
                'amount' => (float) str_replace(',', '.', $record['Amount'] ?? 0),
            ];
        }
        
        fclose($handle);
        
        return $this->processOperations($operations);
    }
    
    /**
     * Przetwarza operacje - parsuje comment, mapuje symbole
     */
    private function processOperations(array $operations): array {
        $processed = [];
        
        foreach ($operations as $op) {
            $transactionType = self::IMPORT_TYPES[$op['type']] ?? null;
            if (!$transactionType) continue;
            
            $parsed = $this->parseComment($op['comment'], $transactionType);
            
            $processed[] = [
                'xtb_id' => $op['id'],
                'xtb_symbol' => $op['symbol'],
                'yahoo_symbol' => $this->convertSymbol($op['symbol']),
                'transaction_type' => $transactionType,
                'quantity' => $parsed['quantity'],
                'price' => $parsed['price'],
                'amount' => abs($op['amount']),
                'transaction_date' => $op['time'],
                'comment' => $op['comment'],
            ];
        }
        
        return $processed;
    }
    
    /**
     * Parsuje komentarz XTB aby wyciągnąć ilość i cenę
     * Przykłady:
     * - "OPEN BUY 1 @ 284.70"
     * - "CLOSE BUY 1 @ 281.40"
     * - "Dividend payment for 10 shares"
     */
    /**
     * Parsuje komentarz XTB aby wyciągnąć ilość i cenę
     */
    private function parseComment(string $comment, string $type): array {
        $quantity = 0;
        $price = 0;
        
        // Obsługuje format: "OPEN BUY 1 @ 100" ORAZ "OPEN BUY 0.25/1.25 @ 100"
        if (preg_match('/(OPEN|CLOSE)\s+(BUY|SELL)\s+([\d.]+)(?:\/[\d.]+)?\s+@\s+([\d.]+)/i', $comment, $matches)) {
            $quantity = (float) $matches[3];
            $price = (float) $matches[4];
        }
        // Pattern dla dywidendy (bez zmian)
        elseif ($type === 'dividend' && preg_match('/(\d+)\s+shares?/i', $comment, $matches)) {
            $quantity = (float) $matches[1];
        }
        
        return [
            'quantity' => $quantity,
            'price' => $price,
        ];
    }
    
    /**
     * Konwertuje symbol XTB na Yahoo Finance
     * CDR.PL → CDR.WA
     * AAPL.US → AAPL
     */
    public function convertSymbol(string $xtbSymbol): string {
        if (empty($xtbSymbol)) return '';
        
        // Rozdziel symbol i sufiks
        $parts = explode('.', $xtbSymbol);
        
        if (count($parts) < 2) {
            return $xtbSymbol; // Brak sufiksu, zwróć bez zmian
        }
        
        $baseSymbol = $parts[0];
        $countrySuffix = strtoupper($parts[1]);
        
        // Znajdź mapowanie
        $yahooSuffix = self::SUFFIX_MAP[$countrySuffix] ?? $countrySuffix;
        
        if (empty($yahooSuffix)) {
            return $baseSymbol; // USA - bez sufiksu
        }
        
        return $baseSymbol . '.' . $yahooSuffix;
    }
    
    /**
     * Znajduje duplikaty w bazie
     */
    public function findDuplicates(int $userId, array $operations): array {
        if (empty($operations)) return [];
        
        $xtbIds = array_column($operations, 'xtb_id');
        $placeholders = implode(',', array_fill(0, count($xtbIds), '?'));
        
        $stmt = $this->database->prepare("
            SELECT xtb_operation_id 
            FROM import_history 
            WHERE user_id = ? AND xtb_operation_id IN ($placeholders)
        ");
        
        $stmt->execute(array_merge([$userId], $xtbIds));
        $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        return $existing;
    }
    
    /**
     * Importuje operacje do bazy
     */
    public function importOperations(int $userId, int $portfolioId, array $operations): array {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        // 1. Znajdź duplikaty (to zostaje bez zmian)
        $duplicates = $this->findDuplicates($userId, $operations);
        
        $this->database->beginTransaction();
        
        try {
            $stmtTransaction = $this->database->prepare('
                INSERT INTO transactions (portfolio_id, asset_id, transaction_type, quantity, price, commission, transaction_date, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmtHistory = $this->database->prepare('
                INSERT INTO import_history (user_id, xtb_operation_id)
                VALUES (?, ?)
            ');

            foreach ($operations as $op) {
                // Pomiń duplikaty
                if (in_array($op['xtb_id'], $duplicates)) {
                    $skipped++;
                    continue;
                }
                
                // Walidacja danych
                if (empty($op['yahoo_symbol']) || $op['quantity'] <= 0) {
                    $errors[] = "Pominięto operację {$op['xtb_id']}: brak symbolu lub ilości";
                    $skipped++;
                    continue;
                }
                
                // Pobierz ID aktywa
                $assetId = $this->getOrCreateAsset($op['yahoo_symbol'], $op['xtb_symbol']);
                
                if (!$assetId) {
                    $errors[] = "Nie można utworzyć aktywa dla {$op['yahoo_symbol']}";
                    $skipped++;
                    continue;
                }
                
                // Oblicz cenę dla dywidendy
                $price = $op['price'];
                if ($op['transaction_type'] === 'dividend' && $op['quantity'] > 0 && $price == 0) {
                    $price = $op['amount'] / $op['quantity'];
                }

                // WYKONANIE ZAPYTANIA
                // Tutaj musi być DOKŁADNIE 8 elementów w tablicy, bo mamy 8 znaków zapytania w SQL powyżej
                $stmtTransaction->execute([
                    $portfolioId,               // 1. portfolio_id
                    $assetId,                   // 2. asset_id
                    $op['transaction_type'],    // 3. transaction_type
                    $op['quantity'],            // 4. quantity
                    $price,                     // 5. price
                    0,                          // 6. commission (jako parametr, nie hardcode w SQL)
                    $op['transaction_date'],    // 7. transaction_date
                    'Import XTB: ' . ($op['comment'] ?? '') // 8. notes
                ]);
                
                // Zapisz w historii
                $stmtHistory->execute([$userId, $op['xtb_id']]);
                
                $imported++;
            }
            
            $this->database->commit();
            
        } catch (Exception $e) {
            $this->database->rollBack();
            // Rzuć wyjątek dalej, żeby zobaczyć błąd w przeglądarce
            throw $e; 
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
    
    /**
     * Pobiera lub tworzy asset na podstawie symbolu
     */
    private function getOrCreateAsset(string $yahooSymbol, string $xtbSymbol): ?int {
        // Sprawdź czy asset istnieje
        $stmt = $this->database->prepare('SELECT id FROM assets WHERE yahoo_symbol = ?');
        $stmt->execute([$yahooSymbol]);
        $assetId = $stmt->fetchColumn();
        
        if ($assetId) {
            return (int) $assetId;
        }
        
        // Utwórz nowy asset
        $parts = explode('.', $xtbSymbol);
        $baseSymbol = $parts[0];
        $countrySuffix = strtoupper($parts[1] ?? 'US');
        
        // Określ walutę na podstawie kraju
        $currency = $this->getCurrencyForCountry($countrySuffix);
        
        // Określ typ (stock vs etf)
        $assetType = $this->guessAssetType($baseSymbol);
        
        $stmt = $this->database->prepare('
            INSERT INTO assets (symbol, name, asset_type, currency, yahoo_symbol)
            VALUES (?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $baseSymbol,
            $baseSymbol, // Nazwa tymczasowa - można później zaktualizować
            $assetType,
            $currency,
            $yahooSymbol
        ]);
        
        return (int) $this->database->lastInsertId();
    }
    
    /**
     * Zwraca walutę dla danego kraju
     */
    private function getCurrencyForCountry(string $country): string {
        $currencies = [
            'PL' => 'PLN',
            'US' => 'USD',
            'UK' => 'GBP',
            'DE' => 'EUR',
            'FR' => 'EUR',
            'NL' => 'EUR',
            'ES' => 'EUR',
            'IT' => 'EUR',
        ];
        
        return $currencies[$country] ?? 'USD';
    }
    
    /**
     * Próbuje odgadnąć typ aktywa (stock vs etf)
     */
    private function guessAssetType(string $symbol): string {
        // Popularne ETF-y
        $etfPatterns = ['ETF', 'UCITS', 'ISHARES', 'VANGUARD', 'SPDR', 'LYXOR'];
        
        foreach ($etfPatterns as $pattern) {
            if (stripos($symbol, $pattern) !== false) {
                return 'etf';
            }
        }
        
        return 'stock';
    }
    
    /**
     * Dodaje pojedynczą transakcję ręcznie
     */
    public function addManualTransaction(
        int $userId,
        int $portfolioId,
        string $symbol,
        string $type,
        float $quantity,
        float $price,
        string $date
    ): int {
        // Konwertuj symbol jeśli wygląda na XTB
        $yahooSymbol = $symbol;
        if (strpos($symbol, '.') !== false) {
            $yahooSymbol = $this->convertSymbol($symbol);
        }
        
        // Pobierz lub utwórz asset
        $assetId = $this->getOrCreateAsset($yahooSymbol, $symbol);
        
        if (!$assetId) {
            throw new Exception("Nie można utworzyć aktywa dla symbolu: $symbol");
        }
        
        // Utwórz transakcję
        $stmt = $this->database->prepare('
            INSERT INTO transactions (portfolio_id, asset_id, transaction_type, quantity, price, commission, transaction_date, notes)
            VALUES (?, ?, ?, ?, ?, 0, ?, ?)
        ');
        
        $stmt->execute([
            $portfolioId,
            $assetId,
            $type,
            $quantity,
            $price,
            $date,
            'Ręczne dodanie'
        ]);
        
        return (int) $this->database->lastInsertId();
    }
}