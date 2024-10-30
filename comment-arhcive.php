<?php

/*
 Plugin Name: Comment Archive
Plugin URI:
Description: allows comment to be archived.
Version: 0.3
Author: Jiong Ye
Author URI: http://www.bunchacode.com
License: GPL2
*/
/*  Copyright 2011  Jiong Ye  (email : dexxaye@gmail.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

global $wpdb;

define('COMMENT_ARCHIVE_DIR', WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . 'comment-archive' . DIRECTORY_SEPARATOR);

register_activation_hook(__FILE__, 'comment_archive_install');

add_action('init', 'comment_archive_register_scripts');
add_action('wp_footer', 'comment_archive_footer_include');

add_filter('comment_status_links', 'comment_archive_comment_views');
add_filter('comments_clauses', 'comment_archive_comment_clauses');
add_filter('comment_row_actions', 'comment_archive_comment_action', 10, 2);
add_action('admin_action_archivecomment', 'comment_archive_archive_comment');

add_action('wp_ajax_comment_archive', 'comment_archive_ajax_handle' );
add_action('wp_ajax_comment_unarchive', 'comment_unarchive_ajax_handle' );

//installs the plugin. placeholder for now.
function comment_archive_install()
{

}

//registers javascript files
function comment_archive_register_scripts()
{
	wp_register_script('comment_archive_js', plugins_url('js/comment_archive.js', __FILE__), array('jquery'), '1.0', true);
	wp_localize_script('comment_archive_js', 'ajax', array( 'ajaxurl' => admin_url( 'admin-ajax.php' )));
	wp_enqueue_script('comment_archive_js');
	
	wp_register_style('comment_archive_css', plugins_url('css/comment_archive.css', __FILE__));
	wp_enqueue_style('comment_archive_css');
}

//adds archive link up top and shows archive count
//also shows approved comment count
function comment_archive_comment_views($views)
{
	global $post_id;

	$archiveCount = _comment_archive_count('archive', $post_id);
	$approvedCount = _comment_archive_count('approved', $post_id);
	
	$status = "archive";
	
	if(isset($_GET['comment_status']) && $_GET['comment_status'] == 'archive'){
		$class = ' class="current"';
		
		foreach($views as $s => $l)
		{
			$views[$s] = str_replace('current', '', $views[$s]);
		}
	}
	
	$link = 'edit-comments.php';
	if ( $post_id )
		$link = add_query_arg( 'p', absint( $post_id ), $link );
	$link = add_query_arg( 'comment_status', $status, $link );
	
	$views['archive'] = '<a href="'.$link.'"'.$class.'>Archive <span class="count">(<span class="archive-count">'.$archiveCount.'</span>)</span></a>';
	$views['approved'] = str_replace('Approved</a>', 'Approved <span class="count">(<span class="archive-count">'.$approvedCount.'</span>)</span></a>', $views['approved']);
	
	return $views;
}

//
function comment_archive_comment_clauses($args)
{
	if(isset($_GET['comment_status']) && $_GET['comment_status'] == 'archive'){
		$args['where'] = preg_replace("/comment_approved\s?=\s?'.+?'/i", "comment_approved = 'archive'", $args['where']);
	}

	return $args;
}

//adds archive and unarchive link to each comment
function comment_archive_comment_action($actions, $comment)
{
	$archive_nonce = esc_html( '_wpnonce=' . wp_create_nonce( "approve-comment_$comment->comment_ID" ) );
	$url = "comment.php?c=$comment->comment_ID";
	
	$archive_url = esc_url( $url . "&action=archivecomment&$archive_nonce" );
	$unarchive_url = esc_url( $url . "&action=approvecomment&$archive_nonce" );
	
	if($comment->comment_approved == 'archive')
	{
		unset($actions['approve']);
		unset($actions['unapprove']);
		$actions['archive'] = "<a href='$unarchive_url' class='comment-unarchive' rel='{$comment->comment_ID}'>Unarchive</a>";
	}
	else 
	{
		$actions['unarchive'] = "<a href='$archive_url' class='comment-archive' rel='{$comment->comment_ID}'>Archive</a>";
	}
	return $actions;
}

//non-ajax version of archive handle
function comment_archive_archive_comment()
{
	if (! wp_verify_nonce($_GET['_wpnonce'], 'approve-comment_'.$_GET['c']) ) 
		die('Security check');
	
	_comment_arcive_archive_comment($_GET['c']);
	
	$wp_list_table = _get_list_table('WP_Comments_List_Table');
	$pagenum = $wp_list_table->get_pagenum();
	$redirect_to = remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'spammed', 'unspammed', 'approved', 'unapproved', 'ids' ), wp_get_referer() );
	$redirect_to = add_query_arg( 'paged', $pagenum, $redirect_to );
	wp_safe_redirect( $redirect_to );
	exit;
}

//ajax version of archive handle
function comment_archive_ajax_handle()
{
	if(isset($_POST['id']))
	{
		_comment_arcive_archive_comment($_POST['id']);
		echo true;
	}
	
	exit;
}

function comment_unarchive_ajax_handle()
{
	if(isset($_POST['id']))
	{
		_comment_arcive_unarchive_comment($_POST['id']);
		echo true;
	}
	
	exit;	
}

function _comment_archive_count($state, $post_id = '')
{
	global $wpdb;
	$where = '';
	
	switch($state)
	{
		case 'archive':
			$where = "WHERE comment_approved = 'archive' ";
			break;
		case 'approved':
			$where = "WHERE comment_approved = 1 ";
			break;
	}
	
	
	if ( $post_id > 0 )
		$where .= $wpdb->prepare( "AND comment_post_ID = %d", $post_id );
	
	return number_format_i18n($wpdb->get_var( "SELECT COUNT( * ) AS num_comments FROM {$wpdb->comments} {$where}"));
}

function _comment_arcive_archive_comment($id)
{
	global $wpdb;
	
	$wpdb->query($wpdb->prepare("UPDATE {$wpdb->comments} SET comment_approved='archive' WHERE comment_ID=%d", $id));
}

function _comment_arcive_unarchive_comment($id)
{
	global $wpdb;
	
	$wpdb->query($wpdb->prepare("UPDATE {$wpdb->comments} SET comment_approved=1 WHERE comment_ID=%d", $id));
}
