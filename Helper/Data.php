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
        \Magento\Customer\Model\Session $customerSession

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

    public function getProductAvailability($productSku)
    {
        return $this::AVAILABILITY[mt_rand(0, 2)];
    }

    public function getProductAvailableInStore($productSku)
    {
        if(empty($productSku)) {
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
                $data[$value->getId()]['availability'][$value2] = $this->getProductAvailability($value2);
                $data[$value->getId()]['finalAvailability'][] = $data[$value->getId()]['availability'][$value2];
                $data[$value->getId()]['minDay'][$value2] = mt_rand(1, 3);
                $data[$value->getId()]['maxDay'][$value2] = mt_rand(4, 6);
            }
            $data[$value->getId()]['finalAvailability'] = array_intersect($this::AVAILABILITY, array_unique($data[$value->getId()]['finalAvailability']));
            reset($data[$value->getId()]['finalAvailability']);
            $data[$value->getId()]['finalAvailability'] = current($data[$value->getId()]['finalAvailability']);
            $data[$value->getId()]['available']=$data[$value->getId()]['finalAvailability']!=\Cleargo\AigleClearomniConnector\Helper\Data::OOS;
            $data[$value->getId()]['finalMinDay'] = max($data[$value->getId()]['minDay']);
            $data[$value->getId()]['finalMaxDay'] = max($data[$value->getId()]['maxDay']);
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
        return $this->getProductAvailableInStore($productSku);
    }
}