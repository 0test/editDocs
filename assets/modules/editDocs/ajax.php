<?php

define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', true);

include_once(__DIR__ . "/../../../index.php");
$modx->db->connect();
if (empty ($modx->config)) {
    $modx->getSettings();
}
$modx->invokeEvent("OnWebPageInit");

if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest')) {
    $modx->sendRedirect($modx->config['site_url']);
}
//////
if (IN_MANAGER_MODE != "true" || empty($modx) || !($modx instanceof DocumentParser)) {
    die("<b>INCLUDE_ORDERING_ERROR</b><br /><br />Please use the MODX Content Manager instead of accessing this file directly.");
}
if (!$modx->hasPermission('exec_module')) {
    header("location: " . $modx->getManagerPath() . "?a=106");
}
if (!is_array($modx->event->params)) {
    $modx->event->params = array();
}
if (!isset($_SESSION['mgrValidated'])) {
    die();
}
/////

$obj = new editDocs($modx);

if ($_POST['clear']) {
    $obj->clearCache();
    echo 'Кэш очищен';
}

if ($_POST['bigparent'] || $_POST['bigparent'] == '0') {
    echo $obj->getAllList();
}
if ($_POST['id']) {
    echo $obj->editDoc();
}
if ($_FILES['myfile']) {
    echo $obj->uploadFile();
}
if ($_POST['upd']) {
    echo $obj->updateExcel();
}

if ($_POST['imp']) {
    echo $obj->importExcel();
}


if ($_POST['export']) {
    //print_r($_FILES);
    echo $obj -> export();
}

/////////////// CLASS ////////////

class editDocs
{
    public function __construct($modx)
    {
        include_once(MODX_BASE_PATH . "assets/lib/MODxAPI/modResource.php");
        $this->modx = $modx;
        $this->doc = new modResource($this->modx);
        $this->params['prevent_date'] = array('price', 'oldprice');
        $this->step = 1000;//сколько строк за раз импортируем
        $this->start_line = 2;//начинаем импорт со второй строки файла
        $this->params['max_rows'] = 20; //количество выводимых на экран строк после импорта / загрузки файла . false - если не нужно ограничивать
        $this->snipPrepare = 'editDocsPrepare';//сниппет prepare - модификация данных при сохранении
    }

    public function editDoc()
    {
        $id = $_POST['id'];
        $data = $_POST['dat'];
        $pole = $_POST['pole'];

        $this->doc->edit($id);
        $this->doc->set($pole, $data);
        $end = $this->doc->save(true, false);

        if ($end) {
            return '<div class="alert-ok">Ресурс ' . $id . ' - отредактирован!';
        } else {
            return '<div class="alert-err">ERROR!</div>';
        }
    }

    public function getAllList()
    {
        $out = '';
        $parent = $this->modx->db->escape($_POST['bigparent']);

        if ($_POST['fields']) {
            $fields = $this->modx->db->escape($_POST['fields']);
            $depth = $this->modx->db->escape($_POST['tree']);
            $disp = isset($_POST['paginat']) ? 20 : 0;
            $disp = isset($_POST['neopub']) ? 1 : '';
            foreach ($fields as $val) {
                $r .= '[+' . $val . '+] - ';
                $tvlist .= $val . ',';
                $rowth .= '<td>' . $val . '</td>';
                $rowtd .= '<td><input type="text" name="' . $val . '" value="[+' . $val . '+]"  /></td>';
            }

            $tvlist = substr($tvlist, 0, strlen($tvlist) - 1);
            $tab = '
                    <form id="dataf">
                        <table class="tabres">
                            <tr>
                                <td>id</td>' . $rowth . '
                            </tr>
            ';
            $endtab = '</table></form><br/>';

            $out = $this->modx->runSnippet('DocLister', array(
                'idType' => 'parents',
                'depth' => $depth,
                'parents' => $parent,
                'showParent' => 1,
                'id' => 'list',
                'paginate' => 'pages',
                'pageLimit' => '1',
                'pageAdjacents' => '5',
                'TplPage' => '@CODE:<span class="page">[+num+]</span>',
                'TplCurrentPage' => '@CODE:<b class="current">[+num+]</b>',
                'TplNextP' => '',
                'TplPrevP' => '',
                'TplDotsPage' => '@CODE:&nbsp;...&nbsp;',
                'display' => $disp,
                'tvPrefix' => '',
                //'ownerTPL' => '@CHUNK: paginateEditDocs',
                'ownerTPL' => '@CODE: [+dl.wrap+][+phx:if=`[+list.pages+]`:ne=``:then=`<tr><td colspan="100" align="center"><br/>[+list.pages+]<br/></td></tr>`+]',
                'tvList' => $tvlist,
                'tpl' => '@CODE:  <tr class="row"><td class="idd">[+id+]</td>' . $rowtd . '</tr>',
                'showNoPublish' => $addw
            ));

            $out = $tab . $out . $endtab;

        } else {
            $out = 'Выберите поля/TV для редактирования!';
        }
        return $out;
    }

