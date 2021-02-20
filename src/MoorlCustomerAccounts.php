<?php declare(strict_types=1);

namespace MoorlCustomerAccounts;

use MoorlFoundation\Core\PluginFoundation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class MoorlCustomerAccounts extends Plugin
{
    private function refreshPluginData(Context $context, $justDelete = null): void
    {
        /* @var $foundation PluginFoundation */
        $foundation = $this->container->get(PluginFoundation::class);
        $foundation->setContext($context);

        $data = [
            [
                'name' => 'moorl_ca',
                'config' => [
                    'label' => [
                        'en-GB' => 'Customer accounts',
                        'de-DE' => 'Kunden Accounts',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'customer'],
                    ['entityName' => 'order']
                ],
                'customFields' => [
                    [
                        'name' => 'moorl_ca_parent_id',
                        'type' => CustomFieldTypes::JSON,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Main customer',
                                'de-DE' => 'Hauptkunde',
                            ],
                            'componentName' => "sw-entity-single-select",
                            'entity' => 'customer',
                            'customFieldType' => CustomFieldTypes::JSON,
                            'labelProperty' => "email"
                        ]
                    ]
                ]
            ]
        ];

        $foundation->updateCustomFields($data, 'moorl_ca');

        if ($justDelete) {
            return;
        }
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        $this->refreshPluginData($activateContext->getContext());
    }

    public function install(InstallContext $context): void
    {
        parent::install($context);

        $this->refreshPluginData($context->getContext());
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $this->refreshPluginData($context->getContext(), true);
    }

    private function getTemplateFile($name, $locale, $extension)
    {
        $file = __DIR__ . '/../content/mail-templates/' . $name . '-' . $locale . '.' . $extension . '.twig';
        if (file_exists($file)) {
            return file_get_contents($file);
        }
        return 'Template file not found: ' . $file;
    }
}
