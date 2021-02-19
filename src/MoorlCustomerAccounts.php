<?php declare(strict_types=1);

namespace MoorlCustomerAccounts;

use MoorlFoundation\Core\PluginFoundation;
use MoorlCustomerAccounts\Event\PickAndCollectEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateActions;
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

        $foundation->removeMailTemplates([
            self::PICK_AND_COLLECT
        ], $justDelete);

        $foundation->removeEventActions([
            PickAndCollectEvent::EVENT_NAME
        ]);

        $foundation->removeShippingMethods([
            self::PICK_UP_DELIVER,
            self::PICK_AND_COLLECT
        ]);

        if ($justDelete) {
            $foundation->removeCustomFields('moorl_mf');

            return;
        }

        $data = [
            [
                'technical_name' => self::PICK_AND_COLLECT,
                'event_name' => PickAndCollectEvent::EVENT_NAME,
                'action_name' => MailTemplateActions::MAIL_TEMPLATE_MAIL_SEND_ACTION,
                'locale' => [
                    'en-GB' => [
                        'name' => 'Click & Collect: Order placed',
                        'description' => 'moori merchant picker',
                        'content_html' => $this->getTemplateFile(self::NAME, 'en-GB', 'html'),
                        'content_plain' => $this->getTemplateFile(self::NAME, 'en-GB', 'txt'),
                    ],
                    'de-DE' => [
                        'name' => 'Click & Collect: Bestellung eingegangen',
                        'description' => 'moori Händler Auswahl',
                        'content_html' => $this->getTemplateFile(self::NAME, 'de-DE', 'html'),
                        'content_plain' => $this->getTemplateFile(self::NAME, 'de-DE', 'txt'),
                    ],
                ],
                'availableEntities' => [
                    'salesChannel' => 'sales_channel',
                    'merchant' => 'moorl_merchant',
                    'order' => 'order'
                ]
            ]
        ];

        $foundation->addMailTemplates($data);

        $data = [
            [
                'name' => 'moorl_mf',
                'config' => [
                    'label' => [
                        'en-GB' => 'Merchant',
                        'de-DE' => 'Fachhändler',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'customer'],
                    ['entityName' => 'order'],
                    ['entityName' => 'order_delivery'],
                    ['entityName' => 'order_line_item'],
                ],
                'customFields' => [
                    [
                        'name' => 'moorl_mf_merchant_id',
                        'type' => CustomFieldTypes::JSON,
                        'config' => [
                            'label' => [
                                'en-GB' => 'Selected merchant',
                                'de-DE' => 'Ausgewählter Fachhändler',
                            ],
                            'componentName' => "sw-entity-single-select",
                            'entity' => 'moorl_merchant',
                            'customFieldType' => CustomFieldTypes::JSON,
                            'labelProperty' => "company"
                        ]
                    ],
                    [
                        'name' => 'moorl_mf_merchant_origin_id',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-field',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'label' => [
                                'en-GB' => 'Merchant ID',
                                'de-DE' => 'Fachhändler ID',
                            ],
                        ]
                    ],
                    [
                        'name' => 'moorl_mf_merchant_address',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-code-editor',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'label' => [
                                'en-GB' => 'Merchant address',
                                'de-DE' => 'Fachändler Adresse',
                            ],
                        ]
                    ],
                    [
                        'name' => 'moorl_mf_desired_date',
                        'type' => 'text',
                        'config' => [
                            'type' => 'date',
                            'componentName' => 'sw-field',
                            'customFieldType' => 'date',
                            'dateType' => 'date',
                            'config' => [
                                'time_24hr' => true
                            ],
                            'label' => [
                                'en-GB' => 'Desired pickup date',
                                'de-DE' => 'Wunsch Abholdatum',
                            ],
                        ]
                    ],
                    [
                        'name' => 'moorl_mf_desired_time',
                        'type' => 'text',
                        'config' => [
                            'type' => 'date',
                            'componentName' => 'sw-field',
                            'customFieldType' => 'date',
                            'dateType' => 'time',
                            'config' => [
                                'time_24hr' => true
                            ],
                            'label' => [
                                'en-GB' => 'Desired pickup time',
                                'de-DE' => 'Wunsch Abholzeit',
                            ],
                        ]
                    ],
                    [
                        'name' => 'moorl_mf_comment',
                        'type' => CustomFieldTypes::TEXT,
                        'config' => [
                            'componentName' => 'sw-code-editor',
                            'customFieldType' => CustomFieldTypes::TEXT,
                            'label' => [
                                'en-GB' => 'Comment to merchant',
                                'de-DE' => 'Kommentar an Fachhändler',
                            ],
                        ]
                    ],
                ]
            ]
        ];

        $foundation->updateCustomFields($data, 'moorl_mf');

        $data = [
            [
                'technical_name' => self::PICK_UP_DELIVER,
                'locale' => [
                    'en-GB' => [
                        'name' => 'Pick-Up & Deliver',
                        'description' => 'Buy online and let your local merchant deliver your goods',
                    ],
                    'de-DE' => [
                        'name' => 'Pick-Up & Deliver',
                        'description' => 'Kaufen Sie online und Ihr Händler liefert Ihnen Ihre Ware zu.',
                    ],
                ]
            ],
            [
                'technical_name' => self::PICK_AND_COLLECT,
                'locale' => [
                    'en-GB' => [
                        'name' => 'Pick & Collect',
                        'description' => 'Buy online and collect your goods at your local merchant.',
                    ],
                    'de-DE' => [
                        'name' => 'Pick & Collect',
                        'description' => 'Kaufen Sie online und holen Sie Ihre Ware bei Ihrem Händler ab.',
                    ],
                ]
            ]
        ];

        $foundation->addShippingMethods($data);
    }

    public function activate(ActivateContext $activateContext): void
    {
        parent::activate($activateContext);

        //$this->refreshPluginData($activateContext->getContext());
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
