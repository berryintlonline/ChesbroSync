<?php

namespace am;

use controllers\channels\BuyerController;
use controllers\channels\FTPController;
use controllers\channels\tax\TaxController;
use ecommerce\Ecommerce;
use \DateTime;
use \DateTimeZone;
use models\channels\address\Address;
use models\channels\address\City;
use models\channels\address\State;
use models\channels\address\ZipCode;
use models\channels\Buyer;
use models\channels\Channel;
use models\channels\order\Order;
use models\channels\order\OrderItem;
use controllers\channels\order\OrderItemXMLController;
use controllers\channels\order\OrderXMLController;
use controllers\channels\ShippingController;
use models\channels\SKU;
use models\channels\Tax;
use controllers\channels\tax\TaxXMLController;
use controllers\channels\XMLController;

class AmazonOrder extends Amazon
{

    public function updateTrackingInfo($orderNum, $trackingID, $carrier, $num)
    {
        $xml = [
            'Message' => [
                'MessageID' => $num,
                'OrderFulfillment' => [
                    'AmazonOrderID' => $orderNum,
                    'FulfillmentDate' => gmdate("Y-m-d\TH:i:s\Z", time()),
                    'FulfillmentData' => [
                        'CarrierName' => $carrier,
                        'ShipperTrackingNumber' => $trackingID
                    ]
                ]
            ]
        ];
        $amazonFeed = XMLController::makeXML($xml);
        return $amazonFeed;
    }

    public function update_amazon_tracking($xml1)
    {
        $action = 'SubmitFeed';
        $feedtype = '_POST_ORDER_FULFILLMENT_DATA_';
        $feed = 'Feeds';
        $version = AmazonClient::getAPIFeedInfo($feed)['versionDate'];
        $whatToDo = 'POST';

        $paramAdditionalConfig = [
            'SellerId'
        ];

        $param = AmazonClient::setParams($action, $feedtype, $version, $paramAdditionalConfig);

        $xml = [
            'MessageType' => 'OrderFulfillment',
        ];
        $xml = XMLController::makeXML($xml);
        $xml .= $xml1;

        $response = AmazonClient::amazonCurl($xml, $feed, $version, $param, $whatToDo);

        return $response;
    }

    public function getOrders()
    {
        $action = 'ListOrders';
        $feedtype = '';
        $feed = 'Orders';
        $version = AmazonClient::getAPIFeedInfo($feed)['versionDate'];
        $whatToDo = 'POST';
        $paramAdditionalConfig = [
            'MarketplaceId.Id.1',
            'SellerId',
        ];

        $param = AmazonClient::setParams($action, $feedtype, $version, $paramAdditionalConfig);

        $param['OrderStatus.Status.1'] = 'Unshipped';
        $param['OrderStatus.Status.2'] = 'PartiallyShipped';
//        $param['OrderStatus.Status.1'] = 'Shipped';
//        $param['FulfillmentChannel.Channel.1'] = 'MFN';
        $from = $this->get_order_dates(AmazonClient::getStoreID());
        $from = $from['api_pullfrom'];
//        $from = "-1";
        $from .= ' days';
        $createdAfter = new DateTime($from, new DateTimeZone('America/Boise'));
        $createdAfter = $createdAfter->format("Y-m-d\TH:i:s\Z");
        $param['CreatedAfter'] = $createdAfter;

        $xml = '';

        $response = AmazonClient::amazonCurl($xml, $feed, $version, $param, $whatToDo);

        return $response;
    }

    public function getMoreOrders($nextToken)
    {
        $action = 'ListOrdersByNextToken';
        $feedtype = '';
        $feed = 'Orders';
        $version = AmazonClient::getAPIFeedInfo($feed)['versionDate'];
        $whatToDo = 'POST';
        $paramAdditionalConfig = [
            'MarketplaceId.Id.1',
            'SellerId',
        ];

        $param = AmazonClient::setParams($action, $feedtype, $version, $paramAdditionalConfig);

        $param['NextToken'] = $nextToken;

        $xml = '';

        $response = AmazonClient::amazonCurl($xml, $feed, $version, $param, $whatToDo);

        return $response;
    }

    public function getOrderItems($orderNum)
    {
        $action = 'ListOrderItems';
        $feedtype = '';
        $feed = 'Orders';
        $version = AmazonClient::getAPIFeedInfo($feed)['versionDate'];
        $whatToDo = 'POST';
        $paramAdditionalConfig = [
            'SellerId'
        ];

        $param = AmazonClient::setParams($action, $feedtype, $version, $paramAdditionalConfig);
        $param['AmazonOrderId'] = $orderNum;

        $xml = '';

        $response = AmazonClient::amazonCurl($xml, $feed, $version, $param, $whatToDo);

        return $response;
    }

