<?php


namespace Cleargo\AigleClearomniConnector\Controller\Popup;


class Reload extends \Magento\Framework\App\Action\Action
{
    protected $resultPageFactory;
    protected $jsonHelper;
    /**
     * @var \Cleargo\MultiCart\Helper\Data
     */
    protected $helper;

    /**
     * @var \Magento\Framework\View\LayoutFactory
     */
    protected $layoutFactory;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    protected $resultRawFactory;
    /**
     * @var $registry \Magento\Framework\Registry
     */
    protected $registry;

    /**
     * Constructor
     *
     * @param \Magento\Framework\App\Action\Context  $context
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Framework\View\Result\PageFactory $resultPageFactory,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Cleargo\MultiCart\Helper\Data $helper,
        \Magento\Framework\View\LayoutFactory $layoutFactory,
        \Magento\Framework\Controller\Result\RawFactory $resultRawFactory,
        \Magento\Framework\Registry $registry
    ) {
        $this->resultPageFactory = $resultPageFactory;
        $this->jsonHelper = $jsonHelper;
        $this->helper=$helper;
        $this->layoutFactory = $layoutFactory;
        $this->resultRawFactory=$resultRawFactory;
        $this->registry=$registry;
        parent::__construct($context);
    }

    /**
     * Execute view action
     *
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        $productId=$this->getRequest()->getParam('product');
        $product=$this->_objectManager->create('Magento\Catalog\Model\Product')->load($productId);
        $this->registry->register('product',$product);
        $this->registry->register('current_product',$product);
        $availability=$this->_objectManager->create('Cleargo\AigleClearomniConnector\Block\Catalog\Product\Retailer\Availability');
        $availability->setProductId($productId);
        $jsConfig=$availability->getJsLayout();
        $storeAvail=json_decode($jsConfig,true);
        $storeAvail=$storeAvail['components']['catalog-product-retailer-availability']['storeOffers'];
        $this->registry->register('storeAvail',$storeAvail);
        return $this->resultPageFactory->create();
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
