<?php
/**
 * file2arr()
 *
 * Local function to read an association files into an array
 */
function file2arr($file) {
  if (!is_file($file) || !is_readable($file)) {
    print "Alias file '$file' not found in ".getcwd() .". Please create alias file.</br>";
    return false;
  }
  $arr = array();
  $fp = fopen($file,"r");
  while (false != ($line = fgets($fp,4096))) {
      if (!preg_match("/.+\s.+/",$line,$match)) continue;
      $tmp = preg_split("/\s/",trim($line));
      $arr[$tmp[0]] = $tmp[1];
  }
  fclose ($fp);

  return $arr;
}//file2arr()

// eksc hack: a desparate move to remove this pane if feature type is not a gene
$feature  = $variables['node']->feature;
if ($feature->type_id->name != 'gene') return

  // Get gene family URL prefix from the view.
  //   Note that the URL takes the gene model name rather than gene family name.
  $gene_family_url = "/chado_gene_phylotree_v2/"; // default
  $view = views_get_view('gene');
  foreach ($view->display as $part) {
    if (isset($part->display_options['fields']['gene_family']['alter'])) {
      $gene_family_url = $part->display_options['fields']['gene_family']['alter']['path'];
      $gene_family_url = preg_replace("/\[.*?\]/", '', $gene_family_url);

      // make sure there is a leading /
      if (!preg_match("/^\//", $gene_family_url) 
            && !preg_match("/^http/", $gene_family_url)) {
        $gene_family_url = "/$gene_family_url";
      }
    }
  }

  drupal_add_css($my_path . '/theme/css/basket.css');

  $feature  = $variables['node']->feature;  
  $feature_id = $feature->feature_id;
//echo "<pre>";var_dump($feature);echo "</pre>";

  // Always want to expand joins as arrays regardless of how many matches
  //   there are
  $table_options = array('return_array' => true);

  // Get the overview record for this gene model
  $sql = "
    SELECT * FROM {gene}
    WHERE uniquename = '".$feature->uniquename."'";
  if ($res = chado_query($sql, array())) {
    $row = $res->fetchObject();
    
    $gene_family      = $row->gene_family;
    $gene_description = $row->description;
    $genus            = $row->genus;
    $species          = $row->species;
  }
  else {
    $gene_family      = 'unknown';
    $gene_description = 'None given.';
    $genus            = 'unknown';
    $species          = 'unknown';
  }

  // Get gene model build (represented as an analysis record)
  $feature = chado_expand_var($feature, 'table', 'analysisfeature', $table_options);
  $analysis = $feature->analysisfeature[0]->analysis_id;
  $gene_model_build = $analysis->name;

  // Get properties
  $properties = array();
  $feature = chado_expand_var($feature, 'table', 'featureprop', $table_options);
  $props = $feature->featureprop;
  foreach ($props as $prop){
    $prop = chado_expand_var($prop, 'field', 'featureprop.value');
    $properties[$prop->type_id->name] = $prop->value;
  }
//echo "<pre>";var_dump($properties);echo "</pre>";

  // Expand relationships
  $mRNAs = array();
  $feature = chado_expand_var($feature, 'table', 'feature_relationship', $table_options);
  $related = $feature->feature_relationship->object_id;
  foreach ($related as $relative) {
    if ($relative->subject_id->type_id->name == 'mRNA') {
      $mRNAs[$relative->subject_id->name] = $relative->subject_id->uniquename;
    }
  }
  ksort($mRNAs);
//echo "<pre>";var_dump($mRNAs);echo "</pre>";
  
  
  ///////////////////////   SET UP JBROWSE SECTION   ////////////////////////
  
  $jbrowse_html = '';
  
  // These files link identifiers in Chado to identifers in JBrowse
  $aliasdir    = 'files/aliasfiles/';
  $data_file   = $aliasdir . 'data_alias.tab';
  $tracks_file = $aliasdir . 'tracks_alias.tab';
  $chr_file    = $aliasdir . 'chr_alias.tab';
  
  // convert alias files to associative arrays:
  $data_arr   = file2arr($data_file);
  $tracks_arr = file2arr($tracks_file);
  $chr_arr    = file2arr($chr_file);

  $key = $feature->organism_id->abbreviation;

  // $data_arr maps the genus and species abbreviation to the dataset name:
  $data   = $data_arr[$key];
  
  // $tracks_arr maps the genus and species abbreviation to the gene model
  //   track name:
  $tracks = $tracks_arr[$key];
  
  $feature = chado_expand_var($feature, 'table', featureloc, $table_options);
  $srcfeatures =  $feature->featureloc->feature_id;
  
  while (list(, $srcf) = each($srcfeatures)) {
    // only interested in srcfeature of type 'chromosome'
//echo "<pre>";var_dump($srcf);echo "</pre>";
    if ($srcf->srcfeature_id->type_id->name == 'chromosome') {
      $chrname = $srcf->srcfeature_id->name;
      $chrlen  = $srcf->srcfeature_id->seqlen;
      $start   = $srcf->fmin;
      $end     = $srcf->fmax;
      break;
    } 
    else {
      continue;
    }
  }
