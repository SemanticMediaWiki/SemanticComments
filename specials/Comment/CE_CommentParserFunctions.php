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
 * @ingroup CEComment
 *
 * This file contains takes care about parser functions
 * for comment component of SemanticComments extension.
 *
 * @author Benjamin Langguth
 * Date: 16.11.2009
 *
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the SemanticComments extension. It is not a valid entry point.\n" );
}

### Includes ###
global $cegIP;

$wgExtensionFunctions[] = 'cefInitCommentParserfunctions';

$wgHooks['LanguageGetMagic'][] = 'cefCommentLanguageGetMagic';


function cefInitCommentParserfunctions() {
	global $wgParser;

	CECommentParserFunctions::getInstance();

	$wgParser->setFunctionHook( 'showcommentform', array( 'CECommentParserFunctions', 'showcommentform' ) );
	$wgParser->setFunctionHook( 'averagerating', array( 'CECommentParserFunctions', 'getAverageRating' ) );
	$wgParser->setFunctionHook( 'arraymapce', array( 'CECommentParserFunctions', 'renderArrayMap' ) );
	$wgParser->setFunctionHook( 'bin2hex', array( 'CECommentParserFunctions', 'ceBin2Hex' ) );

	return true;
}

function cefCommentLanguageGetMagic( &$magicWords, $langCode ) {
	global $cegContLang;
	$magicWords['showcommentform'] = array(0, 'showcommentform' );
	$magicWords['averagerating'] = array( 0, 'averagerating' );
	$magicWords['arraymapce'] = array ( 0, 'arraymapce' );
	$magicWords['bin2hex'] = array ( 0, 'bin2hex' );

	return true;
}


/**
 * The class CECommentParserFunctions contains all parser functions of the Comment component of
 * SemanticComments extension. The following functions are parsed:
 * - show comment form
 *
 */
class CECommentParserFunctions {

	//--- Constants ---
	const SUCCESS = 0;

	const FORM_ALREADY_SHOWN = 1;
	const NOBODY_ALLOWED_TO_COMMENT = 2;
	const USER_NOT_ALLOWED_TO_COMMENT = 3;

	const COMMENTS_DISABLED = 4;
	const COMMENTS_FOR_NOT_DEF = 5;


	//--- Private fields ---
	// Title: The title to which the functions are applied
	private $mTitle = 0;

	// bool: Is the form already displayed?
	private $mCommentFormDisplayed = false;

	// bool: list of related comment titles
	private $mRelCommentTitles = array();

	// bool: true if all parser functions of an article are valid
	private $mDefinitionValid = true;

	// CECommentParserFunctions: The only instance of this class
	private static $mInstance = null;

	/**
	 * Constructor for CECommentParserFunctions. This object is a singleton.
	 */
	public function __construct() {
	}

	//--- Public methods ---

	public static function getInstance() {
		if (is_null(self::$mInstance)) {
			self::$mInstance = new self;
		}
		return self::$mInstance;
	}

