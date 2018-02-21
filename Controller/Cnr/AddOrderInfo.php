<?php


namespace Cleargo\AigleClearomniConnector\Controller\Cnr;

class AddOrderInfo extends \Magento\Framework\App\Action\Action
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
    protected $logger;
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
        \Magento\Customer\Model\Session $customerSession,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->storeManager=$storeManager;
        $this->retailerRepository=$retailerRepository;
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->cartHelper=$cartHelper;
        $this->customerSession=$customerSession;
        $this->logger=$logger;
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
        $this->customerSession->setStore($params['store']);
        $retailer=$this->retailerRepository->get($params['store'],$this->storeManager->getDefaultStoreView()->getId());
        $address=$retailer->getExtensionAttributes()->getAddress()->getData();
        $customer=$this->customerSession->getCustomer();
        $payload=[
            'addressInformation'=>[
                'shippingAddress'=>[
                    "region"=> $address['region'],
                    "region_id"=> $address['region_id'],
                    "country_id"=> $address['country_id'],
                    "street"=> is_array($address['street'])?$address['street']:[$address['street']],
                    "company"=> $retailer->getName(),
                    "postcode"=> $address['postcode'],
                    "city"=> $address['city'],
                    "firstname"=> $customer->getFirstname(),
                    "lastname"=> $customer->getLastname(),
                    "email"=> $customer->getEmail(),
                    "prefix"=> $customer->getPrefix(),
                    "region_code"=> $address['region_id'],
                    "telephone"=>"26644557",
                    "sameAsBilling"=> 1
                ],
                "billingAddress"=> [
                    "region"=> $address['region'],
                    "region_id"=> $address['region_id'],
                    "country_id"=> $address['country_id'],
                    "street"=> is_array($address['street'])?$address['street']:[$address['street']],
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
            ]
        ];
        $data=$this->cartHelper->addAddressInfo($payload);
        $result=[
            'result'=>'true',
            'data'=>$data,
            'payload'=>$payload
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