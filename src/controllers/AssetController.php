<?php

require_once __DIR__ . '/../repository/TransactionRepository.php';
require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/../repository/PortfolioRepository.php';
require_once __DIR__ . '/../services/PriceService.php';
require_once 'AppController.php';

class AssetController extends AppController {

    private TransactionRepository $transactionRepository;
    private AssetRepository $assetRepository;
    private PortfolioRepository $portfolioRepository;
    private PriceService $priceService;

    public function __construct()
    {
        $this->transactionRepository = new TransactionRepository();
        $this->assetRepository = new AssetRepository();
        $this->portfolioRepository = new PortfolioRepository();
        $this->priceService = new PriceService();
    }

    public function index(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        
        // Pobierz portfele użytkownika
        $portfolios = $this->portfolioRepository->findByUserId($userId);
        
        // Aktywny portfel (z GET lub wszystkie)
        $activePortfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;
        
        if ($activePortfolioId && !$this->portfolioRepository->belongsToUser($activePortfolioId, $userId)) {
            $activePortfolioId = null;
        }
        
        // Pobierz holdings
        if ($activePortfolioId) {
            $holdings = $this->transactionRepository->getHoldingsSummary($activePortfolioId);
        } else {
            $holdings = $this->transactionRepository->getHoldingsSummaryByUserId($userId);
        }

        // Pobierz aktualne ceny
        $prices = $this->priceService->getPricesForHoldings($holdings);
        $portfolioData = $this->priceService->calculatePortfolioValue($holdings, $prices);

        $this->render('assets', [
            'portfolios' => $portfolios,
            'activePortfolioId' => $activePortfolioId,
            'holdings' => $portfolioData['holdings'],
            'portfolioSummary' => $portfolioData['summary'],
            'userName' => $_SESSION['user_name'] ?? 'Użytkownik'
        ]);
    }

    /**
     * Usuwa aktywo (i wszystkie powiązane transakcje - CASCADE)
     */
    public function delete(): void
    {
        $this->requireAuth();
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        
        if (!$assetId) {
            $_SESSION['error'] = 'Nieprawidłowe ID aktywa';
            $this->redirect('/assets');
            return;
        }
        
        // Sprawdź czy aktywo należy do użytkownika (ma transakcje)
        $asset = $this->assetRepository->findById($assetId);
        
        if (!$asset) {
            $_SESSION['error'] = 'Aktywo nie istnieje';
            $this->redirect('/assets');
            return;
        }
        
        // Sprawdź czy użytkownik ma transakcje tego aktywa
        $hasTransactions = false;
        $portfolios = $this->portfolioRepository->findByUserId($userId);
        
        foreach ($portfolios as $portfolio) {
            $transactions = $this->transactionRepository->findByPortfolioAndAsset($portfolio->getId(), $assetId);
            if (!empty($transactions)) {
                $hasTransactions = true;
                break;
            }
        }
        
        if (!$hasTransactions) {
            $_SESSION['error'] = 'Nie masz uprawnień do usunięcia tego aktywa';
            $this->redirect('/assets');
            return;
        }
        
        // Usuń aktywo (CASCADE usunie: transactions, bonds, price_cache, import_history)
        try {
            $this->assetRepository->delete($assetId);
            $_SESSION['success'] = 'Aktywo zostało usunięte wraz ze wszystkimi transakcjami. Możesz zaimportować je ponownie.';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Błąd podczas usuwania aktywa';
        }
        
        $this->redirect('/assets');
    }

    // ============================================
    // FETCH API ENDPOINT
    // ============================================

    /**
     * API: Usuwa aktywo (zwraca JSON)
     */
    public function deleteApi(): void
    {
        header('Content-Type: application/json');
        
        if (!$this->getCurrentUserId()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Wymagane zalogowanie']);
            return;
        }
        
        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['success' => false, 'error' => 'Metoda niedozwolona']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $assetId = (int) ($_POST['asset_id'] ?? 0);
        
        if (!$assetId) {
            echo json_encode(['success' => false, 'error' => 'Nieprawidłowe ID aktywa']);
            return;
        }
        
        // Sprawdź czy aktywo istnieje
        $asset = $this->assetRepository->findById($assetId);
        if (!$asset) {
            echo json_encode(['success' => false, 'error' => 'Aktywo nie istnieje']);
            return;
        }
        
        // Sprawdź uprawnienia
        $hasTransactions = false;
        $portfolios = $this->portfolioRepository->findByUserId($userId);
        
        foreach ($portfolios as $portfolio) {
            $transactions = $this->transactionRepository->findByPortfolioAndAsset($portfolio->getId(), $assetId);
            if (!empty($transactions)) {
                $hasTransactions = true;
                break;
            }
        }
        
        if (!$hasTransactions) {
            echo json_encode(['success' => false, 'error' => 'Brak uprawnień do tego aktywa']);
            return;
        }
        
        try {
            $this->assetRepository->delete($assetId);
            echo json_encode([
                'success' => true, 
                'message' => 'Aktywo zostało usunięte wraz ze wszystkimi transakcjami'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Błąd podczas usuwania aktywa']);
        }
    }
}
