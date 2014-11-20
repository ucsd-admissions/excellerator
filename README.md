# Excellerator

Excellerator is a WordPress plugin that builds on the open-source [Nuovo Spreadsheet Reader](https://github.com/nuovo/spreadsheet-reader) to sync a group of posts with data from a spreadsheet (including Excel .xls, .xlsx, .xlsm, Open Office .ods, or .csv). 

**CAUTION: This plugin is under construction and has not yet been thoroughly tested.** Feel free to fork or tinker, but use in a live site is not yet recommended.

## Table of Contents
1. [What is Excellerator?](#about)
2. [Installation and Usage](#usage)
3. [Complete Installation Example](#example)
4. [Constructor](#constructor)
5. [$map Syntax](#map-syntax)
6. [Data Filters](#filters)
7. [What changes does Excellerator make to my WordPress site?](#changes)
8. [License (Three-Clause BSD)](#license)

## <a name='about'></a>What Does Excellerator Do?

Excellerator makes it easy to:

*   Add multiple upload forms to the admin area, attached to a particular post type or to the Tools page
*   Map spreadsheet columns to basic post properties, post metadata (including Advanced Custom Fields), or taxonomies
*   Filter spreadsheet data before it's imported
*   Maintain unique ids on the spreadsheet side to avoid duplication when re-importing
*   Download previously uploaded spreadsheets

## <a name='usage'></a>Installation and Usage

Install and activate Excellerator as you would any other WordPress plugin.

**Excellerator configuration is intentionally non-user-facing.** In your theme's functions.php or somewhere in your plugin, add an action to the `init` hook. 

In the callback, create a new Excellerator instance for each upload form you would like to appear on the site. [View a complete installation example.](#complete-installation-example)

When documents are uploaded through an Excellerator form, they are processed using the [$map](#map-syntax) supplied in the constructor, and posts are either inserted or updated with data from the spreadsheet.

Previously uploaded documents can be re-downloaded from the same page, providing some crude version control (or at least a backup). You can also view the log of inserted and updated posts for each import.

## <a name='example'></a>Complete Installation Example

```php
add_action( 'init', 'my_event_form', 10, 0 );
function my_event_form(){

	// Bail if the plugin is deactivated
	if( ! class_exists( 'Excellerator' ) ){
		return;
	}

	// Map spreadsheet columns to post properties
	$map = array(
		'xlrtr_settings' => array( 
			'header_row' => 2, 
			'force_publish' => true, 
			'append_tax' => true,
		),
		'uniqid' => 'A',
		'post_title' => 'B', 
		'post_content' => 'C', 
		'category' => 'D',
		'tax/phase' => 'E', 
		'location' => 'F', 
		'meta/start_time' => 'G', 
		'field_543f00c23c5c7' => 'H', 
	);

	// Register form
	$xlrtr = new Excellerator( 
		$map, 
		'event', 
		'Upload your spreadsheet here', 
		'event_uploader_1'
	);

	// MAKE ALL THE DATA UPPERCASE
	$row_filter = $xlrtr->get_row_filter_name();
	add_filter( $row_filter, 'my_row_filter', 10, 1 );
	function my_row_filter( $row ){
		foreach( $row as $k=>$v ){
			$row[$k] = strtoupper( $v );
		}
		return $row;
	}

}
```

## <a name='constructor'></a>Constructor

```php
<?php $xlrtr = new Excellerator( $map, $post_type, $title, $slug ); ?>
```

### Parameters

#### $map

_(array) (required)_ An array that maps spreadsheet columns to post properties. [Details](#map-syntax)

#### $post_type

_(string) (optional)_ If you supply a post type slug, the form will appear underneath that post type's admin menu, and all imported posts will default to that post type unless your $map says otherwise.

#### $title

_(string) (optional)_ A custom title for the upload page.

#### $slug

_(string) (optional)_ In the rare case that you have more than one Excellerator form attached to a given post type (or more than one form _not_ attached to a post type), a unique slug is required so the system can tell the forms -- and the spreadsheets upload through them -- apart.

### Return Values

Excellerator instance. This is mostly useful for [filtering data](#filters).

## <a name='map-syntax'></a>$map Syntax

The $map parameter is an associative array that tells Excellerator how to interpret the data it finds in each column of the uploaded spreadsheet. 

Each array **key** represents a post property, and each array **value** refers to the column that holds the value for that property. For the most part, $map array elements have this syntax:

`{post_property} => {column_reference}`

Here's a complete example. Each part will be explained below:

```php
$map = array(
	
	'xlrtr_settings' => array(
		// setting => default
		'header_row' => 2, // There is a blank row before my header
	);

	'uniqid' => 'A', // Col A has this spreadsheet's unique ID for this post.

	'post_title' => 'B', // Col B has the post title.
	'post_content' => 'C', // Col C has the post content.

	'category' => 'D', // Col D has the post category.
	'tax/phase' => 'E', // Col E has the value for phase, a custom taxonomy.

	'location' => 'F', // Col F will be saved to wp_postmeta, with the meta_key 'location'.
	'meta/start_time' => 'G', // Col G will also be saved to wp_postmeta, with the meta_key 'start_time'.
	'field_543f00c23c5c7' => 'H', // Col H will also be saved be postmeta, but in an Advanced Custom Fields-friendly way.

);
```

### How to reference a post property

Excellerator will generally try to interpret your post properties (that is, your array **keys**) in this order:

1. Is it a [wp_insert_post](http://codex.wordpress.org/Function_Reference/wp_insert_post#Parameters) property? (e.g. 'post_title', 'post_status') Note: some properties accepted by wp_insert_post, such as ID and guid, are not available.
2. If not, is it a category or tag? (e.g. 'category', 'tag', but also 'categories', 'cat', 'cats', 'tags')
3. If not, it must be metadata.

See [Tips and Tricks](#tips-and-tricks) for ways to explicitly reference a meta property or custom taxonomy.

### How to reference a column

There are a couple ways to write your column references (the **values** of your array).

**Letters:** The simplest way is to reference columns using their letters as presented in the spreadsheet ('A', 'B' ... 'ZY', 'ZZ'). This is especially useful if your columns may be renamed in the future. (Just don't rearrange them!)

**Labels:** You can also use the column's label, which for our purposes is defined as its value in the header row. If your column is titled 'Location', for example, you can just use that. This is useful if your columns may be rearranged in the future. (Just don't rename them!)

### xlrtr_settings

The **xlrtr_settings** key is reserved for an array of additional configuration options. If the defaults work for you, you can skip this.
```php
'xlrtr_settings' => array(
	// setting => default
	'header_row' => 1, // The 1-based row of the spreadsheet that has your column labels
	'force_publish' => false, // Import as a published post rather than a draft
	'append_tax' => false, // Append taxonomy terms rather than replacing
);
```

### uniqid

The **uniqid** key is required and must reference a spreadsheet column with a unique string of characters that does not change between uploads. Excellerator will keep track of which uniqid refers to which post, so that you don't end up with duplicate posts when you import again in the future.  

### Tips and tricks

You can influence the post property interpretation process in a few ways:

**Prefix your key with 'meta/' to force Excellerator to interpret it as metadata.** 

Example: `'meta/post_status' => 'A'` will insert the value from column A into the wp_postmeta table with meta_key 'post_status'. The post's actual post status will be unaffected.

**When using Advanced Custom Fields, use the field ID as the key.** 

Example: `'field_543f00f13c5c9' => 'A'` will use the ACF API to import your data so ACF can manipulate it in the future.

**Prefix your key with 'tax/' to import to a custom taxonomy.** 

Example: `'tax/my_taxonomy_slug' => 'A'` will insert your value as a my_taxonomy_slug term attached to this post, or error out if the taxonomy does not exist.

**You can also prefix basic post properties with 'post/', but it has no benefit other than clarity.**

## <a name='filters'></a>Data Filters

You can filter your data before it gets saved, either a row at a time or a cell at a time. 

Filtering uses the standard WordPress API, but you'll want to use your Excellerator instance to get the filter name that is specific to your upload form. 

### Example: Filter data a row at a time

To get the row filter name, use `$xlrtr->get_row_filter_name()`.

This filter returns completely raw data, as a **(0-based)** numerically indexed array of cells. 

Example: Suppose Column B/$row[1] contains the 'status' of a record using terms specific to your organization, and Column C/$row[2] indicates whether a stakeholder has signed off. You've left Column D/$row[3] blank and mapped it to the actual post_status, which you'll determine using Columns B and C.


```php
$xlrtr = new Excellerator( array(
	'uniqid' => 'A',
	// We'll use Column B in filtering, but won't save it anywhere
	// We'll use Column C in filtering, but won't save it anywhere
	'post_status' => 'D',
) );

$row_filter = $xlrtr->get_row_filter_name();

add_filter( $row_filter, 'my_row_filter', 10, 1 );

function my_row_filter( $row ){
	if( $row[1] === 'on hold' || $row[2] === 'N' ){
		$row[3] = 'draft';
	} else {
		$row[3] = 'publish';
	}
	return $row;
}
```

### Example: Filter data a cell at a time

To get the cell filter name, use `$xlrtr->get_cell_filter_name( $col )`.

This filters the same cell _in each row_ based on the column reference you provide. 

Example: suppose people are entering datetime information into Column B in various nonstandard ways, and you want to standardize as a timestamp before saving.

```php
$xlrtr = new Excellerator( array(
	'uniqid' => 'A',
	'meta/start_time' => 'B',
) );

// Specify the column as in $map - by letter or label.
$cell_filter_b = $xlrtr->get_cell_filter_name( 'B' );

add_filter( $cell_filter_b, 'my_cell_filter', 10, 1 );

function my_cell_filter( $cell ){
	$cell = strtotime( $cell );
	return $cell;
}
```

## <a name='changes'></a>What changes does Excellerator make to my WordPress site? 

* Excellerator installs a new table, '{prefix}_xlrtr_uniqid'.
* Excellerator registers a private (non-user-facing) post type for internal use, 'xlrtr_upload'.
* Excellerator stores uploaded spreadsheets in a new subdirectory of your uploads folder, 'xlrtr'.

## <a name='license'></a>License (Three-Clause BSD)

Copyright (c) 2014, Regents of the University of California. All rights reserved.

Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:

*   Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
*   Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
*   Neither the name of the University of California nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.