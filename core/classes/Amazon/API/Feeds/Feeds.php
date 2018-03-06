<?php

namespace Amazon\API\Feeds;

use Amazon\API\{APIMethods, APIParameters, APIParameterValidation, APIProperties};

class Feeds
{

    use APIMethods;
    use APIParameters;
    use APIProperties;
    use APIParameterValidation;

    protected static $feedType = "";
    protected static $feedContent = "";
    protected static $feed = "Feeds";
    protected static $versionDate = "2009-01-01";
    private static $overviewUrl = "http://docs.developer.amazonservices.com/en_US/feeds/Feeds_Overview.html";
    private static $libraryUpdateUrl = "http://docs.developer.amazonservices.com/en_US/feeds/Feeds_ClientLibraries.html";

}