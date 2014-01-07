<?php
/*
Plugin Name: WP Comment Image
Plugin URI: https://github.com/xjpvictor/wp-comment-image
Version: 0.0.1
Author: xjpvictor Huang
Description: A wordpress plugin to allow using images for comments.
*/

if(!class_exists('wp_comment_image')):
class wp_comment_image{

  var $options_key = array('wpci_url', 'wpci_dir', 'wpci_width', 'wpci_size', 'wpci_limit', 'wpci_class', 'wpci_input', 'wpci_input_text');
  var $options = array();
  var $message = '';
  var $images = array();
  var $upload_limit = '';
  var $incre_id = '';

  function wp_comment_image(){
    $this->wpci_init();
    $this->wpci_init_hook();
    if (!empty($_POST))
      $this->wpci_options_update();
  }

  function wpci_init(){
    $this->upload_limit = ini_get('upload_max_filesize');
    $this->incre_id = 0;
    $unit = strtolower(substr($this->upload_limit, strlen($this->upload_limit)-1));
    switch($unit) {
    case 'g':
      $this->upload_limit = (int)$this->upload_limit * 1024;
    case 'm':
      $this->upload_limit = (int)$this->upload_limit * 1024;
    case 'k':
      $this->upload_limit = (int)$this->upload_limit * 1024;
    }
    $this->upload_limit = round((int)$this->upload_limit / 1024 / 1024, 2);

    $options_default = array(site_url('/wp-content/comment-images/'), ABSPATH.'wp-content/comment-images/', '200', min(10, $this->upload_limit), '10', 'wpci', '1', 'You may add up to [wpci_limit] png/gif/jpg images (less than [wpci_size]M each)');

    foreach ($this->options_key as $key => $option_key) {
      $this->options[$option_key] = get_option($option_key);
      if (!$this->options[$option_key]) {
        update_option($option_key, $options_default[$key]);
        $this->options[$option_key] = $options_default[$key];
      }
      if ($option_key == 'wpci_dir') {
        if (substr($this->options[$option_key], -1) !== '/')
          $this->options[$option_key] .= '/';
        if (!file_exists($this->options[$option_key])) {
          if (!mkdir($this->options[$option_key])) {
            $this->message = 'Error create directory';
          }
        }
      } elseif ($option_key == 'wpci_url') {
        if (substr($this->options[$option_key], -1) !== '/')
          $this->options[$option_key] .= '/';
      } elseif ($option_key == 'wpci_size') {
        $this->options[$option_key] = round(min($this->options[$option_key], $this->upload_limit), 2);
      }
    }
  }

  function wpci_init_hook(){
    if (is_admin()) {
      add_filter('delete_comment', array(&$this, 'wpci_delete_comment'));
      add_action('admin_menu', array(&$this, 'wpci_options_page'));
      add_action('add_meta_boxes', array(&$this, 'wpci_add_meta_boxes'));
      add_action('comment_save_pre', array(&$this, 'wpci_edit_comment'));
    } else {
      add_filter('comment_text', array(&$this, 'wpci_comment_text'));
      add_filter('wp_insert_comment', array(&$this, 'wpci_insert_comment'), 99);
      add_filter('comment_form_field_comment', array(&$this, 'wpci_comment_form'), 99);
    }
    register_deactivation_hook( __FILE__, array(&$this, 'wpci_deactivate'));
  }

  function wpci_deactivate() {
    foreach ($this->options_key as $option) {
      delete_option($option);
    }
  }

  function wpci_options_page(){
    add_options_page('WP Comment Image Option', 'WP Comment Image', 'manage_options', 'wp-comment-image.php', array(&$this, 'options_page'));
  }