    public function uploadFile()
    {

        $output_dir = MODX_BASE_PATH . "assets/modules/editdocs/uploads/";

        $ret = array();
        $pathinfo = array();
        $error = $_FILES["myfile"]["error"];
        if (!is_array($_FILES["myfile"]["name"])) {//single file
            $fileName = $_FILES["myfile"]["name"];
            move_uploaded_file($_FILES["myfile"]["tmp_name"], $output_dir . $fileName);
            $ret[] = $fileName;
            $pathinfo = pathinfo($output_dir . $fileName);
        }
        if (isset($pathinfo['extension']) && $pathinfo['extension'] == 'csv') {
            //загрузили csv
            $tmp[] = array();
            if (($handle = fopen($output_dir . $fileName, "r")) !== false) {
                while (($tmp2 = fgetcsv($handle, 1000, ";")) !== false) {
                    $row = array();
                    foreach ($tmp2 as $k => $v) {
                        $encoding = mb_detect_encoding($v);
                        $v = iconv($encoding, "UTF-8", $v);
                        $row[$k] = $v;
                    }
                    $tmp[] = $row;
                }
            }
            unset($tmp[0]);
            $sheetData = $tmp;
        } else {
            //загрузили xls/xlsx
            include_once MODX_BASE_PATH . "assets/modules/editdocs/libs/PHPExcel/IOFactory.php";
            $objPHPExcel = PHPExcel_IOFactory::load($output_dir . $fileName);
            $sheetData = $objPHPExcel->getActiveSheet()->toArray(null, true, true, true);
        }
        $_SESSION['data'] = $sheetData;
        $_SESSION['import_start'] = $this->start_line;
        $_SESSION['import_total'] = count($_SESSION['data']) + $_SESSION['import_start'] - 1;
        $_SESSION['import_i'] = $_SESSION['import_j'] = 0;
        echo $_SESSION['import_start'] . '|Всего строк - ' . ($_SESSION['import_total'] - $this->start_line) . '|' . $this->table($sheetData, $this->params['max_rows']);
    }
/*
    public function updateExcel()
    {

        if ($_SESSION['data']) {
            return $this->updateReady($this->newMassif($_SESSION['data'])) . $this->table($_SESSION['data'], $this->params['max_rows']);
        } else return 'Сессия устарела, загрузите файл заново!';
    }


    public function updateReady($data)
    {

        $field = $this->modx->db->escape($_POST['field']);
        $log = '';

        foreach ($data as $k => $val) {
            $i = 0;
            foreach ($val as $key => $value) {

                if ($key == $field) {
                    $check = $this->checkField($field);
                    array_push($check, $value);
                    $id = $this->getID($check);
                    //print_r($this->check);
                    //echo $this->id;
                }

                if ($id > 0) {
                    if (!isset($_POST['test'])) {
                        $this->doc->edit($this->id);
                        $this->doc->set($key, $value);
                        $this->doc->save(true, false);
                        $log .= 'id-' . $id . ';' . $key . '=>' . $value . '<br/>';
                    } else $log .= 'id-' . $id . ';' . $key . '=>' . $value . ' - Тестовый режим! <br/>';
                } elseif ($i < 1) $log .= 'Не найдено совпадений по значению - <b>' . $value . '</b>! <br/>';

                $i++;
            }
            $log .= '<hr/>';

        }
        //print_r($this->check);
        return $log;
    }
*/
    public function importExcel()
    {
        if (!$_POST['parimp']) {
            return '<div class="alert-ok ">Введите ID родителя!</div>' . $this->table($_SESSION['data'], $this->params['max_rows']);
        }
        if ($_SESSION['data']) {
            return $this->importReady($this->newMassif($_SESSION['data'])) . $this->table($_SESSION['data'], $this->params['max_rows']);
        } else {
            return 'Сессия устарела, загрузите файл заново!';
        }
    }

