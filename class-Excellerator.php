<?php
class Excellerator {

	/* ------------------------------------------------------------------------ *
	 * Properties
	 * ------------------------------------------------------------------------ */
	
	/**
	 * post_type
	 * @since 0.0.1
	 *
	 * The post type this instance of Excellerator is attached to. Serves as a
	 * default post type when uploading, determines where in the admin the
	 * uploader will appear, and appears in the slugs of the upload page and AJAX
	 * callbacks. Defaults to 'xlrtr' if not defined.
	 */
	protected $post_type;


	/**
	 * map
	 * @since 0.0.1
	 *
	 * An array supplied by the user that connects post properties to fields in
	 * the uploaded spreadsheet, and also defines some basic settings.
	 */
	protected $map;


	/**
	 * title
	 * @since 0.0.1
	 *
	 * The title of the upload page associated with this instance.
	 */
	protected $title;


	/**
	 * slug
	 * @since 0.0.1
	 *
	 * A unique slug to identify this instance of Excellerator, necessary in the 
	 * event that two instances are attached to the same post type (or are both
	 * unattached).
	 */
	protected $slug;


	/**
	 * acf_enabled
	 * @since 0.0.1
	 *
	 * Boolean indicating whether Advanced Custom Fields is installed and active.
	 */
	protected $acf_enabled;


	/**
	 * saved
	 * @since 0.0.1
	 *
	 * Boolean indicating whether the file was successfully saved.
	 */
	protected $saved = false;


	/**
	 * filename
	 * @since 0.0.1
	 *
	 * The filename of the saved file.
	 */
	protected $filename;


	/**
	 * mime_types
	 * @since 0.0.1
	 *
	 * List of MIME types supported by the plugin. 
	 */
	protected $mime_types = array(
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.ms-excel.sheet.macroenabled.12',
		'application/vnd.oasis.opendocument.spreadsheet', 
		'application/x-vnd.oasis.opendocument.spreadsheet',
		'text/csv',
	);


	/**
	 * defaults
	 * @since 0.0.1
	 *
	 * Default Excellerator import options.
	 */
	protected $defaults = array(
  	'header_row' => 1, // 1-based numbering to match user input
  	'force_publish' => false,
  	'append_tax' => false
  );
    

	/* ------------------------------------------------------------------------ *
	 * Construction and initialization
	 * ------------------------------------------------------------------------ */

	/**
	 * Constructor
	 * @since 0.0.1
	 *
	 * Sets up properties and AJAX upload and progress callbacks.
	 */
	public function __construct( $map, $post_type = null, $title = 'Import from Excel', $slug = null ){

		$this->map = $map;
		$this->post_type = $this->validate_post_type( $post_type );
		$this->title = $title ? $title : 'Import from Excel';
		$this->slug = $slug ? sanitize_key( $slug ) : 'default';

		add_action( 'admin_menu', array( $this, 'register_upload_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts_and_styles' ) );
		add_action( 'wp_ajax_xlrtr_' . $this->post_type . '_' . $this->slug, array( $this, 'handle_upload' ) );
		add_action( 'wp_ajax_xlrtr_progress', array( $this, 'check_progress' ) );

		$this->acf_enabled = function_exists( 'update_field' );
	}


	/**
	 * register_uniqid_table
	 * @since 0.0.1
	 *
	 * Creates a table to link unique IDs from Excel documents to their
	 * created or updated post ID. Called on activation.
	 */
	public static function register_uniqid_table(){
		
		global $wpdb;

		$table_name = $wpdb->prefix . "xlrtr_uniqid";

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`uniq_id` varchar(45) NOT NULL,
			`post_id` int(11) NOT NULL,
			`post_type` varchar(45) NOT NULL,
			PRIMARY KEY (`id`) )";

