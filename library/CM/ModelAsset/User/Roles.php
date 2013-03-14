<?php

class CM_ModelAsset_User_Roles extends CM_ModelAsset_User_Abstract {

	public function _loadAsset() {
	}

	public function _onModelDelete() {
		CM_Mysql::delete(TBL_CM_ROLE, array('userId' => $this->_model->getId()));
	}

	/**
	 * @param int $role
	 * @return int|null
	 */
	public function getStartStamp($role) {
		return $this->_get($role, 'startStamp');
	}

	/**
	 * @param int $role
	 * @return int|null
	 */
	public function getExpirationStamp($role) {
		return $this->_get($role, 'expirationStamp');
	}

	/**
	 * @param int $role...
	 * @return boolean
	 */
	public function contains($role) {
		$roles = func_get_args();
		foreach ($roles as $role) {
			if (array_key_exists((int) $role, $this->_getAll())) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function get() {
		return array_keys($this->_getAll());
	}

	/**
	 * @param int      $role
	 * @param int|null $duration
	 */
	public function add($role, $duration = null) {
		$role = (int) $role;
		if (null !== $duration) {
			$duration = (int) $duration;
		}
		self::deleteOld($this->_model);
		if ($duration) {
			CM_Db_Db::exec('
				INSERT INTO TBL_CM_ROLE (`userId`, `role`, `startStamp`, `expirationStamp`)
				VALUES(?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE `expirationStamp` = `expirationStamp` + ?',
				array($this->_model->getId(), $role, time(), time() + $duration, $duration));
		} else {
			CM_Db_Db::insert(TBL_CM_ROLE, array('userId', 'role', 'startStamp'),
				array($this->_model->getId(), $role, time()), array('expirationStamp' => null));
		}
		$this->_change();
	}

	/**
	 * @param int $role
	 */
	public function delete($role) {
		CM_Mysql::delete(TBL_CM_ROLE, array('userId' => $this->_model->getId(), 'role' => $role));
		$this->_change();
	}

	/**
	 * @return array
	 */
	public function getDefault() {
		return $this->_model->getDefaultRoles();
	}

	/**
	 * @param int    $role
	 * @param string $key
	 * @return mixed|null
	 * @throws CM_Exception_Invalid
	 */
	private function _get($role, $key) {
		if (!$this->contains($role)) {
			throw new CM_Exception_Invalid('User `' . $this->_model->getId() . '` does not have the role `' . $role . '`');
		}
		$values = $this->_getAll();
		if (!isset($values[$role][$key])) {
			return null;
		}
		return $values[$role][$key];
	}

	private function _getAll() {
		if (($values = $this->_cacheGet('roles')) === false) {
			$values = CM_Db_Db::select(TBL_CM_ROLE, array('role', 'startStamp', 'expirationStamp'),
					'`userId`=' . $this->_model->getId() . ' AND (`expirationStamp` > ' . time() . ' OR `expirationStamp` IS NULL)')
					->fetchAllTree();
			$this->_cacheSet('roles', $values);
		}
		foreach ($this->getDefault() as $role) {
			$values[$role] = array(null, null);
		}
		return $values;
	}

	/**
	 * @param CM_Model_User $user OPTIONAL
	 */
	public static function deleteOld(CM_Model_User $user = null) {
		$userWhere = $user ? ' AND `userId` = ' . (int) $user->getId() : '';
		$result = CM_Db_Db::exec("SELECT `userId`, `role` FROM TBL_CM_ROLE WHERE `expirationStamp` < ?" . $userWhere, array(time()));
		while ($row = $result->fetch()) {
			$user = CM_Model_User::factory($row['userId']);
			$user->getRoles()->delete($row['role']);
			$user->getSite()->getEventHandler()->trigger('roleExpired', array('user' => $user, 'role' => $row['role']));
		}
	}
}
