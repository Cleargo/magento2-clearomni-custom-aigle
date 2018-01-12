<?php


namespace Cleargo\AigleClearomniConnector\Controller\Cnr;

class CheckReserve extends \Magento\Framework\App\Action\Action
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
     * @var \Cleargo\Clearomni\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    public $searchCriteria;
    /**
     * @var \Magento\Framework\Api\Search\FilterGroup
     */
    public $filterGroup;
    /**
     * @var \Magento\Framework\Api\FilterBuilder
     */
    public $filterBuilder;
    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $_searchCriteria;
    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    public $orderRepository;
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
        \Cleargo\Clearomni\Helper\Data $helper,
        \Magento\Framework\Api\SearchCriteriaInterface $criteria,
        \Magento\Framework\Api\Search\FilterGroup $filterGroup,
        \Magento\Framework\Api\FilterBuilder $filterBuilder,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepositoryInterface,
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
        $this->helper=$helper;
        $this->searchCriteria = $criteria;
        $this->filterGroup = $filterGroup;
        $this->filterBuilder = $filterBuilder;
        $this->_searchCriteria = $searchCriteriaBuilder;
        $this->logger=$logger;
        $this->orderRepository = $orderRepositoryInterface;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $result = [
            'result' => 'true',
            'message' => ''
        ];
        $count=0;
        $maxReserve = $this->helper->getMaxReserve();
        if ($this->customerSession->isLoggedIn()) {
            $this->searchCriteria=$this->_searchCriteria
                ->addFilter('state','processing')
                ->addFilter('customer_id',$this->customerSession->getCustomer()->getId())->create();
            $list = $this->orderRepository->getList($this->searchCriteria);
//                    echo $list->getSelect();
//                    exit;
            foreach ($list->getItems() as $key=>$value){
                if($value->getPayment()->getMethod()=='clickandreserve'){
                    $count++;
                }
            }
            if($count>=$maxReserve){
                $result=[
                  'result'=>'false',
                    'message'=>__('Excess max reserve limit')
                ];
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