    protected function importReady($data)
    {
        $log = '';
        $uniq = isset($_POST['checktv']) && $_POST['checktv'] != '0' ? $_POST['checktv'] : 'id';
        $check = $this->checkField($uniq);
        $i = 0;//количество добавленных
        $j = 0;//количество отредактированных
        $start = isset($_SESSION['import_start']) ? $_SESSION['import_start'] : 0;
        $finish = isset($_SESSION['import_start']) ? ($start + $this->step) : count($data);
        $this->checkPrepareSnip();//проверяем, есть ли обработчик prepare (сниппет)
        for ($ii = $start; $ii < $finish; $ii++){
            if (!isset($data[$ii])) continue;
            $val = $data[$ii];
        //foreach ($data as $k => $val) {
            $inbase = 0;
            if (isset($val[$uniq])) {
                $check[2] = $val[$uniq];
                $inbase = $this->getID($check);
            }
            foreach ($val as $key => $value) {
                $create[$key] = $value;
                foreach ($this->params['prevent_date'] as $v) {
                    $v = trim($v);
                    if ($key == $v) {
                        $value = str_replace(',', '.', $value);
                    }
                }
                $create[$key] = $value;
            }
            if (!isset($_POST['test'])) {
                if (!$inbase) { //не существует в базе
                    $create['parent'] = $this->modx->db->escape($_POST['parimp']);
                    if ($_POST['tpl']) $tpl = $this->modx->db->escape($_POST['tpl']);
                    if ($tpl != 'file') $create['template'] = $tpl;
                    if ($this->issetPrepare) {
                        $create = $this->makePrepare($create, 'new');
                    }
                    $this->doc->create($create);
                    $new = $this->doc->save(true, false);
                    $i++;
                } else if ($inbase > 0) {
                    if ($this->issetPrepare) {
                        $create = $this->makePrepare($create, 'upd');
                    }
                    $edit = $this->doc->edit($inbase)->fromArray($create)->save(true, false);
                    $j++;
                } else {
                //ошибка проверки
                }
            } else { //тестовый режим
                if ($this->issetPrepare) {
                    $create = $this->makePrepare($create, 'upd');
                }
                foreach ($create as $key => $val) {
                    $log .= $key . ' - ' . $val . ' - Тестовый режим! <br>';
                    $log .= '<hr>';
                }
                return ($_SESSION['import_total'] - $this->start_line) . '|' . ($_SESSION['import_total'] - $this->start_line) . '|' . $log;
            }
        }
        $_SESSION['import_i'] += $i;
        $_SESSION['import_j'] += $j;
        if (!isset($_POST['test'])) {
            $log .= '<br><b>Добавлено - ' . $_SESSION['import_i'] . ', отредактировано - ' . $_SESSION['import_j'] . ' -> [ok!]</b> <hr>';
        }
        $_SESSION['import_start'] = $start + $i + $j;
        return ($_SESSION['import_start'] - $this->start_line) . '|' . ($_SESSION['import_total'] - $this->start_line) . '|' . $log;
    }

    protected function newMassif($data)
    {
        $j = 0;
        $sheetDataNew = array();

        foreach ($data[1] as $zna) {
            $newkeys[$j] = $zna;
            $j++;
        }

        foreach ($data as $k => $val) {
            if ($k > 1) {
                $i = 0;
                foreach ($val as $key => $value) {
                    $z = $newkeys[$i];
                    $dn[$z] = $value;

                    $i++;
                }
                $sheetDataNew[$k] = $dn;
            }
        }
        unset ($data);
        return $sheetDataNew;
    }

    protected function table($data, $max = false)
    {
        $header = '<table class="tabres">';
        $footer = '</table>';
        $this->zag = $data[1];
        $out = '';
        $i = 0;
        foreach ($data as $k => $val) {
            $row = '';
            $i++;
            if ($max && $max + 1 < $i) break;
            foreach ($val as $key => $value) {
                $row .= '<td>' . $value . '</td>';
            }
            $out .= '<tr>' . $row . '</tr>';
        }
        return $header . $out . $footer;
    }

    protected function checkField($field)
    {
        $param = array();
        $res = $this->modx->db->getValue("SELECT name FROM " . $this->modx->getFullTableName('site_tmplvars') . " WHERE `name`='" . $field . "'");
        $temp = 0;
        if ($res) {
            $temp = 1;
            $param[0] = 'tv';
            $param[1] = $field;
        }
        if ($temp == 0) {
            $res = $this->modx->db->query("SHOW columns FROM " . $this->modx->getFullTableName('site_content') . " where Field = '" . $field . "'");
            if ($this->modx->db->getRecordCount($res) > 0) {
                $param[0] = 'nonetv';
                $param[1] = $field;
            } else {
                $param[0] = 'notfound';
                $param[1] = $field;
            }
        }
        return $param;
    }

