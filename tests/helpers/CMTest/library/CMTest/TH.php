<?php

class CMTest_TH {

    private static $_timeStart;
    private static $timeDelta = 0;
    private static $_configBackup;

    public static function init() {
        $output = new CM_OutputStream_Null();
        $loader = CM_App::getInstance()->getProvisionLoader();
        $loader->unload($output);
        $loader->load($output);

        self::$_configBackup = serialize(CM_Config::get());

        // Reset environment
        self::clearEnv();
        self::randomizeAutoincrement();
        self::timeInit();
    }

    public static function clearEnv() {
        self::clearDb();
        self::clearCache();
        self::timeReset();
        self::clearFilesystem();
        CM_Service_Manager::getInstance()->resetServiceInstances();
        CM_Config::set(unserialize(self::$_configBackup));
    }

    public static function clearFilesystem() {
        $script = new CM_File_Filesystem_SetupScript(CM_Service_Manager::getInstance());
        $script->unload(new CM_OutputStream_Null());
    }

    public static function clearCache() {
        CM_Cache_Shared::getInstance()->flush();
        CM_Cache_Local::getInstance()->flush();
    }

    public static function timeInit() {
        if (!isset(self::$_timeStart)) {
            runkit_function_copy('time', 'time_original');
            runkit_function_redefine('time', '', 'return CMTest_TH::time();');
        }
        self::$_timeStart = time_original();
        self::$timeDelta = 0;
    }

    public static function time() {
        return self::$_timeStart + self::$timeDelta;
    }

    public static function timeForward($sec) {
        self::$timeDelta += $sec;
    }

    public static function timeDaysForward($days) {
        self::timeForward($days * 24 * 60 * 60);
    }

    public static function timeReset() {
        self::$timeDelta = 0;
    }

    public static function timeDelta() {
        return self::$timeDelta;
    }

    public static function timeDiffInDays($stamp1, $stamp2) {
        return round(($stamp2 - $stamp1) / (60 * 60 * 24));
    }

    /**
     * @return CM_Service_Manager
     */
    public static function getServiceManager() {
        return CM_Service_Manager::getInstance();
    }

    /**
     * @return CM_Model_User
     */
    public static function createUser() {
        return CM_Model_User::createStatic();
    }

    /**
     * @param string|null $abbreviation
     * @return CM_Model_Language
     */
    public static function createLanguage($abbreviation = null) {
        if (!$abbreviation) {
            do {
                $abbreviation = self::_randStr(5);
            } while (CM_Model_Language::findByAbbreviation($abbreviation));
        }
        return CM_Model_Language::create('English', $abbreviation, true);
    }

    /**
     * @param string             $pageClass
     * @param CM_Model_User|null $viewer OPTIONAL
     * @param array              $params OPTIONAL
     * @return CM_Page_Abstract
     */
    public static function createPage($pageClass, CM_Model_User $viewer = null, $params = array()) {
        $request = new CM_Http_Request_Get('?' . http_build_query($params), array(), $viewer);
        return new $pageClass(CM_Params::factory($request->getQuery(), true), $request->getViewer());
    }

    /**
     * @param CM_Model_User|null $user
     * @return CM_Session
     */
    public static function createSession(CM_Model_User $user = null) {
        if (is_null($user)) {
            $user = self::createUser();
        }
        $session = new CM_Session();
        $session->setUser($user);
        $session->write();
        return $session;
    }

    /**
     * @param int|null    $type
     * @param int|null    $adapterType
     * @param string|null $mediaId
     * @return CM_Model_StreamChannel_Abstract
     */
    public static function createStreamChannel($type = null, $adapterType = null, $mediaId = null) {
        if (null === $type) {
            $type = CM_Model_StreamChannel_Media::getTypeStatic();
        }

        if (null === $adapterType) {
            $adapterType = CM_Wowza_Service::getTypeStatic();
        }

        $data = array('key' => rand(1, 10000) . '_' . rand(1, 100));
        if (CM_Model_StreamChannel_Media::getTypeStatic() == $type) {
            $mediaId = (null !== $mediaId) ? (string) $mediaId : null;
            $data['width'] = 480;
            $data['height'] = 720;
            $data['serverId'] = 1;
            $data['adapterType'] = $adapterType;
            $data['mediaId'] = $mediaId;
        }
        return CM_Model_StreamChannel_Abstract::createType($type, $data);
    }

