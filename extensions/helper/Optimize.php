<?php
namespace li3_assets\extensions\helper;
use \lithium\core\Libraries;
use \lithium\net\http\Media;

class Optimize extends \lithium\template\Helper {
    
    public function scripts() {
        $library = Libraries::get('li3_assets');
        // Set Defaults
        $library += array('config' => array());
        $library['config'] += array('js' => array());
        
        $library['config']['js'] += array(
                                        'compression' => 'jsmin',
                                        'output_directory' => 'optimized',
                                        'packer_encoding' => 'Normal',
                                        'packer_fast_decode' => true,
                                        'packer_special_chars' => false                                        
                                    );
        
        // Ensure output directory is formatted properly, first remove any beginning slashes
        if($library['config']['js']['output_directory'][0] == DIRECTORY_SEPARATOR) {
            $library['config']['js']['output_directory'] = substr($library['config']['js']['output_directory'], 1);
        }
        // ...then any trailing slashes
        if(substr($library['config']['js']['output_directory'], -1, 1) == DIRECTORY_SEPARATOR) {
            $library['config']['js']['output_directory'] = substr($library['config']['js']['output_directory'], 0, -1);
        }
        
        // Set the output path
        $webroot = Media::webroot(true);
        $output_hash = md5(serialize($this->_context->scripts));
        $output_file = Media::asset($library['config']['js']['output_directory'] . DIRECTORY_SEPARATOR . $output_hash, 'js');
        $output_folder = $webroot . strstr($output_file, $output_hash, true);
            
        // If the output directory doesn't exist, return the scripts like normal... TODO: also ensure permissions to write here?
        if(!file_exists($output_folder)) {
            // If it doesn't exist, try to create it
            if (!mkdir($output_folder, 0777, true)) {
                die('Failed to create folders...');
            }
            // If it still doesn't exist, return the scripts
            if(!file_exists($output_folder)) {
                return $this->_context->scripts();
            }
        }
        
        if(!empty($library['config']['js']['compression'])) {
            if(!file_exists($webroot . $output_file)) {
                $js = '';
                // JSMin
                if(($library['config']['js']['compression'] === true) || ($library['config']['js']['compression'] == 'jsmin')) {
                    foreach($this->_context->scripts as $file) {
                        if(preg_match('/js\/(.*)"/', $file, $matches)) {
                            $script = $webroot . Media::asset($matches[1], 'js');
                            // It is possible that a reference to a file that does not exist was passed
                            if(file_exists($script)) {
                                $js .= \li3_assets\jsminphp\JSMin::minify(file_get_contents($script));
                            }
                        }
                    }
                // Dean Edwards Packer
                } elseif($library['config']['js']['compression'] == 'packer') {
                    foreach($this->_context->scripts as $file) {
                        if(preg_match('/\/js\/(.*)"/', $file, $matches)) {
                            $script = $webroot . Media::asset($matches[1], 'js');
                            // It is possible that a reference to a file that does not exist was passed                            
                            if(file_exists($script)) {
                                $script_contents = file_get_contents($script);
                                $packer = new \li3_assets\packer\JavaScriptPacker($script_contents, $library['config']['js']['packer_encoding'], $script, $library['config']['js']['packer_fast_decode'], $script, $library['config']['js']['packer_special_chars']);
                                $js .= $packer->pack();
                            }
                        }
                    }
                }
                file_put_contents($webroot . $output_file, $js);
            }
            // One last safety check to ensure the file is there (reasons why it may not be: primarily, write permissions)
            if(file_exists($webroot . $output_file)) {
                return '<script type="text/javascript" src="' . Media::asset($output_file, 'js') . '"></script>';
            } else {
                return $this->_context_scripts();
            }
        } else {
            return $this->_context->scripts();
        }
        
    }
    
