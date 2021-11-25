<?php
namespace InnovateOne\EshopsWithIQ\Block;

use Magento\Framework\App\ObjectManager;
use InnovateOne\EshopsWithIQ\Model\Helper;

class Order extends \Magento\Framework\View\Element\Template
{
    protected $_salesOrderCollection;
	public $order_data;
	
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
		array $data = [],
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $salesOrderCollection,
		Helper $helper,
		\Magento\Customer\Model\Session $customerSession,
		\Magento\Framework\App\Request\Http $request
    ) {
        $this->_salesOrderCollection = $salesOrderCollection;
		$this->customerSession = $customerSession;
		$this->request = $request;
		
		$collection = $this->_salesOrderCollection->create();
        $order = $collection->getLastItem();
		
		if (empty($order)) return;
		
		$products = [];
		foreach ($order->getAllVisibleItems() as $item) {
			$products[] = [
				'id' => $item->getId(),
				'sku' => $this->escapeJsQuote($item->getSku()),
				'name' => $this->escapeJsQuote($item->getName()),
				'price' => $item->getPrice(),
				'quantity' => $item->getQtyOrdered(),
			];
		}
		
		$this->order_data = [
			'id' => $order->getIncrementId(),
			'revenue' => $order->getGrandTotal(),
			'shipping' => $order->getShippingAmount(),
			'currency' => $order->getOrderCurrencyCode(),
			'products' => $products,
		];
		
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
		if (wc_get_product()) {
			$post_data['page_data']['product_id'] = wc_get_product()->get_id();
		}
		$post_data['order'] = $this->order_data;
		if (count($post_data)) {
			$post_data['session_id'] = $session_id;
			$response = $helper->call('http://cts.eshopswithiq.com/p', $post_data);
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
}
