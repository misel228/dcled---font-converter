<?php
define('IMAGE', 'image');
define('TEXT', 'text');

#valid input types
$input = array('png'=>IMAGE,'gif'=>IMAGE,'txt'=>TEXT);
$output = array('png'=>IMAGE,'gif'=>IMAGE,'txt'=>TEXT);
#powers of two - (just being lazy with the pow statement)
$powers = array(1,2,4,8,16,32,64,128,256);


#necessary parameters for processing
$params = array(
	'input_type' => null,
	'input_file' => null,
	'output_type' => null,
	'output_file' => null,
);
#required image dimensions
define('IMAGE_WIDTH', 2048);
define('IMAGE_HEIGHT', 7);


#parse parameters
$options = getopt('i:f:o:F:h');
if($options['h']) {
	print_usage();
}
if(!in_array($options['i'], array_keys($input))) {
	printf("Unknown input option\n");
	print_usage();
}
if(!in_array($options['o'], array_keys($output))) {
	printf("Unknown output option\n");
	print_usage();
}
if(!isset($options['f'])) {
	printf("No input file given\n");
	print_usage();
}
if(!isset($options['F'])) {
	printf("No output file given\n");
	print_usage();
}

#open input file
if(!is_file($options['f'])) {
	printf("File does not exist: %s\n", $options['f']);
	print_usage();
}

$font_data = array();
if($input[$options['i']] == TEXT) {
	$font_data = text_to_array($options['f']);
} else {
	#when image - check dimensions
	$dim = getimagesize($options['f']);
	#var_dump($dim);
	if($dim[0]==IMAGE_WIDTH && $dim[1]==IMAGE_HEIGHT) {
		switch($options['i']) {
			case 'png': $image = imagecreatefrompng ( $options['f'] ); break;
			case 'gif': $image = imagecreatefrompng ( $options['f'] ); break;
			default: die('You should not get here');
		}
		$font_data = image_to_array($image);
	} else { 
		printf("File \"%s\" has dimensions %dx%d but should be %dx%d\n", $options['f'],$dim[0],$dim[1],IMAGE_WIDTH , IMAGE_HEIGHT);
		print_usage();
	}
}



if($output[$options['o']] == TEXT) {
	$output = to_text($font_data);
	$result = file_put_contents ( $options['F'] , $output );
	if($result===false) {
		printf('Could not write to file "%s"'."\n", $options['F']);
	}
	return 0;

} else {
	$image = to_image($font_data);
	
	if($image) {
		switch($options['o']) {
			case 'png': $image = imagepng ( $image, $options['F'] ); break;
			case 'gif': $image = imagegif ( $image, $options['F'] ); break;
			default: die('You should not get here');
		}
		if(!$image) {
			printf("Could not write image file: \"%s\"",$options['F']);
		}
	} else { 
		printf("Could not create image!\n");
	}
}

die('END OF PROGRAM'."\n");

function print_usage() {
	echo 'font convert'."\n\n";
	echo 'Parameters:'."\n";
	echo "-f\tinput file\n";
	echo "-F\toutput file\n";
	echo "-i\tinput type ('txt', 'png', 'gif')\n";
	echo "-o\toutput type ('txt', 'png', 'gif')\n";
#	echo 'TODO: How to use this program'."\n";
	die();
}

#write array to image resource
function to_image($font_data) {
	$image = imagecreate(IMAGE_WIDTH, IMAGE_HEIGHT);
	$black = imagecolorallocate ( $image , 0 , 0 , 0);
	$white = imagecolorallocate ( $image , 0xFF , 0xFF , 0xFF);

	foreach($font_data as $key => $letter) {
		draw_letter($image,$letter,$key, $black, $white);
	}
	return $image;
}

#draw each letter separately
function draw_letter( $image, $letter, $base_x, $black, $white ) {
	global $powers;
	foreach($letter as $line_nr => $line) {
		$new_line = array();
		foreach($powers as $power_nr => $power) {
			if($power & $line) {
				$color = $black;
			} else {
				$color = $white;
			}
			imagesetpixel ( $image , $base_x*8 + $power_nr , $line_nr , $color );
		}
	}
}

#write array to C-code
function to_text($font_data) {
	$output = '';
	global $powers;

	foreach($font_data as $letter) {
		$values = array();
		foreach($letter as $key => $value) {
			$values[]= sprintf("0x%02X",$value);
		}
		$line = '{ ';
		$line .= implode(', ',$values);
		$line .= ' },';
		$output .= $line ."\n";
	}
	return $output;
}

#read from text file and create an internal array
function text_to_array($file) {
	$font_data = array();
	$content_st = file_get_contents($file);
	$content_st = str_replace("\r","",$content_st);
	$content = explode("\n", $content_st);
	foreach($content as $line) {
		$font_line = array();
		$my_line = str_replace(array("{","}"),"", $line);
		$my_values = explode(',',$my_line);
		foreach($my_values as $value) {
			if(trim($value)=="") continue;
			array_push($font_line, intval(trim($value),16));
		}
		array_push($font_data, $font_line);
	}
	return $font_data;
}

#read from an image resource and create an internal array
function image_to_array($image) {
	$output = array();
	global $powers;
	$black = array("red"=>0,"green"=>0,"blue"=>0,"alpha"=>0);

	$counter = 0;
	for( $i = 0 ; $i < IMAGE_WIDTH; $i+=8 ) {
		#echo $i;
		$values = array();
		$line = array();

		for( $j = 0 ; $j < IMAGE_HEIGHT; $j++ ) {
			$value = 0;
			for( $k = 0 ; $k < 8 ; $k++ ) {
				$x = $i + $k;
				$y = $j;
				$pixel = imagecolorat($image, $x, $y);
				$color = imagecolorsforindex($image, $pixel);
				if($color == $black) {
					$value |= $powers[$k];
				}
			}
			array_push($line, $value);
		}
		array_push($output, $line);
	}
	return $output;
}
