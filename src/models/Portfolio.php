<?php

class Portfolio {
    private ?int $id;
    private int $userId;
    private string $name;
    private ?string $description;
    private bool $isDefault;
    private ?string $createdAt;

    public function __construct(
        int $userId,
        string $name,
        ?string $description = null,
        bool $isDefault = false,
        ?int $id = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->userId = $userId;
        $this->name = $name;
        $this->description = $description;
        $this->isDefault = $isDefault;
        $this->createdAt = $createdAt;
    }

    public static function fromArray(array $data): Portfolio
    {
        return new Portfolio(
            $data['user_id'],
            $data['name'],
            $data['description'] ?? null,
            (bool) ($data['is_default'] ?? false),
            $data['id'] ?? null,
            $data['created_at'] ?? null
        );
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

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // Settery
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setIsDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
    }
}