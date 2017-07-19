<?php

namespace controllers\channels\order;


use ecommerce\Ecommerce;

class OrderController
{
    public static function createItemXmlArray($itemID, $itemDesc, $poNumber, $qty, $amount, $sku, $upc)
    {
        return [
            'Item' => [
                'ItemId' => $itemID,
                'ItemDesc' => "<![CDATA[ {$itemDesc} ]]>",
                'POLineNumber' => $poNumber,
                'UOM' => 'EACH',
                'Qty' => $qty,
                'UCValue' => Ecommerce::formatMoneyNoComma($amount),
                'UCCurrencyCode' => '',
                'RetailValue' => '',
                'RetailCurrencyCode' => '',
                'StdPackQty' => '',
                'StdContainerQty' => '',
                'SupplierItemId' => $sku,
                'BarcodeId' => $upc,
                'BarcodeType' => 'UPC',
                'ItemNote' => ''
            ]
        ];
    }
}