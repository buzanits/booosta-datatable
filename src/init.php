<?php
namespace booosta\datatable;

\booosta\Framework::add_module_trait('webapp', 'datatable\webapp');

trait webapp
{
  protected function preparse_datatable()
  {
    if($this->moduleinfo['datatable'] && !$this->config('datatables_loaded'))
      $this->add_includes("<script type='text/javascript' src='{$this->base_dir}vendor/datatables.net/datatables.net-dt/js/dataTables.dataTables.min.js'></script>
                           <style type='text/css' title='currentStyle'> @import '{$this->base_dir}vendor/datatables.net/datatables.net-dt/css/jquery.dataTables.min.css' </style>");
  }

  protected function datatable_get_ajax_params()
  {
    $result['start'] = intval($this->VAR['start']);
    $result['length'] = intval($this->VAR['length']);
    $result['search'] = $this->VAR['search']['value'];
    $result['order'] = $this->VAR['order'][0]['column'];
    $result['orderdir'] = $this->VAR['order'][0]['dir'];

    return $result;
  }

  protected function datatable_after_process_data($data)
  {
    return array_values($data);
  }

  protected function datatable_process_data(&$data, $key, $param)
  {
    // set edit and delete links
    if(in_array('edit', $param['allfields'])):
      $editstr = $this->config('edit_pic_code');

      $edit_params = $this->edit_params ?: '?action=edit&object_id=';
      $edit = "<a href='$edit_params{$data['id']}'>$editstr</a>";
    endif;

    if(in_array('delete', $param['allfields'])):
      $deletestr = $this->config('delete_pic_code');

      $delete_params = $this->delete_params ?: '?action=delete&object_id=';
      $delete = "<a href='$delete_params{$data['id']}'>$deletestr</a>";
    endif;

    // use only fields in $this->fields, in order of this variable
    $fields = $param['fields'];
    $field_filter = array_flip($fields);
    $tmp = array_intersect_key(array_merge($field_filter, $data), $field_filter);
    $tmp = array_merge($tmp, $edit ? [$edit] : [], $delete ? [$delete] : []);

    // substitute foreign keys
    foreach($param['foreign_keys'] as $field=>$val)
      if(isset($tmp[$field]))
        $tmp[$field] = $this->DB->query_value("select `{$val['showfield']}` from `{$val['table']}` where `{$val['idfield']}`='{$tmp[$field]}'");

    // use replaces, links and extrafields from in_default_makelist() hook
    $list = $this->makeInstance("\\booosta\\webapp\\pseudo_tablelister");
    $this->in_default_makelist($list);

    // replaces
    $replaces = $list->get_replaces();
    #\booosta\debug($replaces);
    foreach($replaces as $fkey=>$repl)
      if(!is_string($repl) && is_callable($repl)):
        $func_reflection = new \ReflectionFunction($repl);
        $num_of_params = $func_reflection->getNumberOfParameters();

        if($num_of_params == 1) $tmp[$fkey] = $repl($tmp[$fkey]);
        else $tmp[$fkey] = $repl($tmp[$fkey], $data['id']);
      else:
        $tmp[$fkey] = $repl;
      endif;

    // post processing of data specific to the used datatable module
    if(is_callable([$this, 'datatable_after_process_data'])) $tmp = $this->datatable_after_process_data($tmp);
    $data = $tmp;
    #\booosta\debug($data);
  }

  protected function action_datatable_ajaxload()
  {
    $this->apply_userfield('action default', $this);
    #\booosta\debug("default_clause: $this->default_clause");
    $params = $this->datatable_get_ajax_params();
    #\booosta\debug($params);
    $whereclause = '';

    $start = intval($params['start']);
    $length = intval($params['length']);
    $search = $params['search'];
    $order = $params['order'];
    $orderdir = $params['orderdir'];

    $fields = $allfields = explode(',', $this->fields);
    $fields = array_diff($fields, ['edit', 'delete']);

    if(is_numeric($order)) $this->default_order = "{$fields[$order]} $orderdir";
    $this->default_clause ??= '0=0';
    $default_clause = $this->default_clause;

    if($search):
      foreach($fields as $field) $whereclause .= " or `$field` like '%$search%' ";
      $this->default_clause = "($default_clause) and (0=1 $whereclause)";
    endif;

    $name = $this->listclassname ?? $this->name;
    $sql = 'select count(*) from ' . $name . ' where ' . $this->default_clause;
    $total = $this->DB->query_value($sql);
    #\booosta\debug("$total $sql");
    if($length) $this->default_limit = "$start, $length";
    $data = $this->getall_data();
    #\booosta\debug($data);

    $this->normalize_foreign_keys();
    foreach($this->foreign_keys as $fk=>$val) $param['foreign_keys'][$fk] = $val;

    $param['fields'] = $fields;
    $param['allfields'] = $allfields;

    array_walk($data, [$this, 'datatable_process_data'], $param);
    $json = json_encode(['data' => $data, 'recordsFiltered' => $total]);
    #\booosta\debug($json);

    header('Content-type: application/json');
    header('Content-Length: ' . mb_strlen($json));
    print $json;

    $this->no_output = true;
  }
}