    /**
     * @param CM_Model_StreamChannel_Media|null $streamChannel
     * @param CM_Model_User|null                $user
     * @param string|null                       $filename
     * @return CM_Model_StreamChannelArchive_Media
     */
    public static function createStreamChannelVideoArchive(CM_Model_StreamChannel_Media $streamChannel = null, CM_Model_User $user = null, $filename = null) {
        if (is_null($streamChannel)) {
            $streamChannel = static::createStreamChannel();
            static::createStreamPublish($user, $streamChannel);
        }
        if (!$streamChannel->hasStreamPublish()) {
            static::createStreamPublish($user, $streamChannel);
        }
        if (null !== $filename) {
            $filename = (string) $filename;
        }
        return CM_Model_StreamChannelArchive_Media::createStatic(array('streamChannel' => $streamChannel, 'file' => $filename));
    }

    /**
     * @param CM_Model_User|null                   $user
     * @param CM_Model_StreamChannel_Abstract|null $streamChannel
     * @return CM_Model_Stream_Publish
     */
    public static function createStreamPublish(CM_Model_User $user = null, CM_Model_StreamChannel_Abstract $streamChannel = null) {
        if (!$user) {
            $user = self::createUser();
        }
        if (is_null($streamChannel)) {
            $streamChannel = self::createStreamChannel();
        }
        return CM_Model_Stream_Publish::createStatic(array(
            'streamChannel' => $streamChannel,
            'user'          => $user,
            'start'         => time(),
            'key'           => rand(1, 10000) . '_' . rand(1, 100),
        ));
    }

    /**
     * @param CM_Model_User|null                   $user
     * @param CM_Model_StreamChannel_Abstract|null $streamChannel
     * @return CM_Model_Stream_Subscribe
     */
    public static function createStreamSubscribe(CM_Model_User $user = null, CM_Model_StreamChannel_Abstract $streamChannel = null) {
        if (is_null($streamChannel)) {
            $streamChannel = self::createStreamChannel();
        }
        return CM_Model_Stream_Subscribe::createStatic(array('streamChannel' => $streamChannel, 'user' => $user, 'start' => time(),
                                                             'key'           => rand(1, 10000) . '_' . rand(1, 100)));
    }

    /**
     * @param string             $uri
     * @param array|null         $headers
     * @param CM_Model_User|null $viewer
     * @return CM_Http_Response_Page
     */
    public static function createResponsePage($uri, array $headers = null, CM_Model_User $viewer = null) {
        if (!$headers) {
            $site = CM_Site_Abstract::factory();
            $headers = array('host' => $site->getHost());
        }
        $request = new CM_Http_Request_Get($uri, $headers, null, $viewer);
        return new CM_Http_Response_Page($request, self::getServiceManager());
    }

    /**
     * @param string                $uri
     * @param array|null            $headers
     * @param CM_Model_User|null    $viewer
     * @param CM_Site_Abstract|null $site
     * @return CM_Http_Response_Page
     * @throws CM_Class_Exception_TypeNotConfiguredException
     */
    public static function createResponsePageEmbed($uri, array $headers = null, CM_Model_User $viewer = null, CM_Site_Abstract $site = null) {
        if (null === $site) {
            $site = CM_Site_Abstract::factory();
        }
        if (!$headers) {
            $headers = array('host' => $site->getHost());
        }
        $request = new CM_Http_Request_Get($uri, $headers, null, $viewer);
        return new CM_Http_Response_Page_Embed($request, $site, self::getServiceManager());
    }