  function options_page(){
  ?>
  <div class="wrap">
    <h2>WP Comment Image</h2>
    <p><strong>A wordpress plugin to allow using images for comments.</strong></p>
      <fieldset name="wp_basic_options"  class="options">
    <form method="post" action="">
      <?php if (!empty($this->message)) echo '<p style="color:red;">'.$this->message.'</p>'; ?>
      <p>Base URL for images:<br/>
      <input required type="text" class="regular-text" name="wpci_url" value="<?php echo htmlentities($this->options['wpci_url']); ?>" /></p>
      <p>Directory for images:<br/>
      <input required type="text" class="regular-text" name="wpci_dir" value="<?php echo htmlentities($this->options['wpci_dir']); ?>" /></p>
      <p>Thumbnail width:<br/>
      <input required type="text" size="4" name="wpci_width" value="<?php echo $this->options['wpci_width']; ?>" /> px</p>
      <p>Maximum image file size (0 for unlimited, must be less than <?php echo $this->upload_limit; ?>):<br/>
      <input required type="text" size="4" name="wpci_size" value="<?php echo $this->options['wpci_size']; ?>" /> MB</p>
      <p>Maximum number of files uploaded (0 for unlimited):<br/>
      <input required type="text" size="4" name="wpci_limit" value="<?php echo $this->options['wpci_limit']; ?>" /></p>
      <p>CSS class for images (Optional, separate by space):<br/>
      <input type="text" class="regular-text" name="wpci_class" value="<?php echo htmlentities($this->options['wpci_class']); ?>" /></p>
      <p>Add upload button (Optional):<br/>
      <label><input type="radio" name="wpci_input" value="1" <?php if ($this->options['wpci_input'] == '1') { ?> checked="checked"<?php } ?>/> Automatic, may not work depending on your theme</label><br/>
      <label><input type="radio" name="wpci_input" value="0" <?php if ($this->options['wpci_input'] == '0') { ?> checked="checked"<?php } ?>/> Manual, add <span style="padding:2px 5px;border:1px solid #aaa;">enctype="multipart/form-data"</span> and <span style="padding:2px 5px;border:1px solid #aaa;">&lt;input type="file" name="image[]" multiple/&gt;</span> to comment form</label></p>
      <p>Text before input button (Optional):<br/>
      <textarea rows="5" cols="50" class="large-text" name="wpci_input_text"><?php echo htmlentities($this->options['wpci_input_text']); ?></textarea><br/>
      * Use [wpci_limit] as maximum upload files and [wpci_size] as maximum file size</p>
      <input type="submit" class="button button-primary" name="wpci_update" />
    </form>
      </fieldset>
  </div>
  <?php
  }

  function wpci_options_update() {
    if (isset($_POST['wpci_update'])) {
      if (array_key_exists('wpci_url', $_POST)) {
        foreach ($this->options_key as $option) {
          if (isset($_POST[$option]) && $_POST[$option] !== $this->options[$option]) {
            if ($option == 'wpci_dir') {
              if (substr($_POST[$option], -1) !== '/')
                $_POST[$option] .= '/';
              if (!file_exists($_POST[$option])) {
                if (!mkdir($_POST[$option])) {
                  $this->message = 'Error create directory';
                }
              }
            }
            if ($option == 'wpci_url') {
              if (substr($_POST[$option], -1) !== '/')
                $_POST[$option] .= '/';
            }
            if ($option == 'wpci_size') {
              $_POST[$option] = round(min($_POST[$option], $this->upload_limit), 2);
            }
            update_option($option, $_POST[$option]);
            $this->options[$option] = $_POST[$option];
          }
        }
      }
    }
  }

  function wpci_comment_text($comment = '') {
    if (!empty($this->options['wpci_class']))
      $comment = preg_replace('/<a href=([^>]+)><img src=([^>]+)><\/a>/', '<a class="'.$this->options['wpci_class'].'" href=${1}><img class="'.$this->options['wpci_class'].'" src=${2}></a>', $comment);
    return $comment;
  }

  function wpci_insert_comment($comment_id = '') {
    $comment = array();
    $comment['comment_ID'] = $comment_id;

    if ($this->wpci_get_image($comment['comment_ID'])) {
      add_filter('pre_comment_content', array(&$this, 'wpci_pre_comment_content'), 99);
      wp_update_comment($comment);
    }

    return $comment_id;
  }

