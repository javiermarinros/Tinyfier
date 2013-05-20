<?php

/**
 * Rutinas de compresión y procesado de código CSS
 *
 * @package Tinyfier
 */
abstract class TinyfierCSS {

    /**
     * Process a CSS file
     * @param string $file
     * @param array $settings
     * @return string
     */
    public static function process_file($file, array $settings = array()) {
        $settings['absolute_path'] = $file;
        return self::process(file_get_contents($file), $settings);
    }

    /**
     * Process CSS code
     *
     * Available settings:
     *   'less': enable/disable LESS parser
     *   'compress': if FALSE, adds line breaks and indentation to its output code to make the code easier for humans to read
     *   'absolute_path': absolute path to the file
     *   'relative_path': relative path from the document root
     *   'cache_path': cache folder
     *   'ie_compatible': boolean value that indicates if the generated css will be compatible with old IE versions
     *   'data': array with the vars passed to the css parser for use in the code
     *
     * @param string $css
     * @param array $settings
     * @return string
     */
    public static function process($css = NULL, array $settings = array()) {
        //Load settings
        $settings = $settings + self::default_settings();

        // 1. Process the file with LESS    
        if ($settings['less']) {
            require_once 'less/tinyfier_less.php';
            $less = new tinyfier_less();
            $css = $less->process($css, $settings);
        }

        // 2. Optimize, add vendor prefix and remove hacks        
        require_once 'css_optimizer.php';
        $optimizer = new css_optimizer(array(
            'compress' => $settings['compress'],
            'optimize' => $settings['optimize'],
            'extra_optimize' => $settings['extra_optimize'],
            'remove_ie_hacks' => FALSE, //$settings['ie_compatible'] == FALSE,
            'prefix' => $settings['prefix'],
        ));
        $css = $optimizer->process($css);


        if ($settings['compress']) { //Remove trailing semicolons
            $css = str_replace(';}', '}', $css);
        }

        return $css;
    }

    public static function default_settings() {
        return array(
            'less' => TRUE,
            'absolute_path' => '',
            'relative_path' => '',
            'cache_path' => '',
            'compress' => TRUE,
            'optimize' => TRUE,
            'extra_optimize' => FALSE,
            'optimize_images' => TRUE,
            'ie_compatible' => FALSE,
            'data' => NULL,
            'prefix' => array(
                'webkit' => TRUE,
                'mozilla' => TRUE,
                'opera' => TRUE,
                'microsoft' => TRUE,
            ),
        );
    }

}
