<?php
/**
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future.
 *
 * @category  Smile
 * @package   Smile\RetailerOffer
 * @author    Romain Ruaud <romain.ruaud@smile.fr>
 * @copyright 2017 Smile
 * @license   Open Software License ("OSL") v. 3.0
 */
namespace Cleargo\AigleClearomniConnector\Block\Catalog\Product\Retailer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Helper\Product;
use Magento\Customer\Model\Session;
use Smile\Map\Api\MapProviderInterface;
use Smile\Map\Model\AddressFormatter;
use Smile\Offer\Api\Data\OfferInterface;
use Smile\Offer\Model\Offer;
use Smile\Offer\Model\OfferManagement;
use Smile\Retailer\Api\Data\RetailerInterface;
use Smile\Retailer\Model\ResourceModel\Retailer\CollectionFactory as RetailerCollectionFactory;

/**
 * Block rendering availability in store for a given product.
 *
 * @category Smile
 * @package  Smile\RetailerOffer
 * @author   Romain Ruaud <romain.ruaud@smile.fr>
 */
class Availability extends \Smile\RetailerOffer\Block\Catalog\Product\Retailer\Availability
{
    /**
     * {@inheritDoc}
     */
    protected $retailerRepository;
    protected $imageHelper;
    protected $registry;
    protected $helper;
    protected $clearomniHelper;
    protected $deliveryHelper;
    protected $requestType;
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        OfferManagement $offerManagement,
        RetailerCollectionFactory $retailerCollectionFactory,
        AddressFormatter $addressFormatter,
        MapProviderInterface $mapProvider,
        \Smile\Retailer\Api\RetailerRepositoryInterface $retailerRepository,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Registry $registry,
        \Cleargo\AigleClearomniConnector\Helper\Data $helper,
        \Cleargo\Clearomni\Helper\Request $clearomniHelper,
        \Cleargo\DeliveryMinMaxDay\Helper\Data $deliveryHelper,
        array $data = []
    ) {

        $this->retailerRepository=$retailerRepository;
        $this->imageHelper=$imageHelper;
        $this->registry = $registry;
        $this->helper=$helper;
        $this->clearomniHelper=$clearomniHelper;
        $this->deliveryHelper=$deliveryHelper;
        $this->requestType='cnr';
        parent::__construct(
            $context,
            $productRepository,
            $offerManagement,
            $retailerCollectionFactory,
            $addressFormatter,
            $mapProvider,
            $data
        );

    }

    public function getJsLayout()
    {
//        var_dump($type);
//        exit;
        $result=parent::getJsLayout();
        $result=json_decode($result,true);
        //get clearomni stock
        $product=$this->registry->registry('firebear_product');
        if($product){
            $result['components']['catalog-product-retailer-availability']['productId']=$product->getId();
        }
//        if($product) {
////            $response=$this->helper->getProductAvailability($product->getSku(),false,true,$this->requestType);
//            $response = $this->clearomniHelper->request('/get-store?order_type='.$this->requestType.'&store_view=1&skus[]=' . $product->getSku());
//            if(!empty($response)){
//                if(isset($response['data'][$product->getSku()]['children'])) {
//                    $productInventory = $response['data'][$product->getSku()]['children'];
//                }else{
//                    $productInventory = $response['data'][$product->getSku()];
//                }
//            }
//        }
//        var_dump('/get-store?order_type='.$this->requestType.'&store_view=1&skus[]=' . $product->getSku(),$productInventory);
//        exit;
        //turn sku->warehouse  to warehouse->sku
//        $stock=[];
//        if(isset($productInventory)) {
//            foreach ($productInventory as $key => $value) {
//                if(isset($value['warehouses'])) {
//                    foreach ($value['warehouses'] as $key2 => $value2) {
//                        if (!isset($stock[$value2['code']])) {
//                            $stock[$value2['code']] = ['net' => '', 'actual' => ''];
//                        }
//                        $stock[$value2['code']][$key]['net'] = $value2['net'];
//                        $stock[$value2['code']][$key]['actual'] = $value2['actual'];
//                    }
//                }else{
//                    foreach ($productInventory['warehouses'] as $key2 => $value2) {
//                        if (!isset($stock[$value2['code']])) {
//                            $stock[$value2['code']] = ['net' => $value2['net'], 'actual' => $value2['actual']];
//                        }
//                        $stock[$value2['code']][$key]['net'] = $value2['net'];
//                        $stock[$value2['code']][$key]['actual'] = $value2['actual'];
//                    }
//                }
//            }
//        }
        $warehouses=$this->helper->getProductAvailability($result['components']['catalog-product-retailer-availability']['productId'],false,false,$this->requestType);
//        $result['components']['catalog-product-retailer-availability']['productId']=$product->getId();
        foreach ($result['components']['catalog-product-retailer-availability']['storeOffers'] as $key=>$value){
            $seller=$this->retailerRepository->get($value['sellerId']);
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['id']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['entity_id']=$seller->getId();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['code']=$seller->getSellerCode();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['name']=$seller->getName();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['tel']=$seller->getContactPhone();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['address']=$seller->getExtensionAttributes()->getAddress()->getData();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['cleargoOpeningHour']=$seller->getCleargoOpeningHour();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['openingHour']=$seller->getExtensionAttributes()->getOpeningHours();
//            $availability=$this->helper->getProductAvailability($result['components']['catalog-product-retailer-availability']['productId'],$seller->getSellerCode(),false,$this->requestType);
            $availability=isset($warehouses[$seller->getSellerCode()])?$warehouses[$seller->getSellerCode()]:[];
            if(!empty($availability)) {
                $stock=$availability;
                if(isset($availability['net'])){//simple product
                }else{//config product
                    //get first child as selected
                    $keys=array_keys($stock);
                    $stock=$stock[$keys[0]];
                }
                $net=$stock['net'];
                $actual=$stock['actual'];
//                if($net<=0&&$actual<=0){
//                    $availability=\Cleargo\AigleClearomniConnector\Helper\Data::OOS;
//                }else{
                    $availability=$this->deliveryHelper->getStatus($net,$actual);
//                }
            }else{
                $availability=\Cleargo\AigleClearomniConnector\Helper\Data::OOS;
            }
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['availability']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['finalAvailability']=$availability;
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['available']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['availability']!=\Cleargo\AigleClearomniConnector\Helper\Data::OOS;
            $minMaxDay=$this->deliveryHelper->getMinMaxDay($availability);
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['minDay']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['finalMinDay']=$minMaxDay['min'];
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['maxDay']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['finalMaxDay']=$minMaxDay['max'];
            if(isset($stock)) {
                $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['stock'] = $stock['net'];
            }else{
                $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['stock']=[];
            }
        }
        usort($result['components']['catalog-product-retailer-availability']['storeOffers'], function($a, $b) {
            //get their order
            $aKey=array_search($a['finalAvailability'],\Cleargo\AigleClearomniConnector\Helper\Data::AVAILABILITY);
            $bKey=array_search($b['finalAvailability'],\Cleargo\AigleClearomniConnector\Helper\Data::AVAILABILITY);
            return $bKey - $aKey;
        });
        return json_encode($result);
    }


    public function getProductImageUrl(){
        return $this->imageHelper->init($this->getProduct(), 'product_small_image')->getUrl();
    }

    public function getProduct()
    {
        return $this->registry->registry('current_product');
    }

    protected function _toHtml()
    {
        $this->setModuleName($this->extractModuleName('Smile\RetailerOffer\Block\Catalog\Product\Retailer\Availability'));
        return parent::_toHtml();
    }

    /**
     * @return string
     */
    public function getRequestType()
    {
        return $this->requestType;
    }

    /**
     * @param string $type
     */
    public function setRequestType($type)
    {
        $this->requestType = $type;
    }

}
