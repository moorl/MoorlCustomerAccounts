<?php declare(strict_types=1);

namespace MoorlCustomerAccounts\Core\Content;

use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\AbstractContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class ContextSwitchRouteDecorator extends AbstractContextSwitchRoute
{
    public function __construct(private readonly AbstractContextSwitchRoute $decorated)
    {
    }

    public function getDecorated(): AbstractContextSwitchRoute
    {
        return $this->decorated;
    }

    public function switchContext(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        /* Restore origin customer id before context change (https://account.shopware.com/producer/support/241698) */
        $customer = $context->getCustomer();

        /* @var $customerAccount CustomerAccountStruct */
        $customerAccount = $customer->getExtension('CustomerAccount');
        if ($customerAccount && $customerAccount->getParent()) {
            $customer->setId($customerAccount->getId());
        }

        return $this->decorated->switchContext($data, $context);
    }
}
