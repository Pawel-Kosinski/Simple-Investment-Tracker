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
     * Tworzy nowy portfel
     */
    public function create(): void
    {
        $this->requireAuth();
        
        if (!$this->isPost()) {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }
        
        $userId = $this->getCurrentUserId();
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $isDefault = isset($_POST['is_default']) && $_POST['is_default'] === '1';
        
        // Walidacja
        if (empty($name)) {
            $_SESSION['error'] = 'Nazwa portfela jest wymagana';
            $this->redirect('/dashboard');
            return;
        }
        
        if (strlen($name) > 100) {
            $_SESSION['error'] = 'Nazwa portfela jest za długa (max 100 znaków)';
            $this->redirect('/dashboard');
            return;
        }
        
        // Jeśli to pierwszy portfel użytkownika, ustaw jako domyślny
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount === 0) {
            $isDefault = true;
        }
        
        try {
            $portfolio = new Portfolio($userId, $name, $description, $isDefault);
            $portfolioId = $this->portfolioRepository->create($portfolio);
            
            $_SESSION['success'] = 'Portfel "' . htmlspecialchars($name) . '" został utworzony!';
            $this->redirect('/dashboard');
        } catch (Exception $e) {
            $_SESSION['error'] = 'Błąd podczas tworzenia portfela';
            $this->redirect('/dashboard');
        }
    }
    
    /**
     * Usuwa portfel
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
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        
        if (!$portfolioId) {
            $_SESSION['error'] = 'Nieprawidłowe ID portfela';
            $this->redirect('/dashboard');
            return;
        }
        
        // Sprawdź czy portfel należy do użytkownika
        if (!$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $_SESSION['error'] = 'Nie masz uprawnień do usunięcia tego portfela';
            $this->redirect('/dashboard');
            return;
        }
        
        // Sprawdź czy to nie ostatni portfel
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount <= 1) {
            $_SESSION['error'] = 'Nie możesz usunąć ostatniego portfela';
            $this->redirect('/dashboard');
            return;
        }
        
        try {
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

    /**
     * API: Tworzy nowy portfel (zwraca JSON)
     */
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
        
        // Walidacja
        if (empty($name)) {
            echo json_encode(['success' => false, 'error' => 'Nazwa portfela jest wymagana']);
            return;
        }
        
        if (strlen($name) > 100) {
            echo json_encode(['success' => false, 'error' => 'Nazwa portfela jest za długa (max 100 znaków)']);
            return;
        }
        
        // Pierwszy portfel = domyślny
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount === 0) {
            $isDefault = true;
        }
        
        try {
            $portfolio = new Portfolio($userId, $name, $description, $isDefault);
            $portfolioId = $this->portfolioRepository->create($portfolio);
            
            echo json_encode([
                'success' => true,
                'message' => 'Portfel "' . htmlspecialchars($name) . '" został utworzony!',
                'portfolio_id' => $portfolioId
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Błąd podczas tworzenia portfela']);
        }
    }

    /**
     * API: Usuwa portfel (zwraca JSON)
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
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        
        if (!$portfolioId) {
            echo json_encode(['success' => false, 'error' => 'Nieprawidłowe ID portfela']);
            return;
        }
        
        if (!$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            echo json_encode(['success' => false, 'error' => 'Brak uprawnień do tego portfela']);
            return;
        }
        
        $portfolioCount = $this->portfolioRepository->countByUserId($userId);
        if ($portfolioCount <= 1) {
            echo json_encode(['success' => false, 'error' => 'Nie można usunąć ostatniego portfela']);
            return;
        }
        
        try {
            $this->portfolioRepository->delete($portfolioId, $userId);
            echo json_encode(['success' => true, 'message' => 'Portfel został usunięty']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Błąd podczas usuwania portfela']);
        }
    }
}