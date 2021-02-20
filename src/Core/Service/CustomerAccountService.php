<?php

namespace MoorlCustomerAccounts\Core\Service;

use Doctrine\DBAL\Connection;
use MoorlCustomerAccounts\Core\Content\CustomerAccountStruct;
use MoorlCustomerAccounts\Core\Event\InitialPasswordEvent;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Struct\ArrayStruct;
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
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;
    /*
     * @var Translator
     */
    private $translator;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SystemConfigService $systemConfigService,
        RequestStack $requestStack,
        AbstractSalutationRoute $salutationRoute,
        EventDispatcherInterface $eventDispatcher,
        Translator $translator
    )
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->systemConfigService = $systemConfigService;
        $this->requestStack = $requestStack;
        $this->salutationRoute = $salutationRoute;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
    }

    public function addCustomerIdToOrder(OrderEntity $order): void
    {
        $customer = $this->getSalesChannelContext()->getCustomer();

        if ($customer->hasExtension('CustomerAccount')) {
            /* @var $customerAccountStruct CustomerAccountStruct */
            $customerAccountStruct = $customer->getExtension('CustomerAccount');

            if ($customerAccountStruct->getParent()) {
                $repo = $this->definitionInstanceRegistry->getRepository('order');

                $customFields = $order->getCustomFields();
                $customFields['moorl_ca_customer_id'] = $customerAccountStruct->getParent()->getId();

                $repo->update([[
                    'id' => $order->getId(),
                    'customFields' => $customFields
                ]], $this->getSalesChannelContext()->getContext());
            }
        }
    }

    public function getCustomer(?string $customerId, $isChild = true): ?CustomerEntity
    {
        if (!$customerId) {
            return null;
        }

        $parentId = null;
        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        $criteria = new Criteria([$customerId]);
        $criteria->setLimit(1);
        if ($isChild) {
            $parentId = $this->getSalesChannelContext()->getCustomer()->getId();
            $criteria->addFilter(new EqualsFilter('customFields.moorl_ca_parent_id', $parentId));
        }

        $customer = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->first();

        if (!$customer) {
            throw new MissingPermissionError([
                $this->translator->trans('moorl-customer-accounts.errorNoPermission', ['%parentId%' => $parentId, '%customerId%' => $customerId])
            ]);
        }

        return $customer;
    }

    public function getCustomers(): ?CustomerCollection
    {
        $repo = $this->definitionInstanceRegistry->getRepository('customer');
        $context = $this->getSalesChannelContext();
        $customer = $context->getCustomer();
        $parentId = $customer->getId();
        $groupId = $customer->getGroupId();

        $groupIds = $this->systemConfigService->get('MoorlCustomerAccounts.config.groupIds', $context->getSalesChannelId());

        if (!$groupIds || !in_array($groupId, $groupIds)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->setLimit(500);
        $criteria->addFilter(new EqualsFilter('customFields.moorl_ca_parent_id', $parentId));

        return $repo->search($criteria, $this->getSalesChannelContext()->getContext())->getEntities();
    }

    public function removeCustomer(array $data): void
    {
        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        $repo->delete([['id' => $data['customerId']]], $this->getSalesChannelContext()->getContext());
    }

    public function addCustomer(array $data): void
    {
        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        if (empty($data['customerId'])) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('customer.email', $data['email']));
            $criteria->addFilter(new EqualsFilter('customer.guest', 0));

            $results = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->count();

            if ($results > 0) {
                throw new \Exception($this->translator->trans('moorl-customer-accounts.errorEmailInUse', ['%email%' => $data['email']]));
            }

            $parentCustomerNumber = $this->getSalesChannelContext()->getCustomer()->getCustomerNumber();

            if (strpos($data['customerNumber'], $parentCustomerNumber) !== 0) {
                throw new \Exception($this->translator->trans('moorl-customer-accounts.errorCustomerNumber', ['%customerNumber%' => $parentCustomerNumber]));
            }
        } else {
            unset($data['customerNumber']);
        }

        $context = $this->getSalesChannelContext();
        $parent = $context->getCustomer();
        $customerId = empty($data['customerId']) ? Uuid::randomHex() : $data['customerId'];

        $data = array_merge($data, [
            'id' => $customerId,
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

        if (!empty($data['password'])) {
            $password = $data['password'];
        } else {
            if ($data['action'] == 'newPassword' || isset($data['sendNewPassword'])) {
                $password = $this->randomPassword();
                $data['password'] = $password;
            } else {
                unset($data['password']);
            }
        }

        $repo->upsert([$data], $context->getContext());

        if ($data['action'] == 'newPassword' || isset($data['sendNewPassword'])) {
            $criteria = new Criteria([$customerId]);
            $criteria->setLimit(1);
            /* @var $customer CustomerEntity */
            $customer = $repo->search($criteria, $context->getContext())->first();

            $customer->setPassword($password);

            $event = new InitialPasswordEvent(
                $context->getContext(),
                $context->getSalesChannelId(),
                new MailRecipientStruct([$customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName()]),
                $customer,
                $parent
            );

            $this->eventDispatcher->dispatch(
                $event,
                InitialPasswordEvent::EVENT_NAME
            );
        }
    }

    private function randomPassword(): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = [];
        $alphaLength = strlen($alphabet) - 1;
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass);
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