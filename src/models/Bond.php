<?php

class Bond {
    private ?int $id;
    private int $assetId;
    private string $bondCode;
    private string $bondType;
    private string $issueDate;
    private string $maturityDate;
    private ?float $firstYearRate;     // oprocentowanie w pierwszym okresie
    private ?float $interestRate;      // stała stopa (dla OTS, TOS)
    private ?float $interestMargin;    // marża (dla COI, EDO, ROS, ROD, ROR, DOR)
    private ?string $rateBase;         // 'inflation' lub 'nbp_rate'
    private string $interestFrequency;
    private float $nominalValue;
    private ?string $createdAt;

    public function __construct(
        int $assetId,
        string $bondCode,
        string $bondType,
        string $issueDate,
        string $maturityDate,
        ?float $firstYearRate = null,
        ?float $interestRate = null,
        ?float $interestMargin = null,
        ?string $rateBase = null,
        string $interestFrequency = 'monthly',
        float $nominalValue = 100.00,
        ?int $id = null,
        ?string $createdAt = null
    ) {
        $this->id = $id;
        $this->assetId = $assetId;
        $this->bondCode = $bondCode;
        $this->bondType = $bondType;
        $this->issueDate = $issueDate;
        $this->maturityDate = $maturityDate;
        $this->firstYearRate = $firstYearRate;
        $this->interestRate = $interestRate;
        $this->interestMargin = $interestMargin;
        $this->rateBase = $rateBase;
        $this->interestFrequency = $interestFrequency;
        $this->nominalValue = $nominalValue;
        $this->createdAt = $createdAt;
    }

    public static function fromArray(array $data): Bond
    {
        return new Bond(
            $data['asset_id'],
            $data['bond_code'],
            $data['bond_type'],
            $data['issue_date'],
            $data['maturity_date'],
            $data['first_year_rate'] ?? null,
            $data['interest_rate'] ?? null,
            $data['interest_margin'] ?? null,
            $data['rate_base'] ?? null,
            $data['interest_frequency'] ?? 'monthly',
            $data['nominal_value'] ?? 100.00,
            $data['id'] ?? null,
            $data['created_at'] ?? null
        );
    }

    // Gettery
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAssetId(): int
    {
        return $this->assetId;
    }

    public function getBondCode(): string
    {
        return $this->bondCode;
    }

    public function getBondType(): string
    {
        return $this->bondType;
    }

    public function getIssueDate(): string
    {
        return $this->issueDate;
    }

    public function getMaturityDate(): string
    {
        return $this->maturityDate;
    }

    public function getFirstYearRate(): ?float
    {
        return $this->firstYearRate;
    }

    public function getInterestRate(): ?float
    {
        return $this->interestRate;
    }

    public function getInterestMargin(): ?float
    {
        return $this->interestMargin;
    }

    public function getInterestFrequency(): string
    {
        return $this->interestFrequency;
    }

    public function getRateBase(): ?string
    {
        return $this->rateBase;
    }

    public function getNominalValue(): float
    {
        return $this->nominalValue;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // Settery
    public function setBondCode(string $bondCode): void
    {
        $this->bondCode = $bondCode;
    }

    public function setBondType(string $bondType): void
    {
        $this->bondType = $bondType;
    }

    public function setIssueDate(string $issueDate): void
    {
        $this->issueDate = $issueDate;
    }

    public function setMaturityDate(string $maturityDate): void
    {
        $this->maturityDate = $maturityDate;
    }

    public function setFirstYearRate(?float $firstYearRate): void
    {
        $this->firstYearRate = $firstYearRate;
    }

    public function setInterestRate(?float $interestRate): void
    {
        $this->interestRate = $interestRate;
    }

    public function setInterestMargin(?float $interestMargin): void
    {
        $this->interestMargin = $interestMargin;
    }

    public function setInterestFrequency(string $interestFrequency): void
    {
        $this->interestFrequency = $interestFrequency;
    }

    public function setRateBase(?string $rateBase): void
    {
        $this->rateBase = $rateBase;
    }

    public function setNominalValue(float $nominalValue): void
    {
        $this->nominalValue = $nominalValue;
    }

    // Metody pomocnicze
    public function isFixedRate(): bool
    {
        return in_array($this->bondType, ['OTS', 'TOS']);
    }

    public function isInflationLinked(): bool
    {
        return in_array($this->bondType, ['COI', 'EDO', 'ROS', 'ROD']);
    }

    public function isNbpRateLinked(): bool
    {
        return in_array($this->bondType, ['ROR', 'DOR']);
    }

    public function hasCapitalization(): bool
    {
        return in_array($this->bondType, ['EDO', 'ROD']);
    }

    public function getInterestPaymentsPerYear(): int
    {
        // EDO i ROD mają kapitalizację - wypłata na koniec
        if ($this->hasCapitalization()) {
            return 0; // wypłata przy wykupie
        }
        
        return match($this->interestFrequency) {
            'monthly' => 12,
            'quarterly' => 4,
            'semi-annual' => 2,
            'annual' => 1,
            default => 12
        };
    }

    public function getDaysToMaturity(): int
    {
        $maturity = new DateTime($this->maturityDate);
        $now = new DateTime();
        $diff = $now->diff($maturity);
        return (int)$diff->format('%r%a');
    }

    public function isMatured(): bool
    {
        return $this->getDaysToMaturity() <= 0;
    }

    public function getBondTypeDisplayName(): string
    {
        return match($this->bondType) {
            'OTS' => 'Obligacje 3-miesięczne (OTS)',
            'ROR' => 'Obligacje roczne (ROR)',
            'DOR' => 'Obligacje 2-letnie (DOR)',
            'TOS' => 'Obligacje 3-letnie (TOS)',
            'COI' => 'Obligacje 4-letnie indeksowane (COI)',
            'EDO' => 'Obligacje 10-letnie emerytalne (EDO)',
            'ROS' => 'Obligacje 6-letnie rodzinne (ROS)',
            'ROD' => 'Obligacje 12-letnie rodzinne (ROD)',
            default => $this->bondType
        };
    }
}