	/**
	 * Callback for parser function "#showcommentform:".
	 * This parser function displays the commentform.
	 * It can appear only once in an article and has the following parameters:
	 * ratingstyle:
	 *
	 * @param Parser $parser
	 * 		The parser object
	 *
	 * @return string
	 * 		Wikitext
	 *
	 * @throws
	 * 		CEException(CEException::INTERNAL_ERROR)
	 * 			... if there's sthg wrong, that can not be caught by CE itself
	 */
	public static function showcommentform(&$parser) {
		wfProfileIn( __METHOD__ . ' [SemanticComments]' );
		global $cegContLang, $wgUser, $cegScriptPath, $cegEnableRatingForArticles,
			$cegEnableFileAttachments, $cegUseRMUploadFunc, $cegDefaultDelimiter,
			$smwgEnableRichMedia, $wgJsMimeType, $wgParser;

		# do checks #
		$status = self::$mInstance->doInitialChecks($parser);

		switch ($status) {
			case self::SUCCESS:
				//continue
				break;
			case self::COMMENTS_DISABLED:
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return self::$mInstance->commentFormWarning(wfMessage('ce_cf_disabled')->text());
			case self::COMMENTS_FOR_NOT_DEF:
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return self::$mInstance->commentFormWarning(wfMessage('ce_var_undef', 'cegEnableCommentFor')->text());
			case self::NOBODY_ALLOWED_TO_COMMENT:
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return self::$mInstance->commentFormWarning(wfMessage('ce_cf_all_not_allowed')->text());
			case self::USER_NOT_ALLOWED_TO_COMMENT:
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return self::$mInstance->commentFormWarning(wfMessage('ce_cf_you_not_allowed')->text());
			case self::FORM_ALREADY_SHOWN:
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return self::$mInstance->commentFormWarning(wfMessage('ce_cf_already_shown')->text());
			default:
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				throw new CEException(CEException::INTERNAL_ERROR, __METHOD__ . ": Unknown value `{$status}` <br/>" );
		}

		$encPreComment = htmlspecialchars(wfMessage('ce_cf_predef')->text());
		$comment_disabled = '';

		#rating#
		$ratingValues = array( 0 => wfMessage('ce_ce_rating_0')->text(),
			1 => wfMessage('ce_ce_rating_1')->text(),
			2 => wfMessage('ce_ce_rating_2')->text());
		$ratingTitleBad = wfMessage('ce_cf_rating_title_b')->text();
		$ratingTitleNeutral = wfMessage('ce_cf_rating_title_n')->text();
		$ratingTitleGood = wfMessage('ce_cf_rating_title_g')->text();

		#user#
		$currentUser = $wgUser->getName();
		if($wgUser->isAnon()) {
			$userImageTitle = Title::newFromText('defaultuser.gif', NS_FILE);
			$userIsSysopJSText = 'var wgCEUserIsSysop = false;';
			if($userImageTitle->exists()){
				$image = wfLocalFile($userImageTitle);
				$userImgSrc = $image->getURL();
			}
		} else {
			//user should be saved with Namespace!
			$ns = MWNamespace::getCanonicalName(NS_USER);
			SMWQueryProcessor::processFunctionParams(
				array(
					"[[".$ns.":".$wgUser->getName()."]]",
					"[[User_image::+]]",
					"?User_image="
				),
				$querystring,
				$params,
				$printouts
			);
			$params = SMWQueryProcessor::getProcessedParams(
				$params,
				$printouts
			);
			$query = SMWQueryProcessor::createQuery(
				$querystring,
				$params,
				SMWQueryProcessor::INLINE_QUERY,
				"",
				$printouts
			);
			$queryResult = explode( "|",
				SMWQueryProcessor::getResultFromQuery(
					$query,
					$params,
					$printouts,
					SMW_OUTPUT_WIKI,
					SMWQueryProcessor::INLINE_QUERY,
					""
				)
			);
			unset($queryResult[0]);
			//just get the first property value and use this
			if(isset($queryResult[1])) {
				$userImageTitle = Title::newFromText($queryResult[1], NS_FILE);
				if($userImageTitle->exists()){
					$image = wfLocalFile($userImageTitle);
					$userImgSrc = $image->getURL();
				}
			}
			// Get users groups and check for Sysop-Rights
			$groups = $wgUser->getEffectiveGroups();
			if (in_array( 'sysop', $groups ) == 1) {
				//provide delete link for every existing comment
				$userIsSysopJSText = 'var wgCEUserIsSysop = true;';
			} else {
				$userIsSysopJSText = 'var wgCEUserIsSysop = false;';
			}
		}
		if(!isset($userImgSrc) || !$userImgSrc) {
			// We provide own icon, if there is none in the wiki
			$userImgSrc = $cegScriptPath. '/skins/Comment/icons/defaultuser.gif';
		}

		$submitButtonID = 'collabComFormSubmitbuttonID';
		$resetButtonID = 'collabComFormResetbuttonID';

		$ratingHTML = '';
		if( isset($cegEnableRatingForArticles) && $cegEnableRatingForArticles ) {
			$ratingHTML = Xml::openElement('div', array( 'id' => 'collabComFormRating')) .
					wfMessage('ce_cf_article_rating')->text() .
					'<span class="collabComFormGrey">' . '&nbsp;' .
						wfMessage('ce_cf_article_rating2')->text() .
					'</span>' . ":" .
					Xml::openElement('span', array( 'id' => 'collabComFormRadiobuttons' )) .
						Xml::Element('img', array( 'id' => 'collabComFormRating1',
							'class' => 'collabComFormRatingImg',
							'src' => $cegScriptPath . '/skins/Comment/icons/bad_inactive.png',
							'title' => $ratingTitleBad,
							'onClick' => 'ceCommentForm.switchRating(\'#collabComFormRating1\',-1);' )) .
						Xml::Element('img', array( 'id' => 'collabComFormRating2',
							'class' => 'collabComFormRatingImg',
							'src' => $cegScriptPath . '/skins/Comment/icons/neutral_inactive.png',
							'title' => $ratingTitleNeutral,
							'onClick' => 'ceCommentForm.switchRating(\'#collabComFormRating2\',0);' )) .
						Xml::Element('img', array( 'id' => 'collabComFormRating3',
							'class' => 'collabComFormRatingImg',
							'title' => $ratingTitleGood,
							'src' => $cegScriptPath . '/skins/Comment/icons/good_inactive.png',
							'onClick' => 'ceCommentForm.switchRating(\'#collabComFormRating3\',1);' )) .
					Xml::closeElement('span') .
				Xml::closeElement('div');

			global $cegAllowEmptyComments;
			$script = '<script type="'.$wgJsMimeType.'">/*<![CDATA[*/'.
				'var wgCEEnableRating = true;' .
				'var wgCEAllowEmptyComments = ' . ($cegAllowEmptyComments ? 'true' : 'false') . ';' .
				'/*]]>*/</script>';
			SMWOutputs::requireHeadItem('CEJS_Variables2', $script);
		}

		$script = '<script type="'.$wgJsMimeType.'">/*<![CDATA[*/'.
			($userIsSysopJSText? $userIsSysopJSText : "") .
			'/*]]>*/</script>';
		SMWOutputs::requireHeadItem('CEJS_Variables3', $script);

		// file attachments
		$fileAttachmentHTML = '';
		if( isset( $cegEnableFileAttachments ) && $cegEnableFileAttachments ) {
			$fileAttachmentHTML = Xml::openElement( 'div',
				array( 'id' => 'collabComFormFileAttachHelp' ) ) .
				wfMessage( 'ce_cf_file_attach' )->text() . Xml::closeElement( 'div' ) .
				Xml::input( 'collabComFormFileAttach', '', '',
					array( 'id' => 'collabComFormFileAttach',
						'class' => 'wickEnabled',
						'pastens' => 'true'
					)
				);
			if( isset( $cegUseRMUploadFunc ) && $cegUseRMUploadFunc
				&& isset( $smwgEnableRichMedia ) && $smwgEnableRichMedia ) {
				// we need an additional upload link that is connected to the input field
				$uploadtext = 'Upload file';
				$uploadTitle = 'Upload title';
				$rmlWikiText = '{{#rml:' . wfMessage('ce_cf_file_upload_text')->text() . '|' .
					wfMessage('ce_cf_file_upload_link')->text() . '|sfInputID=collabComFormFileAttach&sfDelimiter=' .
					$cegDefaultDelimiter . '}}';
				$fileAttachmentHTML .= Xml::openElement( 'span', array(
						'id' => 'collabComFormFileAttachLink' ) ) .
					$wgParser->recursiveTagParse( $rmlWikiText ) .
					Xml::closeElement( 'span' );
			}
		}
		$html = Xml::openElement( 'div', array( 'id' => 'collabComFormHeader' )) .
			Xml::openElement( 'form', array( 'id' => 'collabComForm',
			'style' => 'display:none',
			'onSubmit' => 'return ceCommentForm.processForm()' ) ) .
			Xml::openElement('div', array('id' => 'collabComFormUserIcon')) .
				Xml::Element( 'img', array( 'id' => 'collabComFormUserImg',
					'src' => $userImgSrc? $userImgSrc : '' )) .
			Xml::closeElement('div') .
			Xml::openElement('div', array('id' => 'collabComFormRight')) .
				Xml::openElement( 'div', array( 'id' => 'collabComFormUser') ) .
					'<span class="userkey">' .wfMessage('ce_cf_author')->escaped() . '</span>' .
					'<span class="uservalue">' .
						$parser->recursiveTagParse('[['.$wgUser->getUserPage()->getPrefixedText().'|'.$currentUser.']]') . '</span>' .
				Xml::closeElement('div') .
				$ratingHTML .
				Xml::openElement('div', array( 'id' => 'collabComFormHelp')) .
					wfMessage('ce_cf_comment')->escaped() .
					Xml::openElement('span', array('class' => 'red')) .
						'*' .
					Xml::closeElement('span') .
					Xml::openElement('span') . ':' . Xml::closeElement('span') .
				Xml::closeElement('div') .
				Xml::openElement('textarea', array( 'id' => 'collabComFormTextarea',
					'rows' => '5', 'defaultValue' => $encPreComment,
					'onClick' => 'ceCommentForm.selectTextarea();',
					'onKeyDown' => 'ceCommentForm.textareaKeyPressed();')) .
				$encPreComment .
				Xml::closeElement('textarea') .
				$fileAttachmentHTML .
				Xml::openElement('div', array( 'id' => 'collabComFormButtons' ) ) .
			Xml::submitButton( wfMessage( 'ce_cf_submit_button_name' )->text(),
				array ( 'id' => $submitButtonID) ) .
			Xml::element( 'span', array(
				'id' => $resetButtonID,
				'onClick' => 'ceCommentForm.formReset();')) .
				' | ' . wfMessage( 'ce_cf_reset_button_name' )->escaped() .
			Xml::closeElement('span') .
			Xml::closeElement('div') . //end collabComFormRight
			Xml::closeElement('div') . //end collabComFormButtons
			Xml::closeElement('form') .
			Xml::openElement('div', array('id' => 'collabComFormMessage',
				'style' => 'display:none')) .
			Xml::closeElement('div') .
			Xml::closeElement('div');

		self::$mInstance->mCommentFormDisplayed = true;

		wfProfileOut( __METHOD__ . ' [SemanticComments]' );
		return $parser->insertStripItem( $html, $parser->mStripState );
	}

