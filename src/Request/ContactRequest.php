<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A contact to insert, or the changes to one.
 *
 *     ContactRequest::person('Joe', 'Blogs')->asCustomer()->withEmailAddress('joe@example.com')
 *
 * withContactReference() writes Saasu's `ContactId` field - your own reference for the contact,
 * not its id. See Entity\Contact.
 *
 * `CreatedDateUtc` IS CARRIED BY from(), unlike on every other resource. Measured 2026-09-14: a
 * contact update that did not send it came back with `CreatedDateUtc` null - the creation date
 * erased - and one that sent it back kept it. Saasu's model does not mark it read-only on a
 * contact, and it is not.
 */
final class ContactRequest extends Payload
{
    protected const WRITABLE = [
        'Salutation',
        'GivenName',
        'MiddleInitials',
        'FamilyName',
        'IsActive',
        'IsCustomer',
        'IsSupplier',
        'IsContractor',
        'IsPartner',
        'CompanyId',
        'PositionTitle',
        'EmailAddress',
        'AdditionalEmails',
        'PrimaryPhone',
        'MobilePhone',
        'HomePhone',
        'OtherPhone',
        'Fax',
        'WebsiteUrl',
        'ContactId',
        'ContactManagerId',
        'PostalAddress',
        'OtherAddress',
        'SaleTradingTerms',
        'PurchaseTradingTerms',
        'DirectDepositDetails',
        'ChequeDetails',
        'BpayDetails',
        'DefaultSaleDiscount',
        'DefaultPurchaseDiscount',
        'AutoSendStatement',
        'IsOcrSender',
        'CustomField1',
        'CustomField2',
        'TwitterId',
        'SkypeId',
        'LinkedInProfile',
        'Tags',
        'CreatedDateUtc',
        'LastUpdatedId',
    ];

    public static function person(string $givenName, ?string $familyName = null): self
    {
        return self::make()
            ->withGivenName(Cast::required($givenName, 'A contact needs a given name.'))
            ->withFamilyName($familyName);
    }

    public function asCustomer(): self
    {
        return $this->withCustomer(true);
    }

    public function asSupplier(): self
    {
        return $this->withSupplier(true);
    }

    /**
     * Additional addresses invoices are also sent to, written as Saasu's one comma-separated
     * string.
     *
     * @param  list<string>  $emails
     */
    public function withAdditionalEmailList(array $emails): self
    {
        return $this->withAdditionalEmails(implode(', ', array_filter(array_map('trim', $emails))));
    }

    public function withSalutation(?string $salutation): static
    {
        return $this->with('Salutation', $salutation);
    }

    public function withGivenName(?string $givenName): static
    {
        return $this->with('GivenName', $givenName);
    }

    public function withMiddleInitials(?string $middleInitials): static
    {
        return $this->with('MiddleInitials', $middleInitials);
    }

    public function withFamilyName(?string $familyName): static
    {
        return $this->with('FamilyName', $familyName);
    }

    public function withActive(?bool $active): static
    {
        return $this->with('IsActive', $active);
    }

    public function withCustomer(?bool $customer): static
    {
        return $this->with('IsCustomer', $customer);
    }

    public function withSupplier(?bool $supplier): static
    {
        return $this->with('IsSupplier', $supplier);
    }

    public function withContractor(?bool $contractor): static
    {
        return $this->with('IsContractor', $contractor);
    }

    public function withPartner(?bool $partner): static
    {
        return $this->with('IsPartner', $partner);
    }

    public function withCompanyId(?int $companyId): static
    {
        return $this->with('CompanyId', $companyId);
    }

    public function withPositionTitle(?string $positionTitle): static
    {
        return $this->with('PositionTitle', $positionTitle);
    }

    public function withEmailAddress(?string $emailAddress): static
    {
        return $this->with('EmailAddress', $emailAddress);
    }

    public function withAdditionalEmails(?string $additionalEmails): static
    {
        return $this->with('AdditionalEmails', $additionalEmails);
    }

    public function withPrimaryPhone(?string $primaryPhone): static
    {
        return $this->with('PrimaryPhone', $primaryPhone);
    }

