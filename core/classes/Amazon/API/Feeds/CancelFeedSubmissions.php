<?php

namespace Amazon\API\Feeds;

class CancelFeedSubmissions extends Feeds
{

    protected static $requestQuota = 10;
    protected static $restoreRate = 1;
    protected static $restoreRateTime = 45;
    protected static $restoreRateTimePeriod = "second";
    protected static $hourlyRequestQuota = 80;
    protected static $action = "CancelFeedSubmissions";
    protected static $method = "POST";
    private static $curlParameters = [];
    private static $apiUrl = "http://docs.developer.amazonservices.com/en_US/feeds/Feeds_CancelFeedSubmissions.html";
    protected static $requiredParameters = [
        "MarketplaceId",
        "SellerId"
    ];
    protected static $allowedParameters = [
        "FeedSubmissionIdList.Id",
        "FeedTypeList",
        "SubmittedFromDate",
        "SubmittedToDate"
    ];

    public function __construct($parametersToSet = null)
    {

        static::setParameters($parametersToSet);

        static::verifyParameters();

    }

}