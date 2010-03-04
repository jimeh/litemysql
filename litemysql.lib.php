<?php

/*

   LiteMySQL v1.2.0
   http://github.com/jimeh/litemysql

   Very light-weight and simple ORM-like MySQL library for PHP. Kind of like
   ActiveRecord's little brother.

   Requires PHP 5.0 or later.



   (The MIT License)
   
   Copyright (c) 2010 Jim Myhrberg.
   
   Permission is hereby granted, free of charge, to any person obtaining
   a copy of this software and associated documentation files (the
   'Software'), to deal in the Software without restriction, including
   without limitation the rights to use, copy, modify, merge, publish,
   distribute, sublicense, and/or sell copies of the Software, and to
   permit persons to whom the Software is furnished to do so, subject to
   the following conditions:
   
   The above copyright notice and this permission notice shall be
   included in all copies or substantial portions of the Software.
   
   THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND,
   EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
   MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.
   IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY
   CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT,
   TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
   SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.



   Code Examples
   ----------------
   # general usage
   $sql = new litemysql('host', 'username', 'password', 'database', 'table', 'encoding');
   $rows = $sql->find_all();
   ----------------
   # config.php file
   <?php
   $database_settings = array(
   	'host' => 'localhost',
   	'username' => 'user',
   	'password' => 'pass',
   	'database' => 'database',
   	'table' => 'table',
   	'encoding' => 'utf8',
   	'primary_key' => 'user_id',
   	'persistent' => false,
   );
   ?>
   ----------------
   # using a config file
   $sql = new litemysql('config.php');
   ----------------
   # using and overriding a config file
   $sql = new litemysql('config.php', null, null, 'my_database', 'my_table');
   ----------------
   # conditions
   # - the following three uses of the find function all produce identical results
   $result = $sql->find(3);
   $result = $sql->find(array('id' => 3));
   $result = $sql->find('`id` = 3');
   ----------------
   # custom primary keys
   # - change the default column name used by the condition builder
   $sql->primary_key = 'user_id';
   $result = $sql->find(3);
   # creates a "`user_id` = 3" WHERE clause in the query
   ----------------
   # insert a single row
   $sql->insert(
   	array(
   		'title' => 'hello world',
   		'body' => 'my first blog post :D',
   		'author' => 'John Doe'
   	)
   );
   ----------------
   # insert multiple rows
   $sql->insert(
   	array(
   		array(
   			'title' => 'hello world',
   			'body' => 'my first blog post :D',
   			'author' => 'John Doe',
   		),
   		array(
   			'title' => 'omg omg!',
   			'body' => 'i r uber cool!',
   			'author' => 'Rupert McIdiot',
   		),
   	)
   );
   ----------------
   # update a single row
   # - uses the same conditions format as the find functions
   $sql->update(4, array('author' => 'John Smith'));
   ----------------
   # update multiple rows (change author "John Doe" to "John Smith")
   $sql->update_all(array('author' => 'John Doe'), array('author' => 'John Smith'));
   ----------------
   # get 15 rows selected by random WHERE `author` = 'John Smith'
   $result = $sql->random(15, array('author' => 'John Smith'));
   ----------------
   # delete a single row WHERE `id` = '4'
   $sql->delete(4);
   ----------------
   # delete all rows WHERE `author` = 'John Smith'
   $sql->delete_all(array('author' => 'John Smith'));
   ----------------
   # optimize the table
   $sql->optimize();
   ----------------
   # truncate a table - WARNING!!! THIS WILL REMOVE ALL RECORDS IN THE TABLE!!!
   $sql->truncate(true);
   ----------------

*/


class LiteMySQL {
	
	public
	
	/*
	   Configuration
	   - set these options with $object->var_name = 'value';
	*/
	
		$host = null,
		$username = null,
		$password = null,
		$database = null,
		$table = null,
		$persistent = false,
		$primary_key = 'id',
		$encoding = 'utf8',
		$config_var = 'database_settings',
	
		
	/*
	   Internal variables
	*/
		
		$connected = false,
		$selected_database = null,
	
		$last_insert_id = null,
		
		$enable_logging = false,
		$query_log = array(),
	
		$connection_id = null,
		$resource = null;
	
	public static
		$resources = array();
	
	
	
