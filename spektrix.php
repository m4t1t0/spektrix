<?php
/*
Plugin Name: Spektrix
Plugin URI: 
Description: Plugin de integración con Spektrix
Version: 1.0
Author: Rafael Matito
Author URI: 
License: GPL2
*/

// Defines
define('SPEKTRIX_EVENTS_TABLE_NAME', 'spektrix_events');
define('SPEKTRIX_INSTANCES_TABLE_NAME', 'spektrix_instances');
define('SPEKTRIX_SINCRONIZATION_TABLE_NAME', 'spektrix_sincronization');
define('SPEKTRIX_IFRAME_CHOOSE_SEATS_URL', 'https://system.spektrix.com/demoyllana/website/ChooseSeats.aspx');

// Requires
require_once('spektrixexecutor.php');
require_once('spektrix_utils.php');
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

//------------------------------------------------------------------------------
// Functions
//------------------------------------------------------------------------------

//Define more recurrences
function spektrix_more_reccurences() {
	return array(
		'two_hours' => array('interval' => 7200, 'display' => 'Two hours'),
	);
}

//Cron function
function spektrix_two_hours_function() {
  SpektrixExecutor::run(FALSE);
}

//Add custom scripts
function spektrix_add_js() {
  wp_enqueue_script('spektrix', '/wp-content/plugins/spektrix/js/spektrix.js');
}

// Register Admin Options Page
function register_spektrix_options_page() {
	add_options_page('Spektrix Configuration', 'Spektrix', 'manage_options', 'spektrix', 'spektrix_options_page');
}

// Admin Settings Options Page function
function spektrix_options_page() {

	// Check to see if user has adequate permission to access this page
	if (!current_user_can('manage_options')){
      wp_die( __('You do not have sufficient permissions to access this page.') );
    }

?>
<div class="wrap">
    <h2><?php print __('Integración con Spektrix'); ?></h2>
    
    <form method="post" action="">
      
      <?php
      // Proccess form
      if (isset($_POST['spk_op']) && $_POST['spk_op']) {
        switch ($_POST['spk_op']) {
          case __('Sincronizar'):
            SpektrixExecutor::run(TRUE);
            break;
          case __('Limpiar semáforo'):
            SpektrixExecutor::cleanSemaphore();
            break;
          default:
            wp_die( __('Opción no reconocida'));
        }
      }
      else {
        $spk_execution = get_option('spk_execution');
        $spk_last_execution = get_option('spk_last_execution');
        $date_time_format = get_option('date_format') . ' ' . get_option('time_format');
      ?>
      
      <div id="spektrix-admin-info">
        <div>
          <label>Última sincronización:</label>
          <?php if ($spk_execution): ?>
            <span class="spk-execution-active"><?php print __('En proceso'); ?></span>
          <?php else: ?>
            <span class="spk-last-execution"><?php print date($date_time_format, $spk_last_execution); ?></span>
          <?php endif; ?>
        </div>
        <div>
          <?php if ($spk_execution): ?>
            <input id="spk-clean-action" name="spk_op" class="button-primary menu-save" type="submit" value="<?php print __('Limpiar semáforo'); ?>"/>
          <?php else: ?>
            <input id="spk-run-action" name="spk_op" class="button-primary menu-save" type="submit" value="<?php print __('Sincronizar'); ?>"/>
          <?php endif; ?>
        </div>
      </div>
    </form>
    
<?php
     }
}

function spektrix_install() {
  global $wpdb;
  global $wp_rewrite;

  //Create events table
  $table_name = $wpdb->prefix . SPEKTRIX_EVENTS_TABLE_NAME;

  $sql = "CREATE TABLE $table_name (
    id INT(11) NOT NULL DEFAULT 0,
    name VARCHAR(255) NOT NULL DEFAULT '',
    description TEXT NOT NULL DEFAULT '',
    duration TINYINT(3) NOT NULL DEFAULT 0,
    image VARCHAR(255) NOT NULL DEFAULT '',
    thumbnail VARCHAR(255) NOT NULL DEFAULT '',
    proximamente TINYINT(3) NOT NULL DEFAULT 0,
    PRIMARY KEY id (id)
  );";

  dbDelta($sql);

  //Create instances table
  $table_name = $wpdb->prefix . SPEKTRIX_INSTANCES_TABLE_NAME;

  $sql = "CREATE TABLE $table_name (
    id INT(11) NOT NULL DEFAULT 0,
    event_id INT(11) NOT NULL DEFAULT 0,
    time datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
    capacity SMALLINT(6) NOT NULL DEFAULT 0,
    seats_available SMALLINT(6) NOT NULL DEFAULT 0,
    seats_locked SMALLINT(6) NOT NULL DEFAULT 0,
    seats_reserved SMALLINT(6) NOT NULL DEFAULT 0,
    seats_selected SMALLINT(6) NOT NULL DEFAULT 0,
    seats_sold SMALLINT(6) NOT NULL DEFAULT 0,
    PRIMARY KEY id (id)
  );";

  dbDelta($sql);
  
  //Create sincronization data tabla
  $table_name = $wpdb->prefix . SPEKTRIX_SINCRONIZATION_TABLE_NAME;

  $sql = "CREATE TABLE $table_name (
    id INT(11) NOT NULL AUTO_INCREMENT,
    spektrix_id INT(11) NOT NULL DEFAULT 0,
    post_id INT(11) NOT NULL DEFAULT 0,
    type ENUM('event', 'instance', 'tribe') NOT NULL DEFAULT 'event',
    PRIMARY KEY id (id)
  );";
  
  dbDelta($sql);
  
  //Flush rules
	$wp_rewrite->flush_rules();
}

