<?php
/*
 * Copyright (C) Vulcan Inc.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program.If not, see <http://www.gnu.org/licenses/>.
 *
 */

/**
 * @file
 * @ingroup SemanticComments
 *
 * This file contains global functions that are called from the SemanticComments extension.
 *
 * @author Benjamin Langguth
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the SemanticComments extension. It is not a valid entry point.\n" );
}

/**
 * Switch on SemanticComments features. This function must be called in
 * LocalSettings.php after CE_Initialize.php was included and default values
 * that are defined there have been modified.
 * For readability, this is the only global function that does not adhere to the
 * naming conventions.
 *
 * This function installs the extension, sets up all autoloading, special pages
 * etc.
 */
function enableSemanticComments() {
	wfProfileIn( __METHOD__ . ' [SemanticComments]' );
	global $cegIP, $cegEnableSemanticComments,  $cegEnableComment,
		$wgExtensionMessagesFiles, $wgExtensionFunctions,
		$wgAutoloadClasses, $wgHooks;

	require_once($cegIP . '/specials/Comment/CE_CommentParserFunctions.php');

	$wgExtensionFunctions[] = 'cefSetupExtension';
	$wgHooks['LanguageGetMagic'][] = 'cefAddMagicWords'; // setup names for parser functions (needed here)
	$wgHooks['MakeGlobalVariablesScript'][] = 'cefAddGlobalJSVariables';
	$wgExtensionMessagesFiles['SemanticComments'] = $cegIP . '/languages/CE_Messages.php'; // register messages (requires MW=>1.11)

	//--- Comment classes ---
	$wgAutoloadClasses['CEComment'] = $cegIP . '/specials/Comment/CE_Comment.php';
	$wgAutoloadClasses['CECommentUtils'] = $cegIP . '/specials/Comment/CE_CommentUtils.php';

	//--- Autoloading for exception classes ---
	$wgAutoloadClasses['CEException'] = $cegIP . '/exeptions/CE_Exception.php';

	require_once($cegIP . '/specials/Comment/CE_CommentAjaxAccess.php');

	$wgAutoloadClasses['CECommentSpecial'] = $cegIP . '/specials/Comment/CE_CommentSpecial.php';

	//so that other extensions know about the SemanticComments extension
	$cegEnableSemanticComments = true;

	wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	return true;
}

/**
 * Do the actual initialisation of the extension. This is just a delayed init that
 * makes sure MediaWiki is set up properly before we add our stuff.
 *
 * The main things this function does are: register all hooks, set up extension
 * credits, and init some globals that are not for configuration settings.
 */
function cefSetupExtension() {
	wfProfileIn( __METHOD__ . ' [SemanticComments]' );
	global $wgHooks, $wgExtensionCredits, $wgRequest, $wgContLang, $wgSpecialPages, $wgOut;

	///// Register specials pages
	$wgSpecialPages['SemanticComments'] = array('CECommentSpecial');

	celfSetupScriptAndStyleModule();
	$spns_text = $wgContLang->getNsText(NS_SPECIAL);
	// register AddHTMLHeader functions for special pages
	// to include javascript and css files (only on special page requests).
	$url = $wgRequest->getRequestURL();
	if(  stripos( $url, $spns_text . ":SemanticComments" ) !== false
	     || stripos( $url, $spns_text . "%3ASemanticComments" ) !== false ) {
	   $wgOut->addModules( 'ext.ce.comment.specialpage' );
	} else {
	   $wgOut->addModules( 'ext.ce.comment' );
	}

	### credits (see Special:Version) ###
	$wgExtensionCredits['semantic'][]= array(
		'path'           => __FILE__,
		'name' => 'SemanticComments',
		'version' => CE_VERSION,
		'author'=>array("Patrick Barret","Benjamin Langguth","Thomas Schweitzer"),
		'url' => 'https://www.mediawiki.org/wiki/SemanticComments',
		'description' => 'SemanticComments toolset, eg article comments.'
		);

	### Register autocompletion icon ###
	$wgHooks['smwhACNamespaceMappings'][] = 'cefRegisterACIcon';

	wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	return true;
}


/**
 * Creates a module for the resource loader that contains all scripts and styles
 * that are needed for this extension.
 */
