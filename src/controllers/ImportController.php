<?php

require_once __DIR__ . '/../services/XtbImportService.php';
require_once __DIR__ . '/../repository/PortfolioRepository.php';
require_once 'AppController.php';

class ImportController extends AppController {
    
    private XtbImportService $importService;
    private PortfolioRepository $portfolioRepository;
    
    public function __construct() {
        $this->importService = new XtbImportService();
        $this->portfolioRepository = new PortfolioRepository();
    }
    
    /**
     * Wyświetla stronę importu
     */
    public function index(): void {
        $this->requireAuth();
        
        $userId = $this->getCurrentUserId();
        $portfolios = $this->portfolioRepository->findByUserId($userId);
        
        // Sprawdź czy są dane z sesji (preview)
        $previewData = $_SESSION['import_preview'] ?? null;
        
        $this->render('import', [
            'portfolios' => $portfolios,
            'previewData' => $previewData,
            'userName' => $_SESSION['user_name'] ?? 'Użytkownik'
        ]);
    }
    
    /**
     * Obsługuje upload pliku i generuje podgląd
     */
    public function upload(): void {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            $this->redirect('/import');
            return;
        }
        
        $userId = $this->getCurrentUserId();
        
        // Sprawdź czy plik został przesłany
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['import_error'] = 'Błąd przesyłania pliku';
            $this->redirect('/import');
            return;
        }
        
        $file = $_FILES['file'];
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        
        // Walidacja portfela
        if (!$portfolioId || !$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $_SESSION['import_error'] = 'Nieprawidłowy portfel';
            $this->redirect('/import');
            return;
        }
        
        // Walidacja rozszerzenia
        $allowedExtensions = ['xlsx', 'xls', 'csv'];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, $allowedExtensions)) {
            $_SESSION['import_error'] = 'Nieobsługiwany format pliku. Dozwolone: ' . implode(', ', $allowedExtensions);
            $this->redirect('/import');
            return;
        }
        
        // Przenieś plik do tymczasowej lokalizacji
        $uploadDir = sys_get_temp_dir();
        $uploadPath = $uploadDir . '/xtb_import_' . uniqid() . '.' . $extension;
        
        if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
            $_SESSION['import_error'] = 'Nie można zapisać pliku';
            $this->redirect('/import');
            return;
        }
        
        try {
            // Parsuj plik
            $operations = $this->importService->parseFile($uploadPath);
            
            if (empty($operations)) {
                $_SESSION['import_error'] = 'Nie znaleziono operacji do zaimportowania';
                unlink($uploadPath);
                $this->redirect('/import');
                return;
            }
            
            // Znajdź duplikaty
            $duplicates = $this->importService->findDuplicates($userId, $operations);
            
            // Zapisz w sesji do podglądu
            $_SESSION['import_preview'] = [
                'file_path' => $uploadPath,
                'file_name' => $file['name'],
                'portfolio_id' => $portfolioId,
                'operations' => $operations,
                'duplicates' => $duplicates,
                'total_count' => count($operations),
                'duplicate_count' => count($duplicates),
                'new_count' => count($operations) - count($duplicates),
            ];
            
            unset($_SESSION['import_error']);
            
        } catch (Exception $e) {
            $_SESSION['import_error'] = 'Błąd parsowania pliku: ' . $e->getMessage();
            unlink($uploadPath);
        }
        
        $this->redirect('/import');
    }
    
    /**
     * Potwierdza i wykonuje import
     */
    public function confirm(): void {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            $this->redirect('/import');
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $previewData = $_SESSION['import_preview'] ?? null;
        
        if (!$previewData) {
            $_SESSION['import_error'] = 'Brak danych do importu';
            $this->redirect('/import');
            return;
        }
        
        // Pobierz wybrane operacje (checkboxy)
        $selectedIds = $_POST['selected'] ?? [];
        
        if (empty($selectedIds)) {
            $_SESSION['import_error'] = 'Nie wybrano żadnych operacji';
            $this->redirect('/import');
            return;
        }
        
        // Filtruj tylko wybrane operacje
        $operations = array_filter($previewData['operations'], function($op) use ($selectedIds) {
            return in_array($op['xtb_id'], $selectedIds);
        });
        
        try {
            $result = $this->importService->importOperations(
                $userId,
                $previewData['portfolio_id'],
                $operations
            );
            
            // Wyczyść sesję
            if (file_exists($previewData['file_path'])) {
                unlink($previewData['file_path']);
            }
            unset($_SESSION['import_preview']);
            
            $_SESSION['import_success'] = sprintf(
                'Zaimportowano %d operacji. Pominięto: %d.',
                $result['imported'],
                $result['skipped']
            );
            
            if (!empty($result['errors'])) {
                $_SESSION['import_warnings'] = $result['errors'];
            }
            
        } catch (Exception $e) {
            $_SESSION['import_error'] = 'Błąd importu: ' . $e->getMessage();
        }
        
        $this->redirect('/import');
    }
    
    /**
     * Anuluje import i czyści sesję
     */
    public function cancel(): void {
        $this->requireAuth();
        
        $previewData = $_SESSION['import_preview'] ?? null;
        
        if ($previewData && file_exists($previewData['file_path'])) {
            unlink($previewData['file_path']);
        }
        
        unset($_SESSION['import_preview']);
        
        $this->redirect('/import');
    }
    
    /**
     * Dodaje transakcję ręcznie
     */
    public function manual(): void {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            $this->redirect('/import');
            return;
        }
        
        $userId = $this->getCurrentUserId();
        
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        $symbol = trim($_POST['symbol'] ?? '');
        $type = $_POST['type'] ?? 'buy';
        $quantity = (float) ($_POST['quantity'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Walidacja
        $errors = [];
        
        if (!$portfolioId || !$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $errors[] = 'Nieprawidłowy portfel';
        }
        
        if (empty($symbol)) {
            $errors[] = 'Symbol jest wymagany';
        }
        
        if (!in_array($type, ['buy', 'sell', 'dividend'])) {
            $errors[] = 'Nieprawidłowy typ operacji';
        }
        
        if ($quantity <= 0) {
            $errors[] = 'Ilość musi być większa od 0';
        }
        
        if ($price <= 0) {
            $errors[] = 'Cena musi być większa od 0';
        }
        
        if (!empty($errors)) {
            $_SESSION['import_error'] = implode(', ', $errors);
            $this->redirect('/import');
            return;
        }
        
        try {
            $this->importService->addManualTransaction(
                $userId,
                $portfolioId,
                strtoupper($symbol),
                $type,
                $quantity,
                $price,
                $date
            );
            
            $_SESSION['import_success'] = 'Transakcja została dodana';
            
        } catch (Exception $e) {
            $_SESSION['import_error'] = 'Błąd: ' . $e->getMessage();
        }
        
        $this->redirect('/import');
    }
}