<?php
/**
 * @defgroup UsersWatchList Users userswatchlist handling
 */

/**
 * Implements Special:UsersWatchList
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 * @ingroup UsersWatchList
 */

/**
 * Provides the UI through which users can perform editing
 * operations on their userswatchlist
 *
 * @ingroup SpecialPage
 * @ingroup UsersWatchList
 * @author Rob Church <robchur@gmail.com>
 */
class SpecialEditUsersWatchList extends UnlistedSpecialPage {
	/**
	 * Editing modes. EDIT_CLEAR is no longer used; the "Clear" link scared people
	 * too much. Now it's passed on to the raw editor, from which it's very easy to clear.
	 */
	const EDIT_CLEAR = 1;
	const EDIT_RAW = 2;
	const EDIT_NORMAL = 3;

	protected $successMessage;

	private $badItems = array();

	public function __construct() {
		parent::__construct( 'EditUsersWatchList', 'editmyuserswatchlist' );
	}

	/**
	 * Main execution point
	 *
	 * @param int $mode
	 */
	public function execute( $mode ) {
		$this->setHeaders();

		# Anons don't get a userswatchlist
		$this->requireLogin( 'userswatchlistanontext' );


		$out = $this->getOutput();

		$this->checkPermissions();
		$this->checkReadOnly();

		$this->outputHeader();
		$this->outputSubtitle();

		# B/C: $mode used to be waaay down the parameter list, and the first parameter
		# was $wgUser
		if ( $mode instanceof User ) {
			$args = func_get_args();
			if ( count( $args ) >= 4 ) {
				$mode = $args[3];
			}
		}
		$mode = self::getMode( $this->getRequest(), $mode );

		switch ( $mode ) {
			case self::EDIT_RAW:
				$out->setPageTitle( $this->msg( 'userswatchlistedit-raw-title' ) );
				$form = $this->getRawForm();
				if ( $form->show() ) {
					$out->addHTML( $this->successMessage );
					$out->addReturnTo( SpecialPage::getTitleFor( 'UsersWatchList' ) );
				}
				break;
			case self::EDIT_CLEAR:
				$out->setPageTitle( $this->msg( 'userswatchlistedit-clear-title' ) );
				$form = $this->getClearForm();
				if ( $form->show() ) {
					$out->addHTML( $this->successMessage );
					$out->addReturnTo( SpecialPage::getTitleFor( 'UsersWatchList' ) );
				}
				break;

			case self::EDIT_NORMAL:
			default:
			$this->executeViewEditUsersWatchList();
				break;
		}
	}

	/**
	 * Renders a subheader on the userswatchlist page.
	 */
	protected function outputSubtitle() {
		$out = $this->getOutput();
		$out->addSubtitle( $this->msg( 'userswatchlistfor2', $this->getUser()->getName() )
			->rawParams( SpecialEditUsersWatchList::buildTools( null ) ) );
	}