function celfSetupScriptAndStyleModule() {
	global $wgResourceModules, $cegIP;

	$messages = array(
		'ce_com_default_header',
		'ce_com_ext_header',
		'ce_invalid',
		'ce_reload',
		'ce_deleting',
		'ce_full_deleting',
		'ce_delete',
		'ce_delete_button',
		'ce_cancel_button',
		'ce_full_delete',
		'ce_close_button',
		'ce_delete_title',
		'ce_edit_title',
		'ce_edit_cancel_title',
		'ce_reply_title',
		'ce_com_reply',
		'ce_edit_rating_text',
		'ce_edit_rating_text2',
		'ce_edit_button',
		'ce_com_show',
		'ce_com_hide',
		'ce_com_view',
		'ce_com_view_flat',
		'ce_com_view_threaded',
		'ce_com_file_toggle',
		'ce_com_rating_text',
		'ce_com_rating_text2',
		'ce_com_rating_text_short',
		'ce_com_toggle_tooltip',
		'ce_form_toggle_tooltip',
		'ce_form_toggle_no_edit_tooltip',
		'ce_edit_intro',
		'ce_edit_date_intro',
	);

	$ceResourceTemplate = array(
		'localBasePath' => $cegIP,
		'remoteExtPath' => 'SemanticComments',
		'group' => 'ext.ce'
	);

	$wgResourceModules += array(
		'ext.ce.comment' => $ceResourceTemplate + array(
			'scripts' => array(
				'scripts/overlay.js',
				'scripts/Language/CE_Language.js',
				'scripts/Comment/CE_Comment.js',
			),
			'styles' => array(
				'skins/Comment/ce-comment.css',
				'skins/Comment/ce-overlay.css'
			),
			'messages' => $messages
		),
		'ext.ce.comment.specialpage' => $ceResourceTemplate + array(
			'dependencies' => array( 'ext.smw.sorttable' )
		)
	);
}

/*********************************/
/***** namespace settings *****/
/*********************************/

/**
 * Init the additional namespaces used by SemanticComments. The
 * parameter denotes the least unused even namespace ID that is
 * greater or equal to 100.
 */
function cefInitNamespaces() {
	wfProfileIn( __METHOD__ . ' [SemanticComments]' );
	global $cegCommentNamespaceIndex, $wgExtraNamespaces, $wgNamespaceAliases,
		$wgNamespacesWithSubpages, $wgLanguageCode, $cegContLang;

	if (!isset($cegCommentNamespaceIndex)) {
		$cegCommentNamespaceIndex = 700;
	}

	define('CE_COMMENT_NS', $cegCommentNamespaceIndex);
	define('CE_COMMENT_NS_TALK', $cegCommentNamespaceIndex+1);

	cefInitContentLanguage($wgLanguageCode);

	// Register namespace identifiers
	if (!is_array($wgExtraNamespaces)) {
		$wgExtraNamespaces = array();
	}

	$ceNamespaces = $cegContLang->getNamespaces();
	$ceNamespacealiases = $cegContLang->getNamespaceAliases();
	$wgExtraNamespaces = $wgExtraNamespaces + $ceNamespaces;
	$wgNamespaceAliases = $wgNamespaceAliases + $ceNamespacealiases;

	// make the NS semantic
	global $smwgNamespacesWithSemanticLinks;
	$smwgNamespacesWithSemanticLinks[CE_COMMENT_NS] = true;

	wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	return true;
}

/**
 * Internationalized messages
 */

/**
 * Set up (possibly localised) names for SemanticComments
 */
function cefAddMagicWords(&$magicWords, $langCode) {
	#$magicWords['showcomments']     = array( 0, 'showcomments' );
	#$magicWords['showcommentform']     = array( 0, 'showcommentform' );
	return true;
}

/**
 * Initialise a global language object for content language. This
 * must happen early on, even before user language is known, to
 * determine labels for additional namespaces. In contrast, messages
 * can be initialised much later when they are actually needed.
 */
