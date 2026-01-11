<?php

require_once __DIR__ . '/../repository/BondRepository.php';
require_once __DIR__ . '/../repository/AssetRepository.php';
require_once __DIR__ . '/../repository/PortfolioRepository.php';
require_once __DIR__ . '/../repository/TransactionRepository.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../../Database.php';
require_once 'AppController.php';

class BondController extends AppController {

    private BondRepository $bondRepository;
    private AssetRepository $assetRepository;
    private PortfolioRepository $portfolioRepository;
    private TransactionRepository $transactionRepository;

    public function __construct()
    {
        $this->bondRepository = new BondRepository();
        $this->assetRepository = new AssetRepository();
        $this->portfolioRepository = new PortfolioRepository();
        $this->transactionRepository = new TransactionRepository();
    }

    /**
     * Dodaje obligację ręcznie
     */
    public function add(): void
    {
        $this->requireAuth();

        if (!$this->isPost()) {
            $this->redirect('/import');
            return;
        }

        $userId = $this->getCurrentUserId();

        // Pobierz dane z formularza
        $portfolioId = (int) ($_POST['portfolio_id'] ?? 0);
        $bondCode = strtoupper(trim($_POST['bond_code'] ?? ''));
        $bondType = $_POST['bond_type'] ?? '';
        $issueDate = $_POST['issue_date'] ?? '';
        $maturityDate = $_POST['maturity_date'] ?? '';
        $firstYearRate = !empty($_POST['first_year_rate']) ? (float) $_POST['first_year_rate'] : null;
        $interestRate = !empty($_POST['interest_rate']) ? (float) $_POST['interest_rate'] : null;
        $interestMargin = !empty($_POST['interest_margin']) ? (float) $_POST['interest_margin'] : null;
        $interestFrequency = $_POST['interest_frequency'] ?? 'annual';
        $quantity = (float) ($_POST['quantity'] ?? 0);
        $price = (float) ($_POST['price'] ?? 0);
        $purchaseDate = $_POST['purchase_date'] ?? date('Y-m-d');

        // Walidacja
        $errors = [];

        if (!$portfolioId || !$this->portfolioRepository->belongsToUser($portfolioId, $userId)) {
            $errors[] = 'Nieprawidłowy portfel';
        }

        if (empty($bondCode)) {
            $errors[] = 'Kod obligacji jest wymagany';
        }

        if (empty($bondType)) {
            $errors[] = 'Typ obligacji jest wymagany';
        }

        if (empty($issueDate)) {
            $errors[] = 'Data emisji jest wymagana';
        }

        if (empty($maturityDate)) {
            $errors[] = 'Data wykupu jest wymagana';
        }

        // Walidacja zgodności typu z oprocentowaniem
        $fixedRateTypes = ['OTS', 'TOS'];
        $inflationLinkedTypes = ['COI', 'EDO', 'ROS', 'ROD'];
        $nbpRateLinkedTypes = ['ROR', 'DOR'];

        // OTS i TOS - tylko stała stopa
        if (in_array($bondType, $fixedRateTypes) && $interestRate === null) {
            $errors[] = 'Dla typu ' . $bondType . ' wymagana jest stała stopa procentowa';
        }

        // COI, EDO, ROS, ROD - pierwszy rok + marża
        if (in_array($bondType, $inflationLinkedTypes)) {
            if ($firstYearRate === null) {
                $errors[] = 'Dla typu ' . $bondType . ' wymagana jest stopa pierwszego roku';
            }
            if ($interestMargin === null) {
                $errors[] = 'Dla typu ' . $bondType . ' wymagana jest marża';
            }
        }

        // ROR, DOR - pierwszy okres + marża
        if (in_array($bondType, $nbpRateLinkedTypes)) {
            if ($firstYearRate === null) {
                $errors[] = 'Dla typu ' . $bondType . ' wymagana jest stopa pierwszego okresu';
            }
            if ($interestMargin === null) {
                $errors[] = 'Dla typu ' . $bondType . ' wymagana jest marża';
            }
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
            $db = Database::getInstance()->connect();
            $db->beginTransaction();

            // 1. Znajdź lub utwórz asset
            $asset = $this->assetRepository->findBySymbol($bondCode);

            if (!$asset) {
                // Utwórz nowy asset
                $bondName = $this->generateBondName($bondType, $bondCode);
                $asset = new Asset(
                    $bondCode,
                    $bondName,
                    'bond',
                    'PLN',
                    null
                );
                $assetId = $this->assetRepository->create($asset);
                $asset = $this->assetRepository->findById($assetId);
            } else {
                // Sprawdź czy to obligacja
                if (!$asset->isBond()) {
                    throw new Exception('Aktywo o tym symbolu już istnieje i nie jest obligacją');
                }
            }

            $assetId = $asset->getId();

            // 2. Sprawdź czy szczegóły obligacji już istnieją
            $existingBond = $this->bondRepository->findByAssetId($assetId);

            if (!$existingBond) {
                // Określ rate_base
                $rateBase = null;
                if (in_array($bondType, $inflationLinkedTypes)) {
                    $rateBase = 'inflation';
                } elseif (in_array($bondType, $nbpRateLinkedTypes)) {
                    $rateBase = 'nbp_rate';
                }

                // Utwórz nowe szczegóły obligacji
                $bond = new Bond(
                    $assetId,
                    $bondCode,
                    $bondType,
                    $issueDate,
                    $maturityDate,
                    $firstYearRate,
                    $interestRate,
                    $interestMargin,
                    $rateBase,
                    $interestFrequency,
                    100.00 // nominal value
                );
                $this->bondRepository->create($bond);
            }

            // 3. Dodaj transakcję zakupu
            $transaction = new Transaction(
                    $portfolioId,
                    $assetId,
                    'buy',
                    $quantity,
                    $price,
                    $purchaseDate,  // transactionDate
                    0.00           // commission
            );
            $this->transactionRepository->create($transaction);

            $db->commit();

            $_SESSION['import_success'] = 'Obligacja została dodana pomyślnie';

        } catch (Exception $e) {
            if (isset($db)) {
                $db->rollBack();
            }
            $_SESSION['import_error'] = 'Błąd: ' . $e->getMessage();
        }

        $this->redirect('/import');
    }

    /**
     * Generuje nazwę obligacji na podstawie typu i kodu
     */
    private function generateBondName(string $type, string $code): string
    {
        $names = [
            'OTS' => 'Obligacje 3-miesięczne',
            'ROR' => 'Obligacje roczne',
            'DOR' => 'Obligacje 2-letnie',
            'TOS' => 'Obligacje 3-letnie',
            'COI' => 'Obligacje 4-letnie indeksowane',
            'EDO' => 'Obligacje 10-letnie emerytalne',
            'ROS' => 'Obligacje 6-letnie rodzinne',
            'ROD' => 'Obligacje 12-letnie rodzinne'
        ];

        $baseName = $names[$type] ?? 'Obligacje';
        return $code . ' - ' . $baseName;
    }
}