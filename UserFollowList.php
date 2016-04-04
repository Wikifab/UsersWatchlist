<?php


$wgAutoloadClasses['UserFollowList'] = __DIR__ . "/includes/UserFollowList.php";
$wgAutoloadClasses['SpecialFollowlist'] = __DIR__ . "/includes/SpecialFollowlist.php";
$wgAutoloadClasses['SpecialEditFollowList'] = __DIR__ . "/includes/SpecialEditFollowList.php";

$wgHooks['LoadExtensionSchemaUpdates'][] = 'UserFollowList::onLoadExtensionSchemaUpdates';
$wgHooks['GetPreferences'][] = 'UserFollowList::getPreferences';

$wgExtensionCredits['specialpage'][] = array(
		'path' => __FILE__,
		'name' => 'FollowList',
		'author' => 'Pierre Boutet',
		'description' => "View and edit users follow list.",
		'descriptionmsg' => 'followlist-desc',
		'version' => '0.1.0',
);

$wgMessagesDirs['UserFollowList'] = __DIR__ . "/i18n"; # Location of localisation files (Tell MediaWiki to load them)

$wgSpecialPages['FollowList'] = 'SpecialFollowlist'; # Tell MediaWiki about the new special page and its class name
$wgSpecialPages['EditFollowList'] = 'SpecialEditFollowList'; # Tell MediaWiki about the new special page and its class name

//default permissions :
$wgGroupPermissions['*']['editmyfollowlist'] = true;


// To hide opt-in in preference and allow all user to be followed :
//$wgUserFollowListAllowAll = true;