function cefInitContentLanguage($langcode) {
	wfProfileIn( __METHOD__ . ' [SemanticComments]' );
	global $cegIP, $cegContLang;
	if (!empty($cegContLang)) {
		wfProfileOut( __METHOD__ . ' [SemanticComments]' );
		return;
	}

	$ceContLangFile = 'CELanguage' . str_replace( '-', '_', ucfirst( $langcode ) );
	$ceContLangClass = 'CELanguage' . str_replace( '-', '_', ucfirst( $langcode ) );
	if (file_exists($cegIP . '/languages/'. $ceContLangFile . '.php')) {
		include_once( $cegIP . '/languages/'. $ceContLangFile . '.php' );
	}

	// fallback if language not supported
	if ( !class_exists($ceContLangClass) ) {
		include_once($cegIP . '/languages/CELanguageEn.php');
		$ceContLangClass = 'CELanguageEn';
	}
	$cegContLang = new $ceContLangClass();

	wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	return true;
}

function cefInitMessages() {
	global $cegMessagesInitialized;
	if( isset( $cegMessagesInitialized ) ) {
		return true; // prevent double init
	}

	cefInitUserMessages(); // lazy init for ajax calls

	$cegMessagesInitialized = true;

	return true;
}

/**
 * Registers SemanticComments extension user messages.
 */
function cefInitUserMessages() {
	wfProfileIn( __METHOD__ . ' [SemanticComments]' );
	global $wgMessageCache, $cegContLang, $wgLanguageCode;
	cefInitContentLanguage($wgLanguageCode);

	global $cegIP, $cegLang;
	if( !empty( $cegLang ) ) {
		wfProfileOut( __METHOD__ . ' [SemanticComments]' );
		return true;
	}
	global $wgMessageCache, $wgLang;
	$cegLangClass = 'CELanguage' . str_replace( '-', '_', ucfirst( $wgLang->getCode() ) );

	if( file_exists( $cegIP . '/languages/'. $cegLangClass . '.php' ) ) {
		include_once( $cegIP . '/languages/'. $cegLangClass . '.php' );
	}
	// fallback if language not supported
	if( !class_exists( $cegLangClass ) ) {
		global $cegContLang;
		$cegLang = $cegContLang;
	} else {
		$cegLang = new $cegLangClass();
	}

	$wgMessageCache->addMessages( $cegLang->getUserMsgArray(), $wgLang->getCode() );

	wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	return true;
}

/**
 * Registers the autocompletion icons of the Comment namespace for the SMWHaloAutocompletion.
 *
 * @param array $namespaceMappings
 * @return bool
 */
function cefRegisterACIcon( &$namespaceMappings) {
	$namespaceMappings[CE_COMMENT_NS] = "/extensions/SemanticComments/skins/Comment/icons/Datawiki_comment_icon_16x16.gif";
	return true;
}

/**
 * Add SemanticComments's global JS variables
 * @param array $vars
 * @return boolean true
 */
function cefAddGlobalJSVariables( &$vars ) {
	wfProfileIn( __METHOD__ . ' [SemanticComments]' );
	global $cegScriptPath, $cegEnableRatingForArticles,
		$cegShowCommentsExpanded, $cegEnableFileAttachments,
		$cegUseRMUploadFunc, $smwgEnableRichMedia, $cegDefaultDelimiter;

	$vars['wgCEScriptPath'] = $cegScriptPath;
	$vars['wgCEUserNS'] = MWNamespace::getCanonicalName( NS_USER );
	$vars['wgCEEnableFullDeletion'] = $cegEnableRatingForArticles;
	$vars['wgCEShowCommentsExpanded'] = $cegShowCommentsExpanded;
	if( isset( $cegEnableFileAttachments ) && $cegEnableFileAttachments ) {
		$vars['wgCEEnableAttachments'] = $cegEnableFileAttachments;
		if( isset( $cegUseRMUploadFunc ) && $cegUseRMUploadFunc
			&& isset( $smwgEnableRichMedia ) && $smwgEnableRichMedia )
		{
			$uploadWindowPage = SpecialPageFactory::getPage( 'UploadWindow' );
			if( isset( $uploadWindowPage ) ) {
				$uploadWindowUrl = $uploadWindowPage->getPageTitle()->getFullURL( 'sfDelimiter=' .
					urlencode( $cegDefaultDelimiter ) . '&sfInputID=collabComEditFormFileAttach' .
					'&wpIgnoreWarning=true'
				);
				$vars['wgCEEditUploadURL'] = $uploadWindowUrl;
			}
		}
	}

	wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	return true;
}
