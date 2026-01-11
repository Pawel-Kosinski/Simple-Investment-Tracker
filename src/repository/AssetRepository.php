<?php

require_once 'Repository.php';
require_once __DIR__ . '/../models/Asset.php';

class AssetRepository extends Repository
{
    public function findAll(): array
    {
        $stmt = $this->database->prepare('SELECT * FROM assets ORDER BY symbol');
        $stmt->execute();

        $assets = [];
        while ($row = $stmt->fetch()) {
            $assets[] = Asset::fromArray($row);
        }

        return $assets;
    }

    public function findById(int $id): ?Asset
    {
        $stmt = $this->database->prepare('SELECT * FROM assets WHERE id = :id');
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? Asset::fromArray($row) : null;
    }

    public function findBySymbol(string $symbol): ?Asset
    {
        $stmt = $this->database->prepare('SELECT * FROM assets WHERE symbol = :symbol');
        $stmt->bindParam(':symbol', $symbol);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? Asset::fromArray($row) : null;
    }

    public function findByYahooSymbol(string $yahooSymbol): ?Asset
    {
        $stmt = $this->database->prepare('SELECT * FROM assets WHERE yahoo_symbol = :yahoo_symbol');
        $stmt->bindParam(':yahoo_symbol', $yahooSymbol);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? Asset::fromArray($row) : null;
    }

    public function findByType(string $assetType): array
    {
        $stmt = $this->database->prepare('SELECT * FROM assets WHERE asset_type = :asset_type ORDER BY symbol');
        $stmt->bindParam(':asset_type', $assetType);
        $stmt->execute();

        $assets = [];
        while ($row = $stmt->fetch()) {
            $assets[] = Asset::fromArray($row);
        }

        return $assets;
    }

    public function search(string $query): array
    {
        $searchQuery = '%' . strtoupper($query) . '%';
        $stmt = $this->database->prepare('
            SELECT * FROM assets 
            WHERE UPPER(symbol) LIKE :query 
               OR UPPER(name) LIKE :query
            ORDER BY symbol
            LIMIT 20
        ');
        $stmt->bindParam(':query', $searchQuery);
        $stmt->execute();

        $assets = [];
        while ($row = $stmt->fetch()) {
            $assets[] = Asset::fromArray($row);
        }

        return $assets;
    }

    public function create(Asset $asset): int
    {
        $stmt = $this->database->prepare('
            INSERT INTO assets (symbol, name, asset_type, currency, yahoo_symbol)
            VALUES (:symbol, :name, :asset_type, :currency, :yahoo_symbol)
        ');

        $stmt->execute([
            'symbol' => $asset->getSymbol(),
            'name' => $asset->getName(),
            'asset_type' => $asset->getAssetType(),
            'currency' => $asset->getCurrency(),
            'yahoo_symbol' => $asset->getYahooSymbol()
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function update(Asset $asset): bool
    {
        $stmt = $this->database->prepare('
            UPDATE assets 
            SET symbol = :symbol,
                name = :name,
                asset_type = :asset_type,
                currency = :currency,
                yahoo_symbol = :yahoo_symbol
            WHERE id = :id
        ');

        return $stmt->execute([
            'id' => $asset->getId(),
            'symbol' => $asset->getSymbol(),
            'name' => $asset->getName(),
            'asset_type' => $asset->getAssetType(),
            'currency' => $asset->getCurrency(),
            'yahoo_symbol' => $asset->getYahooSymbol()
        ]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->database->prepare('DELETE FROM assets WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    // Znajduje lub tworzy aktywo na podstawie symbolu
    public function findOrCreate(string $symbol, ?string $name = null, string $assetType = 'stock', string $currency = 'PLN'): Asset
    {
        $asset = $this->findBySymbol($symbol);
        
        if ($asset === null) {
            $newAsset = new Asset($symbol, $name, $assetType, $currency);
            $id = $this->create($newAsset);
            $asset = $this->findById($id);
        }

        return $asset;
    }

    //Pobiera aktywa które użytkownik posiada (ma transakcje)
    public function findByUserId(int $userId): array
    {
        $stmt = $this->database->prepare('
            SELECT DISTINCT a.* 
            FROM assets a
            INNER JOIN transactions t ON t.asset_id = a.id
            INNER JOIN portfolios p ON t.portfolio_id = p.id
            WHERE p.user_id = :user_id
            ORDER BY a.symbol
        ');
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $assets = [];
        while ($row = $stmt->fetch()) {
            $assets[] = Asset::fromArray($row);
        }

        return $assets;
    }
}