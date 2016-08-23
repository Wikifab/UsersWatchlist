<?php

/**
 * core opération for users Watch list
 *
 * @author Pierre Boutet
 */
class UsersWatchListCore  {

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
			array(
				 'user_properties' => array( 'INNER JOIN', array(
					'fl_user_followed=user_id'
				 ) ),
				'ORDER BY' => array( 'user_name' )
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
