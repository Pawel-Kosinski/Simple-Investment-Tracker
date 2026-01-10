<?php

require_once 'Repository.php';
require_once __DIR__ . '/../models/Transaction.php';
require_once __DIR__ . '/../models/Asset.php';

class TransactionRepository extends Repository
{
    public function findById(int $id): ?Transaction
    {
        $stmt = $this->database->prepare('
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.id = :id
        ');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? Transaction::fromArray($row) : null;
    }

    public function findByPortfolioId(int $portfolioId, ?int $limit = null, int $offset = 0): array
    {
        $sql = '
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.portfolio_id = :portfolio_id
            ORDER BY t.transaction_date DESC
        ';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->database->prepare($sql);
        $stmt->bindParam(':portfolio_id', $portfolioId, PDO::PARAM_INT);

        if ($limit !== null) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch()) {
            $transactions[] = Transaction::fromArray($row);
        }

        return $transactions;
    }

    /**
     * Pobiera transakcje dla wszystkich portfeli użytkownika
     */
    public function findByUserId(int $userId, ?int $limit = null, int $offset = 0): array
    {
        $sql = '
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol, p.name as portfolio_name
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            INNER JOIN portfolios p ON t.portfolio_id = p.id
            WHERE p.user_id = :user_id
            ORDER BY t.transaction_date DESC
        ';

        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->database->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);

