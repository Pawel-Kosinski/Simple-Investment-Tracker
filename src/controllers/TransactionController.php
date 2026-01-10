<?php

require_once __DIR__ . '/../repository/TransactionRepository.php';
require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/../repository/PortfolioRepository.php';
require_once 'AppController.php';

class TransactionController extends AppController {

    private TransactionRepository $transactionRepository;
    private AssetRepository $assetRepository;
    private PortfolioRepository $portfolioRepository;

    public function __construct()
    {
        $this->transactionRepository = new TransactionRepository();
        $this->assetRepository = new AssetRepository();
        $this->portfolioRepository = new PortfolioRepository();
    }

    /**
     * Wyświetla historię transakcji
     */
    public function index(): void
    {
        $this->requireAuth();
        
        $userId = $this->getCurrentUserId();
        
        // Pobierz portfele użytkownika
        $portfolios = $this->portfolioRepository->findByUserId($userId);
        
        // Filtr portfela
        $activePortfolioId = isset($_GET['portfolio']) ? (int) $_GET['portfolio'] : null;
        
        if ($activePortfolioId && !$this->portfolioRepository->belongsToUser($activePortfolioId, $userId)) {
            $activePortfolioId = null;
        }
        
        // Filtr typu transakcji
        $typeFilter = $_GET['type'] ?? 'all';
        
        // Pobierz transakcje z repository
        $transactions = $this->transactionRepository->getTransactionsWithFilters(
            $userId, 
            $activePortfolioId, 
            $typeFilter
        );
        
        $this->render('transactions', [
            'transactions' => $transactions,
            'portfolios' => $portfolios,
            'activePortfolioId' => $activePortfolioId,
            'typeFilter' => $typeFilter,
            'userName' => $_SESSION['user_name'] ?? 'Użytkownik'
        ]);
    }
    
    /**
     * Usuwa transakcję (cofa operację)
     */
    public function delete(): void
    {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $transactionId = (int) ($_POST['transaction_id'] ?? 0);
        
        if (!$transactionId) {
            $_SESSION['error'] = 'Nieprawidłowe ID transakcji';
            $this->redirect('/transactions');
            return;
        }
        
        try {
            // Pobierz transakcję z weryfikacją własności
            $transaction = $this->transactionRepository->getTransactionWithOwnership($transactionId);
            
            if (!$transaction) {
                $_SESSION['error'] = 'Transakcja nie istnieje';
                $this->redirect('/transactions');
                return;
            }
            
            // Sprawdź własność
            if ($transaction['user_id'] != $userId) {
                $_SESSION['error'] = 'Nie masz uprawnień do usunięcia tej transakcji';
                $this->redirect('/transactions');
                return;
            }
            
            // Usuń z import_history jeśli to był import XTB
            if (!empty($transaction['notes']) && preg_match('/Import XTB: (\d+)/', $transaction['notes'], $matches)) {
                $xtbId = (int) $matches[1];
                $this->transactionRepository->deleteImportHistory($userId, $xtbId);
            }
            
            // Usuń transakcję
            $this->transactionRepository->deleteById($transactionId);
            
            $_SESSION['success'] = 'Transakcja została cofnięta';
            
        } catch (Exception $e) {
            error_log('Error deleting transaction: ' . $e->getMessage());
            $_SESSION['error'] = 'Błąd podczas cofania transakcji';
        }
        
        $this->redirect('/transactions');
    }
}