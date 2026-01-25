<?php

require_once __DIR__ . '/../repository/PortfolioRepository.php';
require_once __DIR__ . '/../models/Portfolio.php';
require_once 'AppController.php';

class PortfolioController extends AppController {

    private PortfolioRepository $portfolioRepository;

    public function __construct()
    {
        $this->portfolioRepository = new PortfolioRepository();
    }

    /**
     * Standardowa metoda tworzenia (formularz HTML)
     */
    public function create(): void
    {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            http_response_code(405);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isDefault = isset($_POST['is_default']) && $_POST['is_default'] === '1';
        
        if (empty($name)) {
            $_SESSION['error'] = 'Nazwa portfela jest wymagana';
            $this->redirect('/dashboard');
            return;
        }
        
        if (strlen($name) > 100) {
            $_SESSION['error'] = 'Nazwa portfela jest za długa';
            $this->redirect('/dashboard');
            return;
        }
        
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount === 0) {
            $isDefault = true;
        }
        
        try {
            $portfolio = new Portfolio($userId, $name, $description, $isDefault);
            $this->portfolioRepository->create($portfolio);
            $_SESSION['success'] = 'Portfel został utworzony!';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Błąd podczas tworzenia portfela';
        }
        
        $this->redirect('/dashboard');
    }
    
    /**
     * Standardowa metoda usuwania (formularz HTML)
     */
    public function delete(): void
    {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            http_response_code(405);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        
        if (!$portfolioId) {
            $this->redirect('/dashboard');
            return;
        }
        
        if (!$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $_SESSION['error'] = 'Brak uprawnień';
            $this->redirect('/dashboard');
            return;
        }
        
        // Zabezpieczenie przed usunięciem ostatniego portfela
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount <= 1) {
            $_SESSION['error'] = 'Nie możesz usunąć ostatniego portfela';
            $this->redirect('/dashboard');
            return;
        }
        
        try {
            // Czyścimy zależności i usuwamy
            $this->deletePortfolioDependencies($portfolioId);
            $this->portfolioRepository->delete($portfolioId, $userId);
            $_SESSION['success'] = 'Portfel został usunięty';
        } catch (Exception $e) {
            $_SESSION['error'] = 'Błąd podczas usuwania portfela';
        }
        
        $this->redirect('/dashboard');
    }

    // ============================================
    // FETCH API ENDPOINTS
    // ============================================

    public function createApi(): void
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
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isDefault = isset($_POST['is_default']) && $_POST['is_default'] === '1';
        
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Nazwa portfela jest wymagana']);
            return;
        }
        
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount === 0) {
            $isDefault = true;
        }
        
        try {
            $portfolio = new Portfolio($userId, $name, $description, $isDefault);
            $portfolioId = $this->portfolioRepository->create($portfolio);
            
            echo json_encode([
                'success' => true,
                'message' => 'Portfel utworzony',
                'portfolio_id' => $portfolioId
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Błąd bazy danych']);
        }
    }

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
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        
        if (!$portfolioId) {
            echo json_encode(['success' => false, 'error' => 'Nieprawidłowe ID']);
            return;
        }
        
        if (!$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            echo json_encode(['success' => false, 'error' => 'Brak uprawnień']);
            return;
        }
        
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount <= 1) {
            echo json_encode(['success' => false, 'error' => 'Nie można usunąć ostatniego portfela']);
            return;
        }
        
        try {
            // Najpierw usuwamy aktywa i transakcje
            $this->deletePortfolioDependencies($portfolioId);
            
            // Potem usuwamy sam portfel
            $this->portfolioRepository->delete($portfolioId, $userId);
            
            echo json_encode(['success' => true, 'message' => 'Portfel został usunięty']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Błąd podczas usuwania: ' . $e->getMessage()]);
        }
    }

    /**
     * Pomocnicza metoda do usuwania kaskadowego
     */
    private function deletePortfolioDependencies(int $portfolioId): void {
        $db = Database::getInstance()->connect();
        try {
            $stmt = $db->prepare('DELETE FROM transactions WHERE portfolio_id = :pid');
            $stmt->execute(['pid' => $portfolioId]);
        } catch (PDOException $e) {
            throw new Exception("Błąd struktury bazy: " . $e->getMessage());
        }
    }
}