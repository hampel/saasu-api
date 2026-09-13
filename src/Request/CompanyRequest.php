<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

use Hampel\Saasu\Api\Support\Cast;

/**
 * A company to insert, or the changes to one.
 */
final class CompanyRequest extends Payload
{
    protected const WRITABLE = [
        'Name',
        'Abn',
        'Website',
        'LongDescription',
        'TradingName',
        'CompanyEmail',
        'LogoUrl',
        'LastUpdatedId',
    ];

    public static function of(string $name): self
    {
        return self::make()->withName(Cast::required($name, 'A company needs a name.'));
    }

    public function withName(?string $name): static
    {
        return $this->with('Name', $name);
    }

    public function withAbn(?string $abn): static
    {
        return $this->with('Abn', $abn);
    }

    public function withWebsite(?string $website): static
    {
        return $this->with('Website', $website);
    }

    public function withLongDescription(?string $longDescription): static
    {
        return $this->with('LongDescription', $longDescription);
    }

    public function withTradingName(?string $tradingName): static
    {
        return $this->with('TradingName', $tradingName);
    }

    public function withCompanyEmail(?string $companyEmail): static
    {
        return $this->with('CompanyEmail', $companyEmail);
    }

    public function withLogoUrl(?string $logoUrl): static
    {
        return $this->with('LogoUrl', $logoUrl);
    }

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
