<?php
/**
 *
 *
 * Created on Sep 25, 2006
 *
 * Copyright © 2006 Yuri Astrakhan "<Firstname><Lastname>@gmail.com"
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
 */

/**
 * Query module to enumerate all available pages.
 *
 * @ingroup API
 */
class ApiQueryAllPages extends ApiQueryGeneratorBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'ap' );
	}

	public function execute() {
		$this->run();
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	/**
	 * @param ApiPageSet $resultPageSet
	 * @return void
	 */
	public function executeGenerator( $resultPageSet ) {
		if ( $resultPageSet->isResolvingRedirects() ) {
			$this->dieUsage(
				'Use "gapfilterredir=nonredirects" option instead of "redirects" ' .
					'when using allpages as a generator',
				'params'
			);
		}

		$this->run( $resultPageSet );
	}

	/**
	 * @param ApiPageSet $resultPageSet
	 * @return void
	 */
	private function run( $resultPageSet = null ) {
		$db = $this->getDB();

		$params = $this->extractRequestParams();

		// Page filters
		$this->addTables( 'page' );

		if ( !is_null( $params['continue'] ) ) {
			$cont = explode( '|', $params['continue'] );
			$this->dieContinueUsageIf( count( $cont ) != 1 );
			$op = $params['dir'] == 'descending' ? '<' : '>';
			$cont_from = $db->addQuotes( $cont[0] );
			$this->addWhere( "page_title $op= $cont_from" );
		}

		if ( $params['filterredir'] == 'redirects' ) {
			$this->addWhereFld( 'page_is_redirect', 1 );
		} elseif ( $params['filterredir'] == 'nonredirects' ) {
			$this->addWhereFld( 'page_is_redirect', 0 );
		}

		$this->addWhereFld( 'page_namespace', $params['namespace'] );
		$dir = ( $params['dir'] == 'descending' ? 'older' : 'newer' );
		$from = ( $params['from'] === null
			? null
			: $this->titlePartToKey( $params['from'], $params['namespace'] ) );
		$to = ( $params['to'] === null
			? null
			: $this->titlePartToKey( $params['to'], $params['namespace'] ) );
		$this->addWhereRange( 'page_title', $dir, $from, $to );

		if ( isset( $params['prefix'] ) ) {
			$this->addWhere( 'page_title' . $db->buildLike(
				$this->titlePartToKey( $params['prefix'], $params['namespace'] ),
				$db->anyString() ) );
		}

		if ( is_null( $resultPageSet ) ) {
			$selectFields = array(
				'page_namespace',
				'page_title',
				'page_id'
			);
		} else {
			$selectFields = $resultPageSet->getPageTableFields();
		}

		$this->addFields( $selectFields );
		$forceNameTitleIndex = true;
		if ( isset( $params['minsize'] ) ) {
			$this->addWhere( 'page_len>=' . intval( $params['minsize'] ) );
			$forceNameTitleIndex = false;
		}

		if ( isset( $params['maxsize'] ) ) {
			$this->addWhere( 'page_len<=' . intval( $params['maxsize'] ) );
			$forceNameTitleIndex = false;
		}

		// Page protection filtering
		if ( count( $params['prtype'] ) || $params['prexpiry'] != 'all' ) {
			$this->addTables( 'page_restrictions' );
			$this->addWhere( 'page_id=pr_page' );
			$this->addWhere( "pr_expiry > {$db->addQuotes( $db->timestamp() )} OR pr_expiry IS NULL" );

			if ( count( $params['prtype'] ) ) {
				$this->addWhereFld( 'pr_type', $params['prtype'] );

				if ( isset( $params['prlevel'] ) ) {
					// Remove the empty string and '*' from the prlevel array
					$prlevel = array_diff( $params['prlevel'], array( '', '*' ) );

					if ( count( $prlevel ) ) {
						$this->addWhereFld( 'pr_level', $prlevel );
					}
				}
				if ( $params['prfiltercascade'] == 'cascading' ) {
					$this->addWhereFld( 'pr_cascade', 1 );
				} elseif ( $params['prfiltercascade'] == 'noncascading' ) {
					$this->addWhereFld( 'pr_cascade', 0 );
				}
			}
			$forceNameTitleIndex = false;

			if ( $params['prexpiry'] == 'indefinite' ) {
				$this->addWhere( "pr_expiry = {$db->addQuotes( $db->getInfinity() )} OR pr_expiry IS NULL" );
			} elseif ( $params['prexpiry'] == 'definite' ) {
				$this->addWhere( "pr_expiry != {$db->addQuotes( $db->getInfinity() )}" );
			}

			$this->addOption( 'DISTINCT' );
		} elseif ( isset( $params['prlevel'] ) ) {
			$this->dieUsage( 'prlevel may not be used without prtype', 'params' );
		}

		if ( $params['filterlanglinks'] == 'withoutlanglinks' ) {
			$this->addTables( 'langlinks' );
			$this->addJoinConds( array( 'langlinks' => array( 'LEFT JOIN', 'page_id=ll_from' ) ) );
			$this->addWhere( 'll_from IS NULL' );
			$forceNameTitleIndex = false;
		} elseif ( $params['filterlanglinks'] == 'withlanglinks' ) {
			$this->addTables( 'langlinks' );
			$this->addWhere( 'page_id=ll_from' );
			$this->addOption( 'STRAIGHT_JOIN' );
			// We have to GROUP BY all selected fields to stop
			// PostgreSQL from whining
			$this->addOption( 'GROUP BY', $selectFields );
			$forceNameTitleIndex = false;
		}

		if ( $forceNameTitleIndex ) {
			$this->addOption( 'USE INDEX', 'name_title' );
		}

		$limit = $params['limit'];
		$this->addOption( 'LIMIT', $limit + 1 );
		$res = $this->select( __METHOD__ );

		//Get gender information
		if ( MWNamespace::hasGenderDistinction( $params['namespace'] ) ) {
			$users = array();
			foreach ( $res as $row ) {
				$users[] = $row->page_title;
			}
			GenderCache::singleton()->doQuery( $users, __METHOD__ );
			$res->rewind(); //reset
		}

		$count = 0;
		$result = $this->getResult();
		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				// We've reached the one extra which shows that there are
				// additional pages to be had. Stop here...
				$this->setContinueEnumParameter( 'continue', $row->page_title );
				break;
			}

			if ( is_null( $resultPageSet ) ) {
				$title = Title::makeTitle( $row->page_namespace, $row->page_title );
				$vals = array(
					'pageid' => intval( $row->page_id ),
					'ns' => intval( $title->getNamespace() ),
					'title' => $title->getPrefixedText()
				);
				$fit = $result->addValue( array( 'query', $this->getModuleName() ), null, $vals );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue', $row->page_title );
					break;
				}
			} else {
				$resultPageSet->processDbRow( $row );
			}
		}

		if ( is_null( $resultPageSet ) ) {
			$result->setIndexedTagName_internal( array( 'query', $this->getModuleName() ), 'p' );
		}
	}

	public function getAllowedParams() {
		global $wgRestrictionLevels;

		return array(
			'from' => null,
			'continue' => null,
			'to' => null,
			'prefix' => null,
			'namespace' => array(
				ApiBase::PARAM_DFLT => NS_MAIN,
				ApiBase::PARAM_TYPE => 'namespace',
			),
			'filterredir' => array(
				ApiBase::PARAM_DFLT => 'all',
				ApiBase::PARAM_TYPE => array(
					'all',
					'redirects',
					'nonredirects'
				)
			),
			'minsize' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'maxsize' => array(
				ApiBase::PARAM_TYPE => 'integer',
			),
			'prtype' => array(
				ApiBase::PARAM_TYPE => Title::getFilteredRestrictionTypes( true ),
				ApiBase::PARAM_ISMULTI => true
			),
			'prlevel' => array(
				ApiBase::PARAM_TYPE => $wgRestrictionLevels,
				ApiBase::PARAM_ISMULTI => true
			),
			'prfiltercascade' => array(
				ApiBase::PARAM_DFLT => 'all',
				ApiBase::PARAM_TYPE => array(
					'cascading',
					'noncascading',
					'all'
				),
			),
			'limit' => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			),
			'dir' => array(
				ApiBase::PARAM_DFLT => 'ascending',
				ApiBase::PARAM_TYPE => array(
					'ascending',
					'descending'
				)
			),
			'filterlanglinks' => array(
				ApiBase::PARAM_TYPE => array(
					'withlanglinks',
					'withoutlanglinks',
					'all'
				),
				ApiBase::PARAM_DFLT => 'all'
			),
			'prexpiry' => array(
				ApiBase::PARAM_TYPE => array(
					'indefinite',
					'definite',
					'all'
				),
				ApiBase::PARAM_DFLT => 'all'
			),
		);
	}

	public function getParamDescription() {
		$p = $this->getModulePrefix();

		return array(
			'from' => 'The page title to start enumerating from',
			'continue' => 'When more results are available, use this to continue',
			'to' => 'The page title to stop enumerating at',
			'prefix' => 'Search for all page titles that begin with this value',
			'namespace' => 'The namespace to enumerate',
			'filterredir' => 'Which pages to list',
			'dir' => 'The direction in which to list',
			'minsize' => 'Limit to pages with at least this many bytes',
			'maxsize' => 'Limit to pages with at most this many bytes',
			'prtype' => 'Limit to protected pages only',
			'prlevel' => "The protection level (must be used with {$p}prtype= parameter)",
			'prfiltercascade'
				=> "Filter protections based on cascadingness (ignored when {$p}prtype isn't set)",
			'filterlanglinks' => array(
				'Filter based on whether a page has langlinks',
				'Note that this may not consider langlinks added by extensions.',
			),
			'limit' => 'How many total pages to return.',
			'prexpiry' => array(
				'Which protection expiry to filter the page on',
				' indefinite - Get only pages with indefinite protection expiry',
				' definite - Get only pages with a definite (specific) protection expiry',
				' all - Get pages with any protections expiry'
			),
		);
	}

	public function getResultProperties() {
		return array(
			'' => array(
				'pageid' => 'integer',
				'ns' => 'namespace',
				'title' => 'string'
			)
		);
	}

	public function getDescription() {
		return 'Enumerate all pages sequentially in a given namespace.';
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array(
				'code' => 'params',
				'info' => 'Use "gapfilterredir=nonredirects" option instead of ' .
					'"redirects" when using allpages as a generator'
			),
			array( 'code' => 'params', 'info' => 'prlevel may not be used without prtype' ),
		) );
	}

	public function getExamples() {
		return array(
			'api.php?action=query&list=allpages&apfrom=B' => array(
				'Simple Use',
				'Show a list of pages starting at the letter "B"',
			),
			'api.php?action=query&generator=allpages&gaplimit=4&gapfrom=T&prop=info' => array(
				'Using as Generator',
				'Show info about 4 pages starting at the letter "T"',
			),
			'api.php?action=query&generator=allpages&gaplimit=2&' .
				'gapfilterredir=nonredirects&gapfrom=Re&prop=revisions&rvprop=content'
				=> array( 'Show content of first 2 non-redirect pages beginning at "Re"' )
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/API:Allpages';
	}
}
