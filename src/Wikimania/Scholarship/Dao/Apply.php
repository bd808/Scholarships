<?php
/**
 * @section LICENSE
 * This file is part of Wikimania Scholarship Application.
 *
 * Wikimania Scholarship Application is free software: you can redistribute it
 * and/or modify it under the terms of the GNU General Public License as
 * published by the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * Wikimania Scholarship Application is distributed in the hope that it will
 * be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General
 * Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with Wikimania Scholarship Application.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 * @file
 * @copyright © 2013 Bryan Davis and Wikimedia Foundation.
 * @copyright © 2013 Calvin W. F. Siu, Wikimania 2013 Hong Kong organizing team
 * @copyright © 2012-2013 Katie Filbert, Wikimania 2012 Washington DC organizing team
 * @copyright © 2011 Harel Cain, Wikimania 2011 Haifa organizing team
 * @copyright © 2010 Wikimania 2010 Gdansk organizing team
 * @copyright © 2009 Wikimania 2009 Buenos Aires organizing team
 */

namespace Wikimania\Scholarship\Dao;

/**
 * Data access object for scholarship applications.
 */
class Apply extends AbstractDao {

	/**
	 * @var int $userid
	 */
	protected $userid;

	public function __construct( $uid = false ) {
		parent::__construct();
		$this->userid = $uid;
	}

	/**
	 * Save a new application.
	 *
	 * @param array $answers Application data
	 * @return int|bool Application id or false if an exception was generated
	 */
	public function saveApplication( $answers ) {
		$cols = array_keys( $answers );
		$parms = array_map( function ($elm) { return ":{$elm}"; }, $cols );
		$sql = self::concat(
			'INSERT INTO scholarships (',
			implode( ',', $cols ),
			') VALUES (',
			implode( ',', $parms ),
			')'
		);
		return $this->insert( $sql, $answers );
	}

