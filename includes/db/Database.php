<?php
/**
 * @defgroup Database Database
 *
 * This file deals with database interface functions
 * and query specifics/optimisations.
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
 * @ingroup Database
 */

/**
 * Base interface for all DBMS-specific code. At a bare minimum, all of the
 * following must be implemented to support MediaWiki
 *
 * @file
 * @ingroup Database
 */
interface DatabaseType {
	/**
	 * Get the type of the DBMS, as it appears in $wgDBtype.
	 *
	 * @return string
	 */
	function getType();

	/**
	 * Open a connection to the database. Usually aborts on failure
	 *
	 * @param string $server database server host
	 * @param string $user database user name
	 * @param string $password database user password
	 * @param string $dbName database name
	 * @return bool
	 * @throws DBConnectionError
	 */
	function open( $server, $user, $password, $dbName );

	/**
	 * Fetch the next row from the given result object, in object form.
	 * Fields can be retrieved with $row->fieldname, with fields acting like
	 * member variables.
	 * If no more rows are available, false is returned.
	 *
	 * @param $res ResultWrapper|object as returned from DatabaseBase::query(), etc.
	 * @return object|bool
	 * @throws DBUnexpectedError Thrown if the database returns an error
	 */
	function fetchObject( $res );

	/**
	 * Fetch the next row from the given result object, in associative array
	 * form.  Fields are retrieved with $row['fieldname'].
	 * If no more rows are available, false is returned.
	 *
	 * @param $res ResultWrapper result object as returned from DatabaseBase::query(), etc.
	 * @return array|bool
	 * @throws DBUnexpectedError Thrown if the database returns an error
	 */
	function fetchRow( $res );

	/**
	 * Get the number of rows in a result object
	 *
	 * @param $res Mixed: A SQL result
	 * @return int
	 */
	function numRows( $res );

	/**
	 * Get the number of fields in a result object
	 * @see http://www.php.net/mysql_num_fields
	 *
	 * @param $res Mixed: A SQL result
	 * @return int
	 */
	function numFields( $res );

	/**
	 * Get a field name in a result object
	 * @see http://www.php.net/mysql_field_name
	 *
	 * @param $res Mixed: A SQL result
	 * @param $n Integer
	 * @return string
	 */
	function fieldName( $res, $n );

	/**
	 * Get the inserted value of an auto-increment row
	 *
	 * The value inserted should be fetched from nextSequenceValue()
	 *
	 * Example:
	 * $id = $dbw->nextSequenceValue( 'page_page_id_seq' );
	 * $dbw->insert( 'page', array( 'page_id' => $id ) );
	 * $id = $dbw->insertId();
	 *
	 * @return int
	 */
	function insertId();

	/**
	 * Change the position of the cursor in a result object
	 * @see http://www.php.net/mysql_data_seek
	 *
	 * @param $res Mixed: A SQL result
	 * @param $row Mixed: Either MySQL row or ResultWrapper
	 */
	function dataSeek( $res, $row );

	/**
	 * Get the last error number
	 * @see http://www.php.net/mysql_errno
	 *
	 * @return int
	 */
	function lastErrno();

	/**
	 * Get a description of the last error
	 * @see http://www.php.net/mysql_error
	 *
	 * @return string
	 */
	function lastError();

	/**
	 * mysql_fetch_field() wrapper
	 * Returns false if the field doesn't exist
	 *
	 * @param string $table table name
	 * @param string $field field name
	 *
	 * @return Field
	 */
	function fieldInfo( $table, $field );

	/**
	 * Get information about an index into an object
	 * @param string $table Table name
	 * @param string $index Index name
	 * @param string $fname Calling function name
	 * @return Mixed: Database-specific index description class or false if the index does not exist
	 */
	function indexInfo( $table, $index, $fname = __METHOD__ );

	/**
	 * Get the number of rows affected by the last write query
	 * @see http://www.php.net/mysql_affected_rows
	 *
	 * @return int
	 */
	function affectedRows();

	/**
	 * Wrapper for addslashes()
	 *
	 * @param string $s to be slashed.
	 * @return string: slashed string.
	 */
	function strencode( $s );

	/**
	 * Returns a wikitext link to the DB's website, e.g.,
	 *     return "[http://www.mysql.com/ MySQL]";
	 * Should at least contain plain text, if for some reason
	 * your database has no website.
	 *
	 * @return string: wikitext of a link to the server software's web site
	 */
	function getSoftwareLink();

	/**
	 * A string describing the current software version, like from
	 * mysql_get_server_info().
	 *
	 * @return string: Version information from the database server.
	 */
	function getServerVersion();

	/**
	 * A string describing the current software version, and possibly
	 * other details in a user-friendly way.  Will be listed on Special:Version, etc.
	 * Use getServerVersion() to get machine-friendly information.
	 *
	 * @return string: Version information from the database server
	 */
	function getServerInfo();
}

/**
 * Interface for classes that implement or wrap DatabaseBase
 * @ingroup Database
 */
interface IDatabase {}

/**
 * Database abstraction object
 * @ingroup Database
 */
abstract class DatabaseBase implements IDatabase, DatabaseType {
	/** Number of times to re-try an operation in case of deadlock */
	const DEADLOCK_TRIES = 4;
	/** Minimum time to wait before retry, in microseconds */
	const DEADLOCK_DELAY_MIN = 500000;
	/** Maximum time to wait before retry */
	const DEADLOCK_DELAY_MAX = 1500000;

# ------------------------------------------------------------------------------
# Variables
# ------------------------------------------------------------------------------

	protected $mLastQuery = '';
	protected $mDoneWrites = false;
	protected $mPHPError = false;

	protected $mServer, $mUser, $mPassword, $mDBname;

	protected $mConn = null;
	protected $mOpened = false;

	/** @var callable[] */
	protected $mTrxIdleCallbacks = array();
	/** @var callable[] */
	protected $mTrxPreCommitCallbacks = array();

	protected $mTablePrefix;
	protected $mFlags;
	protected $mForeign;
	protected $mTrxLevel = 0;
	protected $mErrorCount = 0;
	protected $mLBInfo = array();
	protected $mFakeSlaveLag = null, $mFakeMaster = false;
	protected $mDefaultBigSelects = null;
	protected $mSchemaVars = false;

	protected $preparedArgs;

	protected $htmlErrors;

	protected $delimiter = ';';

	/**
	 * Remembers the function name given for starting the most recent transaction via begin().
	 * Used to provide additional context for error reporting.
	 *
	 * @var String
	 * @see DatabaseBase::mTrxLevel
	 */
	private $mTrxFname = null;

	/**
	 * Record if possible write queries were done in the last transaction started
	 *
	 * @var Bool
	 * @see DatabaseBase::mTrxLevel
	 */
	private $mTrxDoneWrites = false;

	/**
	 * Record if the current transaction was started implicitly due to DBO_TRX being set.
	 *
	 * @var Bool
	 * @see DatabaseBase::mTrxLevel
	 */
	private $mTrxAutomatic = false;

	/**
	 * @since 1.21
	 * @var file handle for upgrade
	 */
	protected $fileHandle = null;

# ------------------------------------------------------------------------------
# Accessors
# ------------------------------------------------------------------------------
	# These optionally set a variable and return the previous state

	/**
	 * A string describing the current software version, and possibly
	 * other details in a user-friendly way.  Will be listed on Special:Version, etc.
	 * Use getServerVersion() to get machine-friendly information.
	 *
	 * @return string: Version information from the database server
	 */
	public function getServerInfo() {
		return $this->getServerVersion();
	}

	/**
	 * @return string: command delimiter used by this database engine
	 */
	public function getDelimiter() {
		return $this->delimiter;
	}

	/**
	 * Boolean, controls output of large amounts of debug information.
	 * @param $debug bool|null
	 *   - true to enable debugging
	 *   - false to disable debugging
	 *   - omitted or null to do nothing
	 *
	 * @return bool|null previous value of the flag
	 */
	public function debug( $debug = null ) {
		return wfSetBit( $this->mFlags, DBO_DEBUG, $debug );
	}

	/**
	 * Turns buffering of SQL result sets on (true) or off (false). Default is
	 * "on".
	 *
	 * Unbuffered queries are very troublesome in MySQL:
	 *
	 *   - If another query is executed while the first query is being read
	 *     out, the first query is killed. This means you can't call normal
	 *     MediaWiki functions while you are reading an unbuffered query result
	 *     from a normal wfGetDB() connection.
	 *
	 *   - Unbuffered queries cause the MySQL server to use large amounts of
	 *     memory and to hold broad locks which block other queries.
	 *
	 * If you want to limit client-side memory, it's almost always better to
	 * split up queries into batches using a LIMIT clause than to switch off
	 * buffering.
	 *
	 * @param $buffer null|bool
	 *
	 * @return null|bool The previous value of the flag
	 */
	public function bufferResults( $buffer = null ) {
		if ( is_null( $buffer ) ) {
			return !(bool)( $this->mFlags & DBO_NOBUFFER );
		} else {
			return !wfSetBit( $this->mFlags, DBO_NOBUFFER, !$buffer );
		}
	}

	/**
	 * Turns on (false) or off (true) the automatic generation and sending
	 * of a "we're sorry, but there has been a database error" page on
	 * database errors. Default is on (false). When turned off, the
	 * code should use lastErrno() and lastError() to handle the
	 * situation as appropriate.
	 *
	 * @param $ignoreErrors bool|null
	 *
	 * @return bool The previous value of the flag.
	 */
	public function ignoreErrors( $ignoreErrors = null ) {
		return wfSetBit( $this->mFlags, DBO_IGNORE, $ignoreErrors );
	}

	/**
	 * Gets or sets the current transaction level.
	 *
	 * Historically, transactions were allowed to be "nested". This is no
	 * longer supported, so this function really only returns a boolean.
	 *
	 * @param int $level An integer (0 or 1), or omitted to leave it unchanged.
	 * @return int The previous value
	 */
	public function trxLevel( $level = null ) {
		return wfSetVar( $this->mTrxLevel, $level );
	}

	/**
	 * Get/set the number of errors logged. Only useful when errors are ignored
	 * @param int $count The count to set, or omitted to leave it unchanged.
	 * @return int The error count
	 */
	public function errorCount( $count = null ) {
		return wfSetVar( $this->mErrorCount, $count );
	}

	/**
	 * Get/set the table prefix.
	 * @param string $prefix The table prefix to set, or omitted to leave it unchanged.
	 * @return string The previous table prefix.
	 */
	public function tablePrefix( $prefix = null ) {
		return wfSetVar( $this->mTablePrefix, $prefix );
	}

	/**
	 * Set the filehandle to copy write statements to.
	 *
	 * @param $fh filehandle
	 */
	public function setFileHandle( $fh ) {
		$this->fileHandle = $fh;
	}

	/**
	 * Get properties passed down from the server info array of the load
	 * balancer.
	 *
	 * @param string $name The entry of the info array to get, or null to get the
	 *   whole array
	 *
	 * @return LoadBalancer|null
	 */
	public function getLBInfo( $name = null ) {
		if ( is_null( $name ) ) {
			return $this->mLBInfo;
		} else {
			if ( array_key_exists( $name, $this->mLBInfo ) ) {
				return $this->mLBInfo[$name];
			} else {
				return null;
			}
		}
	}

	/**
	 * Set the LB info array, or a member of it. If called with one parameter,
	 * the LB info array is set to that parameter. If it is called with two
	 * parameters, the member with the given name is set to the given value.
	 *
	 * @param $name
	 * @param $value
	 */
	public function setLBInfo( $name, $value = null ) {
		if ( is_null( $value ) ) {
			$this->mLBInfo = $name;
		} else {
			$this->mLBInfo[$name] = $value;
		}
	}

	/**
	 * Set lag time in seconds for a fake slave
	 *
	 * @param $lag int
	 */
	public function setFakeSlaveLag( $lag ) {
		$this->mFakeSlaveLag = $lag;
	}

	/**
	 * Make this connection a fake master
	 *
	 * @param $enabled bool
	 */
	public function setFakeMaster( $enabled = true ) {
		$this->mFakeMaster = $enabled;
	}

	/**
	 * Returns true if this database supports (and uses) cascading deletes
	 *
	 * @return bool
	 */
	public function cascadingDeletes() {
		return false;
	}

	/**
	 * Returns true if this database supports (and uses) triggers (e.g. on the page table)
	 *
	 * @return bool
	 */
	public function cleanupTriggers() {
		return false;
	}

	/**
	 * Returns true if this database is strict about what can be put into an IP field.
	 * Specifically, it uses a NULL value instead of an empty string.
	 *
	 * @return bool
	 */
	public function strictIPs() {
		return false;
	}

	/**
	 * Returns true if this database uses timestamps rather than integers
	 *
	 * @return bool
	 */
	public function realTimestamps() {
		return false;
	}

	/**
	 * Returns true if this database does an implicit sort when doing GROUP BY
	 *
	 * @return bool
	 */
	public function implicitGroupby() {
		return true;
	}

	/**
	 * Returns true if this database does an implicit order by when the column has an index
	 * For example: SELECT page_title FROM page LIMIT 1
	 *
	 * @return bool
	 */
	public function implicitOrderby() {
		return true;
	}

	/**
	 * Returns true if this database can do a native search on IP columns
	 * e.g. this works as expected: .. WHERE rc_ip = '127.42.12.102/32';
	 *
	 * @return bool
	 */
	public function searchableIPs() {
		return false;
	}

	/**
	 * Returns true if this database can use functional indexes
	 *
	 * @return bool
	 */
	public function functionalIndexes() {
		return false;
	}

	/**
	 * Return the last query that went through DatabaseBase::query()
	 * @return String
	 */
	public function lastQuery() {
		return $this->mLastQuery;
	}

	/**
	 * Returns true if the connection may have been used for write queries.
	 * Should return true if unsure.
	 *
	 * @return bool
	 */
	public function doneWrites() {
		return $this->mDoneWrites;
	}

	/**
	 * Returns true if there is a transaction open with possible write
	 * queries or transaction pre-commit/idle callbacks waiting on it to finish.
	 *
	 * @return bool
	 */
	public function writesOrCallbacksPending() {
		return $this->mTrxLevel && (
			$this->mTrxDoneWrites || $this->mTrxIdleCallbacks || $this->mTrxPreCommitCallbacks
		);
	}

	/**
	 * Is a connection to the database open?
	 * @return Boolean
	 */
	public function isOpen() {
		return $this->mOpened;
	}

	/**
	 * Set a flag for this connection
	 *
	 * @param $flag Integer: DBO_* constants from Defines.php:
	 *   - DBO_DEBUG: output some debug info (same as debug())
	 *   - DBO_NOBUFFER: don't buffer results (inverse of bufferResults())
	 *   - DBO_IGNORE: ignore errors (same as ignoreErrors())
	 *   - DBO_TRX: automatically start transactions
	 *   - DBO_DEFAULT: automatically sets DBO_TRX if not in command line mode
	 *       and removes it in command line mode
	 *   - DBO_PERSISTENT: use persistant database connection
	 */
	public function setFlag( $flag ) {
		global $wgDebugDBTransactions;
		$this->mFlags |= $flag;
		if ( ( $flag & DBO_TRX ) & $wgDebugDBTransactions ) {
			wfDebug( "Implicit transactions are now  disabled.\n" );
		}
	}

