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
 * This file contains the ajax functions of comment component for Semantic Comments extension.
 *
 * @author Peter Grassberger <petertheone@gmail.com>
 */
if ( !defined( 'MEDIAWIKI' ) ) {
	die( "This file is part of the SemanticComments extension. It is not a valid entry point.\n" );
}

class CECommentCreatePageApi extends ApiBase {

	public function execute() {
		$parameters = $this->extractRequestParams();
		$title = CECommentUtils::unescape( $parameters['title'] );
		$content = CECommentUtils::unescape( $parameters['content'] );
		$edit = false;
		if ( isset( $parameters['edit'] ) ) {
			$edit = $parameters['edit'];
		}

		$value = CEComment::createComment( $title, $content, $edit );
		$apiResult = $this->getResult();
		$apiResult->addValue(null, 'data', $value);
	}

	public function getAllowedParams() {
		return array(
			'title' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'content' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			),
			'edit' => array(
				ApiBase::PARAM_TYPE => 'boolean',
				ApiBase::PARAM_REQUIRED => false,
			),
		);
	}
}