	/**
	 * @param array $params Query parameters
	 */
	public function gridData( $params ) {
		$defaults = array(
			'apps'   => 'unreviewed',
			'items'  => 50,
			'page' => 0,
			'min'    => -2,
			'max'    => 999,
			'phase'  => 1,
		);
		$params = array_merge( $defaults, $params );

		$where = array();
		$myid = $this->userid ?: 0;

		if ( $params['items'] == 'all' ) {
			$limit = " ";
			$offset = " ";

		} else {
			$limit = " LIMIT {$params['items']} ";
			$offset = " OFFSET " . ( (int)$params['page'] * $params['items'] );
		}

		$fields = array(
			's.id',
			's.fname',
			's.lname',
			's.email',
			's.residence',
			's.exclude',
			's.sex',
			'(YEAR(NOW()) - YEAR(s.dob)) as age',
			'(s.canpaydiff * s.wantspartial) as partial',
			'c.country_name',
			'COALESCE(p1score, 0) as p1score',
			'mycount',
		);

		if ( $params['phase'] == 1 ) {
			switch( $params['apps'] ) {
				case 'unreviewed':
					$where[] = 'p1count IS NULL';
					break;
				case 'myapps':
					$where[] = 'mycount IS NULL';
					break;
				default:
					break;
			}

		} else if ( $params['phase'] == 2 ) {
			switch( $params['apps'] ) {
				case 'unreviewed':
					$where[] = 'nscorers IS NULL';
					break;
				case 'myapps':
					$where[] = 'mycount IS NULL';
					break;
				default:
					break;
			}
		}

		$p1scoreSql = self::concat(
			"SELECT scholarship_id, SUM(rank) AS p1score",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"GROUP BY scholarship_id"
		);

		$p1countSql = self::concat(
			"SELECT scholarship_id, COUNT(rank) AS p1count",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"GROUP BY scholarship_id"
		);

		$mycountSql = self::concat(
			"SELECT scholarship_id, COUNT(rank) AS mycount",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"AND user_id = {$myid}",
			"GROUP BY scholarship_id"
		);

		$p2scoreSql = self::concat(
			"SELECT scholarship_id, SUM(rank) AS p2score",
			"FROM rankings",
			"WHERE criterion <> 'valid'",
			"GROUP BY scholarship_id"
		);

		$nscorersSql = self::concat(
			"SELECT scholarship_id, COUNT(DISTINCT user_id) AS nscorers",
			"FROM rankings",
			"WHERE criterion <> 'valid'",
			"GROUP BY scholarship_id"
		);

		$mycount2Sql = self::concat(
			"SELECT scholarship_id, COUNT(rank) AS mycount",
			"FROM rankings",
			"WHERE criterion <> 'valid'",
			"AND user_id = {$myid}",
			"GROUP BY scholarship_id"
		);

		if ( $params['phase'] == 1 ) {
			$fields[] = 'p1count';

			$min = (int)$params['min'];
			$max = (int)$params['max'];

			$sql = self::concat(
				"SELECT SQL_CALC_FOUND_ROWS", implode( ',', $fields ),
				"FROM scholarships s",
				"LEFT OUTER JOIN ( {$p1scoreSql} ) r1 ON s.id = r1.scholarship_id",
				"LEFT OUTER JOIN ( {$p1countSql} ) r2 on s.id = r2.scholarship_id",
				"LEFT OUTER JOIN ( {$mycountSql} ) r3 on s.id = r3.scholarship_id",
				"LEFT OUTER JOIN countries c ON s.residence = c.id",
				$this->buildWhere( $where ),
				"GROUP BY s.id, s.fname, s.lname, s.email, s.residence",
				"HAVING p1score >= {$min} AND p1score <= {$max} AND s.exclude = 0",
				"ORDER BY s.id",
				$limit,
				$offset
			);

		} else {
			$fields[] = 'COALESCE(p2score, 0) as p2score';
			$fields[] = 'COALESCE(nscorers, 0) as nscorers';

			$where[] = 'p1score >= 3';

			$sql = self::concat(
				"SELECT SQL_CALC_FOUND_ROWS", implode( ',', $fields ),
				"FROM scholarships s",
				"LEFT OUTER JOIN ( {$p1scoreSql} ) r1 ON s.id = r1.scholarship_id",
				"LEFT OUTER JOIN ( {$p2scoreSql} ) r2 ON s.id = r2.scholarship_id",
				"LEFT OUTER JOIN ( {$nscorersSql} ) r3 ON s.id = r3.scholarship_id",
				"LEFT OUTER JOIN ( {$mycount2Sql} ) r4 ON s.id = r4.scholarship_id",
				"LEFT OUTER JOIN countries c ON s.residence = c.id",
				$this->buildWhere( $where ),
				"GROUP BY s.id, s.fname, s.lname, s.email, s.residence",
				"ORDER BY s.id",
				$limit,
				$offset
			);
		}

		return $this->fetchAllWithFound( $sql );
	} // end gridData

