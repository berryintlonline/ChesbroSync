<?php

namespace Amazon\API\Finances;

class GetServiceStatus extends Finances
{

    protected static $requestQuota = 2;
    protected static $restoreRate = 1;
    protected static $restoreRateTime = 5;
    protected static $restoreRateTimePeriod = "minute";
    protected static $action = "GetServiceStatus";
    protected static $method = "POST";
    private static $curlParameters = [];
    private static $apiUrl = "";
    protected static $requiredParameters = [];
    protected static $allowedParameters = [];
    protected static $parameters = [];

    public function __construct($parametersToSet = null)
    {

        static::setParameters($parametersToSet);

        static::verifyParameters();

    }

}