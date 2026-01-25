<?php

require_once __DIR__ . '/../repository/TransactionRepository.php';
require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/../repository/PortfolioRepository.php';
require_once __DIR__ . '/../services/PriceService.php';
require_once 'AppController.php';

class DashboardController extends AppController {

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
        
        // Aktywny portfel
        $activePortfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;
        
        if ($activePortfolioId && !$this->portfolioRepository->belongsToUser($activePortfolioId, $userId)) {
            $activePortfolioId = null;
        }
        
        // SZYBKIE ŁADOWANIE: używamy TYLKO cache (bez API calls)
        $portfolioData = [];
        foreach ($portfolios as $portfolio) {
            $pId = $portfolio->getId();
            $pHoldings = $this->transactionRepository->getHoldingsSummary($pId);
            // Cache only - natychmiastowe
            $pPrices = $this->priceService->getPricesForHoldingsCacheOnly($pHoldings);
            $calculated = $this->priceService->calculatePortfolioValue($pHoldings, $pPrices);
            $portfolioData[$pId] = $calculated['summary'];
        }

        // Dane dla wybranego portfela lub wszystkich (też z cache)
        if ($activePortfolioId) {
            $holdings = $this->transactionRepository->getHoldingsSummary($activePortfolioId);
        } else {
            $holdings = $this->transactionRepository->getHoldingsSummaryByUserId($userId);
        }

        $prices = $this->priceService->getPricesForHoldingsCacheOnly($holdings);
        $currentPortfolioData = $this->priceService->calculatePortfolioValue($holdings, $prices);

        $this->render('dashboard', [
            'portfolios' => $portfolios,
            'portfolioData' => $portfolioData,
            'activePortfolioId' => $activePortfolioId,
            'holdings' => $currentPortfolioData['holdings'],
            'portfolioSummary' => $currentPortfolioData['summary'],
            'userName' => $_SESSION['user_name'] ?? 'Użytkownik'
        ]);
    }

    /**
     * API: Pobiera dane wszystkich portfeli ze świeżymi cenami (do lazy loading)
     */
    public function getPortfolioDataApi(): void
    {
        $this->requireAuth();
        header('Content-Type: application/json');

        $userId = $this->getCurrentUserId();
        $portfolios = $this->portfolioRepository->findByUserId($userId);
        
        $portfolioData = [];
        foreach ($portfolios as $portfolio) {
            $pId = $portfolio->getId();
            $pHoldings = $this->transactionRepository->getHoldingsSummary($pId);
            // ŚWIEŻE DANE z API
            $pPrices = $this->priceService->getPricesForHoldings($pHoldings);
            $calculated = $this->priceService->calculatePortfolioValue($pHoldings, $pPrices);
            $portfolioData[$pId] = $calculated['summary'];
        }
        
        // Ogólne podsumowanie
        $allHoldings = $this->transactionRepository->getHoldingsSummaryByUserId($userId);
        $allPrices = $this->priceService->getPricesForHoldings($allHoldings);
        $totalData = $this->priceService->calculatePortfolioValue($allHoldings, $allPrices);

        echo json_encode([
            'success' => true,
            'portfolios' => $portfolioData,
            'total' => $totalData['summary'],
            'updated_at' => date('H:i:s')
        ]);
    }

    public function refreshPrices(): void
    {
        $this->requireAuth();

        $this->priceService->clearCache();
        
        $redirect = '/dashboard';
        if (isset($_GET['portfolio'])) {
            $redirect .= '?portfolio=' . $_GET['portfolio'];
        }
        
        $this->redirect($redirect);
    }

    public function getHoldings(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $portfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;

        if ($portfolioId && $this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $holdings = $this->transactionRepository->getHoldingsSummary($portfolioId);
        } else {
            $holdings = $this->transactionRepository->getHoldingsSummaryByUserId($userId);
        }

        $prices = $this->priceService->getPricesForHoldings($holdings);
        $portfolioData = $this->priceService->calculatePortfolioValue($holdings, $prices);

        $this->jsonResponse([
            'holdings' => $portfolioData['holdings'],
            'summary' => $portfolioData['summary']
        ]);
    }

    public function getStats(): void
    {
        $this->requireAuth();

        $userId = $this->getCurrentUserId();
        $portfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;

        if ($portfolioId && $this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $stats = $this->transactionRepository->getPortfolioStats($portfolioId);
        } else {
            $stats = $this->transactionRepository->getStatsByUserId($userId);
        }

        $this->jsonResponse(['stats' => $stats]);
    }
}