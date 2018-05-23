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
 * CE_CommentSpecial - comment management.
 *
 * @addtogroup SpecialPage
 *
 * @author Benjamin Langguth
 */

//@TODO: license & modify query...

class CECommentSpecial extends SpecialPage {
	public function __construct() {
		parent::__construct('SemanticComments');
	}

	function execute($query) {
		global $wgRequest, $wgOut, $wgScriptPath, $wgUser, $wgParser, $smwIP;

		$wgOut->setPageTitle($this->msg('semanticcomments'));

		$introText = $this->msg('ce_sp_intro')->escaped();
		$wgOut->addHTML($introText);
		$queryText = '{{#ask: [[Comment:+]]
  | mainlabel = Comment
  | ?Has comment person = Person
  | ?Has comment date = Date
  | ?Has comment text = Content
  | ?Has comment rating = Rating
  | ?Comment was deleted = Deleted?
  | ?Belongs to article = Related article
  | ?Belongs to comment = Related comment
  | sort = Has comment date
  | order = desc
  | limit = 200
  | searchlabel = <<...further comments>>
  | default=No comments existent in this wiki.
}}';

		$popt = new ParserOptions();
		$popt->setEditSection(false);

		$pout = $wgParser->parse($queryText, $this->getPageTitle(), $popt);
		$result = $pout->getText();
		$wgOut->addHTML($result);
	}
}
