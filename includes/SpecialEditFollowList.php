<?php
/**
 * @defgroup Followlist Users followlist handling
 */

/**
 * Implements Special:Followlist
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
 * @ingroup Followlist
 */

/**
 * Provides the UI through which users can perform editing
 * operations on their followlist
 *
 * @ingroup SpecialPage
 * @ingroup Followlist
 * @author Rob Church <robchur@gmail.com>
 */
class SpecialEditFollowList extends UnlistedSpecialPage {
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
		parent::__construct( 'EditFollowList', 'editmyfollowlist' );
	}

	/**
	 * Main execution point
	 *
	 * @param int $mode
	 */
	public function execute( $mode ) {
		$this->setHeaders();

		# Anons don't get a followlist
		$this->requireLogin( 'followlistanontext' );
		

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
				$out->setPageTitle( $this->msg( 'followlistedit-raw-title' ) );
				$form = $this->getRawForm();
				if ( $form->show() ) {
					$out->addHTML( $this->successMessage );
					$out->addReturnTo( SpecialPage::getTitleFor( 'Followlist' ) );
				}
				break;
			case self::EDIT_CLEAR:
				$out->setPageTitle( $this->msg( 'followlistedit-clear-title' ) );
				$form = $this->getClearForm();
				if ( $form->show() ) {
					$out->addHTML( $this->successMessage );
					$out->addReturnTo( SpecialPage::getTitleFor( 'Followlist' ) );
				}
				break;

			case self::EDIT_NORMAL:
			default:
			$this->executeViewEditFollowlist();
				break;
		}
	}

	/**
	 * Renders a subheader on the followlist page.
	 */
	protected function outputSubtitle() {
		$out = $this->getOutput();
		$out->addSubtitle( $this->msg( 'followlistfor2', $this->getUser()->getName() )
			->rawParams( SpecialEditFollowlist::buildTools( null ) ) );
	}

	/**
	 * Executes an edit mode for the followlist view, from which you can manage your followlist
	 *
	 */
	protected function executeViewEditFollowlist() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'followlistedit-normal-title' ) );
		$form = $this->getNormalForm();
		if ( $form->show() ) {
			$out->addHTML( $this->successMessage );
			$out->addReturnTo( SpecialPage::getTitleFor( 'Followlist' ) );
		}
	}

	/**
	 * Return an array of subpages beginning with $search that this special page will accept.
	 *
	 * @param string $search Prefix to search for
	 * @param int $limit Maximum number of results to return
	 * @return string[] Matching subpages
	 */
	public function prefixSearchSubpages( $search, $limit = 10 ) {
		return self::prefixSearchArray(
			$search,
			$limit,
			// SpecialFollowlist uses SpecialEditFollowlist::getMode, so new types should be added
			// here and there - no 'edit' here, because that the default for this page
			array(
				'clear',
				'raw',
			)
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

		$titles = array();

		foreach ( $list as $text ) {
			$text = trim( $text );
			if ( strlen( $text ) > 0 ) {
				$title = Title::newFromText( $text );
				if ( $title instanceof Title && $title->isWatchable() ) {
					$titles[] = $title;
				}
			}
		}

		GenderCache::singleton()->doTitlesArray( $titles );

		$list = array();
		/** @var Title $title */
		foreach ( $titles as $title ) {
			$list[] = $title->getPrefixedText();
		}

		return array_unique( $list );
	}

	public function submitRaw( $data ) {
		$wanted = $this->extractTitles( $data['Titles'] );
		$current = $this->getFollowlist();

		if ( count( $wanted ) > 0 ) {
			$toFollow = array_diff( $wanted, $current );
			$toUnfollow = array_diff( $current, $wanted );
			$this->followUsers( $toFollow );
			$this->unfollowUsers( $toUnfollow );
			$this->getUser()->invalidateCache();

			if ( count( $toFollow ) > 0 || count( $toUnfollow ) > 0 ) {
				$this->successMessage = $this->msg( 'followlistedit-raw-done' )->parse();
			} else {
				return false;
			}

			if ( count( $toFollow ) > 0 ) {
				$this->successMessage .= ' ' . $this->msg( 'followlistedit-raw-added' )
					->numParams( count( $toFollow ) )->parse();
				$this->showTitles( $toFollow, $this->successMessage );
			}

			if ( count( $toUnfollow ) > 0 ) {
				$this->successMessage .= ' ' . $this->msg( 'followlistedit-raw-removed' )
					->numParams( count( $toUnfollow ) )->parse();
				$this->showTitles( $toUnfollow, $this->successMessage );
			}
		} else {
			$this->clearFollowlist();
			$this->getUser()->invalidateCache();

			if ( count( $current ) > 0 ) {
				$this->successMessage = $this->msg( 'followlistedit-raw-done' )->parse();
			} else {
				return false;
			}

			$this->successMessage .= ' ' . $this->msg( 'followlistedit-raw-removed' )
				->numParams( count( $current ) )->parse();
			$this->showTitles( $current, $this->successMessage );
		}

		return true;
	}

	public function submitClear( $data ) {
		$current = $this->getFollowlist();
		$this->clearFollowlist();
		$this->getUser()->invalidateCache();
		$this->successMessage = $this->msg( 'followlistedit-clear-done' )->parse();
		$this->successMessage .= ' ' . $this->msg( 'followlistedit-clear-removed' )
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
			$output = wfMessage( 'followlistedit-too-many' )->parse();
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
	 * Prepare a list of titles on a user's followlist (excluding talk pages)
	 * and return an array of (prefixed) strings
	 *
	 * @return array
	 */
	private function getFollowlist() {
		$list = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			'followlist',
			array(
				'fl_user_followed'
			), array(
				'fl_user' => $this->getUser()->getId(),
			),
			__METHOD__
		);

		if ( $res->numRows() > 0 ) {
			$users = array();
			foreach ( $res as $row ) {
				$users[] = User::whoIs( $row->fl_user_followed  );
			}
			$res->free();
		}
		$this->cleanupFollowlist();

		return $users;
	}

	/**
	 * Get a list of titles on a user's followlist
	 *
	 * @return array
	 */
	protected function getFollowlistInfo() {
		$users = array();
		$dbr = wfGetDB( DB_MASTER );

		$res = $dbr->select(
			array( 'followlist', 'user' ),
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
	 * Validates followlist entry
	 *
	 * @param Title $title
	 * @param int $namespace
	 * @param string $dbKey
	 * @return bool Whether this item is valid
	 */
	private function checkTitle( $title, $namespace, $dbKey ) {
		if ( $title
			&& ( $title->isExternal()
				|| $title->getNamespace() < 0
			)
		) {
			$title = false; // unrecoverable
		}

		if ( !$title
			|| $title->getNamespace() != $namespace
			|| $title->getDBkey() != $dbKey
		) {
			$this->badItems[] = array( $title, $namespace, $dbKey );
		}

		return (bool)$title;
	}

	//TODO : implement this
	/**
	 * Attempts to clean up broken items
	 */
	private function cleanupFollowlist() {
		if ( !count( $this->badItems ) ) {
			return; //nothing to do
		}

		$dbw = wfGetDB( DB_MASTER );
		$user = $this->getUser();

		foreach ( $this->badItems as $row ) {
			list( $title, $namespace, $dbKey ) = $row;
			$action = $title ? 'cleaning up' : 'deleting';
			wfDebug( "User {$user->getName()} has broken followlist item ns($namespace):$dbKey, $action.\n" );

			$dbw->delete( 'followlist',
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
	 * Remove all titles from a user's followlist
	 */
	private function clearFollowlist() {
		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete(
			'followlist',
			array( 'fl_user' => $this->getUser()->getId() ),
			__METHOD__
		);
	}

	/**
	 * Add a list of users to a user's followlist
	 *
	 * $users can be an array of strings of User Object;
	 *
	 * @param array $users Array of strings, or Title objects
	 */
	private function followUsers( $users ) {
		$dbw = wfGetDB( DB_MASTER );
		$rows = array();

		foreach ( $users as $user ) {
			if ( !$user instanceof User ) {
				$user = User::newFromName($user);
			}

			if ( $user instanceof User ) {
				$rows[] = array(
					'fl_user' => $this->getUser()->getId(),
					'fl_user_followed' => $user->getId()
				);
			}
		}

		$dbw->insert( 'followlist', $rows, __METHOD__, 'IGNORE' );
	}

	/**
	 * Remove a list of titles from a user's followlist
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
					'followlist',
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
			$this->successMessage = $this->msg( 'followlistedit-normal-done'
			)->numParams( count( $removed ) )->parse();
			$this->showTitles( $removed, $this->successMessage );

			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the standard followlist editing form
	 *
	 * @return HTMLForm
	 */
	protected function getNormalForm() {
		global $wgContLang;

		$fields = array();
		$count = 0;

		// Allow subscribers to manipulate the list of followed pages (or use it
		// to preload lots of details at once)
		$followlistInfo = $this->getFollowlistInfo();
		wfRunHooks(
			'FollowlistEditorBeforeFormRender',
			array( &$followlistInfo )
		);

		$options = array();
		foreach ( $followlistInfo as $user ) {
			$text = $this->buildRemoveLine( $user );
			$options[$text] = $user->getName();
			$count++;
		}
		if ( count( $options ) > 0 ) {
			$fields['TitlesNs'] = array(
					'class' => 'EditFollowlistCheckboxSeriesField',
					'options' => $options,
					'section' => "user",
			);
		}
		
		$this->cleanupFollowlist();

		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle() ); // Remove subpage
		$form = new EditFollowlistNormalHTMLForm( $fields, $context );
		$form->setSubmitTextMsg( 'followlistedit-normal-submit' );
		# Used message keys:
		# 'accesskey-followlistedit-normal-submit', 'tooltip-followlistedit-normal-submit'
		$form->setSubmitTooltip( 'followlistedit-normal-submit' );
		$form->setWrapperLegendMsg( 'followlistedit-normal-legend' );
		$form->addHeaderText( $this->msg( 'followlistedit-normal-explain' )->parse() );
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
	 * Get a form for editing the followlist in "raw" mode
	 *
	 * @return HTMLForm
	 */
	protected function getRawForm() {
		$titles = implode( $this->getFollowlist(), "\n" );
		$fields = array(
			'Titles' => array(
				'type' => 'textarea',
				'label-message' => 'followlistedit-raw-titles',
				'default' => $titles,
			),
		);
		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle( 'raw' ) ); // Reset subpage
		$form = new HTMLForm( $fields, $context );
		$form->setSubmitTextMsg( 'followlistedit-raw-submit' );
		# Used message keys: 'accesskey-followlistedit-raw-submit', 'tooltip-followlistedit-raw-submit'
		$form->setSubmitTooltip( 'followlistedit-raw-submit' );
		$form->setWrapperLegendMsg( 'followlistedit-raw-legend' );
		$form->addHeaderText( $this->msg( 'followlistedit-raw-explain' )->parse() );
		$form->setSubmitCallback( array( $this, 'submitRaw' ) );

		return $form;
	}

	/**
	 * Get a form for clearing the followlist
	 *
	 * @return HTMLForm
	 */
	protected function getClearForm() {
		$context = new DerivativeContext( $this->getContext() );
		$context->setTitle( $this->getPageTitle( 'clear' ) ); // Reset subpage
		$form = new HTMLForm( array(), $context );
		$form->setSubmitTextMsg( 'followlistedit-clear-submit' );
		# Used message keys: 'accesskey-followlistedit-clear-submit', 'tooltip-followlistedit-clear-submit'
		$form->setSubmitTooltip( 'followlistedit-clear-submit' );
		$form->setWrapperLegendMsg( 'followlistedit-clear-legend' );
		$form->addHeaderText( $this->msg( 'followlistedit-clear-explain' )->parse() );
		$form->setSubmitCallback( array( $this, 'submitClear' ) );

		return $form;
	}

	/**
	 * Determine whether we are editing the followlist, and if so, what
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
	 * between followlist viewing and editing modes
	 *
	 * @param null $unused
	 * @return string
	 */
	public static function buildTools( $unused ) {
		global $wgLang;

		$tools = array();
		$modes = array(
			'view' => array( 'Followlist', false ),
			'edit' => array( 'EditFollowlist', false ),
			'raw' => array( 'EditFollowlist', 'raw' ),
			'clear' => array( 'EditFollowlist', 'clear' ),
		);

		foreach ( $modes as $mode => $arr ) {
			// can use messages 'followlisttools-view', 'followlisttools-edit', 'followlisttools-raw'
			$tools[] = Linker::linkKnown(
				SpecialPage::getTitleFor( $arr[0], $arr[1] ),
				wfMessage( "followlisttools-{$mode}" )->escaped()
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
class EditFollowlistNormalHTMLForm extends HTMLForm {
	public function getLegend( $namespace ) {
		$namespace = substr( $namespace, 2 );

		return $namespace == NS_MAIN
			? $this->msg( 'blanknamespace' )->escaped()
			: htmlspecialchars( $this->getContext()->getLanguage()->getFormattedNsText( $namespace ) );
	}

	public function getBody() {
		return $this->displaySection( $this->mFieldTree, '', 'editfollowlist-' );
	}
}

class EditFollowlistCheckboxSeriesField extends HTMLMultiSelectField {
	/**
	 * HTMLMultiSelectField throws validation errors if we get input data
	 * that doesn't match the data set in the form setup. This causes
	 * problems if something gets removed from the followlist while the
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