	/**
	 * Clear a flag for this connection
	 *
	 * @param $flag: same as setFlag()'s $flag param
	 */
	public function clearFlag( $flag ) {
		global $wgDebugDBTransactions;
		$this->mFlags &= ~$flag;
		if ( ( $flag & DBO_TRX ) && $wgDebugDBTransactions ) {
			wfDebug( "Implicit transactions are now disabled.\n" );
		}
	}

	/**
	 * Returns a boolean whether the flag $flag is set for this connection
	 *
	 * @param $flag: same as setFlag()'s $flag param
	 * @return Boolean
	 */
	public function getFlag( $flag ) {
		return !!( $this->mFlags & $flag );
	}

	/**
	 * General read-only accessor
	 *
	 * @param $name string
	 *
	 * @return string
	 */
	public function getProperty( $name ) {
		return $this->$name;
	}

	/**
	 * @return string
	 */
	public function getWikiID() {
		if ( $this->mTablePrefix ) {
			return "{$this->mDBname}-{$this->mTablePrefix}";
		} else {
			return $this->mDBname;
		}
	}

	/**
	 * Return a path to the DBMS-specific schema file, otherwise default to tables.sql
	 *
	 * @return string
	 */
	public function getSchemaPath() {
		global $IP;
		if ( file_exists( "$IP/maintenance/" . $this->getType() . "/tables.sql" ) ) {
			return "$IP/maintenance/" . $this->getType() . "/tables.sql";
		} else {
			return "$IP/maintenance/tables.sql";
		}
	}

# ------------------------------------------------------------------------------
# Other functions
# ------------------------------------------------------------------------------

	/**
	 * Constructor.
	 * @param string $server database server host
	 * @param string $user database user name
	 * @param string $password database user password
	 * @param string $dbName database name
	 * @param $flags
	 * @param string $tablePrefix database table prefixes. By default use the prefix gave in LocalSettings.php
	 * @param bool $foreign disable some operations specific to local databases
	 */
	function __construct( $server = false, $user = false, $password = false, $dbName = false,
		$flags = 0, $tablePrefix = 'get from global', $foreign = false
	) {
		global $wgDBprefix, $wgCommandLineMode, $wgDebugDBTransactions;

		$this->mFlags = $flags;

		if ( $this->mFlags & DBO_DEFAULT ) {
			if ( $wgCommandLineMode ) {
				$this->mFlags &= ~DBO_TRX;
				if ( $wgDebugDBTransactions ) {
					wfDebug( "Implicit transaction open disabled.\n" );
				}
			} else {
				$this->mFlags |= DBO_TRX;
				if ( $wgDebugDBTransactions ) {
					wfDebug( "Implicit transaction open enabled.\n" );
				}
			}
		}

		/** Get the default table prefix*/
		if ( $tablePrefix == 'get from global' ) {
			$this->mTablePrefix = $wgDBprefix;
		} else {
			$this->mTablePrefix = $tablePrefix;
		}

		$this->mForeign = $foreign;

		if ( $user ) {
			$this->open( $server, $user, $password, $dbName );
		}
	}

	/**
	 * Called by serialize. Throw an exception when DB connection is serialized.
	 * This causes problems on some database engines because the connection is
	 * not restored on unserialize.
	 */
	public function __sleep() {
		throw new MWException( 'Database serialization may cause problems, since the connection is not restored on wakeup.' );
	}

	/**
	 * Given a DB type, construct the name of the appropriate child class of
	 * DatabaseBase. This is designed to replace all of the manual stuff like:
	 *	$class = 'Database' . ucfirst( strtolower( $type ) );
	 * as well as validate against the canonical list of DB types we have
	 *
	 * This factory function is mostly useful for when you need to connect to a
	 * database other than the MediaWiki default (such as for external auth,
	 * an extension, et cetera). Do not use this to connect to the MediaWiki
	 * database. Example uses in core:
	 * @see LoadBalancer::reallyOpenConnection()
	 * @see ForeignDBRepo::getMasterDB()
	 * @see WebInstaller_DBConnect::execute()
	 *
	 * @since 1.18
	 *
	 * @param string $dbType A possible DB type
	 * @param array $p An array of options to pass to the constructor.
	 *    Valid options are: host, user, password, dbname, flags, tablePrefix
	 * @return DatabaseBase subclass or null
	 */
	final public static function factory( $dbType, $p = array() ) {
		$canonicalDBTypes = array(
			'mysql', 'postgres', 'sqlite', 'oracle', 'mssql'
		);
		$dbType = strtolower( $dbType );
		$class = 'Database' . ucfirst( $dbType );

		if ( in_array( $dbType, $canonicalDBTypes ) || ( class_exists( $class ) && is_subclass_of( $class, 'DatabaseBase' ) ) ) {
			return new $class(
				isset( $p['host'] ) ? $p['host'] : false,
				isset( $p['user'] ) ? $p['user'] : false,
				isset( $p['password'] ) ? $p['password'] : false,
				isset( $p['dbname'] ) ? $p['dbname'] : false,
				isset( $p['flags'] ) ? $p['flags'] : 0,
				isset( $p['tablePrefix'] ) ? $p['tablePrefix'] : 'get from global',
				isset( $p['foreign'] ) ? $p['foreign'] : false
			);
		} else {
			return null;
		}
	}

	protected function installErrorHandler() {
		$this->mPHPError = false;
		$this->htmlErrors = ini_set( 'html_errors', '0' );
		set_error_handler( array( $this, 'connectionErrorHandler' ) );
	}

	/**
	 * @return bool|string
	 */
	protected function restoreErrorHandler() {
		restore_error_handler();
		if ( $this->htmlErrors !== false ) {
			ini_set( 'html_errors', $this->htmlErrors );
		}
		if ( $this->mPHPError ) {
			$error = preg_replace( '!\[<a.*</a>\]!', '', $this->mPHPError );
			$error = preg_replace( '!^.*?:\s?(.*)$!', '$1', $error );
			return $error;
		} else {
			return false;
		}
	}

	/**
	 * @param $errno
	 * @param $errstr
	 * @access private
	 */
	public function connectionErrorHandler( $errno, $errstr ) {
		$this->mPHPError = $errstr;
	}

	/**
	 * Closes a database connection.
	 * if it is open : commits any open transactions
	 *
	 * @throws MWException
	 * @return Bool operation success. true if already closed.
	 */
	public function close() {
		if ( count( $this->mTrxIdleCallbacks ) ) { // sanity
			throw new MWException( "Transaction idle callbacks still pending." );
		}
		$this->mOpened = false;
		if ( $this->mConn ) {
			if ( $this->trxLevel() ) {
				if ( !$this->mTrxAutomatic ) {
					wfWarn( "Transaction still in progress (from {$this->mTrxFname}), " .
						" performing implicit commit before closing connection!" );
				}

				$this->commit( __METHOD__, 'flush' );
			}

			$ret = $this->closeConnection();
			$this->mConn = false;
			return $ret;
		} else {
			return true;
		}
	}

	/**
	 * Closes underlying database connection
	 * @since 1.20
	 * @return bool: Whether connection was closed successfully
	 */
	abstract protected function closeConnection();

	/**
	 * @param string $error fallback error message, used if none is given by DB
	 * @throws DBConnectionError
	 */
	function reportConnectionError( $error = 'Unknown error' ) {
		$myError = $this->lastError();
		if ( $myError ) {
			$error = $myError;
		}

		# New method
		throw new DBConnectionError( $this, $error );
	}

	/**
	 * The DBMS-dependent part of query()
	 *
	 * @param  $sql String: SQL query.
	 * @return ResultWrapper Result object to feed to fetchObject, fetchRow, ...; or false on failure
	 */
	abstract protected function doQuery( $sql );

	/**
	 * Determine whether a query writes to the DB.
	 * Should return true if unsure.
	 *
	 * @param $sql string
	 *
	 * @return bool
	 */
	public function isWriteQuery( $sql ) {
		return !preg_match( '/^(?:SELECT|BEGIN|ROLLBACK|COMMIT|SET|SHOW|EXPLAIN|\(SELECT)\b/i', $sql );
	}

	/**
	 * Run an SQL query and return the result. Normally throws a DBQueryError
	 * on failure. If errors are ignored, returns false instead.
	 *
	 * In new code, the query wrappers select(), insert(), update(), delete(),
	 * etc. should be used where possible, since they give much better DBMS
	 * independence and automatically quote or validate user input in a variety
	 * of contexts. This function is generally only useful for queries which are
	 * explicitly DBMS-dependent and are unsupported by the query wrappers, such
	 * as CREATE TABLE.
	 *
	 * However, the query wrappers themselves should call this function.
	 *
	 * @param  $sql        String: SQL query
	 * @param  $fname      String: Name of the calling function, for profiling/SHOW PROCESSLIST
	 *     comment (you can use __METHOD__ or add some extra info)
	 * @param  $tempIgnore Boolean:   Whether to avoid throwing an exception on errors...
	 *     maybe best to catch the exception instead?
	 * @throws MWException
	 * @return boolean|ResultWrapper. true for a successful write query, ResultWrapper object
	 *     for a successful read query, or false on failure if $tempIgnore set
	 */
	public function query( $sql, $fname = __METHOD__, $tempIgnore = false ) {
		$isMaster = !is_null( $this->getLBInfo( 'master' ) );
		if ( !Profiler::instance()->isStub() ) {
			# generalizeSQL will probably cut down the query to reasonable
			# logging size most of the time. The substr is really just a sanity check.

			if ( $isMaster ) {
				$queryProf = 'query-m: ' . substr( DatabaseBase::generalizeSQL( $sql ), 0, 255 );
				$totalProf = 'DatabaseBase::query-master';
			} else {
				$queryProf = 'query: ' . substr( DatabaseBase::generalizeSQL( $sql ), 0, 255 );
				$totalProf = 'DatabaseBase::query';
			}

			wfProfileIn( $totalProf );
			wfProfileIn( $queryProf );
		}

		$this->mLastQuery = $sql;
		if ( !$this->mDoneWrites && $this->isWriteQuery( $sql ) ) {
			# Set a flag indicating that writes have been done
			wfDebug( __METHOD__ . ": Writes done: $sql\n" );
			$this->mDoneWrites = true;
		}

		# Add a comment for easy SHOW PROCESSLIST interpretation
		global $wgUser;
		if ( is_object( $wgUser ) && $wgUser->isItemLoaded( 'name' ) ) {
			$userName = $wgUser->getName();
			if ( mb_strlen( $userName ) > 15 ) {
				$userName = mb_substr( $userName, 0, 15 ) . '...';
			}
			$userName = str_replace( '/', '', $userName );
		} else {
			$userName = '';
		}

		// Add trace comment to the begin of the sql string, right after the operator.
		// Or, for one-word queries (like "BEGIN" or COMMIT") add it to the end (bug 42598)
		$commentedSql = preg_replace( '/\s|$/', " /* $fname $userName */ ", $sql, 1 );

		# If DBO_TRX is set, start a transaction
		if ( ( $this->mFlags & DBO_TRX ) && !$this->mTrxLevel &&
			$sql != 'BEGIN' && $sql != 'COMMIT' && $sql != 'ROLLBACK' )
		{
			# Avoid establishing transactions for SHOW and SET statements too -
			# that would delay transaction initializations to once connection
			# is really used by application
			$sqlstart = substr( $sql, 0, 10 ); // very much worth it, benchmark certified(tm)
			if ( strpos( $sqlstart, "SHOW " ) !== 0 && strpos( $sqlstart, "SET " ) !== 0 ) {
				global $wgDebugDBTransactions;
				if ( $wgDebugDBTransactions ) {
					wfDebug( "Implicit transaction start.\n" );
				}
				$this->begin( __METHOD__ . " ($fname)" );
				$this->mTrxAutomatic = true;
			}
		}

		# Keep track of whether the transaction has write queries pending
		if ( $this->mTrxLevel && !$this->mTrxDoneWrites && $this->isWriteQuery( $sql ) ) {
			$this->mTrxDoneWrites = true;
			Profiler::instance()->transactionWritingIn( $this->mServer, $this->mDBname );
		}

		if ( $this->debug() ) {
			static $cnt = 0;

			$cnt++;
			$sqlx = substr( $commentedSql, 0, 500 );
			$sqlx = strtr( $sqlx, "\t\n", '  ' );

			$master = $isMaster ? 'master' : 'slave';
			wfDebug( "Query {$this->mDBname} ($cnt) ($master): $sqlx\n" );
		}

		$queryId = MWDebug::query( $sql, $fname, $isMaster );

		# Do the query and handle errors
		$ret = $this->doQuery( $commentedSql );

		MWDebug::queryTime( $queryId );

		# Try reconnecting if the connection was lost
		if ( false === $ret && $this->wasErrorReissuable() ) {
			# Transaction is gone, like it or not
			$this->mTrxLevel = 0;
			$this->mTrxIdleCallbacks = array(); // cancel
			$this->mTrxPreCommitCallbacks = array(); // cancel
			wfDebug( "Connection lost, reconnecting...\n" );

			if ( $this->ping() ) {
				wfDebug( "Reconnected\n" );
				$sqlx = substr( $commentedSql, 0, 500 );
				$sqlx = strtr( $sqlx, "\t\n", '  ' );
				global $wgRequestTime;
				$elapsed = round( microtime( true ) - $wgRequestTime, 3 );
				if ( $elapsed < 300 ) {
					# Not a database error to lose a transaction after a minute or two
					wfLogDBError( "Connection lost and reconnected after {$elapsed}s, query: $sqlx\n" );
				}
				$ret = $this->doQuery( $commentedSql );
			} else {
				wfDebug( "Failed\n" );
			}
		}

		if ( false === $ret ) {
			$this->reportQueryError( $this->lastError(), $this->lastErrno(), $sql, $fname, $tempIgnore );
		}

		if ( !Profiler::instance()->isStub() ) {
			wfProfileOut( $queryProf );
			wfProfileOut( $totalProf );
		}

		return $this->resultObject( $ret );
	}

