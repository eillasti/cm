<?php

class CMService_Inspectlet_Client implements CM_Service_Tracking_ClientInterface {

    /** @var int */
    protected $_code;

    /** @var string|null */
    protected $_identity;

    /**
     * @param int $code
     */
    public function __construct($code) {
        $this->_code = (int) $code;
    }

    public function getHtml(CM_Frontend_Environment $environment) {
        $html = <<<EOF
<script type="text/javascript" id="inspectletjs">
window.__insp = window.__insp || [];
__insp.push(['wid', {$this->_getCode()}]);
(function() {
function __ldinsp(){var insp = document.createElement('script'); insp.type = 'text/javascript'; insp.async = true; insp.id = "inspsync"; insp.src = ('https:' == document.location.protocol ? 'https' : 'http') + '://cdn.inspectlet.com/inspectlet.js'; var x = document.getElementsByTagName('script')[0]; x.parentNode.insertBefore(insp, x); }
if (window.attachEvent) window.attachEvent('onload', __ldinsp);
else window.addEventListener('load', __ldinsp, false);
})();
EOF;
        if ($user = $environment->getViewer()) {
            $this->_setUser($user);
        }
        $html .= $this->getJs();
        $html .= '</script>';
        return $html;
    }

    /**
     * @return string
     */
    public function getJs() {
        $js = '';
        if (isset($this->_identity)) {
            $js .= "__insp.push(['identify', '{$this->_identity}']);";
        }
        return $js;
    }

    public function trackAction(CM_Action_Abstract $action) {
    }

    public function trackAffiliate($requestClientId, $affiliateName) {
    }

    public function trackPageView(CM_Frontend_Environment $environment, $path) {
        if ($viewer = $environment->getViewer()) {
            $this->_setUser($viewer);
        }
    }

    public function trackSplittest(CM_Splittest_Fixture $fixture, CM_Model_SplittestVariation $variation) {
    }

    /**
     * @return string
     */
    protected function _getCode() {
        return $this->_code;
    }

    /**
     * @return string|null
     */
    protected function _getIdentity() {
        return $this->_identity;
    }

    /**
     * @param string $identity
     */
    protected function _setIdentity($identity) {
        $this->_identity = (string) $identity;
    }

    /**
     * @param CM_Model_User $user
     */
    protected function _setUser(CM_Model_User $user) {
        $this->_setIdentity($user->getDisplayName());
    }
}
