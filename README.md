# LiteMySQL

A very light-weight and simple ORM-like MySQL library for PHP. Kind of like ActiveRecord's little brother.


## Quick-start

    # initialize and connect to database
    $sql = new litemysql('host', 'username', 'password', 'database', 'table', 'encoding');

    # fetch all rows
    $rows = $sql->find_all();
    
    # insert a single row
    $sql->insert(array('title' => 'hello', 'body' => 'Dude, sweet!', 'author' => 'John Doe'));
    
    # fetch last inserted row
    $sql->find($sql->last_insert_id);


### Config File

You can place all your MySQL settings in a file like `config.php` bellow:

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

Then use the config file:

    $sql = new litemysql('config.php');

And you can also override any option specified in the config file:

    $sql = new litemysql('config.php', null, null, 'my_database', 'my_table');


## Input Arguments


### Conditions

Conditions can be specified in three ways:

* As an **integer**, which is used as the primary_key value to find a single row.
* As an **array**, which uses the array keys as columns and array values as column values.
    * If one of the values in the array, is an array itself, the value is specified using `IN` in the SQL query.
* As a **string**, which is used as a custom raw SQL where statement. Use with care as it's not filtered for possible SQL injections.

#### Examples:

    $result = $sql->find( 3 );
    $result = $sql->find( array('id' => 3) );
    $result = $sql->find( '`id` = 3' );
    $result = $sql->find_all( array('id' => array(1,3,5)) );


### Options

Using the `$options` argument, you can specify `LIMIT`, `OFFSET`, `ORDER BY`, and `GROUP BY` options for the query. They key names used in the array are `limit`, `offset`, `order`, and `group`.

#### Example:

    $result = $sql->find_all(null, array('order' => 'title'));


## Methods


### `find( $conditions, $options = array() )`

Retrieves a single record matching `$conditions`, or `false` if no record is found.


### `find_all( $conditions, $options = array() )`

Works just like `find()`, but retrieves all matching rows. Can be limited with the `limit` option. Returns an empty array if no records matched the specified conditions.


### `insert( $input )`

Takes an array as input. To insert a single record, the array should have keys matching the columns. To insert multiple records, `$input` must be an array with arrays containing column keys.

#### Examples:

Insert a single record:

    $sql->insert(
       array(
          'title' => 'hello world',
          'body' => 'my first blog post :D',
          'author' => 'John Doe'
       )
    );

Insert multiple records:

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


### `update( $condtions, $input = array(), $options = array() )`

Updates a single record matching `$conditions`, with the same input format as `insert()` takes for a single record insert.

#### Example:

    $sql->update(4, array('author' => 'James Dole'));


### `update_all( $condtions, $input = array(), $options = array() )`

Works just like `update()`, but it updates all records matching `$conditions`.

#### Example:

Change author "John Doe" to "John Smith".

    $sql->update_all(array('author' => 'John Doe'), array('author' => 'John Smith'));


### `delete( $conditions, $options = array() )`

Deletes a single record matching `$conditions`.

#### Example:

    $sql->delete(4);


### `delete_all( $conditions, $options = array() )`

Deletes all records matching `$conditions`.

#### Example:

    $sql->delete_all(array('author' => 'John Smith'));


### `count( $conditions, $options = array() )`

Counts how many records match `$conditions`.

#### Example:

    $result = $sql->count(array('author' => 'John Smith'));


### `random( $limit = null, $conditions = null )`

Fetches X number of records from table sorted by random matching `$conditions`. Use `$limit` to specify how many records to retrieve. Be warned that this method uses `ORDER BY RAND()` which can be very slow on large tables.


### `increment( $conditions, $column, $count = 1, $options = array() )`

Increment a integer column by `$count` on a single record matching `$conditions`. `$count` defaults to `1` if not specified.

To decrement instead of increment, simply specify a negative `$count` value.

#### Example:

    $sql->increment(array('slug' => 'hello-world'), 'views');


### `query( $query )`

Perform a SQL query.


### `optimize()`

Performs an `OPTIMIZE` query on the current table.


### `truncate( $are_you_sure = false )`

Truncates the table if `$are_you_sure` is true. **WARNING**: This removes ALL records from the current table.

## LiteMySQL Object Properties

### Primary Key

Change the default column name used by the condition builder.

    $sql->primary_key = 'user_id';
    $result = $sql->find(3);

This creates a `user_id = 3` WHERE clause in the query instead of the default `id = 3`.


### Last Insert ID

Contains the primary_key value of the last inserted record.

    $sql->last_insert_id;


### Query Log

If `$sql->enable_logging` is set to true, all queries are logged to `$sql->query_log`.


## To-do

* Create some kind of basic tests for all the features.
* Restructure and rewrite this ReadMe so it doesn't suck.
* Come up with more to-do's for this list.


## License

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

