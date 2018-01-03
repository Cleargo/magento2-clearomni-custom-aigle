<?php


namespace Cleargo\AigleClearomniConnector\Controller\Cnr;

class ClearCart extends \Magento\Framework\App\Action\Action
{
    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    protected $resultPageFactory;
    /**
     * @var $storeManager \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var $retailerRepository \Smile\Retailer\Api\RetailerRepositoryInterface
     */
    protected $retailerRepository;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;
    /**
     * @var \Cleargo\MultiCart\Helper\Data
     */
    protected $cartHelper;
    /**
     * @var \Magento\Customer\Model\Session
     */
    protected $customerSession;
    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Smile\Retailer\Api\RetailerRepositoryInterface $retailerRepository,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Cleargo\MultiCart\Helper\Data $cartHelper,
        \Magento\Customer\Model\Session $customerSession
    ) {
        $this->storeManager=$storeManager;
        $this->retailerRepository=$retailerRepository;
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->cartHelper=$cartHelper;
        $this->customerSession=$customerSession;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result=[
            'result'=>'true'
        ];
        $this->cartHelper->getCheckoutSession()->unsCartToken();
        $this->cartHelper->getCheckoutSession()->unsSecondQuoteId();
        try {
            return $this->jsonResponse($result);
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            return $this->jsonResponse($e->getMessage());
        } catch (\Exception $e) {
            $this->logger->critical($e);
            return $this->jsonResponse($e->getMessage());
        }
    }

    /**
     * Create json response
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function jsonResponse($response = '')
    {
        return $this->getResponse()->representJson(
            $this->jsonHelper->jsonEncode($response)
        );
    }
}