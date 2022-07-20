<?php
namespace booosta\datatable;
\booosta\Framework::init_module('datatable');


class Datatable extends \booosta\ui\UI
{
  use moduletrait_datatable;

  protected $data;
  protected $autoheader;
  public $lang;
  protected $display_length;
  protected $class, $thead_class, $tbody_class, $tr_class;
  protected $lightmode = false;
  protected $use_form;
  protected $statesave = true;
  protected $tableclass = 'table table-striped display responsive';
  protected $ajaxurl;
  protected $omit_columns = [];    // omitted in mobile devices


  public function __construct($id = 'datatable', $data = null, $autoheader = false)
  {
    parent::__construct($id);
    $this->data = $data;
    $this->autoheader = $autoheader;
    $this->display_length = $this->config('datatable_display_length') ?? '25';
    #\booosta\debug($this->display_length);

    if(is_bool($this->config('datatable_statesave'))) $this->statesave = $this->config('datatable_statesave');
  }


  public function after_instanciation()
  {
    parent::after_instanciation();

    if(is_object($this->topobj) && is_a($this->topobj, "\\booosta\\webapp\\Webapp")):
      $this->topobj->moduleinfo['datatable'] = true;
      if($this->topobj->moduleinfo['jquery']['use'] == '') $this->topobj->moduleinfo['jquery']['use'] = true;
    endif;
  }


  public function set_data($data) { $this->data = $data; }
  public function set_lang($lang) { $this->lang = $lang; }
  public function set_autoheader($autoheader = true) { $this->autoheader = $autoheader; }
  public function set_display_length($display_length) { $this->display_length = $display_length; }
  public function set_lightmode($lightmode = true) { $this->lightmode = $lightmode; }
  public function set_ajaxurl($ajaxurl) { $this->ajaxurl = $ajaxurl; }
  public function set_statesave($statesave = true) { $this->statesave = $statesave; }
  public function set_tableclass($tableclass) { $this->tableclass = $tableclass; }
  
  public function use_form($name = 'form0') { $this->use_form = $name; }
  public function set_class($data) { $this->class = $data; }
  public function set_tbody_class($data) { $this->tbody_class = $data; }
  public function set_thead_class($data) { $this->thead_class = $data; }
  public function set_tr_class($data) { $this->tr_class = $data; }

  public function set_omit_columns($data) { $this->omit_columns = $data; }
  public function add_omit_column($data) { $this->omit_columns[] = $data; }


  public function get_html_includes($libpath = 'lib/modules/datatable')
  {
    return "<script type='text/javascript' src='$libpath/datatables.min.js'></script>
             <style type='text/css' title='currentStyle'> @import '$libpath/datatables.min.css' </style>";
  }


