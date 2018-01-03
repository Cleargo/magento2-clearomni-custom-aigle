<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Cleargo\AigleClearomniConnector\Block;

/**
 * Customer login form block
 *
 * @api
 * @author      Magento Core Team <core@magentocommerce.com>
 * @since 100.0.2
 */
class CnRPopUp extends \Magento\Framework\View\Element\Template
{

    /**
     * @var $registry \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * @var $storeManager \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var $retailerRepository \Smile\Retailer\Api\RetailerRepositoryInterface
     */
    protected $retailerRepository;

    /**
     * @var $helper \Cleargo\AigleClearomniConnector\Helper\Data
     */
    protected $helper;
    /**
     * @var \Magento\Catalog\Helper\Image
     */
    protected $imageHelper;
    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param array $data
     */
    public function __construct(
        
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Smile\Retailer\Api\RetailerRepositoryInterface $retailerRepository,
        \Cleargo\AigleClearomniConnector\Helper\Data $helper,
        \Magento\Catalog\Helper\Image $imageHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry=$registry;
        $this->storeManager=$storeManager;
        $this->retailerRepository=$retailerRepository;
        $this->helper=$helper;
        $this->imageHelper=$imageHelper;
    }

    /**
     * @return $this
     */
    protected function _prepareLayout()
    {
        return parent::_prepareLayout();
    }

    public function getHelper(){
        return $this->helper;
    }

    /**
     * @return \Smile\Retailer\Api\RetailerRepositoryInterface
     */
    public function getRetailerRepository()
    {
        return $this->retailerRepository;
    }

    /**
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    public function getStoreManager()
    {
        return $this->storeManager;
    }

    /**
     * @return \Magento\Framework\Registry
     */
    public function getRegistry()
    {
        return $this->registry;
    }

    /**
     * @return \Magento\Catalog\Helper\Image
     */
    public function getImageHelper()
    {
        return $this->imageHelper;
    }


}