		$wpdb->query( $sql );
	}


	/**
	 * register_upload_page
	 * @since 0.0.1
	 *
	 * Adds a submenu page beneath the menu item for the chosen post_type.
	 */
	public function register_upload_page(){

		switch( $this->post_type ){

			// Generic pages go under the Tools menu.
			case 'xlrtr':
				add_submenu_page(
					'tools.php',
					$this->title,
					$this->title,
					'edit_posts',
					'xlrtr_import_' . $this->post_type . '_' . $this->slug,
					array( $this, 'render_upload_page' )
				);
				break;

			// Pages with a defined post type go under that menu.
			default:
				add_submenu_page(
					'edit.php?post_type=' . $this->post_type,
					$this->title,
					$this->title,
					'edit_posts',
					'xlrtr_import_' . $this->post_type . '_' . $this->slug,
					array( $this, 'render_upload_page' )
				);
		}
	}


	/**
	 * render_upload_page
	 * @since 0.0.1
	 *
	 * Renders upload page markup.
	 */
	public function render_upload_page(){

		$past_uploads = get_posts( array(
			'post_type' => 'xlrtr_upload',
			'posts_per_page' => -1,
			'xlrtr_tag' => $this->post_type . '_' . $this->slug,
		));

		?>

		<div class='wrap'>

			<h2><?php echo $this->title; ?></h2>

			<form class='xlrtr-form'>
				<div class='xlrtr-flipper'>

					<div class='xlrtr-flipper-a'>
						<span href='' class='button xlrtr-button'>
							Choose a file
							<input type='file' class='xlrtr-chooser' accept='<?php echo implode( ', ', $this->mime_types ); ?>' />
						</span>
					</div>

					<div class='xlrtr-flipper-b'>
						<span class='button xlrtr-button xlrtr-change'>Change file</span>
						<span class='button-primary xlrtr-button xlrtr-submit'>Upload and process</span>
					</div>

					<div class='xlrtr-flipper-c'>
						<div class='xlrtr-meter'>
							<div class='xlrtr-progress'></div>
						</div>
					</div>

				</div>
				<p class='xlrtr-info'><span class='xlrtr-note'>Accepted file types:</span> <strong>.xls, .xlsx, .xlsm, .ods, .csv</strong></p>
			</form>

			<?php if( ! empty( $past_uploads ) ){ ?>

				<h3>Past Uploads</h3>

				<table class='xlrtr-past' cellpadding='0' cellspacing='0'>
					<tr>
						<th>Date</th>
						<th>Filename</th>
						<th>Download</th>
						<th>View log</th>
					</tr>

				<?php foreach( $past_uploads as $upload ){ 
					$log = json_decode( $upload->post_content, true );
					$date = date( 'F j, Y g:i a', strtotime( $upload->post_date ) ); 
					?>

					<tr>
						<td><?php echo $date; ?></td>
						<td><?php echo $upload->post_title; ?></td>
						<td><a href='<?php echo $log['public_url']; ?>'>Download</a></td>
						<td class='xlrtr-log-cell'>
							<span class='button xlrtr-view-log'>View Log</span>
							<ul class='xlrtr-log'>

								<?php if( $log['status'] === 'error' ){ ?>

									<li class='xlrtr-mode-error'>Error: <?php echo $log['error_message']; ?></li>

								<?php } ?>

								<?php foreach( $log['posts'] as $post_id=>$mode ){ ?>

									<li class='xlrtr-mode-<?php echo $mode; ?>'>Post #<?php echo $post_id . ' ' . $mode; ?></li> 

								<?php } ?>

							</ul>
						</td>
					</tr>

					<?php
				} 
				?>

				</table>

			<?php } ?>

		</div>

		<?php
	}


	/**
	 * register_scripts_and_styles
	 * @since 0.0.1
	 *
	 * Static callback to register plugin scripts and styles.
	 */
	public static function register_scripts_and_styles(){
		
		wp_register_style(
			'xlrtr',
			plugins_url( 'css/xlrtr.css', __FILE__ ),
			array(),
			false
		);
		
		wp_register_script(
			'xlrtr',
			plugins_url( 'js/xlrtr.js', __FILE__ ),
			array( 'jquery' ),
			false,
			true
		);
	}


	/**
	 * register_post_type
	 * @since 0.0.1
	 *
	 * Static callback to register xlrtr_log private post type
	 */
	public static function register_post_type(){
		register_post_type( 'xlrtr_upload' );
	}


	/**
	 * register_taxonomy
	 * @since 0.0.1
	 *
	 * Static callback to register xlrtr_tag private taxonomy
	 */
	public static function register_taxonomy(){
		register_taxonomy( 'xlrtr_tag', 'xlrtr_upload', array( 'public' => false ) );
	}


	/**
	 * enqueue_scripts_and_styles
	 * @since 0.0.1
	 *
	 * Adds scripts and styles for upload pages and localizes the script with
	 * the ajax callback specific to this uploader.
	 */
	public function enqueue_scripts_and_styles( $hook ){

		// Bail if not on the upload page for this specific instance.
		$screen = get_current_screen();
		$id = 'xlrtr_import_' . $this->post_type . '_' . $this->slug;
		if( strpos( $screen->id, $id ) === false ){
			return;
		}

		// Stylesheet
		wp_enqueue_style( 'xlrtr' );

		// Javascript
		wp_enqueue_script( 'xlrtr' );
		wp_localize_script( 
			'xlrtr', 
			'xlrtrData', 
			array(
				'upload' => array(
					'dest' => 'xlrtr_' . $this->post_type . '_' . $this->slug,
					'nonce' => wp_create_nonce( 'xlrtr_' . $this->post_type . '_' . $this->slug ),
				),
				'progress' => array(
					'dest' => 'xlrtr_progress',
					'nonce' => wp_create_nonce( 'xlrtr_progress' ),
				),
			)
		);
	}


	/* ------------------------------------------------------------------------ *
	 * Progress callback
	 * ------------------------------------------------------------------------ */

	/**
	 * handle_progress
	 * @since 0.0.1
	 *
	 * Handle ajax progress upload progress request.
	 */
	public function check_progress(){
		check_admin_referer( 'xlrtr_progress' );
		$this->print_log();
		die();
	}


	/* ------------------------------------------------------------------------ *
	 * Upload callback and major components
	 * ------------------------------------------------------------------------ */

	/**
	 * handle_upload
	 * @since 0.0.1
	 *
	 * Handle Excel document upload
	 */
	public function handle_upload(){

		check_admin_referer( 'xlrtr_' . $this->post_type . '_' . $this->slug );

		$this->log_init();

		$this->confirm_upload();

		$file = $_FILES['xlrtr_file'];

 		$this->validate_file( $file );

		$this->log( 'status', 'saving' );

 		$saved = $this->save_file( $file );

 		$this->saved = true;
		$this->filename = preg_replace( '/^([0-9])*\-/', '', basename( $saved['file'] ) );

		$this->log( array(
			'status' => 'processing',
			'public_url' => $saved['url'],
		));
		
		require( plugin_dir_path( __FILE__ ) . 'inc/nuovo/php-excel-reader/excel_reader2.php' );
    require( plugin_dir_path( __FILE__ ) . 'inc/nuovo/SpreadsheetReader.php' );

    $spreadsheet = new SpreadsheetReader( $saved['file'] );

    $options = $this->merge_options();

    $total_rows = $this->count_rows( $spreadsheet );

    $this->log( 'total', $total_rows );

    foreach( $spreadsheet as $i => $row ){

    	// Above header -- skip row
    	if( $i < $options['header_row'] ){
    	}

    	// Header row -- determine header references, then use them to convert
    	// the user-supplied map into an internal template that's easier to
    	// feed to WordPress. 
    	else if( $i === $options['header_row'] ){
    		$col_refs = $this->process_col_refs( $row );
    		$template = $this->assemble( $this->map, $col_refs );
    	}

    	// All other rows -- the magick happens.
    	else {

    		$this->filter( $row, $col_refs );

    		$compiled = $template;
		  	array_walk_recursive( $compiled, array( $this, 'interpolate' ), $row );

    		$this->process( $compiled, $options );

    	}

      $this->log( 'processed', $i + 1 );

    }

    $this->log( 'status', 'complete' );

    $upload_id = wp_insert_post( array(
    	'post_content' => json_encode( $this->get_log() ),
    	'post_title' => $this->filename,
    	'post_status' => 'publish',
    	'post_type' => 'xlrtr_upload',
    	'tax_input' => array( 'xlrtr_tag' => $this->post_type . '_' . $this->slug ),
    ));

    $this->print_log();
    die();
	}


	/**
	 * save_file
	 * @since 0.0.1
	 *
	 * @param resource $file The uploaded file
	 *
	 * Saves the file in the filesystem.
	 */
	protected function save_file( $file ){

		add_filter( 'upload_dir', array( $this, 'filter_upload_directory' ) );
		add_filter( 'sanitize_file_name', array( $this, 'filter_filename' ) );

		$overrides = array( 'test_form' => false );
		$saved = wp_handle_upload( $file, $overrides );

		remove_filter( 'upload_dir', array( $this, 'filter_upload_directory' ) );
		remove_filter( 'sanitize_file_name', array( $this, 'filter_filename' ) );

		// TODO: A proper way to check success?
		if( ! array_key_exists( 'file', $saved ) ){
			$this->error( 'Excellerator was unable to save the document to the server.' );
		}

		return $saved;
	}


	/**
	 * process_col_refs
	 * @since 0.0.1
	 *
	 * @param array $row The header row of the spreadsheet.
	 *
	 * Uses the content and length of the header row array to determine slug and
	 * code references for each column, as well as the total number of columns
	 * we will be processing. 
	 */
	protected function process_col_refs( $row ){

		$col_refs = array();

		foreach( $row as $i => $cell ){
			// Slugs are sanitized versions of the header cell content
			$col_refs['slugs'][ $i ] = sanitize_title( $cell );
			// Codes are Excel-style references ('A','B',...'ZY','ZZ')
			$col_refs['codes'][ $i ] = $this->get_col_code( $i );
			$col_refs['total'] = count( $row );
		}

		return $col_refs;
	}
 

	/**
	 * assemble
	 * @since 0.0.1
	 *
	 * @param array $user_map The user-supplied map.
	 * @param array $col_refs Array linking string column references to their
	 * index counterparts
	 *
	 * Converts the user-supplied template into an internal array with column
	 * references simplified to indices and a structure that is easier to feed
	 * to WordPress.
	 */
	protected function assemble( $user_map, $col_refs ){

		$xlrtr_template = array( 
			'uniqid' => null,
			'post' => array(),
			'meta' => array(),
			'tax' => array(),
		);

		// Unless prefixed with 'meta/' by the user, these properties are assumed
		// to refer to wp_insert_post parameters. 
		$post_properties = array(
		  'post_content',
		  'post_name',
		  'post_title',
		  'post_status',
		  'post_author',
		  'ping_status',
		  'post_type',
		  'post_parent',
		  'menu_order',
		  'to_ping',
		  'pinged',
		  'post_password',
		  'post_excerpt',
		  'post_date',
		  'post_date_gmt',
		  'comment_status',
		  'page_template',
		);

		$cat_equiv = array(
			'category',
			'categories',
			'cat',
			'cats',
		);

		$tag_equiv = array(
			'tag',
			'tags',
		);

		foreach( $user_map as $name=>$entry ){

			if( 'xlrtr_settings' === strtolower( $name ) ){
				continue;
			}

			if( 'uniqid' === strtolower( $name ) ){
				$xlrtr_template['uniqid'] = $entry;
			}

			// e.g. post_title
			else if( in_array( $name, $post_properties ) ){
				$xlrtr_template['post'][ $name ] = $entry;
			}

			// e.g. post/post_title
			else if( 'post/' === substr( $name, 0, 5 ) && in_array( substr( $name, 5 ), $post_properties ) ){
				$xlrtr_template['post'][ substr( $name, 5 ) ] = $entry;
			}

			// e.g. post/not_a_valid_property
			else if( 'post/' === substr( $name, 0, 5 ) ){
				$this->error( 'The map is malformed. ' . substr( $name, 5 ) . ' is not a valid post property.' );
			}

			// e.g. category, categories, cat, cats
			else if( in_array( $name, $cat_equiv ) ){
				if( ! array_key_exists( 'category', $xlrtr_template['tax'] ) ){
					$xlrtr_template['tax']['category'] = array();
				}
				$xlrtr_template['tax']['category'][] = $entry;
			}

			// e.g. tax/category, tax/categories, tax/cat, tax/cats 
			else if( 'tax/' === substr( $name, 0, 4 ) && in_array( substr( $name, 4 ), $cat_equiv ) ){
				if( ! array_key_exists( 'category', $xlrtr_template['tax'] ) ){
					$xlrtr_template['tax']['category'] = array();
				}
				$xlrtr_template['tax']['category'][] = $entry;
			}

			// e.g. tag, tags
			else if( in_array( $name, $tag_equiv ) ){
				if( ! array_key_exists( 'post_tag', $xlrtr_template['tax'] ) ){
					$xlrtr_template['tax']['post_tag'] = array();
				}
				$xlrtr_template['tax']['post_tag'][] = $entry;
			}

			// e.g. tax/tag, tax/tags
			else if( 'tax/' === substr( $name, 0, 4 ) && in_array( substr( $name, 4 ), $tag_equiv ) ){
				if( ! array_key_exists( 'post_tag', $xlrtr_template['tax'] ) ){
					$xlrtr_template['tax']['post_tag'] = array();
				}
				$xlrtr_template['tax']['post_tag'][] = $entry;
			}

			// e.g. tax/my_registered_taxonomy
			else if( 'tax/' === substr( $name, 0, 4 ) && taxonomy_exists( substr( $name, 4 ) ) ){
				$taxonomy = substr( $name, 4 );
				if( ! array_key_exists( $taxonomy, $xlrtr_template['tax'] ) ){
					$xlrtr_template['tax'][ $taxonomy ] = array();
				}
				$xlrtr_template['tax'][ $taxonomy ][] = $entry;
			}

			// e.g. tax/not_a_registered_taxonomy
			else if( 'tax/' === substr( $name, 0, 4 ) ){
				$this->error( 'The map is malformed. ' . substr( $name, 5 ) . ' is not a valid taxonomy.' );
			}

			// Everything else is assumed to be post meta, including meta/property.
			else {
				$name = preg_replace( '/^meta\//', '', $name );
				$xlrtr_template['meta'][ $name ] = $entry;
			}
		}

		if( ! array_key_exists( 'uniqid', $xlrtr_template ) ){
			$this->error( 'A unique ID must be established for every record and defined in the map.' );
		}

		// Convert all column references to indices
		array_walk_recursive( $xlrtr_template, array( $this, 'simplify_references' ), $col_refs );

		return $xlrtr_template;
	}


	/**
	 * simplify_references
	 * @since 0.0.1
	 *
	 * @param mixed $ref The value of the array item, expected to be a column
	 * reference
	 * @param int|str $key The key of the array item
	 * @param array $col_refs Array linking string column references to their
	 * index counterparts
	 *
	 * Walk the template, converting all column references (theretically, every
	 * array leaf node) to 0-based column indices.
	 */
	protected function simplify_references( &$ref, $key, $col_refs ){

		// String
		if( is_string( $ref ) ){

			$index = false;

			// Code
			if( preg_match( '/^[A-Z][A-Z]?$/', $ref ) ){
				$index = array_search( $ref, $col_refs['codes'] );
			}
			// Slug
			else {
				$ref = sanitize_title( $ref );
				$index = array_search( strtolower( $ref ), $col_refs['slugs'] );
			}

			// Bail if reference was not found. 
			// NOTE: Can't simply check falsiness because the index may be 0. 
			if( $index === false ){
				$this->error( 'The map is malformed. A column name or code does not match the spreadsheet. The document was saved but not processed.' );
			} 

			$ref = $index;
		}

		// Integer
		else if( is_int( $ref ) ){

			// Bail if referenced column is outside the scope defined by header row
			if( $ref > $col_refs['total'] || $ref < 1 ){
				$this->error( 'The map is malformed. A column reference refers to a column which is outside the scope of the spreadsheet. Note that columns follow 1-based counting and only columns with data in a header row will be processed.' );
			}

			// Convert from 1-based to 0-based counting
			$ref = $ref--;
		}

		// Wrong type
		else{
			$this->error( 'The map is malformed. Column references must be a string or integer.' );
		}
	}


	/**
	 * filter
	 * @since 0.0.1
	 *
	 * @param array $row The row of data to filter.
	 * @param array $col_refs An array connecting column references to indices.
	 *
	 * Applies required filters to row and each cell within.
	 */
	protected function filter( &$row, $col_refs ){

		// Filter the row
  	$row = apply_filters( $this->get_row_filter_name(), $row );
  	if( ! $row ){
  		$this->error( 'A row filter has caused a problem.' );
  	}

  	// Filter each cell
  	for( $i = 0; $i < count( $row ); $i++ ){

  		$ref_methods = array(
  			'index' => $i + 1,
  			'slug' => $col_refs['slugs'][ $i ],
  			'code' => $col_refs['codes'][ $i ],
  		);

  		foreach( $ref_methods as $ref_method ){

  			$was_blank = empty( $row[ $i ] );

				$row[ $i ] = apply_filters( $this->get_cell_filter_name( $ref_method, false ), $row[ $i ] );

				if( empty( $row[ $i ] ) && ! $was_blank ){ 
					$this->error( 'A cell filter has caused a problem.' );
				}
			}
  	}
	}


	/**
	 * interpolate
	 * @since 0.0.1
	 * 
	 * @param int $ref The array item to replace with row data. At this stage, 
	 * this should consist of an integer representing the column get_c where the
	 * replacement data lives. 
	 * @param int|str $key The key of the array item (ignored)
	 * @param array $row The filtered row of data from the spreadsheet
	 *
	 * array_walk_recursive callback working on the xlrtr_template. Compiles one 
	 * row of data against the column reference stored in the template.
	 */
	protected function interpolate( &$ref, $key, $row ){

		if( ! is_int( $ref ) ){
			$this->error( 'A column reference has not been properly converted to an integer. This is almost certainly an internal plugin error. Try using an earlier version of Excellerator or filing a bug report.' );
		}
		$ref = $row[ $ref ];
	}


	/**
	 * process
	 * @since 0.0.1
	 *
	 * @param array $compiled The xlrtr_template with filtered data inserted.
	 * @param array $options Array of processing options.
	 *
	 * Inserts or updates posts according to data.
	 */
	protected function process( $compiled, $options ){

		$uniqid = $compiled[ 'uniqid' ];
		$mode = null;

		if( $options['force_publish'] ){
			$compiled['post']['post_status'] = 'publish';
		}

		/**
		 * Fill in post type if blank. If attached to a post type, that post type 
		 * is used; if this is a generic uploader, 'post' is used. 
		 *
		 * NOTE: Even though wp_insert_post will assume 'post' if not provided, we 
		 * explicitly add this value to $compiled so that we can populate the 
		 * uniqid table with the final post_type.
		 */
		if( ! array_key_exists( 'post_type', $compiled['post'] ) && $this->post_type === 'xlrtr' ){
			$compiled['post']['post_type'] = 'post';
		} 
		else if( ! array_key_exists( 'post_type', $compiled['post'] ) && $this->post_type !== 'xlrtr' ) {
			$compiled['post']['post_type'] = $this->post_type;
		}

		$post_id = $this->get_post_by_uniqid( $uniqid, $compiled['post']['post_type'] );

		if( $post_id ){

			$mode = 'updated';

			$compiled['post']['ID'] = $post_id;

			$updated = wp_update_post( $compiled['post'] );

			if( ! $updated ){
				$this->error( "Excellerator was unable to update a post for an unknown reason. One possible explanation is that the post to be updated was deleted." );
			}

		}

		else {

			$mode = 'inserted';

			$compiled['post']['post_type'] = $this->post_type;

			$post_id = wp_insert_post( $compiled['post'] );

			if( ! $post_id ){
				$this->error( 'Excellerator was unable to insert a new post for an unknown reason.' );
			}

			$this->set_post_for_uniqid( $uniqid, $post_id, $compiled['post']['post_type'] );
		}

		foreach( $compiled['meta'] as $key=>$value ){
			if( $this->acf_enabled && 'field_' === substr( $key, 0, 6 ) ){
				update_field( $key, $value, $post_id );
			}
			else{
				update_post_meta( $post_id, $key, $value );
			}
		}

		foreach( $compiled['tax'] as $taxonomy=>$term_array ){
			$terms = $this->flatten_and_split( $term_array );
			wp_set_object_terms( $post_id, $terms, $taxonomy, $options['append_tax'] );
		}

		$posts = $this->get_log_prop( 'posts', array() );

		$posts[ $post_id ] = $mode;

		$this->log( 'posts', $posts );
	}


	/* ------------------------------------------------------------------------ *
	 * Utilities
	 * ------------------------------------------------------------------------ */

	/**
	 * filter_upload_directory
	 * @since 0.0.1
	 *
	 * @param array $param The current upload_dir array from WordPress.
	 *
	 * Changes upload directory to xlrtr folder.
	 */
	public function filter_upload_directory( $param ){
		$param['path'] = $param['basedir'] . '/xlrtr/' . $this->post_type . '/' . $this->slug;
		$param['url'] = $param['baseurl'] . '/xlrtr/' . $this->post_type . '/' . $this->slug;
		return $param;
	}


	/**
	 * filter_filename
	 * @since 0.0.1
	 *
	 * @param string $filename The current filename from WordPress.
	 *
	 * Appends the date to the filename for reference.
	 */
	public function filter_filename( $filename ){
		$filename = time() . '-' . $filename;
		return $filename;
	}


	/**
	 * merge_options
	 * @since 0.0.1
	 *
	 * Merges user options with defaults.
	 */
	protected function merge_options(){

		$options = $this->defaults;

    if( array_key_exists( 'xlrtr_settings', $this->map ) ){
    	$options = array_merge( $this->defaults, $this->map['xlrtr_settings'] );
    }

    // We expect to receive 1-based numbering to match Excel row labels, but
    // we want to work with 0-based.
    $options['header_row']--;

    return $options;
	}


	/**
	 * get_row_filter_name
	 * @since 0.0.1
	 *
	 * Returns the unique filter name for filtering data a row at a time for this
	 * specific instance of Excellerator.
	 */
	public function get_row_filter_name(){
		return 'xlrtr/' . $this->post_type . '/' . $this->slug . '/row';
	}


	/**
	 * get_cell_filter_name
	 * @since 0.0.1
	 *
	 * @param string/integer $ref The column to filter
	 * @param bool $sanitize Whether to sanitize a column slug
	 *
	 * Returns the unique filter name for a specific column for this specific 
	 * instance of Excellerator. $ref can be an integer referring to the
	 * 0-based index of the column, a string with an uppercase letter designating
	 * the column in the spreadsheet, or a string with the column header. All 
	 * three filters will be called when processing the spreadsheet. 
	 */
	public function get_cell_filter_name( $ref, $sanitize = true ){

		if( $this->is_col_slug( $ref ) && $sanitize ){
			$ref = sanitize_title( $ref );
		}

		return 'xlrtr/' . $this->post_type . '/' . $this->slug . '/' . $ref;
	}


	/**
	 * is_col_slug
	 * @since 0.0.1
	 *
	 * @param int|str $ref The reference to check.
	 *
	 * Returns true if reference is a column slug.
	 */
	protected function is_col_slug( $ref ){
		if( is_string( $ref ) && ! preg_match( '/^[A-Z][A-Z]?$/', $ref ) ){
			return true;
		}
		return false;
	} 


	/**
	 * get_col_code
	 * @since 0.0.1
	 *
	 * @param integer $index The 0-based index of the column
	 *
	 * Converts a column index into a column code. 
	 */
	protected function get_col_code( $index ){

		$letters = array( 'A','B','C','D','E','F','G','H','I','J','K','L','M','N',
			'O','P','Q','R','S','T','U','V','W','X','Y','Z' );

		if( $index < 0 ){
			return false;
		}

		if( $index < 26 ){
			return $letters[ $index ];
		}

		$primary = floor( $index / 26 );
		$secondary = $index % 26;

		return $letters[ $primary - 1 ] . $letters[ $secondary ];
	}


	/**
	 * confirm_upload
	 * @since 0.0.1
	 *
	 * Confirms that a file has been uploaded.
	 */
	protected function confirm_upload(){
		if( empty( $_FILES ) || ! array_key_exists( 'xlrtr_file', $_FILES ) ){
			$this->error( 'No file was received.' );
		}
	}


	/**
	 * validate_file
	 * @since 0.0.1
	 *
	 * @param resource $file The uploaded file
	 * 
	 * Validates that the upload matches one of the accepted MIME types. 
	 * NOTE: This function is for user checking, not security.
	 */
	protected function validate_file( $file ){
		if( ! in_array( strtolower( $file['type'] ), $this->mime_types ) ){
			$this->error( 'This file is in an unsupported format. Refer to the documentation to see which file types are acceptable, or try resaving your document.' );
		}
	}


	/**
	 * count_rows
	 * @since 0.0.1
	 *
	 * @param SpreadsheetReader $spreadsheet The file reader.
	 *
	 * Blasts through the rows of the spreadsheet to get total row count.
	 */
	protected function count_rows( $spreadsheet ){

    $total_rows = 0;

    foreach( $spreadsheet as $row ){
    	$total_rows++;
    }

    return $total_rows;
	}


	/**
	 * get_post_by_uniqid
	 * @since 0.0.1
	 *
	 * @param int|str $uniqid The unique ID supplied by the user
	 *
	 * Searches the xlrtr_uniqid table for the uniqid and returns the post ID if
	 * available, otherwise false.
	 */
	protected function get_post_by_uniqid( $uniqid ){

		global $wpdb;

		$table = $wpdb->prefix . 'xlrtr_uniqid';

		$post_id = $wpdb->get_var( 
			$wpdb->prepare(
				"SELECT post_id FROM $table WHERE uniq_id = %s AND post_type = %s",
				(string)$uniqid,
				$this->post_type
			)
		);

		return $post_id;
	}


	/**
	 * set_post_for_uniqid
	 * @since 0.0.1
	 *
	 * @param int|str $uniqid The unique ID supplied by the user
	 * @param int $post_id The ID of the post just created
	 *
	 * Inserts a record into the xlrtr_uniqid table linking a uniqid to a post id.
	 */
	protected function set_post_for_uniqid( $uniqid, $post_id ){

		global $wpdb;

		$table = $wpdb->prefix . 'xlrtr_uniqid';

		$data = array(
			'uniq_id' => $uniqid,
			'post_id' => $post_id,
			'post_type' => $this->post_type,
		);

		$rel_id = $wpdb->insert( $table, $data, '%s' );

		return $rel_id;
	}


	/**
	 * validate_post_type
	 * @since 0.0.1
	 *
	 * @param str $post_type The post type supplied by the user
	 *
	 * Validates that a post type has been supplied and is registered. Returns 
	 * the post type slug if registered, or 'xlrtr' if not.
	 */
	protected function validate_post_type( $post_type ){
		if( ! $post_type || ! post_type_exists( $post_type ) ){
			return 'xlrtr';
		}
		return $post_type;
	}


	/**
	 * flatten_and_split
	 * @since 0.0.1
	 *
	 * @param array $terms The terms array.
	 *
	 * Flattens subarrays while also separating delimited strings into individual
	 * array elements. Inspired by http://stackoverflow.com/a/1320112.
	 */
	protected function flatten_and_split( $terms ){

	  if( ! is_array( $terms ) ) {

	  	// If this is a term id, make sure it's an integer.
	  	if( is_numeric( $terms ) ){
	  		$terms = intval( $terms );
	  	}

      return array( $terms );
  	}

    $final_terms = array();

    foreach( $terms as $term ){

    	// If this is a string with delimiters, convert to an array
			if( is_string( $term ) && preg_match( '/[\s\r\n\|,]/', $term ) ){
				$term = preg_split( '/[\s\r\n\|,]/', $term, -1, PREG_SPLIT_NO_EMPTY );
			}

			// Flatten the arrays.
      $final_terms = array_merge( $final_terms, $this->flatten_and_split( $term ) );
    }

    return $final_terms;
	}


	/* ------------------------------------------------------------------------ *
	 * Errors and logging
	 * ------------------------------------------------------------------------ */

	/**
	 * log_init
	 * @since 0.0.1
	 *
	 * Creates a blank log and saves as a transient. The log must be saved in the
	 * database because it must be accessed by the browser's asynchronous
	 * progress requests, as well as the main import procedure.
	 */
	protected function log_init(){
		$log_key = $this->get_log_key();
		$log = array(
			'status' => null,
			'total' => 0,
			'processed' => 0,
			'posts' => array(),
			'error_message' => null,
		);
		set_transient( $log_key, $log, 60 * 60 * 24 );
	}


	/**
	 * get_log_key
	 * @since 0.0.1
	 *
	 * Gets the unique transient key for the log of this instance of Excellerator.
	 */
	protected function get_log_key(){
		$user_id = get_current_user_id();
		$key = 'xlrtr_' . md5( $this->post_type . '_' . $this->slug . '_' . $user_id );
		return $key;
	}


	/**
	 * get_log
	 * @since 0.0.1
	 *
	 * Get the log
	 */
	protected function get_log(){
		$log_key = $this->get_log_key();
		$log = get_transient( $log_key );
		return $log ? $log : array();
	}


	/**
	 * error
	 * @since 0.0.1
	 *
	 * @param string $message The message to return to the user
	 *
	 * Logs an error, returns data to the user, and dies.
	 */
	protected function error( $message ){

		$this->log( array(
			'status' => 'error',
			'error_message' => $message,
		));

		if( $this->saved ){
	    $upload_id = wp_insert_post( array(
	    	'post_content' => json_encode( $this->get_log() ),
	    	'post_title' => $this->filename,
	    	'post_status' => 'publish',
	    	'post_type' => 'xlrtr_upload',
    		'tax_input' => array( 'xlrtr_tag' => $this->post_type . '_' . $this->slug ),
	    ));
		}

		$this->print_log();
		die();
	}


	/**
	 * log
	 * @since 0.0.1
	 *
	 * @param string|array $data A single property to log, or an associative
	 * array of log properties and their values.
	 * @param mixed $message The value to log against $data.
	 *
	 * Updates the log.
	 */
	protected function log( $data, $message = null ){

		$log_key = $this->get_log_key();

		$log = $this->get_log();

		if( is_string( $data ) && $message ){

			$log[ $data ] = $message;

		}
		else if( is_array( $data ) ){

			foreach( $data as $key=>$val ){

				$log[ $key ] = $val;

			}
		}
		else{
			$this->error( 'Improper logging detected.' );
		}

		set_transient( $log_key, $log, 60 * 60 * 24 );
	}


	/**
	 * print_log
	 * @since 0.0.1
	 *
	 * Prints the log.
	 */
	protected function print_log(){
		$log = $this->get_log();
		echo json_encode( $log );
	}


	/**
	 * get_log_prop
	 * @since 0.0.1
	 *
	 * @param string $property The property to retrieve
	 * @param mixed $default The default value to return if the array key exists
	 * or is empty
	 *
	 * Get an item from the log.
	 */
	protected function get_log_prop( $property, $default = null ){

		$log = $this->get_log();

		if( ! array_key_exists( $property, $log ) ){
			return $default;
		}

		return $log[ $property ];
	}
}