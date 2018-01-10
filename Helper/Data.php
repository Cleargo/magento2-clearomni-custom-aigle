<?php


/**
 * Catalog data helper
 */
namespace Cleargo\AigleClearomniConnector\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
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
        \Cleargo\Clearomni\Helper\Data $clearomniHelper,
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
                if (sizeof($group) == 1 || !is_array($value2)) {
                    $reserveKey = __($week[$value2])->getText();
                } else {
                    $reserveKey = __($week[$value2[0]]) . ' - ' . __($week[$value2[sizeof($value2) - 1]]);
                }
                $result[$reserveKey] = $key;
            }
        }
        return $result;
    }

    public function convert12hrTo24hr($value)
    {
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
                        $stock[$value2['code']][$key] = $value2['actual'];
                    }
                }
            }
        } else {
            if (isset($productInventory)) {
                foreach ($productInventory['warehouses'] as $key2 => $value2) {
                    if (!isset($stock[$value2['code']])) {
                        $stock[$value2['code']] = [];
                    }
                    $stock[$value2['code']] = $value2['actual'];
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
                if (empty($availability)) {
                    $availability = \Cleargo\AigleClearomniConnector\Helper\Data::OOS;
                } else {
                    $availability=$this->deliveryHelper->getStatus($availability);
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

    public function getCartAvailableInStore()
    {
        $item = $this->checkoutSession->getQuote()->getItems();
        $productSku = [];
        if ($item) {
            foreach ($item as $key => $value) {
                $productSku[] = $value->getSku();
            }
        }
        return $this->getProductAvailableInStore($productSku,'cnc');
    }
}