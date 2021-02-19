<?php declare(strict_types=1);

namespace MoorlCustomerAccounts;

use MoorlFoundation\Core\PluginFoundation;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class MoorlCustomerAccounts extends Plugin
{
    private function refreshPluginData(Context $context, $justDelete = null): void
    {
        /* @var $foundation PluginFoundation */
        $foundation = $this->container->get(PluginFoundation::class);
        $foundation->setContext($context);

        if ($justDelete) {
            return;
        }
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
