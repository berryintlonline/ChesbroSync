<?php

namespace Amazon\API\Orders;

class ListOrderItemsByNextToken extends Orders
{

    protected static $requestQuota = 30;
    protected static $restoreRate = 1;
    protected static $restoreRateTime = 2;
    protected static $retoreRateTimePeriod = "second";
    protected static $action = "ListOrderItemsByNextToken";
    protected static $method = "POST";
    private static $curlParameters = [];
    private static $apiUrl = "http://docs.developer.amazonservices.com/en_US/orders-2013-09-01/Orders_ListOrderItemsByNextToken.html";
    protected static $requiredParameters = [
        "SellerId",
        "NextToken"
    ];
    protected static $allowedParameters = [];

    public function __construct($nextItemToken)
    {

        static::setParameters();

        static::setParameterByKey("NextToken", $nextItemToken);

        static::verifyParameters();

    }

}