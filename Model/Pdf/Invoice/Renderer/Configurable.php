<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Julio\Invoice\Model\Pdf\Invoice\Renderer;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Sales\Model\Order\Pdf\Items\Invoice\DefaultInvoice;
use Zend_Pdf_Image;

/**
 * Sales Order Invoice Pdf default items renderer
 */
class Configurable extends DefaultInvoice
{
    /**
     * @var \Magento\Catalog\Helper\Image
     */
    private $imageHelper;
    /**
     * @var \Magento\Framework\Url
     */
    private $urlBuilder;

    /**
     * Configurable constructor.
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param \Magento\Tax\Helper\Data $taxData
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Filter\FilterManager $filterManager
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param \Magento\Catalog\Helper\Image $imageHelper
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource|null $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Tax\Helper\Data $taxData,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Framework\Filter\FilterManager $filterManager,
        \Magento\Framework\Stdlib\StringUtils $string,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Framework\Url $urlBuilder,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ){
        parent::__construct($context, $registry, $taxData, $filesystem, $filterManager, $string, $resource, $resourceCollection, $data);
        $this->imageHelper = $imageHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Draw item line
     *
     * @return void
     */
    public function draw()
    {
        $order = $this->getOrder();
        $item = $this->getItem();
        $pdf = $this->getPdf();
        $page = $this->getPage();
        $lines = [];

        $orderItem = $item->getOrderItem();
        $thumbnail = false;
        $leftIndent = 35;
        $parent = $orderItem->getBuyRequest()
            ? $orderItem->getBuyRequest()->getData('parent')
            : false ;

        // Additional information:
        $skuLines = $this->string->split($this->getSku($item), 20);
        if ($parent) {
            $product = new \Magento\Framework\DataObject([
                'entity_id' => $parent['id'],
                'thumbnail' => $parent['img'],
                'sku' => $parent['sku']
            ]);
            $urlThumbnail = $this->imageHelper->init($product, 'product_thumbnail_image')
                ->setImageFile($product->getThumbnail())
                ->getUrl();
            $pubDir = $this->_rootDirectory->getAbsolutePath() . DirectoryList::PUB . '/';
            $thumbnail = str_replace($this->urlBuilder->getUrl('', ['_nosid' => true]), $pubDir, $urlThumbnail);
            $parentSku = $this->string->split($parent['sku'], 20);
            $skuLines = array_merge($parentSku, $skuLines);
            $leftIndent = 65;
        }

        // draw Product name
        $lines[0] = [['text' => $this->string->split($item->getName(), 35, true, true), 'feed' => $leftIndent]];

        // draw SKU
        $lines[0][] = [
            'text' => $skuLines,
            'feed' => 290,
            'align' => 'right',
            'height' => 10
        ];

        // draw QTY
        $lines[0][] = ['text' => $item->getQty() * 1, 'feed' => 435, 'align' => 'right'];

        // draw item Prices
        $i = 0;
        $prices = $this->getItemPricesForDisplay();
        $feedPrice = 395;
        $feedSubtotal = $feedPrice + 170;
        foreach ($prices as $priceData) {
            if (isset($priceData['label'])) {
                // draw Price label
                $lines[$i][] = ['text' => $priceData['label'], 'feed' => $feedPrice, 'align' => 'right'];
                // draw Subtotal label
                $lines[$i][] = ['text' => $priceData['label'], 'feed' => $feedSubtotal, 'align' => 'right'];
                $i++;
            }
            // draw Price
            $lines[$i][] = [
                'text' => $priceData['price'],
                'feed' => $feedPrice,
                'font' => 'bold',
                'align' => 'right',
            ];
            // draw Subtotal
            $lines[$i][] = [
                'text' => $priceData['subtotal'],
                'feed' => $feedSubtotal,
                'font' => 'bold',
                'align' => 'right',
            ];
            $i++;
        }

        // draw Tax
        $lines[0][] = [
            'text' => $order->formatPriceTxt($item->getTaxAmount()),
            'feed' => 495,
            'font' => 'bold',
            'align' => 'right',
        ];

        // custom options
        $options = $this->getItemOptions();
        if ($options) {
            foreach ($options as $option) {
                // draw options label
                $lines[][] = [
                    'text' => $this->string->split($this->filterManager->stripTags($option['label']), 40, true, true),
                    'font' => 'italic',
                    'feed' => $leftIndent,
                    'height' => 15
                ];

                if ($option['value']) {
                    if (isset($option['print_value'])) {
                        $printValue = $option['print_value'];
                    } else {
                        $printValue = $this->filterManager->stripTags($option['value']);
                    }
                    $values = explode(', ', $printValue);
                    foreach ($values as $value) {
                        $lines[][] = ['text' => $this->string->split($value, 30, true, true), 'feed' => $leftIndent+20, 'height' => 15];
                    }
                }
            }
        }

        // Image
        if ($thumbnail) {
            $image = Zend_Pdf_Image::imageWithPath($thumbnail);
            $page->drawImage($image, 15, $pdf->y-30, 65, $pdf->y+10);
        }
        $initY = $pdf->y;

        $lineBlock = ['lines' => $lines, 'height' => 20];
        $page = $pdf->drawLineBlocks($page, [$lineBlock], ['table_header' => true]);

        if ($thumbnail) {
            $endY = $pdf->y;
            $diffY = $initY - $endY;

            if ($diffY < 50) { // 50 - default image height
                $pdf->y -= (50 - $diffY);
            }
        }

        $this->setPage($page);
    }
}
