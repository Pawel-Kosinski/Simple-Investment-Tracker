<?php

class Transaction {
    public const TYPE_BUY = 'buy';
    public const TYPE_SELL = 'sell';
    public const TYPE_DIVIDEND = 'dividend';

    private ?int $id;
    private int $userId;
    private int $assetId;
    private string $transactionType;
    private float $quantity;
    private float $price;
    private float $commission;
    private string $transactionDate;
    private ?string $notes;
    private ?string $createdAt;

    // Opcjonalnie załadowane relacje
    private ?Asset $asset = null;

    public function __construct(
        int $userId,
        int $assetId,
        string $transactionType,
        float $quantity,
        float $price,
        string $transactionDate,
        float $commission = 0,
        ?string $notes = null,
        ?int $id = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->assetId = $assetId;
        $this->transactionType = $transactionType;
        $this->quantity = $quantity;
        $this->price = $price;
        $this->commission = $commission;
        $this->transactionDate = $transactionDate;
        $this->notes = $notes;
        $this->createdAt = $createdAt;
    }

    public static function fromArray(array $data): Transaction
    {
        $transaction = new Transaction(
            $data['user_id'],
            $data['asset_id'],
            $data['transaction_type'],
            (float) $data['quantity'],
            (float) $data['price'],
            $data['transaction_date'],
            (float) ($data['commission'] ?? 0),
            $data['notes'] ?? null,
            $data['id'] ?? null,
            $data['created_at'] ?? null
        );

        // Jeśli dane zawierają informacje o aktywie, załaduj je
        if (isset($data['symbol'])) {
            $transaction->setAsset(Asset::fromArray($data));
        }

        return $transaction;
    }

    // Gettery
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAssetId(): int
    {
        return $this->assetId;
    }

    public function getTransactionType(): string
    {
        return $this->transactionType;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getCommission(): float
    {
        return $this->commission;
    }

    public function getTransactionDate(): string
    {
        return $this->transactionDate;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    // Settery
    public function setUserId(int $userId): void
    {
        $this->userId = $userId;
    }

    public function setAssetId(int $assetId): void
    {
        $this->assetId = $assetId;
    }

    public function setTransactionType(string $transactionType): void
    {
        $this->transactionType = $transactionType;
    }

    public function setQuantity(float $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function setPrice(float $price): void
    {
        $this->price = $price;
    }

    public function setCommission(float $commission): void
    {
        $this->commission = $commission;
    }

    public function setTransactionDate(string $transactionDate): void
    {
        $this->transactionDate = $transactionDate;
    }

    public function setNotes(?string $notes): void
    {
        $this->notes = $notes;
    }

    public function setAsset(?Asset $asset): void
    {
        $this->asset = $asset;
    }

    // Metody pomocnicze
    public function isBuy(): bool
    {
        return $this->transactionType === self::TYPE_BUY;
    }

    public function isSell(): bool
    {
        return $this->transactionType === self::TYPE_SELL;
    }

    public function isDividend(): bool
    {
        return $this->transactionType === self::TYPE_DIVIDEND;
    }

    public function getValue(): float
    {
        return $this->quantity * $this->price;
    }

    public function getTotalCost(): float
    {
        $value = $this->getValue();
        
        if ($this->isBuy()) {
            return $value + $this->commission;
        }
        
        // Dla sprzedaży prowizja zmniejsza przychód
        return $value - $this->commission;
    }

    public function getFormattedDate(string $format = 'Y-m-d H:i'): string
    {
        return date($format, strtotime($this->transactionDate));
    }

    // Zwraca typ transakcji w przyjaznej formie
    public function getTypeLabel(): string
    {
        return match($this->transactionType) {
            self::TYPE_BUY => 'Kupno',
            self::TYPE_SELL => 'Sprzedaż',
            self::TYPE_DIVIDEND => 'Dywidenda',
            default => $this->transactionType
        };
    }
}