    protected function ifItemsExist($orderNum, $orderId, $totalTax, $totalShipping, Ecommerce $ecommerce)
    {
        $orderItems = simplexml_load_string($this->getOrderItems($orderNum));

        if (isset($orderItems->ListOrderItemsResult->OrderItems->OrderItem)) {
            $items = $this->parseItems($orderItems->ListOrderItemsResult->OrderItems->OrderItem, $orderId, $totalTax,
                $totalShipping, $ecommerce);
            return $items;
        } else {
            sleep(2);
            $this->ifItemsExist($orderNum, $orderId, $totalTax, $totalShipping, $ecommerce);
        }
    }

    protected function parseItems($items, $orderId, $totalTax, $totalShipping, Ecommerce $ecommerce)
    {
        $totalWithoutTax = 0.00;
        $poNumber = 1;
        $itemXml = '';

        $itemObject = [];

        foreach ($items as $item) {
            $quantity = $item->QuantityOrdered;

            $title = $item->Title;
            $sku = $item->SellerSKU;
            $itemObject['sku'] = $sku;
            $upc = '';

            $itemPrice = (float)$item->ItemPrice->Amount;
            $promotionDiscount = (float)$item->PromotionDiscount->Amount;
            $itemPrice += (float)$promotionDiscount;

            $giftWrapPrice = (float)$item->GiftWrapPrice->Amount;
            $itemPrice += (float)$giftWrapPrice;

            $totalWithoutTax += (float)$itemPrice;
            $principle = Ecommerce::formatMoney((float)$itemPrice / $quantity);

            $shippingPrice = (float)$item->ShippingPrice->Amount;
            $shippingDiscount = (float)$item->ShippingDiscount->Amount;
            $shippingPrice += (float)$shippingDiscount;
            $totalShipping += (float)$shippingPrice;

            $itemTax = Ecommerce::formatMoney((float)$item->ItemTax->Amount);
            $totalTax += (float)$itemTax;

            $shippingTax = (float)$item->ShippingTax->Amount;
            $totalTax += (float)$shippingTax;

            $giftWrapTax = (float)$item->GiftWrapTax->Amount;
            $totalTax += (float)$giftWrapTax;
            $totalTax = Ecommerce::formatMoney($totalTax);
            Ecommerce::dd("Total Tax: $totalTax");

            $skuId = SKU::searchOrInsert($sku);
            if (!LOCAL) {
                OrderItem::save($orderId, $skuId, $itemPrice, $quantity);
            }
            $itemXml .= OrderItemXMLController::create($sku, $title, $poNumber, $quantity, $principle, $upc);
            $poNumber++;
        }
        $itemObject['poNumber'] = $poNumber;
        $itemObject['itemXml'] = $itemXml;
        $itemObject['totalWithoutTax'] = $totalWithoutTax;
        $itemObject['totalTax'] = $totalTax;
        $itemObject['totalShipping'] = $totalShipping;
        return (object)$itemObject;
    }

