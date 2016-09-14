<?php

/**
 * core opération for users Watch list
 *
 * @author Pierre Boutet
 */
class UsersWatchListCore  {


	/**
	 *	return an instance of UserWatchlistCore
	 *
	 * @return UsersWatchListCore
	 */
	public static function getInstance() {
		static $instance = null;
		if (!$instance) {
			$instance = new UsersWatchListCore();
		}
		return $instance;
	}

	/**
	 * Prepare a list of titles on a user's userswatchlist (excluding talk pages)
	 * and return an array of (prefixed) strings
	 *
	 * @return array
	 */
	public function getUsersWatchList(User $user) {
		$list = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			'userswatchlist',
			array(
				'fl_user_followed'
			), array(
				'fl_user' => $user->getId(),
			),
			__METHOD__
		);

		$users = array();
		if ( $res->numRows() > 0 ) {
			foreach ( $res as $row ) {
				$users[] = User::whoIs( $row->fl_user_followed  );
			}
			$res->free();
		}

		return $users;
	}

	public function getUserCounters($user) {

		$dbr = wfGetDB( DB_MASTER );

		if ( !$user instanceof User ) {
			$user = User::newFromName($user);
		}
		$following = 0;
		$followers = 0;

		// get following counters :
		$res = $dbr->select(
				'userswatchlist',
				array(
						'count' => 'count(*)'
				), array(
						'fl_user' => $user->getId(),
				),
				__METHOD__
				);
		if ( $res->numRows() > 0 ) {
			foreach ( $res as $row ) {
				$following = $row->count ;
			}
			$res->free();
		}

		// get followers counters :
		$res = $dbr->select(
				'userswatchlist',
				array(
						'count' => 'count(*)'
				), array(
						'fl_user_followed' => $user->getId(),
				),
				__METHOD__
				);
		if ( $res->numRows() > 0 ) {
			foreach ( $res as $row ) {
				$followers = $row->count;
			}
			$res->free();
		}

		return [
				'following' => $following,
				'followers' => $followers,
		];
	}

	public function getUserIsFollowing($user, $userFollowed) {
		static $followedUsers = [];

		if ( !$user instanceof User ) {
			$user = User::newFromName($user);
		}
		if ( !$userFollowed instanceof User ) {
			$userFollowed = User::newFromName($userFollowed);
		}

		$userId = $user->getId();

		if( ! isset($followedUsers[$userId])) {
			$followedUsers[$userId] = $this->getUsersWatchListInfo($user);
		}

		foreach ($followedUsers[$userId] as $userB) {
			if ($userB->getId() == $userFollowed->getId()) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get a list of ids of users watching the given user
	 *
	 * @param int $userId
	 * @return int[]
	 */
	public function getFollowersIds($userId) {
		$users = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
				array( 'userswatchlist', 'user' ),
				array( 'fl_user', 'user_name', 'user_id' ),
				array( 'fl_user_followed' => $userId ),
				__METHOD__,
				array('ORDER BY' => array( 'user_name' )),
				array(
						'user' => array( 'INNER JOIN', array(
								'fl_user_followed=user_id'
						) )
				)
		);

		if ( $res->numRows() > 0 ) {
			$users = array();
			foreach ( $res as $row ) {
				$users[] = $row->fl_user;
			}
			$res->free();
		}

		return $users;
	}

	/**
	 * Get a list of titles of user watching the given user
	 *
	 * @return array
	 */
	public function getUsersFollowersInfo(User $user) {

		//TODO : could be refactor to use $this->getFollowersIds()

		$users = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			array( 'userswatchlist', 'user' ),
			array( 'fl_user', 'user_name', 'user_id' ),
			array( 'fl_user_followed' => $user->getId() ),
			__METHOD__,
			array('ORDER BY' => array( 'user_name' )),
			array(
				 'user' => array( 'INNER JOIN', array(
					'fl_user_followed=user_id'
				 ) )
			)
		);

		if ( $res->numRows() > 0 ) {
			$users = array();
			foreach ( $res as $row ) {
				$users[] = User::newFromId( $row->fl_user );
			}
			$res->free();
		}

		return $users;
	}

	/**
	 * Get a list of titles on a user's userswatchlist
	 *
	 * @return array
	 */
	public function getUsersWatchListInfo(User $user) {
		$users = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			array( 'userswatchlist', 'user' ),
			array( 'fl_user_followed', 'user_name', 'user_id' ),
			array( 'fl_user' => $user->getId() ),
			__METHOD__,
			array('ORDER BY' => array( 'user_name' )),
			array(
				 'user' => array( 'INNER JOIN', array(
					'fl_user_followed=user_id'
				 ) )
			)
		);

		if ( $res->numRows() > 0 ) {
			$users = array();
			foreach ( $res as $row ) {
				$users[] = User::newFromId( $row->fl_user_followed  );
			}
			$res->free();
		}

		return $users;
	}


	/**
	 * Remove all titles from a user's userswatchlist
	 */
	public function clearUsersWatchList(User $user) {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'userswatchlist',
			array( 'fl_user' => $user->getId() ),
			__METHOD__
		);
	}

	/**
	 * return true if user allow to be followed
	 * @param User $user
	 */
	public function isCanBeFollowed(User $user) {

		global $wgUsersWatchListAllowAll;
		if ($wgUsersWatchListAllowAll) {
			return true;
		}
		return $user->getBoolOption( 'userswatchlist-allow' );
	}

	/**
	 * Add a list of users to a user's userswatchlist
	 *
	 * $users can be an array of strings of User Object;
	 *
	 * @param User $user
	 * @param array $users Array of strings, or Title objects
	 */
	public function followUsers( User $user, $users ) {
		$dbw = wfGetDB( DB_MASTER );
		$rows = array();

		$followedUsers = array();

		foreach ( $users as $userToWatch ) {
			$inputUser = $userToWatch;
			if ( !$userToWatch instanceof User ) {
				$userToWatch = User::newFromName($userToWatch);
			}

			if ( $userToWatch instanceof User && $this->isCanBeFollowed($userToWatch) ) {
				$rows[] = array(
					'fl_user' => $user->getId(),
					'fl_user_followed' => $userToWatch->getId()
				);
				$followedUsers[] = $inputUser;

				Hooks::run( 'UsersWatchList-newFollower', [ $user, $userToWatch ] );
			}
		}

		$dbw->insert( 'userswatchlist', $rows, __METHOD__, 'IGNORE' );
		return $followedUsers;
	}

	/**
	 * Remove a list of titles from a user's userswatchlist
	 *
	 * $titles can be an array of strings or User objects; the former
	 * is preferred, since User are very memory-heavy
	 *
	 * @param User $user
	 * @param array $users Array of strings, or User objects
	 */
	public function unfollowUsers( User $user, $users ) {
		$dbw = wfGetDB( DB_MASTER );
		foreach ( $users as $userUnfollowed ) {
			if ( !$userUnfollowed instanceof User ) {
				$userUnfollowed = User::newFromName($userUnfollowed);
			}

			if ( $userUnfollowed instanceof User ) {
				$dbw->delete(
					'userswatchlist',
					array(
						'fl_user' => $user->getId(),
						'fl_user_followed' => $userUnfollowed->getId()
					),
					__METHOD__
				);
			}
		}
		return true;
	}
}
