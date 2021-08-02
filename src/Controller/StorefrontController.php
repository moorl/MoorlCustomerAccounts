<?php

namespace MoorlCustomerAccounts\Controller;

use MoorlCustomerAccounts\Core\Service\CustomerAccountService;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
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
     * @LoginRequired()
     */
    public function profileCustomerAccounts(Request $request, SalesChannelContext $context): Response
    {
        $this->customerAccountService->setSalesChannelContext($context);

        if ($action = $request->request->get('action')) {
            try {
                if ($action == 'edit') {
                    $this->customerAccountService->addCustomer($request->request->all());
                    $this->addFlash('success', $this->trans('moorl-customer-accounts.customerCreated'));
                } else if ($action == 'remove') {
                    $this->customerAccountService->removeCustomer($request->request->all());
                    $this->addFlash('success', $this->trans('moorl-customer-accounts.customerRemoved'));
                } else if ($action == 'newPassword') {
                    $this->customerAccountService->addCustomer($request->request->all());
                    $this->addFlash('success', $this->trans('moorl-customer-accounts.newPasswordSent'));
                } else {
                    throw new \Exception('Something went wrong');
                }
            } catch (\Exception $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/customer-accounts/index.html.twig', [
            'page' => $page,
            'children' => $this->customerAccountService->getCustomers()
        ]);
    }

    /**
     * @Route("/account/notification-settings", name="moorl-customer-accounts.account.notification-settings.page", methods={"GET","POST"})
     * @LoginRequired()
     */
    public function profileNotificationSettings(Request $request, SalesChannelContext $context): Response
    {
        $this->customerAccountService->setSalesChannelContext($context);
        $orderBusinessEvents = $this->customerAccountService->getOrderBusinessEvents();

        if ($action = $request->request->get('action')) {
            try {
                if ($action == 'edit') {
                    $this->customerAccountService->saveNotificationSettings($request->request->get('moorl_ca_email'));
                    $this->addFlash('success', $this->trans('moorl-customer-accounts.settingsSaved'));
                } else {
                    throw new \Exception('Something went wrong');
                }
            } catch (\Exception $exception) {
                $this->addFlash('danger', $exception->getMessage());
            }
        }

        $page = $this->profilePageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/storefront/page/account/notification-settings/index.html.twig', [
            'page' => $page,
            'orderBusinessEvents' => $orderBusinessEvents
        ]);
    }

    /**
     * @Route("/account/edit/{customerId}", name="moorl-customer-accounts.account.customer-accounts.edit", methods={"GET"}, defaults={"customerId"=null,"XmlHttpRequest"=true})
     * @LoginRequired()
     */
    public function editCustomerModal(?string $customerId, Request $request, SalesChannelContext $context): Response
    {
        $this->customerAccountService->setSalesChannelContext($context);

        $customer = $this->customerAccountService->getCustomer($customerId);

        $body = $this->renderView('plugin/moorl-customer-accounts/edit-customer.html.twig', [
            'customer' => $customer,
            'salutations' => $this->customerAccountService->getSalutations()
        ]);

        return $this->renderStorefront('plugin/moorl-foundation/modal.html.twig', [
            'modal' => [
                'title' => $customer ? $customer->getEmail() : $this->trans('moorl-customer-accounts.createCustomer'),
                'size' => 'md',
                'body' => $body
            ]
        ]);
    }
}
