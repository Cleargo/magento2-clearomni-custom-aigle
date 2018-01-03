<?php


namespace Cleargo\AigleClearomniConnector\Controller\Cnr;

class Submit extends \Magento\Framework\App\Action\Action
{

    protected $resultPageFactory;
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
     * @var $storeManager \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var $retailerRepository \Smile\Retailer\Api\RetailerRepositoryInterface
     */
    protected $retailerRepository;

    /**
     * @var \Magento\Newsletter\Model\SubscriberFactory
     */
    protected $subscriberFactory;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Cleargo\MultiCart\Helper\Data $cartHelper,
        \Smile\Retailer\Api\RetailerRepositoryInterface $retailerRepository,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->cartHelper = $cartHelper;
        $this->customerSession=$customerSession;
        $this->storeManager=$storeManager;
        $this->retailerRepository=$retailerRepository;
        $this->subscriberFactory = $subscriberFactory;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {

        $params=$this->getRequest()->getParams();
        $retailer=$this->retailerRepository->get($this->customerSession->getStore(),$this->storeManager->getDefaultStoreView()->getId());
        $address=$retailer->getExtensionAttributes()->getAddress()->getData();
        $customer=$this->customerSession->getCustomer();
        if($params['is_subscribe']=='true'||$params['is_subscribe']==true){
            $this->subscriberFactory->create()->subscribeCustomerById($customer->getId());
        }
        /**
         * {
         * "paymentMethod": {
         * "method": "checkmo"
         * }
         * }
         */
        $params = $this->getRequest()->getParams();
        $payload = [
            'paymentMethod' => [
                'method' => 'clickandreserve'
            ],
            "billingAddress"=> [
                "region"=> $address['region'],
                "region_id"=> $address['region_id'],
                "country_id"=> $address['country_id'],
                "street"=> $address['street'],
                "company"=> $retailer->getName(),
                "postcode"=> $address['postcode'],
                "city"=> $address['city'],
                "firstname"=> $customer->getFirstname(),
                "lastname"=> $customer->getLastname(),
                "email"=> $customer->getEmail(),
                "prefix"=> $customer->getPrefix(),
                "region_code"=> $address['region_id'],
                "telephone"=>"26644557",
            ],
            "shipping_method_code"=> "smilestoredelivery",
            "shipping_carrier_code"=> "smilestoredelivery"
        ];
        $data = $this->cartHelper->placeOrder($payload);
        $result = [
            'result' => 'true',
            'data' => $data
        ];
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