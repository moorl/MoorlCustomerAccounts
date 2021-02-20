<?php

namespace MoorlCustomerAccounts\Controller;

use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Account\Profile\AccountProfilePageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class StorefrontController extends \Shopware\Storefront\Controller\StorefrontController
{
    /**
     * @var CustomerAccountService
     */
    private $customerAccountService;

    /**
     * @var AccountProfilePageLoader
     */
    private $profilePageLoader;

    public function __construct(
        CustomerAccountService $customerAccountService,
        AccountProfilePageLoader $profilePageLoader
    )
    {
        $this->customerAccountService = $customerAccountService;
        $this->profilePageLoader = $profilePageLoader;
    }

    /**
     * @Route("/account/customer-accounts", name="moorl-customer-accounts.account.customer-accounts.page", methods={"GET","POST"})
     */
    public function profileCustomerAccounts(Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/customer-accounts/index.html.twig', [
            'page' => $page,
        ]);
    }

    /**
     * @Route("/account/edit/{customerId}", name="moorl-customer-accounts.account.customer-accounts.edit", methods={"GET"}, defaults={"customerId"=null,"XmlHttpRequest"=true})
     */
    public function editCustomerModal(?string $customerId, Request $request, SalesChannelContext $context): Response
    {
        $this->denyAccessUnlessLoggedIn();

        $this->customerAccountService->setSalesChannelContext($context);

        $customer = $this->customerAccountService->getCustomer($customerId);

        $body = $this->renderView('plugin/moorl-customer-accounts/edit-customer.html.twig', ['customer' => $customer]);

        return $this->renderStorefront('plugin/moorl-foundation/modal.html.twig', [
            'modal' => [
                'title' => $customer ? $customer->getEmail() : $this->trans('moorl-customer-accounts.createCustomer'),
                'size' => 'md',
                'body' => $body
            ]
        ]);
    }
}