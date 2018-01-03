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
        array $data = []
    ) {

        $this->retailerRepository=$retailerRepository;
        $this->imageHelper=$imageHelper;
        $this->registry = $registry;
        $this->helper=$helper;
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
        $result=parent::getJsLayout();
        $result=json_decode($result,true);
        foreach ($result['components']['catalog-product-retailer-availability']['storeOffers'] as $key=>$value){
            $seller=$this->retailerRepository->get($value['sellerId']);
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['id']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['entity_id']=$seller->getId();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['code']=$seller->getSellerCode();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['name']=$seller->getName();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['tel']=$seller->getContactPhone();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['address']=$seller->getExtensionAttributes()->getAddress()->getData();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['openingHour']=$seller->getExtensionAttributes()->getOpeningHours();
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['availability']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['finalAvailability']=$this->helper->getProductAvailability($result['components']['catalog-product-retailer-availability']['productId']);
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['available']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['availability']!=\Cleargo\AigleClearomniConnector\Helper\Data::OOS;
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['minDay']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['finalMinDay']=mt_rand(1,3);
            $result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['maxDay']=$result['components']['catalog-product-retailer-availability']['storeOffers'][$key]['finalMaxDay']=mt_rand(4,6);
        }

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
}
