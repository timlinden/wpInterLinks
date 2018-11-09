<?php
/*
Plugin Name: wpInterLinks
Plugin URI:  http://www.timlinden.com/wordpress-plugins/wpinterlinks/
Description: Automatically link keywords in post content
Author:      Tim Linden
Version:     1.0.0
Author URI:  http://www.timlinden.com
License:     GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt
*/

global $wpil_db_version;
$wpil_db_version = '1.0';


function wpil_save($id) {
    global $wpdb;

    $nonce = esc_attr( $_REQUEST['_wpnonce'] );
    if (!wp_verify_nonce( $nonce, 'wpil_save_keyword' )) { return; }

    $link = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpinterlinks WHERE ID='$id'");
    if ($link->type == 'auto') {

        $wpdb->update(
            "{$wpdb->prefix}wpinterlinks",
            array(
                'keyword' => $_POST["keyword"],
                'uoption' => $_POST["uoption"],
                'uignore' => $_POST["uignore"]
            ),
            array( 'ID' => $id )
            );

    } else {
        $wpdb->update(
            "{$wpdb->prefix}wpinterlinks",
            array(
                'keyword' => $_POST["keyword"],
                'url' => $_POST["url"]
            ),
            array( 'ID' => $id )
            );

    }
    echo "<strong>Keyword updated</strong><p>";
}

function wpil_edit($id) {
    global $wpdb;

    $link = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpinterlinks WHERE ID='$id'");




    if ($link->type == 'auto') {  ?>
	    
	    
	    
	    <div class="wrap" id="add">
		<h2>Add Auto Link</h2>
		<p>Auto keywords will automatically pick blog posts to link to based on the keyword. Posts that use the keyword in the Title and Body more will be prioritized. As you add, edit, or remove posts
		the keyword links will automatically point to the best post for the keyword.</p>
		<form method="POST">
			<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
			<table width="100%">
				<tr>
					<th align="right" width="100">Keyword:</th>
					<td><input type="text" name="keyword" value="<?php echo stripslashes($link->keyword) ?>" style="width: 75%" id="old"/> <i>Only one keyword/phrase per auto link.</i></td>
				</tr>
				<tr>
					<th align="right" width="100">Option:</th>
					<td><select name="uoption" size="1"><option value="">Automatically link to the most relevant blog post</option><option value="latest" <?php if ($link->uoption == "latest") { echo "SELECTED"; } ?>>Automatically link to the most recently posted blog post</select></td>
				</tr>
				<tr>
					<th align="right" width="100">Ignore:</th>
					<td><input type="text" name="uignore" value="<?php echo stripslashes($link->uignore) ?>" style="width: 75%" id="uignore"/> <i>Comma separated list of categorie slugs to ignore for selection</i></td>
				</tr>
				<tr>
					<th></th>
					<td>
						<input class="button-primary" type="submit" name="add" value="Save Keyword" id="submit"/>
						<input type="hidden" name="id" value="<?php echo $id ?>">
						<input type="hidden" name="action" value="savekeyword"/>
						<?php wp_nonce_field( 'wpil_save_keyword' ) ?>
					</td>
				</tr>
			</table>
		</form>
		</div>
	    
	    
	    
	    <?php } else { ?>
	    <div class="wrap" id="add">
		<h2>Add Manual Link</h2>
		<form method="POST">
			<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
			<table width="100%">
				<tr>
					<th align="right" width="100">Keyword:</th>
					<td><input type="text" name="keyword" value="<?php echo stripslashes($link->keyword) ?>" style="width: 75%" id="old"/> <i>For multiple keywords separate with a comma</i></td>
				</tr>
				<tr>
					<th align="right" width="100">URL:</th>
					<td><input type="text" name="url" value="<?php echo stripslashes($link->url) ?>" style="width: 95%" id="old"/></td>
				</tr>
				<tr>
					<th></th>
					<td>
						<input class="button-primary" type="submit" name="add" value="Save Keyword" id="submit"/>
						<input type="hidden" name="id" value="<?php echo $id ?>">
						<input type="hidden" name="action" value="savekeyword"/>
						<?php wp_nonce_field( 'wpil_save_keyword' ) ?>
					</td>
				</tr>
			</table>
		</form>
		</div>
	    
	    <?php
		}
}

