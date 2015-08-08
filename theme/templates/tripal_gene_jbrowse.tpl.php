<?php
function file2arr($file) {
    if (!is_file($file) || !is_readable($file)) {
      print "Alias file '$file' not found in ".getcwd() .". Please create alias file.</br>";
      return false;
    }
    $arr = [];
    $fp = fopen($file,"r");
    while (false != ($line = fgets($fp,4096))) {
        if (!preg_match("/.+\s.+/",$line,$match)) continue;
        $tmp = preg_split("/\s/",trim($line));
        $arr[$tmp[0]] = $tmp[1];
    }
    fclose ($fp);

    return $arr;
}

$feature = $variables['node']->feature;

if (! $feature || ! $feature->featureloc->feature_id ) return;

$aliasdir    = 'files/aliasfiles/';
$data_file   = $aliasdir . 'data_alias.tab';
$tracks_file = $aliasdir . 'tracks_alias.tab';
$chr_file    = $aliasdir . 'chr_alias.tab';

// storing the data from alias files in associative arrays:
$data_arr   = file2arr($data_file);
$tracks_arr = file2arr($tracks_file);
$chr_arr    = file2arr($chr_file);

// a concatenation of the initial letters of genus and species is to be used as a key for assoc arrays:
$key = strtolower(substr($feature->organism_id->genus, 0, 1) . substr($feature->organism_id->species, 0, 1));

// $data_arr maps the concatenation of the initial letters of genus and species to the data set name:
$data   = $data_arr[$key];

// $tracks_arr maps the concatenation of the initial letters of genus and species to the Gene Models track name:
$tracks = $tracks_arr[$key];

$srcfeatures =  $feature->featureloc->feature_id ;

while (list(, $srcf) = each($srcfeatures)) {
  // only intersted in a src feature of type 'chromosome', skip all other types of src features:
  if ($srcf->srcfeature_id->type_id->name == 'chromosome') {
    $chrname = $srcf->srcfeature_id->name;
    $chrlen  = $srcf->srcfeature_id->seqlen;
    $start   = $srcf->fmin;
    $end     = $srcf->fmax;
    break;
  } else {
    continue;
  }
}
if (! $chrname || ! $chrlen || ! $start || ! $end) return; 

// the LIS chr name is mapped to its jbrowse equivalent 
$chr = $chr_arr[$chrname];

// expand the region with 2k:
if (($start- 2000) < 0) {
  $start = 0;
} else {
  $start = $start- 2000;
}

if (($end + 2000) > $chrlen){
  $end = $chrlen;
} else {
  $end = $end + 2000;
}

$loc = $chr.":".$start."..".$end;


if (($feature->type_id->name == "gene") && $data && $loc && $tracks) {

  if ($key ==  "gm") {

    $url_source = $data;
    $qry_params = "?start=%s;stop=%s;ref=%s;";
    $url_source = sprintf($url_source.$qry_params, $start, $end, $chr);
    $html = "<div>If the GBrowse window is not opened automatically, click <a href='".$url_source."' target=_blank>here</a>.</div>";
    $html .= "<script>
    var re = new RegExp('jbrowse');
    if (window.location.href.match(re)) {
      window.onload = function() { window.open('".$url_source."'); }
    }
    </script>";

  } else {
  
    $url_source = $data;    
    $qry_params = "&loc=%s&tracks=%s";
    $url_source = sprintf($url_source.$qry_params, $loc, $tracks);
  
    $html = "
    </br>   
    <div>
      <iframe id='frameviewer' frameborder='0' width='100%' height='1000' scrolling='yes' src='".$url_source."' name='frameviewer'></iframe>
    </div>";
  }
  
  print $html;  
}

/*
e.g. url params:
$data   = "Mt4.0";
$tracks = "mtGene%2CmtTErelated%2CmtrRNAmod";
$loc    = "Mt2:27441888-27442030";
*/