function spektrix_uninstall() {
  global $wpdb;
  global $wp_rewrite;

  //Delete events table
  $table_name = $wpdb->prefix . SPEKTRIX_EVENTS_TABLE_NAME;
  $wpdb->query("DROP TABLE $table_name");

  //Delete instances table
  $table_name = $wpdb->prefix . SPEKTRIX_INSTANCES_TABLE_NAME;
  $wpdb->query("DROP TABLE $table_name");
  
  //Delete sincronization table
  $table_name = $wpdb->prefix . SPEKTRIX_SINCRONIZATION_TABLE_NAME;
  $wpdb->query("DROP TABLE $table_name");
  
  //Flush rules
	$wp_rewrite->flush_rules();
}

function spektrix_create_post_type() {
  //Custom type para eventos de Spektrix
  register_post_type('spektrix_event',
    array(
      'labels' => array(
        'name' => __('Espectáculos'),
        'singular_name' => __('Espectáculo')
      ),
    'public' => true,
    'show_ui' => true,
    'has_archive' => true,
    'rewrite' => array('slug' => 'espectaculos'),
    'supports' => array('title', 'editor', 'custom-fields', 'thumbnail'),
    'show_in_menu' => true,
    'menu_position' => 5,
    'taxonomies' => array('category'),
    )
  );
  
  register_taxonomy_for_object_type('category', 'spektrix_event');

  //Custom type para instancias de Spektrix
  register_post_type('spektrix_instance',
    array(
      'labels' => array(
        'name' => __('Instancias'),
        'singular_name' => __('Instancia')
      ),
    'public' => true,
    'show_ui' => false,
    'has_archive' => true,
    'rewrite' => array('slug' => 'instancias'),
    'supports' => array('title', 'editor', 'custom-fields'),
    )
  );
  
  //Flush rules
  flush_rewrite_rules();
}

function spektrix_delete_post($pid) {
  global $wpdb;
  $sincronization_table_name = $wpdb->prefix . SPEKTRIX_SINCRONIZATION_TABLE_NAME;
  $aux = array();
  $aux[] = $pid;
  $post = get_post($pid);
  if ($post->post_type == 'spektrix_event') {
    //Sacamos sus intancias
    $instances = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}posts WHERE post_parent=$pid AND post_type IN ('spektrix_instance', 'tribe_events')");
    if ($instances) {
      foreach ($instances as $instance) {
        if ($instance->post_type == 'spektrix_instance') {
          $aux[] = $instance->ID;
        }
        wp_delete_post($instance->ID, TRUE);
      }
    }
    
    //Borramos el evento y sus instancias de la tabla de sincronización
    $aux = implode(',', $aux);
    $wpdb->query("DELETE FROM $sincronization_table_name WHERE post_id IN($aux)");
  }
}

function spektrix_publish_event($pid) {
  global $wpdb;
  $instances = spektrix_get_instances_for_event($pid);
  if ($instances) {
    foreach ($instances as $instance) {
      wp_publish_post($instance->ID);
    }
  }
  
  $tribe_events = spektrix_get_tribe_events_for_event($pid);
  if ($tribe_events) {
    foreach ($tribe_events as $te) {
      wp_publish_post($te->ID);
    }
  }
}

function spektrix_trash_event($pid) {
  global $wpdb;
  $instances = spektrix_get_instances_for_event($pid);
  if ($instances) {
    foreach ($instances as $instance) {
      wp_trash_post($instance->ID);
    }
  }
}