	/**
	 * Function to get the average rating for an article.
	 *
	 * @param Parser $parser
	 */
	public static function getAverageRating(&$parser) {
		wfProfileIn( __METHOD__ . ' [SemanticComments]' );
		$title = $parser->getTitle();
		if (self::$mInstance->mTitle == null) {
			self::$mInstance->mTitle = $title;
		}

		SMWQueryProcessor::processFunctionParams(
			array(
				"[[Category:Comment]] [[Belongs to article::" . $title->getFullText() . "]]",
				"[[Has comment rating::+]]",
				"[[Comment was deleted::!true]]",
				"?Has comment rating=",
				"format=list",
				"mainlabel=-",
				"searchlabel="
			),
			$querystring,
			$params,
			$printouts
		);
		$params = SMWQueryProcessor::getProcessedParams(
			$params,
			$printouts
		);
		$query = SMWQueryProcessor::createQuery(
			$querystring,
			$params,
			SMWQueryProcessor::INLINE_QUERY,
			"",
			$printouts
		);
		$queryResult = explode( "|",
			SMWQueryProcessor::getResultFromQuery(
				$query,
				$params,
				$printouts,
				SMW_OUTPUT_WIKI,
				SMWQueryProcessor::INLINE_QUERY,
				""
			)
		);

//		SMWQueryProcessor::processFunctionParams(
//			array("[[Category:Comment]] [[Belongs to article::" . $title->getFullText() . "]]",
//				"[[Has comment rating::+]]", "[[Comment was deleted::!true]]",
//				"?Has comment rating=", "format=list", "mainlabel=-", "searchlabel="
//			),
//			$querystring, $params, $printouts
//		);
//		$queryResult = explode( "," ,
//			SMWQueryProcessor::getResultFromQueryString(
//				$querystring, $params, $printouts, SMW_OUTPUT_WIKI
//			)
//		);
		$count = count( $queryResult );
		if( $count == 0 ) {
			wfProfileOut( __METHOD__ . ' [SemanticComments]' );
			return '';
		}
		$sum = 0;
		$avg = 0;
		foreach ( $queryResult as $res ) {
			$sum += $res;
		}

		wfProfileOut( __METHOD__ . ' [SemanticComments]' );
		return $sum / $count;
	}

