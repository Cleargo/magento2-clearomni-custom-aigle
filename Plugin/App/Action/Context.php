<?php
namespace Cleargo\AigleClearomniConnector\Plugin\App\Action;

use Cleargo\AigleClearomniConnector\Model\Customer\Context as CustomerSessionContext;

class Context
{
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;

    /**
     * @var \Magento\Framework\App\Http\Context
     */
    protected $httpContext;

    /**
     * @var \Magento\Framework\App\Cache\StateInterface
     */
    protected $cacheState;

    /**
     * @param \Magento\Customer\Model\Session $customerSession
     * @param \Magento\Framework\App\Http\Context $httpContext
     */
    public function __construct(
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Framework\App\Http\Context $httpContext,
        \Magento\Framework\App\Cache\StateInterface $cacheState
    )
    {
        $this->customerSession = $customerSession;
        $this->httpContext = $httpContext;
        $this->cacheState = $cacheState;
    }

    /**
     * @param \Magento\Framework\App\ActionInterface $subject
     * @param callable $proceed
     * @param \Magento\Framework\App\RequestInterface $request
     * @return mixed
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function beforeDispatch(\Magento\Framework\App\Action\AbstractAction $subject, \Magento\Framework\App\RequestInterface $request)
    {
        /** @var bool $isEnabled */
//        $isEnabled = $this->cacheState->isEnabled(
//            \Magento\PageCache\Model\Cache\Type::TYPE_IDENTIFIER
//        );
//        if (!$isEnabled) {
//            return;
//        }
        $customerId = $this->customerSession->getCustomerId();
        if (!$customerId) {
            $customerId = 0;
        }

        $this->httpContext->setValue(
            CustomerSessionContext::CONTEXT_CUSTOMER_ID,
            $customerId,
            false
        );
//        var_dump($customerId);
//        echo ' ';//no idea why i have to print something to make session not disappear
    }
}