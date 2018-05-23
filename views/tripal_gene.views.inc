<?php

function tripal_gene_views_data_alter(&$data) {
    $data['gene']['actions'] = array(
        'title' => t('Bucket'),
        'help' => t('Clickable links to actions a user may perform on a Node.'),
        'field' => array(
            'handler' => 'tripal_gene_views_handler_field_actions',
            'group' => 'Gene',
            'click sortable' => FALSE,
        ),
    );
}


function tripal_gene_views_pre_view() {
    // op = Add all genes to Basket should not be passed via pager links:
    if(isset($_GET["op"]) && $_GET["op"] == "Add all genes to Basket") {
        $_GET = drupal_get_query_parameters($_GET, array('submit_basket', 'op'));
    }
}