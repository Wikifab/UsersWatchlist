<?php
/**
 *
 * @file
 * @ingroup Extensions
 *
 * @author Pierre Boutet
 */

class UserFollowList {


	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {

		var_dump('onLoadExtensionSchemaUpdates');
		$updater->addExtensionTable( 'followlist',
				__DIR__ . '/tables.sql' );
		return true;
	}

	/**
	 * @param User $user
	 * @param array $defaultPreferences
	 */
	static function getPreferences( $user,  &$defaultPreferences ) {

		global $wgUserFollowListAllowAll;

		if( ! $wgUserFollowListAllowAll) {

			$defaultPreferences['followlist-allow'] = array(
					'type' => 'toggle',
					'section' => 'watchlist/userwatchlist',
					'label-message' => 'tog-followlist-allow',
			);
		}
	}
}