	/**
	 * Function to convert binary strings to hex equivalents.
	 *
	 * @param Parser $parser
	 * @param String $str
	 */
	public static function ceBin2Hex ( &$parser, $str = '' ) {
		return bin2hex( $str );
	}

	/**
	 * This function is equal to Semantic Form's parser function 'arraymap'
	 * to store attached articles as property values.
	 * We can skip a template like 'http://meta.wikimedia.org/wiki/Template:For' with this PF.
	 *
	 * {{#arraymapce:value|delimiter|var|formula|new_delimiter}}
	 *
	 * @param parser the parser object
	 * @param value
	 * @param delimiter the actual delimiter
	 * @param var the variable name
	 * @param formula the formula used to represent the new value
	 * @param delimiter the new delimiter
	 */
	static function renderArrayMap( &$parser, $value = '', $delimiter = ',', $var = 'x', $formula = 'x', $new_delimiter = ', ' ) {
		wfProfileIn( __METHOD__ . ' [SemanticComments]' );
		// let '\n' represent newlines - chances that anyone will
		// actually need the '\n' literal are small
		$delimiter = str_replace( '\n', "\n", $delimiter );
		$actual_delimiter = $parser->mStripState->unstripNoWiki( $delimiter );
		$new_delimiter = str_replace( '\n', "\n", $new_delimiter );

		if ( $actual_delimiter == '' ) {
			$values_array = preg_split( '/(.)/u', $value, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE );
		} else {
			$values_array = explode( $actual_delimiter, $value );
		}

		$results = array();
		foreach ( $values_array as $cur_value ) {
			$cur_value = trim( $cur_value );
			// ignore a value if it's null
			if ( $cur_value != '' ) {
				// remove whitespaces
				$results[] = str_replace( $var, $cur_value, $formula );
			}
		}

		wfProfileOut( __METHOD__ . ' [SemanticComments]' );
		return implode( $new_delimiter, $results );
	}

