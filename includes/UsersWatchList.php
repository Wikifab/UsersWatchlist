<?php
/**
 *
 * @file
 * @ingroup Extensions
 *
 * @author Pierre Boutet
 */

class UsersWatchList {


	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {

		var_dump('onLoadExtensionSchemaUpdates');
		$updater->addExtensionTable( 'userswatchlist',
				__DIR__ . '/tables.sql' );
		return true;
	}

	/**
	 * @param User $user
	 * @param array $defaultPreferences
	 */
	static function getPreferences( $user,  &$defaultPreferences ) {

		global $wgUsersWatchListAllowAll;

		if( ! $wgUsersWatchListAllowAll) {

			$defaultPreferences['userswatchlist-allow'] = array(
					'type' => 'toggle',
					'section' => 'watchlist/userswatchlist',
					'label-message' => 'tog-userswatchlist-allow',
			);
		}
	}
}