	/**
	 * Report a query error. Log the error, and if neither the object ignore
	 * flag nor the $tempIgnore flag is set, throw a DBQueryError.
	 *
	 * @param $error String
	 * @param $errno Integer
	 * @param $sql String
	 * @param $fname String
	 * @param $tempIgnore Boolean
	 * @throws DBQueryError
	 */
	public function reportQueryError( $error, $errno, $sql, $fname, $tempIgnore = false ) {
		# Ignore errors during error handling to avoid infinite recursion
		$ignore = $this->ignoreErrors( true );
		++$this->mErrorCount;

		if ( $ignore || $tempIgnore ) {
			wfDebug( "SQL ERROR (ignored): $error\n" );
			$this->ignoreErrors( $ignore );
		} else {
			$sql1line = str_replace( "\n", "\\n", $sql );
			wfLogDBError( "$fname\t{$this->mServer}\t$errno\t$error\t$sql1line\n" );
			wfDebug( "SQL ERROR: " . $error . "\n" );
			throw new DBQueryError( $this, $error, $errno, $sql, $fname );
		}
	}

	/**
	 * Intended to be compatible with the PEAR::DB wrapper functions.
	 * http://pear.php.net/manual/en/package.database.db.intro-execute.php
	 *
	 * ? = scalar value, quoted as necessary
	 * ! = raw SQL bit (a function for instance)
	 * & = filename; reads the file and inserts as a blob
	 *     (we don't use this though...)
	 *
	 * @param $sql string
	 * @param $func string
	 *
	 * @return array
	 */
	protected function prepare( $sql, $func = 'DatabaseBase::prepare' ) {
		/* MySQL doesn't support prepared statements (yet), so just
		   pack up the query for reference. We'll manually replace
		   the bits later. */
		return array( 'query' => $sql, 'func' => $func );
	}

	/**
	 * Free a prepared query, generated by prepare().
	 * @param $prepared
	 */
	protected function freePrepared( $prepared ) {
		/* No-op by default */
	}

	/**
	 * Execute a prepared query with the various arguments
	 * @param string $prepared the prepared sql
	 * @param $args Mixed: Either an array here, or put scalars as varargs
	 *
	 * @return ResultWrapper
	 */
	public function execute( $prepared, $args = null ) {
		if ( !is_array( $args ) ) {
			# Pull the var args
			$args = func_get_args();
			array_shift( $args );
		}

		$sql = $this->fillPrepared( $prepared['query'], $args );

		return $this->query( $sql, $prepared['func'] );
	}

	/**
	 * For faking prepared SQL statements on DBs that don't support it directly.
	 *
	 * @param string $preparedQuery a 'preparable' SQL statement
	 * @param array $args of arguments to fill it with
	 * @return string executable SQL
	 */
	public function fillPrepared( $preparedQuery, $args ) {
		reset( $args );
		$this->preparedArgs =& $args;

		return preg_replace_callback( '/(\\\\[?!&]|[?!&])/',
			array( &$this, 'fillPreparedArg' ), $preparedQuery );
	}

	/**
	 * preg_callback func for fillPrepared()
	 * The arguments should be in $this->preparedArgs and must not be touched
	 * while we're doing this.
	 *
	 * @param $matches Array
	 * @throws DBUnexpectedError
	 * @return String
	 */
	protected function fillPreparedArg( $matches ) {
		switch ( $matches[1] ) {
			case '\\?':
				return '?';
			case '\\!':
				return '!';
			case '\\&':
				return '&';
		}

		list( /* $n */, $arg ) = each( $this->preparedArgs );

		switch ( $matches[1] ) {
			case '?':
				return $this->addQuotes( $arg );
			case '!':
				return $arg;
			case '&':
				# return $this->addQuotes( file_get_contents( $arg ) );
				throw new DBUnexpectedError( $this, '& mode is not implemented. If it\'s really needed, uncomment the line above.' );
			default:
				throw new DBUnexpectedError( $this, 'Received invalid match. This should never happen!' );
		}
	}

	/**
	 * Free a result object returned by query() or select(). It's usually not
	 * necessary to call this, just use unset() or let the variable holding
	 * the result object go out of scope.
	 *
	 * @param $res Mixed: A SQL result
	 */
	public function freeResult( $res ) {
	}

	/**
	 * A SELECT wrapper which returns a single field from a single result row.
	 *
	 * Usually throws a DBQueryError on failure. If errors are explicitly
	 * ignored, returns false on failure.
	 *
	 * If no result rows are returned from the query, false is returned.
	 *
	 * @param string|array $table Table name. See DatabaseBase::select() for details.
	 * @param string $var The field name to select. This must be a valid SQL
	 *   fragment: do not use unvalidated user input.
	 * @param string|array $cond The condition array. See DatabaseBase::select() for details.
	 * @param string $fname The function name of the caller.
	 * @param string|array $options The query options. See DatabaseBase::select() for details.
	 *
	 * @return bool|mixed The value from the field, or false on failure.
	 */
	public function selectField( $table, $var, $cond = '', $fname = __METHOD__,
		$options = array()
	) {
		if ( !is_array( $options ) ) {
			$options = array( $options );
		}

		$options['LIMIT'] = 1;

		$res = $this->select( $table, $var, $cond, $fname, $options );

		if ( $res === false || !$this->numRows( $res ) ) {
			return false;
		}

		$row = $this->fetchRow( $res );

		if ( $row !== false ) {
			return reset( $row );
		} else {
			return false;
		}
	}

	/**
	 * Returns an optional USE INDEX clause to go after the table, and a
	 * string to go at the end of the query.
	 *
	 * @param array $options associative array of options to be turned into
	 *              an SQL query, valid keys are listed in the function.
	 * @return Array
	 * @see DatabaseBase::select()
	 */
	public function makeSelectOptions( $options ) {
		$preLimitTail = $postLimitTail = '';
		$startOpts = '';

		$noKeyOptions = array();

		foreach ( $options as $key => $option ) {
			if ( is_numeric( $key ) ) {
				$noKeyOptions[$option] = true;
			}
		}

		$preLimitTail .= $this->makeGroupByWithHaving( $options );

		$preLimitTail .= $this->makeOrderBy( $options );

		// if (isset($options['LIMIT'])) {
		//	$tailOpts .= $this->limitResult('', $options['LIMIT'],
		//		isset($options['OFFSET']) ? $options['OFFSET']
		//		: false);
		// }

		if ( isset( $noKeyOptions['FOR UPDATE'] ) ) {
			$postLimitTail .= ' FOR UPDATE';
		}

		if ( isset( $noKeyOptions['LOCK IN SHARE MODE'] ) ) {
			$postLimitTail .= ' LOCK IN SHARE MODE';
		}

		if ( isset( $noKeyOptions['DISTINCT'] ) || isset( $noKeyOptions['DISTINCTROW'] ) ) {
			$startOpts .= 'DISTINCT';
		}

		# Various MySQL extensions
		if ( isset( $noKeyOptions['STRAIGHT_JOIN'] ) ) {
			$startOpts .= ' /*! STRAIGHT_JOIN */';
		}

		if ( isset( $noKeyOptions['HIGH_PRIORITY'] ) ) {
			$startOpts .= ' HIGH_PRIORITY';
		}

		if ( isset( $noKeyOptions['SQL_BIG_RESULT'] ) ) {
			$startOpts .= ' SQL_BIG_RESULT';
		}

		if ( isset( $noKeyOptions['SQL_BUFFER_RESULT'] ) ) {
			$startOpts .= ' SQL_BUFFER_RESULT';
		}

		if ( isset( $noKeyOptions['SQL_SMALL_RESULT'] ) ) {
			$startOpts .= ' SQL_SMALL_RESULT';
		}

		if ( isset( $noKeyOptions['SQL_CALC_FOUND_ROWS'] ) ) {
			$startOpts .= ' SQL_CALC_FOUND_ROWS';
		}

		if ( isset( $noKeyOptions['SQL_CACHE'] ) ) {
			$startOpts .= ' SQL_CACHE';
		}

		if ( isset( $noKeyOptions['SQL_NO_CACHE'] ) ) {
			$startOpts .= ' SQL_NO_CACHE';
		}

		if ( isset( $options['USE INDEX'] ) && is_string( $options['USE INDEX'] ) ) {
			$useIndex = $this->useIndexClause( $options['USE INDEX'] );
		} else {
			$useIndex = '';
		}

		return array( $startOpts, $useIndex, $preLimitTail, $postLimitTail );
	}

	/**
	 * Returns an optional GROUP BY with an optional HAVING
	 *
	 * @param array $options associative array of options
	 * @return string
	 * @see DatabaseBase::select()
	 * @since 1.21
	 */
	public function makeGroupByWithHaving( $options ) {
		$sql = '';
		if ( isset( $options['GROUP BY'] ) ) {
			$gb = is_array( $options['GROUP BY'] )
				? implode( ',', $options['GROUP BY'] )
				: $options['GROUP BY'];
			$sql .= ' GROUP BY ' . $gb;
		}
		if ( isset( $options['HAVING'] ) ) {
			$having = is_array( $options['HAVING'] )
				? $this->makeList( $options['HAVING'], LIST_AND )
				: $options['HAVING'];
			$sql .= ' HAVING ' . $having;
		}
		return $sql;
	}

	/**
	 * Returns an optional ORDER BY
	 *
	 * @param array $options associative array of options
	 * @return string
	 * @see DatabaseBase::select()
	 * @since 1.21
	 */
	public function makeOrderBy( $options ) {
		if ( isset( $options['ORDER BY'] ) ) {
			$ob = is_array( $options['ORDER BY'] )
				? implode( ',', $options['ORDER BY'] )
				: $options['ORDER BY'];
			return ' ORDER BY ' . $ob;
		}
		return '';
	}

	/**
	 * Execute a SELECT query constructed using the various parameters provided.
	 * See below for full details of the parameters.
	 *
	 * @param string|array $table Table name
	 * @param string|array $vars Field names
	 * @param string|array $conds Conditions
	 * @param string $fname Caller function name
	 * @param array $options Query options
	 * @param $join_conds Array Join conditions
	 *
	 * @param $table string|array
	 *
	 * May be either an array of table names, or a single string holding a table
	 * name. If an array is given, table aliases can be specified, for example:
	 *
	 *    array( 'a' => 'user' )
	 *
	 * This includes the user table in the query, with the alias "a" available
	 * for use in field names (e.g. a.user_name).
	 *
	 * All of the table names given here are automatically run through
	 * DatabaseBase::tableName(), which causes the table prefix (if any) to be
	 * added, and various other table name mappings to be performed.
	 *
	 *
	 * @param $vars string|array
	 *
	 * May be either a field name or an array of field names. The field names
	 * can be complete fragments of SQL, for direct inclusion into the SELECT
	 * query. If an array is given, field aliases can be specified, for example:
	 *
	 *   array( 'maxrev' => 'MAX(rev_id)' )
	 *
	 * This includes an expression with the alias "maxrev" in the query.
	 *
	 * If an expression is given, care must be taken to ensure that it is
	 * DBMS-independent.
	 *
	 *
	 * @param $conds string|array
	 *
	 * May be either a string containing a single condition, or an array of
	 * conditions. If an array is given, the conditions constructed from each
	 * element are combined with AND.
	 *
	 * Array elements may take one of two forms:
	 *
	 *   - Elements with a numeric key are interpreted as raw SQL fragments.
	 *   - Elements with a string key are interpreted as equality conditions,
	 *     where the key is the field name.
	 *     - If the value of such an array element is a scalar (such as a
	 *       string), it will be treated as data and thus quoted appropriately.
	 *       If it is null, an IS NULL clause will be added.
	 *     - If the value is an array, an IN(...) clause will be constructed,
	 *       such that the field name may match any of the elements in the
	 *       array. The elements of the array will be quoted.
	 *
	 * Note that expressions are often DBMS-dependent in their syntax.
	 * DBMS-independent wrappers are provided for constructing several types of
	 * expression commonly used in condition queries. See:
	 *    - DatabaseBase::buildLike()
	 *    - DatabaseBase::conditional()
	 *
	 *
	 * @param $options string|array
	 *
	 * Optional: Array of query options. Boolean options are specified by
	 * including them in the array as a string value with a numeric key, for
	 * example:
	 *
	 *    array( 'FOR UPDATE' )
	 *
	 * The supported options are:
	 *
	 *   - OFFSET: Skip this many rows at the start of the result set. OFFSET
	 *     with LIMIT can theoretically be used for paging through a result set,
	 *     but this is discouraged in MediaWiki for performance reasons.
	 *
	 *   - LIMIT: Integer: return at most this many rows. The rows are sorted
	 *     and then the first rows are taken until the limit is reached. LIMIT
	 *     is applied to a result set after OFFSET.
	 *
	 *   - FOR UPDATE: Boolean: lock the returned rows so that they can't be
	 *     changed until the next COMMIT.
	 *
	 *   - DISTINCT: Boolean: return only unique result rows.
	 *
	 *   - GROUP BY: May be either an SQL fragment string naming a field or
	 *     expression to group by, or an array of such SQL fragments.
	 *
	 *   - HAVING: May be either an string containing a HAVING clause or an array of
	 *     conditions building the HAVING clause. If an array is given, the conditions
	 *     constructed from each element are combined with AND.
	 *
	 *   - ORDER BY: May be either an SQL fragment giving a field name or
	 *     expression to order by, or an array of such SQL fragments.
	 *
	 *   - USE INDEX: This may be either a string giving the index name to use
	 *     for the query, or an array. If it is an associative array, each key
	 *     gives the table name (or alias), each value gives the index name to
	 *     use for that table. All strings are SQL fragments and so should be
	 *     validated by the caller.
	 *
	 *   - EXPLAIN: In MySQL, this causes an EXPLAIN SELECT query to be run,
	 *     instead of SELECT.
	 *
	 * And also the following boolean MySQL extensions, see the MySQL manual
	 * for documentation:
	 *
	 *    - LOCK IN SHARE MODE
	 *    - STRAIGHT_JOIN
	 *    - HIGH_PRIORITY
	 *    - SQL_BIG_RESULT
	 *    - SQL_BUFFER_RESULT
	 *    - SQL_SMALL_RESULT
	 *    - SQL_CALC_FOUND_ROWS
	 *    - SQL_CACHE
	 *    - SQL_NO_CACHE
	 *
	 *
	 * @param $join_conds string|array
	 *
	 * Optional associative array of table-specific join conditions. In the
	 * most common case, this is unnecessary, since the join condition can be
	 * in $conds. However, it is useful for doing a LEFT JOIN.
	 *
	 * The key of the array contains the table name or alias. The value is an
	 * array with two elements, numbered 0 and 1. The first gives the type of
	 * join, the second is an SQL fragment giving the join condition for that
	 * table. For example:
	 *
	 *    array( 'page' => array( 'LEFT JOIN', 'page_latest=rev_id' ) )
	 *
	 * @return ResultWrapper. If the query returned no rows, a ResultWrapper
	 *   with no rows in it will be returned. If there was a query error, a
	 *   DBQueryError exception will be thrown, except if the "ignore errors"
	 *   option was set, in which case false will be returned.
	 */
	public function select( $table, $vars, $conds = '', $fname = __METHOD__,
		$options = array(), $join_conds = array() ) {
		$sql = $this->selectSQLText( $table, $vars, $conds, $fname, $options, $join_conds );

		return $this->query( $sql, $fname );
	}

