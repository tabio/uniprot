<?php
/**
 * Website: https://github.com/tabio/uniprot
 * 
 * This program is to get phosphorylation and snip information for target protein on Uniprot web site.
 *
 * [Step 1]
 *   set ncbi accession number lists of target proteins from your database or file.
 *
 * [Step 2]
 *   execute this script.
 *   =>   transfer ncbi accession number to uniprot accesssion number.
 *   ==>  get phosphorylation and snip information for annotated target protin.
 *   ===> regist these infomation on your database.
 *
 * @auther  tabio <tabio@gmail.com>
 * @version 1.0
 */

// define
//---- database
define('DSN',        'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'uniprot');
//---- proxy
define('IS_PROXY',   false);
define('PROXY_HOST', 'hoge.co.jp');
define('PROXY_PORT', 8080);
//---- blowser
define('U_AGENT', 'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0)');
define('REFERER', 'http://github.com');
//---- etc
define('SLEEP_COUNT', 10);

// pear install HTTP_Request
require_once('HTTP/Request.php');

// set database object
function set_db() {
  $pdo    = array();

  try {
    $conn = sprintf('mysql:host=%s;dbname=%s', DSN, DB_NAME);
    $pdo  = new PDO($conn, DB_USER, DB_PASS);
    $pdo->query('SET NAMES utf8');
  } catch(PDOException $e) {
    var_dump($e->getMessage());
    exit;
  }
  return $pdo;
}

// set Http_Request object
function set_http_obj($url) {
  $opt = array(
    'timeout'        => 10,
    'allowRedirects' => true,
    'maxRedirects'   => 1
  );

  if (IS_PROXY) {
    $opt['proxy_host'] = PROXY_HOST;
    $opt['proxy_port'] = PROXY_PORT;
  }

  $http = new HTTP_Request($url, $opt);
  $http->addHeader('User-Agent', U_AGENT);
  $http->addHeader('Referer',    REFERER);

  return $http; 
}

// delete target strings
function str_rep($str) {
  return trim(str_replace(array("\n","\r\n","\r"), '', $str));
}

// get accession information from uniprot
function ncbi2uniprot($np_acc) {
  $results  = array();
  $url      = 'http://www.uniprot.org/uniprot/?query='.$np_acc.'+AND+reviewed%3Ayes&sort=score';
  $http     = set_http_obj($url);
  $response = $http->sendRequest();
  
  if (!PEAR::isError($response)) {
    $data = $http->getResponseBody();
    $config = array(
      'indent'       => TRUE,
      'output-xhtml' => TRUE,
      'wrap'         => 200
    );
    $tidy = tidy_parse_string($data, $config, 'UTF8');
    $tidy->cleanRepair();
    $sxe = simplexml_load_string(ereg_replace("&nbsp;", ' ',$tidy->body()->value));
    foreach ($sxe->xpath('//table[@id="results"]/tr') as $tmp) {
      $i = 0;
      foreach ($tmp->td as $td) {
        if ($i === 1) {
          array_push($results, (string)$td->a);
          break;
        }
        $i++;
      }
    }
  } else {
    return false;
  }
  return $results;
}

// clear off reference data
function evi2pubmed($evi_keys='', $pubmeds=array()) {
  $res = '';
  if (preg_match('/^ref/', $evi_keys)) {
    $res = $evi_keys;
  } else {
    $tmp = preg_split('/\s/', $evi_keys);
    foreach($tmp as $key) {
      if ($res) $res .= ',';
      $res .= $pubmeds[$key];
    }
  }
  return $res;
}

// get phosphorylation and snip information from uniprot
function get_phos_snip($arr_uni_acc) {
  $info = array();
  foreach ($arr_uni_acc as $uni_acc) {

    $info[$uni_acc] = array();

    $url  = sprintf('http://www.uniprot.org/uniprot/%s.xml', $uni_acc);
    $http = set_http_obj($url);
    $response = $http->sendRequest();
    $response = $http->sendRequest();
  
    if (!PEAR::isError($response)) {
      $xml = simplexml_load_string($http->getResponseBody());

      // sequence
      if (isset($xml->entry->sequence)) $info[$uni_acc]['sequence'] = str_rep($xml->entry->sequence);

      // reference
      $pubmeds = array();
      foreach($xml->entry->evidence as $evi) {
        if (isset($evi->source['ref'])) {
          $pubmeds[(string)$evi['key']] = 'ref'.(int)$evi->source['ref'];
        } else {
          $pubmeds[(string)$evi['key']] = (int)$evi->source->dbReference['id'];
        }
      }

      foreach($xml->entry->feature as $tmp) {
        switch($tmp['type']) {
          case 'sequence variant':
            if (!isset($info[$uni_acc]['variant'])) $info[$uni_acc]['variant']    = array();
            $info[$uni_acc]['variant'][(int)$tmp->location->position['position']] = array();
            $info[$uni_acc]['variant'][(int)$tmp->location->position['position']]['original']  = @(string)$tmp->original;
            $info[$uni_acc]['variant'][(int)$tmp->location->position['position']]['variation'] = @(string)$tmp->variation;

            // evidence key => pubmed_id
            $info[$uni_acc]['variant'][(int)$tmp->location->position['position']]['pubmed_id'] = '';
            if (isset($tmp['evidence'])) {
              $info[$uni_acc]['variant'][(int)$tmp->location->position['position']]['pubmed_id'] = evi2pubmed((string)$tmp['evidence'], $pubmeds);
            }

            break;
          case 'modified residue':
            switch($tmp['description']) {
              case 'Phosphothreonine':
              case 'Phosphoserine':
              case 'Phosphotyrosine':
                if (!isset($info[$uni_acc][(string)$tmp['description']])) $info[$uni_acc][(string)$tmp['description']] = array();
                $info[$uni_acc][(string)$tmp['description']][(int)$tmp->location->position['position']] = array();

                // evidence key => pubmed_id
                $info[$uni_acc][(string)$tmp['description']][(int)$tmp->location->position['position']]['pubmed_id'] = '';
                if (isset($tmp['evidence'])) {
                  $info[$uni_acc][(string)$tmp['description']][(int)$tmp->location->position['position']]['pubmed_id'] = evi2pubmed((string)$tmp['evidence'], $pubmeds);
                }

                $info[$uni_acc][(string)$tmp['description']][(int)$tmp->location->position['position']]['status'] = @(string)$tmp['status'];
                break;
            }
            break;
        }
      }
    }
  }
  return $info;
}

/**
 * change phosphorylation and snip status to easy flags
 * 0 -> none
 * 1 -> probable
 * 2 -> pubmed ids
 * 3 -> reference number
*/
function get_status_type($ref_str='', $status='') {
  $flg = 0;
  if (empty($ref_str)) {
    if (!empty($status)) $flg = 1;
  } else {
    $flg = (preg_match('/^ref/',$ref_str)) ? 3 : 2;
  }
  return $flg;
}

// regist database
function reg_target_info($np_acc, $info=array()) {
  echo "---> $np_acc\n";

  $ins_chg_acc = 'insert into t_chg_acc (gene_acc_no, uni_acc_no, sequence) values (?, ?, ?)';
  $ins_phos    = 'insert into t_acc_phospho (uni_acc_no, position, residue_type, reference, status) values (?, ?, ?, ?, ?)';
  $ins_snip    = 'insert into t_acc_snip (uni_acc_no, position, original, variation, reference, status) values (?, ?, ?, ?, ?, ?)';

  // set each pdo objects
  try {
    $dbh       = set_db();
    $stmt_chg  = $dbh->prepare($ins_chg_acc);
    $stmt_phos = $dbh->prepare($ins_phos);
    $stmt_snip = $dbh->prepare($ins_snip);
  } catch(PDOException $e) {
    var_dump($e->getMessage());
    exit;
  }

  foreach($info as $key => $val){
    echo "------> $key\n";

    //----- insert t_chg_acc
    try {
      $stmt_chg->bindParam(1, $np_acc,          PDO::PARAM_STR);
      $stmt_chg->bindParam(2, $key,             PDO::PARAM_STR);
      $stmt_chg->bindParam(3, $val['sequence'], PDO::PARAM_STR);

      if (!$stmt_chg->execute()) throw new Exception("query execute error\n");

    } catch(PDOException $e) {
      echo "error t_chg_acc => ".$e->getMessage();
    } catch(Exception $e) {
      echo "error insert t_chg_acc => ".$e->getMessage();
    }

    //------ insert t_acc_phospho
    try {
      $phos_arr = array(
        0 => 'Phosphoserine',
        1 => 'Phosphothreonine',
        2 => 'Phosphotyrosine',
      );

      foreach ($phos_arr as $k => $v) {
        $type = $k;
        if (isset($val[$v])) {
          foreach($val[$v] as $position => $tmp) {
            $status = get_status_type($tmp['pubmed_id'], $tmp['status']);

            $stmt_phos->bindParam(1, $key,              PDO::PARAM_STR);
            $stmt_phos->bindParam(2, $position,         PDO::PARAM_INT);
            $stmt_phos->bindParam(3, $type,             PDO::PARAM_INT);
            $stmt_phos->bindParam(4, $tmp['pubmed_id'], PDO::PARAM_STR);
            $stmt_phos->bindParam(5, $status,           PDO::PARAM_INT);

            if (!$stmt_phos->execute()) throw new Exception("query execute error\n");
          }
        }
      }

    } catch(PDOException $e) {
      echo "error t_acc_phospho => ".$e->getMessage();
    } catch(Exception $e) {
      echo "error insert t_acc_phospho => ".$e->getMessage();
    }

    //----- insert t_acc_snip
    try {
      if (isset($val['variant'])) {
        foreach($val['variant'] as $position => $tmp) {
          $status = get_status_type($tmp['pubmed_id']);

          $stmt_snip->bindParam(1, $key,              PDO::PARAM_STR);
          $stmt_snip->bindParam(2, $position,         PDO::PARAM_INT);
          $stmt_snip->bindParam(3, $tmp['original'],  PDO::PARAM_STR);
          $stmt_snip->bindParam(4, $tmp['variation'], PDO::PARAM_STR);
          $stmt_snip->bindParam(5, $tmp['pubmed_id'], PDO::PARAM_STR);
          $stmt_snip->bindParam(6, $status,           PDO::PARAM_INT);

          if (!$stmt_snip->execute()) throw new Exception("query execute error\n");
        }
      }
    } catch(PDOException $e) {
      echo "error t_acc_snip =>".$e->getMessage();
    } catch(Exception $e) {
      echo "error insert t_acc_snip =>".$e->getMessage();
    }
    
  }
}

// get ncbi accession number of target protein from ncbi
function get_np_acc($file='') {
  $res = array();
  try {
    if ($file) {
      $fp = fopen($file, 'r');
      while (!feof($fp)) {
        $line = fgets($fp);
        $line = trim($line);
        if (empty($line)) continue;

        // check format
        $cnt  = count(preg_split('/(\s|\t|,)/', $line, -1, PREG_SPLIT_NO_EMPTY));
        if ($cnt > 1) throw new Exception("file format error\n");

        // add array
        array_push($res, array('gene_acc_no' => $line));
      }
    } else {
      $sql = 'select gene_acc_no from proteins group by gene_acc_no';
      $dbh  = set_db();
      $stmt = $dbh->prepare($sql);
      if (!$stmt->execute()) throw new Exception("query execute error\n");
      $res = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } catch(PDOException $e) {
    echo $e->getMessage();
    exit;
  } catch(Exception $e) {
    echo $e->getMessage();
    exit;
  }
  return $res;
}

// main
function main($file='') {
  echo "[start] program =========>\n";

  // get ncbi accession numbers
  $np_acc_arr = get_np_acc($file);

  foreach($np_acc_arr as $val) {
    sleep(SLEEP_COUNT);

    // initialize
    $np_acc  = '';
    $uni_acc = array();
    $info    = array();

    // set ncbi accession number
    $np_acc = $val['gene_acc_no'];

    // change ncbi acc to uni acc
    $uni_acc = ncbi2uniprot($np_acc);

    // get phospho and snip info
    $info = get_phos_snip($uni_acc);

    // regist
    reg_target_info($np_acc, $info);
  }

  echo "[end]   program <=========\n";
}

//-- check argument
$opt = getopt('f:');
if (isset($opt['f']) && !empty($opt['f'])) {
  if (file_exists($opt['f'])) {
    $is_file = true;
  } else {
    echo "[warning] check args!!\n";
    echo "php ".__FILE__." -f file path\n";
    exit(1);
  }
}
main($opt['f']);

?>
