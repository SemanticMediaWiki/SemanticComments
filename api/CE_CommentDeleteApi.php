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

use SMW\MediaWiki\Api\ApiRequestParameterFormatter;

/**
 * @file
 * @ingroup SemanticComments
 *
 * This file contains the ajax functions of comment component for Semantic Comments extension.
 *
 * @author Peter Grassberger <petertheone@gmail.com>
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the SemanticComments extension. It is not a valid entry point.\n" );
}

class CECommentDeleteApi extends ApiBase {

	public function execute() {
		$parameters = $this->extractRequestParams();
		$commentsToDelete = CECommentUtils::unescape( $parameters['commentsToDelete'] );
		$fullDelete = false;
		if ( isset( $parameters['fullDelete'] ) ) {
			$fullDelete = $parameters['fullDelete'];
		}

		$value = null;
		if ($fullDelete) {
			$value = $this->fullDeleteComments( $commentsToDelete );
		} else {
			$value = $this->deleteComment( $commentsToDelete );
		}
		$apiResult = $this->getResult();
		$apiResult->addValue(null, 'data', $value);
	}

	private function deleteComment( $pageName ) {
		wfProfileIn( __METHOD__ . ' [Semantic Comments]' );
		global $wgUser;
		$pageName = CECommentUtils::unescape( $pageName );
		$result = wfMessage( 'ce_nothing_deleted' )->text();
		$success = true;
		if ( $pageName != null ) {
			try {
				$title = Title::newFromText( $pageName );
				if ( $title->getNamespace() != CE_COMMENT_NS ) {
					$title = Title::makeTitle( CE_COMMENT_NS, $title );
				}
				$article = new Article( $title );
				$articleContentText = ContentHandler::getContentText( $article->getPage()->getContent() );
				$date = new Datetime( null, new DateTimeZone( 'UTC' ) );
				$articleContentText = preg_replace( '/\|CommentContent.*}}/',
						'|CommentContent=' . $wgUser->getName() . ' ' .
						wfMessage( 'ce_comment_has_deleted' )->text() . ' ' .
						$date->format( 'r' ) . '|CommentWasDeleted=true|}}',
						$articleContentText
				);
				$article->doEditContent( ContentHandler::makeContent( $articleContentText, $title ), wfMessage( 'ce_comment_delete_reason' )->text() );
				CEComment::updateRelatedArticle( $articleContentText );
				$result = wfMessage( 'ce_comment_deletion_successful' )->text();
				wfProfileOut( __METHOD__ . ' [Semantic Comments]' );
				return CECommentUtils::createXMLResponse( $result, '0', $pageName );
			} catch( Exception $e ) {
				$result .= wfMessage( 'ce_comment_deletion_error' )->text();
				$success = false;
				wfProfileOut( __METHOD__ . ' [Semantic Comments]' );
				return CECommentUtils::createXMLResponse( $result, '1', $pageName );
			}
		}

		wfProfileOut( __METHOD__ . ' [Semantic Comments]' );
		return CECommentUtils::createXMLResponse( 'sth went wrong here', '1', $pageName );
	}

	private function fullDeleteComments( $pageNames ) {
		wfProfileIn( __METHOD__ . ' [Semantic Comments]' );
		global $wgUser;
		$pageNames = CECommentUtils::unescape( $pageNames );
		$pageNames = explode( ',', $pageNames );
		$result = wfMessage( 'ce_nothing_deleted' )->text();
		$success = false;
		foreach ( $pageNames as $pageName) {
			try {
				$title = Title::newFromText( $pageName );
				if ( $title->getNamespace() != CE_COMMENT_NS ) {
					$title = Title::makeTitle( CE_COMMENT_NS, $title );
				}
				$article = new Article( $title );
				$articleContentText = ContentHandler::getContentText( $article->getPage()->getContent() );
				$articleDel = $article->doDelete( wfMessage( 'ce_comment_delete_reason' )->text() );
				$success = true;
			} catch( Exception $e ) {
				$result .= wfMessage( 'ce_comment_deletion_error' )->text();
				$success = false;
				wfProfileOut( __METHOD__ . ' [Semantic Comments]' );
				return CECommentUtils::createXMLResponse( $result, '1', $pageName);
			}
		}
		if( $success ) {
			$result = wfMessage( 'ce_comment_massdeletion_successful' )->text();
			wfProfileOut( __METHOD__ . ' [Semantic Comments]' );
			return CECommentUtils::createXMLResponse( $result, '0', $pageNames[0] );
		} else {
			$pageNames = json_encode($pageNames);
			wfProfileOut( __METHOD__ . ' [Semantic Comments]' );
			return CECommentUtils::createXMLResponse( 'sth went wrong here', '1', $pageNames );
		}
	}

	public function getAllowedParams() {
		return array(
			'commentsToDelete' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'fullDelete' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_REQUIRED => false,
			),
		);
	}
}
