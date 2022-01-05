<?php declare(strict_types=1);

namespace MoorlCustomerAccounts;

use MoorlCustomerAccounts\Core\Event\InitialPasswordEvent;
use MoorlFoundation\Core\PluginFoundation;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class MoorlCustomerAccounts extends Plugin
{
    public const EVENT_NAME = 'moorl_ca_initial_password.send';
    public const TECHNICAL_NAME = 'moorl_ca_initial_password';

    public const PLUGIN_CUSTOM_FIELDS = [
        'moorl_ca_parent_id',
        'moorl_ca_customer_id',
        'moorl_ca_order_copy',
    ];

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
                        'name' => 'moorl_ca_order_copy',
                        'type' => 'bool',
                        'config' => [
                            'componentName' => 'sw-field',
                            'customFieldType' => 'switch',
                            'label' => [
                                'en-GB' => 'Send copy of orders',
                                'de-DE' => 'Sende Kopie von Bestellungen',
                            ],
                        ]
                    ],
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
                    ],
                    [
                        'name' => 'moorl_ca_customer_id',
                        'type' => CustomFieldTypes::JSON,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Customer',
                                'de-DE' => 'Kunde',
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

        $foundation->removeMailTemplates([
            self::TECHNICAL_NAME
        ], $justDelete);

        $foundation->removeEventActions([
            InitialPasswordEvent::EVENT_NAME
        ]);

        if ($justDelete) {
            return;
        }

        $data = [
            [
                'technical_name' => self::TECHNICAL_NAME,
                'event_name' => InitialPasswordEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'locale' => [
                    'en-GB' => [
                        'name' => 'Your access',
                        'description' => 'Customer Accounts Initial Password',
                        'content_html' => $this->getTemplateFile(self::TECHNICAL_NAME, 'en-GB', 'html'),
                        'content_plain' => $this->getTemplateFile(self::TECHNICAL_NAME, 'en-GB', 'txt'),
                    ],
                    'de-DE' => [
                        'name' => 'Dein Zugang',
                        'description' => 'Kunden Accounts Initiales Passwort',
                        'content_html' => $this->getTemplateFile(self::TECHNICAL_NAME, 'de-DE', 'html'),
                        'content_plain' => $this->getTemplateFile(self::TECHNICAL_NAME, 'de-DE', 'txt'),
                    ],
                ],
                'availableEntities' => [
                    'salesChannel' => 'sales_channel',
                    'customer' => 'customer',
                    'parent' => 'customer'
                ]
            ]
        ];

        $foundation->addMailTemplates($data);
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
