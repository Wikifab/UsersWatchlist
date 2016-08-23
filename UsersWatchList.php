<?php


$wgAutoloadClasses['UsersWatchList'] = __DIR__ . "/includes/UsersWatchList.php";
$wgAutoloadClasses['SpecialUsersWatchlist'] = __DIR__ . "/includes/SpecialUsersWatchList.php";
$wgAutoloadClasses['SpecialEditUsersWatchList'] = __DIR__ . "/includes/SpecialEditUsersWatchList.php";
$wgAutoloadClasses['ApiUsersWatchList'] = __DIR__ . "/includes/ApiUsersWatchList.php";
$wgAutoloadClasses['UsersWatchListCore'] = __DIR__ . "/includes/UsersWatchListCore.php";

$wgHooks['LoadExtensionSchemaUpdates'][] = 'UsersWatchList::onLoadExtensionSchemaUpdates';
$wgHooks['GetPreferences'][] = 'UsersWatchList::getPreferences';

$wgExtensionCredits['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'UsersWatchList',
		'author' => 'Pierre Boutet',
		'description' => "View and edit users watch list.",
		'descriptionmsg' => 'userswatchlist-desc',
		'version' => '0.2.0',
);

$wgMessagesDirs['UsersWatchList'] = __DIR__ . "/i18n"; # Location of localisation files (Tell MediaWiki to load them)

$wgSpecialPages['UsersWatchList'] = 'SpecialUsersWatchlist'; # Tell MediaWiki about the new special page and its class name
$wgSpecialPages['EditUsersWatchList'] = 'SpecialEditUsersWatchList'; # Tell MediaWiki about the new special page and its class name

//default permissions :
$wgGroupPermissions['*']['editmyuserswatchlist'] = true;

$wgAPIModules['userswatch'] = 'ApiUsersWatchList';


// To hide opt-in in preference and allow all user to be watched :
//$wgUsersWatchListAllowAll = true;