<?php

require_once 'Repository.php';
require_once __DIR__ . '/../models/Bond.php';

class BondRepository extends Repository {

    /**
     * Zapisuje nową obligację
     */
    public function create(Bond $bond): ?int
    {
        $stmt = $this->database->prepare('
            INSERT INTO bonds (asset_id, bond_code, bond_type, issue_date, maturity_date, 
                             first_year_rate, interest_rate, interest_margin, rate_base, 
                             interest_frequency, nominal_value)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ');
        
        $stmt->execute([
            $bond->getAssetId(),
            $bond->getBondCode(),
            $bond->getBondType(),
            $bond->getIssueDate(),
            $bond->getMaturityDate(),
            $bond->getFirstYearRate(),
            $bond->getInterestRate(),
            $bond->getInterestMargin(),
            $bond->getRateBase(),
            $bond->getInterestFrequency(),
            $bond->getNominalValue()
        ]);

        $id = $this->database->lastInsertId();
        return $id ? (int)$id : null;
    }

    /**
     * Znajduje obligację po ID
     */
    public function findById(int $id): ?Bond
    {
        $stmt = $this->database->prepare('
            SELECT * FROM bonds WHERE id = ?
        ');
        $stmt->execute([$id]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Bond::fromArray($data) : null;
    }

    /**
     * Znajduje obligację po asset_id
     */
    public function findByAssetId(int $assetId): ?Bond
    {
        $stmt = $this->database->prepare('
            SELECT * FROM bonds WHERE asset_id = ?
        ');
        $stmt->execute([$assetId]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Bond::fromArray($data) : null;
    }

    /**
     * Znajduje obligację po kodzie
     */
    public function findByBondCode(string $bondCode): ?Bond
    {
        $stmt = $this->database->prepare('
            SELECT * FROM bonds WHERE bond_code = ?
        ');
        $stmt->execute([$bondCode]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? Bond::fromArray($data) : null;
    }

    /**
     * Pobiera wszystkie obligacje
     */
    public function findAll(): array
    {
        $stmt = $this->database->prepare('
            SELECT b.*, a.symbol, a.name
            FROM bonds b
            JOIN assets a ON b.asset_id = a.id
            ORDER BY b.maturity_date DESC
        ');
        $stmt->execute();

        $bonds = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bonds[] = Bond::fromArray($row);
        }

        return $bonds;
    }

    /**
     * Aktualizuje obligację
     */
    public function update(Bond $bond): bool
    {
        $stmt = $this->database->prepare('
            UPDATE bonds 
            SET bond_code = ?, 
                bond_type = ?, 
                issue_date = ?, 
                maturity_date = ?, 
                first_year_rate = ?,
                interest_rate = ?, 
                interest_margin = ?,
                rate_base = ?,
                interest_frequency = ?,
                nominal_value = ?
            WHERE id = ?
        ');

        return $stmt->execute([
            $bond->getBondCode(),
            $bond->getBondType(),
            $bond->getIssueDate(),
            $bond->getMaturityDate(),
            $bond->getFirstYearRate(),
            $bond->getInterestRate(),
            $bond->getInterestMargin(),
            $bond->getRateBase(),
            $bond->getInterestFrequency(),
            $bond->getNominalValue(),
            $bond->getId()
        ]);
    }

    /**
     * Usuwa obligację
     */
    public function delete(int $id): bool
    {
        $stmt = $this->database->prepare('
            DELETE FROM bonds WHERE id = ?
        ');
        return $stmt->execute([$id]);
    }

    /**
     * Pobiera obligacje dla użytkownika (przez portfele)
     */
    public function findByUserId(int $userId): array
    {
        $stmt = $this->database->prepare('
            SELECT DISTINCT b.*, a.symbol, a.name, a.currency
            FROM bonds b
            JOIN assets a ON b.asset_id = a.id
            JOIN transactions t ON t.asset_id = a.id
            JOIN portfolios p ON t.portfolio_id = p.id
            WHERE p.user_id = ? AND a.asset_type = \'bond\'
            ORDER BY b.maturity_date DESC
        ');
        $stmt->execute([$userId]);

        $bonds = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $bonds[] = Bond::fromArray($row);
        }

        return $bonds;
    }

    /**
     * Sprawdza czy obligacja o danym kodzie już istnieje
     */
    public function exists(string $bondCode): bool
    {
        $stmt = $this->database->prepare('
            SELECT COUNT(*) FROM bonds WHERE bond_code = ?
        ');
        $stmt->execute([$bondCode]);
        return $stmt->fetchColumn() > 0;
    }
}
