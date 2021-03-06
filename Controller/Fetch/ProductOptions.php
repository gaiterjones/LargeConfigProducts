<?php

namespace Elgentos\LargeConfigProducts\Controller\Fetch;

use Elgentos\LargeConfigProducts\Cache\CredisClientFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\ConfigurableProduct\Block\Product\View\Type\Configurable as ProductTypeConfigurable;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProductOptions extends Action
{
    protected $helper;

    protected $catalogProduct;

    protected $credis;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /** @var CustomerSession */
    private $customerSession;

    /**
     * ProductOptions constructor.
     *
     * @param Context                    $context
     * @param ProductRepositoryInterface $productRepository
     * @param CredisClientFactory        $credisClientFactory
     * @param StoreManagerInterface      $storeManager
     * @param CustomerSession            $customerSession
     * @param ScopeConfigInterface       $scopeConfig
     *
     * @internal param Product $catalogProduct
     */
    public function __construct(
        Context $context,
        ProductRepositoryInterface $productRepository,
        CredisClientFactory $credisClientFactory,
        StoreManagerInterface $storeManager,
        CustomerSession $customerSession,
        ScopeConfigInterface $scopeConfig
    ) {
        parent::__construct($context);
        $this->productRepository = $productRepository;
        $this->storeManager = $storeManager;
        $this->customerSession = $customerSession;
        $this->scopeConfig = $scopeConfig;
        $this->credis = $credisClientFactory->create();
    }

    /**
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function execute()
    {
        $productId = $this->_request->getParam('productId');

        echo $this->getProductOptionInfo($productId);
        exit;
    }

    /**
     * @param $productId
     *
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     *
     * @return bool|mixed|string
     */
    public function getProductOptionInfo($productId)
    {
        if (!$this->credis) {
            return false;
        }

        $storeId = $this->storeManager->getStore()->getId();

        $customerGroupId = 0;

        $enableCustomerGroupId = $this->scopeConfig->getValue('elgentos_largeconfigproducts/options/enable_customer_groups');
        if ($enableCustomerGroupId) {
            $customerGroupId = $this->customerSession->getCustomerGroupId();
        }

        $cacheKey = 'LCP_PRODUCT_INFO_'.$storeId.'_'.$productId.'_'.$customerGroupId;

        if ($this->credis->exists($cacheKey)) {
            return $this->credis->get($cacheKey);
        }

        $product = $this->productRepository->getById($productId);
        if ($product->getId()) {
            $productOptionInfo = $this->getJsonConfig($product);
            $this->credis->set($cacheKey, $productOptionInfo);

            return $productOptionInfo;
        }

        return false;
    }

    /**
     * @param $currentProduct
     *
     * @return mixed
     *
     * See original method at Magento\ConfigurableProduct\Block\Product\View\Type\Configurable::getJsonConfig
     */
    public function getJsonConfig($currentProduct)
    {
        /** @var ProductTypeConfigurable $block */
        $block = $this->_view->getLayout()->createBlock(ProductTypeConfigurable::class)->setData('product', $currentProduct);

        return $block->getJsonConfig();
    }
}
