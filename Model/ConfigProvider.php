<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Cleargo\AigleClearomniConnector\Model;

class ConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Captcha\Helper\Data
     */
    protected $captchaData;

    /**
     * @var \Cleargo\AigleClearomniConnector\Helper\Data
     */
    protected $ccHelper;
    /**
     * @var $retailerRepository \Smile\Retailer\Api\RetailerRepositoryInterface
     */
    protected $retailerRepository;

    /**
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Captcha\Helper\Data $captchaData
     */
    public function __construct(
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Captcha\Helper\Data $captchaData,
        \Cleargo\AigleClearomniConnector\Helper\Data $ccHelper,
        \Smile\Retailer\Api\RetailerRepositoryInterface $retailerRepository
    ) {
        $this->storeManager = $storeManager;
        $this->captchaData = $captchaData;
        $this->ccHelper=$ccHelper;
        $this->retailerRepository=$retailerRepository;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfig()
    {
        $config = [];
        try {
//            var_dump($this->ccHelper->getCustomerSession()->getRetailerId());
            $retailer = $this->retailerRepository->get($this->ccHelper->getCustomerSession()->getRetailerId(), $this->storeManager->getDefaultStoreView()->getId());
            $config['currentStore'] = $this->ccHelper->getCustomerSession()->getRetailerId();
            $config['currentStoreDetail'] = $retailer->getData();
            $config['currentStoreDetail']['address'] = $retailer->getExtensionAttributes()->getAddress()->getData();
            $availStore=$this->ccHelper->getCartAvailableInStore('cnc');
            $config['allStoreAvailability']=array_values($availStore);
            $config['currentStoreDetail']['finalMinDay']=$availStore[$retailer->getId()]['finalMinDay'];
            $config['currentStoreDetail']['finalMaxDay']=$availStore[$retailer->getId()]['finalMaxDay'];
            $config['currentStoreDetail']['finalAvailability']=$availStore[$retailer->getId()]['finalAvailability'];
        }catch(\Exception $e){

        }
        return $config;
    }

}