	/**
	 * The equivalent of DatabaseBase::select() except that the constructed SQL
	 * is returned, instead of being immediately executed. This can be useful for
	 * doing UNION queries, where the SQL text of each query is needed. In general,
	 * however, callers outside of Database classes should just use select().
	 *
	 * @param string|array $table Table name
	 * @param string|array $vars Field names
	 * @param string|array $conds Conditions
	 * @param string $fname Caller function name
	 * @param string|array $options Query options
	 * @param $join_conds string|array Join conditions
	 *
	 * @return string SQL query string.
	 * @see DatabaseBase::select()
	 */
	public function selectSQLText( $table, $vars, $conds = '', $fname = __METHOD__,
		$options = array(), $join_conds = array() )
	{
		if ( is_array( $vars ) ) {
			$vars = implode( ',', $this->fieldNamesWithAlias( $vars ) );
		}

		$options = (array)$options;
		$useIndexes = ( isset( $options['USE INDEX'] ) && is_array( $options['USE INDEX'] ) )
			? $options['USE INDEX']
			: array();

		if ( is_array( $table ) ) {
			$from = ' FROM ' .
				$this->tableNamesWithUseIndexOrJOIN( $table, $useIndexes, $join_conds );
		} elseif ( $table != '' ) {
			if ( $table[0] == ' ' ) {
				$from = ' FROM ' . $table;
			} else {
				$from = ' FROM ' .
					$this->tableNamesWithUseIndexOrJOIN( array( $table ), $useIndexes, array() );
			}
		} else {
			$from = '';
		}

		list( $startOpts, $useIndex, $preLimitTail, $postLimitTail ) =
			$this->makeSelectOptions( $options );

		if ( !empty( $conds ) ) {
			if ( is_array( $conds ) ) {
				$conds = $this->makeList( $conds, LIST_AND );
			}
			$sql = "SELECT $startOpts $vars $from $useIndex WHERE $conds $preLimitTail";
		} else {
			$sql = "SELECT $startOpts $vars $from $useIndex $preLimitTail";
		}

		if ( isset( $options['LIMIT'] ) ) {
			$sql = $this->limitResult( $sql, $options['LIMIT'],
				isset( $options['OFFSET'] ) ? $options['OFFSET'] : false );
		}
		$sql = "$sql $postLimitTail";

		if ( isset( $options['EXPLAIN'] ) ) {
			$sql = 'EXPLAIN ' . $sql;
		}

		return $sql;
	}

	/**
	 * Single row SELECT wrapper. Equivalent to DatabaseBase::select(), except
	 * that a single row object is returned. If the query returns no rows,
	 * false is returned.
	 *
	 * @param string|array $table Table name
	 * @param string|array $vars Field names
	 * @param array $conds Conditions
	 * @param string $fname Caller function name
	 * @param string|array $options Query options
	 * @param $join_conds array|string Join conditions
	 *
	 * @return object|bool
	 */
	public function selectRow( $table, $vars, $conds, $fname = __METHOD__,
		$options = array(), $join_conds = array() )
	{
		$options = (array)$options;
		$options['LIMIT'] = 1;
		$res = $this->select( $table, $vars, $conds, $fname, $options, $join_conds );

		if ( $res === false ) {
			return false;
		}

		if ( !$this->numRows( $res ) ) {
			return false;
		}

		$obj = $this->fetchObject( $res );

		return $obj;
	}

	/**
	 * Estimate rows in dataset.
	 *
	 * MySQL allows you to estimate the number of rows that would be returned
	 * by a SELECT query, using EXPLAIN SELECT. The estimate is provided using
	 * index cardinality statistics, and is notoriously inaccurate, especially
	 * when large numbers of rows have recently been added or deleted.
	 *
	 * For DBMSs that don't support fast result size estimation, this function
	 * will actually perform the SELECT COUNT(*).
	 *
	 * Takes the same arguments as DatabaseBase::select().
	 *
	 * @param string $table table name
	 * @param array|string $vars : unused
	 * @param array|string $conds : filters on the table
	 * @param string $fname function name for profiling
	 * @param array $options options for select
	 * @return Integer: row count
	 */
	public function estimateRowCount( $table, $vars = '*', $conds = '',
		$fname = __METHOD__, $options = array() )
	{
		$rows = 0;
		$res = $this->select( $table, array( 'rowcount' => 'COUNT(*)' ), $conds, $fname, $options );

		if ( $res ) {
			$row = $this->fetchRow( $res );
			$rows = ( isset( $row['rowcount'] ) ) ? $row['rowcount'] : 0;
		}

		return $rows;
	}

	/**
	 * Removes most variables from an SQL query and replaces them with X or N for numbers.
	 * It's only slightly flawed. Don't use for anything important.
	 *
	 * @param string $sql A SQL Query
	 *
	 * @return string
	 */
	static function generalizeSQL( $sql ) {
		# This does the same as the regexp below would do, but in such a way
		# as to avoid crashing php on some large strings.
		# $sql = preg_replace( "/'([^\\\\']|\\\\.)*'|\"([^\\\\\"]|\\\\.)*\"/", "'X'", $sql );

		$sql = str_replace( "\\\\", '', $sql );
		$sql = str_replace( "\\'", '', $sql );
		$sql = str_replace( "\\\"", '', $sql );
		$sql = preg_replace( "/'.*'/s", "'X'", $sql );
		$sql = preg_replace( '/".*"/s', "'X'", $sql );

		# All newlines, tabs, etc replaced by single space
		$sql = preg_replace( '/\s+/', ' ', $sql );

		# All numbers => N
		$sql = preg_replace( '/-?[0-9]+/s', 'N', $sql );

		return $sql;
	}

	/**
	 * Determines whether a field exists in a table
	 *
	 * @param string $table table name
	 * @param string $field filed to check on that table
	 * @param string $fname calling function name (optional)
	 * @return Boolean: whether $table has filed $field
	 */
	public function fieldExists( $table, $field, $fname = __METHOD__ ) {
		$info = $this->fieldInfo( $table, $field );

		return (bool)$info;
	}

	/**
	 * Determines whether an index exists
	 * Usually throws a DBQueryError on failure
	 * If errors are explicitly ignored, returns NULL on failure
	 *
	 * @param $table
	 * @param $index
	 * @param $fname string
	 *
	 * @return bool|null
	 */
	public function indexExists( $table, $index, $fname = __METHOD__ ) {
		if ( !$this->tableExists( $table ) ) {
			return null;
		}

		$info = $this->indexInfo( $table, $index, $fname );
		if ( is_null( $info ) ) {
			return null;
		} else {
			return $info !== false;
		}
	}

	/**
	 * Query whether a given table exists
	 *
	 * @param $table string
	 * @param $fname string
	 *
	 * @return bool
	 */
	public function tableExists( $table, $fname = __METHOD__ ) {
		$table = $this->tableName( $table );
		$old = $this->ignoreErrors( true );
		$res = $this->query( "SELECT 1 FROM $table LIMIT 1", $fname );
		$this->ignoreErrors( $old );

		return (bool)$res;
	}

	/**
	 * mysql_field_type() wrapper
	 * @param $res
	 * @param $index
	 * @return string
	 */
	public function fieldType( $res, $index ) {
		if ( $res instanceof ResultWrapper ) {
			$res = $res->result;
		}

		return mysql_field_type( $res, $index );
	}

	/**
	 * Determines if a given index is unique
	 *
	 * @param $table string
	 * @param $index string
	 *
	 * @return bool
	 */
	public function indexUnique( $table, $index ) {
		$indexInfo = $this->indexInfo( $table, $index );

		if ( !$indexInfo ) {
			return null;
		}

		return !$indexInfo[0]->Non_unique;
	}

	/**
	 * Helper for DatabaseBase::insert().
	 *
	 * @param $options array
	 * @return string
	 */
	protected function makeInsertOptions( $options ) {
		return implode( ' ', $options );
	}

	/**
	 * INSERT wrapper, inserts an array into a table.
	 *
	 * $a may be either:
	 *
	 *   - A single associative array. The array keys are the field names, and
	 *     the values are the values to insert. The values are treated as data
	 *     and will be quoted appropriately. If NULL is inserted, this will be
	 *     converted to a database NULL.
	 *   - An array with numeric keys, holding a list of associative arrays.
	 *     This causes a multi-row INSERT on DBMSs that support it. The keys in
	 *     each subarray must be identical to each other, and in the same order.
	 *
	 * Usually throws a DBQueryError on failure. If errors are explicitly ignored,
	 * returns success.
	 *
	 * $options is an array of options, with boolean options encoded as values
	 * with numeric keys, in the same style as $options in
	 * DatabaseBase::select(). Supported options are:
	 *
	 *   - IGNORE: Boolean: if present, duplicate key errors are ignored, and
	 *     any rows which cause duplicate key errors are not inserted. It's
	 *     possible to determine how many rows were successfully inserted using
	 *     DatabaseBase::affectedRows().
	 *
	 * @param $table   String Table name. This will be passed through
	 *                 DatabaseBase::tableName().
	 * @param $a       Array of rows to insert
	 * @param $fname   String Calling function name (use __METHOD__) for logs/profiling
	 * @param array $options of options
	 *
	 * @return bool
	 */
	public function insert( $table, $a, $fname = __METHOD__, $options = array() ) {
		# No rows to insert, easy just return now
		if ( !count( $a ) ) {
			return true;
		}

		$table = $this->tableName( $table );

		if ( !is_array( $options ) ) {
			$options = array( $options );
		}

		$fh = null;
		if ( isset( $options['fileHandle'] ) ) {
			$fh = $options['fileHandle'];
		}
		$options = $this->makeInsertOptions( $options );

		if ( isset( $a[0] ) && is_array( $a[0] ) ) {
			$multi = true;
			$keys = array_keys( $a[0] );
		} else {
			$multi = false;
			$keys = array_keys( $a );
		}

		$sql = 'INSERT ' . $options .
			" INTO $table (" . implode( ',', $keys ) . ') VALUES ';

		if ( $multi ) {
			$first = true;
			foreach ( $a as $row ) {
				if ( $first ) {
					$first = false;
				} else {
					$sql .= ',';
				}
				$sql .= '(' . $this->makeList( $row ) . ')';
			}
		} else {
			$sql .= '(' . $this->makeList( $a ) . ')';
		}

		if ( $fh !== null && false === fwrite( $fh, $sql ) ) {
			return false;
		} elseif ( $fh !== null ) {
			return true;
		}

		return (bool)$this->query( $sql, $fname );
	}

	/**
	 * Make UPDATE options for the DatabaseBase::update function
	 *
	 * @param array $options The options passed to DatabaseBase::update
	 * @return string
	 */
	protected function makeUpdateOptions( $options ) {
		if ( !is_array( $options ) ) {
			$options = array( $options );
		}

		$opts = array();

		if ( in_array( 'LOW_PRIORITY', $options ) ) {
			$opts[] = $this->lowPriorityOption();
		}

		if ( in_array( 'IGNORE', $options ) ) {
			$opts[] = 'IGNORE';
		}

		return implode( ' ', $opts );
	}

	/**
	 * UPDATE wrapper. Takes a condition array and a SET array.
	 *
	 * @param $table  String name of the table to UPDATE. This will be passed through
	 *                DatabaseBase::tableName().
	 *
	 * @param array $values  An array of values to SET. For each array element,
	 *                the key gives the field name, and the value gives the data
	 *                to set that field to. The data will be quoted by
	 *                DatabaseBase::addQuotes().
	 *
	 * @param $conds  Array:  An array of conditions (WHERE). See
	 *                DatabaseBase::select() for the details of the format of
	 *                condition arrays. Use '*' to update all rows.
	 *
	 * @param $fname  String: The function name of the caller (from __METHOD__),
	 *                for logging and profiling.
	 *
	 * @param array $options An array of UPDATE options, can be:
	 *                   - IGNORE: Ignore unique key conflicts
	 *                   - LOW_PRIORITY: MySQL-specific, see MySQL manual.
	 * @return Boolean
	 */
	function update( $table, $values, $conds, $fname = __METHOD__, $options = array() ) {
		$table = $this->tableName( $table );
		$opts = $this->makeUpdateOptions( $options );
		$sql = "UPDATE $opts $table SET " . $this->makeList( $values, LIST_SET );

		if ( $conds !== array() && $conds !== '*' ) {
			$sql .= " WHERE " . $this->makeList( $conds, LIST_AND );
		}

		return $this->query( $sql, $fname );
	}

	/**
	 * Makes an encoded list of strings from an array
	 * @param array $a containing the data
	 * @param int $mode Constant
	 *      - LIST_COMMA:          comma separated, no field names
	 *      - LIST_AND:            ANDed WHERE clause (without the WHERE). See
	 *        the documentation for $conds in DatabaseBase::select().
	 *      - LIST_OR:             ORed WHERE clause (without the WHERE)
	 *      - LIST_SET:            comma separated with field names, like a SET clause
	 *      - LIST_NAMES:          comma separated field names
	 *
	 * @throws MWException|DBUnexpectedError
	 * @return string
	 */
	public function makeList( $a, $mode = LIST_COMMA ) {
		if ( !is_array( $a ) ) {
			throw new DBUnexpectedError( $this, 'DatabaseBase::makeList called with incorrect parameters' );
		}

		$first = true;
		$list = '';

		foreach ( $a as $field => $value ) {
			if ( !$first ) {
				if ( $mode == LIST_AND ) {
					$list .= ' AND ';
				} elseif ( $mode == LIST_OR ) {
					$list .= ' OR ';
				} else {
					$list .= ',';
				}
			} else {
				$first = false;
			}

			if ( ( $mode == LIST_AND || $mode == LIST_OR ) && is_numeric( $field ) ) {
				$list .= "($value)";
			} elseif ( ( $mode == LIST_SET ) && is_numeric( $field ) ) {
				$list .= "$value";
			} elseif ( ( $mode == LIST_AND || $mode == LIST_OR ) && is_array( $value ) ) {
				if ( count( $value ) == 0 ) {
					throw new MWException( __METHOD__ . ": empty input for field $field" );
				} elseif ( count( $value ) == 1 ) {
					// Special-case single values, as IN isn't terribly efficient
					// Don't necessarily assume the single key is 0; we don't
					// enforce linear numeric ordering on other arrays here.
					$value = array_values( $value );
					$list .= $field . " = " . $this->addQuotes( $value[0] );
				} else {
					$list .= $field . " IN (" . $this->makeList( $value ) . ") ";
				}
			} elseif ( $value === null ) {
				if ( $mode == LIST_AND || $mode == LIST_OR ) {
					$list .= "$field IS ";
				} elseif ( $mode == LIST_SET ) {
					$list .= "$field = ";
				}
				$list .= 'NULL';
			} else {
				if ( $mode == LIST_AND || $mode == LIST_OR || $mode == LIST_SET ) {
					$list .= "$field = ";
				}
				$list .= $mode == LIST_NAMES ? $value : $this->addQuotes( $value );
			}
		}

		return $list;
	}

