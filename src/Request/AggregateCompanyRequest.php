<?php

declare(strict_types=1);

namespace Hampel\Saasu\Api\Request;

/**
 * The company inside a contact aggregate. With an `Id`, the aggregate write UPDATES that company -
 * and needs its LastUpdatedId; without one, it inserts a new company.
 */
final class AggregateCompanyRequest extends Payload
{
    protected const WRITABLE = [
        'Id',
        'Name',
        'Abn',
        'LongDescription',
        'TradingName',
        'CompanyEmail',
        'LastUpdatedId',
    ];

    public function withId(?int $id): static
    {
        return $this->with('Id', $id);
    }

    public function withName(?string $name): static
    {
        return $this->with('Name', $name);
    }

    public function withAbn(?string $abn): static
    {
        return $this->with('Abn', $abn);
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

    public function withLastUpdatedId(?string $lastUpdatedId): static
    {
        return $this->with('LastUpdatedId', $lastUpdatedId);
    }
}