        if ($limit !== null) {
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        }

        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch()) {
            $transactions[] = Transaction::fromArray($row);
        }

        return $transactions;
    }

    public function findByPortfolioAndAsset(int $portfolioId, int $assetId): array
    {
        $stmt = $this->database->prepare('
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.portfolio_id = :portfolio_id AND t.asset_id = :asset_id
            ORDER BY t.transaction_date ASC
        ');
        $stmt->bindParam(':portfolio_id', $portfolioId, PDO::PARAM_INT);
        $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch()) {
            $transactions[] = Transaction::fromArray($row);
        }

        return $transactions;
    }

    public function findByDateRange(int $portfolioId, string $startDate, string $endDate): array
    {
        $stmt = $this->database->prepare('
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.portfolio_id = :portfolio_id 
              AND t.transaction_date >= :start_date 
              AND t.transaction_date <= :end_date
            ORDER BY t.transaction_date DESC
        ');
        $stmt->execute([
            'portfolio_id' => $portfolioId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        $transactions = [];
        while ($row = $stmt->fetch()) {
            $transactions[] = Transaction::fromArray($row);
        }

        return $transactions;
    }

    public function create(Transaction $transaction): int
    {
        $stmt = $this->database->prepare('
            INSERT INTO transactions (portfolio_id, asset_id, transaction_type, quantity, price, commission, transaction_date, notes)
            VALUES (:portfolio_id, :asset_id, :transaction_type, :quantity, :price, :commission, :transaction_date, :notes)
        ');

        $stmt->execute([
            'portfolio_id' => $transaction->getPortfolioId(),
            'asset_id' => $transaction->getAssetId(),
            'transaction_type' => $transaction->getTransactionType(),
            'quantity' => $transaction->getQuantity(),
            'price' => $transaction->getPrice(),
            'commission' => $transaction->getCommission(),
            'transaction_date' => $transaction->getTransactionDate(),
            'notes' => $transaction->getNotes()
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function createMany(array $transactions): int
    {
        $count = 0;
        $this->database->beginTransaction();

        try {
            foreach ($transactions as $transaction) {
                $this->create($transaction);
                $count++;
            }
            $this->database->commit();
        } catch (Exception $e) {
            $this->database->rollBack();
            throw $e;
        }

        return $count;
    }

    public function update(Transaction $transaction): bool
    {
        $stmt = $this->database->prepare('
            UPDATE transactions 
            SET asset_id = :asset_id,
                transaction_type = :transaction_type,
                quantity = :quantity,
                price = :price,
                commission = :commission,
                transaction_date = :transaction_date,
                notes = :notes
            WHERE id = :id AND portfolio_id = :portfolio_id
        ');

        return $stmt->execute([
            'id' => $transaction->getId(),
            'portfolio_id' => $transaction->getPortfolioId(),
            'asset_id' => $transaction->getAssetId(),
            'transaction_type' => $transaction->getTransactionType(),
            'quantity' => $transaction->getQuantity(),
            'price' => $transaction->getPrice(),
            'commission' => $transaction->getCommission(),
            'transaction_date' => $transaction->getTransactionDate(),
            'notes' => $transaction->getNotes()
        ]);
    }

    public function delete(int $id, int $portfolioId): bool
    {
        $stmt = $this->database->prepare('DELETE FROM transactions WHERE id = :id AND portfolio_id = :portfolio_id');
        return $stmt->execute(['id' => $id, 'portfolio_id' => $portfolioId]);
    }

    public function deleteByPortfolioId(int $portfolioId): int
    {
        $stmt = $this->database->prepare('DELETE FROM transactions WHERE portfolio_id = :portfolio_id');
        $stmt->execute(['portfolio_id' => $portfolioId]);
        return $stmt->rowCount();
    }

    public function countByPortfolioId(int $portfolioId): int
    {
        $stmt = $this->database->prepare('SELECT COUNT(*) FROM transactions WHERE portfolio_id = :portfolio_id');
        $stmt->execute(['portfolio_id' => $portfolioId]);
        return (int) $stmt->fetchColumn();
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->database->prepare('
            SELECT COUNT(*) FROM transactions t
            INNER JOIN portfolios p ON t.portfolio_id = p.id
            WHERE p.user_id = :user_id
        ');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Pobiera podsumowanie holdings dla portfela
     */
    public function getHoldingsSummary(int $portfolioId): array
    {
        $stmt = $this->database->prepare('
            SELECT 
                a.id as asset_id,
                a.symbol,
                a.name,
                a.asset_type,
                a.currency,
                a.yahoo_symbol,
                SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity ELSE 0 END) as total_bought,
                SUM(CASE WHEN t.transaction_type = \'sell\' THEN t.quantity ELSE 0 END) as total_sold,
                SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity * t.price ELSE 0 END) as total_cost,
                SUM(CASE WHEN t.transaction_type = \'sell\' THEN t.quantity * t.price ELSE 0 END) as total_revenue,
                SUM(t.commission) as total_commission,
                COUNT(*) as transaction_count,
                MIN(CASE WHEN t.transaction_type = \'buy\' THEN t.transaction_date END) as first_purchase_date,
                b.first_year_rate,
                b.interest_rate,
                b.interest_margin,
                b.rate_base,
                b.bond_type,
                b.issue_date
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            LEFT JOIN bonds b ON a.id = b.asset_id
            WHERE t.portfolio_id = :portfolio_id
            GROUP BY a.id, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol,
                     b.first_year_rate, b.interest_rate, b.interest_margin, b.rate_base,
                     b.bond_type, b.issue_date
            HAVING SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity ELSE -t.quantity END) > 0
            ORDER BY a.symbol
        ');
        $stmt->bindParam(':portfolio_id', $portfolioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Pobiera podsumowanie holdings dla wszystkich portfeli użytkownika
     */
    public function getHoldingsSummaryByUserId(int $userId): array
    {
        $stmt = $this->database->prepare('
            SELECT 
                a.id as asset_id,
                a.symbol,
                a.name,
                a.asset_type,
                a.currency,
                a.yahoo_symbol,
                SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity ELSE 0 END) as total_bought,
                SUM(CASE WHEN t.transaction_type = \'sell\' THEN t.quantity ELSE 0 END) as total_sold,
                SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity * t.price ELSE 0 END) as total_cost,
                SUM(CASE WHEN t.transaction_type = \'sell\' THEN t.quantity * t.price ELSE 0 END) as total_revenue,
                SUM(t.commission) as total_commission,
                COUNT(*) as transaction_count,
                MIN(CASE WHEN t.transaction_type = \'buy\' THEN t.transaction_date END) as first_purchase_date,
                b.first_year_rate,
                b.interest_rate,
                b.interest_margin,
                b.rate_base,
                b.bond_type,
                b.issue_date
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            INNER JOIN portfolios p ON t.portfolio_id = p.id
            LEFT JOIN bonds b ON a.id = b.asset_id
            WHERE p.user_id = :user_id
            GROUP BY a.id, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol,
                     b.first_year_rate, b.interest_rate, b.interest_margin, b.rate_base,
                     b.bond_type, b.issue_date
            HAVING SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity ELSE -t.quantity END) > 0
            ORDER BY a.symbol
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Pobiera statystyki portfela
     */
    public function getPortfolioStats(int $portfolioId): array
    {
        $stmt = $this->database->prepare('
            SELECT 
                COUNT(DISTINCT asset_id) as unique_assets,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN transaction_type = \'buy\' THEN quantity * price + commission ELSE 0 END) as total_invested,
                SUM(CASE WHEN transaction_type = \'sell\' THEN quantity * price - commission ELSE 0 END) as total_withdrawn,
                SUM(CASE WHEN transaction_type = \'dividend\' THEN quantity * price ELSE 0 END) as total_dividends,
                MIN(transaction_date) as first_transaction,
                MAX(transaction_date) as last_transaction
            FROM transactions
            WHERE portfolio_id = :portfolio_id
        ');
        $stmt->bindParam(':portfolio_id', $portfolioId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    /**
     * Pobiera statystyki dla wszystkich portfeli użytkownika
     */
    public function getStatsByUserId(int $userId): array
    {
        $stmt = $this->database->prepare('
            SELECT 
                COUNT(DISTINCT t.asset_id) as unique_assets,
                COUNT(*) as total_transactions,
                SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity * t.price + t.commission ELSE 0 END) as total_invested,
                SUM(CASE WHEN t.transaction_type = \'sell\' THEN t.quantity * t.price - t.commission ELSE 0 END) as total_withdrawn,
                SUM(CASE WHEN t.transaction_type = \'dividend\' THEN t.quantity * t.price ELSE 0 END) as total_dividends,
                MIN(t.transaction_date) as first_transaction,
                MAX(t.transaction_date) as last_transaction
            FROM transactions t
            INNER JOIN portfolios p ON t.portfolio_id = p.id
            WHERE p.user_id = :user_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }

    /**
     * Pobiera transakcje dla użytkownika z filtrami
     */
    public function getTransactionsWithFilters(int $userId, ?int $portfolioId = null, string $typeFilter = 'all'): array
    {
        $sql = '
            SELECT 
                t.*,
                a.symbol,
                a.name as asset_name,
                a.currency,
                p.name as portfolio_name
            FROM transactions t
            JOIN assets a ON t.asset_id = a.id
            JOIN portfolios p ON t.portfolio_id = p.id
            WHERE p.user_id = :user_id
        ';
        
        $params = ['user_id' => $userId];
        
        // Filtr portfela
        if ($portfolioId) {
            $sql .= ' AND t.portfolio_id = :portfolio_id';
            $params['portfolio_id'] = $portfolioId;
        }
        
        // Filtr typu
        if ($typeFilter !== 'all') {
            $sql .= ' AND t.transaction_type = :type';
            $params['type'] = $typeFilter;
        }
        
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';
        
        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Pobiera transakcję z weryfikacją własności użytkownika
     */
    public function getTransactionWithOwnership(int $transactionId): ?array
    {
        $stmt = $this->database->prepare('
            SELECT t.*, p.user_id 
            FROM transactions t
            JOIN portfolios p ON t.portfolio_id = p.id
            WHERE t.id = :id
        ');
        $stmt->execute(['id' => $transactionId]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Usuwa wpis z import_history dla operacji XTB
     */
    public function deleteImportHistory(int $userId, int $xtbId): bool
    {
        $stmt = $this->database->prepare('
            DELETE FROM import_history 
            WHERE user_id = :user_id AND xtb_operation_id = :xtb_id
        ');
        return $stmt->execute(['user_id' => $userId, 'xtb_id' => $xtbId]);
    }

    /**
     * Usuwa transakcję po ID
     */
    public function deleteById(int $transactionId): bool
    {
        $stmt = $this->database->prepare('DELETE FROM transactions WHERE id = :id');
        return $stmt->execute(['id' => $transactionId]);
    }
}