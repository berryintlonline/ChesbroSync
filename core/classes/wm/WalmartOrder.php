<?php

namespace wm;

use controllers\channels\BuyerController;
use controllers\channels\FTPController;
use ecommerce\Ecommerce;
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
use models\channels\SKU;
use models\channels\Tax;
use controllers\channels\tax\TaxXMLController;
use \Walmart\Order as WMOrder;

class WalmartOrder extends Walmart
{
    public function configure()
    {
        $wmorder = new WMOrder([
            'consumerId' => WalmartClient::getConsumerKey(),
            'privateKey' => WalmartClient::getSecretKey(),
            'wmConsumerChannelType' => WalmartClient::getAPIHeader()
        ]);
        return $wmorder;
    }

    public function acknowledge_order($order_num)
    {
        $wmorder = $this->configure();
        $poId = $order_num;
        $orderAcknowledge = $wmorder->acknowledge([
            'purchaseOrderId' => $poId,
        ]);
        return $orderAcknowledge;
    }

    public function get_wm_order($order)
    {
        $orderNum = $order['purchaseOrderId'];
        $channelName = 'Walmart';
        $purchaseDate = (string)$order['orderDate'];

        $tax = 0;
        $shippingPrice = 0;
        $shippingCode = 'ZSTD';
        $orderTotal = 0;

        echo "<br><br>Order: $orderNum<br><pre>";
        print_r($order);
        echo '</pre><br><br>';

        if (array_key_exists('lineNumber', $order['orderLines']['orderLine'])) {
            $orderInfo = $this->process_orders($order['orderLines']['orderLine'], $tax, $shippingPrice, $orderTotal);
            $tax += $orderInfo['total_tax'];
            $shippingPrice += $orderInfo['shipping_total'];
            $orderTotal += $orderInfo['order_total'];

            $orderItems = $order['orderLines'];
        } else {
            foreach ($order['orderLines']['orderLine'] as $o) {
                $orderInfo = $this->process_orders($o, $tax, $shippingPrice, $orderTotal);
                $tax += $orderInfo['total_tax'];
                $shippingPrice += $orderInfo['shipping_total'];
                $orderTotal += $orderInfo['order_total'];
            }
            $orderItems = $order['orderLines']['orderLine'];
        }

        if ($orderTotal > 299) {
            $shippingCode = 'URIP';
        }

        //Address
        $streetAddress = (string)$order['shippingInfo']['postalAddress']['address1'];
        $streetAddress2 = (string)$order['shippingInfo']['postalAddress']['address2'] ?? '';
        $city = (string)$order['shippingInfo']['postalAddress']['city'];
        $state = (string)$order['shippingInfo']['postalAddress']['state'];
        $zipCode = (string)$order['shippingInfo']['postalAddress']['postalCode'];
        $country = (string)$order['shippingInfo']['postalAddress']['country'];


        //Buyer
        $shipToName = (string)$order['shippingInfo']['postalAddress']['name'];
        $phone = (string)$order['shippingInfo']['phone'];
        list($lastName, $firstName) = BuyerController::splitName($shipToName);
        $buyer = new Buyer($firstName, $lastName, $streetAddress, $streetAddress2, $city, $state, $zipCode, $country, $phone);

        $Order = new Order($channelName, WalmartClient::getStoreID(), $buyer, $orderNum, $purchaseDate, $shippingCode, $shippingPrice, $tax);

        //Save Orders
        if (!LOCAL) {
//            $orderID = Order::save(WalmartClient::getStoreID(), $buyerID, $orderNum, $shippingCode, $shippingPrice, $tax);
            $Order->save(WalmartClient::getStoreID());
        }
        $infoArray = $this->get_wm_order_items($orderItems, $state, $tax, $Order);
        $itemXml = $infoArray['item_xml'];
        $orderXml = $this->save_wm_order_to_xml($order, $itemXml, $Order, $buyer);

        if (!LOCAL) {
            FTPController::saveXml($orderNum, $orderXml, $channelName);
        }
    }

