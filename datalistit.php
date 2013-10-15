<?php
/*
 Plugin Name: Datalist
 Plugin URI:
 Description: Display csv file to html table
 Version: 0.0.1
 Author: Mathias Gorenflot
 Author URI: http://cezarion.net
 */
?>
<?php

error_reporting(E_ERROR | E_WARNING | E_PARSE);

define("DIR_TABLES", dirname(__FILE__).DIRECTORY_SEPARATOR."tables".DIRECTORY_SEPARATOR);
define("URI_TABLES", plugins_url( '/tables'.DIRECTORY_SEPARATOR , __FILE__ )  );
define("FILE_EXT", '.json');

function convert_to ( $input , $encoding = 'UTF-8' )
{
	$current_encoding = mb_detect_encoding( $input );
	if( $current_encoding !== $encoding )
		return iconv( $current_encoding , 'UTF-8', $input );
	else
	      return $input;
}

function datalist_tabledata($file) {
	$row = 0;
	if (($handle = fopen( $file, "r")) !== FALSE)
	{
		$response = array();
		while (($data = fgetcsv($handle, 2000, ",")) !== FALSE)
		{
			if($row === 0)
			{
				$cols = count($data);
				$header=array();
				for( $c = 0 ; $c < $cols ; $c++ )
				{
					$header[ sanitize_title( $data[$c] ) ] = convert_to( $data[$c] );
				}
				$response['header'] = $header;
				$header_keys = array_keys($header);
			}
			else
			{
				$data = array_map('convert_to' , $data);
				$response['contents'][] = array_combine( $header_keys, $data ) ;
			}
			$row++;
		}
		fclose($handle);
		return json_encode($response);
	}
	return false;
}

function isSupportedFile( $ext )
{
	$ext = strtolower($ext);
	//return $ext == 'xls' || $ext == 'xlsx' || $ext == 'csv' ;
	return $ext == 'json' || $ext == 'csv' ;
}

function datalist_create_table() {
	if(isset($_POST['deletecheck'])) {
		$filesToDelete = $_POST['deletecheck'];
		foreach($filesToDelete as $filename) {
			$realNameFile= DIR_TABLES.$filename;
			if(file_exists($realNameFile)){
				unlink($realNameFile);
			}
		}
	}

	$uploadFileName = $_FILES['upload']['name'];
	$uploadedFile = FALSE;
	if(isset( $_FILES['upload']) && $uploadFileName != "" )
	{
		$path_parts = pathinfo( $uploadFileName );
		$uploadFileName=basename($_FILES['upload']['name']);

		if ( !isSupportedFile( $path_parts['extension'])  )
		{
			$retours =  "<strong>Wrong file format: ".$uploadFileName."</strong>";
		} else {
			$uploadFileName = sanitize_file_name($uploadFileName);
			$tablesDirectory = DIR_TABLES.$uploadFileName;
			if ( move_uploaded_file ($_FILES['upload']['tmp_name'] , $tablesDirectory) )
			{
				$retours = "<strong>Success: ".$uploadFileName." uploaded</strong>";
				$path_parts = pathinfo( $tablesDirectory );
				if ( isSupportedFile( $path_parts['extension']  ))
				{
					$output = datalist_tabledata($tablesDirectory);
					if ( $output )
					{
						$table_name = DIR_TABLES.$path_parts['filename'].FILE_EXT;
						if( !file_put_contents($table_name, $output))
						{
							echo "<strong>Something went wrong!</strong >";
						}
						if(	file_exists($tablesDirectory)){
							unlink($tablesDirectory);
						}
					}
					else
					{
						$retours = "<strong>Something went wrong. Unable to open file!</strong >";
					}
				}
			}
			else
			{
				$retours = "<strong>Error: ".$uploadFileName." not uploaded</strong>";
			}
		}
	}

	$tables = glob(DIR_TABLES."*");

	foreach($tables as $table) {


	}
?>

<div class="wrap">
<div id='icon-options-general' class='icon32'><br />
</div>

<h2>Datalist <small>manage your data</small> </h2>
<br/>
<?php echo $retours; ?>
<form id="champ-settings" enctype="multipart/form-data" action="" method="post">
<br/><table  class="widefat">
  			<thead>
  				<tr>
					<th scope="col" width="15%">Click to remove</th>
					<th scope="col" >File name</th>
					<th scope="col" >Short code</th>
				</tr>
  			</thead>
  			<tbody>
			<?php

			$tables = glob(DIR_TABLES."*");
			if ($tables) {
				$cont =0;
				foreach($tables as $table) {
					$path_parts = pathinfo( $table );
					$fileName = $path_parts['basename'];
					$nameJson = $path_parts['filename'].FILE_EXT;
			?>
				    <tr>
						<th scope="row" class="check-column">
						<input type="checkbox" name="deletecheck[<?php echo $cont; ?>]" value="<?php echo $nameJson;?>"/></th>
						<td><?php echo $nameJson;?></td>
						<td>[datalist table="<?php echo urlencode($path_parts['filename']);?>"]</td>
					</tr>
	 		<?php
				$cont ++;
				}
	 		} else {
			?>
				<tr id='no-id'><td scope="row" colspan="5"><em>No files found </em></td></tr>
			<?php } ?>
			</tbody>
		</table>
		Choose file to upload: <input type="file" name="upload" >
		<br>
<input type="submit" class="button-secondary action" id="doaction" name="" value="GO"/>
</form>
<?php
}