	/**
	 * Build a partial where clause from a 2-d array such as used for LinkBatch.
	 * The keys on each level may be either integers or strings.
	 *
	 * @param array $data organized as 2-d
	 *              array(baseKeyVal => array(subKeyVal => [ignored], ...), ...)
	 * @param string $baseKey field name to match the base-level keys to (eg 'pl_namespace')
	 * @param string $subKey field name to match the sub-level keys to (eg 'pl_title')
	 * @return Mixed: string SQL fragment, or false if no items in array.
	 */
	public function makeWhereFrom2d( $data, $baseKey, $subKey ) {
		$conds = array();

		foreach ( $data as $base => $sub ) {
			if ( count( $sub ) ) {
				$conds[] = $this->makeList(
					array( $baseKey => $base, $subKey => array_keys( $sub ) ),
					LIST_AND );
			}
		}

		if ( $conds ) {
			return $this->makeList( $conds, LIST_OR );
		} else {
			// Nothing to search for...
			return false;
		}
	}

	/**
	 * Return aggregated value alias
	 *
	 * @param $valuedata
	 * @param $valuename string
	 *
	 * @return string
	 */
	public function aggregateValue( $valuedata, $valuename = 'value' ) {
		return $valuename;
	}

	/**
	 * @param $field
	 * @return string
	 */
	public function bitNot( $field ) {
		return "(~$field)";
	}

	/**
	 * @param $fieldLeft
	 * @param $fieldRight
	 * @return string
	 */
	public function bitAnd( $fieldLeft, $fieldRight ) {
		return "($fieldLeft & $fieldRight)";
	}

	/**
	 * @param  $fieldLeft
	 * @param  $fieldRight
	 * @return string
	 */
	public function bitOr( $fieldLeft, $fieldRight ) {
		return "($fieldLeft | $fieldRight)";
	}

	/**
	 * Build a concatenation list to feed into a SQL query
	 * @param array $stringList list of raw SQL expressions; caller is responsible for any quoting
	 * @return String
	 */
	public function buildConcat( $stringList ) {
		return 'CONCAT(' . implode( ',', $stringList ) . ')';
	}

	/**
	 * Change the current database
	 *
	 * @todo Explain what exactly will fail if this is not overridden.
	 *
	 * @param $db
	 *
	 * @return bool Success or failure
	 */
	public function selectDB( $db ) {
		# Stub.  Shouldn't cause serious problems if it's not overridden, but
		# if your database engine supports a concept similar to MySQL's
		# databases you may as well.
		$this->mDBname = $db;
		return true;
	}

	/**
	 * Get the current DB name
	 */
	public function getDBname() {
		return $this->mDBname;
	}

	/**
	 * Get the server hostname or IP address
	 */
	public function getServer() {
		return $this->mServer;
	}

	/**
	 * Format a table name ready for use in constructing an SQL query
	 *
	 * This does two important things: it quotes the table names to clean them up,
	 * and it adds a table prefix if only given a table name with no quotes.
	 *
	 * All functions of this object which require a table name call this function
	 * themselves. Pass the canonical name to such functions. This is only needed
	 * when calling query() directly.
	 *
	 * @param string $name database table name
	 * @param string $format One of:
	 *   quoted - Automatically pass the table name through addIdentifierQuotes()
	 *            so that it can be used in a query.
	 *   raw - Do not add identifier quotes to the table name
	 * @return String: full database name
	 */
	public function tableName( $name, $format = 'quoted' ) {
		global $wgSharedDB, $wgSharedPrefix, $wgSharedTables;
		# Skip the entire process when we have a string quoted on both ends.
		# Note that we check the end so that we will still quote any use of
		# use of `database`.table. But won't break things if someone wants
		# to query a database table with a dot in the name.
		if ( $this->isQuotedIdentifier( $name ) ) {
			return $name;
		}

		# Lets test for any bits of text that should never show up in a table
		# name. Basically anything like JOIN or ON which are actually part of
		# SQL queries, but may end up inside of the table value to combine
		# sql. Such as how the API is doing.
		# Note that we use a whitespace test rather than a \b test to avoid
		# any remote case where a word like on may be inside of a table name
		# surrounded by symbols which may be considered word breaks.
		if ( preg_match( '/(^|\s)(DISTINCT|JOIN|ON|AS)(\s|$)/i', $name ) !== 0 ) {
			return $name;
		}

		# Split database and table into proper variables.
		# We reverse the explode so that database.table and table both output
		# the correct table.
		$dbDetails = explode( '.', $name, 2 );
		if ( count( $dbDetails ) == 2 ) {
			list( $database, $table ) = $dbDetails;
			# We don't want any prefix added in this case
			$prefix = '';
		} else {
			list( $table ) = $dbDetails;
			if ( $wgSharedDB !== null # We have a shared database
				&& $this->mForeign == false # We're not working on a foreign database
				&& !$this->isQuotedIdentifier( $table ) # Paranoia check to prevent shared tables listing '`table`'
				&& in_array( $table, $wgSharedTables ) # A shared table is selected
			) {
				$database = $wgSharedDB;
				$prefix = $wgSharedPrefix === null ? $this->mTablePrefix : $wgSharedPrefix;
			} else {
				$database = null;
				$prefix = $this->mTablePrefix; # Default prefix
			}
		}

		# Quote $table and apply the prefix if not quoted.
		$tableName = "{$prefix}{$table}";
		if ( $format == 'quoted' && !$this->isQuotedIdentifier( $tableName ) ) {
			$tableName = $this->addIdentifierQuotes( $tableName );
		}

		# Quote $database and merge it with the table name if needed
		if ( $database !== null ) {
			if ( $format == 'quoted' && !$this->isQuotedIdentifier( $database ) ) {
				$database = $this->addIdentifierQuotes( $database );
			}
			$tableName = $database . '.' . $tableName;
		}

		return $tableName;
	}

	/**
	 * Fetch a number of table names into an array
	 * This is handy when you need to construct SQL for joins
	 *
	 * Example:
	 * extract( $dbr->tableNames( 'user', 'watchlist' ) );
	 * $sql = "SELECT wl_namespace,wl_title FROM $watchlist,$user
	 *         WHERE wl_user=user_id AND wl_user=$nameWithQuotes";
	 *
	 * @return array
	 */
	public function tableNames() {
		$inArray = func_get_args();
		$retVal = array();

		foreach ( $inArray as $name ) {
			$retVal[$name] = $this->tableName( $name );
		}

		return $retVal;
	}

	/**
	 * Fetch a number of table names into an zero-indexed numerical array
	 * This is handy when you need to construct SQL for joins
	 *
	 * Example:
	 * list( $user, $watchlist ) = $dbr->tableNamesN( 'user', 'watchlist' );
	 * $sql = "SELECT wl_namespace,wl_title FROM $watchlist,$user
	 *         WHERE wl_user=user_id AND wl_user=$nameWithQuotes";
	 *
	 * @return array
	 */
	public function tableNamesN() {
		$inArray = func_get_args();
		$retVal = array();

		foreach ( $inArray as $name ) {
			$retVal[] = $this->tableName( $name );
		}

		return $retVal;
	}

	/**
	 * Get an aliased table name
	 * e.g. tableName AS newTableName
	 *
	 * @param string $name Table name, see tableName()
	 * @param string|bool $alias Alias (optional)
	 * @return string SQL name for aliased table. Will not alias a table to its own name
	 */
	public function tableNameWithAlias( $name, $alias = false ) {
		if ( !$alias || $alias == $name ) {
			return $this->tableName( $name );
		} else {
			return $this->tableName( $name ) . ' ' . $this->addIdentifierQuotes( $alias );
		}
	}

	/**
	 * Gets an array of aliased table names
	 *
	 * @param $tables array( [alias] => table )
	 * @return array of strings, see tableNameWithAlias()
	 */
	public function tableNamesWithAlias( $tables ) {
		$retval = array();
		foreach ( $tables as $alias => $table ) {
			if ( is_numeric( $alias ) ) {
				$alias = $table;
			}
			$retval[] = $this->tableNameWithAlias( $table, $alias );
		}
		return $retval;
	}

	/**
	 * Get an aliased field name
	 * e.g. fieldName AS newFieldName
	 *
	 * @param string $name Field name
	 * @param string|bool $alias Alias (optional)
	 * @return string SQL name for aliased field. Will not alias a field to its own name
	 */
	public function fieldNameWithAlias( $name, $alias = false ) {
		if ( !$alias || (string)$alias === (string)$name ) {
			return $name;
		} else {
			return $name . ' AS ' . $alias; //PostgreSQL needs AS
		}
	}

	/**
	 * Gets an array of aliased field names
	 *
	 * @param $fields array( [alias] => field )
	 * @return array of strings, see fieldNameWithAlias()
	 */
	public function fieldNamesWithAlias( $fields ) {
		$retval = array();
		foreach ( $fields as $alias => $field ) {
			if ( is_numeric( $alias ) ) {
				$alias = $field;
			}
			$retval[] = $this->fieldNameWithAlias( $field, $alias );
		}
		return $retval;
	}

	/**
	 * Get the aliased table name clause for a FROM clause
	 * which might have a JOIN and/or USE INDEX clause
	 *
	 * @param array $tables ( [alias] => table )
	 * @param $use_index array Same as for select()
	 * @param $join_conds array Same as for select()
	 * @return string
	 */
	protected function tableNamesWithUseIndexOrJOIN(
		$tables, $use_index = array(), $join_conds = array()
	) {
		$ret = array();
		$retJOIN = array();
		$use_index = (array)$use_index;
		$join_conds = (array)$join_conds;

		foreach ( $tables as $alias => $table ) {
			if ( !is_string( $alias ) ) {
				// No alias? Set it equal to the table name
				$alias = $table;
			}
			// Is there a JOIN clause for this table?
			if ( isset( $join_conds[$alias] ) ) {
				list( $joinType, $conds ) = $join_conds[$alias];
				$tableClause = $joinType;
				$tableClause .= ' ' . $this->tableNameWithAlias( $table, $alias );
				if ( isset( $use_index[$alias] ) ) { // has USE INDEX?
					$use = $this->useIndexClause( implode( ',', (array)$use_index[$alias] ) );
					if ( $use != '' ) {
						$tableClause .= ' ' . $use;
					}
				}
				$on = $this->makeList( (array)$conds, LIST_AND );
				if ( $on != '' ) {
					$tableClause .= ' ON (' . $on . ')';
				}

				$retJOIN[] = $tableClause;
			// Is there an INDEX clause for this table?
			} elseif ( isset( $use_index[$alias] ) ) {
				$tableClause = $this->tableNameWithAlias( $table, $alias );
				$tableClause .= ' ' . $this->useIndexClause(
					implode( ',', (array)$use_index[$alias] ) );

				$ret[] = $tableClause;
			} else {
				$tableClause = $this->tableNameWithAlias( $table, $alias );

				$ret[] = $tableClause;
			}
		}

		// We can't separate explicit JOIN clauses with ',', use ' ' for those
		$implicitJoins = !empty( $ret ) ? implode( ',', $ret ) : "";
		$explicitJoins = !empty( $retJOIN ) ? implode( ' ', $retJOIN ) : "";

		// Compile our final table clause
		return implode( ' ', array( $implicitJoins, $explicitJoins ) );
	}

	/**
	 * Get the name of an index in a given table
	 *
	 * @param $index
	 *
	 * @return string
	 */
	protected function indexName( $index ) {
		// Backwards-compatibility hack
		$renamed = array(
			'ar_usertext_timestamp' => 'usertext_timestamp',
			'un_user_id' => 'user_id',
			'un_user_ip' => 'user_ip',
		);

		if ( isset( $renamed[$index] ) ) {
			return $renamed[$index];
		} else {
			return $index;
		}
	}

	/**
	 * If it's a string, adds quotes and backslashes
	 * Otherwise returns as-is
	 *
	 * @param $s string
	 *
	 * @return string
	 */
	public function addQuotes( $s ) {
		if ( $s === null ) {
			return 'NULL';
		} else {
			# This will also quote numeric values. This should be harmless,
			# and protects against weird problems that occur when they really
			# _are_ strings such as article titles and string->number->string
			# conversion is not 1:1.
			return "'" . $this->strencode( $s ) . "'";
		}
	}

	/**
	 * Quotes an identifier using `backticks` or "double quotes" depending on the database type.
	 * MySQL uses `backticks` while basically everything else uses double quotes.
	 * Since MySQL is the odd one out here the double quotes are our generic
	 * and we implement backticks in DatabaseMysql.
	 *
	 * @param $s string
	 *
	 * @return string
	 */
	public function addIdentifierQuotes( $s ) {
		return '"' . str_replace( '"', '""', $s ) . '"';
	}

	/**
	 * Returns if the given identifier looks quoted or not according to
	 * the database convention for quoting identifiers .
	 *
	 * @param $name string
	 *
	 * @return boolean
	 */
	public function isQuotedIdentifier( $name ) {
		return $name[0] == '"' && substr( $name, -1, 1 ) == '"';
	}

	/**
	 * @param $s string
	 * @return string
	 */
	protected function escapeLikeInternal( $s ) {
		$s = str_replace( '\\', '\\\\', $s );
		$s = $this->strencode( $s );
		$s = str_replace( array( '%', '_' ), array( '\%', '\_' ), $s );

		return $s;
	}

	/**
	 * LIKE statement wrapper, receives a variable-length argument list with parts of pattern to match
	 * containing either string literals that will be escaped or tokens returned by anyChar() or anyString().
	 * Alternatively, the function could be provided with an array of aforementioned parameters.
	 *
	 * Example: $dbr->buildLike( 'My_page_title/', $dbr->anyString() ) returns a LIKE clause that searches
	 * for subpages of 'My page title'.
	 * Alternatively: $pattern = array( 'My_page_title/', $dbr->anyString() ); $query .= $dbr->buildLike( $pattern );
	 *
	 * @since 1.16
	 * @return String: fully built LIKE statement
	 */
	public function buildLike() {
		$params = func_get_args();

		if ( count( $params ) > 0 && is_array( $params[0] ) ) {
			$params = $params[0];
		}

		$s = '';

		foreach ( $params as $value ) {
			if ( $value instanceof LikeMatch ) {
				$s .= $value->toString();
			} else {
				$s .= $this->escapeLikeInternal( $value );
			}
		}

		return " LIKE '" . $s . "' ";
	}

	/**
	 * Returns a token for buildLike() that denotes a '_' to be used in a LIKE query
	 *
	 * @return LikeMatch
	 */
	public function anyChar() {
		return new LikeMatch( '_' );
	}

	/**
	 * Returns a token for buildLike() that denotes a '%' to be used in a LIKE query
	 *
	 * @return LikeMatch
	 */
	public function anyString() {
		return new LikeMatch( '%' );
	}