  public function get_js() 
  { 
    $ss = $this->t('Search all columns');
    $lenghtMenu = $this->t('Show _MENU_ entries');
    $nodata = $this->t('No data available in table');

    #\booosta\debug("lightmode: $this->lightmode"); \booosta\debug($this->data);
    #\booosta\debug("lightmode: $this->lightmode");
    if(is_bool($this->lightmode)):
      $lightmode = $this->lightmode;
    elseif(is_numeric($this->lightmode) && is_array($this->data)):
      $lightmode = (sizeof($this->data) <= intval($this->lightmode));
    elseif(is_numeric($this->lightmode) && is_string($this->data)):
      $count = substr_count(str_replace(' ', '', $this->data), '<tr>');
      $lightmode = ($count <= intval($this->lightmode));
    else:
      $lightmode = false;
    endif;

    if($lightmode) $optcode = "'paging': false, 'searching': false, 'lengthChange': false, ";
    else $optcode = "'paging': true, 'searching': true, 'lengthChange': true, ";

    if($this->statesave) $optcode .= "'stateSave': true, ";
    if($this->ajaxurl) $ajaxcode = "'serverSide' : true, 'ajax': { 'url': '$this->ajaxurl', 'type': 'GET' }, ";

    $code = "
      var datatable_$this->id = $('#datatable_$this->id').dataTable({
      $optcode $ajaxcode
      'bJQueryUI': true, 'bPaginate': true, 'sPaginationType': 'full_numbers',
      'pageLength': $this->display_length, 'bInfo': false, 'aaSorting': [],
      'responsive': { 'details': false },
      'language': {
        'search': '$ss:', 'lengthMenu': '$lenghtMenu', 'emptyTable': '$nodata', 'zeroRecords': '$nodata',
        'oPaginate': { 'sNext': '&gt;', 'sLast': '&gt;&gt;', 'sFirst': '&lt;&lt;', 'sPrevious': '&lt;' }
      }}); ";

    if($this->use_form)
      $code .= "   $('#$this->use_form').on('submit', function(e){
      var form = this;
      var params = datatable_$this->id.$('input,select,textarea').serializeArray();
      $.each(params, function(){
         if(!$.contains(document, form[this.name])){
            $(form).append(
               $('<input>')
                  .attr('type', 'hidden')
                  .attr('name', this.name)
                  .val(this.value)
      ); } }); }); ";
      
    if(is_object($this->topobj) && is_a($this->topobj, '\booosta\webapp\webapp')):
      $this->topobj->add_jquery_ready($code);
      return '';
    else:
      return "$(document).ready(function(){ $code })";
    endif;
  }


  public function get_htmlonly()
  {
    #\booosta\debug($this->data);
    if(is_string($this->data)):
      if($this->ajaxurl):
        $code = str_replace(['<tbody>', '</tbody>'], '', $this->data);
      else:
        $code = $this->data;
      endif;
    else:
      if($this->ajaxurl):
        $code = $this->process_header();
        $code .= '</table>';
      else: 
        $code = $this->process_header();

        if(!is_array($this->tabledata)) return false;
        $code .= '<tbody>';

        foreach($this->tabledata as $line):
          if(!is_array($line)) $line = [$line];
          $code .= '<tr>';
          foreach($line as $val) $code .= "<td>$val</td>";
          $code .= '</tr>';
        endforeach;

        $code .= '</tbody>';
        $code .= '</table>';
      endif;
    endif;

    return $code;
  }

  protected function process_header()
  {
    $class = $this->tableclass ? "class='$this->tableclass'" : '';
    $code = "<table id='datatable_$this->id' $class>";

    if(!is_array($this->data['header']) && $this->autoheader):
      $this->tabledata = $this->data;    // if ['header'] exists, also ['data'] must exist in $this->data
      $this->data = [];
      $this->data['data'] = $this->tabledata;
      $this->data['header'] = [];

      $line = $this->data['data'][0];
      #\booosta\debug($line);
      foreach($line as $var=>$val) $this->data['header'][] = $this->t($var);
    endif;
    #\booosta\debug($this->data);

    $thead_class = $this->thead_class ? "class='$this->thead_class'" : '';
    
    if(is_array($this->data['header'])):
      $code .= "<thead $thead_class><tr>";

      #debug($this->omit_columns);
      foreach($this->data['header'] as $header):
        $thclass = in_array($header, $this->omit_columns) ? 'class="min-phone-l"' : 'class="all"';
        $code .= "<th $thclass>$header</th>";
      endforeach;

      $code .= '</tr></thead>';

      $this->tabledata = $this->data['data'];  // if ['header'] exists, also ['data'] must exist in $this->data
    else:
      $this->tabledata = $this->data;   // if ['header'] does not exist, $this->data holds the data directly

      // get size of longest array in data and fill header with <th> elements of this number
      $maxsize = 0;
      $header = '';

      foreach($this->tabledata as $dat) $maxsize = max($maxsize, sizeof($dat));
      for($i=0; $i<$maxsize; $i++) $header .= '<th>&nbsp;</th>';
      $code .= "<thead $thead_class><tr>$header</tr></thead>";
    endif;

    return $code;
  }
}