function wpil_autofill() {
	global $wpdb;
	$IDS = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}wpinterlinks WHERE type='auto' AND (url='' OR url='#' OR URL IS NULL)");
	foreach ($IDS as $ID) {
	wpil_calculate($ID->ID);
	}
}

function wpil_auto() {
	global $wpdb;
	$ID = $wpdb->get_var("SELECT ID FROM {$wpdb->prefix}wpinterlinks WHERE type='auto' ORDER BY updated ASC LIMIT 1");
	
	wpil_calculate($ID);
}

function wpil_calculate($id) {
	global $wpdb;
	
	$keyword = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}wpinterlinks WHERE ID='$id'");
	
	if ($keyword->keyword == "") { return; }
	
	if ($keyword->uignore) {
		if (strstr($keyword->uignore, ",")) {
			$uignore = explode(",", $keyword->uignore);
		} else {
			$uignore[] = $keyword->uignore;
		}
		
		$cats = $wpdb->get_var("SELECT GROUP_CONCAT(t.term_id) FROM wp_terms AS t INNER JOIN wp_term_taxonomy AS tt ON t.term_id = tt.term_id WHERE tt.taxonomy = 'category' AND t.slug  IN ('".implode("','", $uignore)."')");

		$uignoresql = " AND ID NOT IN (
              SELECT tr.object_id FROM wp_term_relationships 
				AS tr INNER JOIN wp_term_taxonomy AS tt 
				ON tr.term_taxonomy_id = tt.term_taxonomy_id 
				WHERE tt.taxonomy = 'category'
				 AND tt.term_id IN ($cats)
            ) ";
	} else {
		$uignoresql = "";
	}
	
	if ($keyword->uoption == "latest") {
		$ORDER = " ORDER BY post_date DESC ";
	} else {
		$ORDER = " ORDER BY counttt DESC, post_date DESC ";
	}
	
	$query = "SELECT 
    ID,
    ROUND (   
        (
            CHAR_LENGTH(post_title)
            - CHAR_LENGTH( REPLACE ( post_title, \"{$keyword->keyword}\", \"\") ) 
        ) / CHAR_LENGTH(\"{$keyword->keyword}\")        
    ) * 5 + ROUND (   
        (
            CHAR_LENGTH( post_name )
            - CHAR_LENGTH( REPLACE ( post_name , \"{$keyword->keyword}\", \"\") ) 
        ) / CHAR_LENGTH(\"{$keyword->keyword}\")        
    ) * 3 + LEAST(ROUND (   
        (
            CHAR_LENGTH( post_content )
            - CHAR_LENGTH( REPLACE ( post_content , \"{$keyword->keyword}\", \"\") ) 
        ) / CHAR_LENGTH(\"{$keyword->keyword}\")      
    ),3)  AS counttt
FROM {$wpdb->prefix}posts WHERE post_status = 'publish' HAVING counttt > 0 $uignoresql $ORDER LIMIT 1";
	
	#echo $query;
	
		$bestmatch = $wpdb->get_row($query);
		
		#var_dump($bestmatch);
		
		#echo $wpdb->last_error;
		if ($bestmatch) {
			
			$wpdb->update(
					"{$wpdb->prefix}wpinterlinks",
					array(
							'url' => get_permalink($bestmatch->ID)
					),
					array( 'ID' => $id )
			);
			#echo $wpdb->last_error;
		} else {
			$wpdb->update(
					"{$wpdb->prefix}wpinterlinks",
					array(
							'url' => '#'
					),
					array( 'ID' => $id )
					);
			
		}
		
		
}





function wpil_sort($a,$b){
	return strlen($b)-strlen($a);
}




function wpil_hourly() {
	wpil_auto();
}



function wpil_content($content) {
	global $wpdb, $post, $ranalready;
	
	$permalink = get_permalink();

	$links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpinterlinks WHERE keyword!='' AND url!='#' AND url IS NOT NULL AND url!='$permalink'");

	/** Loop through each keyphrase, looking for each one in the post */
	foreach ($links as $link) {
		
		if (strstr($link->keyword, ",")) {
			$keywords = explode(",", $link->keyword);
			
			
			
			usort($keywords,'wpil_sort');
		} else {
			$keywords = array($link->keyword);
		}
		

		foreach ($keywords as $keyword) {
			if (!preg_match('/'.$keyword.'/i', $content)) {
				continue;
			}
			
			if ($link->type == "auto") { $target = ""; }
			else { $target="target=\"_blank\""; }
			
			##
			
			$regex = '~<strong>[^<]*</strong>|<a[^>]+>[^<]*</a>|<img[^>]+>|<a[^>]+>|( )(\b'. $keyword . '\b)( |\.|,)~si';
			
			$content = preg_replace_callback(
					$regex,
					function($m) use ($link) { if(empty($m[1])) return $m[0];
					else return $m[1]."<a href=\"".$link->url."\" class=\"wpinterlink\" ".$target.">".$m[2]."</a>".$m[3];},
					$content);
		}
	}

	
	return $content;
}

function wpil_savedpost( $post_id ) {
	global $wpdb;
	
	$links = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}wpinterlinks WHERE type='auto'");
	
	foreach ($links as $link) {
	
		if (strstr($link->keyword, ",")) {
			$keywords = explode(",", $link->keyword);
		} else {
			$keywords = array($link->keyword);
		}
	
		foreach ($keywords as $keyword) {
			$query = "SELECT ID FROM {$wpdb->prefix}posts WHERE ID=$post_id AND (post_content LIKE '%{$keyword}%' OR post_title LIKE '%{$keyword}%')";
			$bestmatch = $wpdb->get_row($query);
			
			if ($bestmatch) {
				$wpdb->update(
						"{$wpdb->prefix}wpinterlinks",
						array(
								'updated' => date('Y-m-d H:i:s')
						),
						array( 'ID' => $links->ID )
						);
			}
			
		}
	}

}

add_action( 'save_post', 'wpil_savedpost' );



if(!class_exists('WP_List_Table')){
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}




/************************** CREATE A PACKAGE CLASS *****************************
 *******************************************************************************
 * Create a new list table package that extends the core WP_List_Table class.
 * WP_List_Table contains most of the framework for generating the table, but we
 * need to define and override some methods so that our data can be displayed
 * exactly the way we need it to be.
 *
 * To display this example on a page, you will first need to instantiate the class,
 * then call $yourInstance->prepare_items() to handle any data manipulation, then
 * finally call $yourInstance->display() to render the table to the page.
 *
 * Our theme for this list table is going to be movies.
 */
class wpil_List_Table extends WP_List_Table {

	


	/** ************************************************************************
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	***************************************************************************/
	function __construct(){
		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
				'singular'  => 'link',     //singular name of the listed records
				'plural'    => 'links',    //plural name of the listed records
				'ajax'      => false        //does this table support ajax?
		) );

	}


	/** ************************************************************************
	 * Recommended. This method is called when the parent class can't find a method
	 * specifically build for a given column. Generally, it's recommended to include
	 * one method for each column you want to render, keeping your package class
	 * neat and organized. For example, if the class needs to process a column
	 * named 'keyword', it would first see if a method named $this->column_title()
	 * exists - if it does, that method will be used. If it doesn't, this one will
	 * be used. Generally, you should try to use custom column methods as much as
	 * possible.
	 *
	 * Since we have defined a column_title() method later on, this method doesn't
	 * need to concern itself with any column with a name of 'title'. Instead, it
	 * needs to handle everything else.
	 *
	 * For more detailed insight into how columns are handled, take a look at
	 * WP_List_Table::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 * @param array $column_name The name/slug of the column to be processed
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default($item, $column_name){
		switch($column_name){
			case 'url':
				return stripslashes($item[$column_name]);
			default:
				return print_r($item,true); //Show the whole array for troubleshooting purposes
		}
	}


	/** ************************************************************************
	 * Recommended. This is a custom column method and is responsible for what
	 * is rendered in any column with a name/slug of 'title'. Every time the class
	 * needs to render a column, it first looks for a method named
	 * column_{$column_title} - if it exists, that method is run. If it doesn't
	 * exist, column_default() is called instead.
	 *
	 * This example also illustrates how to implement rollover actions. Actions
	 * should be an associative array formatted as 'slug'=>'link html' - and you
	 * will need to generate the URLs yourself. You could even ensure the links
	 *
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @param array $item A singular item (one full row's worth of data)
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	
	
	function column_keyword($item){

		
		$delete_nonce = wp_create_nonce( 'wpil_delete_keyword' );
		//Build row actions
		
		if ($_REQUEST["sub"] == "auto") {
			$actions['edit']      = sprintf('<a href="?page=%s&amp;sub=auto&amp;action=%s&amp;wpinterlink=%s">Edit</a>',$_REQUEST['page'],'edit',$item['ID']);
			$actions['refresh']      = sprintf('<a href="?page=%s&amp;sub=auto&amp;action=%s&amp;wpinterlink=%s">Refresh</a>',$_REQUEST['page'],'refresh',$item['ID']);
			$actions['delete']    = sprintf('<a href="?page=%s&amp;sub=auto&amp;action=%s&amp;wpinterlink=%s&amp;_wpnonce=%s">Delete</a>', esc_attr($_REQUEST['page']),'delete',$item['ID'], $delete_nonce);
		} else {
			$actions['edit']      = sprintf('<a href="?page=%s&amp;action=%s&amp;wpinterlink=%s">Edit</a>',$_REQUEST['page'],'edit',$item['ID']);
			$actions['delete']    = sprintf('<a href="?page=%s&amp;action=%s&amp;wpinterlink=%s&amp;_wpnonce=%s">Delete</a>', esc_attr($_REQUEST['page']),'delete',$item['ID'], $delete_nonce);
		}

		
		return $item['keyword'] . $this->row_actions( $actions );
	}


	/** ************************************************************************
	 * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
	 * is given special treatment when columns are processed. It ALWAYS needs to
	 * have it's own method.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @param array $item A singular item (one full row's worth of data)
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/
	function column_cb($item){
		return sprintf(
				'<input type="checkbox" name="wpinterlink[]" value="%1$s" />', $item['ID']
		);
	}


	/** ************************************************************************
	 * REQUIRED! This method dictates the table's columns and titles. This should
	 * return an array where the key is the column slug (and class) and the value
	 * is the column's title text. If you need a checkbox for bulk actions, refer
	 * to the $columns array below.
	 *
	 * The 'cb' column is treated differently than the rest. If including a checkbox
	 * column in your table you must create a column_cb() method. If you don't need
	 * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
	 *
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Keywords'
	 **************************************************************************/
	function get_columns(){
		$columns = array(
				'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
				'keyword'     => 'Keyword',
				'url'    => 'URL',
		);
		return $columns;
	}

	
	/** ************************************************************************
	 * Optional. If you want one or more columns to be sortable (ASC/DESC toggle),
	 * you will need to register it here. This should return an array where the
	 * key is the column that needs to be sortable, and the value is db column to
	 * sort by. Often, the key and value will be the same, but this is not always
	 * the case (as the value is a column name from the database, not the list table).
	 *
	 * This method merely defines which columns should be sortable and makes them
	 * clickable - it does not handle the actual sorting. You still need to detect
	 * the ORDERBY and ORDER querystring variables within prepare_items() and sort
	 * your data accordingly (usually by modifying your query).
	 *
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	function get_sortable_columns() {
		$sortable_columns = array(
				'keyword'     => array('keyword',false),     //true means it's already sorted
				'url'    => array('url',false),
		);
		return $sortable_columns;
	}


	/** ************************************************************************
	 * Optional. If you need to include bulk actions in your list table, this is
	 * the place to define them. Bulk actions are an associative array in the format
	 * 'slug'=>'Visible Keyword'
	 *
	 * If this method returns an empty value, no bulk action will be rendered. If
	 * you specify any bulk actions, the bulk actions box will be rendered with
	 * the table automatically on display().
	 *
	 * Also note that list tables are not automatically wrapped in <form> elements,
	 * so you will need to create those manually in order for bulk actions to function.
	 *
	 * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Keywords'
	 **************************************************************************/
	function get_bulk_actions() {
		$actions = array(
				'bulk-delete'    => 'Delete'
		);
		return $actions;
	}


	/** ************************************************************************
	 * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
	 * For this example package, we will handle it in the class to keep things
	 * clean and organized.
	 *
	 * @see $this->prepare_items()
	 **************************************************************************/
	
	
	public function process_bulk_action() {
		
		
		if ( 'addkeyword' === $this->current_action() ) {
			
			
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );
			
			if ( ! wp_verify_nonce( $nonce, 'wpil_add_keyword' ) ) {
				wp_redirect( admin_url('admin.php?page='.$_REQUEST['page']) );
			}
			else {
				self::add_keyword();

				wp_redirect( admin_url('admin.php?page='.$_REQUEST['page']) );
				exit;
			}
			
			
			
			exit;
		}
	
		//Detect when a bulk action is being triggered...
		if ( 'delete' === $this->current_action() ) {
	
			// In our file that handles the request, verify the nonce.
			$nonce = esc_attr( $_REQUEST['_wpnonce'] );
	
			if ( ! wp_verify_nonce( $nonce, 'wpil_delete_keyword' ) ) {
				die( 'Go get a life script kiddies' );
			}
			else {
				self::delete_keyword( absint( $_GET['wpinterlink'] ) );
	
				wp_redirect( esc_url( add_query_arg() ) );
				exit;
			}
	
		}
	
		// If the delete bulk action is triggered
		if ( ( isset( $_GET['action'] ) && $_GET['action'] == 'bulk-delete' )
				|| ( isset( $_GET['action2'] ) && $_GET['action2'] == 'bulk-delete' )
		) {
	
			$delete_ids = esc_sql( $_GET['wpinterlink'] );
	
			// loop over the array of record IDs and delete them
			foreach ( $delete_ids as $id ) {
				self::delete_keyword( $id );
	
			}
	
			wp_redirect( esc_url( add_query_arg() ) );
			exit;
		}
	}
	
	public static function delete_keyword( $id ) {
		global $wpdb;
	
		$wpdb->delete(
				"{$wpdb->prefix}wpinterlinks",
				[ 'ID' => $id ],
				[ '%d' ]
				);
	}
	
	


	public static function add_keyword() {
		global $wpdb;
		
		if ($_REQUEST["sub"] == "auto") {
			$TYPE = "auto";
		} else {
			$TYPE = "manual";
		}
		
		$wpdb->insert("{$wpdb->prefix}wpinterlinks", array(
				"keyword" => $_POST["keyword"],
				"type" => $TYPE,
				"url" => $_POST["url"],
				"uoption" => $_POST["uoption"],
				"uignore" => $_POST["uignore"]
		));
		
		#echo $wpdb->last_error;
		#exit();
		
		wpil_autofill();
	}
	
	


	/** ************************************************************************
	 * REQUIRED! This is where you prepare your data for display. This method will
	 * usually be used to query the database, sort and filter the data, and generally
	 * get it ready to be displayed. At a minimum, we should set $this->items and
	 * $this->set_pagination_args(), although the following properties and methods
	 * are frequently interacted with here...
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {
		global $wpdb; //This is used only if making any database queries

		/**
		 * First, lets decide how many records per page to show
		 */
		
		$screen = get_current_screen();
		

		$this->process_bulk_action();
		
		
		/* -- Preparing your query -- */
		$query = "SELECT * FROM {$wpdb->prefix}wpinterlinks";
		
		if (!empty($_REQUEST["s"])) {
			$s = mysql_real_escape_string($_REQUEST["s"]);
			$where[] =" keyword LIKE '%{$s}%' ";
		}
		
		if ($_GET['sub'] == "auto") {
			$where[] = " type='auto' ";
		} else {
			$where[] = " type='manual' ";
		}
		
		$query .= " WHERE " . implode(" AND ", $where);
		
		
		
		/* -- Ordering parameters -- */
		//Parameters that are going to be used to order the result
		$orderby = !empty($_GET["orderby"]) ? mysql_real_escape_string($_GET["orderby"]) : 'ASC';
		$order = !empty($_GET["order"]) ? mysql_real_escape_string($_GET["order"]) : '';
		if(!empty($orderby) & !empty($order)){ $query.=' ORDER BY '.$orderby.' '.$order; }
		
		/* -- Pagination parameters -- */
		//Number of elements in your table?
		$totalitems = $wpdb->query($query); //return the total number of affected rows
		//How many to display per page?
		$perpage = 10;
		//Which page is this?
		$paged = !empty($_GET["paged"]) ? mysql_real_escape_string($_GET["paged"]) : '';
		//Page Number
		if(empty($paged) || !is_numeric($paged) || $paged<=0 ){ $paged=1; }
		//How many pages do we have in total?
		$totalpages = ceil($totalitems/$perpage);
		//adjust the query to take pagination into account
		if(!empty($paged) && !empty($perpage)){
			$offset=($paged-1)*$perpage;
			$query.=' LIMIT '.(int)$offset.','.(int)$perpage;
		}
		
		/* -- Register the pagination -- */
		$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page" => $perpage,
				'orderby'   => ! empty( $_REQUEST['orderby'] ) && '' != $_REQUEST['orderby'] ? $_REQUEST['orderby'] : 'keyword',
				'order'     => ! empty( $_REQUEST['order'] ) && '' != $_REQUEST['order'] ? $_REQUEST['order'] : 'asc'
		) );
		
		
		
		//The pagination links are automatically built according to those parameters
		
		/* -- Register the Columns -- */

		
		/* -- Fetch the items -- */
		$this->items = $wpdb->get_results($query, ARRAY_A);

		
		/**
		 * REQUIRED. Now we need to define our column headers. This includes a complete
		 * array of columns to be displayed (slugs & keywords), a list of columns
		 * to keep hidden, and a list of columns that are sortable. Each of these
		 * can be defined in another method (as we've done here) before being
		 * used to build the value for our _column_headers property.
		 */
		$columns = $this->get_columns();
		$hidden = array();
		$sortable = $this->get_sortable_columns();


		/**
		 * REQUIRED. Finally, we build an array to be used by the class for column
		 * headers. The $this->_column_headers property takes an array which contains
		 * 3 other arrays. One for all columns, one for hidden columns, and one
		 * for sortable columns.
		*/
		$this->_column_headers = array($columns, $hidden, $sortable);


	}


}









/** *************************** RENDER TEST PAGE ********************************
 *******************************************************************************
 * This function renders the admin page and the example list table. Although it's
 * possible to call prepare_items() and display() from the constructor, there
 * are often times where you may need to include logic here between those steps,
 * so we've instead called those methods explicitly. It keeps things flexible, and
 * it's the way the list tables are used in the WordPress core.
*/
function wpil_admin_keywords(){
	global $wpdb;
	
	wpil_autofill();
	
	if ($_GET["action"] == "refresh") { wpil_calculate($_GET["wpinterlink"]); }

	//Create an instance of our package class...
	$interTable = new wpil_List_Table();
	//Fetch, prepare, sort, and filter our data...
	$interTable->prepare_items();

	
	
	?>
	
    <div class="wrap">
        
        <div id="icon-users" class="icon32"><br/></div>
        <h2>wpInterLinks</h2>
        <ul class="subsubsub">
			<li>
				<a <?php if ( !isset( $_GET['sub'] ) || $_GET['sub'] == '' ) echo 'class="current"'; ?> href="<?php echo admin_url( 'admin.php?page='.$_REQUEST['page'] ); ?>">
					Manual Links
				</a> |
			</li>
			<li>
				<a <?php if ( isset( $_GET['sub'] ) && $_GET['sub'] == 'auto' ) echo 'class="current"'; ?> href="<?php echo admin_url( 'admin.php?page='.$_REQUEST['page'] ); ?>&amp;sub=auto">
					Auto Links
				</a>
			</li>
		</ul>
		
		<div style="clear:both;"></div>
		<?php 
		
		if ($_POST["action"] == "savekeyword") {
			wpil_save($_POST["id"]);
		}
		
		
		if ($_GET["action"] == "edit") {
			
			wpil_edit($_GET["wpinterlink"]);
			
		} else {
		
		?>

        
        
        <!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
        <form id="movies-filter" method="GET">
            <!-- For plugins, we also need to ensure that the form posts back to our current page -->
            <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
            <!-- Now we can render the completed list table -->
            <?php 

            $interTable->search_box( 'search', 'search_id' );
            $interTable->display();

            ?>
        </form>
        
    
    <?php if ($_GET['sub'] == 'auto') {  ?>
    
    
    
    <div class="wrap" id="add">
	<h2>Add Auto Link</h2>
	<p>Auto keywords will automatically pick blog posts to link to based on the keyword. Posts that use the keyword in the Title and Body more will be prioritized. As you add, edit, or remove posts
	the keyword links will automatically point to the best post for the keyword.</p>
	<form method="POST">
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
		<table width="100%">
			<tr>
				<th align="right" width="100">Keyword:</th>
				<td><input type="text" name="keyword" style="width: 75%" id="old"/> <i>Only one keyword/phrase per auto link.</i></td>
			</tr>
			<tr>
				<th align="right" width="100">Option:</th>
				<td><select name="uoption" size="1"><option value="">Automatically link to the most relevant blog post</option><option value="latest">Automatically link to the most recently posted blog post</select></td>
			</tr>
			<tr>
				<th align="right" width="100">Ignore:</th>
				<td><input type="text" name="uignore" style="width: 75%" id="uignore"/> <i>Comma separated list of categorie slugs to ignore for selection</i></td>
			</tr>
			<tr>
				<th></th>
				<td>
					<input class="button-primary" type="submit" name="add" value="Add Keyword" id="submit"/>
					
					<input type="hidden" name="action" value="addkeyword"/>
					<?php wp_nonce_field( 'wpil_add_keyword' ) ?>
				</td>
			</tr>
		</table>
	</form>
	</div>
    
    
    
    <?php } else { ?>
    <div class="wrap" id="add">
	<h2>Add Manual Link</h2>
	<form method="POST">
		<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>" />
		<table width="100%">
			<tr>
				<th align="right" width="100">Keyword:</th>
				<td><input type="text" name="keyword" style="width: 75%" id="old"/> <i>For multiple keywords separate with a comma</i></td>
			</tr>
			<tr>
				<th align="right" width="100">URL:</th>
				<td><input type="text" name="url" style="width: 95%" id="old"/></td>
			</tr>
			<tr>
				<th></th>
				<td>
					<input class="button-primary" type="submit" name="add" value="Add Keyword" id="submit"/>
					
					<input type="hidden" name="action" value="addkeyword"/>
					<?php wp_nonce_field( 'wpil_add_keyword' ) ?>
				</td>
			</tr>
		</table>
	</form>
	</div>
    
    <?php
	}
	

	 } ?>
	        
	    </div>
	    
	    <?php 
}

$timestamp = wp_next_scheduled( 'wpil_hourly' );
if (!$timestamp) {
	wp_schedule_event(time(), 'hourly', 'wpil_hourly');
}




add_action('wpil_hourly', 'wpil_hourly');
add_filter('the_content', 'wpil_content', 1);

register_activation_hook(__FILE__, 'wpil_install');
register_deactivation_hook( __FILE__, 'wpil_deactivate' );

function wpil_deactivate() {
	$timestamp = wp_next_scheduled( 'wpil_hourly' );
	while ($timestamp) {
		wp_unschedule_event($timestamp, 'wpil_hourly' );
		$timestamp = wp_next_scheduled( 'wpil_hourly' );
	}
}

function wpil_install() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$wpdb->prefix}wpinterlinks (
    ID int(11) NOT NULL AUTO_INCREMENT,
    type enum('manual','auto') NOT NULL,
    keyword varchar(200) DEFAULT NULL,
    url varchar(200) DEFAULT '',
    updated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    uoption varchar(10) DEFAULT '',
    uignore varchar(100) DEFAULT '',
    PRIMARY KEY (ID),
    KEY type (type)
    ) $charset_collate";

    #$wpdb->query($sql);

    #die("error" . $wpdb->last_error . "<Br>$sql");

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );

    add_option( 'wpil_db_version', $wpil_db_version );
}