	/**
	 * Returns an appropriately quoted sequence value for inserting a new row.
	 * MySQL has autoincrement fields, so this is just NULL. But the PostgreSQL
	 * subclass will return an integer, and save the value for insertId()
	 *
	 * Any implementation of this function should *not* involve reusing
	 * sequence numbers created for rolled-back transactions.
	 * See http://bugs.mysql.com/bug.php?id=30767 for details.
	 * @param $seqName string
	 * @return null
	 */
	public function nextSequenceValue( $seqName ) {
		return null;
	}

	/**
	 * USE INDEX clause.  Unlikely to be useful for anything but MySQL.  This
	 * is only needed because a) MySQL must be as efficient as possible due to
	 * its use on Wikipedia, and b) MySQL 4.0 is kind of dumb sometimes about
	 * which index to pick.  Anyway, other databases might have different
	 * indexes on a given table.  So don't bother overriding this unless you're
	 * MySQL.
	 * @param $index
	 * @return string
	 */
	public function useIndexClause( $index ) {
		return '';
	}

	/**
	 * REPLACE query wrapper.
	 *
	 * REPLACE is a very handy MySQL extension, which functions like an INSERT
	 * except that when there is a duplicate key error, the old row is deleted
	 * and the new row is inserted in its place.
	 *
	 * We simulate this with standard SQL with a DELETE followed by INSERT. To
	 * perform the delete, we need to know what the unique indexes are so that
	 * we know how to find the conflicting rows.
	 *
	 * It may be more efficient to leave off unique indexes which are unlikely
	 * to collide. However if you do this, you run the risk of encountering
	 * errors which wouldn't have occurred in MySQL.
	 *
	 * @param string $table The table to replace the row(s) in.
	 * @param array $rows Can be either a single row to insert, or multiple rows,
	 *    in the same format as for DatabaseBase::insert()
	 * @param array $uniqueIndexes is an array of indexes. Each element may be either
	 *    a field name or an array of field names
	 * @param string $fname Calling function name (use __METHOD__) for logs/profiling
	 */
	public function replace( $table, $uniqueIndexes, $rows, $fname = __METHOD__ ) {
		$quotedTable = $this->tableName( $table );

		if ( count( $rows ) == 0 ) {
			return;
		}

		# Single row case
		if ( !is_array( reset( $rows ) ) ) {
			$rows = array( $rows );
		}

		foreach ( $rows as $row ) {
			# Delete rows which collide
			if ( $uniqueIndexes ) {
				$sql = "DELETE FROM $quotedTable WHERE ";
				$first = true;
				foreach ( $uniqueIndexes as $index ) {
					if ( $first ) {
						$first = false;
						$sql .= '( ';
					} else {
						$sql .= ' ) OR ( ';
					}
					if ( is_array( $index ) ) {
						$first2 = true;
						foreach ( $index as $col ) {
							if ( $first2 ) {
								$first2 = false;
							} else {
								$sql .= ' AND ';
							}
							$sql .= $col . '=' . $this->addQuotes( $row[$col] );
						}
					} else {
						$sql .= $index . '=' . $this->addQuotes( $row[$index] );
					}
				}
				$sql .= ' )';
				$this->query( $sql, $fname );
			}

			# Now insert the row
			$this->insert( $table, $row, $fname );
		}
	}

	/**
	 * REPLACE query wrapper for MySQL and SQLite, which have a native REPLACE
	 * statement.
	 *
	 * @param string $table Table name
	 * @param array $rows Rows to insert
	 * @param string $fname Caller function name
	 *
	 * @return ResultWrapper
	 */
	protected function nativeReplace( $table, $rows, $fname ) {
		$table = $this->tableName( $table );

		# Single row case
		if ( !is_array( reset( $rows ) ) ) {
			$rows = array( $rows );
		}

		$sql = "REPLACE INTO $table (" . implode( ',', array_keys( $rows[0] ) ) . ') VALUES ';
		$first = true;

		foreach ( $rows as $row ) {
			if ( $first ) {
				$first = false;
			} else {
				$sql .= ',';
			}

			$sql .= '(' . $this->makeList( $row ) . ')';
		}

		return $this->query( $sql, $fname );
	}

	/**
	 * INSERT ON DUPLICATE KEY UPDATE wrapper, upserts an array into a table.
	 *
	 * This updates any conflicting rows (according to the unique indexes) using
	 * the provided SET clause and inserts any remaining (non-conflicted) rows.
	 *
	 * $rows may be either:
	 *   - A single associative array. The array keys are the field names, and
	 *     the values are the values to insert. The values are treated as data
	 *     and will be quoted appropriately. If NULL is inserted, this will be
	 *     converted to a database NULL.
	 *   - An array with numeric keys, holding a list of associative arrays.
	 *     This causes a multi-row INSERT on DBMSs that support it. The keys in
	 *     each subarray must be identical to each other, and in the same order.
	 *
	 * It may be more efficient to leave off unique indexes which are unlikely
	 * to collide. However if you do this, you run the risk of encountering
	 * errors which wouldn't have occurred in MySQL.
	 *
	 * Usually throws a DBQueryError on failure. If errors are explicitly ignored,
	 * returns success.
	 *
	 * @param string $table Table name. This will be passed through DatabaseBase::tableName().
	 * @param array $rows A single row or list of rows to insert
	 * @param array $uniqueIndexes List of single field names or field name tuples
	 * @param array $set An array of values to SET. For each array element,
	 *                the key gives the field name, and the value gives the data
	 *                to set that field to. The data will be quoted by
	 *                DatabaseBase::addQuotes().
	 * @param string $fname Calling function name (use __METHOD__) for logs/profiling
	 * @param array $options of options
	 *
	 * @return bool
	 * @since 1.22
	 */
	public function upsert(
		$table, array $rows, array $uniqueIndexes, array $set, $fname = __METHOD__
	) {
		if ( !count( $rows ) ) {
			return true; // nothing to do
		}
		$rows = is_array( reset( $rows ) ) ? $rows : array( $rows );

		if ( count( $uniqueIndexes ) ) {
			$clauses = array(); // list WHERE clauses that each identify a single row
			foreach ( $rows as $row ) {
				foreach ( $uniqueIndexes as $index ) {
					$index = is_array( $index ) ? $index : array( $index ); // columns
					$rowKey = array(); // unique key to this row
					foreach ( $index as $column ) {
						$rowKey[$column] = $row[$column];
					}
					$clauses[] = $this->makeList( $rowKey, LIST_AND );
				}
			}
			$where = array( $this->makeList( $clauses, LIST_OR ) );
		} else {
			$where = false;
		}

		$useTrx = !$this->mTrxLevel;
		if ( $useTrx ) {
			$this->begin( $fname );
		}
		try {
			# Update any existing conflicting row(s)
			if ( $where !== false ) {
				$ok = $this->update( $table, $set, $where, $fname );
			} else {
				$ok = true;
			}
			# Now insert any non-conflicting row(s)
			$ok = $this->insert( $table, $rows, $fname, array( 'IGNORE' ) ) && $ok;
		} catch ( Exception $e ) {
			if ( $useTrx ) {
				$this->rollback( $fname );
			}
			throw $e;
		}
		if ( $useTrx ) {
			$this->commit( $fname );
		}

		return $ok;
	}

	/**
	 * DELETE where the condition is a join.
	 *
	 * MySQL overrides this to use a multi-table DELETE syntax, in other databases
	 * we use sub-selects
	 *
	 * For safety, an empty $conds will not delete everything. If you want to
	 * delete all rows where the join condition matches, set $conds='*'.
	 *
	 * DO NOT put the join condition in $conds.
	 *
	 * @param $delTable   String: The table to delete from.
	 * @param $joinTable  String: The other table.
	 * @param $delVar     String: The variable to join on, in the first table.
	 * @param $joinVar    String: The variable to join on, in the second table.
	 * @param $conds      Array: Condition array of field names mapped to variables,
	 *                    ANDed together in the WHERE clause
	 * @param $fname      String: Calling function name (use __METHOD__) for
	 *                    logs/profiling
	 * @throws DBUnexpectedError
	 */
	public function deleteJoin( $delTable, $joinTable, $delVar, $joinVar, $conds,
		$fname = __METHOD__ )
	{
		if ( !$conds ) {
			throw new DBUnexpectedError( $this,
				'DatabaseBase::deleteJoin() called with empty $conds' );
		}

		$delTable = $this->tableName( $delTable );
		$joinTable = $this->tableName( $joinTable );
		$sql = "DELETE FROM $delTable WHERE $delVar IN (SELECT $joinVar FROM $joinTable ";
		if ( $conds != '*' ) {
			$sql .= 'WHERE ' . $this->makeList( $conds, LIST_AND );
		}
		$sql .= ')';

		$this->query( $sql, $fname );
	}

	/**
	 * Returns the size of a text field, or -1 for "unlimited"
	 *
	 * @param $table string
	 * @param $field string
	 *
	 * @return int
	 */
	public function textFieldSize( $table, $field ) {
		$table = $this->tableName( $table );
		$sql = "SHOW COLUMNS FROM $table LIKE \"$field\";";
		$res = $this->query( $sql, 'DatabaseBase::textFieldSize' );
		$row = $this->fetchObject( $res );

		$m = array();

		if ( preg_match( '/\((.*)\)/', $row->Type, $m ) ) {
			$size = $m[1];
		} else {
			$size = -1;
		}

		return $size;
	}

	/**
	 * A string to insert into queries to show that they're low-priority, like
	 * MySQL's LOW_PRIORITY.  If no such feature exists, return an empty
	 * string and nothing bad should happen.
	 *
	 * @return string Returns the text of the low priority option if it is
	 *   supported, or a blank string otherwise
	 */
	public function lowPriorityOption() {
		return '';
	}

	/**
	 * DELETE query wrapper.
	 *
	 * @param array $table Table name
	 * @param string|array $conds of conditions. See $conds in DatabaseBase::select() for
	 *               the format. Use $conds == "*" to delete all rows
	 * @param string $fname name of the calling function
	 *
	 * @throws DBUnexpectedError
	 * @return bool|ResultWrapper
	 */
	public function delete( $table, $conds, $fname = __METHOD__ ) {
		if ( !$conds ) {
			throw new DBUnexpectedError( $this, 'DatabaseBase::delete() called with no conditions' );
		}

		$table = $this->tableName( $table );
		$sql = "DELETE FROM $table";

		if ( $conds != '*' ) {
			if ( is_array( $conds ) ) {
				$conds = $this->makeList( $conds, LIST_AND );
			}
			$sql .= ' WHERE ' . $conds;
		}

		return $this->query( $sql, $fname );
	}

	/**
	 * INSERT SELECT wrapper. Takes data from a SELECT query and inserts it
	 * into another table.
	 *
	 * @param string $destTable The table name to insert into
	 * @param string|array $srcTable May be either a table name, or an array of table names
	 *    to include in a join.
	 *
	 * @param array $varMap must be an associative array of the form
	 *    array( 'dest1' => 'source1', ...). Source items may be literals
	 *    rather than field names, but strings should be quoted with
	 *    DatabaseBase::addQuotes()
	 *
	 * @param array $conds Condition array. See $conds in DatabaseBase::select() for
	 *    the details of the format of condition arrays. May be "*" to copy the
	 *    whole table.
	 *
	 * @param string $fname The function name of the caller, from __METHOD__
	 *
	 * @param array $insertOptions Options for the INSERT part of the query, see
	 *    DatabaseBase::insert() for details.
	 * @param array $selectOptions Options for the SELECT part of the query, see
	 *    DatabaseBase::select() for details.
	 *
	 * @return ResultWrapper
	 */
	public function insertSelect( $destTable, $srcTable, $varMap, $conds,
		$fname = __METHOD__,
		$insertOptions = array(), $selectOptions = array() )
	{
		$destTable = $this->tableName( $destTable );

		if ( is_array( $insertOptions ) ) {
			$insertOptions = implode( ' ', $insertOptions );
		}

		if ( !is_array( $selectOptions ) ) {
			$selectOptions = array( $selectOptions );
		}

		list( $startOpts, $useIndex, $tailOpts ) = $this->makeSelectOptions( $selectOptions );

		if ( is_array( $srcTable ) ) {
			$srcTable = implode( ',', array_map( array( &$this, 'tableName' ), $srcTable ) );
		} else {
			$srcTable = $this->tableName( $srcTable );
		}

		$sql = "INSERT $insertOptions INTO $destTable (" . implode( ',', array_keys( $varMap ) ) . ')' .
			" SELECT $startOpts " . implode( ',', $varMap ) .
			" FROM $srcTable $useIndex ";

		if ( $conds != '*' ) {
			if ( is_array( $conds ) ) {
				$conds = $this->makeList( $conds, LIST_AND );
			}
			$sql .= " WHERE $conds";
		}

		$sql .= " $tailOpts";

		return $this->query( $sql, $fname );
	}

	/**
	 * Construct a LIMIT query with optional offset.  This is used for query
	 * pages.  The SQL should be adjusted so that only the first $limit rows
	 * are returned.  If $offset is provided as well, then the first $offset
	 * rows should be discarded, and the next $limit rows should be returned.
	 * If the result of the query is not ordered, then the rows to be returned
	 * are theoretically arbitrary.
	 *
	 * $sql is expected to be a SELECT, if that makes a difference.
	 *
	 * The version provided by default works in MySQL and SQLite.  It will very
	 * likely need to be overridden for most other DBMSes.
	 *
	 * @param string $sql SQL query we will append the limit too
	 * @param $limit Integer the SQL limit
	 * @param $offset Integer|bool the SQL offset (default false)
	 *
	 * @throws DBUnexpectedError
	 * @return string
	 */
	public function limitResult( $sql, $limit, $offset = false ) {
		if ( !is_numeric( $limit ) ) {
			throw new DBUnexpectedError( $this, "Invalid non-numeric limit passed to limitResult()\n" );
		}
		return "$sql LIMIT "
			. ( ( is_numeric( $offset ) && $offset != 0 ) ? "{$offset}," : "" )
			. "{$limit} ";
	}

	/**
	 * Returns true if current database backend supports ORDER BY or LIMIT for separate subqueries
	 * within the UNION construct.
	 * @return Boolean
	 */
	public function unionSupportsOrderAndLimit() {
		return true; // True for almost every DB supported
	}

	/**
	 * Construct a UNION query
	 * This is used for providing overload point for other DB abstractions
	 * not compatible with the MySQL syntax.
	 * @param array $sqls SQL statements to combine
	 * @param $all Boolean: use UNION ALL
	 * @return String: SQL fragment
	 */
	public function unionQueries( $sqls, $all ) {
		$glue = $all ? ') UNION ALL (' : ') UNION (';
		return '(' . implode( $glue, $sqls ) . ')';
	}

