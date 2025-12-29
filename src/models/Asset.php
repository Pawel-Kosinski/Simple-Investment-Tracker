<?php

class Asset {
    private ?int $id;
    private string $symbol;
    private ?string $name;
    private string $assetType;
    private string $currency;
    private ?string $yahooSymbol;
    private ?string $createdAt;

    public function __construct(
        string $symbol,
        ?string $name = null,
        string $assetType = 'stock',
        string $currency = 'PLN',
        ?string $yahooSymbol = null,
        ?int $id = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->symbol = $symbol;
        $this->name = $name;
        $this->assetType = $assetType;
        $this->currency = $currency;
        $this->yahooSymbol = $yahooSymbol;
        $this->createdAt = $createdAt;
    }

    public static function fromArray(array $data): Asset
    {
        return new Asset(
            $data['symbol'],
            $data['name'] ?? null,
            $data['asset_type'] ?? 'stock',
            $data['currency'] ?? 'PLN',
            $data['yahoo_symbol'] ?? null,
            $data['id'] ?? null,
            $data['created_at'] ?? null
        );
    }

    // Gettery
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getDisplayName(): string
    {
        return $this->name ?? $this->symbol;
    }

    public function getAssetType(): string
    {
        return $this->assetType;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getYahooSymbol(): ?string
    {
        return $this->yahooSymbol;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // Settery
    public function setSymbol(string $symbol): void
    {
        $this->symbol = $symbol;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function setAssetType(string $assetType): void
    {
        $this->assetType = $assetType;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function setYahooSymbol(?string $yahooSymbol): void
    {
        $this->yahooSymbol = $yahooSymbol;
    }

    // Pomocnicze
    public function isStock(): bool
    {
        return $this->assetType === 'stock';
    }

    public function isEtf(): bool
    {
        return $this->assetType === 'etf';
    }

    public function isCrypto(): bool
    {
        return $this->assetType === 'crypto';
    }

    public function isBond(): bool
    {
        return $this->assetType === 'bond';
    }
}