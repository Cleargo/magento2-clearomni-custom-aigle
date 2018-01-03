<?php


namespace Cleargo\AigleClearomniConnector\Controller\Customer;


class Login extends \Magento\Customer\Controller\AbstractAccount
{
    /**
     * @var \Magento\Customer\Api\AccountManagementInterface
     */
    protected $customerAccountManagement;

    /**
     * @var \Magento\Framework\Data\Form\FormKey\Validator
     */
    protected $formKeyValidator;

    /**
     * @var AccountRedirect
     */
    protected $accountRedirect;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private $cookieMetadataFactory;

    /**
     * @var \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private $cookieMetadataManager;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    protected $jsonHelper;

    /**
     * @param Context $context
     * @param Session $customerSession
     * @param AccountManagementInterface $customerAccountManagement
     * @param CustomerUrl $customerHelperData
     * @param Validator $formKeyValidator
     * @param AccountRedirect $accountRedirect
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Magento\Customer\Api\AccountManagementInterface $customerAccountManagement,
        \Magento\Customer\Model\Url $customerHelperData,
        \Magento\Framework\Data\Form\FormKey\Validator $formKeyValidator,
        \Magento\Customer\Model\Account\Redirect $accountRedirect,
        \Magento\Framework\Json\Helper\Data $jsonHelper
    )
    {
        $this->session = $customerSession;
        $this->customerAccountManagement = $customerAccountManagement;
        $this->customerUrl = $customerHelperData;
        $this->formKeyValidator = $formKeyValidator;
        $this->accountRedirect = $accountRedirect;
        $this->jsonHelper = $jsonHelper;
        parent::__construct($context);
    }

    /**
     * Get scope config
     *
     * @return ScopeConfigInterface
     * @deprecated 100.0.10
     */
    private function getScopeConfig()
    {
        if (!($this->scopeConfig instanceof \Magento\Framework\App\Config\ScopeConfigInterface)) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\App\Config\ScopeConfigInterface::class
            );
        } else {
            return $this->scopeConfig;
        }
    }

    /**
     * Retrieve cookie manager
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\PhpCookieManager
     */
    private function getCookieManager()
    {
        if (!$this->cookieMetadataManager) {
            $this->cookieMetadataManager = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\PhpCookieManager::class
            );
        }
        return $this->cookieMetadataManager;
    }

    /**
     * Retrieve cookie metadata factory
     *
     * @deprecated 100.1.0
     * @return \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory
     */
    private function getCookieMetadataFactory()
    {
        if (!$this->cookieMetadataFactory) {
            $this->cookieMetadataFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(
                \Magento\Framework\Stdlib\Cookie\CookieMetadataFactory::class
            );
        }
        return $this->cookieMetadataFactory;
    }

    /**
     * Login post action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function execute()
    {
        $result=['result'=>false,'message'=>''];
        if ($this->session->isLoggedIn()) {
            $result['result']=true;
            $result['message']=__('Logged In');
            return $this->jsonResponse($result);
        }
        if(!$this->formKeyValidator->validate($this->getRequest())){
            $result['result']=false;
            $result['message']=__('Invalid Form key');
            return $this->jsonResponse($result);
        }

        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {
                try {
                    $customer = $this->customerAccountManagement->authenticate($login['username'], $login['password']);
                    $this->session->setCustomerDataAsLoggedIn($customer);
                    $this->session->regenerateId();
                    if ($this->getCookieManager()->getCookie('mage-cache-sessid')) {
                        $metadata = $this->getCookieMetadataFactory()->createCookieMetadata();
                        $metadata->setPath('/');
                        $this->getCookieManager()->deleteCookie('mage-cache-sessid', $metadata);
                    }
                    $result['result']=true;
                    $result['message']=__('Logged In');
                } catch (EmailNotConfirmedException $e) {
                    $result['result']=false;
                    $value = $this->customerUrl->getEmailConfirmationUrl($login['username']);
                    $result['message'] = __(
                        'This account is not confirmed. <a href="%1">Click here</a> to resend confirmation email.',
                        $value
                    );
                    $this->session->setUsername($login['username']);
                } catch (UserLockedException $e) {
                    $result['result']=false;
                    $result['message'] = __(
                        'You did not sign in correctly or your account is temporarily disabled.'
                    );
                    $this->session->setUsername($login['username']);
                } catch (AuthenticationException $e) {
                    $result['result']=false;
                    $result['message'] = __('You did not sign in correctly or your account is temporarily disabled.');
                    $this->session->setUsername($login['username']);
                } catch (LocalizedException $e) {
                    $result['result']=false;
                    $result['message'] = $e->getMessage();
                    $this->session->setUsername($login['username']);
                } catch (\Exception $e) {
                    $result['result']=false;
                    // PA DSS violation: throwing or logging an exception here can disclose customer password
                    $result['message']=__($e->getMessage());
                }
            } else {
                $result['result']=false;
                $result['message']=__('A login and a password are required.');
            }
        }

        return $this->jsonResponse($result);
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