	/**
	 * This method is called, when an article is deleted. If the article "has" comment article(s)
	 * they should be also deleted to prevent article corps.
	 *
	 * @param unknown_type $specialPage
	 * @param Title $title
	 */
	public static function articleDelete(&$specialPage, &$title) {
		$name = $title->getFullText();
		// Check if the title has comment article(s)
		// @todo FIXME: $this is not available in static context.
		if (empty($this->mRelCommentTitles))
			$this->mRelCommentTitles = CECommentQuery::getRelatedCommentArticles($title, '');
		if ($this->mRelCommentTitles !== false && is_array($this->mRelCommentTitles) &&
			!empty($this->mRelCommentTitles)) {
			$msg = CECommentStorage::deleteRelatedCommentArticles($this->mRelCommentTitles);
		}
		return true;
	}

	/**
	 * This method is called, when an article is undeleted.
	 * Well, wee need a DB Table that stores the article - comment - relation
	 * just for this functionality :(
	 *
	 * @param unknown_type $specialPage
	 * @param Title $title
	 */
	public static function articleUndelete(&$title, &$create) {
		$name = $title->getFullText();
		// Check if the old title has comment article(s)
		// -> we need check in the table!

		return true;
	}


	### Private Functions ###

	/**
	 * Returns the parser function parameters that were passed to the parser-function
	 * callback.
	 *
	 * @param array(mixed) $args
	 * 		Arguments of a parser function callback
	 * @return array(string=>string)
	 * 		Array of argument names and their values.
	 */
	private function getParameters($args) {
		$parameters = array();

		foreach ($args as $arg) {
			if (!is_string($arg)) {
				continue;
			}
			if (preg_match('/^\s*(.*?)\s*=\s*(.*?)\s*$/', $arg, $p) == 1) {
				$parameters[strtolower($p[1])] = $p[2];
			}
		}

		return $parameters;
	}

	/**
	 * does all the checks.
	 *
	 * @param $parser Parser object
	 * @return status code (see defines at top)
	 */
	private function doInitialChecks(&$parser) {
		global $cegContLang, $wgUser;

		$pfContLangName = $cegContLang->getParserFunction(CELanguage::CE_PF_SHOWCOMMENTS);


		# Check if titles fit #
		$title = $parser->getTitle();
		if (self::$mInstance->mTitle == null) {
			self::$mInstance->mTitle = $title;
		}
		// disabled to due problems when importing or updating articles.
//		 else if ($title->getArticleID() != self::$mInstance->mTitle->getArticleID()) {
//			throw new CEException(CEException::INTERNAL_ERROR,
//                "The parser functions " . $pfContLangName .
//                "are called for different articles.");
//		}

		global $cegEnableComment, $cegEnableCommentFor;
		# check if comments enabled #
		if ( !isset($cegEnableComment) || !$cegEnableComment ) {
			return self::COMMENTS_DISABLED;
		}
		if ( !isset($cegEnableCommentFor) ) {
			return self::COMMENTS_FOR_NOT_DEF;
		}
		# check authorization #
		if ($cegEnableCommentFor == CE_COMMENT_NOBODY) {
			return self::NOBODY_ALLOWED_TO_COMMENT;
		} elseif ( ($cegEnableCommentFor == CE_COMMENT_AUTH_ONLY) &&
			$wgUser->isAnon() ) {
			return self::USER_NOT_ALLOWED_TO_COMMENT;
		} else {
			//user is allowed
			/* leads to strange errors in IE. check that again.
			if( self::$mInstance->mCommentFormDisplayed ) {
				return self::FORM_ALREADY_SHOWN;
			} else {*/
				return self::SUCCESS;
			/*}*/
		}
	}

	/**
	 * There's something wrong with the comment function.
	 *
	 * @param string $warning as HTML
	 * @access private
	 */
	private function commentFormWarning( $warning ) {
		global $wgJsMimeType;
		$script =<<<END
<script type="{$wgJsMimeType}">/* <![CDATA[ */
var wgCECommentsDisabled = true;
/* ]]> */ </script>
END;
		SMWOutputs::requireHeadItem('CEJS_Disabled', $script);

		$html = '<h2>' . wfMessage( 'ce_warning' )->escaped() . "</h2>\n";
		$html .= '<ul class="collabComWarning">' . $warning . "</ul>\n";
		return $html;
	}

} //end class CECommentParserFunctions
