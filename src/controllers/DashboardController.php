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
        
        // Aktywny portfel (z GET lub domyślny)
        $activePortfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;
        
        if ($activePortfolioId && !$this->portfolioRepository->belongsToUser($activePortfolioId, $userId)) {
            $activePortfolioId = null;
        }
        
        // Pobierz dane - dla konkretnego portfela lub wszystkich
        if ($activePortfolioId) {
            $holdings = $this->transactionRepository->getHoldingsSummary($activePortfolioId);
            $stats = $this->transactionRepository->getPortfolioStats($activePortfolioId);
            $recentTransactions = $this->transactionRepository->findByPortfolioId($activePortfolioId, 5);
        } else {
            $holdings = $this->transactionRepository->getHoldingsSummaryByUserId($userId);
            $stats = $this->transactionRepository->getStatsByUserId($userId);
            $recentTransactions = $this->transactionRepository->findByUserId($userId, 5);
        }

        // Pobierz aktualne ceny z Yahoo Finance
        $prices = $this->priceService->getPricesForHoldings($holdings);
        
        // Oblicz wartość portfela z aktualnymi cenami
        $portfolioData = $this->priceService->calculatePortfolioValue($holdings, $prices);

        // Oblicz podstawowe statystyki
        $totalInvested = (float) ($stats['total_invested'] ?? 0);
        $totalWithdrawn = (float) ($stats['total_withdrawn'] ?? 0);
        $totalDividends = (float) ($stats['total_dividends'] ?? 0);

        $this->render('dashboard', [
            'portfolios' => $portfolios,
            'activePortfolioId' => $activePortfolioId,
            'holdings' => $portfolioData['holdings'],
            'portfolioSummary' => $portfolioData['summary'],
            'stats' => $stats,
            'recentTransactions' => $recentTransactions,
            'totalInvested' => $totalInvested,
            'totalWithdrawn' => $totalWithdrawn,
            'totalDividends' => $totalDividends,
            'userName' => $_SESSION['user_name'] ?? 'Użytkownik'
        ]);
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

        // Pobierz aktualne ceny
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

    public function refreshPrices(): void
    {
        $this->requireAuth();

        $this->priceService->clearCache();
        $this->redirect('/dashboard' . (isset($_GET['portfolio']) ? '?portfolio=' . $_GET['portfolio'] : ''));
    }
}