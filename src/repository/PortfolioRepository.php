<?php

require_once 'Repository.php';
require_once __DIR__ . '/../models/Portfolio.php';

class PortfolioRepository extends Repository
{
    public function findById(int $id): ?Portfolio
    {
        $stmt = $this->database->prepare('SELECT * FROM portfolios WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? Portfolio::fromArray($row) : null;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->database->prepare('
            SELECT * FROM portfolios 
            WHERE user_id = :user_id 
            ORDER BY is_default DESC, name ASC
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $portfolios = [];
        while ($row = $stmt->fetch()) {
            $portfolios[] = Portfolio::fromArray($row);
        }

        return $portfolios;
    }

    public function findDefaultByUserId(int $userId): ?Portfolio
    {
        $stmt = $this->database->prepare('
            SELECT * FROM portfolios 
            WHERE user_id = :user_id AND is_default = TRUE
            LIMIT 1
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? Portfolio::fromArray($row) : null;
    }

    public function create(Portfolio $portfolio): int
    {
        // Jeśli to domyślny portfel, usuń flagę z innych
        if ($portfolio->isDefault()) {
            $this->clearDefaultFlag($portfolio->getUserId());
        }

        $stmt = $this->database->prepare('
            INSERT INTO portfolios (user_id, name, description, is_default)
            VALUES (:user_id, :name, :description, :is_default)
        ');

        $stmt->execute([
            'user_id' => $portfolio->getUserId(),
            'name' => $portfolio->getName(),
            'description' => $portfolio->getDescription(),
            'is_default' => $portfolio->isDefault() ? 't' : 'f'
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function update(Portfolio $portfolio): bool
    {
        // Jeśli ustawiamy jako domyślny, usuń flagę z innych
        if ($portfolio->isDefault()) {
            $this->clearDefaultFlag($portfolio->getUserId(), $portfolio->getId());
        }

        $stmt = $this->database->prepare('
            UPDATE portfolios 
            SET name = :name,
                description = :description,
                is_default = :is_default
            WHERE id = :id AND user_id = :user_id
        ');

        return $stmt->execute([
            'id' => $portfolio->getId(),
            'user_id' => $portfolio->getUserId(),
            'name' => $portfolio->getName(),
            'description' => $portfolio->getDescription(),
            'is_default' => $portfolio->isDefault() ? 't' : 'f'
        ]);
    }

    public function delete(int $id, int $userId): bool
    {
        $stmt = $this->database->prepare('
            DELETE FROM portfolios WHERE id = :id AND user_id = :user_id
        ');
        return $stmt->execute(['id' => $id, 'user_id' => $userId]);
    }

    public function countByUserId(int $userId): int
    {
        $stmt = $this->database->prepare('SELECT COUNT(*) FROM portfolios WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    //Sprawdza czy portfel należy do użytkownika
    public function belongsToUser(int $portfolioId, int $userId): bool
    {
        $stmt = $this->database->prepare('
            SELECT COUNT(*) FROM portfolios WHERE id = :id AND user_id = :user_id
        ');
        $stmt->execute(['id' => $portfolioId, 'user_id' => $userId]);
        return $stmt->fetchColumn() > 0;
    }

    //Tworzy domyślny portfel dla nowego użytkownika/
    public function createDefaultForUser(int $userId): int
    {
        $portfolio = new Portfolio($userId, 'Główny', 'Domyślny portfel', true);
        return $this->create($portfolio);
    }

    //Usuwa flagę is_default z wszystkich portfeli użytkownika
    private function clearDefaultFlag(int $userId, ?int $excludeId = null): void
    {
        $sql = 'UPDATE portfolios SET is_default = FALSE WHERE user_id = :user_id';
        $params = ['user_id' => $userId];

        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->database->prepare($sql);
        $stmt->execute($params);
    }
}