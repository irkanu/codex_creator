<?php



function codex_creator_get_file_docblock($lines){
	if(empty($lines)){return false;}

	$i = 0;
	$start = '';
	$end = '';
	$docblock = '';

	// find the starting point if any
	foreach($lines as $line=>$value){
		if($i==5){return false;}// if we dont find a file opening block within the first 5 lines then bail

		if(strpos($value,'/**') !== false){
			$start = $line;break;
		}
		$i++;
	}

	// find the end point
	foreach($lines as $line=>$value){

		if(strpos($value,'*/') !== false){
			$end = $line;break;
		}
	}

	if($start && $end){

		while($start <= $end) {
			$docblock .= $lines[$start];
			$start++;
		}

	}

	if($docblock){return $docblock;}

	return false;// if we get here something has gone wrong so bail

}


function codex_creator_has_codex( $el ) {
	$term = term_exists($el, 'codex_project');
	if ($term !== 0 && $term !== null) {
		return true;
	}else{
		return false;
	}
}

function codex_creator_status_text( $type, $el ) {
	$term = codex_creator_has_codex( $el );

	echo '<div class="cc-add-update-project-bloc">';
	if ( $term ) {
		?>

		<h4><?php _e( 'It looks like this project is already added, select an option below.', WP_CODEX_TEXTDOMAIN ); ?></h4>
		<span onclick="codex_creator_sync_project_files('<?php echo $type;?>','<?php echo $el;?>');" class="cc-add-project-btn"><?php _e( 'Sync all files', WP_CODEX_TEXTDOMAIN ); ?></span>

	<?php

	} else {?>

		<h4><?php _e( 'It looks like this is a new project, please click below to add it.', WP_CODEX_TEXTDOMAIN ); ?></h4>
		<span onclick="codex_creator_add_project('<?php echo $type;?>','<?php echo $el;?>');" class="cc-add-project-btn"><?php _e( 'Add new project', WP_CODEX_TEXTDOMAIN ); ?></span>

	<?php
	}
	echo '</div>';
}


function codex_creator_is_allowed_file( $file ) {
	$allowed = codex_creator_allowed_file_types();
	$ext = pathinfo($file, PATHINFO_EXTENSION);
	if(in_array($ext, $allowed)){
		return true;
	}
	return false;
}