	/**
	 * Returns an SQL expression for a simple conditional.  This doesn't need
	 * to be overridden unless CASE isn't supported in your DBMS.
	 *
	 * @param string|array $cond SQL expression which will result in a boolean value
	 * @param string $trueVal SQL expression to return if true
	 * @param string $falseVal SQL expression to return if false
	 * @return String: SQL fragment
	 */
	public function conditional( $cond, $trueVal, $falseVal ) {
		if ( is_array( $cond ) ) {
			$cond = $this->makeList( $cond, LIST_AND );
		}
		return " (CASE WHEN $cond THEN $trueVal ELSE $falseVal END) ";
	}

	/**
	 * Returns a comand for str_replace function in SQL query.
	 * Uses REPLACE() in MySQL
	 *
	 * @param string $orig column to modify
	 * @param string $old column to seek
	 * @param string $new column to replace with
	 *
	 * @return string
	 */
	public function strreplace( $orig, $old, $new ) {
		return "REPLACE({$orig}, {$old}, {$new})";
	}

	/**
	 * Determines how long the server has been up
	 * STUB
	 *
	 * @return int
	 */
	public function getServerUptime() {
		return 0;
	}

	/**
	 * Determines if the last failure was due to a deadlock
	 * STUB
	 *
	 * @return bool
	 */
	public function wasDeadlock() {
		return false;
	}

	/**
	 * Determines if the last failure was due to a lock timeout
	 * STUB
	 *
	 * @return bool
	 */
	public function wasLockTimeout() {
		return false;
	}

	/**
	 * Determines if the last query error was something that should be dealt
	 * with by pinging the connection and reissuing the query.
	 * STUB
	 *
	 * @return bool
	 */
	public function wasErrorReissuable() {
		return false;
	}

	/**
	 * Determines if the last failure was due to the database being read-only.
	 * STUB
	 *
	 * @return bool
	 */
	public function wasReadOnlyError() {
		return false;
	}

	/**
	 * Perform a deadlock-prone transaction.
	 *
	 * This function invokes a callback function to perform a set of write
	 * queries. If a deadlock occurs during the processing, the transaction
	 * will be rolled back and the callback function will be called again.
	 *
	 * Usage:
	 *   $dbw->deadlockLoop( callback, ... );
	 *
	 * Extra arguments are passed through to the specified callback function.
	 *
	 * Returns whatever the callback function returned on its successful,
	 * iteration, or false on error, for example if the retry limit was
	 * reached.
	 *
	 * @return bool
	 */
	public function deadlockLoop() {
		$this->begin( __METHOD__ );
		$args = func_get_args();
		$function = array_shift( $args );
		$oldIgnore = $this->ignoreErrors( true );
		$tries = self::DEADLOCK_TRIES;

		if ( is_array( $function ) ) {
			$fname = $function[0];
		} else {
			$fname = $function;
		}

		do {
			$retVal = call_user_func_array( $function, $args );
			$error = $this->lastError();
			$errno = $this->lastErrno();
			$sql = $this->lastQuery();

			if ( $errno ) {
				if ( $this->wasDeadlock() ) {
					# Retry
					usleep( mt_rand( self::DEADLOCK_DELAY_MIN, self::DEADLOCK_DELAY_MAX ) );
				} else {
					$this->reportQueryError( $error, $errno, $sql, $fname );
				}
			}
		} while ( $this->wasDeadlock() && --$tries > 0 );

		$this->ignoreErrors( $oldIgnore );

		if ( $tries <= 0 ) {
			$this->rollback( __METHOD__ );
			$this->reportQueryError( $error, $errno, $sql, $fname );
			return false;
		} else {
			$this->commit( __METHOD__ );
			return $retVal;
		}
	}

	/**
	 * Wait for the slave to catch up to a given master position.
	 *
	 * @param $pos DBMasterPos object
	 * @param $timeout Integer: the maximum number of seconds to wait for
	 *   synchronisation
	 *
	 * @return integer: zero if the slave was past that position already,
	 *   greater than zero if we waited for some period of time, less than
	 *   zero if we timed out.
	 */
	public function masterPosWait( DBMasterPos $pos, $timeout ) {
		wfProfileIn( __METHOD__ );

		if ( !is_null( $this->mFakeSlaveLag ) ) {
			$wait = intval( ( $pos->pos - microtime( true ) + $this->mFakeSlaveLag ) * 1e6 );

			if ( $wait > $timeout * 1e6 ) {
				wfDebug( "Fake slave timed out waiting for $pos ($wait us)\n" );
				wfProfileOut( __METHOD__ );
				return -1;
			} elseif ( $wait > 0 ) {
				wfDebug( "Fake slave waiting $wait us\n" );
				usleep( $wait );
				wfProfileOut( __METHOD__ );
				return 1;
			} else {
				wfDebug( "Fake slave up to date ($wait us)\n" );
				wfProfileOut( __METHOD__ );
				return 0;
			}
		}

		wfProfileOut( __METHOD__ );

		# Real waits are implemented in the subclass.
		return 0;
	}

	/**
	 * Get the replication position of this slave
	 *
	 * @return DBMasterPos, or false if this is not a slave.
	 */
	public function getSlavePos() {
		if ( !is_null( $this->mFakeSlaveLag ) ) {
			$pos = new MySQLMasterPos( 'fake', microtime( true ) - $this->mFakeSlaveLag );
			wfDebug( __METHOD__ . ": fake slave pos = $pos\n" );
			return $pos;
		} else {
			# Stub
			return false;
		}
	}

	/**
	 * Get the position of this master
	 *
	 * @return DBMasterPos, or false if this is not a master
	 */
	public function getMasterPos() {
		if ( $this->mFakeMaster ) {
			return new MySQLMasterPos( 'fake', microtime( true ) );
		} else {
			return false;
		}
	}

	/**
	 * Run an anonymous function as soon as there is no transaction pending.
	 * If there is a transaction and it is rolled back, then the callback is cancelled.
	 * Queries in the function will run in AUTO-COMMIT mode unless there are begin() calls.
	 * Callbacks must commit any transactions that they begin.
	 *
	 * This is useful for updates to different systems or when separate transactions are needed.
	 * For example, one might want to enqueue jobs into a system outside the database, but only
	 * after the database is updated so that the jobs will see the data when they actually run.
	 * It can also be used for updates that easily cause deadlocks if locks are held too long.
	 *
	 * @param callable $callback
	 * @since 1.20
	 */
	final public function onTransactionIdle( $callback ) {
		$this->mTrxIdleCallbacks[] = $callback;
		if ( !$this->mTrxLevel ) {
			$this->runOnTransactionIdleCallbacks();
		}
	}

	/**
	 * Run an anonymous function before the current transaction commits or now if there is none.
	 * If there is a transaction and it is rolled back, then the callback is cancelled.
	 * Callbacks must not start nor commit any transactions.
	 *
	 * This is useful for updates that easily cause deadlocks if locks are held too long
	 * but where atomicity is strongly desired for these updates and some related updates.
	 *
	 * @param callable $callback
	 * @since 1.22
	 */
	final public function onTransactionPreCommitOrIdle( $callback ) {
		if ( $this->mTrxLevel ) {
			$this->mTrxPreCommitCallbacks[] = $callback;
		} else {
			$this->onTransactionIdle( $callback ); // this will trigger immediately
		}
	}

	/**
	 * Actually any "on transaction idle" callbacks.
	 *
	 * @since 1.20
	 */
	protected function runOnTransactionIdleCallbacks() {
		$autoTrx = $this->getFlag( DBO_TRX ); // automatic begin() enabled?

		$e = null; // last exception
		do { // callbacks may add callbacks :)
			$callbacks = $this->mTrxIdleCallbacks;
			$this->mTrxIdleCallbacks = array(); // recursion guard
			foreach ( $callbacks as $callback ) {
				try {
					$this->clearFlag( DBO_TRX ); // make each query its own transaction
					$callback();
					$this->setFlag( $autoTrx ? DBO_TRX : 0 ); // restore automatic begin()
				} catch ( Exception $e ) {
				}
			}
		} while ( count( $this->mTrxIdleCallbacks ) );

		if ( $e instanceof Exception ) {
			throw $e; // re-throw any last exception
		}
	}

	/**
	 * Actually any "on transaction pre-commit" callbacks.
	 *
	 * @since 1.22
	 */
	protected function runOnTransactionPreCommitCallbacks() {
		$e = null; // last exception
		do { // callbacks may add callbacks :)
			$callbacks = $this->mTrxPreCommitCallbacks;
			$this->mTrxPreCommitCallbacks = array(); // recursion guard
			foreach ( $callbacks as $callback ) {
				try {
					$callback();
				} catch ( Exception $e ) {}
			}
		} while ( count( $this->mTrxPreCommitCallbacks ) );

		if ( $e instanceof Exception ) {
			throw $e; // re-throw any last exception
		}
	}

	/**
	 * Begin a transaction. If a transaction is already in progress, that transaction will be committed before the
	 * new transaction is started.
	 *
	 * Note that when the DBO_TRX flag is set (which is usually the case for web requests, but not for maintenance scripts),
	 * any previous database query will have started a transaction automatically.
	 *
	 * Nesting of transactions is not supported. Attempts to nest transactions will cause a warning, unless the current
	 * transaction was started automatically because of the DBO_TRX flag.
	 *
	 * @param $fname string
	 */
	final public function begin( $fname = __METHOD__ ) {
		global $wgDebugDBTransactions;

		if ( $this->mTrxLevel ) { // implicit commit
			if ( !$this->mTrxAutomatic ) {
				// We want to warn about inadvertently nested begin/commit pairs, but not about
				// auto-committing implicit transactions that were started by query() via DBO_TRX
				$msg = "$fname: Transaction already in progress (from {$this->mTrxFname}), " .
					" performing implicit commit!";
				wfWarn( $msg );
				wfLogDBError( $msg );
			} else {
				// if the transaction was automatic and has done write operations,
				// log it if $wgDebugDBTransactions is enabled.
				if ( $this->mTrxDoneWrites && $wgDebugDBTransactions ) {
					wfDebug( "$fname: Automatic transaction with writes in progress" .
						" (from {$this->mTrxFname}), performing implicit commit!\n"
					);
				}
			}

			$this->runOnTransactionPreCommitCallbacks();
			$this->doCommit( $fname );
			if ( $this->mTrxDoneWrites ) {
				Profiler::instance()->transactionWritingOut( $this->mServer, $this->mDBname );
			}
			$this->runOnTransactionIdleCallbacks();
		}

		$this->doBegin( $fname );
		$this->mTrxFname = $fname;
		$this->mTrxDoneWrites = false;
		$this->mTrxAutomatic = false;
	}

	/**
	 * Issues the BEGIN command to the database server.
	 *
	 * @see DatabaseBase::begin()
	 * @param type $fname
	 */
	protected function doBegin( $fname ) {
		$this->query( 'BEGIN', $fname );
		$this->mTrxLevel = 1;
	}

	/**
	 * Commits a transaction previously started using begin().
	 * If no transaction is in progress, a warning is issued.
	 *
	 * Nesting of transactions is not supported.
	 *
	 * @param $fname string
	 * @param string $flush Flush flag, set to 'flush' to disable warnings about explicitly committing implicit
	 *        transactions, or calling commit when no transaction is in progress.
	 *        This will silently break any ongoing explicit transaction. Only set the flush flag if you are sure
	 *        that it is safe to ignore these warnings in your context.
	 */
	final public function commit( $fname = __METHOD__, $flush = '' ) {
		if ( $flush != 'flush' ) {
			if ( !$this->mTrxLevel ) {
				wfWarn( "$fname: No transaction to commit, something got out of sync!" );
			} elseif ( $this->mTrxAutomatic ) {
				wfWarn( "$fname: Explicit commit of implicit transaction. Something may be out of sync!" );
			}
		} else {
			if ( !$this->mTrxLevel ) {
				return; // nothing to do
			} elseif ( !$this->mTrxAutomatic ) {
				wfWarn( "$fname: Flushing an explicit transaction, getting out of sync!" );
			}
		}

		$this->runOnTransactionPreCommitCallbacks();
		$this->doCommit( $fname );
		if ( $this->mTrxDoneWrites ) {
			Profiler::instance()->transactionWritingOut( $this->mServer, $this->mDBname );
		}
		$this->runOnTransactionIdleCallbacks();
	}

	/**
	 * Issues the COMMIT command to the database server.
	 *
	 * @see DatabaseBase::commit()
	 * @param type $fname
	 */
	protected function doCommit( $fname ) {
		if ( $this->mTrxLevel ) {
			$this->query( 'COMMIT', $fname );
			$this->mTrxLevel = 0;
		}
	}

	/**
	 * Rollback a transaction previously started using begin().
	 * If no transaction is in progress, a warning is issued.
	 *
	 * No-op on non-transactional databases.
	 *
	 * @param $fname string
	 */
	final public function rollback( $fname = __METHOD__ ) {
		if ( !$this->mTrxLevel ) {
			wfWarn( "$fname: No transaction to rollback, something got out of sync!" );
		}
		$this->doRollback( $fname );
		$this->mTrxIdleCallbacks = array(); // cancel
		$this->mTrxPreCommitCallbacks = array(); // cancel
		if ( $this->mTrxDoneWrites ) {
			Profiler::instance()->transactionWritingOut( $this->mServer, $this->mDBname );
		}
	}

	/**
	 * Issues the ROLLBACK command to the database server.
	 *
	 * @see DatabaseBase::rollback()
	 * @param type $fname
	 */
	protected function doRollback( $fname ) {
		if ( $this->mTrxLevel ) {
			$this->query( 'ROLLBACK', $fname, true );
			$this->mTrxLevel = 0;
		}
	}

	/**
	 * Creates a new table with structure copied from existing table
	 * Note that unlike most database abstraction functions, this function does not
	 * automatically append database prefix, because it works at a lower
	 * abstraction level.
	 * The table names passed to this function shall not be quoted (this
	 * function calls addIdentifierQuotes when needed).
	 *
	 * @param string $oldName name of table whose structure should be copied
	 * @param string $newName name of table to be created
	 * @param $temporary Boolean: whether the new table should be temporary
	 * @param string $fname calling function name
	 * @throws MWException
	 * @return Boolean: true if operation was successful
	 */
	public function duplicateTableStructure( $oldName, $newName, $temporary = false,
		$fname = __METHOD__
	) {
		throw new MWException(
			'DatabaseBase::duplicateTableStructure is not implemented in descendant class' );
	}

	/**
	 * List all tables on the database
	 *
	 * @param string $prefix Only show tables with this prefix, e.g. mw_
	 * @param string $fname calling function name
	 * @throws MWException
	 */
	function listTables( $prefix = null, $fname = __METHOD__ ) {
		throw new MWException( 'DatabaseBase::listTables is not implemented in descendant class' );
	}

	/**
	 * Convert a timestamp in one of the formats accepted by wfTimestamp()
	 * to the format used for inserting into timestamp fields in this DBMS.
	 *
	 * The result is unquoted, and needs to be passed through addQuotes()
	 * before it can be included in raw SQL.
	 *
	 * @param $ts string|int
	 *
	 * @return string
	 */
	public function timestamp( $ts = 0 ) {
		return wfTimestamp( TS_MW, $ts );
	}

