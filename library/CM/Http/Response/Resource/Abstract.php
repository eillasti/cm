<?php

abstract class CM_Http_Response_Resource_Abstract extends CM_Http_Response_Abstract {

    public function __construct(CM_Http_Request_Abstract $request, CM_Service_Manager $serviceManager) {
        parent::__construct($request, $serviceManager);
        $timestamp = $this->_request->popPathPart();
    }

    protected function _setContent($content) {
        $this->setHeader('Access-Control-Allow-Origin', $this->getSite()->getUrlBase());
        $this->setHeaderExpires(86400 * 365);

        parent::_setContent($content);
    }
}
