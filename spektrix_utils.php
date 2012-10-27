<?php
/*
File: spektrix_utils.php
Description: Funciones auxiliares para el plugin de Spektrix
*/

function spektrix_month_name($id) {
  $meses = array('Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre');
  return __($meses[$id-1]);
}

function spektrix_get_instances_for_event($event_id) {
  global $wpdb;
  $instances = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_parent=$event_id AND post_type='spektrix_instance'");
  return $instances; 
}

function spektrix_get_tribe_events_for_event($event_id) {
  global $wpdb;
  $tribe_events = $wpdb->get_results("SELECT ID FROM {$wpdb->prefix}posts WHERE post_parent=$event_id AND post_type='tribe_events'");
  return $tribe_events; 
}

function spektrix_get_data_instances_for_event($event_id) {
  global $wpdb;
  $instances = $wpdb->get_results("SELECT p.ID, p.post_title, pm.meta_value AS seats_available 
                                   FROM {$wpdb->prefix}posts p 
                                   INNER JOIN {$wpdb->prefix}postmeta pm on (p.ID=pm.post_id)
                                   WHERE p.post_parent=$event_id AND p.post_type='spektrix_instance'
                                   AND pm.meta_key='spektrix_seats_available'
                                   ORDER BY p.post_title"
                                 );
  return $instances;
}

function spektrix_get_last_events($limit = 8) {
  global $wpdb;
  
  $now = date('Y-m-d H:i:s');
  $event_ids = $wpdb->get_col("SELECT DISTINCT post_parent FROM {$wpdb->prefix}posts
                               WHERE post_type = 'spektrix_instance'
                               AND post_status = 'publish'
                               AND post_title > '".$now."'
                               ORDER BY post_title ASC
                               LIMIT $limit"
                              );
  
  return $event_ids;
}

function spektrix_get_next_instances_for_event($event_id, $date = '', $limit = 10) {
  global $wpdb;
  if (!$date) {
    $date = date('Y-m-d H:m:s');
  }
  
  $instances = $wpdb->get_results("SELECT p.ID, p.post_title, pm.meta_value AS seats_available 
                                   FROM {$wpdb->prefix}posts p 
                                   INNER JOIN {$wpdb->prefix}postmeta pm on (p.ID=pm.post_id)
                                   WHERE p.post_parent=$event_id AND p.post_type='spektrix_instance'
                                   AND pm.meta_key='spektrix_seats_available'
                                   AND p.post_title > '".$date."'
                                   ORDER BY p.post_title
                                   LIMIT $limit"
                                 );
                                   
  return $instances;
}

function spektrix_get_post_name($str) {
  setlocale(LC_ALL, "es_ES");
  $str = strtolower($str);
  $str = str_replace(array(' ', '_'), '-', $str);
  return $str;
}

function spektrix_suma_fechas($fecha, $tiempo) {
  $new_date = strtotime("+ $tiempo minutes", strtotime($fecha));
  return date('Y-m-d H:i:s', $new_date);
}

function spektrix_get_next_events_by_month($month) {
  global $wpdb;
  
  $first_day = $month . '-01 00:00:00';
  $last_day = $month . '-' . date('t', strtotime($first_day)) . ' 23:59:59';
  
  $event_ids = $wpdb->get_col("SELECT DISTINCT post_parent FROM {$wpdb->prefix}posts
                               WHERE post_type = 'spektrix_instance'
                               AND post_status = 'publish'
                               AND post_title > '$first_day'
                               AND post_title < '$last_day'
                               ORDER BY post_title DESC"
                              );
  
  return $event_ids;
}

function spektrix_get_next_events_range() {
  global $wpdb;
  
  $proximo_mes = date('Y-m-d H:i:s', strtotime('+1 month', strtotime(date('Y-m-01'))));
  $event_titles = $wpdb->get_col("SELECT post_title FROM {$wpdb->prefix}posts
                               WHERE post_type = 'spektrix_instance'
                               AND post_status = 'publish'
                               AND post_title > '$proximo_mes'
                               ORDER BY post_title ASC"
                              );
  
  $range = array();
  foreach ($event_titles as $et) {
    $range[] = substr($et, 0, 7);
  }
  $range = array_values(array_unique($range));
  
  return $range;
}