  function wpci_edit_comment($comment_content) {
    $comment_id = $_POST['comment_ID'];
    $this->incre_id = 1;

    if ($this->wpci_get_image($comment_id))
      $comment_content = $this->wpci_pre_comment_content($comment_content);

    if (isset($_POST['wpci_del'])) {
      foreach ($_POST['wpci_del'] as $file => $value) {
        if (file_exists($this->options['wpci_dir'].$file))
          unlink($this->options['wpci_dir'].$file);
        if (file_exists($this->options['wpci_dir'].$file.'-t.jpg'))
          unlink($this->options['wpci_dir'].$file.'-t.jpg');
        $comment_content = preg_replace('/<br\/><a href="[^"]+'.$file.'" title="[^"]+"[^>]*><img src="[^"]+'.$file.'(-t.jpg)?" alt="[^"]+"[^>]*><\/a><br\/>/', '', $comment_content);
      }
    }

    return $comment_content;
  }

  function wpci_get_image($comment_id) {
    if (isset($_POST['wpci_drop_file']) && $_POST['wpci_drop_file'] !== '') {
      $files = array();
      $drop_files = explode('|', substr($_POST['wpci_drop_file'], 0, strrpos($_POST['wpci_drop_file'], '|')));
      $drop_filenames = json_decode($_POST['wpci_drop_filename']);
      foreach ($drop_files as $id => $data) {
        $rand_string = substr("abcdefghijklmnopqrstuvwxyz", mt_rand(0, 50), 1).substr(md5(time()), 1);
        $tmp_file = tempnam(sys_get_temp_dir(), $rand_string);
        file_put_contents($tmp_file, base64_decode((strpos($data, ',') !== false?substr($data, strpos($data, ',')+1):$data)));
        $files['tmp_name'][$id] = $tmp_file;
        if (isset($drop_filenames[$id]))
          $files['name'][$id] = $drop_filenames[$id];
        else
          $files['name'][$id] = '';
      }
    } elseif (isset($_FILES['image']['tmp_name']) && ((is_array($_FILES['image']['tmp_name']) && $_FILES['image']['tmp_name'][0] !== '') || (is_string($_FILES['image']['tmp_name']) && $_FILES['image']['tmp_name'] !== ''))) {
      $files = $_FILES['image'];
    } else
      return $comment_id;

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $i = 0;
    $j = 0;
    if ($this->incre_id) {
      $file_list = $this->wpci_get_image_list($comment_id);
      if (!empty($file_list)) {
        foreach ($file_list as $file) {
          $file = substr($file, strrpos($file, '/')+1);
          $j = max($j, (int)substr($file, strpos($file, '-')+1, strpos($file, '.')-strpos($file, '-')-1)+1);
        }
      }
    }

    if (is_string($files['name'])) {
      $files['name'] = array($files['name']);
      $files['tmp_name'] = array($files['tmp_name']);
    }

    foreach ($files['name'] as $image_id => $file) {
      $name = $files['name'][$image_id];
      $tmp_file = $files['tmp_name'][$image_id];
      if ($this->options['wpci_size'] && !is_admin() && round(filesize($tmp_file) / 1024 / 1024, 2) > $this->options['wpci_size']) {
        unlink($tmp_file);
        $error = true;
      } elseif (!$this->options['wpci_limit'] || $i < $this->options['wpci_limit'] || is_admin()) {
        $type = finfo_file($finfo, $tmp_file);
        if ($type == 'image/jpeg' || $type == 'image/png' || $type == 'image/gif') {
          $ext = str_replace('jpeg', 'jpg', substr($type, strpos($type, '/')+1));
          $file_name = $comment_id.'-'.($i+$j).'.'.$ext;
          $image = wp_get_image_editor($tmp_file);
          if (!is_wp_error($image)) {
            $size = $image->get_size();
            if ($size['width'] > $this->options['wpci_width'] || $type !== 'image/jpeg') {
              if (!file_exists($this->options['wpci_dir'].$file_name.'-t.jpg')) {
                $image->resize($this->options['wpci_width'], '', false);
                $image->save($this->options['wpci_dir'].$file_name.'-t.jpg', 'image/jpeg');
              }
              $this->images[$file_name] = array('1', $name);
            } else
              $this->images[$file_name] = array('0', $name);
          }
          if (!file_exists($this->options['wpci_dir'].$file_name)) {
            if (is_uploaded_file($tmp_file))
              move_uploaded_file($tmp_file, $this->options['wpci_dir'].$file_name);
            else
              rename($tmp_file, $this->options['wpci_dir'].$file_name);
          } elseif (file_exists($tmp_file))
            unlink($tmp_file);
          $i++;
        } elseif (file_exists($tmp_file)) {
          unlink($tmp_file);
          $error = true;
        }
      } else {
        unlink($tmp_file);
        $error = true;
      }
    }
    $_FILES = array();
    if (isset($_POST['wpci_drop_file']))
      unset($_POST['wpci_drop_file']);

    if (isset($error)) {
      wp_delete_comment($comment_id, true);
      wp_die('Only png, gif, jpg are allowed and '.(!$this->options['wpci_limit']?'':'maximum '.$this->options['wpci_limit'].' images, ').(!$this->options['wpci_size']?'':'each file less than '.$this->options['wpci_size'].'M'));
    }
    return $comment_id;
  }