    /**
     * @param int|null $level
     * @return CM_Model_Location
     */
    public static function createLocation($level = null) {
        $country = CM_Db_Db::insert('cm_model_location_country', array('abbreviation' => 'FOO', 'name' => 'countryFoo'));
        $state = CM_Db_Db::insert('cm_model_location_state', array('countryId' => $country, 'name' => 'stateFoo'));
        $city = CM_Db_Db::insert('cm_model_location_city', array('stateId' => $state, 'countryId' => $country, 'name' => 'cityFoo', 'lat' => 10,
                                                                 'lon'     => 15));
        $zip = CM_Db_Db::insert('cm_model_location_zip', array('cityId' => $city, 'name' => '1000', 'lat' => 10, 'lon' => 15));

        CM_Model_Location::createAggregation();

        switch ($level) {
            case CM_Model_Location::LEVEL_COUNTRY:
                return new CM_Model_Location(CM_Model_Location::LEVEL_COUNTRY, $country);

            case CM_Model_Location::LEVEL_CITY:
                return new CM_Model_Location(CM_Model_Location::LEVEL_CITY, $city);

            case CM_Model_Location::LEVEL_STATE:
                return new CM_Model_Location(CM_Model_Location::LEVEL_STATE, $state);

            default:
                return new CM_Model_Location(CM_Model_Location::LEVEL_ZIP, $zip);
        }
    }

    /**
     * @return CM_Model_Currency
     */
    public static function createDefaultCurrency() {
        $defaultCurrencyConfig = CM_Config::get()->CM_Model_Currency->default;
        if (!$defaultCurrency = CM_Model_Currency::findByAbbreviation($defaultCurrencyConfig['abbreviation'])) {
            $defaultCurrency = CM_Model_Currency::create($defaultCurrencyConfig['code'], $defaultCurrencyConfig['abbreviation']);
        }
        return $defaultCurrency;
    }

    /**
     * @return CM_MongoDb_Client
     */
    public static function getMongoDb() {
        return CM_Service_Manager::getInstance()->getMongoDb();
    }

    public static function randomizeAutoincrement() {
        $tables = CM_Db_Db::exec('SHOW TABLES')->fetchAllColumn();
        foreach ($tables as $table) {
            if (CM_Db_Db::exec("SHOW COLUMNS FROM `" . $table . "` WHERE `Extra` = 'auto_increment'")->fetch()) {
                CM_Db_Db::exec("ALTER TABLE `" . $table . "` AUTO_INCREMENT = " . rand(1, 1000));
            }
        }
    }

    /**
     * @param CM_Model_Abstract $model
     */
    public static function reinstantiateModel(CM_Model_Abstract &$model) {
        $model = CM_Model_Abstract::factoryGeneric($model->getType(), $model->getIdRaw());
    }

    /**
     * @param string $className
     * @param string $methodName
     * @return ReflectionMethod
     */
    public static function getProtectedMethod($className, $methodName) {
        $class = new ReflectionClass($className);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * @param string|object $objectOrClassName
     * @param string        $methodName
     * @param array|null    $args
     * @return mixed
     */
    public static function callProtectedMethod($objectOrClassName, $methodName, array $args = null) {
        $args = (array) $args;
        $context = null;
        if (is_object($objectOrClassName)) {
            $context = $objectOrClassName;
        }
        $reflectionMethod = new ReflectionMethod($objectOrClassName, $methodName);
        $reflectionMethod->setAccessible(true);
        return $reflectionMethod->invokeArgs($context, $args);
    }

    /**
     * @param int    $length
     * @param string $charset
     * @return string
     */
    private static function _randStr($length, $charset = 'abcdefghijklmnopqrstuvwxyz0123456789') {
        $str = '';
        $count = strlen($charset);
        while ($length--) {
            $str .= $charset[mt_rand(0, $count - 1)];
        }
        return $str;
    }

    private static function clearDb() {
        self::clearCache();
        CM_App::getInstance()->getProvisionLoader()->reload(new CM_OutputStream_Null());
    }
}