function wpil_update_db_check() {
    global $wpil_db_version;
    if ( get_site_option( 'wpil_db_version' ) != $wpil_db_version ) {
        wpil_install();
    }
}
add_action( 'plugins_loaded', 'wpil_update_db_check' );


function wpil_setup() {

    add_menu_page(
        "wpInterLinks",             // The text to be displayed in the title tags of the page when the menu is selected
        "wpInterLinks",             // The text to be used for the menu
        "read",                     // The capability required for this menu to be displayed to the user
        "wpinterlinks",             // The slug name to refer to this menu by. Should be unique for this menu page
        "wpil_admin_keywords",      // The function to be called to output the content for this page
        "dashicons-admin-links",    // The URL to the icon to be used for this menu
        "15" // The position in the menu order this one should appear
        );
    
    /*
     * for future expansion
     * 
    $hook_trckme_keywords = add_submenu_page(
        "wpinterlinks",             // The slug name for the parent menu
        "wpInterLinks Keywords",    // The text to be displayed in the title tags of the page when the menu is selected.
        "Keywords",                 // The text to be used for the menu
        "read",                     // The capability required for this menu to be displayed to the user
        "wpinterlinks",             // The slug name to refer to this menu by
        "wpil_admin_keywords"       // The function to be called
        );
    add_submenu_page(
        "wpinterlinks",             // The slug name for the parent menu
        "wpInterLinks Settings",    // The text to be displayed in the title tags of the page when the menu is selected.
        "Settings",                 // The text to be used for the menu
        "read",                     // The capability required for this menu to be displayed to the user
        "wpinterlinks-settings",    // The slug name to refer to this menu by
        "wpil_admin_settings"       // The function to be called
        );
    */
}


// Actual function that handles the settings sub-page
function wpil_admin_settings() {
    global $wpdb;
    ?>
   <div class="wrap">
      <h2>wpInterLinks Settings</h2>

   </div>
   <?php
}




add_action('admin_menu', 'wpil_setup');
?>