    public function parseOrders($orders, Ecommerce $ecommerce, $folder, $companyId, $nextPage = null)
    {
        $taxableStates = Tax::getCompanyInfo($companyId);

        $xmlOrders = simplexml_load_string($orders);

        $page = "ListOrdersResult";
        if ($nextPage) {
            $page = "ListOrdersByNextTokenResult";
        }
        foreach ($xmlOrders->{$page}->Orders->Order as $order) {

            $orderNum = $order->AmazonOrderId;

            $found = Order::get($orderNum);

            if (LOCAL || !$found) {

                Ecommerce::dd($order);

                $latestDeliveryDate = (string)$order->LatestDeliveryDate;
                $orderType = (string)$order->OrderType;
                $purchaseDate = rtrim((string)$order->PurchaseDate, 'Z');

                $isReplacementOrder = (string)$order->IsReplacementOrder;
                $numberOfItemsShipped = (int)$order->NumberOfItemsShipped;
                $numberOfItemsUnshipped = (int)$order->NumberOfItemsUnshipped;
                $orderStatus = (string)$order->OrderStatus;
                $salesChannel = (string)$order->SalesChannel;
                $isBusinessOrder = (string)$order->IsBusinessOrder;
                $lastUpdateDate = (string)$order->LastUpdateDate;
                $shipServiceLevel = (string)$order->ShipServiceLevel;
                $shippedByAmazonTFM = (string)$order->ShippedByAmazonTFM;
                $paymentMethodDetails = (object)$order->PaymentMethodDetails;
                $paymentMethodDetail = (string)$order->PaymentMethodDetail;
                $paymentMethod = (string)$order->PaymentMethod;
                $earliestDeliveryDate = (string)$order->EarliestDeliveryDate;
                $earliestShipDate = (string)$order->EarliestShipDate;
                $isPremiumOrder = (string)$order->IsPremiumOrder;
                $marketplaceId = (string)$order->MarketplaceId;
                $fulfillmentChannel = (string)$order->FulfillmentChannel;
                $isPrime = (string)$order->IsPrime;
                $buyer = (string)$order->BuyerName;

                $orderTotal = (object)$order->OrderTotal;
                $orderTotalAmount = (float)$order->OrderTotal->Amount;

                $shippingAddress = (object)$order->ShippingAddress;

                $shipToName = (string)$order->ShippingAddress->Name;
                list($lastName, $firstName) = BuyerController::splitName($shipToName);
                $buyerEmail = (string)$order->BuyerEmail;
                $shippingPhone = (string)$order->ShippingAddress->Phone;
                $shipByDate = (string)$order->LatestShipDate;

                $shippingAddressLine1 = (string)$order->ShippingAddress->AddressLine1;
                $shippingAddressLine2 = (string)$order->ShippingAddress->AddressLine2 ?? '';
                $shippingCity = (string)$order->ShippingAddress->City;
                $shippingState = strtolower((string)$order->ShippingAddress->StateOrRegion);
                if (strlen($shippingState) > 2) {
                    $shippingState = State::getAbbr(ucfirst($shippingState));
                }
                $shippingState = strtoupper($shippingState);
                $shippingPostalCode = (string)$order->ShippingAddress->PostalCode;
                $shippingCountryCode = (string)$order->ShippingAddress->CountryCode;
                $shippingCountryCode = (string)$shippingCountryCode == 'US' ? 'USA' : $shippingCountryCode;

                $shipmentMethod = (string)$order->ShipmentServiceLevelCategory;

                $shipping = ShippingController::code($orderTotalAmount, [], $shipmentMethod);

                $totalTax = 0.00;
                $totalShipping = 0.00;

                $custId = (new Buyer($firstName, $lastName, $shippingAddressLine1, $shippingAddressLine2, $shippingCity, $shippingState, $shippingPostalCode))->getBuyerId();
                if (!LOCAL) {
                    $orderId = Order::save(AmazonClient::getStoreID(), $custId, $orderNum, $shipping,
                        $totalShipping, $totalTax);
                }

                $items = $this->ifItemsExist($orderNum, $orderId, $totalTax, $totalShipping, $ecommerce);

                $poNumber = (string)$items->poNumber;
                $totalTax = Ecommerce::formatMoney((float)$items->totalTax);
                $totalWithoutTax = (float)$items->totalWithoutTax;
                $totalShipping = Ecommerce::formatMoney((float)$items->totalShipping);
                $sku = (string)$items->sku;
                $itemXml = (string)$items->itemXml;

                if (TaxController::state($taxableStates, $shippingState)) {
                    echo 'Should be taxed<br>';
                    if ($totalTax == 0) {
                        // No tax collected, but tax is required to remit.
                        // Need to calculate taxes and subtract from sales price of item(s)
                        $totalTax = TaxController::calculate($taxableStates[$shippingState], $totalWithoutTax,
                            $totalShipping);
                    }
                    $itemXml .= TaxXMLController::getItemXml(
                        $shippingState,
                        $poNumber,
                        $totalTax,
                        $taxableStates[$shippingState]['tax_line_name']
                    );
                }

                $orderId = Order::updateShippingAndTaxes($orderId, $totalShipping, $totalTax);
                $channelName = 'Amazon';
                $channelNum = Channel::getAccountNumbersBySku($channelName, $sku);

                $orderXml = OrderXMLController::create($channelNum, $channelName, $orderNum, $purchaseDate, $totalShipping, $shipping, $shippingPhone, $shipToName, $shippingAddressLine1, $shippingAddressLine2, $shippingCity, $shippingState, $shippingPostalCode, $shippingCountryCode, $itemXml);
                if (!LOCAL) {
                    FTPController::saveXml($orderNum, $orderXml, $folder, $channelName);
                }
            }
        }

        if (isset($xmlOrders->{$page}->NextToken)) {
            $nextToken = (string)$xmlOrders->{$page}->NextToken;
            Ecommerce::dd("Next Token:" . $nextToken);
        }
        if (isset($nextToken)) {
            $orders = $this->getMoreOrders($nextToken);

            $this->parseOrders($orders, $ecommerce, $folder, $companyId, true);
        }
    }


}