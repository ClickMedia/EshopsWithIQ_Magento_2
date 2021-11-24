<?php
namespace InnovateOne\EshopsWithIQ\Controller\Catalog;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use InnovateOne\EshopsWithIQ\Model\ProductFeed;


class Update extends Action
{
	/**
		* @var RawFactory
	*/
	private $rawResultFactory;
	
	public function __construct(
	Context $context,
	RawFactory $rawResultFactory
	) {
		if (isset($_GET['eswiq_debug'])) {
			ini_set('display_errors', 1);
			ini_set('display_startup_errors', 1);
			error_reporting(E_ALL);
		}  
		
		$this->rawResultFactory = $rawResultFactory;
		return parent::__construct($context);
	}
	
	public function execute() {
		
		$result = $this->rawResultFactory->create();
		try {
			$result->setHeader('Content-Type', 'text/html; charset=utf-8');
			$contents =  require_once __DIR__ . '/eshopswithiq_updater.php';
			$result->setContents($contents);
		} catch(\Exception $ex) {
			$result->setContents($ex->getMessage());
		}
		return $result;
	}
}