	/**
	 * Executes an edit mode for the userswatchlist view, from which you can manage your userswatchlist
	 *
	 */
	protected function executeViewEditUsersWatchList() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'userswatchlistedit-normal-title' ) );
		$form = $this->getNormalForm();
		if ( $form->show() ) {
			$out->addHTML( $this->successMessage );
			$out->addReturnTo( SpecialPage::getTitleFor( 'UsersWatchList' ) );
		}
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return
	 * @param int $offset Number of results to skip
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit, $offset ) {
		return self::prefixSearchArray(
			$search,
			$limit,
			// SpecialUsersWatchList uses SpecialEditUsersWatchList::getMode, so new types should be added
			// here and there - no 'edit' here, because that the default for this page
			array(
				'clear',
				'raw',
			),
			$offset
		);
	}

	/**
	 * Extract a list of titles from a blob of text, returning
	 * (prefixed) strings; unfollowable titles are ignored
	 *
	 * @param string $list
	 * @return array
	 */
	private function extractTitles( $list ) {
		$list = explode( "\n", trim( $list ) );
		if ( !is_array( $list ) ) {
			return array();
		}

		$users = array();


		foreach ( $list as $text ) {
			$text = trim( $text );
			if ( strlen( $text ) > 0 ) {
				$user = User::newFromName($text);
				$title = Title::newFromText( $text );
				if ( $user && $user->getId() != 0 ) {
					$users[] = $user;
				}
			}
		}

		$list = array();
		/** @var Title $title */
		foreach ( $users as $user ) {
			$list[] = $user->getName();
		}

		return array_unique( $list );
	}

	public function submitRaw( $data ) {
		$wanted = $this->extractTitles( $data['Titles'] );
		$current = $this->getUsersWatchList();

		if ( count( $wanted ) > 0 ) {
			$toFollow = array_diff( $wanted, $current );
			$toUnfollow = array_diff( $current, $wanted );
			$followedUsers = $this->followUsers( $toFollow );
			$failsUsers = array_diff( $toFollow, $followedUsers );
			$this->unfollowUsers( $toUnfollow );
			$this->getUser()->invalidateCache();

			if ( count( $toFollow ) > 0 || count( $toUnfollow ) > 0 ) {
				$this->successMessage = $this->msg( 'userswatchlistedit-raw-done' )->parse();
			} else {
				return false;
			}
			if ( count( $followedUsers ) > 0 ) {
				$this->successMessage .= ' ' . $this->msg( 'userswatchlistedit-raw-added' )
					->numParams( count( $followedUsers ) )->parse();
				$this->showTitles( $followedUsers, $this->successMessage );
			}
			if ( count( $failsUsers ) > 0 ) {
				$this->successMessage .= ' ' . $this->msg( 'userswatchlistedit-raw-failed' )
					->numParams( count( $failsUsers ) )->parse();
				$this->showTitles( $failsUsers, $this->successMessage );
			}

			if ( count( $toUnfollow ) > 0 ) {
				$this->successMessage .= ' ' . $this->msg( 'userswatchlistedit-raw-removed' )
					->numParams( count( $toUnfollow ) )->parse();
				$this->showTitles( $toUnfollow, $this->successMessage );
			}
		} else {
			$this->clearUsersWatchList();
			$this->getUser()->invalidateCache();

			if ( count( $current ) > 0 ) {
				$this->successMessage = $this->msg( 'userswatchlistedit-raw-done' )->parse();
			} else {
				return false;
			}

			$this->successMessage .= ' ' . $this->msg( 'userswatchlistedit-raw-removed' )
				->numParams( count( $current ) )->parse();
			$this->showTitles( $current, $this->successMessage );
		}

		return true;
	}

	public function submitClear( $data ) {
		$current = $this->getUsersWatchList();
		$this->clearUsersWatchList();
		$this->getUser()->invalidateCache();
		$this->successMessage = $this->msg( 'userswatchlistedit-clear-done' )->parse();
		$this->successMessage .= ' ' . $this->msg( 'userswatchlistedit-clear-removed' )
			->numParams( count( $current ) )->parse();
		$this->showTitles( $current, $this->successMessage );

		return true;
	}

	/**
	 * Print out a list of linked titles
	 *
	 * $titles can be an array of strings or Title objects; the former
	 * is preferred, since Titles are very memory-heavy
	 *
	 * @param array $titles Array of strings, or Title objects
	 * @param string $output
	 */
	private function showTitles( $titles, &$output ) {
		$talk = $this->msg( 'talkpagelinktext' )->escaped();
		// Do a batch existence check
		$batch = new LinkBatch();
		if ( count( $titles ) >= 100 ) {
			$output = wfMessage( 'userswatchlistedit-too-many' )->parse();
			return;
		}
		foreach ( $titles as $title ) {
			if ( !$title instanceof Title ) {
				$title = Title::newFromText( $title );
			}

			if ( $title instanceof Title ) {
				$batch->addObj( $title );
				$batch->addObj( $title->getTalkPage() );
			}
		}

		$batch->execute();

		// Print out the list
		$output .= "<ul>\n";

		foreach ( $titles as $title ) {
			if ( !$title instanceof Title ) {
				$title = Title::newFromText( $title );
			}

			if ( $title instanceof Title ) {
				$output .= "<li>"
					. Linker::link( $title )
					. ' (' . Linker::link( $title->getTalkPage(), $talk )
					. ")</li>\n";
			}
		}

		$output .= "</ul>\n";
	}

	/**
	 * Prepare a list of titles on a user's userswatchlist (excluding talk pages)
	 * and return an array of (prefixed) strings
	 *
	 * @return array
	 */
	private function getUsersWatchList() {
		$list = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			'userswatchlist',
			array(
				'fl_user_followed'
			), array(
				'fl_user' => $this->getUser()->getId(),
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
		$this->cleanupUsersWatchList();

		return $users;
	}

	/**
	 * Get a list of titles on a user's userswatchlist
	 *
	 * @return array
	 */
	protected function getUsersWatchListInfo() {
		$users = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			array( 'userswatchlist', 'user' ),
			array( 'fl_user_followed', 'user_name', 'user_id' ),
			array( 'fl_user' => $this->getUser()->getId() ),
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
	 * Attempts to clean up broken items
	 */
	private function cleanupUsersWatchList() {
		if ( !count( $this->badItems ) ) {
			return; //nothing to do
		}

		$dbw = wfGetDB( DB_MASTER );
		$user = $this->getUser();

		foreach ( $this->badItems as $row ) {
			list( $title, $namespace, $dbKey ) = $row;
			$action = $title ? 'cleaning up' : 'deleting';
			wfDebug( "User {$user->getName()} has broken userswatchlist item ns($namespace):$dbKey, $action.\n" );

			$dbw->delete( 'userswatchlist',
				array(
					'wl_user' => $user->getId(),
					'wl_namespace' => $namespace,
					'wl_title' => $dbKey,
				),
				__METHOD__
			);

			// Can't just do an UPDATE instead of DELETE/INSERT due to unique index
			if ( $title ) {
				$user->addFollow( $title );
			}
		}
	}

	/**
	 * Remove all titles from a user's userswatchlist
	 */
	private function clearUsersWatchList() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'userswatchlist',
			array( 'fl_user' => $this->getUser()->getId() ),
			__METHOD__
		);
	}

	/**
	 * return true if user allow to be followed
	 * @param User $user
	 */
	private function isCanBeFollowed(User $user) {

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
	 * @param array $users Array of strings, or Title objects
	 */
	private function followUsers( $users ) {
		$dbw = wfGetDB( DB_MASTER );
		$rows = array();

		$followedUsers = array();

		foreach ( $users as $user ) {
			$inputUser = $user;
			if ( !$user instanceof User ) {
				$user = User::newFromName($user);
			}

			if ( $user instanceof User && $this->isCanBeFollowed($user) ) {
				$rows[] = array(
					'fl_user' => $this->getUser()->getId(),
					'fl_user_followed' => $user->getId()
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
	 * @param array $users Array of strings, or User objects
	 */
	private function unfollowUsers( $users ) {
		$dbw = wfGetDB( DB_MASTER );

		foreach ( $users as $user ) {
			if ( !$user instanceof User ) {
				$user = User::newFromName($user);
			}

			if ( $user instanceof User ) {
				$dbw->delete(
					'userswatchlist',
					array(
						'fl_user' => $this->getUser()->getId(),
						'fl_user_followed' => $user->getId()
					),
					__METHOD__
				);
			}
		}
	}

	public function submitNormal( $data ) {
		$removed = array();

		foreach ( $data as $titles ) {
			$this->unfollowUsers( $titles );
			$removed = array_merge( $removed, $titles );
		}

		if ( count( $removed ) > 0 ) {
			$this->successMessage = $this->msg( 'userswatchlistedit-normal-done'
			)->numParams( count( $removed ) )->parse();
			$this->showTitles( $removed, $this->successMessage );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the standard userswatchlist editing form
	 *
	 * @return HTMLForm
	 */
	protected function getNormalForm() {
		global $wgContLang;

		$fields = array();
		$count = 0;

		// Allow subscribers to manipulate the list of followed pages (or use it
		// to preload lots of details at once)
		$userswatchlistInfo = $this->getUsersWatchListInfo();
		wfRunHooks(
			'UsersWatchListEditorBeforeFormRender',
			array( &$userswatchlistInfo )
		);

		$options = array();
		foreach ( $userswatchlistInfo as $user ) {
			$text = $this->buildRemoveLine( $user );
			$options[$text] = $user->getName();
			$count++;
		}
		if ( count( $options ) > 0 ) {
			$fields['TitlesNs'] = array(
					'class' => 'EditUsersWatchListCheckboxSeriesField',
					'options' => $options,
					'section' => "user",
			);
		}

		$this->cleanupUsersWatchList();

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage
		$form = new EditUsersWatchListNormalHTMLForm( $fields, $context );
		$form->setSubmitTextMsg( 'userswatchlistedit-normal-submit' );
		# Used message keys:
		# 'accesskey-userswatchlistedit-normal-submit', 'tooltip-userswatchlistedit-normal-submit'
		$form->setSubmitTooltip( 'userswatchlistedit-normal-submit' );
		$form->setWrapperLegendMsg( 'userswatchlistedit-normal-legend' );
		$form->addHeaderText( $this->msg( 'userswatchlistedit-normal-explain' )->parse() );
		$form->setSubmitCallback( array( $this, 'submitNormal' ) );

		return $form;
	}

	/**
	 * Build the label for a checkbox, with a link to the title, and various additional bits
	 *
	 * @param Title $title
	 * @return string
	 */
	private function buildRemoveLine( $user ) {
		$link = Linker::userLink( $user->getId(), $user->getName());

		return $link . " (" . $this->getLanguage()->pipeList( array() ) . ")";
		return $link ;
	}

	/**
	 * Get a form for editing the userswatchlist in "raw" mode
	 *
	 * @return HTMLForm
	 */
	protected function getRawForm() {
		$titles = implode( $this->getUsersWatchList(), "\n" );
		$fields = array(
			'Titles' => array(
				'type' => 'textarea',
				'label-message' => 'userswatchlistedit-raw-titles',
				'default' => $titles,
			),
		);
		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle( 'raw' ) ); // Reset subpage
		$form = new HTMLForm( $fields, $context );
		$form->setSubmitTextMsg( 'userswatchlistedit-raw-submit' );
		# Used message keys: 'accesskey-userswatchlistedit-raw-submit', 'tooltip-userswatchlistedit-raw-submit'
		$form->setSubmitTooltip( 'userswatchlistedit-raw-submit' );
		$form->setWrapperLegendMsg( 'userswatchlistedit-raw-legend' );
		$form->addHeaderText( $this->msg( 'userswatchlistedit-raw-explain' )->parse() );
		$form->setSubmitCallback( array( $this, 'submitRaw' ) );

		return $form;
	}

	/**
	 * Get a form for clearing the userswatchlist
	 *
	 * @return HTMLForm
	 */
	protected function getClearForm() {
		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle( 'clear' ) ); // Reset subpage
		$form = new HTMLForm( array(), $context );
		$form->setSubmitTextMsg( 'userswatchlistedit-clear-submit' );
		# Used message keys: 'accesskey-userswatchlistedit-clear-submit', 'tooltip-userswatchlistedit-clear-submit'
		$form->setSubmitTooltip( 'userswatchlistedit-clear-submit' );
		$form->setWrapperLegendMsg( 'userswatchlistedit-clear-legend' );
		$form->addHeaderText( $this->msg( 'userswatchlistedit-clear-explain' )->parse() );
		$form->setSubmitCallback( array( $this, 'submitClear' ) );

		return $form;
	}

	/**
	 * Determine whether we are editing the userswatchlist, and if so, what
	 * kind of editing operation
	 *
	 * @param WebRequest $request
	 * @param string $par
	 * @return int
	 */
	public static function getMode( $request, $par ) {
		$mode = strtolower( $request->getVal( 'action', $par ) );

		switch ( $mode ) {
			case 'clear':
			case self::EDIT_CLEAR:
				return self::EDIT_CLEAR;
			case 'raw':
			case self::EDIT_RAW:
				return self::EDIT_RAW;
			case 'edit':
			case self::EDIT_NORMAL:
				return self::EDIT_NORMAL;
			default:
				return false;
		}
	}

	/**
	 * Build a set of links for convenient navigation
	 * between userswatchlist viewing and editing modes
	 *
	 * @param null $unused
	 * @return string
	 */
	public static function buildTools( $unused ) {
		global $wgLang;

		$tools = array();
		$modes = array(
			'view' => array( 'UsersWatchList', false ),
			'edit' => array( 'EditUsersWatchList', false ),
			'raw' => array( 'EditUsersWatchList', 'raw' ),
			'clear' => array( 'EditUsersWatchList', 'clear' ),
		);

		foreach ( $modes as $mode => $arr ) {
			// can use messages 'userswatchlisttools-view', 'userswatchlisttools-edit', 'userswatchlisttools-raw'
			$tools[] = Linker::linkKnown(
				SpecialPage::getTitleFor( $arr[0], $arr[1] ),
				wfMessage( "userswatchlisttools-{$mode}" )->escaped()
			);
		}

		return Html::rawElement(
			'span',
			array( 'class' => 'mw-watchlist-toollinks' ),
			wfMessage( 'parentheses', $wgLang->pipeList( $tools ) )->text()
		);
	}
}

/**
 * Extend HTMLForm purely so we can have a more sane way of getting the section headers
 */
class EditUsersWatchListNormalHTMLForm extends HTMLForm {
	public function getLegend( $namespace ) {
		$namespace = substr( $namespace, 2 );

		return $namespace == NS_MAIN
			? $this->msg( 'blanknamespace' )->escaped()
			: htmlspecialchars( $this->getContext()->getLanguage()->getFormattedNsText( $namespace ) );
	}

	public function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'edituserswatchlist-' );
	}
}

class EditUsersWatchListCheckboxSeriesField extends HTMLMultiSelectField {
	/**
	 * HTMLMultiSelectField throws validation errors if we get input data
	 * that doesn't match the data set in the form setup. This causes
	 * problems if something gets removed from the userswatchlist while the
	 * form is open (bug 32126), but we know that invalid items will
	 * be harmless so we can override it here.
	 *
	 * @param string $value The value the field was submitted with
	 * @param array $alldata The data collected from the form
	 * @return bool|string Bool true on success, or String error to display.
	 */
	function validate( $value, $alldata ) {
		// Need to call into grandparent to be a good citizen. :)
		return HTMLFormField::validate( $value, $alldata );
	}
}
