<?php
/*
 * There are several ways that sequences can be displayed.  They can come from the 
 * feature.residues column,  they can come from an alignment with another feature,
 * they can come from a protein sequence that has relationship with this sequence,
 * or they can come from sub children (e.g. CDS coding sequences).
 *   
 * This template will show all types depending on the data available.
 *
 */

  // number of bases per line in FASTA format
  $num_bases = 50; 

  /**
   * makeFASTAdiv()
   *
   * Local function to format residues field contents into FASTA
   */
  function makeFASTAdiv($name, $residues, $num_bases) {
    $sequence_html .= '<div id="residues" class="tripal_feature-sequence-item">';
    $sequence_html .= "<p><b>$name sequence</b></p>";
    $sequence_html .= '<pre class="tripal_feature-sequence">';
    $sequence_html .= ">$name\n";
    $sequence_html .= wordwrap($residues, $num_bases, "<br>", TRUE);
    $sequence_html .= '</pre>';
    $sequence_html .= '<a href="#sequences-top">back to top</a>';
    $sequence_html .= '</div>';
    
    return $sequence_html;
  }//makeFASTAdiv()
  
  
  $feature = $variables['node']->feature;
  
  // eksc hack: a desparate move to remove this pane if feature type is not a gene
  if ($feature->type_id->name != 'gene') return

  // Always want to expand joins as arrays regardless of how many matches
  //   there are
  $table_options = array('return_array' => true);

  // Expand relationships
  $mRNAs = array();
  $feature = chado_expand_var($feature, 'table', 'feature_relationship', $table_options);
  $related = $feature->feature_relationship->object_id;
  foreach ($related as $relative) {
    if ($relative->subject_id->type_id->name == 'mRNA') {
      $relative = chado_expand_var($relative, 'field', 'feature.residues');
//echo "<pre>";var_dump($relative);echo "</pre>";

      // Get polypeptide associated with the mRNA
      $sql = "
        SELECT p.* FROM {feature_relationship} fr
          INNER JOIN {feature} p 
            ON p.feature_id=fr.subject_id
        WHERE fr.type_id=(SELECT cvterm_id FROM {cvterm} 
                          WHERE name='derives_from'
                                AND cv_id=(SELECT cv_id FROM {cv} 
                                           WHERE name='relationship')) 
              AND fr.object_id=" . $relative->subject_id->feature_id;
      if ($res=chado_query($sql)) {
        $row = $res->fetchObject();
        $pep_name     = $row->name;
        $pep_residues = $row->residues;
      }
          
      $mRNAs[$relative->subject_id->name] = array(
        'uniquename'   => $relative->subject_id->uniquename,
        'residues'     => $relative->subject_id->residues,
        'pep_name'     => $pep_name,
        'pep_residues' => $pep_residues,
      );
    }
  }
  ksort($mRNAs);
//echo "<pre>";var_dump($mRNAs);echo "</pre>";


  ///////////////////////   PREPARE THE RECORD TABLE   ////////////////////////
  
  // the $headers array is an array of fields to use as the column headers. 
  // additional documentation can be found here 
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  // This table for the analysis has a vertical header (down the first column)
  // so we do not provide headers here, but specify them in the $rows array below.
  $headers = array();
  
  // the $rows array contains an array of rows where each row is an array
  // of values for each column of the table in that row.  Additional documentation
  // can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7 
  $rows = array();
  
  // Name row
  $rows[] = array(
    array(
      'data' => 'Gene Model Name',
      'header' => TRUE,
      'width' => '20%',
    ),
    $feature->name
  );
  
  // Name row
  $rows[] = array(
    array(
      'data' => '',
      'header' => TRUE,
      'width' => '20%',
    ),
    'This gene model has ' . count($mRNAs) . ' associated mRNAs',
  );
  
  /////// SEPARATOR /////////
  $rows[] = array(
    array(
      'data' => '',
      'header' => TRUE,
      'height' => 6,
      'style' => 'background-color:white',
    ),
    array(
      'data' => '',
      'style' => 'background-color:white',
    ),
  );

  foreach (array_keys($mRNAs) as $mRNA_name) {
/*
    // mRNA name
    $rows[] = array(
      array(
        'data' => 'mRNA Name',
        'header' => TRUE,
        'width' => '20%',
      ),
      "<a name='$mRNA_name'></a>$mRNA_name"
    );
*/
    // mRNA sequence
    $seq_html = makeFASTAdiv($mRNA_name, $mRNAs[$mRNA_name]['residues'], $num_bases);
    $rows[] = array(
      array(
        'data' => 'mRNA Sequence',
        'header' => TRUE,
        'width' => '20%',
      ),
      "<a name='$mRNA_name'></a>$seq_html"
    );
/*    
    // polypetide name
    $rows[] = array(
      array(
        'data' => 'Protein Name',
        'header' => TRUE,
        'width' => '20%',
      ),
      $mRNAs[$mRNA_name]['pep_name']
    );
*/

    // polypetide sequence
    $seq_html = makeFASTAdiv($mRNA_name, $mRNAs[$mRNA_name]['pep_residues'], $num_bases);
    $rows[] = array(
      array(
        'data' => 'Protein Sequence',
        'header' => TRUE,
        'width' => '20%',
      ),
      $seq_html
    );
    
    /////// SEPARATOR /////////
    $rows[] = array(
      array(
        'data' => '',
        'header' => TRUE,
        'height' => 6,
        'style' => 'background-color:white',
      ),
      array(
        'data' => '',
        'style' => 'background-color:white',
      ),
    );
  }//each mRNA
    
  // the $table array contains the headers and rows array as well as other
  // options for controlling the display of the table.  Additional
  // documentation can be found here:
  // https://api.drupal.org/api/drupal/includes%21theme.inc/function/theme_table/7
  $table = array(
    'header' => $headers,
    'rows' => $rows,
    'attributes' => array(
      'id' => 'tripal_feature-table-base',
      'class' => 'tripal-data-table'
    ),
    'sticky' => FALSE,
    'caption' => '',
    'colgroups' => array(),
    'empty' => '',
  );
  
  // So we can get back to the top of the page quickly:
  print '<a name="sequences-top"></a>';

  // once we have our table array structure defined, we call Drupal's theme_table()
  // function to generate the table.
  print theme_table($table); 