function spektrix_draft_event($pid) {
  global $wpdb;
  $instances = spektrix_get_instances_for_event($pid);
  if ($instances) {
    foreach ($instances as $instance) {
      $wpdb->query("UPDATE {$wpdb->prefix}posts SET post_status='draft' WHERE ID={$instance->ID}");
    }
  }
  
  $tribe_events = spektrix_get_tribe_events_for_event($pid);
  if ($tribe_events) {
    foreach ($tribe_events as $te) {
      $wpdb->query("UPDATE {$wpdb->prefix}posts SET post_status='draft' WHERE ID={$te->ID}");
    }
  }
}

function spektrix_query_vars($vars) {
    $vars[] = 'spektrix_comprar_entradas';
    $vars[] = 'spektrix_mostrar_categoria';
    $vars[] = 'spektrix_mostrar_mes';
    
    return $vars;
}

function spektrix_parse_request($wp) {
  if (array_key_exists('spektrix_comprar_entradas', $wp->query_vars)) {
    spektrix_create_iframe_comprar_entradas($wp->query_vars['spektrix_comprar_entradas']);
  }
  if (array_key_exists('spektrix_mostrar_categoria', $wp->query_vars)) {
    spektrix_show_category($wp->query_vars['spektrix_mostrar_categoria']);
  }
  if (array_key_exists('spektrix_mostrar_mes', $wp->query_vars)) {
    spektrix_show_month($wp->query_vars['spektrix_mostrar_mes']);
  }
}

function spektrix_rewrite_rules($wp_rewrite) {
  $new_rules = array(
    'espectaculos/comprar-entradas/([0-9]*)$' => 'index.php?spektrix_comprar_entradas=$matches[1]',
    'ver-espectaculos/genero/(.*)$' => 'index.php?spektrix_mostrar_categoria=$matches[1]',
    'ver-espectaculos/mes/(.*)$' => 'index.php?spektrix_mostrar_mes=$matches[1]',
  );
  $wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
}

function spektrix_show_category($category) {
  global $wpdb;
  global $wp_query;
  
  $category = urldecode($category);
  require_once(get_template_directory() . '/content-spektrix_category.php');
  exit();
}

function spektrix_show_month($month) {
  global $wpdb;
  global $wp_query;
  
  $month = urldecode($month);
  require_once(get_template_directory() . '/content-spektrix_month.php');
  exit();
}

function spektrix_create_iframe_comprar_entradas($param) {
  global $wpdb;
  global $wp_query;

  //Obtenemos el id de Spektrix
  $spektrix_id = $wpdb->get_var("SELECT spektrix_id FROM {$wpdb->prefix}spektrix_sincronization WHERE post_id=".mysql_real_escape_string($param)." AND type = 'instance'");
  
  if (!$spektrix_id) {
    $wp_query->set_404();
    status_header(404);
    get_template_part(404);
    exit();
  }
  else {
    //Cargamos la plantilla
    require_once(get_template_directory() . '/content-spektrix_iframe.php');
    exit();
  }
}

//------------------------------------------------------------------------------
// Wordpress hooks and actions
//------------------------------------------------------------------------------

// Add Admin Options Page
add_action('admin_menu', 'register_spektrix_options_page');

//Create tables on activation
register_activation_hook(__FILE__,'spektrix_install');

//Delete tables on desactivation
register_deactivation_hook(__FILE__,'spektrix_uninstall');

//Add custom post types
add_action('init', 'spektrix_create_post_type');

//After delete an event delte all it's intances
add_action('delete_post', 'spektrix_delete_post');

//After publish an event publish all it's intances
add_action ('publish_spektrix_event', 'spektrix_publish_event');

//After trash an event trash all it's intances
add_action ('trash_spektrix_event', 'spektrix_trash_event');

//After draft an event draft all it's intances
add_action ('draft_spektrix_event', 'spektrix_draft_event');

//Add custom scripts
add_action('admin_print_scripts', 'spektrix_add_js');

//More recurrences
add_filter('cron_schedules', 'spektrix_more_reccurences');

//Execute cron
if (!wp_next_scheduled('spektrix_two_hours_function_hook')) {
	wp_schedule_event(time(), 'two_hours', 'spektrix_two_hours_function_hook');
}

add_action('spektrix_two_hours_function_hook', 'spektrix_two_hours_function' );

if(!defined('DOING_CRON')) {
  add_action('init', 'wp_cron');
}

//Custom path
add_action('parse_request', 'spektrix_parse_request');
add_filter('query_vars', 'spektrix_query_vars');
add_action('generate_rewrite_rules', 'spektrix_rewrite_rules');