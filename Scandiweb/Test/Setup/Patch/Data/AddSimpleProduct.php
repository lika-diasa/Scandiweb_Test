<?PHP
declare(strict_types=1);

namespace Scandiweb\Test\Setup\Patch\Data;

use Magento\Catalog\Api\CategoryLinkManagementInterface;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Eav\Setup\EavSetup;
use Magento\Framework\App\State;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;

class AddSimpleProduct implements DataPatchInterface
{
    protected ModuleDataSetupInterface $setup;

    protected ProductInterfaceFactory $productInterfaceFactory;

    protected ProductRepositoryInterface $productRepository;

    protected State $appState;

    protected EavSetup $eavSetup;

    protected StoreManagerInterface $storeManager;

    protected SourceItemInterfaceFactory $sourceItemFactory;

    protected SourceItemsSaveInterface $sourceItemsSaveInterface;

    protected CategoryLinkManagementInterface $categoryLink;

    protected array $sourceItems = [];

    public function __construct(
        ModuleDataSetupInterface $setup,
        ProductInterfaceFactory $productInterfaceFactory,
        ProductRepositoryInterface $productRepository,
        State $appState,
        StoreManagerInterface $storeManager,
        EavSetup $eavSetup,
        SourceItemInterfaceFactory $sourceItemFactory,
        SourceItemsSaveInterface $sourceItemsSaveInterface,
        CategoryLinkManagementInterface $categoryLink
    ) {
        $this->appState = $appState;
        $this->productInterfaceFactory = $productInterfaceFactory;
        $this->productRepository = $productRepository;
        $this->setup = $setup;
        $this->eavSetup = $eavSetup;
        $this->storeManager = $storeManager;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->sourceItemsSaveInterface = $sourceItemsSaveInterface;
        $this->categoryLink = $categoryLink;
    }

    public static function getDependencies()
    {
        return [];
    }

    public function getAliases()
    {
        return [];
    }

    public function apply()
    {
        // run setup in back-end area
        $this->appState->emulateAreaCode('adminhtml', [$this, 'execute']);
    }

    public function execute()
    {
        // create the product
        $product = $this->productInterfaceFactory->create();

        // check if the product already exists
        if ($product->getIdBySku('new-product-sku')) {
            return;
        }


        $attributeSetId = $this->eavSetup->getAttributeSetId(Product::ENTITY, 'Default');
        $websiteIDs = [$this->storeManager->getStore()->getWebsiteId()];
        // set attributes
        $product->setTypeId(Type::TYPE_SIMPLE)
            ->setWebsiteIds($websiteIDs)
            ->setAttributeSetId($attributeSetId)
            ->setName('New Product')
            ->setSku('new-product-sku')
            ->setUrlKey('newProduct')
            ->setPrice(9.99)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData(['use_config_manage_stock' => 1, 'is_qty_decimal' => 0, 'is_in_stock' => 1]);

        // save the product to the repository
        $product = $this->productRepository->save($product);

        // create a source item
        $sourceItem = $this->sourceItemFactory->create();
        $sourceItem->setSourceCode('default');
        // set the quantity of items in stock
        $sourceItem->setQuantity(100);
        // add the product's SKU that will be linked to this source item
        $sourceItem->setSku($product->getSku());
        // set the stock status
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $this->sourceItems[] = $sourceItem;

        // save the source item
        $this->sourceItemsSaveInterface->execute($this->sourceItems);

        // assign a product to category
        $this->categoryLink->assignProductToCategories($product->getSku(), [2]);
    }
}