	/**
	 * Constructor
	 * @param   host/file   server host or configuration file
	 * @param   username    mysql username
	 * @param   password    mysql password
	 * @param   database    database to select
	 * @param   table       table to use
	 * @param   encoding    the encoding type to use (defaults is utf8)
	 * @return  nothing
	 */
	function __construct () {
		$args = func_get_args();

		// determine if first arument points to a configuration file or is a hostname
		if ( isset($args[0]) && $args[0] != '' && $args[0] !== null ) {
			if ( is_file($args[0]) ) {
				$this->load_settings($args[0]);
			} else {
				$this->host = $args[0];
			}
			if ( !empty($this->host) ) {
				// set or override username and password settings
				if ( !empty($args[1]) ) $this->username = $args[1];
				if ( !empty($args[2]) ) $this->password = $args[2];

				// connect with determined settings
				if ( $this->connect() ) {
					// set or override database and table settings
					if ( !empty($args[3]) ) $this->select_db($args[3]);
					if ( !empty($args[4]) ) $this->select_table($args[4]);
				}
			}
		}
	}
	
	/**
	 * Internal function
	 */
	function __get ($key) {
		if ( $key == 'columns' && (!property_exists($this, $key) || !is_array($this->columns)) ) {
			$this->get_columns();
			return $this->columns;
		} else {
			return $this->$key;
		}
	}
	
	
	
	
	// ==============================================
	// ----- [ Server & Database ] ------------------
	// ==============================================
	
