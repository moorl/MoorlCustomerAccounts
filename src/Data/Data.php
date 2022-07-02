<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Data;

use MoorlCustomerAccounts\MoorlCustomerAccounts;
use MoorlFoundation\Core\System\DataExtension;
use MoorlFoundation\Core\System\DataInterface;

class Data extends DataExtension implements DataInterface
{
    public function getTables(): ?array
    {
        return array_merge(
            $this->getShopwareTables(),
            $this->getPluginTables()
        );
    }

    public function getShopwareTables(): ?array
    {
        return MoorlCustomerAccounts::SHOPWARE_TABLES;
    }

    public function getPluginTables(): ?array
    {
        return MoorlCustomerAccounts::PLUGIN_TABLES;
    }

    public function getPluginName(): string
    {
        return MoorlCustomerAccounts::NAME;
    }

    public function getCreatedAt(): string
    {
        return MoorlCustomerAccounts::DATA_CREATED_AT;
    }

    public function getName(): string
    {
        return 'data';
    }

    public function getType(): string
    {
        return 'data';
    }

    public function getPath(): string
    {
        return __DIR__;
    }
}