	/**
	 * Convert a timestamp in one of the formats accepted by wfTimestamp()
	 * to the format used for inserting into timestamp fields in this DBMS. If
	 * NULL is input, it is passed through, allowing NULL values to be inserted
	 * into timestamp fields.
	 *
	 * The result is unquoted, and needs to be passed through addQuotes()
	 * before it can be included in raw SQL.
	 *
	 * @param $ts string|int
	 *
	 * @return string
	 */
	public function timestampOrNull( $ts = null ) {
		if ( is_null( $ts ) ) {
			return null;
		} else {
			return $this->timestamp( $ts );
		}
	}

	/**
	 * Take the result from a query, and wrap it in a ResultWrapper if
	 * necessary. Boolean values are passed through as is, to indicate success
	 * of write queries or failure.
	 *
	 * Once upon a time, DatabaseBase::query() returned a bare MySQL result
	 * resource, and it was necessary to call this function to convert it to
	 * a wrapper. Nowadays, raw database objects are never exposed to external
	 * callers, so this is unnecessary in external code. For compatibility with
	 * old code, ResultWrapper objects are passed through unaltered.
	 *
	 * @param $result bool|ResultWrapper
	 *
	 * @return bool|ResultWrapper
	 */
	public function resultObject( $result ) {
		if ( empty( $result ) ) {
			return false;
		} elseif ( $result instanceof ResultWrapper ) {
			return $result;
		} elseif ( $result === true ) {
			// Successful write query
			return $result;
		} else {
			return new ResultWrapper( $this, $result );
		}
	}

	/**
	 * Ping the server and try to reconnect if it there is no connection
	 *
	 * @return bool Success or failure
	 */
	public function ping() {
		# Stub.  Not essential to override.
		return true;
	}

	/**
	 * Get slave lag. Currently supported only by MySQL.
	 *
	 * Note that this function will generate a fatal error on many
	 * installations. Most callers should use LoadBalancer::safeGetLag()
	 * instead.
	 *
	 * @return int Database replication lag in seconds
	 */
	public function getLag() {
		return intval( $this->mFakeSlaveLag );
	}

	/**
	 * Return the maximum number of items allowed in a list, or 0 for unlimited.
	 *
	 * @return int
	 */
	function maxListLen() {
		return 0;
	}

	/**
	 * Some DBMSs have a special format for inserting into blob fields, they
	 * don't allow simple quoted strings to be inserted. To insert into such
	 * a field, pass the data through this function before passing it to
	 * DatabaseBase::insert().
	 * @param $b string
	 * @return string
	 */
	public function encodeBlob( $b ) {
		return $b;
	}

	/**
	 * Some DBMSs return a special placeholder object representing blob fields
	 * in result objects. Pass the object through this function to return the
	 * original string.
	 * @param $b string
	 * @return string
	 */
	public function decodeBlob( $b ) {
		return $b;
	}

	/**
	 * Override database's default behavior. $options include:
	 *     'connTimeout' : Set the connection timeout value in seconds.
	 *                     May be useful for very long batch queries such as
	 *                     full-wiki dumps, where a single query reads out over
	 *                     hours or days.
	 *
	 * @param $options Array
	 * @return void
	 */
	public function setSessionOptions( array $options ) {
	}

	/**
	 * Read and execute SQL commands from a file.
	 *
	 * Returns true on success, error string or exception on failure (depending
	 * on object's error ignore settings).
	 *
	 * @param string $filename File name to open
	 * @param bool|callable $lineCallback Optional function called before reading each line
	 * @param bool|callable $resultCallback Optional function called for each MySQL result
	 * @param bool|string $fname Calling function name or false if name should be
	 *      generated dynamically using $filename
	 * @param bool|callable $inputCallback Callback: Optional function called for each complete line sent
	 * @throws MWException
	 * @throws Exception|MWException
	 * @return bool|string
	 */
	public function sourceFile(
		$filename, $lineCallback = false, $resultCallback = false, $fname = false, $inputCallback = false
	) {
		wfSuppressWarnings();
		$fp = fopen( $filename, 'r' );
		wfRestoreWarnings();

		if ( false === $fp ) {
			throw new MWException( "Could not open \"{$filename}\".\n" );
		}

		if ( !$fname ) {
			$fname = __METHOD__ . "( $filename )";
		}

		try {
			$error = $this->sourceStream( $fp, $lineCallback, $resultCallback, $fname, $inputCallback );
		}
		catch ( MWException $e ) {
			fclose( $fp );
			throw $e;
		}

		fclose( $fp );

		return $error;
	}

	/**
	 * Get the full path of a patch file. Originally based on archive()
	 * from updaters.inc. Keep in mind this always returns a patch, as
	 * it fails back to MySQL if no DB-specific patch can be found
	 *
	 * @param string $patch The name of the patch, like patch-something.sql
	 * @return String Full path to patch file
	 */
	public function patchPath( $patch ) {
		global $IP;

		$dbType = $this->getType();
		if ( file_exists( "$IP/maintenance/$dbType/archives/$patch" ) ) {
			return "$IP/maintenance/$dbType/archives/$patch";
		} else {
			return "$IP/maintenance/archives/$patch";
		}
	}

	/**
	 * Set variables to be used in sourceFile/sourceStream, in preference to the
	 * ones in $GLOBALS. If an array is set here, $GLOBALS will not be used at
	 * all. If it's set to false, $GLOBALS will be used.
	 *
	 * @param bool|array $vars mapping variable name to value.
	 */
	public function setSchemaVars( $vars ) {
		$this->mSchemaVars = $vars;
	}

	/**
	 * Read and execute commands from an open file handle.
	 *
	 * Returns true on success, error string or exception on failure (depending
	 * on object's error ignore settings).
	 *
	 * @param $fp Resource: File handle
	 * @param $lineCallback Callback: Optional function called before reading each query
	 * @param $resultCallback Callback: Optional function called for each MySQL result
	 * @param string $fname Calling function name
	 * @param $inputCallback Callback: Optional function called for each complete query sent
	 * @return bool|string
	 */
	public function sourceStream( $fp, $lineCallback = false, $resultCallback = false,
		$fname = __METHOD__, $inputCallback = false )
	{
		$cmd = '';

		while ( !feof( $fp ) ) {
			if ( $lineCallback ) {
				call_user_func( $lineCallback );
			}

			$line = trim( fgets( $fp ) );

			if ( $line == '' ) {
				continue;
			}

			if ( '-' == $line[0] && '-' == $line[1] ) {
				continue;
			}

			if ( $cmd != '' ) {
				$cmd .= ' ';
			}

			$done = $this->streamStatementEnd( $cmd, $line );

			$cmd .= "$line\n";

			if ( $done || feof( $fp ) ) {
				$cmd = $this->replaceVars( $cmd );

				if ( ( $inputCallback && call_user_func( $inputCallback, $cmd ) ) || !$inputCallback ) {
					$res = $this->query( $cmd, $fname );

					if ( $resultCallback ) {
						call_user_func( $resultCallback, $res, $this );
					}

					if ( false === $res ) {
						$err = $this->lastError();
						return "Query \"{$cmd}\" failed with error code \"$err\".\n";
					}
				}
				$cmd = '';
			}
		}

		return true;
	}

	/**
	 * Called by sourceStream() to check if we've reached a statement end
	 *
	 * @param string $sql SQL assembled so far
	 * @param string $newLine New line about to be added to $sql
	 * @return Bool Whether $newLine contains end of the statement
	 */
	public function streamStatementEnd( &$sql, &$newLine ) {
		if ( $this->delimiter ) {
			$prev = $newLine;
			$newLine = preg_replace( '/' . preg_quote( $this->delimiter, '/' ) . '$/', '', $newLine );
			if ( $newLine != $prev ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Database independent variable replacement. Replaces a set of variables
	 * in an SQL statement with their contents as given by $this->getSchemaVars().
	 *
	 * Supports '{$var}' `{$var}` and / *$var* / (without the spaces) style variables.
	 *
	 * - '{$var}' should be used for text and is passed through the database's
	 *   addQuotes method.
	 * - `{$var}` should be used for identifiers (eg: table and database names),
	 *   it is passed through the database's addIdentifierQuotes method which
	 *   can be overridden if the database uses something other than backticks.
	 * - / *$var* / is just encoded, besides traditional table prefix and
	 *   table options its use should be avoided.
	 *
	 * @param string $ins SQL statement to replace variables in
	 * @return String The new SQL statement with variables replaced
	 */
	protected function replaceSchemaVars( $ins ) {
		$vars = $this->getSchemaVars();
		foreach ( $vars as $var => $value ) {
			// replace '{$var}'
			$ins = str_replace( '\'{$' . $var . '}\'', $this->addQuotes( $value ), $ins );
			// replace `{$var}`
			$ins = str_replace( '`{$' . $var . '}`', $this->addIdentifierQuotes( $value ), $ins );
			// replace /*$var*/
			$ins = str_replace( '/*$' . $var . '*/', $this->strencode( $value ), $ins );
		}
		return $ins;
	}

	/**
	 * Replace variables in sourced SQL
	 *
	 * @param $ins string
	 *
	 * @return string
	 */
	protected function replaceVars( $ins ) {
		$ins = $this->replaceSchemaVars( $ins );

		// Table prefixes
		$ins = preg_replace_callback( '!/\*(?:\$wgDBprefix|_)\*/([a-zA-Z_0-9]*)!',
			array( $this, 'tableNameCallback' ), $ins );

		// Index names
		$ins = preg_replace_callback( '!/\*i\*/([a-zA-Z_0-9]*)!',
			array( $this, 'indexNameCallback' ), $ins );

		return $ins;
	}

	/**
	 * Get schema variables. If none have been set via setSchemaVars(), then
	 * use some defaults from the current object.
	 *
	 * @return array
	 */
	protected function getSchemaVars() {
		if ( $this->mSchemaVars ) {
			return $this->mSchemaVars;
		} else {
			return $this->getDefaultSchemaVars();
		}
	}

	/**
	 * Get schema variables to use if none have been set via setSchemaVars().
	 *
	 * Override this in derived classes to provide variables for tables.sql
	 * and SQL patch files.
	 *
	 * @return array
	 */
	protected function getDefaultSchemaVars() {
		return array();
	}

	/**
	 * Table name callback
	 *
	 * @param $matches array
	 *
	 * @return string
	 */
	protected function tableNameCallback( $matches ) {
		return $this->tableName( $matches[1] );
	}

	/**
	 * Index name callback
	 *
	 * @param $matches array
	 *
	 * @return string
	 */
	protected function indexNameCallback( $matches ) {
		return $this->indexName( $matches[1] );
	}

	/**
	 * Check to see if a named lock is available. This is non-blocking.
	 *
	 * @param string $lockName name of lock to poll
	 * @param string $method name of method calling us
	 * @return Boolean
	 * @since 1.20
	 */
	public function lockIsFree( $lockName, $method ) {
		return true;
	}

	/**
	 * Acquire a named lock
	 *
	 * Abstracted from Filestore::lock() so child classes can implement for
	 * their own needs.
	 *
	 * @param string $lockName name of lock to aquire
	 * @param string $method name of method calling us
	 * @param $timeout Integer: timeout
	 * @return Boolean
	 */
	public function lock( $lockName, $method, $timeout = 5 ) {
		return true;
	}

	/**
	 * Release a lock.
	 *
	 * @param string $lockName Name of lock to release
	 * @param string $method Name of method calling us
	 *
	 * @return int Returns 1 if the lock was released, 0 if the lock was not established
	 * by this thread (in which case the lock is not released), and NULL if the named
	 * lock did not exist
	 */
	public function unlock( $lockName, $method ) {
		return true;
	}

	/**
	 * Lock specific tables
	 *
	 * @param array $read of tables to lock for read access
	 * @param array $write of tables to lock for write access
	 * @param string $method name of caller
	 * @param bool $lowPriority Whether to indicate writes to be LOW PRIORITY
	 *
	 * @return bool
	 */
	public function lockTables( $read, $write, $method, $lowPriority = true ) {
		return true;
	}

	/**
	 * Unlock specific tables
	 *
	 * @param string $method the caller
	 *
	 * @return bool
	 */
	public function unlockTables( $method ) {
		return true;
	}

	/**
	 * Delete a table
	 * @param $tableName string
	 * @param $fName string
	 * @return bool|ResultWrapper
	 * @since 1.18
	 */
	public function dropTable( $tableName, $fName = __METHOD__ ) {
		if ( !$this->tableExists( $tableName, $fName ) ) {
			return false;
		}
		$sql = "DROP TABLE " . $this->tableName( $tableName );
		if ( $this->cascadingDeletes() ) {
			$sql .= " CASCADE";
		}
		return $this->query( $sql, $fName );
	}

	/**
	 * Get search engine class. All subclasses of this need to implement this
	 * if they wish to use searching.
	 *
	 * @return String
	 */
	public function getSearchEngine() {
		return 'SearchEngineDummy';
	}

	/**
	 * Find out when 'infinity' is. Most DBMSes support this. This is a special
	 * keyword for timestamps in PostgreSQL, and works with CHAR(14) as well
	 * because "i" sorts after all numbers.
	 *
	 * @return String
	 */
	public function getInfinity() {
		return 'infinity';
	}

	/**
	 * Encode an expiry time into the DBMS dependent format
	 *
	 * @param string $expiry timestamp for expiry, or the 'infinity' string
	 * @return String
	 */
	public function encodeExpiry( $expiry ) {
		return ( $expiry == '' || $expiry == 'infinity' || $expiry == $this->getInfinity() )
			? $this->getInfinity()
			: $this->timestamp( $expiry );
	}

	/**
	 * Decode an expiry time into a DBMS independent format
	 *
	 * @param string $expiry DB timestamp field value for expiry
	 * @param $format integer: TS_* constant, defaults to TS_MW
	 * @return String
	 */
	public function decodeExpiry( $expiry, $format = TS_MW ) {
		return ( $expiry == '' || $expiry == $this->getInfinity() )
			? 'infinity'
			: wfTimestamp( $format, $expiry );
	}

	/**
	 * Allow or deny "big selects" for this session only. This is done by setting
	 * the sql_big_selects session variable.
	 *
	 * This is a MySQL-specific feature.
	 *
	 * @param $value Mixed: true for allow, false for deny, or "default" to
	 *   restore the initial value
	 */
	public function setBigSelects( $value = true ) {
		// no-op
	}

	/**
	 * @since 1.19
	 */
	public function __toString() {
		return (string)$this->mConn;
	}

	public function __destruct() {
		if ( count( $this->mTrxIdleCallbacks ) || count( $this->mTrxPreCommitCallbacks ) ) {
			trigger_error( "Transaction idle or pre-commit callbacks still pending." ); // sanity
		}
	}
}
