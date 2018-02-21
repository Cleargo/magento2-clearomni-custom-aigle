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
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $orderFactory;

    /**
     * @var \Magento\Sales\Model\Service\InvoiceService
     */
    protected $invoiceService;

    /**
     * @var \Magento\Framework\DB\Transaction
     */
    protected $transaction;

    /**
     * @var \Magento\Sales\Model\Order\Email\Sender\InvoiceSender
     */
    protected $invoiceSender;
    protected $logger;
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
        \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Sales\Model\Service\InvoiceService $invoiceService,
        \Magento\Framework\DB\Transaction $transaction,
        \Magento\Sales\Model\Order\Email\Sender\InvoiceSender $invoiceSender,
        \Psr\Log\LoggerInterface $logger
    )
    {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->cartHelper = $cartHelper;
        $this->customerSession=$customerSession;
        $this->storeManager=$storeManager;
        $this->retailerRepository=$retailerRepository;
        $this->subscriberFactory = $subscriberFactory;
        $this->orderFactory=$orderFactory;
        $this->invoiceService=$invoiceService;
        $this->transaction=$transaction;
        $this->invoiceSender=$invoiceSender;
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
        ];

        $data = $this->cartHelper->placeOrder($payload);
        $result = [
            'result' => 'true',
            'data' => $data
        ];
        if((isset($data['result'])&&$data['result']=='false')||$data==false){
            $result['result']='false';
            return $this->jsonResponse($result);
        }
        //create invoice
        $orderId=$data;

        $order=$this->orderFactory->create()->load($orderId);
        if($order->getPayment()->getMethod()=='clickandreserve'){
            if($order->canInvoice()){
                $invoice_object = $this->invoiceService->prepareInvoice($order);

                // Make sure there is a qty on the invoice
                if (!$invoice_object->getTotalQty()) {
                    throw new \Magento\Framework\Exception\LocalizedException(
                        __('You can\'t create an invoice without products.'));
                }
                // Register as invoice item
                $invoice_object->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_OFFLINE);
                $invoice_object->register();

                // Save the invoice to the order
                $transaction = $this->transaction
                    ->addObject($invoice_object)
                    ->addObject($invoice_object->getOrder());

                $transaction->save();
                // Magento\Sales\Model\Order\Email\Sender\InvoiceSender
                //$this->
//                $this->invoiceSender->send($invoice_object);
                $comment = "Invoice created." ;
                $order->addStatusHistoryComment(
                    __($comment, $invoice_object->getId()))
                    ->setIsCustomerNotified(true)
                    ->save();
            }

        }
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