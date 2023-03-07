<?php declare(strict_types=1);

namespace MoorlCustomerAccounts;

use MoorlFoundation\Core\Service\DataService;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Doctrine\DBAL\Connection;

class MoorlCustomerAccounts extends Plugin
{
    final public const NAME = 'MoorlCustomerAccounts';
    final public const DATA_CREATED_AT = '2000-07-02 00:00:00.000';
    final public const PLUGIN_TABLES = [];
    final public const SHOPWARE_TABLES = [
        'mail_template_type',
        'mail_template_type_translation',
        'mail_template',
        'mail_template_translation',
        'event_action',
        'custom_field_set',
        'flow',
        'flow_sequence',
    ];
    final public const INHERITANCES = [];
    final public const EVENT_NAME = 'moorl_ca_initial_password.send';
    final public const TECHNICAL_NAME = 'moorl_ca_initial_password';
    final public const PLUGIN_CUSTOM_FIELDS = [
        'moorl_ca_parent_id',
        'moorl_ca_customer_id',
        'moorl_ca_order_copy',
    ];

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        /* @var $dataService DataService */
        $dataService = $this->container->get(DataService::class);
        $dataService->install(self::NAME);
    }

    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        try {
            /* @var $dataService DataService */
            $dataService = $this->container->get(DataService::class);
            $dataService->install(self::NAME);
        } catch (\Exception) {
        }
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $this->removePluginData();
        $this->dropTables();
    }

    private function removePluginData(): void
    {
        $connection = $this->container->get(Connection::class);

        foreach (array_reverse(self::SHOPWARE_TABLES) as $table) {
            $sql = sprintf("SET FOREIGN_KEY_CHECKS=0; DELETE FROM `%s` WHERE `created_at` = '%s';", $table, self::DATA_CREATED_AT);

            try {
                $connection->executeStatement($sql);
            } catch (\Exception) {
                continue;
            }
        }
    }

    private function dropTables(): void
    {
        $connection = $this->container->get(Connection::class);

        foreach (self::PLUGIN_TABLES as $table) {
            $sql = sprintf('SET FOREIGN_KEY_CHECKS=0; DROP TABLE IF EXISTS `%s`;', $table);
            $connection->executeStatement($sql);
        }
    }
}