    public function styles() {
        $library = Libraries::get('li3_assets');
        // Set Defaults
        $library += array('config' => array());
        $library['config'] += array('css' => array());
        
        $library['config']['css'] += array(
                                       'compression' => true, // possible values: "tidy", true, false
                                       'tidy_template' => 'highest_compression', // possible values: "high_compression", "highest_compression", "low_compression", or "default"
                                       'less_debug' => false, // sends lessphp error message to a log file, possible values: true, false
                                       'output_directory' => 'optimized' // directory is from webroot/css if full path is not defined
                                    );
        
        // Ensure output directory is formatted properly, first remove any beginning slashes
        if($library['config']['css']['output_directory'][0] == DIRECTORY_SEPARATOR) {
            $library['config']['css']['output_directory'] = substr($library['config']['js']['output_directory'], 1);
        }
        // ...then any trailing slashes
        if(substr($library['config']['css']['output_directory'], -1, 1) == DIRECTORY_SEPARATOR) {
            $library['config']['css']['output_directory'] = substr($library['config']['js']['output_directory'], 0, -1);
        }
        
        // Set the output path
        $webroot = Media::webroot(true);
        $output_hash = md5(serialize($this->_context->styles));
        $output_file = Media::asset($library['config']['css']['output_directory'] . DIRECTORY_SEPARATOR . $output_hash, 'css');
        $output_folder = $webroot . strstr($output_file, $output_hash, true);
            
        // If the output directory doesn't exist, return the scripts like normal...
        if(!file_exists($output_folder)) {
            // If it doesn't exist, try to create it
            if (!mkdir($output_folder, 0777, true)) {
                die('Failed to create folders...');
            }
            // If it still doesn't exist, return the scripts
            if(!file_exists($output_folder)) {
                return $this->_context->styles();
            }
        }
        
        // Run any referenced .less files through lessphp first
        foreach($this->_context->styles as $file) {
            if(preg_match('/\/css\/(.*.less).css"/', $file, $matches)) {
                $sheet = $webroot . substr(Media::asset($matches[1], 'css'), 0, -4);
                try {
                    $less = new \li3_assets\lessphp\lessc();
                    // fortunately, the Html::script() helper will automatically append .css, so the output file can just have .css appended too and match.                    
                    $less::ccompile($sheet, $sheet . '.css');
                } catch (\exception $ex) {
                    if($library['config']['css']['less_debug'] === true) {
                        $fp = fopen($output_folder . DIRECTORY_SEPARATOR .'less_errors', 'a');
                        fwrite($fp, '[' . date("D M j G:i:s Y") . '] [file ' . $file . '] ' . $ex->getMessage() . "\n");
                        fclose($fp);
                    }
                }
            }
        }
        
        // Check compression type and compress/combine
        if(!empty($library['config']['css']['compression'])) {
            $css = '';
            if(!file_exists($webroot . $output_file)) {
                // true is just basic compression and combination. Basically remove white spaces and line breaks where possible.
                if($library['config']['css']['compression'] === true) {
                    foreach($this->_context->styles as $file) {
                        if(preg_match('/\/css\/(.*)"/', $file, $matches)) {
                            $sheet = $webroot . Media::asset($matches[1], 'css');
                            // It is possible that a reference to a file that does not exist was passed
                            if(file_exists($sheet)) {
                                $contents = file_get_contents($sheet);
                            } else {
                                $contents = '';
                            }
                            // remove comments
                            $contents = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $contents);
                            // remove tabs, spaces, newlines, etc.
                            $contents = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $contents);
                            // remove single spaces next to braces (can't remove single spaces everywhere, but we can in a few places)
                            $contents = str_replace(array('{ ', ' {', '; }'), array('{', '{', ';}'), $contents);
                            $css .= $contents;
                        }
                    }
                // 'tidy' setting will run the css files through csstidy which not only removes white spaces and line breaks, but also shortens things like #000000 to #000, etc. where possible.
                } elseif($library['config']['css']['compression'] == 'tidy') {
                    $tidy = new \li3_assets\csstidy\CssTidy();
                    $tidy->set_cfg('remove_last_;',TRUE);
                    $tidy->load_template($library['config']['css']['tidy_template']);
                    // Loop through all the css files, run them through tidy, and combine into one css file
                    foreach($this->_context->styles as $file) {
                        if(preg_match('/\/css\/(.*)"/', $file, $matches)) {
                           $sheet = $webroot . Media::asset($matches[1], 'css');
                            // It is possible that a reference to a file that does not exist was passed
                            if(file_exists($sheet)) {
                                $tidy->parse(file_get_contents($sheet));
                                $css .= $tidy->print->plain();
                            }
                        }
                    }
                    
                }
                file_put_contents($webroot . $output_file, $css);
            }
            // One last safety check to ensure the file is there (reasons why it may not be: primarily, write permissions)
            if(file_exists($webroot . $output_file)) {
                return '<link rel="stylesheet" type="text/css" href="' . Media::asset($output_file, 'css') . '" />';
            } else {
                return $this->_context->styles();
            }
        } else {
            // If compression wasn't set, just return the style sheets like normal
            return $this->_context->styles();
        }
        
    }
    
    /*
     * Call this method at the top of a view template to apply a filter to return all images called with the Html::image() helper
     * in that template as base64 data URIs. Note: IE6 & 7 do not support data URIs. 
     *
    */ 
    public function images() {
        $this->_context->Html->applyFilter('image', function($self, $params, $chain) {
            $library = Libraries::get('li3_assets');
            // Set defaults
            $library += array('config' => array());
            $library['config'] += array('image' => array());        
            $library['config']['image'] += array(
                                                'compression' => true,
                                                'allowed_formats' => array('jpeg', 'jpg', 'jpe', 'png', 'gif')
                                            );
            
            // If the image is not in the list of allowed formats or compression is false, don't encode it, just display it as normal
            $format = substr($params['path'], strrpos($params['path'], '.') + 1);
            if ((!in_array($format, $library['config']['image']['allowed_formats'])) || ($library['config']['image']['compression'] !== true)) {
		return $chain->next($self, $params, $chain);
	    }
            
            // Encode the image data
            if(substr($params['path'], 0, 4) == 'http') {
                $file = $params['path'];
            } else {
                $file = Media::webroot(true) . Media::asset($params['path'], 'image');
            }
            $data = base64_encode(file_get_contents($file));
            
            // Set the html options that go within the img tag
            $html_options = '';
            foreach($params['options'] as $k => $v) {
                $html_options .= $k . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '" ';
            }
            
            // Return the image URI
            return '<img src="data:image/'.$format.';base64,'.$data.'" ' . $html_options . '/>';            
        });
    }
    
    // TODO: make $this->optimize->script(...) so that scripts can be called inline and minified...
    
}
?>