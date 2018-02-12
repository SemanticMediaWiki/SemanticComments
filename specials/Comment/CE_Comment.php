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
 * This file contains the implementation of comment creation for SemanticComments.
 *
 * @author Benjamin Langguth
 * @author Peter Grassberger <petertheone@gmail.com>
 * Date: 07.12.2009
 */

/**
 * @defgroup CEComment
 * @ingroup SemanticComments
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the SemanticComments extension. It is not a valid entry point.\n" );
}


class CEComment {

	/* constants */
	const SUCCESS = 0;
	const COMMENT_ALREADY_EXISTS = 1;
	const PERMISSION_ERROR = 2;

	/**
	 * This function creates a new comment article
	 * @param string $pageName
	 * @param string $pageContent
	 * @param bool $editMode
	 * @return string
	 */
	public static function createComment( $pageName, $pageContent, $editMode = false ) {
		wfProfileIn( __METHOD__ . ' [SemanticComments]' );
		global $wgUser, $cegEnableComment, $cegEnableCommentFor;

		$title = Title::newFromText( $pageName );
		if( $title->getNamespace() != CE_COMMENT_NS ) {
			$title = Title::makeTitle( CE_COMMENT_NS, $title );
		}
		$article = new Article( $title );

		# check if comments are enabled #
		if ( !isset( $cegEnableComment ) || !$cegEnableComment ) {
			wfProfileOut( __METHOD__ . ' [SemanticComments]' );
			return CECommentUtils::createXMLResponse(
				wfMessage( 'ce_cf_disabled' )->text(),
				self::PERMISSION_ERROR, $pageName
			);
		}
		# check authorization #
		if ( !isset( $cegEnableCommentFor )
			|| ( $cegEnableCommentFor == CE_COMMENT_NOBODY )
			|| ( ( $cegEnableCommentFor == CE_COMMENT_AUTH_ONLY ) && $wgUser->isAnon() ) )
		{
			wfProfileOut( __METHOD__ . ' [SemanticComments]' );
			return CECommentUtils::createXMLResponse(
				wfMessage( 'ce_cf_disabled' )->text(),
				self::PERMISSION_ERROR, $pageName );
		} else {
			//user is allowed
			if ( $article->exists() && !$editMode ) {
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return CECommentUtils::createXMLResponse(
					wfMessage( 'ce_comment_exists', $pageName )->text(),
					self::COMMENT_ALREADY_EXISTS, $pageName
				);
			}
			// Insert current Date
			$date = new DateTime();
			$dateString = $date->format( 'c' );
			if ( $editMode ) {
try{
				// use the original DATE!!!
				$comNS = MWNamespace::getCanonicalName( CE_COMMENT_NS );
				$qandp = SMWQueryProcessor::getQueryAndParamsFromFunctionParams(
						array($comNS . ":" . $pageName, "?Has comment date"),
						SMW_OUTPUT_WIKI, INLINE_QUERY, true
				);
				$queryResult = explode( "|", SMWQueryProcessor::getResultFromQuery(
							$qandp[0], $qandp[1],
							SMW_OUTPUT_WIKI, INLINE_QUERY )
				);
				//just get the first property value and use this
				if ( isset( $queryResult[0] ) ) {
					// see '/extensions/SemanticMediaWiki/includes/SMW_DV_Time.php'
					// [...] For export, times are given without timezone information. [...]
					$date = new Datetime( $queryResult[0], new DateTimeZone( 'UTC' ) );
					$dateString = $date->format( 'c' );
				}
				$responseText = wfMessage( 'ce_com_edited' )->text();
				$summary = wfMessage( 'ce_com_edit_sum' )->text();
}catch (Exception $e) {
				$responseText = "system error occurred.";
				$summary = wfMessage( 'ce_sys_err' )->text();
}
			} else {
				$responseText = wfMessage( 'ce_com_created' )->text();
				$summary = wfMessage( 'ce_com_create_sum' )->text();
			}
			$pageContent = str_replace( '##DATE##', $dateString, $pageContent );
			$pageContentObject = ContentHandler::makeContent( $pageContent, $title );
			$article->doEditContent( $pageContentObject, $summary );

			if ( $article->exists() ) {
				self::updateRelatedArticle( $pageContent );
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return CECommentUtils::createXMLResponse(
								$responseText, self::SUCCESS, $pageName
				);
			} else {
				wfProfileOut( __METHOD__ . ' [SemanticComments]' );
				return CECommentUtils::createXMLResponse(
								wfMessage( 'ce_com_edit_not_exists' )->text(), self::PERMISSION_ERROR, $pageName
				);
			}
		}
	}

	/**
	 * This function updates the related article if the new/edited/deleted comment has a rating.
	 *
	 * @param string $commentContent
	 */
	public static function updateRelatedArticle( $commentContent ) {
		wfProfileIn( __METHOD__ . ' [SemanticComments]' );
		global $wgParser, $wgUser;
		$commentHasRating = preg_match('/CommentRating=/', $commentContent);
		$find = preg_match('/CommentRelatedArticle=(.*?)\|/', $commentContent, $extract);
		$relatedArticle = $extract[1];
		if( $commentHasRating !== ( false || 0 )
			&& $relatedArticle && $relatedArticle != '' )
		{
			// update semantic data for the realted article
			$title = Title::newFromText( $relatedArticle );
			$article = new Article( $title );
			$text = ContentHandler::getContentText( $article->getPage()->getContent() );
			$options = new ParserOptions;
			$text = $wgParser->preSaveTransform( $text, $title, $wgUser, $options );
			$output = $wgParser->parse( $text, $article->mTitle, $options);
			if ( isset( $output->mSMWData ) ) {
				$store = smwfGetStore();
				$store->updateData( $output->mSMWData );
			}
		}
		wfProfileOut( __METHOD__ . ' [SemanticComments]' );
	}
}