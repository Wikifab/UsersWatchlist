<?php
/**
 * @file
 * @ingroup SF
 */

/**
 * add fonctions to add/remove users in users watchlist
 *
 * @ingroup SF
 *
 * @author Pierre Boutet
 */
class ApiUsersWatchList extends ApiBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName );
	}

	public function getAllowedParams() {
		return array(
				'user' => array (
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => true
				),
				'watch' => array (
						ApiBase::PARAM_TYPE => 'string',
						ApiBase::PARAM_REQUIRED => false
				),
		);
	}

	public function getParamDescription() {
		return [];
	}

	public function getDescription() {
		return false;
	}

	public function execute() {

		$params = $this->extractRequestParams();

		$userToWatch = $params['user'];

		$user = $this->getUser();

		$core = new UsersWatchListCore();

		if (isset($params['watch']) && $params['watch'] == 'no'){
			$result = $core->unfollowUsers( $user, [$userToWatch] );
		} else {
			$result = $core->followUsers( $user, [$userToWatch] );
		}

		$r=[];
		if($result) {
			$r['success'] = 1;
			$r['result'] = 'OK';
			$r['detail'] = $result;
		} else {
			$r['result'] = 'fail';
			$r['detail'] = $result;
		}

		$this->getResult()->addValue(null, $this->getModuleName(), $r);
	}

	public function needsToken() {
		return 'csrf';
	}
}