	/**
	 * Connect to a MySQL server and/or select database and/or table
	 * @param   host/file   server host or configuration file
	 * @param   username    mysql username
	 * @param   password    mysql password
	 * @param   database    database to select
	 * @param   table       table to use
	 * @param   encoding    the encoding type to use (defaults is utf8)
	 * @return  nothing
	 */
	function connect ($host = null, $username = null, $password = null, $database = null, $table = null, $encoding = null) {
		if ( $host !== null ) $this->host = $host;
		if ( $username !== null ) $this->username = $username;
		if ( $password !== null ) $this->password = $password;
		if ( $database !== null ) $this->database = $database;
		
		if ( $this->host !== null ) {
			
			$this->connection_id = $this->username.':'.$this->password.'@'.$this->host;
			if ( $this->persistent ) {
				$this->connection_id .= '?persistent';
			}
			
			if ( !array_key_exists($this->connection_id, self::$resources) ) {

				$connect_settings = array($this->host, $this->username, $this->password);
				$connect_function = 'mysql_connect';
				if ( !$this->persistent ) {
					$connect_settings[] = true;
				} else {	
					$connect_function = 'mysql_pconnect';
				}
				
				$this->resource = call_user_func_array($connect_function, $connect_settings);
				if ( $this->resource !== false ) {
					$this->connected = true;
					if ( $this->database !== null ) $this->select_db($this->database);
					if ( $table !== null ) $this->select_table($table);
					$this->set_encoding($encoding);
					//TODO fix table-name conflicts with the database resource name
					// self::$resources[$this->connection_id] = &$this->resource;
					return true;
				}
			} else {
				$this->resource = &self::$resources[$this->connection_id];
				if ( $this->resource !== false ) {
					$this->connected = true;
					if ( $this->database !== null ) $this->select_db($this->database);
					if ( $table !== null ) $this->select_table($table);
					return true;
				}
			}
	
		}
		
		return false;
	}
	
	
	/**
	 * Select database
	 * @param   database   the database to select
	 * @return  true or false
	 */
	function select_db ($database = null) {
		if ( !empty($database) ) $this->database = $database;
		if ( $this->database != $this->selected_database ) {
			if ( mysql_select_db($this->database, $this->resource) ) {
				$this->selected_database = $this->database;
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Select table
	 * @param   table   the table to select
	 * @return  nothing
	 */
	function select_table ($table = null) {
		if ( $table !== null ) $this->table = $table;
		if ( property_exists($this, "columns") ) {
			unset($this->columns);
		}
	}
	
	/**
	 * Set connection encoding
	 * @param   encoding   the encoding type to use (defaults is utf8)
	 * @return  nothing
	 */
	function set_encoding ($encoding = null) {
		if ( !empty($encoding) ) $this->encoding = $encoding;
		$this->query("SET NAMES '".$this->encoding."';");
	}
	
	
	/**
	 * Load settings from file
	 * @param   input      filename of config file, or an array with settings
	 * @param   variable   the variable name to load the array from when loading a config file
	 * @return  true or false
	 */
	function load_settings ($input = null, $variable = null) {
		if ( is_array($input) && count($input) ) {
			$properties = array('host', 'username', 'password', 'database', 'table', 'persistent', 'encoding');
			foreach( $properties as $value ) {
				if ( array_key_exists($value, $input) && $input[$value] != '' ) $this->$value = $input[$value];
			}
			return true;
		} elseif ( is_string($input) && $input !== null && is_file($input) ) {
			if ( $variable === null ) $variable = $this->config_var;
			
			include($input);
			$settings = &$$variable;
			
			$properties = array('host', 'username', 'password', 'database', 'table', 'persistent');
			foreach( $properties as $value ) {
				if ( array_key_exists($value, $settings) ) $this->$value = $settings[$value];
			}
			return true;
		}
		return false;
	}
	
	
	
	
	// ==============================================
	// ----- [ Data Manipulation ] ------------------
	// ==============================================
	
	/**
	 * Find a single row which matches specified conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  array with result row or false
	 */
	function find ($conditions = null, $options = array()) {
		if ( !array_key_exists('limit', $options) ) {
			$options['limit'] = 1;
		}
		$sql = $this->build_find_query($conditions, $options);
		$result = $this->query($sql);
		if ( $result && $row = mysql_fetch_assoc($result) ) {
			return $row;
		}
		return false;
	}
	
	/**
	 * Find multiple rows which match specified conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @param   index        row value to use as array key for each row
	 * @return  array with result rows or null
	 */
	function find_all ($conditions = null, $options = array(), $index = null) {
		$sql = $this->build_find_query($conditions, $options);
		
		$result = $this->query($sql);
		$return = array();
		if ( mysql_num_rows($result) > 0 ) {
			while ($row = mysql_fetch_assoc($result)) {
				if ( $index !== null && isset($row[$index]) ) {
					if ( !isset($return[$row[$index]]) ) {
						$return[$row[$index]] = $row;
					} else {
						for ( $i=1; isset($return[$row[$index].'_'.$i]); $i++ ) { }
						$return[$row[$index].'_'.$i] = $row;
					}
				} else {
					$return[] = $row;
				}
			}
		}	
		return $return;
	}
	
	/**
	 * Insert rows
	 * @param   input   array with input values
	 * @return  true or false
	 */
	function insert ($input = null) {
		$sql = $this->build_insert_query($input);
		$result = ( $sql !== false ) ? $this->query($sql) : false ;
		$this->last_insert_id = mysql_insert_id($this->resource);
		return $result;
	}
	
	/**
	 * Update a single row matching specified conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   input        array with input values
	 * @param   options      additional options to pass in the query
	 * @return  true or false
	 */
	function update ($conditions = null, $input = array(), $options = array()) {
		if ( !array_key_exists('limit', $options) ) {
			$options['limit'] = 1;
		}
		return $this->update_all($conditions, $input, $options);
	}
	
	/**
	 * Update one or more row matching specified conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   input        array with input values
	 * @param   options      additional options to pass in the query
	 * @return  true or false
	 */
	function update_all ($conditions = null, $input = array(), $options = array()) {
		$sql = $this->build_update_query($conditions, $input, $options);
		return ( $sql !== false ) ? $this->query($sql) : false ;
	}
	
	/**
	 * Delete a single row matching specified conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  true or false
	 */
	function delete ($conditions = null, $options = array()) {
		if ( !array_key_exists('limit', $options) ) {
			$options['limit'] = 1;
		}
		return $this->delete_all($conditions, $options);
	}
	
	/**
	 * Delete multiple rows matching specified conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  true or false
	 */
	function delete_all ($conditions = null, $options = array()) {
		$sql = $this->build_delete_query($conditions, $options);
		return $this->query($sql);
	}
	
	/**
	 * Fetch one or more rows selected by random
	 * @param   limit        how many rows to fetch
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @return  array with result rows or false
	 */
	function random ($limit = null, $conditions = null) {
		$options = array();
		if ( $limit !== null ) {
			$options['limit'] = $limit;
		}
		$options['order_by'] = 'RAND()';
		return $this->find_all($conditions, $options);
	}
	
	/**
	* Increment an integer column by count
	* @param   conditions   conditions to match, accepted input is string, int, or array
	* @param   column       the name of the column to increment
	* @param   count        how much to increment column with
	* @param   options      additional options to pass in the query
	* @return  true or false
	*/
	function increment ($conditions, $column, $count = 1, $options = array()) {
		if ( !array_key_exists('limit', $options) ) {
			$options['limit'] = 1;
		}
 		if ( $count == null ) {
			$count = 1;
		}
		$sql = $this->build_increment_query($conditions, $column, $count, $options);
		return ( $sql !== false ) ? $this->query($sql) : false ;
	}
	
	/**
	 * Count number of matching rows to specified conditions and options
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  integer with row count or null
	 */
	function count ($conditions = null, $options = '') {
		$query = 'SELECT ';
		if ( is_array($options) ) {
			if ( !empty($options['select']) ) $query .= $options['select'].', ';
			$query .= 'COUNT(*) FROM `'.$this->table.'`';
			if ( !empty($conditions) ) $query .= $this->build_query_conditions($conditions);
			if ( !empty($options['group_by']) ) $query .= ' GROUP BY '.$options['group_by'];
			$query .= ';';
		} else {	
			$query .= 'COUNT(*) FROM `'.$this->table.'`';
			if ( !empty($conditions) ) {
				$query .= $this->build_query_conditions($conditions);
			}
			$query .= ';';
		}
		if ( $result = $this->query($query) ) {
			$count = mysql_fetch_assoc($result);
			return (int)$count['COUNT(*)'];
		}
		return null;
	}

	
	
	
// ==============================================
// ---- [ Advanced ] ----------------------------
// ==============================================

	/**
	 * Send SQL query
	 * @param   query      SQL query to send
	 * @param   resource   connection resource to use
	 * @return  true or false
	 */
	function query ($query = null, $resource = null) {
		if ( !empty($query) && is_string($query) ) {
			$result = mysql_query($query, ($resource !== null) ? $resource : $this->resource );
			if ( $this->enable_logging ) $this->query_log[] = $query;
			return $result;
		}
		return false;
	}
	
	/**
	 * Optimize table
	 * @param   no_write_to_binlog   don't write optimize operation to the binary log
	 * @return  true or false
	 */
	function optimize ($no_write_to_binlog = false) {
		$bin = ( $no_write_to_binlog ) ? 'NO_WRITE_TO_BINLOG ' : '';
		return $this->query('OPTIMIZE '.$bin.'TABLE '.$this->table);
	}
	
	/**
	 * Truncate table - WARNING!!! THIS WILL REMOVE ALL RECORDS IN THE TABLE!!
	 * @param   are_you_sure   must be true to run truncation
	 * @return  true or false
	 */
	function truncate ($are_you_sure = false) {
		if ( $are_you_sure ) {
			return $this->query('TRUNCATE TABLE '.$this->table);
		}
	}
	
	
	
	
	// ==============================================
	// ----- [ Internal Functions ] -------------
	// ==============================================
	
	/*
	   Build Query functions
	*/
	
	/**
	 * Build find query - used by find methods
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  SELECT query string 
	 */
	function build_find_query ($conditions = null, $options = array()) {

		$select = ( isset($options['select']) && $options['select'] != '' ) ? $options['select'] : '*' ;
		$query = 'SELECT '.$select.' FROM `'.$this->table.'`';
		
		if ( !empty($options['joins']) ) {
			$query .= ' '.$options['joins'];
			unset($options['joins']);
		}
		$query .= $this->build_query_conditions($conditions);
		$query .= $this->build_query_options($options);

		return $query.';';
	}
	
	/**
	 * Build delete query - used by delete methods
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  SELECT query string 
	 */
	function build_delete_query ($conditions = null, $options = array()) {

		$query = 'DELETE FROM `'.$this->table.'`';

		$query .= $this->build_query_conditions($conditions, $options);
		$query .= $this->build_query_options($options);

		return $query.';';
	}
	
	/**
	 * Build insert query - used by insert method
	 * @param   input   array with input values
	 * @return  true or false
	 */
	function build_insert_query ($input = array()) {

		if ( is_array($input) && count($input) ) {
			if ( $this->columns === null ) {
				$this->get_columns();
			}
			if ( !is_array(current($input)) ) {
				$input = array($input);
			}
		} else {
			return false;
		}

		// start building the query
		$query = 'INSERT INTO `'.$this->table.'` ( ';

		// field/column definition
		$columns = array_keys($this->columns);
		$query .= '`'.implode('` , `', $columns).'` )';

		$query .= ' VALUES ';
		$value_sets = array();
		foreach( $input as $current ) {
			$values = array();
			foreach( $columns as $column ) {
				if ( is_array($current) && isset($current[$column]) && $current[$column] !== null ) {
					$values[] = $this->sql_quote($current[$column]);
				} elseif ( strstr($this->columns[$column]['Extra'], 'auto_increment') !== false ) {
					$values[] = 'NULL';
				} else {
					$values[] = ( $this->columns[$column]['Null'] == 'YES' ) ? 'NULL' : "''" ;
				}
			}
			$value_sets[] = "( ".implode(" , ", $values)." )";
		}	
		$query .= implode(', ', $value_sets).';';

		return $query;
	}
	
	/**
	 * Build update query - used by update methods
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   input        array with input values
	 * @param   options      additional options to pass in the query
	 * @return  true or false
	 */
	function build_update_query ($conditions = null, $input = array(), $options = array()) {
		
		$conditions = $this->build_query_conditions($conditions, $options);

		if ( $conditions != '' ) {
			if ( is_array($input) && count($input) ) {
				if ( $this->columns === null ) $this->get_columns();
				$columns = array_keys($this->columns);
				$values = array();
				foreach( $columns as $key ) {
					if ( $key != 'id' && isset($input[$key]) && isset($this->columns[$key]) ) {
						if ( $input[$key] !== null ) {
							$values[] = '`'.$key."` = ".$this->sql_quote($input[$key]);
						} else {
							$values[] = '`'.$key."` = " . ( $this->columns[$key]['Null'] == 'YES' ) ? 'NULL' : "''" ;
						}
					}
				}
				if ( count($values) ) {
					$limit = (!empty($options['limit'])) ? " LIMIT ".$options['limit'] : '' ;
					return 'UPDATE `'.$this->table.'` SET '.implode(', ', $values).$conditions.$limit.";";
				}
			}
		}
		return false;
	}
	
	/**
	* Build increment query - used by increment method
	* @param   conditions   conditions to match, accepted input is string, int, or array
	* @param   column       the name of the column to increment
	* @param   count        how much to increment column with
	* @param   options      additional options to pass in the query
	* @return  true or false
	*/
	function build_increment_query ($conditions, $column, $count = 1, $options = array()) {
		if ( $column != "" ) {
			$conditions = $this->build_query_conditions($conditions, $options);
			if ( array_key_exists($column, $this->columns) ) {
				if ( substr($this->columns[$column]['Type'], 0, 3) == "int" ) {
					$limit = (!empty($options['limit'])) ? " LIMIT ".$options['limit'] : '' ;
					$count = (is_numeric($count) || is_int($count)) ? $count : 1 ;
					return 'UPDATE `'.$this->table.'` SET `'.$column.'` = `'.$column.'` + '.$count.' '.$conditions.$limit.";";
				}
			}
		}
		return false;
	}
	
	
	
	
	/*
	   Build Helpers - help the build functions with repeatative tasks
	*/

	/**
	 * Build query conditions
	 * @param   conditions   conditions to match, accepted input is string, int, or array
	 * @param   options      additional options to pass in the query
	 * @return  "WHERE" SQL statement
	 */
	function build_query_conditions ($conditions, $options = array()) {
		if ( empty($conditions) && $conditions !== 0 && $conditions !== '0' ) {
			return '';
		}
		if ( is_string($conditions) || is_int($conditions) || is_float($conditions) ) {
			if ( preg_match('/^[0-9]+$/', trim($conditions)) ) {
				return " WHERE `".$this->primary_key."` = ".$conditions;
			} else {
 				return ' WHERE '.$conditions;
			}
		} elseif ( is_array($conditions) && !empty($conditions) ) {
			$cond = array();
			foreach( $conditions as $key => $value ) {
				if ( is_array($value) ) {
					$cond[] = '`'.$key."` IN (".implode(",", $this->sql_quote($value)).")";
				} elseif ( !preg_match('/^[0-9]+$/', $key) ) {
					$cond[] = '`'.$key."` = ".$this->sql_quote($value);
				} elseif ( preg_match('/^[0-9]+$/', $value) ) {
					$cond[] = "`.$this->primary_key.` = '".$value."'";
				}
			}
			$operator = ( !empty($options['operator']) ) ? $options['operator'] : 'AND' ;
			return ' WHERE '.implode(' '.$operator.' ', $cond);
		}
		return '';
	}

	/**
	 * Build query options (additional statements)
	 * @param   options      additional options to pass in the query
	 * @return  SQL statements
	 */
	function build_query_options ($options) {
		if ( $options !== null && $options !== '' ) {
			if ( is_string($options) ) {
				return ' '.$options;
			} elseif ( is_array($options) && count($options) ) {
				
				$query = '';
				$query_end = ' ';
				
				if ( !empty($options['group']) ){
					$query .= ' GROUP BY '.$this->sql_quote_options($options['group_by']);
					unset($options['group']);
				}
				
				if ( isset($options['order']) && $options['order'] != '' ) {
					$order_by = trim($this->sql_quote_options($options['order']));
					$order = '';
					if ( preg_match('/^(.*)\s(ASC|DESC)$/i', $order_by, $capture) ) {
						$order = ' '.$capture[2];
						$order_by = trim($capture[1]);
					}
					unset($options['order']);
					if ( $order_by != 'RAND()' && strpos($order_by, '`') === false ) $order_by = '`'.$order_by.'`';
					$query .= ' ORDER BY '.$order_by.$order;
				}
				if ( (isset($options['limit']) && $options['limit'] != '') ) {
					$query_end .= 'LIMIT '.$this->sql_quote_options($options['limit']);
					if ( (isset($options['offset']) && $options['offset'] != '') ) {
						$query_end .= ' OFFSET '.$this->sql_quote_options($options['offset']);
						unset($options['offset']);
					}
					unset($options['limit']);
				}
				foreach( $options as $key => $value ) {
					if ($key != 'operator' && $key != 'select' && $value != '') {
						$query .= ' '.strtoupper($key).' '.$this->sql_quote_options($value);
					}
				}
				return $query.$query_end;
			}
		}	
		return '';
	}
	
	
	
	
	/*
	   Internal Functions
	*/	

	/**
	 * Escape special characters as needed in input data
	 * @param   string   data to escape
	 * @param   field    column name
	 * @return  escaped string which is safe for SQL injection
	 */
	function sql_quote ($input, $column = null) {
		if ( is_array($input) ) {
			$return = array();
			foreach( $input as $key => $value ) {
				$return[$key] = $this->sql_quote($value, $column);
			}
			return $return;
		}
		if ( $column !== null ) {
			$column = $this->get_column_type($column);
		}
		if ( ($column == 'integer' || $column == 'float') && preg_match('/^[0-9\-\.]+$/', $input) ) {
			return $input;
		} elseif ( preg_match('/^[0-9\-\.]+$/', $input) ) {
			return $input;
		} else {
			return "'".addslashes($input)."'";
		}
	}
	
	/**
	 * Escape special characters in SQL statement values
	 * @param   string   data to escape
	 * @return  safe statement value
	 */
	function sql_quote_options ($string) {
		$split = explode(";", $string, 2);
		return $split[0];
	}

	/**
	 * Get column type
	 * @param   column   column name to check
	 * @return  string with column type, or false on failure
	 */
	function get_column_type ($column) {
		if ( $this->columns === null ) {
			$this->get_columns();
		}
		if ( isset($this->columns[$column]['Type']) && $this->columns[$column]['Type'] != '' ) {
			$type = strtolower($this->columns[$column]['Type']);
			if ( strpos($type, '(') !== false ) {
				$type = substr($type, 0, strpos($type, '('));
			}
			switch ( $type ) {
				case 'tinyint':
				case 'smallint':
				case 'mediumint':
				case 'int':
				case 'bigint':
					$type = 'integer';
					break;

				case 'float':
				case 'double':
					$type = 'decimal';
					break;

				default:
					$type = 'string';
			}
			return $type;
		}
		return false;
	}
	
	/**
	 * Get list of columns in table
	 * @return  nothing
	 */
	function get_columns () {
		if ( $this->connected && $this->selected_database != null && $this->table != null ) {
			$this->columns = null;
			$sql = 'SHOW COLUMNS FROM `'.$this->table.'`;';
			$result = $this->query($sql);
			if ( mysql_num_rows($result) > 0 ) {
				$this->columns = array();
				while ($row = mysql_fetch_assoc($result)) {
					$this->columns[$row['Field']] = $row;
				}
			}
		}
	}
	
}


?>