    public function getID($mode)
    {
        if ($mode[0] == 'tv') {
            $res = $this->modx->db->query("SELECT contentid FROM " . $this->modx->getFullTableName('site_tmplvar_contentvalues') . " WHERE value='" . $mode[2] . "'");
            if ($this->modx->db->getRecordCount($res) > 0) {
                $row = $this->modx->db->getRow($res);
                return $row['contentid'];
            } else {
                return false;
            }
        } else if ($mode[0] == 'nonetv') {
            $res = $this->modx->db->query("SELECT id FROM " . $this->modx->getFullTableName('site_content') . " WHERE " . $mode[1] . "='" . $mode[2] . "'");
            if ($this->modx->db->getRecordCount($res) > 0) {
                $row = $this->modx->db->getRow($res);
                return $row['id'];
            } else {
                return false;
            }
        } else return 'NO';
    }

    public function export()
    {
        $depth = $this->modx->db->escape($_POST['depth']);
        $parent = $this->modx->db->escape($_POST['stparent']);
        $filename = MODX_BASE_PATH .'assets/modules/editdocs/uploads/export.csv';
        $this->checkPrepareSnip();//проверяем, есть ли обработчик prepare (сниппет)

        if ($_POST['fieldz']) {
            $file = fopen($filename, 'w+');

            $fields = $this->modx->db->escape($_POST['fieldz']);
            array_unshift($fields, 'id');
            foreach ($fields as $key => $val) {
                $tvlist .= $val . ',';
                $ph .= '[+' . $val . '+];';
                $head .= $val . ';';
                $header[] = $val;
            }
            $tvlist = substr($tvlist, 0, strlen($tvlist) - 1);
            $ph = substr($ph, 0, strlen($ph) - 1);
            $head = substr($head, 0, strlen($head) - 1) . "\r\n";
            $this->last = array_pop($fields);
            //to win1251
            if ($_POST['neopub']) $addw = 1; else $addw = '';
            
            fputcsv($file, $header, ";");

            $DL = $this->modx->runSnippet('DocLister', array(
                'api' => 1,
                'idType' => 'parents',
                'depth' => $depth,
                'parents' => $parent,
                'showParent' => -1,
                'id' => 'list',
                'display' => 'all',
                'tvPrefix' => '',
                'orderBy' => 'id ASC',
                'tvList' => $tvlist,
                'tpl' => '@CODE:' . $ph,
                'prepare' =>  function($data, $modx){
                    //$data[$this->last] = $data[$this->last] . "\r\n";
                    foreach ($this->params['prevent_date'] as $v) {
                        $v = trim($v);
                        if (isset($data[$v])) {
                            $data[$v] = str_replace('.', ',', $data[$v]);
                        }
                    }
                    if ($this->issetPrepare) {
                        $data = $this->makePrepare($data, 'upd', 'export');
                    }
                    return $data;
                },
                'showNoPublish' => $addw
            ));
            $DL = json_decode($DL, true);
            foreach ($DL as $string) {
                $import = array();
                foreach ($header as $k => $v) {
                    $import[] = ($_POST['win'] == 1) ? iconv('UTF-8', 'WINDOWS-1251', $string[$v]) : $string[$v];
                }
                fputcsv($file, $import, ";");
            }
            fclose($file);
        }
        //$file = MODX_BASE_PATH .'assets/modules/editdocs/uploads/export.csv';
        //file_put_contents($file, $head . $out);
        if(file_exists($filename)) return 'Success!';
        else return 'Файла не существует!';

    }

    public function clearCache($type = 'full')
    {
        $this->modx->clearCache($type);
        foreach (glob(MODX_BASE_PATH . 'assets/modules/editdocs/uploads/*') as $file) {
            unlink($file);
        }
    }

    protected function checkArt($art)
    {
        $this->art = $art;
        $this->res = $this->modx->db->query("SELECT contentid,value FROM " .$this->modx->getFullTableName('site_tmplvar_contentvalues')." WHERE  value = '".$this->art."'");
        $this->data = $this->modx->db->getRecordCount($this->res);
        return $this->data;
    }

    public function makePrepare($data, $mode = 'upd', $process = 'import') 
    {
        $data = $this->modx->runSnippet($this->snipPrepare, array('data' => $data, 'mode' => $mode, 'process' => $process));
        return $data;
    }
    
    public function checkPrepareSnip()
    {
        $this->issetPrepare = $this->modx->db->getValue("SELECT id FROM " . $this->modx->getFullTableName("site_snippets") . " WHERE `name`='" . $this->modx->db->escape($this->snipPrepare) . "' LIMIT 0,1") ? $this->modx->db->escape($this->snipPrepare) : false;
        return $this;
    }

}

?>