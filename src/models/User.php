<?php

class User {
    private ?int $id;
    private string $email;
    private string $password;
    private string $firstname;
    private string $lastname;
    private ?string $createdAt;
    private bool $enabled;

    public function __construct(
        string $email,
        string $password,
        string $firstname,
        string $lastname,
        ?int $id = null,
        ?string $createdAt = null,
        bool $enabled = true
    ) {
        $this->id = $id;
        $this->email = $email;
        $this->password = $password;
        $this->firstname = $firstname;
        $this->lastname = $lastname;
        $this->createdAt = $createdAt;
        $this->enabled = $enabled;
    }

    public static function fromArray(array $data): User
    {
        return new User(
            $data['email'],
            $data['password'],
            $data['firstname'],
            $data['lastname'],
            $data['id'] ?? null,
            $data['created_at'] ?? null,
            $data['enabled'] ?? true
        );
    }

    // Gettery
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function getLastname(): string
    {
        return $this->lastname;
    }

    public function getFullName(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // Settery
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function setFirstname(string $firstname): void
    {
        $this->firstname = $firstname;
    }

    public function setLastname(string $lastname): void
    {
        $this->lastname = $lastname;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}
