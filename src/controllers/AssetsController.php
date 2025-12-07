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
}