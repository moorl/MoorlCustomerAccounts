<?php

namespace MoorlCustomerAccounts\Core\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class CustomerAccountService
{
    /**
     * @var Context
     */
    private $context;
    /**
     * @var SalesChannelContext|null
     */
    private $salesChannelContext;
    /**
     * @var DefinitionInstanceRegistry
     */
    private $definitionInstanceRegistry;
    /**
     * @var SystemConfigService
     */
    private $systemConfigService;
    /**
     * @var RequestStack
     */
    private $requestStack;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SystemConfigService $systemConfigService,
        RequestStack $requestStack
    )
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->systemConfigService = $systemConfigService;
        $this->requestStack = $requestStack;
    }

    public function getCustomer(?string $customerId): ?CustomerEntity
    {
        if (!$customerId) {
            return null;
        }

        $repo = $this->definitionInstanceRegistry->getRepository('customer');
        $parentId = $this->getSalesChannelContext()->getCustomer()->getId();

        $criteria = new Criteria([$customerId]);
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('customFields.ca_parent_id', $parentId));

        $customer = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->first();

        if (!$customer) {
            throw new MissingPermissionError([
                'Customer '.$parentId.' has no permission to edit ' . $customerId
            ]);
        }

        return $customer;
    }

    /**
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @param Context $context
     */
    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    /**
     * @return SalesChannelContext|null
     */
    public function getSalesChannelContext(): ?SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    /**
     * @param SalesChannelContext|null $salesChannelContext
     */
    public function setSalesChannelContext(?SalesChannelContext $salesChannelContext): void
    {
        $this->salesChannelContext = $salesChannelContext;
    }
}