  function wpci_pre_comment_content($comment) {
    if (!empty($this->images)) {
      foreach ($this->images as $file_name => $et) {
        $comment .= '<br/><a href="'.$this->options['wpci_url'].$file_name.'" title="'.$et[1].'"><img src="'.$this->options['wpci_url'].$file_name.($et[0]?'-t':'').'.jpg'.'" alt="'.$et[1].'" /></a><br/>';
      }
    }
    return $comment;
  }

  function wpci_delete_comment($comment_id = '') {
    $files = glob($this->options['wpci_dir'].$comment_id.'-*', GLOB_NOSORT);
    if (!empty($files)) {
      foreach ($files as $file) {
        unlink($file);
      }
    }
    return $comment_id;
  }

  function wpci_upload_input() {
    return '
<div id="wpci-input" style="padding:0;width:99%;position:relative;'.(is_admin()?'margin:15px 0;':'margin:0;top:-1px;').'">
<input type="text" name="wpci-text" id="wpci-text" readonly
  style="width:60%;color:#4c4c4c;background-color:#f6f6f6;height:1.3em;line-height:1.3em;padding:5px 0.5%;margin:0;text-align:left;border-width:1px 0 1px 1px;border-color:#000;border-style:solid;position:relative;z-index:3;box-sizing:content-box;-moz-box-sizing:content-box;cursor:default;vertical-align:top;'.(!is_admin()?'border-top:none;':'').'"
 value="'.(!is_admin() && !empty($this->options['wpci_input_text'])?str_replace(array('[wpci_limit]', '[wpci_size]'), array($this->options['wpci_limit'], $this->options['wpci_size']), $this->options['wpci_input_text']):'').(is_admin()?'You may add png/gif/jpg images':'').'"
><input type="button" id="wpci-button" value="Choose..."
 style="width:120px;color:#4c4c4c;height:1.3em;line-height:1.3em;padding:5px 0px;margin:0;text-align:center;border-width:1px 1px 1px 0px;border-color:#000;border-style:solid;background-color:#e3e3e3;box-sizing:content-box;-moz-box-sizing:content-box;vertical-align:top;'.(!is_admin()?'border-top:none;':'').'"
><span id="wpci-file-wrap"
 style="position:absolute;opacity:0;top:0;left:61%;width:120px;height:1.3em;height:100%;border:none;margin:0;padding:0;z-index:2;box-sizing:content-box;-moz-box-sizing:content-box;"
>
<input type="file" multiple id="wpci-file" accept="image/jpeg,image/png,image/gif" name="image[]"
 style="width:120px;height:1.3em;height:100%;border:none;margin:0;padding:0;box-sizing:content-box;-moz-box-sizing:content-box;"
 onchange="
   if(!window.File || !window.FileList || !window.FileReader){
     var files=this.files, file=\'\';
     for(var i=0;i<files.length-1;i++){
      file += files[i].name + \', \';
     }
     file += files[i].name;
     document.getElementById(\'wpci-text\').value=file;
     document.getElementById(\''.(!is_admin()?'wpci-input\').parentNode':'post\')').'.enctype=\'multipart/form-data\';
     document.getElementById(\'wpci-clear\').style.display=\'inline-block\';
   }
 "
></span>
<span id="wpci-drop"
 style="display:none;color:#4c4c4c;font-size:16px;line-height:1.3em;padding:3px 5px;margin:1px;vertical-align:middle;border:1px dashed transparent;"
>or drop files here</span>
<input id="wpci-drop-file" name="wpci_drop_file" style="display:none;"><input id="wpci-drop-filename" name="wpci_drop_filename" value="" style="display:none;"><span id="wpci-clear"
 style="height:1.2em;line-height:1.2em;font-size:14px;padding:3px;left:61%;top:0;position:absolute;z-index:4;background-color:#eee;color:#;border-width:0 1px 1px;border-color:#ccc;border-style:solid;margin:0 0 0 -42px;display:none;cursor:pointer;"
 onclick="
   if(window.File && window.FileList && window.FileReader){
     wpciDnd();
   }else{
     document.getElementById(\'wpci-file-wrap\').innerHTML=document.getElementById(\'wpci-file-wrap\').innerHTML;
   }
   document.getElementById(\'wpci-text\').value=\''.(!is_admin() && !empty($this->options['wpci_input_text'])?str_replace(array('[wpci_limit]', '[wpci_size]'), array($this->options['wpci_limit'], $this->options['wpci_size']), $this->options['wpci_input_text']):'').(is_admin()?'You may add png/gif/jpg images':'').'\';
   this.style.display=\'none\';
   document.getElementById(\'wpci-text\').style.width=\'60%\';
   document.getElementById(\'wpci-text\').style.paddingRight=\'0.5%\';
   document.getElementById(\'wpci-error\').style.display=\'none\';
   document.getElementById(\'wpci-drop-file\').value=\'\';
   document.getElementById(\'wpci-drop-filename\').value=\'\';
 "
>clear</span>
<div id="wpci-error" style="color:red;font-size:14px;padding:10px 0;display:none;">* Some files will not be uploaded. Only png, gif, jpg are allowed'.(!is_admin()?' and '.(!$this->options['wpci_limit']?'':'maximum '.$this->options['wpci_limit'].' images, ').(!$this->options['wpci_size']?'':'each file less than '.$this->options['wpci_size'].'M'):'').'.</div>
</div>
      <script>
        if (window.File && window.FileList && window.FileReader) {
          var t = "", j = 0, fn = [];
          wpciDnd();
        }
        function wpciDnd() {
          document.getElementById("wpci-file-wrap").innerHTML=document.getElementById("wpci-file-wrap").innerHTML;
          document.getElementById("wpci-file").addEventListener("change", FileSelectHandler, false);
          var filedrop = document.getElementById("wpci-drop");
          filedrop.addEventListener("dragover", FileDragHover, false);
          filedrop.addEventListener("dragleave", FileDragHover, false);
          filedrop.addEventListener("drop", FileSelectHandler, false);
          filedrop.style.display = "inline-block";
        }
        function FileDragHover(e) {
          e.stopPropagation();
          e.preventDefault();
          e.target.style.borderColor = (e.type == "dragover" ? "#000" : "transparent");
        }
        function FileSelectHandler(e) {
          FileDragHover(e);
          var files = e.target.files || e.dataTransfer.files;
          for (var i = 0; i < files.length-1; i++) {
            f = files[i];
            //readFile(f, 
            var reader = new FileReader();
            reader.onload = ParseFile(f, 0);
            reader.readAsDataURL(f);
          }
          var reader = new FileReader();
          reader.onload = ParseFile(files[i], 1);
          reader.readAsDataURL(files[i]);
        }
        function ParseFile(file, o) {
          return function(e) {
            if ('.(!is_admin() && $this->options['wpci_limit']?'j < '.$this->options['wpci_limit'].' && ':'').'(file.type.indexOf("image/png") == 0 || file.type.indexOf("image/jpeg") == 0 || file.type.indexOf("image/gif") == 0)'.(!is_admin() && $this->options['wpci_size']?' && file.size / 1024 / 1024 < '.$this->options['wpci_size']:'').') {
              var d = e.target.result.match(/,(.*)$/)[1];
              var s = document.getElementById("wpci-drop-file").value;
              if (s.indexOf(d) == -1) {
                s = s + d + "|";
                document.getElementById("wpci-drop-file").value = s;
                fn[fn.length] = file.name;
                j++;
              }
            } else {
              document.getElementById("wpci-error").style.display="block";
            }
            if (o && j) {
              document.getElementById("wpci-clear").style.display="inline-block";
              document.getElementById("wpci-text").style.width = document.getElementById("wpci-text").offsetWidth - document.getElementById("wpci-input").offsetWidth * 0.01 - 45 + "px";
              document.getElementById("wpci-text").style.paddingRight = document.getElementById("wpci-input").offsetWidth * 0.005 + 45 + "px";
              t = "";
              for (i = 0; i < fn.length-1; i++) {
                t = t + fn[i] + ", ";
              }
              t += fn[i];
              document.getElementById("wpci-text").value = t;
              document.getElementById("wpci-drop-filename").value = JSON.stringify(fn);
            }
          }
        }
      </script>
    ';
  }

  function wpci_comment_form($form = '') {
    if (!empty($form) && $this->options['wpci_input']) {
      $form = str_replace('<textarea ', '<textarea style="margin-bottom:0;vertical-align:bottom;" ', $form).$this->wpci_upload_input();
    }
    return $form;
  }

  function wpci_add_meta_boxes() {
    add_meta_box('wpci_edit_comment_images', 'Images', array(&$this, 'wpci_edit_comment_images'), 'comment', 'normal', 'low');
  }

  function wpci_get_image_list($comment_id) {
    $files = glob($this->options['wpci_dir'].$comment_id.'-*', GLOB_NOSORT);
    if (!empty($files)) {
      $thumbs = glob($this->options['wpci_dir'].$comment_id.'-*-t.jpg', GLOB_NOSORT);
      $files = array_diff($files, $thumbs);
    }
    return $files;
  }

  function wpci_edit_comment_images() {
    $comment_id = get_comment_ID();
    if (empty($comment_id))
      return true;

    $files = $this->wpci_get_image_list($comment_id);
    if (!empty($files)) {
      echo '<p>Select to delete image:</p>';
      foreach ($files as $file) {
        $file = substr($file, strrpos($file, '/')+1);
        echo '<label style="display:inline-block;vertical-align:top;position:relative;border:1px solid #000;margin:0 15px 15px 0;padding:0;line-height:0;"><input style="margin:0px;position:absolute;top:3px;left:3px;z-index:2;" type="checkbox" name="wpci_del['.$file.']" value="1" /><img style="max-width:100px;margin:0;padding:0;"  src="'.$this->options['wpci_url'].$file.(file_exists($this->options['wpci_dir'].$file.'-t.jpg')?'-t.jpg':'').'" alt="'.$file.'" /></label>';
      }
    } else
      echo 'No image';
    echo '<br/>'.$this->wpci_upload_input();
    return true;
  }

}
endif;

$new_wp_comment_image = new wp_comment_image;
?>
