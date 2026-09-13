<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Entity;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A person the business deals with - a customer, a supplier, a contractor, a partner, or all four.
 *
 * `Id` AND `ContactId` ARE DIFFERENT THINGS, and the naming is Saasu's. `Id` is Saasu's key, the
 * one every path and every `BillingContactId` uses. `ContactId` is YOUR reference for the
 * contact - an account number, a customer code - and is free text. It is `$contactReference`
 * here so that `$contact->id` cannot be mistaken for it; the wire name is unchanged.
 *
 * `AdditionalEmails` is one comma-separated string, not a list - additionalEmails() splits it.
 */
final class Contact extends Entity
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $lastUpdatedId = null,
        public readonly ?string $salutation = null,
        public readonly ?string $givenName = null,
        public readonly ?string $middleInitials = null,
        public readonly ?string $familyName = null,
        public readonly ?bool $isActive = null,
        public readonly ?bool $isOcrSender = null,
        public readonly ?string $ocrRecipientAlias = null,
        public readonly ?int $companyId = null,
        public readonly ?string $positionTitle = null,
        public readonly ?string $websiteUrl = null,
        public readonly ?string $primaryPhone = null,
        public readonly ?string $homePhone = null,
        public readonly ?string $otherPhone = null,
        public readonly ?string $mobilePhone = null,
        public readonly ?string $fax = null,
        public readonly ?string $emailAddress = null,
        public readonly ?string $additionalEmails = null,
        public readonly ?string $contactReference = null,
        public readonly ?int $contactManagerId = null,
        public readonly ?DirectDepositDetails $directDepositDetails = null,
        public readonly ?ChequeDetails $chequeDetails = null,
        public readonly ?string $customField1 = null,
        public readonly ?string $customField2 = null,
        public readonly ?string $twitterId = null,
        public readonly ?string $skypeId = null,
        public readonly ?string $linkedInProfile = null,
        public readonly ?bool $autoSendStatement = null,
        public readonly ?bool $isPartner = null,
        public readonly ?bool $isCustomer = null,
        public readonly ?bool $isSupplier = null,
        public readonly ?bool $isContractor = null,
        /** @var list<string> */
        public readonly array $tags = [],
        public readonly ?float $defaultSaleDiscount = null,
        public readonly ?float $defaultPurchaseDiscount = null,
        public readonly ?int $lastModifiedByUserId = null,
        public readonly ?BpayDetails $bpayDetails = null,
        public readonly ?Address $postalAddress = null,
        public readonly ?Address $otherAddress = null,
        public readonly ?TradingTerms $saleTradingTerms = null,
        public readonly ?TradingTerms $purchaseTradingTerms = null,
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
            Cast::int($row['Id'] ?? null),
            Cast::text($row['LastUpdatedId'] ?? null),
            Cast::text($row['Salutation'] ?? null),
            Cast::text($row['GivenName'] ?? null),
            Cast::text($row['MiddleInitials'] ?? null),
            Cast::text($row['FamilyName'] ?? null),
            Cast::bool($row['IsActive'] ?? null),
            Cast::bool($row['IsOcrSender'] ?? null),
            Cast::text($row['OcrRecipientAlias'] ?? null),
            Cast::int($row['CompanyId'] ?? null),
            Cast::text($row['PositionTitle'] ?? null),
            Cast::text($row['WebsiteUrl'] ?? null),
            Cast::text($row['PrimaryPhone'] ?? null),
            Cast::text($row['HomePhone'] ?? null),
            Cast::text($row['OtherPhone'] ?? null),
            Cast::text($row['MobilePhone'] ?? null),
            Cast::text($row['Fax'] ?? null),
            Cast::text($row['EmailAddress'] ?? null),
            Cast::text($row['AdditionalEmails'] ?? null),
            Cast::text($row['ContactId'] ?? null),
            Cast::int($row['ContactManagerId'] ?? null),
            Cast::nested($row['DirectDepositDetails'] ?? null, DirectDepositDetails::fromArray(...)),
            Cast::nested($row['ChequeDetails'] ?? null, ChequeDetails::fromArray(...)),
            Cast::text($row['CustomField1'] ?? null),
            Cast::text($row['CustomField2'] ?? null),
            Cast::text($row['TwitterId'] ?? null),
            Cast::text($row['SkypeId'] ?? null),
            Cast::text($row['LinkedInProfile'] ?? null),
            Cast::bool($row['AutoSendStatement'] ?? null),
            Cast::bool($row['IsPartner'] ?? null),
            Cast::bool($row['IsCustomer'] ?? null),
            Cast::bool($row['IsSupplier'] ?? null),
            Cast::bool($row['IsContractor'] ?? null),
            Cast::tags($row['Tags'] ?? null),
            Cast::float($row['DefaultSaleDiscount'] ?? null),
            Cast::float($row['DefaultPurchaseDiscount'] ?? null),
            Cast::int($row['LastModifiedByUserId'] ?? null),
            Cast::nested($row['BpayDetails'] ?? null, BpayDetails::fromArray(...)),
            Cast::nested($row['PostalAddress'] ?? null, Address::fromArray(...)),
            Cast::nested($row['OtherAddress'] ?? null, Address::fromArray(...)),
            Cast::nested($row['SaleTradingTerms'] ?? null, TradingTerms::fromArray(...)),
            Cast::nested($row['PurchaseTradingTerms'] ?? null, TradingTerms::fromArray(...)),
            Cast::datetime($row['CreatedDateUtc'] ?? null),
            Cast::datetime($row['LastModifiedDateUtc'] ?? null),
            $row,
        );
    }

    /**
     * The name as Saasu would print it: salutation, given name, initials, family name.
     */
    public function fullName(): string
    {
        return trim(implode(' ', array_filter(
            [$this->givenName, $this->middleInitials, $this->familyName],
            static fn (?string $part): bool => $part !== null && trim($part) !== ''
        )));
    }

    /**
     * @return list<string>
     */
    public function additionalEmails(): array
    {
        return Cast::tags($this->additionalEmails);
    }
}
