<?php


/**
 * Catalog data helper
 */
namespace Cleargo\AigleClearomniConnector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper implements \Cleargo\Clearomni\Helper\ClearomniHelperInterface
{
//    const XML_MULTICART_URL_PATH='multicart/multicart/url';

    const AVAILABILITY = ['outofstock', 'limit', 'avail'];
    const AVAIL = 'avail';
    const LIMIT = 'limit';
    const OOS = 'outofstock';
    protected $_objectManager;
    protected $_filesystem;


    protected $curl;
    protected $scopeConfig;
    /**
     * @var \Magento\Framework\Stdlib\DateTime\TimezoneInterface
     */
    protected $timezone;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;
    protected $quoteRepository;
    protected $connection;
    protected $customerSession;
    protected $retailerCollectionFactory;
    protected $productRepository;
    protected $clearomniHelper;
    protected $deliveryHelper;

    /**
     * @param Magento\Framework\App\Helper\Context $context
     * @param Magento\Framework\ObjectManagerInterface $objectManager
     * @param Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param Magento\Store\Model\StoreManagerInterface $storeManager
     * @param Session $customerSession
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $timezone,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository,
        \Smile\Retailer\Model\ResourceModel\Retailer\CollectionFactory $retailerCollectionFactory,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Cleargo\Clearomni\Helper\Request $clearomniHelper,
        \Cleargo\DeliveryMinMaxDay\Helper\Data $deliveryHelper
    )
    {
        $this->_objectManager = $objectManager;
        $this->_filesystem = $filesystem;
        $this->curl = $curl;
        $this->scopeConfig = $scopeConfig;
        $this->timezone = $timezone;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
        $this->customerSession = $customerSession;
        $this->connection = $objectManager->get('Magento\Framework\App\ResourceConnection')->getConnection();
        $this->retailerCollectionFactory = $retailerCollectionFactory;
        $this->productRepository = $productRepository;
        $this->clearomniHelper = $clearomniHelper;
        $this->deliveryHelper = $deliveryHelper;
        parent::__construct($context);
    }


    public function getBaseUrl()
    {
        return $this->scopeConfig->getValue(
            self::XML_MULTICART_URL_PATH,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    public function getCheckoutSession()
    {
        return $this->checkoutSession;
    }

    public function getCustomerSession()
    {
        return $this->customerSession;
    }

    public function collectOpeningHour($openingHours)
    {
//        var_dump($openingHours);
//        exit;
        if (empty($openingHours)) {
            return [];
        }
        $week = [
            'Sun',
            'Mon',
            'Tue',
            'Wed',
            'Thu',
            'Fri',
            'Sat',
            'Sun',
            'Mon',
            'Tue',
            'Wed',
            'Thu',
            'Fri',
            'Sat',
        ];
//        array_unshift($openingHours,$openingHours[sizeof($openingHours)-1]);
//        unset($openingHours[sizeof($openingHours)-1]);
        $temp = [];
//        var_dump($openingHours);
//        exit;
        foreach ($openingHours as $key => $value) {
            foreach ($value as $key2 => $value2) {
                $converted = $this->convert12hrTo24hr($value2['start_time']) . '-' . $this->convert12hrTo24hr($value2['end_time']);
//                var_dump($converted);
                if (!isset($temp[$converted])) {
                    $temp[$converted] = [];
                }
                $temp[$converted][] = $key;
            }
        }
        $result = [];
        foreach ($temp as $key => $value) {
            $group = $this->getGrouped($value);
            foreach ($group as $key2 => $value2) {
                $reserveKey='';
                if (sizeof($group) == 1 || !is_array($value2)) {
                    try {
                        if (isset($week[$value2])) {
                            $reserveKey = __($week[$value2])->getText();
                        } else {
                            continue;
                        }
                    }catch (\Exception $e){
//                        var_dump($openingHours,$group,$value2);
//                        exit;
                    }
                } else {
                    try {
                        if (is_array($value2)&& isset($week[$value2[0]])) {
                            $reserveKey = __($week[$value2[0]]) . ' - ' . __($week[$value2[sizeof($value2) - 1]]);
                        } else {
                            continue;
                        }
                    }catch(\Exception $e){
//                        var_dump($week,$value2);
                    }
                }
                $result[$reserveKey] = $key;
            }
        }
        return $result;
    }

    public function convert12hrTo24hr($value)
    {
        // handle chinese locale time format
        if(strpos($value,'上午')!==false){
            $value=str_replace('上午','',$value);
            $value=$value.' AM';
        }
        if(strpos($value,'下午')!==false){
            $value=str_replace('下午','',$value);
            $value=$value.' PM';
        }
        return date("H:i", strtotime("$value"));
    }


    public function getGrouped($arr)
    {
        $group = [];
        $skip = [];
        $added = [];
        foreach ($arr as $key => $value) {
            if (in_array($value, $skip)) {
                continue;
            }
            $v = $value;
            $temp = [];
            for ($i = $key + 1; $i < sizeof($arr); $i++) {
//                var_dump($value.' '.$arr[$i]);
                if ($v + 1 == $arr[$i]) {
                    $v = $arr[$i];
                    if (empty($temp)) {
                        $temp[] = $value;
                        $added[] = $value;
                    }
                    $temp[] = $arr[$i];
                    $added[] = $arr[$i];
                    $added[] = $v;
                    $skip[] = $v;
                }
            }
            if (!empty($temp)) {
                $group[] = $temp;
            }
        }
        //day not handled
        $not = array_diff($arr, $added);
        if (!empty($not)) {
            $group = array_merge($group, $not);
        }
        return $group;
    }

    public function getStore()
    {
        /** @var \Smile\Retailer\Model\ResourceModel\Retailer\Collection $retailerCollection */
        $retailerCollection = $this->retailerCollectionFactory->create();
        $retailerCollection->addAttributeToSelect('*')->addFieldToFilter('is_active', (int)true);
        return $retailerCollection;
    }

    public function getProductAvailability($productId, $storeCode = false, $sku = false,$type='cnr')
    {
        if ($sku == true) {
            $product = $this->productRepository->get($productId);
        } else {
            $product = $this->productRepository->getById($productId);
        }
        $productSku = $product->getSku();
        $response = $this->clearomniHelper->request('/get-store?order_type='.$type.'&store_view=1&skus[]=' . $productSku);
        if($type=='cnc'){//testing data
            if ($product->getTypeId() == 'configurable') {
//                $response = json_decode('{"error":false,"data":{"G9768":{"available":0,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":0,"net":0}],"children":{"G9768-36":{"available":1003,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":1003,"net":1003}]},"G9768-34":{"available":1004,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":1004,"net":1004}]},"G9768-38":{"available":1002,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":1002,"net":1002}]},"G9768-40":{"available":999,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":999,"net":999}]},"G9768-42":{"available":1000,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":1000,"net":1000}]},"G9768-44":{"available":1001,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":1001,"net":1001}]}}}}}', true);
            }else {
//                $response = json_decode('{"error":false,"data":{"G9768-36":{"available":0,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":0,"net":0}]}}}', true);//outof stock
//                $response = json_decode('{"error":false,"data":{"G9768-44":{"available":1003,"warehouses":[{"id":1,"code":"ACP","actual":0,"net":0},{"id":2,"code":"AFW","actual":0,"net":0},{"id":3,"code":"AKT","actual":0,"net":0},{"id":4,"code":"ANP","actual":0,"net":0},{"id":5,"code":"ATS","actual":0,"net":0},{"id":9,"code":"AET","actual":0,"net":0},{"id":10,"code":"AIFC","actual":0,"net":0},{"id":11,"code":"AMM","actual":0,"net":0},{"id":12,"code":"AOT","actual":0,"net":0},{"id":13,"code":"AVC","actual":0,"net":0},{"id":14,"code":"APC","actual":0,"net":0},{"id":15,"code":"ASG","actual":0,"net":0},{"id":16,"code":"AST","actual":0,"net":0},{"id":17,"code":"AYM","actual":0,"net":0},{"id":18,"code":"AFM","actual":0,"net":0},{"id":19,"code":"ATO","actual":0,"net":1000}]}}}', true);//instock
            }
        }
        if ($response['error'] == false) {
            if ($product->getTypeId() == 'configurable') {
                $productInventory = $response['data'][$productSku]['children'];
            } else {
                $productInventory = $response['data'][$productSku];
            }
        }
        //turn sku->warehouse  to warehouse->sku
        $stock = [];
        if ($product->getTypeId() == 'configurable') {
            if (isset($productInventory)) {
                foreach ($productInventory as $key => $value) {
                    foreach ($value['warehouses'] as $key2 => $value2) {
                        if (!isset($stock[$value2['code']])) {
                            $stock[$value2['code']] = [];
                        }
                        $stock[$value2['code']][$key]['net'] = $value2['net'];
                        $stock[$value2['code']][$key]['actual'] = $value2['actual'];
                    }
                }
            }
        } else {
            if (isset($productInventory)) {
                foreach ($productInventory['warehouses'] as $key2 => $value2) {
                    if (!isset($stock[$value2['code']])) {
                        $stock[$value2['code']] = ['net'=>'','actual'=>''];
                    }
                    $stock[$value2['code']]['net'] = $value2['net'];
                    $stock[$value2['code']]['actual'] = $value2['actual'];
                }
            }
        }
        if (isset($stock[$storeCode])) {
            return $stock[$storeCode];
        }
        return [];

    }

    public function getProductAvailableInStore($productSku,$type='cnr')
    {
        if (empty($productSku)) {
            return [];
        }
        $store = $this->getStore();
        $avail = true;
        $data = [];
        foreach ($store as $key => $value) {
            $data[$value->getId()] = $value->getData();
            $data[$value->getId()]['availability'] = [];
            $data[$value->getId()]['finalAvailability'] = [];
            $data[$value->getId()]['minDay'] = [];
            $data[$value->getId()]['maxDay'] = [];
            foreach ($productSku as $key2 => $value2) {
                $availability = $this->getProductAvailability($value2, $value['seller_code'], true,$type);
                $minDay=0;
                $maxDay=0;
                if (empty($availability['actual'])&&empty($availability['net'])) {//both empty = out of stock
                    $availability = \Cleargo\AigleClearomniConnector\Helper\Data::OOS;
                } else {
                    $availability=$this->deliveryHelper->getStatus($availability['net'],$availability['actual']);
                    $minMaxDay=$this->deliveryHelper->getMinMaxDay($availability);
                    $minDay=$minMaxDay['min'];
                    $maxDay=$minMaxDay['max'];
                }
                $data[$value->getId()]['availability'][$value2] = $availability;
//                var_dump($data[$value->getId()]['availability'][$value2],$value['seller_code']);
                $data[$value->getId()]['finalAvailability'][] = $data[$value->getId()]['availability'][$value2];
                $data[$value->getId()]['minDay'][$value2] = $minDay;
                $data[$value->getId()]['maxDay'][$value2] = $maxDay;
            }
//            exit;
            $data[$value->getId()]['finalAvailability'] = array_intersect($this::AVAILABILITY, array_unique($data[$value->getId()]['finalAvailability']));
            reset($data[$value->getId()]['finalAvailability']);
            $data[$value->getId()]['finalAvailability'] = current($data[$value->getId()]['finalAvailability']);
            $data[$value->getId()]['available'] = $data[$value->getId()]['finalAvailability'] != \Cleargo\AigleClearomniConnector\Helper\Data::OOS;
            $data[$value->getId()]['finalMinDay'] = max($data[$value->getId()]['minDay']);
            $data[$value->getId()]['finalMaxDay'] = max($data[$value->getId()]['maxDay']);
            $data[$value->getId()]['code'] = $value['seller_code'];
        }
        return $data;
    }

    public function getCartAvailableInStore($type='cnr')
    {
        $item = $this->checkoutSession->getQuote()->getItems();
        $productSku = [];
        if ($item) {
            foreach ($item as $key => $value) {
                $productSku[] = $value->getSku();
            }
        }
        return $this->getProductAvailableInStore($productSku,$type);
    }

    public function getNetActual($available){
        $stock=['net'=>[0],'actual'=>[0]];
        $temp=array_values($available);
        foreach ($temp as $key=>$value){
            $stock['net'][]=$value['net'];
            $stock['actual'][]=$value['actual'];
        }
        $stock['net']=max($stock['net']);
        $stock['actual']=max($stock['actual']);
//        $stock['net']=100;
//        $stock['actual']=0;
        return $stock;
    }
}