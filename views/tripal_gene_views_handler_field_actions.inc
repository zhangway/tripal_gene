<?php

/**
 * @file
 * handler class for the new field 'basket'
 */

/**
 * class definition
 */
class tripal_gene_views_handler_field_actions extends views_handler_field {
  /**
   * Render function: return html output
  */
  function render($values) {

      foreach ($values as $key => $val) {
          if ($key == "gene_name") {

              $sql =<<<SQL
              select g.*, 
                     chf.nid 
              from chado.gene g, 
                   public.chado_feature chf 
              where g.gene_id = chf.feature_id 
              and g.name = :name
SQL;

              $args = array(':name' => $val);               
              $result = chado_query($sql, $args)->fetchObject();
          }      
      }

      return lightshop_add2basket_link($result->nid, "gene");
   }

    function query() {
        //do nothing but keep the function here;
    }

}