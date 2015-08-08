<?php

/**
 * Implements hook_node_view().
 *
 * @ingroup tripal_gene
 */
function tripal_gene_node_view($node, $view_mode, $langcode) {
  switch ($node->type) {
    case 'chado_feature':
      // Show feature browser and counts
      if ($view_mode == 'full') {          
        $node->content['tripal_gene_description'] = array(
          '#markup' => theme('tripal_gene_description', array('node' => $node)),
          '#tripal_toc_id'    => 'description',
          '#tripal_toc_title' => 'description',
          '#weight' => -100,
        );
        $node->content['tripal_gene_jbrowse'] = array(
            '#theme' => 'tripal_gene_jbrowse',
            '#node' => $node,
            '#tripal_toc_id'    => 'jbrowse',
            '#tripal_toc_title' => 'JBrowse/GBrowse',
        );
      }
      break;
  }
}