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

    public function findByUserId(int $userId, ?int $limit = null, int $offset = 0): array
    {
        $sql = '
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.user_id = :user_id
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

    public function findByUserAndAsset(int $userId, int $assetId): array
    {
        $stmt = $this->database->prepare('
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.user_id = :user_id AND t.asset_id = :asset_id
            ORDER BY t.transaction_date ASC
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':asset_id', $assetId, PDO::PARAM_INT);
        $stmt->execute();

        $transactions = [];
        while ($row = $stmt->fetch()) {
            $transactions[] = Transaction::fromArray($row);
        }

        return $transactions;
    }

    public function findByDateRange(int $userId, string $startDate, string $endDate): array
    {
        $stmt = $this->database->prepare('
            SELECT t.*, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.user_id = :user_id 
              AND t.transaction_date >= :start_date 
              AND t.transaction_date <= :end_date
            ORDER BY t.transaction_date DESC
        ');
        $stmt->execute([
            'user_id' => $userId,
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
            INSERT INTO transactions (user_id, asset_id, transaction_type, quantity, price, commission, transaction_date, notes)
            VALUES (:user_id, :asset_id, :transaction_type, :quantity, :price, :commission, :transaction_date, :notes)
        ');

        $stmt->execute([
            'user_id' => $transaction->getUserId(),
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
            WHERE id = :id AND user_id = :user_id
        ');

        return $stmt->execute([
            'id' => $transaction->getId(),
            'user_id' => $transaction->getUserId(),
            'asset_id' => $transaction->getAssetId(),
            'transaction_type' => $transaction->getTransactionType(),
            'quantity' => $transaction->getQuantity(),
            'price' => $transaction->getPrice(),
            'commission' => $transaction->getCommission(),
            'transaction_date' => $transaction->getTransactionDate(),
            'notes' => $transaction->getNotes()
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->database->prepare('DELETE FROM transactions WHERE id = :id AND user_id = :user_id');
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function deleteByUserId(int $userId): int
    {
        $stmt = $this->database->prepare('DELETE FROM transactions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->rowCount();
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->database->prepare('SELECT COUNT(*) FROM transactions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    //Pobiera podsumowanie holdings dla użytkownika (ilość posiadanych aktywów)
    public function getHoldingsSummary(int $userId): array
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
                COUNT(*) as transaction_count
            FROM transactions t
            INNER JOIN assets a ON t.asset_id = a.id
            WHERE t.user_id = :user_id
            GROUP BY a.id, a.symbol, a.name, a.asset_type, a.currency, a.yahoo_symbol
            HAVING SUM(CASE WHEN t.transaction_type = \'buy\' THEN t.quantity ELSE -t.quantity END) > 0
            ORDER BY a.symbol
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    //Pobiera statystyki portfela
    public function getPortfolioStats(int $userId): array
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
            WHERE user_id = :user_id
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch() ?: [];
    }
}