//echo "key=$key, data=$data, chrname=$chrname, chrlen=$chrlen, start=$start, end=$end, tracks=$tracks<br>";

  if (!$chrname || !$chrlen || !$start || !$end) {
    // Can't create JBrowse object
    $jbrowse_html = 'No browser instance available to display a graphic for this gene.';
  }
  else {
    // the LIS chr name is mapped to its jbrowse equivalent 
    $chr = $chr_arr[$chrname];
    
    // expand the region by 2k:
    $start = (($start- 2000) < 0) ? 0 : $start = $start- 2000;
    $end = (($end + 2000) > $chrlen) ? $chrlen : $end + 2000;
    $loc = $chr.":".$start."..".$end;

    if (($feature->type_id->name == "gene") && $data && $loc && $tracks) {
      if ($key ==  "glyma") {   #peu
        // Glycine max JBrowse instance is at Soybase.org
        $url_source = $data;
        $qry_params = "?start=%s;stop=%s;ref=%s;";
        $url_source = sprintf($url_source.$qry_params, $start, $end, $chr);
        $jbrowse_html = "
          <div>
            <br>
            If the Soybase.org GBrowse window does not open automatically, click 
            <a href='$url_source' target=_blank>here</a> to see this gene model
            on the soybean genome.
          </div>
          <br>
          <script language='javascript'>
            var re = new RegExp('jbrowse');
            if (window.location.href.match(re)) {
              window.onload = function() { window.open('$url_source'); }
            }
          </script>";
      }//Glycine max
      else {
        $url_source = $data;    
	if (preg_match("/gbrowse_img/", $url_source)) {
		$qry_params = "&q=%s&tracks=%s";
	}
	else {
		$qry_params = "&loc=%s&tracks=%s";
	}
        $url_source = sprintf($url_source.$qry_params, $loc, $tracks);
        $jbrowse_html = "
          </br>   
          <div>
            <iframe id='frameviewer' frameborder='0' width='100%' height='1000' 
                    scrolling='yes' src='$url_source' name='frameviewer'></iframe>
          </div>";
      }
    }
  }//JBrowse instance exists

  
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
  
  // Organism row
  $organism = $feature->organism_id->genus 
            . " " . $feature->organism_id->species 
            ." (" . $feature->organism_id->common_name .")";
  if (property_exists($feature->organism_id, 'nid')) {
    $text = "<i>" . $feature->organism_id->genus . " " 
           . $feature->organism_id->species 
           . "</i> (" . $feature->organism_id->common_name .")";
    $url = "node/".$feature->organism_id->nid;
    $organism = l($text, $url, array('html' => TRUE));
  } 
  $rows[] = array(
    array(
      'data' => 'Organism',
      'header' => TRUE,
    ),
    $organism
  );

/*TODO: uncomment when build analysis record is associated with gene models
  // Build (analysis)
  $rows[] = array(
    array(
      'data' => 'Gene Model Build',
      'header' => TRUE,
      'width' => '20%',
    ),
    $gene_model_build
  );
*/
  
  // Description row
  $rows[] = array(
    array(
      'data' => 'Description',
      'header' => TRUE,
      'width' => '20%',
    ),
    $gene_description
  );
  
  // Gene family rows
  if ($gene_family == 'unknown') {
    $gene_family_html = "<i>unknown</i>";
  }
  else {
    // Link with uniquename for gene feature (assumes 1 gene family per gene model)
    $url = $gene_family_url . $feature->uniquename;
    $gene_family_html = "<a href='$url'>$gene_family</a>";
  }
  $rows[] = array(
    array(
      'data' => 'Gene Family',
      'header' => TRUE,
      'width' => '20%',
    ),
    $gene_family_html
  );
  
/* don't know if this is useful; may be only confusing
  // Gene family representative
  $gene_family_representive = $properties['family representative'];
  $rows[] = array(
    array(
      'data' => 'Gene Family Representative',
      'header' => TRUE,
      'width' => '20%',
    ),
    $gene_family_representive
  );
*/

  // mRNA(s)
  $mRNA_html = '';
  foreach (array_keys($mRNAs) as $mRNA_name) {
//    $url = "feature/$genus/$species/mRNA/" . $mRNAs[$mRNA_name];
    $url = "?pane=Sequences#$mRNA_name";
    $mRNA_html .= "<a href='$url'>$mRNA_name</a><br>";
  }
  $rows[] = array(
    array(
      'data' => 'mRNA and protein identifiers<br>(also see Sequences tab)',
      'header' => TRUE,
      'width' => '20%',
    ),
    $mRNA_html
  );
  
  
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
  
  // once we have our table array structure defined, we call Drupal's theme_table()
  // function to generate the table.
  print theme_table($table); 
  
  if ($jbrowse_html) {
    print $jbrowse_html;
  }
