<?php

namespace InnovateOne\EshopsWithIQ\Model;


use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\CatalogInventory\Api\StockStateInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;

use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Gallery\ReadHandler;

class ProductFeed
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $store;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Category\CollectionFactory
     */
    protected $categoryCollectionFactory;

    /**
     * @var \Magento\CatalogInventory\Api\StockStateInterface
     */
    protected $stockState;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollection;

    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    protected $categories;

    protected $executionId;


    public function __construct(
        StoreManagerInterface $store,
        CategoryCollectionFactory $categoryCollectionFactory,
        StockStateInterface $stockState,
        ProductCollectionFactory $productCollection,
        ScopeConfigInterface $scopeConfig
    )
    {
        $this->executionId = bin2hex(random_bytes(20));
        $this->scopeConfig = $scopeConfig;
        $this->productCollection = $productCollection;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->store = $store;
        $this->stockState = $stockState;
    }

    public function getContent() {
        $store = $this->store;
        $storeId = $store->getStore()->getStoreId();
        $websiteId =  $store->getStore()->getWebsiteId();
        return $this->getData();
    }

    protected function getData() {
        $storeId = $this->store->getStore()->getStoreId();
        $productCount = 0;
        $productsFetched = 0;
        $childs = array();
        $collection = $this->getProducts();
		foreach($collection as $product) {
			if($product->getTypeId() == "configurable") {
				$children = $product->getTypeInstance()->getUsedProducts($product);
				foreach($children as $child) {
					$childs[$child->getId()] = $product->getId();
				}
			}
		}
        $products = array();
        foreach($collection as $product) {

            $productCount++;

            $tmpArr = array();
            $parent_id = 0;
            $productType = $product->getTypeId();
            if($product->getTypeId() == "simple" && isset($childs[$product->getId()])) {
                $parent_id = $childs[$product->getId()];
                $productType = 'configurable';
            }
            $storeIds = $product->getStoreIds();
            if(!in_array($storeId, $storeIds)) {
                continue;
            }
            $tmpArr = $this->populateProduct($product, $parent_id);
            if($parent_id > 0) {
                $tmpArr['parent'] = $parent_id;
                $tmpArr['type'] = "child";
            }
            $productsFetched++;
            $products[] = $tmpArr;

        }
        $tmpProducts = array();
        $parentUrls = array();
        foreach($products as $product) {
            if(!isset($product['id'])) { continue;}
            if($product['type'] == "child") {
                $val = (!isset($tmpProducts[$product['parent']])) ? 0 : (int)$tmpProducts[$product['parent']];
                $tmpProducts[$product['parent']] = $val + $product['stock'];

            } elseif($product['type'] == "configurable") {
                $parentUrls[$product['id']] = $product['link'];
            }
        }
        foreach($products as $key => $product) {
            if(!isset($product['id'])) { continue;}
            if($product['type'] == "configurable") {
                if(isset($tmpProducts[$product['id']])) {
                    $products[$key]['stock'] = $tmpProducts[$product['id']];
                }
            } elseif($product['type'] == "child") {
                if(isset($parentUrls[$product['parent']])) {
                    $products[$key]['link'] = $parentUrls[$product['parent']];
                }
                if(!isset($parentUrls[$product['parent']])) {
                    unset($products[$key]);
                }
            }
        }
		
		$categories_data = $categories = $this->categoryCollectionFactory->create()->addAttributeToSelect('*')->load();
		$categories = [];
		foreach($categories_data as $category_data) {
			$category = new \StdClass;
			$category->id = $category_data->getId();
			$category->parent = $category_data->getParentId();
			$category->name = $category_data->getName();
			$category->description = $category_data->getName();
			$category->link = $category_data->getUrl();
			$categories[] = $category;
		}
		
		if (!isset($_GET['raw'])) {
			return $this->formatProductsXml($products);
		} else {
			$data = compact('products', 'categories');

			$data['schema'] = [];
			$data['schema']['products'] = [
				['name' => 'id', 'type' => 'number', 'label' => 'ID'],
				['name' => 'parent', 'type' => 'number', 'label' => 'Parent'],
				['name' => 'sku', 'type' => 'text', 'label' => 'SKU'],
				['name' => 'type', 'type' => 'text', 'label' => 'Type'],
				['name' => 'title', 'type' => 'text', 'label' => 'Name'],
				['name' => 'description', 'type' => 'textarea', 'label' => 'Description'],
				['name' => 'short_description', 'type' => 'textarea', 'label' => 'Short Description'],
				['name' => 'price', 'type' => 'decimal', 'label' => 'Price'],
				['name' => 'sale_price', 'type' => 'decimal', 'label' => 'Discount Price'],
				['name' => 'link', 'type' => 'link', 'label' => 'Url'],
				['name' => 'stock', 'type' => 'number', 'label' => 'Stock Quantity'],
				['name' => 'availability', 'type' => 'text', 'label' => 'Availability'],
				['name' => 'category', 'type' => 'category', 'label' => 'Category'],
				['name' => 'categories', 'type' => 'list<category>', 'label' => 'Categories'],
				['name' => 'image_link', 'type' => 'link', 'label' => 'Image'],
				['name' => 'additional_image_link', 'type' => 'list<link>', 'label' => 'Additional Images'],
				['name' => 'weight', 'type' => 'text', 'label' => 'Weight'],
				['name' => 'size', 'type' => 'text', 'label' => 'Size'],
				['name' => 'color', 'type' => 'text', 'label' => 'Color'],
			]; 
			
			$data['schema']['categories'] = [
				['name' => 'id', 'type' => 'number', 'label' => 'ID'],
				['name' => 'parent', 'type' => 'number', 'label' => 'Parent'],
				['name' => 'title', 'type' => 'text', 'label' => 'Name'],
				['name' => 'description', 'type' => 'textarea', 'label' => 'Description'],
				['name' => 'link', 'type' => 'link', 'label' => 'Url'],
			];

			if (isset($_GET['eswiq_debug'])) die('<pre>'.print_r($data, true).'</pre>');
			return json_encode($data, JSON_UNESCAPED_UNICODE|JSON_HEX_QUOT);
		}
    }

    protected function populateProduct($product, $parent_id = 0) {
        $tmpArr = array();

        $visibility = $product->getVisibility();
        if($parent_id == 0 ) {
            if(!$visibility) {
                return $tmpArr;
            }
        }
        $stock = $this->stockState->getStockQty($product->getId(), $product->getStore()->getWebsiteId());
        /* if($stock < 1) {
            return $tmpArr;
        } */
		//die('<pre>'.print_r(get_class_methods(get_class($product)), true));
        $tmpArr['id'] = $product->getId();
        $tmpArr['sku'] = $product->getSku();
        $tmpArr['type'] = $product->getTypeId();
        $tmpArr['title'] = $product->getName();
        $tmpArr['description'] = strip_tags($product->getDescription());
        $tmpArr['short_description'] = strip_tags($product->getShortDescription());


        $price = $product->getPrice();
        $final_price = $product->getFinalPrice();
        $special_price = $product->getSpecialPrice();

        $special_price = $product->getSpecialPrice();
        if(!empty($special_price) && $special_price > 0) {
            $special_date = $product->getSpecialToDate();
            if (strtotime($special_date) >= time() || empty($special_date)) {
                $final_price = $product->getSpecialPrice();
            }
        }
		
		$tmpArr['price'] = round((float)$price,2);
        if ($price != $final_price) {
			$tmpArr['sale_price'] = round((float)$final_price,2);
        }
        //$tmpArr['price_ttc'] = $product->getPriceInfo()->getPrice('final_price')->getAmount()->getBaseAmount();
        $tmpArr['link'] = $product->getProductUrl();
        $manufacturer = "";
        if(\method_exists($product, "getManufacturer")) {
            $manufacturer = $product->getManufacturer();
        }
        if(empty($manufacturer)) {
            $manufacturer = $product->getResource()->getAttribute('manufacturer')->getFrontend()->getValue($product);
            if(empty($manufacturer)) {
                $manufacturer =$product->getAttributeText('manufacturer');
            }
        }
        $tmpArr['manufacturer'] = $manufacturer;
        
		$inc = 1;
        $galleryImages = $product->getMediaGalleryImages();
		$additional_images = [];
        foreach($galleryImages as $image) {
            $img = $image->toArray();
            if ($inc === 1) $tmpArr["image_link"] = $img['url'];
			else $additional_images[] = $img['url'];
            $inc++;
        }
		if (count($additional_images)) $tmpArr["additional_image_link"] = implode(',', $additional_images);
		
        $tmpArr['stock'] = $stock;
        $tmpArr['availability'] = $product->isAvailable() ? 'in stock' : 'out of stock';


        $attributes = ['weight', 'size', 'color'];
        foreach($attributes as $att) {
            if(empty($att)) {
                continue;
            }
            $method = str_replace("_", " ", $att);
            $method = ucwords($method);
            $method = "get".str_replace(" ", "", $method);
            if(\method_exists($product, $method)) {
                $tmpArr[$att] = $product->$method();
                if(!empty($tmpArr[$att])) {
                  continue;
                }
            }

            if($att == "weight") {
                $val = round((float)$product->getWeight(),2);
				if ($val) $tmpArr['weight'] = $val;
                if(!empty($tmpArr['weight'])) {
                  continue;
                }
            }

            try {
                $val = $product->getResource()->getAttribute($att)->getFrontend()->getValue($product);
                if(empty($val)) {
                    $val = $product->getAttributeText($att);
                }
                if(empty($val)) {
                  $val = $product->getResource()->getAttributeRawValue($product->getId(),$att,$this->store->getStore()->getStoreId());
                }
                if($val instanceof \Magento\Framework\Phrase) {
                    $val = $val->getText();
                }
                if(is_array($val)) {
                    $val = implode(",", $val);
                }
				if ($val) $tmpArr[$att] = $val;
            } catch(\Exception $ex) {
                echo $ex->getMessage();
            }
        }

        $categoryCollection = $product->getCategoryCollection();
        $categories = $this->categoryLogic($categoryCollection);
        $inc = 1;
        foreach($categories as $category) {
            $tmpArr["category"] = $category['name'];
            $inc++;
			break;
        }
		$category_ids = [];
		foreach($categoryCollection as $category) {
			$category_ids[] = $category->getId();
		}
		$tmpArr["categories"] = $category_ids;

        return $tmpArr;
    }


    protected function categoryLogic($categoryCollection) {
        $categories = array();
        $retorno = array();
        foreach($categoryCollection as $category) {
            $categories[$category->getParentId()] = array("id" => $category->getId(), "path" => $category->getPath(),"parent_id" => $category->getParentId(),"name" => strip_tags($this->categories[$category->getId()]));
        }

		$parent_id = 0;
		sort($categories);
		$category = current($categories);
		$categories = explode("/", $category['path']);
		foreach($categories as $p) {
			if($p <= 2) {
				continue;
			}
			$retorno[] = array("id" => $p, "name" => strip_tags($this->categories[$p]));
		}

        return $retorno;
    }



    public function getCategories() {
        $categories = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->load();
        $retorno = array();
        foreach($categories as $category) {
            $retorno[$category->getId()] = $category->getName();
        }
        return $retorno;
    }


    public function getProducts()
    {
        $this->categories = $this->getCategories();
        $collection = $this->productCollection->create()
            ->addAttributeToSelect('*');

        if (!empty($_GET['limit'])) $collection->setPageSize($_GET['limit']);
        $collection->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
        $collection->addUrlRewrite();
        $collection->load();
        $collection->addMediaGalleryData();

        return $collection;
    }

    protected function formatProductsXml($products) {
        $xml = "<catalog>";
        foreach($products as $product) {
            if(!isset($product['id'])) { continue; }
			unset($product['categories']);
            $xml .= "<product>";
            foreach($product as $key => $value) {
                if(is_numeric($value)) {
                    $xml .= "<".$key.">".$value."</".$key.">";
                } elseif(is_string($value)) {
                    $xml .= "<".$key."><![CDATA[".$value."]]></".$key.">";
                } elseif(is_array($value)) {
                    continue;
                } else {
                    $xml .= "<".$key.">".$value."</".$key.">";
                }
            }
            $xml .= "</product>";
        }
        $xml .= "</catalog>";
		$xml = preg_replace('/[^\x{0009}\x{000a}\x{000d}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', ' ', $xml);

        return $xml;
    }
	
}