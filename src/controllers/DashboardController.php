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
        
        // Aktywny portfel (z GET lub wszystkie)
        $activePortfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;
        
        if ($activePortfolioId && !$this->portfolioRepository->belongsToUser($activePortfolioId, $userId)) {
            $activePortfolioId = null;
        }
        
        // Pobierz dane dla każdego portfela (do kart)
        $portfolioData = [];
        foreach ($portfolios as $portfolio) {
            $pId = $portfolio->getId();
            $pHoldings = $this->transactionRepository->getHoldingsSummary($pId);
            $pPrices = $this->priceService->getPricesForHoldings($pHoldings);
            $calculated = $this->priceService->calculatePortfolioValue($pHoldings, $pPrices);
            $portfolioData[$pId] = $calculated['summary'];
            
            // DEBUG - usuń po naprawieniu
            error_log("Portfolio $pId ({$portfolio->getName()}): holdings=" . count($pHoldings) . ", value=" . ($calculated['summary']['total_value'] ?? 'null'));
        }

        // Pobierz dane dla wybranego portfela lub wszystkich
        if ($activePortfolioId) {
            $holdings = $this->transactionRepository->getHoldingsSummary($activePortfolioId);
        } else {
            $holdings = $this->transactionRepository->getHoldingsSummaryByUserId($userId);
        }

        // Pobierz aktualne ceny
        $prices = $this->priceService->getPricesForHoldings($holdings);
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