	public function export() {
		$tables = array( 's' => 'scholarships' );
		$fields = array( 's.id',
			'c.country_name', // residence
			'ow.onwiki',
			'ofw.offwiki',
			'f.future',
			'ct.numranks',
			's.fname',
			's.lname',
			's.dob',
			's.sex',
			's.email',
			's.telephone',
			's.address',
			'c2.country_name', // nationality
			's.haspassport',
			's.airport',
			's.languages',
			's.occupation',
			's.areaofstudy',
			's.wm05',
			's.wm06',
			's.wm07',
			's.wm08',
			's.wm09',
			's.wm10',
			's.wm11',
			's.wm12',
			's.howheard',
			's.why',
			's.future',
			's.involvement',
			's.contribution',
			'ea.englishAbility',
			's.username',
			's.project',
			's.projectlangs',
			's.wantspartial',
			's.canpaydiff',
			's.sincere',
			's.agreestotravelconditions',
			's.willgetvisa',
			's.willpayincidentals',
			's.notes'
		);
		$sql = self::concat(
			"SELECT " . implode( ',', $fields ),
			"FROM scholarships s",
			"LEFT JOIN (select scholarship_id, avg(rank) as onwiki from rankings where criterion IN ('onwiki') group by scholarship_id) ow ON (ow.scholarship_id = s.id)
			LEFT JOIN (select scholarship_id, avg(rank) as offwiki from rankings where criterion IN ('offwiki') group by scholarship_id) ofw ON (ofw.scholarship_id = s.id)
			LEFT JOIN (select scholarship_id, avg(rank) as future from rankings where criterion IN ('future') group by scholarship_id) f ON (f.scholarship_id = s.id)
			LEFT JOIN (select scholarship_id, avg(rank) as englishAbility from rankings where criterion IN ('englishAbility') group by scholarship_id) ea ON (ea.scholarship_id = s.id)
			LEFT JOIN (select scholarship_id, count(rank) as numranks from rankings where criterion IN ('future') group by scholarship_id) ct ON (ct.scholarship_id  = s.id)
			LEFT OUTER JOIN countries c ON s.residence = c.id
			LEFT OUTER JOIN countries c2 ON s.nationality = c2.id
			order by s.id limit 20");
		return $this->fetchAll( $sql );
	}

	public function myUnreviewed( $phase ) {
		// FIXME: NOT IN () instead of left join? (count not used)
		// FIXME: this isn't right, doesn't care about phase
		$sql = "SELECT s.id FROM scholarships s
			LEFT OUTER JOIN (select scholarship_id, count(rank) as mycount from rankings WHERE user_id = ? GROUP BY scholarship_id) r4 on s.id = r4.scholarship_id
			WHERE mycount IS NULL;";

		$res = $this->fetchAll( $sql, array( $this->userid ) );
		return array_map( function ($row) { return $row['id']; }, $res );
	}

	public function search( $params ) {
		$defaults = array(
			'first' => null,
			'last' => null,
			'residence' => null,
			'region' => null,
			'items'  => 50,
			'page' => 0,
		);
		$params = array_merge( $defaults, $params );
		$myid = $this->userid ?: 0;

		$offset = "OFFSET " . ( (int)$params['page'] * $params['items'] );
		$limit = "LIMIT {$params['items']}";

		$where = array();
		$crit = array( ':uid' => $myid );
		if ( $params['last'] !== null ) {
			$where[] = "s.lname = :last";
			$crit[':last'] = $params['last'];
		}
		if ( $params['first'] !== null ) {
			$where[] = "s.fname = :first";
			$crit[':first'] = $params['first'];
		}
		if ( $params['residence'] !== null ) {
			$where[] = "c.country_name = :residence";
			$crit[':residence'] = $params['residence'];
		}
		if ( $params['region'] !== null ) {
			$where[] = "c.region = :region";
			$crit[':region'] = $params['region'];
		}

		$fields = array(
			"s.id",
			"s.fname",
			"s.lname",
			"s.email",
			"s.residence",
			"s.exclude",
			"s.sex",
			"(year(now()) - year(s.dob)) as age",
			"(s.canpaydiff*s.wantspartial) as partial",
			"c.country_name",
			"c.region",
			"coalesce(p1score, 0) as p1score",
			"p1count",
			"mycount",
		);

		$p1scoreSql = self::concat(
			"SELECT scholarship_id, SUM(rank) AS p1score",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"GROUP BY scholarship_id"
		);

		$p1countSql = self::concat(
			"SELECT scholarship_id, COUNT(rank) AS p1count",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"GROUP BY scholarship_id"
		);

		$mycountSql = self::concat(
			"SELECT scholarship_id, COUNT(rank) AS mycount",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"AND user_id = :uid",
			"GROUP BY scholarship_id"
		);

			$sql = self::concat(
				"SELECT SQL_CALC_FOUND_ROWS", implode( ',', $fields ),
				"FROM scholarships s",
				"LEFT OUTER JOIN ( {$p1scoreSql} ) r1 ON s.id = r1.scholarship_id",
				"LEFT OUTER JOIN ( {$p1countSql} ) r2 ON s.id = r2.scholarship_id",
				"LEFT OUTER JOIN ( {$mycountSql} ) r3 ON s.id = r3.scholarship_id",
				"LEFT OUTER JOIN countries c ON s.residence = c.id",
				$this->buildWhere( $where ),
				"GROUP BY s.id, s.fname, s.lname, s.email, s.residence",
				"HAVING p1score >= -2 AND p1score <= 999 AND s.exclude = 0",
				"ORDER BY s.id",
				$limit,
				$offset
			);

		return $this->fetchAllWithFound( $sql, $crit );
	}


	public function getScholarship( $id ) {
		return $this->fetch( 'select *, s.id, s.residence as acountry, c.country_name, r.country_name as residence_name from scholarships s
			left outer join countries c on s.nationality = c.id
			left outer join countries r on s.residence = r.id
			where s.id = ?', array( $id ) );
	}

	public function getNext( $userid, $id, $phase ) {
		$nextid = $this->getNextId( $userid, $id, $phase );
		if ( $nextid != false ) {
			return $this->getScholarship( $i );
		}
		return false;
	}

	/**
	 * Find the next unreviewed id after the given id.
	 * @return int|bool Next id or false if none available
	 */
	public function nextApp( $id, $phase ) {
		// FIXME: this can probably be done in sql
		$myapps = $this->myUnreviewed( $this->userid, $phase );
		foreach ( $myapps as $app ) {
			if ( $app > $id ) {
				return $app;
			}
		}
		return false;
	}

	public function prevApp( $id, $phase ) {
		// FIXME: this can probably be done in sql
		$myapps = $this->myUnreviewed( $this->userid, $phase );
		$prior = false;
		foreach ( $myapps as $app ) {
			if ( $app >= $id ) {
				return $prior;
			}
			$prior = $id;
		}
		return false;
	}

	public function getNextId( $userid, $id, $phase ) {
		$myapps = $this->myUnreviewed( $userid, $phase );
		for ( $i = $id; $i < max( $myapps ); $i++ ) {
			if ( in_array( $i, $myapps ) ) {
				return $i;
			}
		}
		return false;
	}

	public function insertOrUpdateRanking( $scholarship_id, $criterion, $rank ) {
		$this->update( self::concat(
			'INSERT INTO rankings (user_id, scholarship_id, criterion, rank)',
			'VALUES (?, ?, ?, ?)',
			'ON DUPLICATE KEY UPDATE rank = ?, entered_on = now()' ),
			array( $this->userid, $scholarship_id, $criterion, $rank, $rank )
		);
	}

	public function getReviewers( $id, $phase ) {
		$where = array( "r.scholarship_id = ?" );
		if ( $phase == 1 ) {
			array_push( $where, "r.criterion IN ('valid')" );
		} else {
			array_push( $where, "r.criterion IN ('future', 'onwiki', 'offwiki', 'englishAbility')" );
		}
		$sql = "select distinct(u.username) as username from rankings r inner join users u on r.user_id = u.id " . $this->buildWhere( $where ) . " order by u.username";
		return $this->fetchAll( $sql, array( $id ) );
	}

	public function myRankings( $id, $phase ) {
		if ( $phase == 1 ) {
			$sql = "select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion = 'valid' and u.id = ? AND r.scholarship_id = ?";

		} else if ( $phase == 2 ) {
			$sql = "select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion IN ('onwiki', 'future', 'offwiki', 'program', 'englishAbility') and u.id = ? and r.scholarship_id = ? order by r.criterion, u.username, r.rank";
		} else {
			return false;
		}
		return $this->fetchAll( $sql, array( $this->userid, $id ) );
	}

	public function allRankings( $id, $phase ) {
		if ( $phase == 1 ) {
			$sql = 'select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion = "valid" and r.scholarship_id = ?';
		} else if ( $phase == 2 ) {
			$sql = "select r.scholarship_id, u.username, r.rank, r.criterion from rankings r inner join users u on r.user_id = u.id where r.criterion IN ('onwiki', 'future', 'offwiki', 'program', 'englishAbility') and r.scholarship_id = ? order by r.criterion, u.username, r.rank";
		} else {
			return false;
		}
		return $this->fetchAll( $sql, array( $id ) );
	}

	public function getRankingOfUser( $user_id, $scholarship_id, $criterion ) {
		$sql = 'select rank from rankings where user_id = ? and scholarship_id = ? and criterion = ?';
		$ret = $this->fetch( $sql, array( $user_id, $scholarship_id, $criterion ) );
		return ( count( $ret ) > 0 ) ? $ret['rank'] : 0;
	}

	public function updateNotes( $id, $notes ) {
		$this->update(
			'update scholarships set notes = ? where id = ?',
			array( $notes, $id )
		);
	}

	// Phase List

	public function GetPhase1EarlyRejects() {
		return$this->fetchAll( "select s.id, s.fname, s.lname, s.email, s.exclude, coalesce(p1score,0) as p1score from scholarships s
			left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id
			group by s.id, s.fname, s.lname, s.email
			having p1score < 3 and s.exclude = 0" );
	}

	public function GetPhase1Success() {
		// FIXME: hoist and reuse
		$p1scoreSql = self::concat(
			"SELECT scholarship_id, SUM(rank) AS p1score",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"GROUP BY scholarship_id"
		);

		$fields = array(
			's.id',
			's.fname',
			's.lname',
			's.email',
			's.exclude',
			'COALESCE(p1score, 0) as p1score',
		);

		return $this->fetchAll( self::concat(
			"SELECT" , implode( ',', $fields ),
			"FROM scholarships s",
			"LEFT OUTER JOIN ( {$p1scoreSql} ) r2 ON s.id = r2.scholarship_id",
			"GROUP BY s.id, s.fname, s.lname, s.email",
			"HAVING p1score >= 3 AND s.exclude = 0" ) );
	}

	public function getRegionList() {
		$res = $this->fetchAll( "SELECT DISTINCT region FROM countries" );
		return array_map( function ($row) { return $row['region']; }, $res );
	}

	public function getP2List( $partial, $region ) {
		//FIXME make this prettier
		$sql = "SELECT s.id, s.fname, s.lname, s.email, s.residence, s.exclude, s.sex, year(now())-year(s.dob) as age, (s.canpaydiff*s.wantspartial) as partial, c.country_name, coalesce(p1score,0) as p1score, coalesce(nscorers,0) as nscorers, r.onwiki as onwiki, r2.offwiki as offwiki, r3.future as future, r6.englishAbility as englishAbility, 0.5*r.onwiki + 0.15*r2.offwiki + 0.25*r3.future + 0.1*r6.englishAbility as p2score from scholarships s
			left outer join (select scholarship_id, avg(rank) as onwiki from rankings where criterion = 'onwiki' group by scholarship_id) r on s.id = r.scholarship_id
			left outer join (select scholarship_id, avg(rank) as offwiki from rankings where criterion = 'offwiki' group by scholarship_id) r2 on s.id = r2.scholarship_id
			left outer join (select scholarship_id, avg(rank) as future from rankings where criterion = 'future' group by scholarship_id) r3 on s.id = r3.scholarship_id
			left outer join (select scholarship_id, avg(rank) as englishAbility from rankings where criterion = 'englishAbility' group by scholarship_id) r6 on s.id = r6.scholarship_id
			left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r4 on s.id = r4.scholarship_id
			left outer join (select scholarship_id, count(distinct user_id) as nscorers from rankings where criterion in ('onwiki','offwiki', 'future', 'englishAbility') group by scholarship_id) r5 on s.id = r5.scholarship_id
			left outer join countries c on s.residence = c.id ";

		$params = array();

		if ( $region != 'All' ) {
			$params[] = $region;
			$sql .= 'inner join countries c1 on c.region = ? ';
		}

		if ( $partial == 2 ) {
			$sql .= "group by s.id, s.fname, s.lname, s.email, s.residence
				having p1score >= 3 and s.exclude = 0 order by p2score desc";

		} else {
			$params[] = $partial;
			$sql .= 'group by s.id, s.fname, s.lname, s.email, s.residence
				having p1score >= 3 and s.exclude = 0 and partial = ? order by p2score desc';
		}

		return $this->fetchAll( $sql, $params );
	}

	// Final scoring

	public function getFinalScoring( $partial ) {
		$fields = array(
			"s.id",
			"s.fname",
			"s.lname",
			"s.email",
			"s.residence",
			"s.exclude",
			"s.sex",
			"YEAR(NOW())-YEAR(s.dob) AS age",
			"(s.canpaydiff * s.wantspartial) AS partial",
			"c.country_name",
			"coalesce(p1score, 0) AS p1score",
			"coalesce(nscorers, 0) AS nscorers",
			"r.onwiki AS onwiki",
			"r2.offwiki AS offwiki",
			"r3.future AS future",
			"r6.englishAbility AS englishAbility",
			"0.5*r.onwiki + 0.15*r2.offwiki + 0.25*r3.future + 0.1*r6.englishAbility as p2score",
		);

		$sqlRankOnwiki = self::concat(
			"SELECT scholarship_id, AVG(rank) AS onwiki",
			"FROM rankings",
			"WHERE criterion = 'onwiki'",
			"GROUP BY scholarship_id"
		);

		$sqlRankOffwiki = self::concat(
			"SELECT scholarship_id, AVG(rank) AS offwiki",
			"FROM rankings",
			"WHERE criterion = 'offwiki'",
			"GROUP BY scholarship_id"
		);

		$sqlRankFuture = self::concat(
			"SELECT scholarship_id, AVG(rank) AS future",
			"FROM rankings",
			"WHERE criterion = 'future'",
			"GROUP BY scholarship_id"
		);

		$sqlRankEnglish = self::concat(
			"SELECT scholarship_id, AVG(rank) AS englishAbility",
			"FROM rankings",
			"WHERE criterion = 'englishAbility'",
			"GROUP BY scholarship_id"
		);

		$sqlRankP1 = self::concat(
			"SELECT scholarship_id, AVG(rank) AS p1score",
			"FROM rankings",
			"WHERE criterion = 'valid'",
			"GROUP BY scholarship_id"
		);

		$sqlNScores = self::concat(
			"SELECT scholarship_id, COUNT(DISTINCT user_id) AS nscorers",
			"FROM rankings",
			"WHERE criterion IN ('onwiki','offwiki','future','englishAbility')",
			"GROUP BY scholarship_id"
		);

		$sql = self::concat(
			"SELECT " . implode( ',', $fields),
			"FROM scholarships s",
			"LEFT OUTER JOIN ( {$sqlRankOnwiki} ) r ON s.id = r.scholarship_id",
			"LEFT OUTER JOIN ( {$sqlRankOffwiki} ) r2 on s.id = r2.scholarship_id",
			"LEFT OUTER JOIN ( {$sqlRankFuture} ) r3 on s.id = r3.scholarship_id",
			"LEFT OUTER JOIN ( {$sqlRankEnglish} ) r6 on s.id = r6.scholarship_id",
			"LEFT OUTER JOIN ( {$sqlRankP1} ) r4 on s.id = r4.scholarship_id",
			"LEFT OUTER JOIN ( {$sqlNScores} ) r5 on s.id = r5.scholarship_id",
			"LEFT OUTER JOIN countries c ON s.residence = c.id",
			"GROUP BY s.id, s.fname, s.lname, s.email, s.residence",
			"HAVING p1score >= 3 AND s.exclude = 0 AND partial = ?",
			"ORDER BY p2score desc"
		);

		return $this->fetchAll( $sql, array( $partial ) );
	}

	// Country administration

	public function getListOfCountries( $order = "country_name" ) {
		$fields = array(
			"c.id",
			"c.country_name",
			"c.region",
			"s.sid",
		);
		$sqlCountByResidence = self::concat(
			"SELECT COUNT(id) AS sid, residence AS attendees",
			"FROM scholarships",
			"WHERE rank = 1",
			"AND exclude = 0",
			"GROUP BY residence"
		);
		$sql = self::concat(
			"SELECT " . implode( ',', $fields ),
			"FROM countries c",
			"LEFT JOIN ( {$sqlCountByResidence} ) s ON c.id = s.attendees",
			"ORDER BY ?"
		);
		return $this->fetchAll( $sql, array( $order ) );
	}

	public function getListOfRegions() {
		return $this->fetchAll( self::concat(
			"SELECT count(*) as count, c.region",
			"FROM scholarships s",
			"LEFT JOIN countries c ON c.id = s.residence",
			"GROUP BY region"
		) );
	}

	public function GetPhase1EarlyRejectsTemp() {
		$res = $this->fetchAll( "select s.id, s.fname, s.lname, s.email, s.exclude, coalesce(p1score,0) as p1score from scholarships s
			left outer join (select scholarship_id, sum(rank) as p1score from rankings where criterion = 'valid' group by scholarship_id) r2 on s.id = r2.scholarship_id
			group by s.id, s.fname, s.lname, s.email
			having p1score < 3 and s.exclude = 0 and s.id>305" );
	}
}