    public function process_orders($order, $totalTax, $shippingTotal, $orderTotal)
    {
//        echo '<br><br>Order Items:<br><pre>';
//        print_r($order);
//        echo '</pre><br><br>';
        if (array_key_exists('tax', $order['charges']['charge'])) {
            foreach ($order['charges'] as $t) {
                $totalTax += number_format($t['tax']['taxAmount']['amount'], 2, '.', '');
//                echo "Taxes: $total_tax <br>";
            }
        }

        foreach ($order['charges'] as $p) {
            $orderTotal += $p['chargeAmount']['amount'];
        }

        foreach ($order['charges'] as $s) {
            if (in_array('SHIPPING', $s)) {
                foreach ($s['charges'] as $sa) {
                    if ($sa['chargeType'] == 'SHIPPING') {
                        $shippingTotal += number_format($sa['chargeAmount']['amount'], 2, '.', '');
                    }
                }
            }
        }
        return [
            'total_tax' => $totalTax,
            'shipping_total' => $shippingTotal,
            'order_total' => $orderTotal];
    }

    /**
     * @param $wm_consumer_key
     * @param $wm_secret_key
     * @param $order_num
     * @param $order_items
     * @param $state_code
     * @param $total_tax
     * @param $orderID
     * @return array
     */
    public function get_wm_order_items($order_items, $state_code, $total_tax, $Order)
    {
        $wminv = new WalmartInventory();
        $item_xml = '';
        $poNumber = 1;

        foreach ($order_items as $i) {
//            echo '<br><br><pre>';
//            print_r($i);
//            echo '</pre><br><br>';
            $quantity = $i['orderLineQuantity']['amount'];
            $title = $i['item']['productName'];
            $price = 0;
            foreach ($i['charges'] as $p) {
                if ($p['chargeType'] == 'PRODUCT') {
                    $price += $p['chargeAmount']['amount'];
                }
            }
            $price = sprintf("%01.2f", number_format($price, 2, '.', '') / $quantity);
            echo "Item Total: $price";
            $sku = $i['item']['sku'];
            $item = $wminv->getItem($sku);
            $upc = $item['MPItemView']['upc'];
            $skuID = SKU::searchOrInsert($sku);
            $orderItem = new OrderItem($sku, $title, $quantity, $price, $upc, $poNumber);
            if (!LOCAL) {
//                OrderItem::save($orderID, $skuID, $price, $quantity);
                $orderItem->save($Order);
            }
            $item_xml .= OrderItemXMLController::create($orderItem);
            $poNumber++;
        }
        $item_xml .= TaxXMLController::getItemXml($state_code, $poNumber, $total_tax);
        $info_array = [
            'item_xml' => $item_xml
        ];
        return $info_array;
    }

    public function save_wm_order_to_xml($order, $itemXML, Order $Order, Buyer $buyer)
    {
        $sku = $order['orderLines']['orderLine']['item']['sku'];
        $channelNumber = Channel::getAccountNumbersBySku($Order->getChannelName(), $sku);
        $xml = OrderXMLController::create($channelNumber, $Order, $buyer, $itemXML);
        return $xml;
    }

    public function updateWalmartTracking($order_num, $tracking_id, $carrier)
    {
        $wmorder = $this->configure();
        $order = $wmorder->get([
            'purchaseOrderId' => $order_num
        ]);
//        print_r($order);
        if (isset($order['orderLines']['orderLine']['orderLineStatuses']['orderLineStatus']['trackingInfo']) && array_key_exists('trackingInfo', $order['orderLines']['orderLine']['orderLineStatuses']['orderLineStatus'])) {
            return $order;
        }
        echo '<br><br>';
        $date = date("Y-m-d") . "T" . date("H:i:s") . "Z";
        echo "Date: $date<br><br>";
//        $order_num = $order['purchaseOrderId'];
        $trackingURL = '';
        if ($carrier == 'USPS') {
            $trackingURL = "https://tools.usps.com/go/TrackConfirmAction.action";
        } elseif ($carrier == 'UPS') {
            $trackingURL = "http://wwwapps.ups.com/WebTracking/track";
        }
        Ecommerce::dd($order);
        if (array_key_exists('lineNumber', $order['orderLines']['orderLine'])) {
            $tracking = $this->process_tracking($order['orderLines'], $order_num, $date, $carrier, $tracking_id, $trackingURL);
        } else {
            foreach ($order['orderLines']['orderLine'] as $o) {
                $tracking = $this->process_tracking($order['orderLines']['orderLine'], $order_num, $date, $carrier, $tracking_id, $trackingURL);
            }
        }

        return $tracking;
    }