function spektrix_date_format($date, $tipo = 'todo') {
  $dias = array("Dom","Lun","Mar","Mie","Jue","Vie","Sáb");
  $meses = array("ene","feb","mar","abr","may","jun","jul","ago","sep","oct","nov","dic");
  
  $stamp = strtotime($date);
  
  switch ($tipo) {
    case 'dia':
      return $dias[date('w', $stamp)] . " " . date('d', $stamp) . " " . $meses[date('n', $stamp)-1];
      break;
    case 'hora':
      return date('H:i', $stamp);
      break;
    case 'todo':
      return $dias[date('w', $stamp)] . " " . date('d', $stamp) . " " . $meses[date('n', $stamp)-1] . " " . date('H:i', $stamp);
      break;
    default:
      return $dias[date('w', $stamp)] . " " . date('d', $stamp) . " " . $meses[date('n', $stamp)-1] . " " . date('H:i', $stamp);
  }
}

function spektrix_get_proximamente_events() {
  global $wpdb;
  
  $event_ids = $wpdb->get_col("SELECT post_id FROM {$wpdb->prefix}posts p
                               INNER JOIN {$wpdb->prefix}postmeta pm on (p.ID = pm.post_id)
                               WHERE p.post_status = 'publish'
                               AND pm.meta_key = 'spektrix_proximamente'
                               AND pm.meta_value = '1'
                               ORDER BY p.ID"
                             );
                               
  return $event_ids;
}

function spektrix_get_category_range($category) {
  global $wpdb;
  
  $event_ids = _spektrix_get_events_by_category($category);
  if ($event_ids) {
    //Sacamos la cadena
    $str_event_ids = implode(',', $event_ids);  
                             
    $now = date('Y-m-d H:i:s');
    $event_titles = $wpdb->get_col("SELECT post_title FROM {$wpdb->prefix}posts
                                  WHERE post_type = 'spektrix_instance'
                                  AND post_status = 'publish'
                                  AND post_title > '$now'
                                  AND post_parent in ($str_event_ids)
                                  ORDER BY post_title ASC"
                                  );
      
    $range = array();
    foreach ($event_titles as $et) {
      $range[] = substr($et, 0, 7);
    }
    $range = array_values(array_unique($range));
  
    return $range;
  }
  else {
    return array();
  }
}

function spektrix_get_events_by_category_month($category, $month) {
  global $wpdb;
  
  $first_day = $month . '-01 00:00:00';
  $last_day = $month . '-' . date('t', strtotime($first_day)) . ' 23:59:59';
  
  $parent_event_ids = _spektrix_get_events_by_category($category);
  if ($parent_event_ids) {
    //Sacamos la cadena
    $str_parent_event_ids = implode(',', $parent_event_ids);
  
    $event_ids = $wpdb->get_col("SELECT DISTINCT post_parent FROM {$wpdb->prefix}posts
                                 WHERE post_type = 'spektrix_instance'
                                 AND post_status = 'publish'
                                 AND post_title > '$first_day'
                                 AND post_title < '$last_day'
                                 AND post_parent in ($str_parent_event_ids)
                                 ORDER BY post_title DESC"
                               );
  
    return $event_ids;
  }
  else {
    return array();
  }
}
  
function _spektrix_get_events_by_category($category) {
  global $wpdb;

  $term_taxonomy_id = $wpdb->get_var("SELECT tt.term_taxonomy_id FROM {$wpdb->prefix}terms t
                                      INNER JOIN {$wpdb->prefix}term_taxonomy tt ON (t.term_id = tt.term_id)
                                      WHERE t.slug = '{$wpdb->escape($category)}'");

  $event_ids = $wpdb->get_col("SELECT distinct p.ID FROM {$wpdb->prefix}term_relationships tr
                               INNER JOIN {$wpdb->prefix}posts p on (tr.object_id = p.ID)
                               WHERE p.post_type = 'spektrix_event'
                               AND p.post_status = 'publish'
                               AND tr.term_taxonomy_id = $term_taxonomy_id");

  return $event_ids;
}