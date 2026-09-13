<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * The business a file belongs to - legal name, ABN, address, tax registration, year end.
 *
 * `Zone` AND `CurrencyCode` ARE FIXED when the file is created and cannot be changed through
 * this or anything else. The ABN - `BusinessIdentifier` - is required for an Australian file.
 *
 * The year-end fields are a month and a DAY of that month, which Saasu names `...Date`.
 */
final class FileIdentity extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $fullLegalName = null,
        public readonly ?string $tradingName = null,
        public readonly ?int $industryTypeId = null,
        public readonly ?string $businessIdentifier = null,
        public readonly ?int $businessIdentifierBranchNumber = null,
        public readonly ?string $companyIdentifier = null,
        public readonly ?string $primaryPhone = null,
        public readonly ?string $fax = null,
        public readonly ?string $website = null,
        public readonly ?string $email = null,
        public readonly ?string $street = null,
        public readonly ?string $city = null,
        public readonly ?string $state = null,
        public readonly ?string $postCode = null,
        public readonly ?string $country = null,
        public readonly ?string $zone = null,
        public readonly ?string $currencyCode = null,
        public readonly ?FileSettings $fileSettings = null,
        public readonly ?bool $isTaxRegistered = null,
        public readonly ?\DateTimeImmutable $taxRegistrationDate = null,
        public readonly ?int $financialYearEndMonth = null,
        public readonly ?int $financialYearEndDay = null,
        public readonly ?int $payrollYearEndMonth = null,
        public readonly ?int $payrollYearEndDay = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?int $lastModifiedByUserId = null,
        public readonly ?\DateTimeImmutable $createdDateUtc = null,
        public readonly ?\DateTimeImmutable $lastModifiedDateUtc = null,
        array $raw = [],
    ) {
        parent::__construct($raw);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): static
    {
        return new self(
            Cast::text($row['Name'] ?? null),
            Cast::text($row['FullLegalName'] ?? null),
            Cast::text($row['TradingNameOrAlternativeBrandName'] ?? null),
            Cast::int($row['IndustryTypeId'] ?? null),
            Cast::text($row['BusinessIdentifier'] ?? null),
            Cast::int($row['BusinessIdentifierBranchNumber'] ?? null),
            Cast::text($row['CompanyIdentifier'] ?? null),
            Cast::text($row['PrimaryPhone'] ?? null),
            Cast::text($row['Fax'] ?? null),
            Cast::text($row['Website'] ?? null),
            Cast::text($row['Email'] ?? null),
            Cast::text($row['Street'] ?? null),
            Cast::text($row['City'] ?? null),
            Cast::text($row['State'] ?? null),
            Cast::text($row['PostCode'] ?? null),
            Cast::text($row['Country'] ?? null),
            Cast::text($row['Zone'] ?? null),
            Cast::text($row['CurrencyCode'] ?? null),
            Cast::nested($row['FileSettings'] ?? null, FileSettings::fromArray(...)),
            Cast::bool($row['IsTaxRegistered'] ?? null),
            Cast::date($row['TaxRegistrationDate'] ?? null),
            Cast::int($row['FinancialYearEndMonth'] ?? null),
            Cast::int($row['FinancialYearEndDate'] ?? null),
            Cast::int($row['PayrollYearEndMonth'] ?? null),
            Cast::int($row['PayrollYearEndDate'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::int($row['LastModifiedByUserId'] ?? null),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }
}
