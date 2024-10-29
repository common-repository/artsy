<?php
/*
Plugin Name: Artsy
Plugin URI: http://wordpress.org/plugins/artsy
Description: Art Direction for WordPress - Allows you to enter custom CSS to be applied per post/page.
Author: keegnotrub
Version: 1.0
Author URI: http://profiles.wordpress.org/keegnotrub/
*/

/* Display */

add_filter( 'the_title', 'art_lettering', 10 , 2 );

function art_lettering( $title, $id ) {
	if ( ( is_single() || is_page() ) && in_the_loop() ) {
		$letter_word = get_post_meta( $id, 'art_direction_letter_word', true );
		$letter_char = get_post_meta( $id, 'art_direction_letter_char', true );

		$output = '<span id="artsy-title">';

		if ( $letter_word ) {
			$output .= art_lettering_word( $title, $letter_char );
		} else if ( $letter_char ) {
			$output .= art_lettering_char( $title );
		}
		else {
			return $title;
		}
		
		$output .= '</span>';
		
		return $output;
	}
	
	return $title;
}


function art_lettering_char( $title ) {
	$output = "";
	$seek = false;
	$char = 0;

	$letters = str_split( $title );
	
	foreach( $letters as $letter ) {
		if ( $seek ) {        
			$output .= $letter;
			if ( $letter == ';' ) {
				$seek = false;
				$output .= '</span>';          
			}
		}
		else {
			$output .= '<span class="char' . ++$char . '">' . $letter;
			if ( $letter == '&' ) {
				$seek = true;
			}
			else {
				$output .= '</span>';
			}
		}
	}
	
	return $output;
}

function art_lettering_word( $title, $letter_char ) {
	$output = "";
	$words = preg_split( '/\s+/', $title );
	for( $i = 0; $i < count( $words ); $i++ ) {
		$word = $words[$i];
	
		if ( $i != 0 ) {
			$output .= ' ';
		}
		
		$output .= '<span class="word' . ($i + 1) . '">';
		
		if ( $letter_char ) {
			$output .= art_lettering_char( $word );
		}
		else {
			$output .= $word;	
		}
		
		$output .= '</span>';
	}
	
	return $output;
}

add_action( 'wp_head', 'art_inline' );

function art_inline( $data ) {
	global $post;
	
	if( is_single() || is_page() ) {
		$css = get_post_meta( $post->ID, 'art_direction_style', true );
		if ( !empty( $css ) ) {
		  $style = "<style>\n" . $css . "\n</style>";
		  echo "<!-- Begin Artsy -->\n" . $style . "\n"; 
			?>
			<script>
			(function() {
				var artsyTitle = null;
				document.addEventListener("DOMContentLoaded", function() {
					artsyTitle = document.getElementById("artsy-title");
				});
				window.addEventListener("resize", function() {
					if (artsyTitle !== null) {
						artsyTitle.style.zIndex = 1;
					}
				});
			})();
			</script>
			<!-- End Artsy -->
			<?php			
		}
	}
}

/* Publish */
add_action( 'publish_page','art_save_postdata' );
add_action( 'publish_post','art_save_postdata' );
add_action( 'save_post','art_save_postdata' );
add_action( 'edit_post','art_save_postdata' );

/* Save Data */
function art_save_postdata( $post_id ) {
	
	// verify this came from the our screen and with proper authorization,
	// because save_post can be triggered at other times
	if ( !wp_verify_nonce( $_POST['art-direction-nonce'], plugin_basename(__FILE__) ) ) {
		return;
	}
	
	if ( 'page' == $_POST['post_type'] && !current_user_can( 'edit_page', $post_id ) ) {
		return;
	}
	else if ( 'post' == $_POST['post_type'] && !current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	
	// OK, we're authenticated: we need to find and save the data
	if( trim( $_POST['style-code'] ) != '' ) {
		update_post_meta( $post_id, 'art_direction_style', $_POST['style-code'] );
	}
	
	update_post_meta( $post_id, 'art_direction_letter_char', isset( $_POST['letter-char'] ) && $_POST['letter-char'] == '1');
	update_post_meta( $post_id, 'art_direction_letter_word', isset( $_POST['letter-word'] ) && $_POST['letter-word'] == '1');
}

/* admin interface */
add_action( 'admin_menu', 'art_add_meta_box' );
add_action( 'admin_head', 'art_admin_head' );

function art_admin_head() { ?>
<style>
#style-code {
	width: 100%;
	height: 300px;
	font-family: Consolas,Monaco,monospace;
	font-size: 13px;
}
</style>

<?php
}
function art_add_meta_box() {
	if( function_exists( 'add_meta_box' ) ) {
		if( current_user_can('edit_posts') ) {
			add_meta_box( 'art-direction-box', __( 'Artsy Styles', 'art-direction' ), 'art_meta_box', 'post', 'normal' );
		}
		        
		if( current_user_can('edit_pages') ) {
			add_meta_box( 'art-direction-box', __( 'Artsy Styles', 'art-direction' ), 'art_meta_box', 'page', 'normal' );
		}
	}
}

function art_meta_box() {
	global $post;
?>
<form action="art-direction_submit" method="get" accept-charset="utf-8">
	<p><em><code>#artsy-title</code> can be used to style this entry's title.</em></p>
	<?php echo '<input type="hidden" name="art-direction-nonce" id="art-direction-nonce" value="' . wp_create_nonce( plugin_basename(__FILE__) ) . '" />'; ?>
	<!-- style code block -->
	<div>
		<textarea id="style-code" name="style-code" rows="8" cols="40"><?php echo esc_attr( get_post_meta( $post->ID, 'art_direction_style', true ) ); ?></textarea>
	</div>
	<div>
		<label><input name="letter-char" type="checkbox" value="1" <?php checked( get_post_meta( $post->ID, 'art_direction_letter_char', true ) ); ?>> Lettering on Title Characters</label>
	</div>
	<div>
		<label><input name="letter-word" type="checkbox" value="1" <?php checked( get_post_meta( $post->ID, 'art_direction_letter_word', true ) ); ?>> Lettering on Title Words</label>		
	</div>
</form>
<?php
}