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
}