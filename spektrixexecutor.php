<?php

class SpektrixExecutor {
  
  const INFO = 1;
  const WARNING = 2;
  const ERROR = 3;  
  const SPEKTRIX_URL = 'https://system.spektrix.com/demoyllana/api/v1/eventsrestful.svc';
  
  static function run($interactive = FALSE) {
    global $wpdb;

    update_option('spk_execution', '1');
    self::message(__('Comenzando ejecución'), self::INFO, $interactive);
    
    $events_table_name = $wpdb->prefix . SPEKTRIX_EVENTS_TABLE_NAME;
    $instances_table_name = $wpdb->prefix . SPEKTRIX_INSTANCES_TABLE_NAME;
    $sincronization_table_name = $wpdb->prefix . SPEKTRIX_SINCRONIZATION_TABLE_NAME;

    //Borramos los datos existentes para sólo tratar los que actualmente están en Spektrix
    $wpdb->query("TRUNCATE TABLE $events_table_name");
    $wpdb->query("TRUNCATE TABLE $instances_table_name");

    self::message(__('Obteniendo eventos de Spektrix'), self::INFO, $interactive);
    
    //Obtenemos todos los eventos
    if ($xmlData = self::GetAllInstancesFrom()) {
      $data = new SimpleXMLElement($xmlData);

      if ($data->Event) {
        foreach ($data->Event as $event) {
          
          $aux = array();
          $aux['id'] = (int) $event->Id;
          $aux['name'] = (string) $event->Name;
          $aux['description'] = (string) $event->Description;
          $aux['duration'] = (int) $event->Duration;
          $aux['image'] = (string) $event->ImageUrl;
          $aux['thumbnail'] = (string) $event->ThumbnailUrl;
          
          if ($event->Attributes->EventAttribute) {
            foreach ($event->Attributes->EventAttribute as $eva) {
              
              //Metemos el atributo próximamente, que es el único que utilizamos
              if ($eva->Name == 'Proximamente') {
                $aux['proximamente'] = (int) $eva->Value; 
              }
              
            }
          }

          $wpdb->insert($events_table_name, $aux);
          self::message(__('Evento insertado:') . ' ' . $event->Name, self::INFO, $interactive);

          //Insertamos todas las instancias de ese evento
          if ($event->Times) {
            foreach ($event->Times->EventTime as $time) {
              $aux_2 = array();
              $aux_2['id'] = (int) $time->EventInstanceId;
              $aux_2['event_id'] = (int) $event->Id;
              $aux_2['time'] = (string) str_replace('T', ' ', $time->Time);
              $aux_2['capacity'] = (int) $time->Capacity;
              $aux_2['seats_available'] = (int) $time->SeatsAvailable;
              $aux_2['seats_locked'] = (int) $time->SeatsLocked;
              $aux_2['seats_reserved'] = (int) $time->SeatsReserved;
              $aux_2['seats_selected'] = (int) $time->SeatsSelected;
              $aux_2['seats_sold'] = (int) $time->SeatsSold;

              $wpdb->insert($instances_table_name, $aux_2);
              self::message(__('Instancia insertada:') . ' ' . $time->Time, self::INFO, $interactive);
            } 
          }
        }
      }
    }
    else {
      self::message(__('Error al acceder a la información de Spektrix'), self::ERROR, $interactive);
    }
    
    //Insertamos todos los eventos como POST de Wordpress
    $spektrix_events = $wpdb->get_results("SELECT * FROM $events_table_name");
    if ($spektrix_events) {
      foreach ($spektrix_events as $se) {
        //Buscamos si ya hemos insertado anteriormente ese evento
        $current_post_id = $wpdb->get_var("SELECT post_id FROM $sincronization_table_name WHERE spektrix_id = {$se->id} AND type = 'event'");
        
        if ($current_post_id) {
          $my_event = array(
            'ID' => $current_post_id,
            'post_title' => $se->name,
          );
          
          //Actualizamos el post
          $event_post_id = wp_update_post($my_event);
          update_post_meta($event_post_id, 'spektrix_proximamente', $se->proximamente);
          self::message(__('Post de evento actualizado:') . ' ' . $se->name, self::INFO, $interactive);
          
          //Obtenemos el post como un objeto
          $event_post = get_post($event_post_id);
          
          //Actualizamos también todas sus instancias
          $spektrix_instances = $wpdb->get_results("SELECT * FROM $instances_table_name WHERE event_id = {$se->id}");
          if ($spektrix_instances) {
            $this_event_instances = array();
            $this_event_tribes = array();
            foreach ($spektrix_instances as $si) {
              //Buscamos si ya hemos insertado anteriormente esa instancia
              $current_post_id = $wpdb->get_var("SELECT post_id FROM $sincronization_table_name WHERE spektrix_id = {$si->id} AND type = 'instance'");
              
              if ($current_post_id) {
                $my_instance = array(
                  'ID' => $current_post_id,
                  'post_title' => $si->time,
                );
                
                //Actualizamos la instancia
                $instance_post_id = wp_update_post($my_instance);
                update_post_meta($instance_post_id, 'spektrix_seats_available', $si->seats_available);
                self::message(__('Post de instancia actualizada:') . ' ' . $si->time, self::INFO, $interactive);
                
                //Guardamos el id de la instancia, pues después borraremos las que no han venido
                $this_event_instances[] = $instance_post_id;
              }
              else {
                //Insertamos la nueva instancia para el evento
                $my_instance = array(
                  'post_title' => $si->time,
                  'post_status' => $event_post->post_status,
                  'post_type' => 'spektrix_instance',
                  'post_parent' => $event_post_id,
                );
                
                //Insertamos el post
                $instance_post_id = wp_insert_post($my_instance);
                add_post_meta($instance_post_id, 'spektrix_seats_available', $si->seats_available, TRUE);
                self::message(__('Post de instancia insertado:') . ' ' . $si->time, self::INFO, $interactive);
                
                //Guardamos el id de la instancia, pues después borraremos las que no han venido
                $this_event_instances[] = $instance_post_id;
              
                //Insertamos también los datos en la tabla de relaciones
                if ($instance_post_id) {
                  $aux = array(
                    'id' => 0,
                    'spektrix_id' => $si->id,
                    'post_id' => $instance_post_id,
                    'type' => 'instance',
                  );
                  $wpdb->insert($sincronization_table_name, $aux);
                }
              }
              
              //Buscamos si ya hemos insertado anteriormente ese tribe
              $current_post_id = $wpdb->get_var("SELECT post_id FROM $sincronization_table_name WHERE spektrix_id = {$si->id} AND type = 'tribe'");
              
              if ($current_post_id) {
                $my_tribe_event = array(
                  'ID' => $current_post_id,
                  'post_title' => $my_event['post_title'],                  
                );
              
                //Actualizamos el post
                $tribe_event_post_id = wp_update_post($my_tribe_event);
                update_post_meta($tribe_event_post_id, '_EventShowMapLink', 'false');
                update_post_meta($tribe_event_post_id, '_EventShowMap', 'false');
                update_post_meta($tribe_event_post_id, '_EventStartDate', $si->time);
                update_post_meta($tribe_event_post_id, '_EventDuration', $se->duration);              
                update_post_meta($tribe_event_post_id, '_EventEndDate', spektrix_suma_fechas($si->time, $se->duration));
                self::message(__('Evento de calendario actualizado:') . ' ' . $si->time, self::INFO, $interactive);
                
                //Guardamos el id del tribe, pues después borraremos los que no han venido
                $this_event_tribes[] = $tribe_event_post_id;
              }
              else {
                //Insertamos los eventos asociados para mostrarlos en el calendario
                $my_tribe_event = array(
                  'post_title' => $my_event['post_title'],
                  'post_status' => $event_post->post_status,
                  'post_type' => 'tribe_events',
                  'post_author' => 1,
                  'post_name' => spektrix_get_post_name($my_event['post_title']),
                  'post_parent' => $event_post_id,
                );
              
                //Insertamos el post
                $tribe_event_post_id = wp_insert_post($my_tribe_event);
                add_post_meta($tribe_event_post_id, '_EventShowMapLink', 'false', TRUE);
                add_post_meta($tribe_event_post_id, '_EventShowMap', 'false', TRUE);
                add_post_meta($tribe_event_post_id, '_EventStartDate', $si->time, TRUE);
                add_post_meta($tribe_event_post_id, '_EventDuration', $se->duration, TRUE);              
                add_post_meta($tribe_event_post_id, '_EventEndDate', spektrix_suma_fechas($si->time, $se->duration), TRUE);
                self::message(__('Evento de calendario insertado:') . ' ' . $si->time, self::INFO, $interactive);
                
                //Guardamos el id del tribe, pues después borraremos los que no han venido
                $this_event_tribes[] = $tribe_event_post_id;
                
                //Insertamos también los datos en la tabla de relaciones
                if ($tribe_event_post_id) {
                  $aux = array(
                    'id' => 0,
                    'spektrix_id' => $si->id,
                    'post_id' => $tribe_event_post_id,
                    'type' => 'tribe',
                  );
                  $wpdb->insert($sincronization_table_name, $aux);
                }
              }
            }
            
            if ($this_event_instances) {
              $lst_this_event_instances = implode(',', $this_event_instances);
              
              //Borrar los post de las instancias que ya no pertenecen al evento
              $instances_to_delete = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts 
                                                     WHERE post_parent = $event_post_id
                                                     AND post_type = 'spektrix_instance'
                                                     AND ID NOT IN ($lst_this_event_instances)");
              if ($instances_to_delete) {
                foreach ($instances_to_delete as $itd) {
                  //Borramos el post
                  wp_delete_post($itd);
                  self::message(__('Borrada instancia:') . ' ' . $itd, self::INFO, $interactive);
                  
                  //Borramos también los datos en la tabla de relaciones
                  $wpdb->query("DELETE FROM $sincronization_table_name WHERE post_id = $itd");
                }
              }
            }
            
            if ($this_event_tribes) {
              $lst_this_event_tribes = implode(',', $this_event_tribes);
              
              //Borrar los post de las instancias que ya no pertenecen al evento
              $tribes_to_delete = $wpdb->get_col("SELECT ID FROM {$wpdb->prefix}posts 
                                                     WHERE post_parent = $event_post_id
                                                     AND post_type = 'tribe_events'
                                                     AND ID NOT IN ($lst_this_event_tribes)");
              if ($tribes_to_delete) {
                foreach ($tribes_to_delete as $ttd) {
                  //Borramos el post
                  wp_delete_post($ttd);
                  self::message(__('Borrado tribe event:') . ' ' . $ttd, self::INFO, $interactive);
                }
              }
            }
            
          }
        }
        else {
          $my_event = array(
            'post_title' => $se->name,
            'post_status' => 'draft',
            'post_type' => 'spektrix_event',
            'post_content' => $se->description,
            'post_author' => 1, //Mirar con qué autor deberían crearse los POST
          );
        
          //Insertamos el post
          $event_post_id = wp_insert_post($my_event);
          add_post_meta($event_post_id, 'spektrix_proximamente', $se->proximamente, TRUE);
          self::message(__('Post de evento insertado:') . ' ' . $se->name, self::INFO, $interactive);
        
          //Insertamos también los datos en la tabla de relaciones
          if ($event_post_id) {
            $aux = array(
              'id' => 0,
              'spektrix_id' => $se->id,
              'post_id' => $event_post_id,
              'type' => 'event',
            );
            $wpdb->insert($sincronization_table_name, $aux);
          }
          
          //Insertamos también todas las instancias
          $spektrix_instances = $wpdb->get_results("SELECT * FROM $instances_table_name WHERE event_id = {$se->id}");
          if ($spektrix_instances) {
            foreach ($spektrix_instances as $si) {
              $my_instance = array(
                'post_title' => $si->time,
                'post_status' => 'draft',
                'post_type' => 'spektrix_instance',
                'post_author' => 1,
                'post_parent' => $event_post_id,
              );
              
              //Insertamos el post
              $instance_post_id = wp_insert_post($my_instance);
              add_post_meta($instance_post_id, 'spektrix_seats_available', $si->seats_available, TRUE);
              self::message(__('Post de instancia insertado:') . ' ' . $si->time, self::INFO, $interactive);
              
              //Insertamos también los datos en la tabla de relaciones
              if ($instance_post_id) {
                $aux = array(
                  'id' => 0,
                  'spektrix_id' => $si->id,
                  'post_id' => $instance_post_id,
                  'type' => 'instance',
                );
                $wpdb->insert($sincronization_table_name, $aux);
              }
              
              //Insertamos los eventos asociados para mostrarlos en el calendario
              $my_tribe_event = array(
                'post_title' => $my_event['post_title'],
                'post_status' => 'draft',
                'post_type' => 'tribe_events',
                'post_author' => 1,
                'post_name' => spektrix_get_post_name($my_event['post_title']),
                'post_parent' => $event_post_id,
              );
              
              //Insertamos el post
              $tribe_event_post_id = wp_insert_post($my_tribe_event);
              add_post_meta($tribe_event_post_id, '_EventShowMapLink', 'false', TRUE);
              add_post_meta($tribe_event_post_id, '_EventShowMap', 'false', TRUE);
              add_post_meta($tribe_event_post_id, '_EventStartDate', $si->time, TRUE);
              add_post_meta($tribe_event_post_id, '_EventDuration', $se->duration, TRUE);              
              add_post_meta($tribe_event_post_id, '_EventEndDate', spektrix_suma_fechas($si->time, $se->duration), TRUE);
              self::message(__('Evento de calendario insertado:') . ' ' . $si->time, self::INFO, $interactive);
              
              //Insertamos también los datos en la tabla de relaciones
              if ($tribe_event_post_id) {
                $aux = array(
                  'id' => 0,
                  'spektrix_id' => $si->id,
                  'post_id' => $tribe_event_post_id,
                  'type' => 'tribe',
                );
                $wpdb->insert($sincronization_table_name, $aux);
              }
              
            }
          }
        }
        
        
      }
    }
    
    update_option('spk_last_execution', current_time('timestamp'));
    update_option('spk_execution', '0');
    
    ?>
    <div>
      <a href="/wp-admin/options-general.php?page=spektrix"><?php print __('Regresar'); ?></a>
    </div>
    
    <?php 
  }
  
  static function cleanSemaphore() {
    update_option('spk_execution', '0');
    print __("Semáforo limpiado");
    ?>
    <div>      
      <a href="/wp-admin/options-general.php?page=spektrix"><?php print __('Regresar'); ?></a>
    </div>
    
    <?php 
  }
  
  static private function getXmlData($url) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, false);
    $xmlData = curl_exec($curl);
    curl_close($curl);

