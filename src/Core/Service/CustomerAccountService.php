<?php

namespace MoorlCustomerAccounts\Core\Service;

use Doctrine\DBAL\Connection;
use MoorlCustomerAccounts\Core\Content\CustomerAccountStruct;
use MoorlCustomerAccounts\Core\Event\InitialPasswordEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Event\EventAction\EventActionCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
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
    /**
     * @var NumberRangeValueGeneratorInterface
     */
    private $numberRangeValueGenerator;

    public function __construct(
        DefinitionInstanceRegistry $definitionInstanceRegistry,
        SystemConfigService $systemConfigService,
        RequestStack $requestStack,
        AbstractSalutationRoute $salutationRoute,
        EventDispatcherInterface $eventDispatcher,
        Translator $translator,
        NumberRangeValueGeneratorInterface $numberRangeValueGenerator
    )
    {
        $this->definitionInstanceRegistry = $definitionInstanceRegistry;
        $this->systemConfigService = $systemConfigService;
        $this->requestStack = $requestStack;
        $this->salutationRoute = $salutationRoute;
        $this->eventDispatcher = $eventDispatcher;
        $this->translator = $translator;
        $this->numberRangeValueGenerator = $numberRangeValueGenerator;

        $this->context = Context::createDefaultContext();
    }

    public function addCustomerIdToOrder(OrderEntity $order): void
    {
        if (!$this->getSalesChannelContext()) {
            return;
        }

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

    public function saveNotificationSettings(array $settings): void
    {
        $customer = $this->salesChannelContext->getCustomer();

        $payload = [
            'id' => $customer->getId(),
            'customFields' => [
                'moorl_ca_email' => $settings
            ]
        ];
        $customer->setCustomFields($payload['customFields']);

        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        $repo->upsert([$payload], $this->context);
    }

    public function getOrderBusinessEvents(): EventActionCollection
    {
        $repo = $this->definitionInstanceRegistry->getRepository('event_action');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('eventName', 'state_enter.order'));
        $criteria->addSorting(new FieldSorting('eventName', FieldSorting::DESCENDING));
        $criteria->addGroupField(new FieldGrouping('eventName'));

        return $repo->search($criteria, $this->context)->getEntities();
    }

    public function getCustomer(?string $customerId, $isChild = true): ?CustomerEntity
    {
        if (!$customerId) {
            return null;
        }

        $parentId = null;
        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        $criteria = new Criteria([$customerId]);
        $criteria->addAssociation('group');
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
        if ($groupIds && !in_array($groupId, $groupIds)) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addAssociation('group');
        $criteria->setLimit(500);
        $criteria->addFilter(new EqualsFilter('customFields.moorl_ca_parent_id', $parentId));

        return $repo->search($criteria, $this->getSalesChannelContext()->getContext())->getEntities();
    }

    public function removeCustomer(array $data): void
    {
        $customers = $this->getCustomers();

        if (!$customers || !$customers->has($data['customerId'])) {
            throw new \Exception($this->translator->trans('moorl-customer-accounts.notAllowed'));
        }

        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        $repo->delete([['id' => $data['customerId']]], $this->getSalesChannelContext()->getContext());
    }

    public function syncCustomer(CustomerEntity $customer, CustomerEntity $parent): void
    {
        if ($customer->getGroupId() === $parent->getGroupId()) {
            return;
        }

        if (!$this->systemConfigService->get('MoorlCustomerAccounts.config.inheritGroup')) {
            return;
        }

        $repo = $this->definitionInstanceRegistry->getRepository('customer');

        $data = [
            'id' => $customer->getId(),
            'groupId' => $parent->getGroupId(),
            'defaultBillingAddressId' => $parent->getDefaultBillingAddressId(),
            'defaultShippingAddressId' => $parent->getDefaultShippingAddressId(),
            'customFields' => [
                'moorl_ca_parent_id' => $parent->getId()
            ],
        ];

        $repo->upsert([$data], $this->context);
    }

    public function addCustomer(array $data): void
    {
        $repo = $this->definitionInstanceRegistry->getRepository('customer');
        $context = $this->getSalesChannelContext();
        $parent = $context->getCustomer();


        if (empty($data['customerId'])) {
            $criteria = new Criteria();
            $criteria->setLimit(1);
            $criteria->addFilter(new EqualsFilter('customer.email', $data['email']));
            $criteria->addFilter(new EqualsFilter('customer.guest', 0));

            $results = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->count();

            if ($results > 0) {
                throw new \Exception($this->translator->trans('moorl-customer-accounts.errorEmailInUse', ['%email%' => $data['email']]));
            }

            $customerNumberRule = $this->systemConfigService->get('MoorlCustomerAccounts.config.customerNumberRule');

            if ($customerNumberRule == 'auto') {
                $data['customerNumber'] = $this->numberRangeValueGenerator->getValue(
                    $repo->getDefinition()->getEntityName(),
                    $context->getContext(),
                    $context->getSalesChannel()->getId()
                );
            } elseif ($customerNumberRule == 'identical') {
                $data['customerNumber'] = $parent->getCustomerNumber();
            } elseif ($customerNumberRule == 'manualUnique') {
                $data['customerNumber'] = $parent->getCustomerNumber() . '-' . $data['customerNumber'];

                $criteria = new Criteria();
                $criteria->setLimit(1);
                $criteria->addFilter(new EqualsFilter('customer.customerNumber', $data['customerNumber']));

                $results = $repo->search($criteria, $this->getSalesChannelContext()->getContext())->count();

                if ($results > 0) {
                    throw new \Exception($this->translator->trans('moorl-customer-accounts.errorDuplicateCustomerNumber', ['%customerNumber%' => $data['customerNumber']]));
                }
            } else {
                $data['customerNumber'] = $parent->getCustomerNumber() . '-' . $data['customerNumber'];
            }
        } else {
            unset($data['customerNumber']);

            $customers = $this->getCustomers();

            if (!$customers || !$customers->has($data['customerId'])) {
                throw new \Exception($this->translator->trans('moorl-customer-accounts.notAllowed'));
            }
        }

        $customerId = empty($data['customerId']) ? Uuid::randomHex() : $data['customerId'];

        $data = array_merge($data, [
            'id' => $customerId,
            'active' => isset($data['active']),
            'groupId' => $parent->getGroupId(),
            'defaultPaymentMethodId' => $context->getPaymentMethod()->getId(),
            'salesChannelId' => $context->getSalesChannel()->getId(),
            'defaultBillingAddressId' => $parent->getDefaultBillingAddressId(),
            'defaultShippingAddressId' => $parent->getDefaultShippingAddressId(),
            'customFields' => [
                'moorl_ca_parent_id' => $parent->getId(),
                'moorl_ca_order_copy' => !empty($data['orderCopy'])
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
        $salutations = $this->salutationRoute->load(new Request(), $this->getSalesChannelContext(), new Criteria())->getSalutations();

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
        $this->context = $salesChannelContext->getContext();
    }
}