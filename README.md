UserFollowList
===============

Add a tab when viewing a user page to "follow this user", and a separate user-FollowList special page which lists the latest contributions from users on the list.

Installation
===============

1. clone UserFollowList into the 'extensions' directory of your mediawiki installation
2. add the folling Line to your LocalSettings.php file :
> require_once("$IP/extensions/UserFollowList/UserFollowList.php");
3. run php maintenance/update.php 

Usage
===============

Go to the special page 'Special:Followlist'
