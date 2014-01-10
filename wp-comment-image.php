<?php
/*
Plugin Name: WP Comment Image
Plugin URI: https://github.com/xjpvictor/wp-comment-image
Version: 0.0.1
Author: xjpvictor Huang
Description: A wordpress plugin to allow using images for comments.
*/

if (!class_exists('wp_comment_image')):
class wp_comment_image{

  var $options_key = array('wpci_url', 'wpci_dir', 'wpci_width', 'wpci_size', 'wpci_limit', 'wpci_class', 'wpci_input', 'wpci_input_text');
  var $options = array();
  var $message = '';
  var $images = array();
  var $upload_limit = '';
  var $incre_id = '';

  function wp_comment_image() {
    $this->wpci_init();
    $this->wpci_init_hook();
    if (!empty($_POST))
      $this->wpci_options_update();
  }

  function wpci_init() {
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

  function wpci_init_hook() {
    if (is_admin()) {
      add_filter('delete_comment', array(&$this, 'wpci_delete_comment'));
      add_action('admin_menu', array(&$this, 'wpci_options_page'));
      add_action('add_meta_boxes', array(&$this, 'wpci_add_meta_boxes'));
      add_action('comment_save_pre', array(&$this, 'wpci_edit_comment'));
      add_action('admin_head', array(&$this, 'wpci_head'));
    } else {
      add_filter('comment_text', array(&$this, 'wpci_comment_text'));
      add_filter('wp_insert_comment', array(&$this, 'wpci_insert_comment'), 99);
      add_filter('comment_form_field_comment', array(&$this, 'wpci_comment_form'), 99);
      add_action('wp_head', array(&$this, 'wpci_head'));
    }
    register_deactivation_hook( __FILE__, array(&$this, 'wpci_deactivate'));
  }

  function wpci_deactivate() {
    foreach ($this->options_key as $option) {
      delete_option($option);
    }
  }

  function wpci_options_page() {
    add_options_page('WP Comment Image Option', 'WP Comment Image', 'manage_options', 'wp-comment-image.php', array(&$this, 'options_page'));
  }

  function options_page() {
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
      <input required type="number" min="0" size="4" name="wpci_width" value="<?php echo $this->options['wpci_width']; ?>" /> px</p>
      <p>Maximum image file size (0 for unlimited, must be less than <?php echo $this->upload_limit; ?>):<br/>
      <input required type="number" min="0" max="<?php echo $this->upload_limit; ?>" size="4" name="wpci_size" value="<?php echo $this->options['wpci_size']; ?>" /> MB</p>
      <p>Maximum number of files uploaded (0 for unlimited):<br/>
      <input required type="number" min="0" size="4" name="wpci_limit" value="<?php echo $this->options['wpci_limit']; ?>" /></p>
      <p>CSS class for images (Optional, separate by space):<br/>
      <input type="text" class="regular-text" name="wpci_class" value="<?php echo htmlentities($this->options['wpci_class']); ?>" /></p>
      <p>Add upload button (Optional):<br/>
      <label><input type="radio" name="wpci_input" value="1" <?php if ($this->options['wpci_input'] == '1') { ?> checked="checked"<?php } ?>/> Automatic, may not work depending on your theme</label><br/>
      <label><input type="radio" name="wpci_input" value="0" <?php if ($this->options['wpci_input'] == '0') { ?> checked="checked"<?php } ?>/> Manual, add <span style="padding:2px 5px;border:1px solid #aaa;">enctype="multipart/form-data"</span> and <span style="padding:2px 5px;border:1px solid #aaa;">&lt;input type="file" name="image[]" multiple/&gt;</span> to comment form</label></p>
      <p>Text before input button (Optional):<br/>
      <textarea rows="5" cols="50" class="large-text" name="wpci_input_text"><?php echo $this->options['wpci_input_text']; ?></textarea><br/>
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
          $error = false;
          if (isset($_POST[$option]) && $_POST[$option] !== $this->options[$option]) {
            if ($option == 'wpci_dir') {
              if (substr($_POST[$option], -1) !== '/')
                $_POST[$option] .= '/';
              if (!file_exists($_POST[$option])) {
                if (!mkdir($_POST[$option])) {
                  $this->message = 'Error create directory';
                  $error = true;
                }
              }
            } elseif ($option == 'wpci_url') {
              if (substr($_POST[$option], -1) !== '/')
                $_POST[$option] .= '/';
            } elseif ($option == 'wpci_size') {
              $_POST[$option] = round(min($_POST[$option], $this->upload_limit), 2);
            }
            if (!$error) {
              update_option($option, $_POST[$option]);
              $this->options[$option] = $_POST[$option];
            }
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
        $comment_content = preg_replace('/<br\/><a href="[^"]+'.$file.'" title="[^"]*"[^>]*><img src="[^"]+'.$file.'(-t.jpg)?" alt="[^"]*"[^>]*><\/a><br\/>/', '', $comment_content);
      }
    }

    return $comment_content;
  }