function datalist_show_submenu() {
    add_object_page('Datalist','CSV Datalist','edit_posts','datalist','datalist_create_table');
}

class Datalist_Shortcode {
	static $add_script;

	static function init() {
		add_shortcode('datalist', array(__CLASS__, 'handle_shortcode'));

		add_action('init', array(__CLASS__, 'register_script'));
		add_action('wp_footer', array(__CLASS__, 'print_script'));
		add_action('wp_footer', array(__CLASS__, 'load_css'));
	}

	static function datalist_create_row_items( $rows , $balise = 'td' )
	{
		$items = '';
		foreach( $rows as $class => $value )
		{
			$items .= '					<'.$balise.' class="datalist-'.$class.'">'.mb_convert_case($value, MB_CASE_TITLE, "UTF-8").'</'.$balise.'>';
		}
		return $items;
	}

	static function handle_shortcode($atts) {
		self::$add_script = true;

		$filename = urldecode($atts['table']);
		$file =DIR_TABLES.$filename.FILE_EXT;

		if(file_exists($file))
		{
			$json = file_get_contents($file );
			$json_url = URI_TABLES.$filename.FILE_EXT;

			$array = json_decode($json , true );

			$header = $array['header'];
			$datas 	  =  $array['contents'];

			$length  = count($datas);
			$start = key($datas);
			$nb_cells = count($datas);

			ob_start();
?>
<div class="datalist-loader" style="width: 100%; height: 100px; background: url('<?php echo plugins_url('css/ajax-loader.gif', __FILE__); ?>') no-repeat scroll 50% 50% transparent;"></div>
	<div class="node-search-container form-inline hide">
		<div class="input-append">
			<input id="node-search" type="text" placeholder="Rechercher" data-src="<?php echo $json_url; ?>"/>
			<label for="node-search" class="hide">Rechercher</label>
			<button id="node-clear" class="btn btn-small" type="button"><i class="icon-remove" title="Effacer"></i></button>
		</div>
	</div>
	<table id="datalist" class="table table-bordered table-condensed hide">
		<thead>
			<tr>
				<?php echo self::datalist_create_row_items( $header , 'th' ); ?>
			</tr>
		</thead>
		<tbody>
			<?php for ( $cell = $start ; $cell < $nb_cells ; $cell++ ): ?>
				<tr>
					<?php echo self::datalist_create_row_items( $datas[$cell] ); ?>
				</tr>
			<?php endfor; ?>
		</tbody>
	</table>
<?php
			return ob_get_clean();
		}
		else
		{
			return 'nothing found !';
		}
		//
	}

	static function register_script() {
		wp_enqueue_script( 'jquery-ui-autocomplete' );
		wp_register_script('datalist-script', plugins_url('js/datalist.js', __FILE__), array('jquery'), '1.0', true);
	}

	static function print_script() {
		if ( ! self::$add_script )
			return;

		wp_print_scripts( 'jquery-ui-autocomplete' );
		wp_print_scripts('datalist-script');
	}

	static function load_css() {
		if ( ! self::$add_script )
			return;

		wp_enqueue_style( 'datalist-css',  plugins_url('css/datalist.css', __FILE__) );
	}
}

Datalist_Shortcode::init();

add_action('admin_menu','datalist_show_submenu');
?>