    return $xmlData;
  }
  
  static private function GetAllInstancesFrom($date = '') {
    //Si no se pasa día es la fecha actual
    if (!$date) {
      $date = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/alltimes/from?date=' . $date;
    
    return self::getXmlData($url);
  }
  
  static private function GetAllInstancesFromAllAttributes($date = '') {
    //Si no se pasa día es la fecha actual
    if (!$date) {
      $date = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/alltimes/allattributes/from?date=' . $date;
    
    return self::getXmlData($url);
  }
  
  static private function GetAllInstancesFromTo($date_from = '', $date_to = '') {
    //Si no se pasa día es la fecha actual
    if (!$date_from) {
      $date_from = date('Y-m-d');
    }
    
    //Si no se pasa día es la fecha actual
    if (!$date_to) {
      $date_to = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/alltimes/between?dateFrom=' . $date_from . '&dateTo=' . $date_to ;
    
    return self::getXmlData($url);
  }

  static private function GetAllInstancesFromToAllAttributes($date_from = '', $date_to = '') {
    //Si no se pasa día es la fecha actual
    if (!$date_from) {
      $date_from = date('Y-m-d');
    }
    
    //Si no se pasa día es la fecha actual
    if (!$date_to) {
      $date_to = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/alltimes/allattributes/between?dateFrom=' . $date_from . '&dateTo=' . $date_to ;
    
    return self::getXmlData($url);
  }
  
  static private function GetEvent($event_id) {
    $url = self::SPEKTRIX_URL . '/details/' . $event_id;
    
    return self::getXmlData($url);
  }

  static private function GetEventAllAttributes($event_id) {
    $url = self::SPEKTRIX_URL . '/details/allattributes/' . $event_id;
    
    return self::getXmlData($url);
  }

  static private function GetFrom($date = '') {
    //Si no se pasa día es la fecha actual
    if (!$date) {
      $date = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/from?date=' . $date;
    
    return self::getXmlData($url);
  }
  
  static private function GetFromAllAttributes($date = '') {
    //Si no se pasa día es la fecha actual
    if (!$date) {
      $date = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/allattributes/from?date=' . $date;
    
    return self::getXmlData($url);
  }
  
  static private function GetFromTo($date_from = '', $date_to = '') {
    //Si no se pasa día es la fecha actual
    if (!$date_from) {
      $date_from = date('Y-m-d');
    }
    
    //Si no se pasa día es la fecha actual
    if (!$date_to) {
      $date_to = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/between?dateFrom=' . $date_from . '&dateTo=' . $date_to ;
    
    return self::getXmlData($url);
  }
  
  static private function GetFromToAllAttributes($date_from = '', $date_to = '') {
    //Si no se pasa día es la fecha actual
    if (!$date_from) {
      $date_from = date('Y-m-d');
    }
    
    //Si no se pasa día es la fecha actual
    if (!$date_to) {
      $date_to = date('Y-m-d');
    }
    
    $url = self::SPEKTRIX_URL . '/allattributes/between?dateFrom=' . $date_from . '&dateTo=' . $date_to ;
    
    return self::getXmlData($url);
  }
  
  static private function GetNext($n) {
    $url = self::SPEKTRIX_URL . '/next?n=' . $n;
    
    return self::getXmlData($url);
  }
  
  static private function GetNextAllAttributes($n) {
    $url = self::SPEKTRIX_URL . '/allattributes/next?n=' . $n;
    
    return self::getXmlData($url);
  }
  
  static private function message($message, $type, $interactive) {
    
    if ($interactive) {
      
      switch ($type) {
        case self::INFO:
          $message_class = "spektrix-info";
          break;
        case self::WARNING:
          $message_class = "spektrix-warning";
          break;
        case self::ERROR:
          $message_class = "spektrix-error";
          break;
        default:
          $message_class = "spektrix-info";
      }
      
      
      print "<div class=\"$message_class\">$message</div>";    
    }
    
    //Guardamos además la actividad en un fichero de log
    $handle = fopen(ABSPATH . 'wp-content/plugins/spektrix/logs/' . date('Y-m-d-H') . '.log', "a+");
    fwrite($handle, $message . "\n");
    fclose($handle);
  }
  
}