  function wpci_get_image($comment_id) {
    if (isset($_POST['wpci_drop_file']) && !empty($_POST['wpci_drop_file'])) {
      $files = array();
      foreach ($_POST['wpci_drop_file'] as $id => $data) {
        $rand_string = substr("abcdefghijklmnopqrstuvwxyz", mt_rand(0, 50), 1).substr(md5(time()), 1);
        $tmp_file = tempnam(sys_get_temp_dir(), $rand_string);
        file_put_contents($tmp_file, base64_decode((strpos($data, ',') !== false?substr($data, strpos($data, ',')+1):$data)));
        $files['tmp_name'][$id] = $tmp_file;
        if (isset($_POST['wpci_drop_filename'][$id]))
          $files['name'][$id] = $_POST['wpci_drop_filename'][$id];
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
          $file_name = $comment_id.'-'.($i+$j).'-'.time().'.'.$ext;
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
    if (!is_admin()) {
      add_action('wp_footer', array(&$this, 'wpci_footer'), 5);
    } else {
      add_action('admin_footer', array(&$this, 'wpci_footer'), 5);
    }

    return '
<div id="wpci-input">
<p id="wpci-input-wrap">
<span id="wpci-button-wrap">
<span id="wpci-clear" onclick="wpciClear()">Clear</span>
<span id="wpci-button">Upload image</span>
<span id="wpci-file-wrap">
<input type="file"'.((!is_admin() && $this->options['wpci_limit'] !== '1')?' multiple':'').' id="wpci-file" accept="image/jpeg,image/png,image/gif" name="image[]"
 onchange="
   if (!window.File || !window.FileList || !window.FileReader) {
     document.getElementById(\''.(!is_admin()?'wpci-input\').parentNode':'post\')').'.enctype=\'multipart/form-data\';
     wpciAddClass(\'wpci-clear\', \'wpci-show\');
     var files=this.files, file=\'\';
     for (var i=0;i<files.length-1;i++) {
      file += files[i].name + \', \';
     }
     file += files[i].name;
     document.getElementById(\'wpci-list\').innerHTML=file;
     wpciAddClass(\'wpci-list\', \'wpci-show-nf\');
   }
 "
></span>
</span>
<span id="wpci-drop-text">or drop files here</span>
</p>
<noscript><p>Javascript is required for advanced features</p></noscript>
<div id="wpci-list"></div>
<div id="wpci-list-hidden" style="display:none;"></div>
</div>
<p id="wpci-error">* Some files will not be uploaded. Only png, gif, jpg are allowed'.(!is_admin()?' and '.(!$this->options['wpci_limit']?'':'maximum '.$this->options['wpci_limit'].' images, ').(!$this->options['wpci_size']?'':'each file less than '.$this->options['wpci_size'].'M'):'').'.</p>
<p id="wpci-text">'.(!is_admin() && !empty($this->options['wpci_input_text'])?str_replace(array('[wpci_limit]', '[wpci_size]'), array($this->options['wpci_limit'], $this->options['wpci_size']), $this->options['wpci_input_text']):'').(is_admin()?'You may add png/gif/jpg images':'').'</p>
  ';
  }

  function wpci_head() {
?>
<style>
#wpci-input{padding:0;width:90%;max-width:650px;margin:0;}
#wpci-input.wpci-drag{position:static;padding:10px;margin:15px 0;border:1px solid #666;box-shadow:0 0 2px #000;min-height:120px;}
#wpci-input-wrap{padding:0 !important;margin:1em 0 !important;}
#wpci-button-wrap{padding:0;margin:0;background-color:#e3e3e3;border:none;display:inline-block;position:relative;}
#wpci-clear{height:1.3em;line-height:1.3em;padding:5px 20px;margin:0;background-color:transparent;border-width:0 1px 0 0;border-style:solid;border-color:#bbb;color:#4c4c4c;text-align:center;display:none;cursor:pointer;}
#wpci-clear.wpci-show{display:inline-block;}
#wpci-button{width:160px;color:#4c4c4c;height:1.3em;line-height:1.3em;padding:5px 0;margin:0 auto;text-align:center;border:none;background-color:transparent;display:inline-block;}
#wpci-file-wrap{position:absolute;opacity:0;top:0;right:0;width:160px;height:100%;border:none;margin:0;padding:0;z-index:2;}
#wpci-file{width:100%;height:100%;border:none;margin:0;padding:0;}
#wpci-drop-text{display:none;padding:5px;margin:0;}
#wpci-drop-text.wpci-show{display:inline-block;}
#wpci-list{display:none;width:98%;max-width:620px;padding:10px 0;margin:0 0 1em;border:1px dashed #000;}
#wpci-list.wpci-show{display:block;}
#wpci-list.wpci-show-nf{display:block;padding:0 10px;line-height:1.8em;}
#wpci-input.wpci-drag #wpci-list{display:block;border:none;min-height:100px;}
.wpci-list-items{display:inline-block;vertical-align:top;position:relative;border:1px solid #000;margin:10px;padding:0;line-height:0;}
.wpci-list-rm{margin:0px;padding:0px;position:absolute;top:-0.6em;right:-0.7em;z-index:2;font-size:16px;line-height:1em;color:black;cursor:pointer;width:1.2em;height:1.2em;border:1px solid black;border-radius:50%;text-align:center;font-weight:normal;background:#fff;box-shadow:1px 1px 2px #666;}
.wpci-list-images{width:100% !important;max-width:100px !important;margin:0 !important;padding:0 !important;border:none !important;}
#wpci-text{border:none;padding:0 0 1em !important;}
#wpci-error{color:red;display:none;padding:0 0 1em !important;}
#wpci-error.wpci-show{display:block;}
#wpci-image-list label{display:inline-block;vertical-align:top;position:relative;border:1px solid #000;margin:0 15px 15px 0;padding:0;line-height:0;}
#wpci-image-list input{margin:0px;position:absolute;top:3px;left:3px;z-index:2;}
#wpci-image-list img{max-width:100px;margin:0;padding:0;}
</style>
<?php
  }

  function wpci_footer() {
?>
<script>
  if (window.File && window.FileList && window.FileReader) {
    var out = true;
    var timeout = -1;
    var c = 0;
    wpciDnd();
    wpciAddClass("wpci-drop-text", "wpci-show");
  }
  function wpciDnd() {
    document.getElementById("wpci-file-wrap").innerHTML=document.getElementById("wpci-file-wrap").innerHTML;
    document.getElementById("wpci-file").addEventListener("change", wpciFileSelectHandler, false);
    document.getElementById("wpci-input").addEventListener("drop", wpciFileSelectHandler, false);
    var filedrop = document.getElementsByTagName("body")[0];
    filedrop.addEventListener("dragover", wpciFileDragHover, false);
    filedrop.addEventListener("dragleave", wpciFileDragHover, false);
    filedrop.addEventListener("drop", wpciCancelDrag, false);
  }
  function wpciAddClass(id, cls) {
    var elem = document.getElementById(id);
    elem.classList.add(cls);
  }
  function wpciRemoveClass(id, cls) {
    var elem = document.getElementById(id);
    elem.classList.remove(cls);
  }
  function wpciCancelDrag(e) {
    e.stopPropagation();
    e.preventDefault();
    wpciRemoveClass("wpci-input", "wpci-drag");
  }
  function wpciFileDragHover(e) {
    e.stopPropagation();
    e.preventDefault();
    if (e.type == "dragover") {
      out = false;
      wpciAddClass("wpci-input", "wpci-drag");
    } else if (e.type == "dragleave") {
      out = true;
      clearTimeout(timeout);
      timeout = setTimeout(function() {
        if (out) {
          wpciRemoveClass("wpci-input", "wpci-drag");
        }
      }, 100);
    }
  }
  function wpciFileSelectHandler(e) {
    wpciFileDragHover(e);
    var files = e.target.files || e.dataTransfer.files;
    if (files.length) {
      for (var i = 0; i < files.length; i++) {
        f = files[i];
        var reader = new FileReader();
        reader.onload = wpciParseFile(f, files.length);
        reader.readAsDataURL(f);
      }
    }
  }
  function wpciParseFile(file, fl) {
    return function(e) {
      var l = document.getElementById("wpci-list");
      if (<?php echo (!is_admin() && $this->options['wpci_limit']?'l.children.length < '.$this->options['wpci_limit'].' && ':'');?>(file.type.indexOf("image/png") == 0 || file.type.indexOf("image/jpeg") == 0 || file.type.indexOf("image/gif") == 0)<?php echo (!is_admin() && $this->options['wpci_size']?' && file.size / 1024 / 1024 < '.$this->options['wpci_size']:''); ?>) {
        var m = e.target.result;
        var d = m.match(/,(.*)$/)[1];
        if (l.innerHTML.indexOf(d) == -1) {
          var j = l.children.length;
          var h = document.getElementById("wpci-list-hidden");
          h.appendChild(document.createTextNode(file.name));
          var fn = h.innerHTML.replace(/\"/g, "&quot;").replace(/\'/g, "&#39;");
          h.innerHTML = "";
          l.innerHTML += "<div class=\"wpci-list-items\" id=\"wpci-list-item-" + j + "\"><input name=\"wpci_drop_filename[]\" style=\"display:none;\" value=\"" + fn + "\"><textarea name=\"wpci_drop_file[]\" style=\"display:none;\">" + d + "</textarea><span class=\"wpci-list-rm\" title=\"Remove\" onclick=\"wpciDelete(" + j + ");\">&#10007;</span><span id=\"wpci-list-image-" + j + "\"><canvas id=\"wpci-list-canvas-" + j + "\" width=\"100\" height=\"20\" style=\"display:none;max-width:100px;\"></canvas></span></div>";
          wpciUpdateHTML();
          wpciUpdateCanvas(j, m, fn);
        }
        m = "";
        d = "";
      } else {
        wpciAddClass("wpci-error", "wpci-show");
      }
      if (++c == fl) {
        setTimeout(function() {
          wpciRemoveClass("wpci-input", "wpci-drag");
        }, 100);
        c = 0;
      }
    }
  }
  function wpciUpdateHTML() {
    var p = document.getElementById("wpci-list").children;
    if (p.length) {
      wpciAddClass("wpci-clear", "wpci-show");
      document.getElementById("wpci-button").innerHTML="Add more...";
      wpciAddClass("wpci-list", "wpci-show");
    } else {
      wpciClear();
    }
  }
  function wpciUpdateCanvas(i, m, fn) {
    var img = new Image();
    img.onload = function(i, m, fn) {
      return function() {
        var c = document.getElementById("wpci-list-canvas-" + i);
        var mw = c.style.maxWidth.match(/[0-9]+/);
        var ctx = c.getContext("2d");
        if (img.width > mw) {
          img.height *= mw / img.width;
          img.width = mw;
        }
        c.width = img.width;
        c.height = img.height;
        ctx.drawImage(img, 0, 0, img.width, img.height);
        m = c.toDataURL();
        document.getElementById("wpci-list-image-" + i).innerHTML = "<img src=\"" + m + "\" class=\"wpci-list-images\" alt=\"" + fn + "\"/>";
      };
    }(i, m, fn);
    img.src = m;
  }
  function wpciClear() {
    wpciRemoveClass("wpci-clear", "wpci-show");
    wpciRemoveClass("wpci-error", "wpci-show");
    document.getElementById("wpci-button").innerHTML="Upload image";
    wpciRemoveClass("wpci-list", "wpci-show");
    document.getElementById("wpci-list").innerHTML = "";
    if (window.File && window.FileList && window.FileReader) {
      wpciDnd();
    } else {
      document.getElementById("wpci-file-wrap").innerHTML=document.getElementById("wpci-file-wrap").innerHTML;
    }
  }
  function wpciDelete(i) {
    document.getElementById("wpci-list").removeChild(document.getElementById("wpci-list-item-" + i));
    wpciUpdateHTML();
  }
  function wpciSelectAll(v) {
    var elem = document.getElementById("wpci-image-list").children;
    for (var i = 0; i < elem.length; i++) {
      elem[i].children[0].checked = v;
    }
  }
</script>
<?php
  }

  function wpci_comment_form($form = '') {
    if (!empty($form) && $this->options['wpci_input']) {
      $form .= $this->wpci_upload_input();
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
      echo '<p><input type="checkbox" name="wpci_selectall" onclick="if (this.checked) {wpciSelectAll(1);} else {wpciSelectAll(0);}"> Select to delete image:</p><div id="wpci-image-list">';
      foreach ($files as $file) {
        $file = substr($file, strrpos($file, '/')+1);
        echo '<label><input type="checkbox" name="wpci_del['.$file.']" value="1" /><img src="'.$this->options['wpci_url'].$file.(file_exists($this->options['wpci_dir'].$file.'-t.jpg')?'-t.jpg':'').'" alt="'.$file.'" /></label>';
      }
      echo '</div>';
    } else
      echo 'No image';
    echo $this->wpci_upload_input();
    return true;
  }

}
endif;

$new_wp_comment_image = new wp_comment_image;
?>