    public function withMobilePhone(?string $mobilePhone): static
    {
        return $this->with('MobilePhone', $mobilePhone);
    }

    public function withHomePhone(?string $homePhone): static
    {
        return $this->with('HomePhone', $homePhone);
    }

    public function withOtherPhone(?string $otherPhone): static
    {
        return $this->with('OtherPhone', $otherPhone);
    }

    public function withFax(?string $fax): static
    {
        return $this->with('Fax', $fax);
    }

    public function withWebsiteUrl(?string $websiteUrl): static
    {
        return $this->with('WebsiteUrl', $websiteUrl);
    }

    public function withContactReference(?string $contactReference): static
    {
        return $this->with('ContactId', $contactReference);
    }

    public function withContactManagerId(?int $contactManagerId): static
    {
        return $this->with('ContactManagerId', $contactManagerId);
    }

    /**
     * @param  AddressRequest|array<string, mixed>|null  $postalAddress
     */
    public function withPostalAddress(AddressRequest|array|null $postalAddress): static
    {
        return $this->with('PostalAddress', $postalAddress);
    }

    /**
     * @param  AddressRequest|array<string, mixed>|null  $otherAddress
     */
    public function withOtherAddress(AddressRequest|array|null $otherAddress): static
    {
        return $this->with('OtherAddress', $otherAddress);
    }

    /**
     * @param  TradingTermsRequest|array<string, mixed>|null  $saleTradingTerms
     */
    public function withSaleTradingTerms(TradingTermsRequest|array|null $saleTradingTerms): static
    {
        return $this->with('SaleTradingTerms', $saleTradingTerms);
    }

    /**
     * @param  TradingTermsRequest|array<string, mixed>|null  $purchaseTradingTerms
     */
    public function withPurchaseTradingTerms(TradingTermsRequest|array|null $purchaseTradingTerms): static
    {
        return $this->with('PurchaseTradingTerms', $purchaseTradingTerms);
    }

    /**
     * @param  DirectDepositDetailsRequest|array<string, mixed>|null  $directDepositDetails
     */
    public function withDirectDepositDetails(DirectDepositDetailsRequest|array|null $directDepositDetails): static
    {
        return $this->with('DirectDepositDetails', $directDepositDetails);
    }

    /**
     * @param  ChequeDetailsRequest|array<string, mixed>|null  $chequeDetails
     */
    public function withChequeDetails(ChequeDetailsRequest|array|null $chequeDetails): static
    {
        return $this->with('ChequeDetails', $chequeDetails);
    }

    /**
     * @param  BpayDetailsRequest|array<string, mixed>|null  $bpayDetails
     */
    public function withBpayDetails(BpayDetailsRequest|array|null $bpayDetails): static
    {
        return $this->with('BpayDetails', $bpayDetails);
    }

    public function withDefaultSaleDiscount(int|float|null $defaultSaleDiscount): static
    {
        return $this->with('DefaultSaleDiscount', $defaultSaleDiscount);
    }

    public function withDefaultPurchaseDiscount(int|float|null $defaultPurchaseDiscount): static
    {
        return $this->with('DefaultPurchaseDiscount', $defaultPurchaseDiscount);
    }

    public function withAutoSendStatement(?bool $autoSendStatement): static
    {
        return $this->with('AutoSendStatement', $autoSendStatement);
    }

    public function withOcrSender(?bool $ocrSender): static
    {
        return $this->with('IsOcrSender', $ocrSender);
    }

    public function withCustomField1(?string $customField1): static
    {
        return $this->with('CustomField1', $customField1);
    }

    public function withCustomField2(?string $customField2): static
    {
        return $this->with('CustomField2', $customField2);
    }

    public function withTwitterId(?string $twitterId): static
    {
        return $this->with('TwitterId', $twitterId);
    }

    public function withSkypeId(?string $skypeId): static
    {
        return $this->with('SkypeId', $skypeId);
    }

    public function withLinkedInProfile(?string $linkedInProfile): static
    {
        return $this->with('LinkedInProfile', $linkedInProfile);
    }

    /**
     * @param  list<string>  $tags
     */
    public function withTags(array $tags): static
    {
        return $this->putTags($tags);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
