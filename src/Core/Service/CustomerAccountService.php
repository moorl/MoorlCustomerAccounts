<?php

namespace MoorlCustomerAccounts\Core\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\AbstractSalutationRoute;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\System\Salutation\SalutationEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
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
    /**
     * @var AbstractSalutationRoute
     */
    private $salutationRoute;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SystemConfigService $systemConfigService,
        RequestStack $requestStack,
        AbstractSalutationRoute $salutationRoute
    )
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->systemConfigService = $systemConfigService;
        $this->requestStack = $requestStack;
        $this->salutationRoute = $salutationRoute;
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
        $criteria->addFilter(new EqualsFilter('customFields.moorl_ca_parent_id', $parentId));

        $customer = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->first();

        if (!$customer) {
            throw new MissingPermissionError([
                'Customer '.$parentId.' has no permission to edit ' . $customerId
            ]);
        }

        return $customer;
    }

    public function getCustomers(): ?CustomerCollection
    {
        $repo = $this->definitionInstanceRegistry->getRepository('customer');
        $parentId = $this->getSalesChannelContext()->getCustomer()->getId();

        $criteria = new Criteria();
        $criteria->setLimit(500);
        $criteria->addFilter(new EqualsFilter('customFields.moorl_ca_parent_id', $parentId));

        return $repo->search($criteria, $this->getSalesChannelContext()->getContext())->getEntities();
    }

    public function addCustomer(array $data): void
    {
        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        if (empty($data['customerId'])) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customer.email', $data['email']));
            $criteria->addFilter(new EqualsFilter('customer.guest', 0));

            $results = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->count();

            if ($results > 0) {
                throw new \Exception('Customer E-mail already in use');
            }
        }

        $context = $this->getSalesChannelContext();
        $parent = $context->getCustomer();

        $data = array_merge($data, [
            'id' => empty($data['customerId']) ? Uuid::randomHex() : $data['customerId'],
            'active' => isset($data['active']),
            'groupId' => $this->getSalesChannelContext()->getCurrentCustomerGroup()->getId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'defaultBillingAddressId' => $parent->getDefaultBillingAddressId(),
            'defaultShippingAddressId' => $parent->getDefaultShippingAddressId(),
            'customFields' => [
                'moorl_ca_parent_id' => $parent->getId()
            ],
        ]);

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $repo->upsert([$data], $context->getContext());
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function getSalutations(): SalutationCollection
    {
        $salutations = $this->salutationRoute->load(new Request(), $this->getSalesChannelContext())->getSalutations();

        $salutations->sort(function (SalutationEntity $a, SalutationEntity $b) {
            return $b->getSalutationKey() <=> $a->getSalutationKey();
        });

        return $salutations;
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