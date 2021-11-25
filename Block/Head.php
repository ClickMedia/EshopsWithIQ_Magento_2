<?php
namespace InnovateOne\EshopsWithIQ\Block;

use Magento\Framework\App\ObjectManager;
use InnovateOne\EshopsWithIQ\Model\Helper;

class Head extends \Magento\Framework\View\Element\Template
{
	public $product_id;
	
    public function __construct(
		\Magento\Framework\View\Element\Template\Context $context,
		array $data = [],
		Helper $helper,
		string $eswiq_go_site_verification = '',
		\Magento\Framework\HTTP\Client\Curl $curl,
		\Magento\Framework\App\Config\Storage\WriterInterface $configWriter,
		\Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Framework\App\Request\Http $request
	) {
		$this->curl = $curl;
		$this->configWriter = $configWriter;
		$this->scopeConfig = $scopeConfig;
		$this->customerSession = $customerSession;
		$this->request = $request;
		
		$code = $this->scopeConfig->getValue('eshopswithiq/eswiq_go_site_verification');
		if (empty($code) || $this->request->getParam('eswiq_debug')) {
			$code = $this->getSiteVerificationCode();
			if ($code) $this->configWriter->save('eshopswithiq/eswiq_go_site_verification', $code);
		}
		if ($code) $this->eswiq_go_site_verification = '<meta name="google-site-verification" content="'.$code.'" />';
		
		
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
		$product = $objectManager->get('Magento\Framework\Registry')->registry('current_product');//get current product
		if ($product) $this->product_id = $product->getId();
		
		//session
		$session_id = $this->customerSession->getData('eshopswithiq');
		if (empty($session_id)) $session_id = isset($_COOKIE['eshopswithiq']) ? $_COOKIE['eshopswithiq'] : null;
		
		//server side fallback
		$post_data = [];
		if (!empty($this->request->getParam('eclid'))) {
			$post_data['lead'] = $this->request->getParam('eclid');
		} else if (!empty($this->request->getParam('ea_client')) && !empty($this->request->getParam('ea_channel'))) {
			$lead_data = ['client' => $this->request->getParam('ea_client'), 'channel' => $this->request->getParam('ea_channel')];
			if (!empty($this->request->getParam('ea_group'))) $lead_data['group'] = $this->request->getParam('ea_group');
			if (!empty($this->request->getParam('ea_product'))) $lead_data['product'] = $this->request->getParam('ea_product');
			$post_data['lead'] = base64_encode(json_encode($lead_data));
		}
		if ($this->product_id) {
			$post_data['page_data']['product_id'] = $this->product_id;
		}
		if (count($post_data) || empty($session_id)) {
			$post_data['session_id'] = $session_id;
			$response = $helper->call('http://cts.eshopswithiq.com/v', $post_data);
			if ($response) {
				$session_id = $response;
				$this->customerSession->setData('eshopswithiq', $session_id);
			}
		}
		if (empty($_COOKIE['eshopswithiq']) && !empty($session_id)) {
			//setcookie('eshopswithiq', $session_id, time() + (86400 * 30), "/");
			header('Set-Cookie: eshopswithiq='.$session_id.'; expires='.(time() + (86400 * 30)).'; path=/; SameSite=None; Secure');
		}
		
		parent::__construct($context, $data);
    }
	
	public function getSiteVerificationCode()
	{
		$endpoint = 'https://app.eshopswithiq.com/app-installations/get-google-verification-tag?website='.$_SERVER['HTTP_HOST'];
		$this->curl->get($endpoint);
		$body = $this->curl->getBody();
		
		$code = '';
		preg_match('<meta name="(?:[^"]+)" content="([^"]+)" />', $body, $matches);
		if (count($matches)) {
			$code = $matches[1];
		}  
		
		return $code;
	}
}