    public function process_tracking($order, $order_num, $date, $carrier, $tracking_id, $trackingURL)
    {
        foreach ($order as $o) {
            $lineNumber = $o['lineNumber'];
            $quantity = $o['orderLineQuantity']['amount'];
            $wmorder = $this->configure();
            try {
                $tracking = $wmorder->ship(
                    $order_num,
                    $this->create_tracking_array($lineNumber, $quantity, $date, $carrier, $tracking_id, $trackingURL)
                );
            } catch (Exception $e) {
                die("There was a problem requesting the data: " . $e->getMessage());
            }
            print_r($tracking);
        }
        return $tracking;
    }

    public function create_tracking_array($lineNumber, $quantity, $date, $carrier, $tracking_id, $trackingURL)
    {
        $tracking = [
            'orderShipment' => [
                'orderLines' => [
                    [
                        'lineNumber' => $lineNumber,
                        'orderLineStatuses' => [
                            [
                                'status' => 'Shipped',
                                'statusQuantity' => [
                                    'unitOfMeasurement' => 'Each',
                                    'amount' => $quantity
                                ],
                                'trackingInfo' => [
                                    'shipDateTime' => $date,
                                    'carrierName' => [
                                        'carrier' => $carrier
                                    ],
                                    'methodCode' => 'Standard',
                                    'trackingNumber' => $tracking_id,
                                    'trackingURL' => $trackingURL
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
        return $tracking;
    }

    protected function parseOrder($order, WalmartOrder $wmord)
    {
        Ecommerce::dd($order);
        $order_num = $order['purchaseOrderId'];
        echo "Order: $order_num<br><br>";
        $found = Order::get($order_num);
        if (LOCAL || !$found) {
            if (!LOCAL) {
                $acknowledged = $wmord->acknowledge_order($order_num);
            }
//        echo 'Acknowledgement: <br><pre>';
//        print_r($acknowledged);
//        echo '</pre><br><br>';
            if ((array_key_exists('orderLineStatuses', $acknowledged['orderLines']['orderLine']) &&
                    $acknowledged['orderLines']['orderLine']['orderLineStatuses']['orderLineStatus']['status'] == 'Acknowledged')
                || $acknowledged['orderLines']['orderLine'][0]['orderLineStatuses']['orderLineStatus']['status'] == 'Acknowledged'
            ) {
                $wmord->get_wm_order($order);
            }
        }
    }

    public function getOrders($wmorder, $wmord, $next = null)
    {
        try {
            $fromDate = '-3 days';

            if (!empty($next)) {
                $orders = $wmorder->list([
                    'createdStartDate' => date('Y-m-d', strtotime($fromDate)),
                    'nextCursor' => $next
                ]);
            } else {
                $orders = $wmorder->listAll([
                    'createdStartDate' => date('Y-m-d', strtotime($fromDate)),
//                'limit' => 200
                ]);
            }
            Ecommerce::dd($orders);

            echo 'Orders: <br>';
            $totalCount = $orders['meta']['totalCount'];
            echo "Order Count: $totalCount<br><br>";


            if ($totalCount > 1) { // if there are multiple orders to pull **DO NOT CHANGE**
                echo "Multiple Orders<br>";
                foreach ($orders['elements']['order'] as $order) {
                    $this->parseOrder($order, $wmord);
                }
            } else {
                echo "Single Order:<br>";
                foreach ($orders['elements'] as $order) {
                    $this->parseOrder($order, $wmord);
                }
            }
        } catch (Exception $e) {
            die("There was a problem requesting the data: " . $e->getMessage());
        }
    }
}