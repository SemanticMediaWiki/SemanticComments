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
 * Utils for comment component of SemanticComments extension.
 *
 * @author Benjamin Langguth
 * Date: 16.11.2009
 *
 */


define('CE_COM_RESP_START',
			'<?xml version="1.0"?>'."\n".
			'<ReturnValue xmlns="http://www.ontoprise.de/collaboration#">'."\n".
    		'<value>');

define('CE_COM_RESP_MIDDLE1',
			'</value>'. "\n" .
			'<message>');

define('CE_COM_RESP_MIDDLE2',
			'</message>'. "\n" .
			'<article>');

define('CE_COM_RESP_END',
			'</article>'."\n".
    		'</ReturnValue>'."\n");


class CECommentUtils {

	/**
	 * Create a response for the AJAX functions of SemanticComments Comment.
	 *
	 * Example:
	 *
	 * @param string $status The status of the operation: success or failed.
	 * @param string $message The user message of the function status.
	 * @param string pageName The corresponding page name
	 *
	 * @return string The XML string.
	 */
	public static function createXMLResponse( $message, $statusCode = 0, $pageName = '' ){
		
		$xmlString = CE_COM_RESP_START;
		$xmlString .= $statusCode;
		$xmlString .= CE_COM_RESP_MIDDLE1;
		$xmlString .= $message;
		$xmlString .= CE_COM_RESP_MIDDLE2;
		$xmlString .= $pageName;
		$xmlString .= CE_COM_RESP_END;
		return $xmlString;

	}

	/**
	 *
	 * @param <string> javascript-escaped string
	 * @return <string> unescaped string
	 */
//from php.net	
public static function code2utf($num){
  if($num<128)
    return chr($num);
  if($num<1024)
    return chr(($num>>6)+192).chr(($num&63)+128);
  if($num<32768)
    return chr(($num>>12)+224).chr((($num>>6)&63)+128)
          .chr(($num&63)+128);
  if($num<2097152)
    return chr(($num>>18)+240).chr((($num>>12)&63)+128)
          .chr((($num>>6)&63)+128).chr(($num&63)+128);
  return '';
}
	public static function unescape($source) {
		$decodedStr = '';
		$pos = 0;
		$len = strlen ($source);

		while ($pos < $len) {
			$charAt = substr ($source, $pos, 1);
			if ($charAt == '%') {
				$pos++;
				$charAt = substr ($source, $pos, 1);
				if ($charAt == 'u') {
					// we got a unicode character
					$pos++;
					$unicodeHexVal = substr ($source, $pos, 4);
					$unicode = hexdec ($unicodeHexVal);
					$decodedStr .= CECommentUtils::code2utf($unicode);
					$pos += 4;
				} else {
					// we have an escaped ascii character
					$hexVal = substr ($source, $pos, 2);
					$decodedStr .= CECommentUtils::code2utf (hexdec ($hexVal));
					$pos += 2;
				}
			} else {
				$decodedStr .= $charAt;
				$pos++;
			}
		}
		return $decodedStr;
	}
	
}
