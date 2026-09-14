<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * Changes to the file's business identity.
 *
 * Saasu marks `LastUpdatedId`, `FullLegalName`, `IndustryTypeId`, `Street` and `City` required on
 * an update, so build this from() the identity rather than from nothing. `Zone` and the
 * currency cannot be changed, and are not offered.
 *
 * THE DOCUMENTATION UNDERSTATES WHAT IS REQUIRED. Measured 2026-09-14 on an Australian file, a PUT
 * of the identity as read was refused, in plain text, for want of the ABN, street, city, state,
 * postcode, "date format preference" and "number format preference". The last two are
 * `DateTimeFormatId` and `NumberFormatId`, which Saasu marks DEPRECATED - and requires anyway, so
 * from() carries them over. A file whose identity was never filled in cannot be updated until it
 * is.
 */
final class FileIdentityRequest extends Payload
{
    protected const WRITABLE = [
        'Name',
        'FullLegalName',
        'TradingNameOrAlternativeBrandName',
        'IndustryTypeId',
        'BusinessIdentifier',
        'BusinessIdentifierBranchNumber',
        'CompanyIdentifier',
        'PrimaryPhone',
        'Fax',
        'Website',
        'Email',
        'Street',
        'City',
        'State',
        'PostCode',
        'Country',
        'IsTaxRegistered',
        'TaxRegistrationDate',
        'FinancialYearEndMonth',
        'FinancialYearEndDate',
        'PayrollYearEndMonth',
        'PayrollYearEndDate',
        'DateTimeFormatId',
        'NumberFormatId',
        'LastUpdatedId',
    ];

    public function withName(?string $name): static
    {
        return $this->with('Name', $name);
    }

    public function withFullLegalName(?string $fullLegalName): static
    {
        return $this->with('FullLegalName', $fullLegalName);
    }

    public function withTradingName(?string $tradingName): static
    {
        return $this->with('TradingNameOrAlternativeBrandName', $tradingName);
    }

    public function withIndustryTypeId(?int $industryTypeId): static
    {
        return $this->with('IndustryTypeId', $industryTypeId);
    }

    public function withBusinessIdentifier(?string $businessIdentifier): static
    {
        return $this->with('BusinessIdentifier', $businessIdentifier);
    }

    public function withBusinessIdentifierBranchNumber(?int $businessIdentifierBranchNumber): static
    {
        return $this->with('BusinessIdentifierBranchNumber', $businessIdentifierBranchNumber);
    }

    public function withCompanyIdentifier(?string $companyIdentifier): static
    {
        return $this->with('CompanyIdentifier', $companyIdentifier);
    }

    public function withPrimaryPhone(?string $primaryPhone): static
    {
        return $this->with('PrimaryPhone', $primaryPhone);
    }

    public function withFax(?string $fax): static
    {
        return $this->with('Fax', $fax);
    }

    public function withWebsite(?string $website): static
    {
        return $this->with('Website', $website);
    }

    public function withEmail(?string $email): static
    {
        return $this->with('Email', $email);
    }

    public function withStreet(?string $street): static
    {
        return $this->with('Street', $street);
    }

    public function withCity(?string $city): static
    {
        return $this->with('City', $city);
    }

    public function withState(?string $state): static
    {
        return $this->with('State', $state);
    }

    public function withPostCode(?string $postCode): static
    {
        return $this->with('PostCode', $postCode);
    }

    public function withCountry(?string $country): static
    {
        return $this->with('Country', $country);
    }

    public function withTaxRegistered(?bool $taxRegistered): static
    {
        return $this->with('IsTaxRegistered', $taxRegistered);
    }

    public function withTaxRegistrationDate(\DateTimeInterface|string|null $taxRegistrationDate): static
    {
        return $this->putDate('TaxRegistrationDate', $taxRegistrationDate);
    }

    public function withFinancialYearEndMonth(?int $financialYearEndMonth): static
    {
        return $this->with('FinancialYearEndMonth', $financialYearEndMonth);
    }

    public function withFinancialYearEndDay(?int $financialYearEndDay): static
    {
        return $this->with('FinancialYearEndDate', $financialYearEndDay);
    }

    public function withPayrollYearEndMonth(?int $payrollYearEndMonth): static
    {
        return $this->with('PayrollYearEndMonth', $payrollYearEndMonth);
    }

    public function withPayrollYearEndDay(?int $payrollYearEndDay): static
    {
        return $this->with('PayrollYearEndDate', $payrollYearEndDay);
    }

    /**
     * Deprecated by Saasu, and still required: see the class docblock.
     */
    public function withDateTimeFormatId(?int $dateTimeFormatId): static
    {
        return $this->with('DateTimeFormatId', $dateTimeFormatId);
    }

    /**
     * Deprecated by Saasu, and still required: see the class docblock.
     */
    public function withNumberFormatId(?int $numberFormatId): static
    {
        return $this->with('NumberFormatId', $numberFormatId);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
