<?php

/**
 * Collection of upgrade steps
 */
class CRM_Gemeenteafdeling_Upgrader extends CRM_Gemeenteafdeling_Upgrader_Base {

  public function install() {
    $file = fopen($this->extensionDir.'/gemeentes.csv', 'r');
    $lineCount = 0;
    while (!feof($file)) {
      $line = fgets($file);
      $lineCount++;
      //skip first line
      if ($lineCount > 1) {
        list($afdeling,$gemeente) = explode(";", $line);
        $this->import($gemeente, $afdeling);
      } 
    }
    fclose($file); 
  }
  
  protected function import($gemeente, $afdeling) {
    $contact_id = $this->findAfdeling($afdeling);
    $gemeente_id = $this->findGemeente($gemeente);
    if (!$contact_id || !$gemeente_id) {
      CRM_Core_Error::debug_log_message('Could not link afdeling: '.$afdeling.' with '.$gemeente);
      return;
    }
    
    $params[1] = array($contact_id, 'Integer');
    $params[2] = array($gemeente_id, 'String');
    $dao = CRM_Core_DAO::executeQuery("SELECT * FROM `civicrm_value_gemeentes` WHERE `entity_id` = %1 AND `gemeente` = %2", $params);
    if ($dao->fetch()) {
      return;
    }
    
    CRM_Core_DAO::executeQuery("INSERT INTO `civicrm_value_gemeentes` (`entity_id`, `gemeente`) VALUES (%1, %2)", $params);
  }
  
  protected function findGemeente($gemeente) {
    $search = array(' NH', ' ZH', ' LB', ' NB');
    $replace = array(' (Noord-Holland)', ' (Zuid-Holland)', ' (Limburg)', ' (Noord-Brabant)');
    $g = trim(str_replace($search, $replace, $gemeente));
    if (stripos($g, '(')===false) {
      $provincie = CRM_Core_DAO::executeQuery("SELECT DISTINCT(`provincie`) FROM `civicrm_postcodenl` WHERE `gemeente` = %1", array(1 => array($g, 'String')));
      if ($provincie->fetch()) {
        $g .= ' ('.$provincie->provincie.')';
      }
      if ($provincie->fetch()) {
        CRM_Core_Error::debug_log_message("Gemeente ".$g." ook in ".$provincie->provincie);
        return false;
      }
    }
    $value = CRM_Core_OptionGroup::getValue('gemeente', $g, 'value', 'String', 'value');
    if (!empty($value)) {
      CRM_Core_Error::debug_log_message("Gemeente not found in list ".$g.");
      return $value;
    }
    return false;
  }
  
  protected function findAfdeling($afdeling) {
    $params['contact_sub_type'] = 'SP_Afdeling';
    $params['display_name'] = trim($afdeling);
    $params['return'] = 'id';
    try {
      $contact_id = civicrm_api3('Contact', 'getvalue', $params);
      return $contact_id;
    } catch (Exception $e) {
      //do nothing
    }
    return false;
  }

}
