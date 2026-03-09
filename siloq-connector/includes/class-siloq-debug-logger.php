<?php
if ( ! defined( "ABSPATH" ) ) exit;
class Siloq_Debug_Logger {
    private static $instance = null;
    private $log_file;
    private $max_lines = 500;
    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $siloq_dir = $upload_dir["basedir"] . "/siloq";
        $this->log_file = $siloq_dir . "/siloq_debug.log";
        $this->maybe_create_dir( $siloq_dir );
    }
    private function maybe_create_dir( $dir ) {
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
            file_put_contents( $dir . "/.htaccess", "deny from all" );
            file_put_contents( $dir . "/index.php", "<?php // silence" );
        }
    }
    public function log( $message ) {
        if ( ! get_option( "siloq_debug_mode" ) ) return;
        $line = "[" . date( "Y-m-d H:i:s" ) . "] " . $message . PHP_EOL;
        file_put_contents( $this->log_file, $line, FILE_APPEND | LOCK_EX );
        $this->trim_log();
    }
    private function trim_log() {
        if ( ! file_exists( $this->log_file ) ) return;
        $lines = file( $this->log_file );
        if ( count( $lines ) > $this->max_lines ) {
            file_put_contents( $this->log_file, implode( "", array_slice( $lines, -$this->max_lines ) ) );
        }
    }
    public function get_last_lines( $count = 50 ) {
        if ( ! file_exists( $this->log_file ) ) return [];
        $lines = file( $this->log_file );
        return array_slice( $lines, -$count );
    }
    public function clear() {
        if ( file_exists( $this->log_file ) ) file_put_contents( $this->log_file, "" );
    }
    public function get_file_path() { return $this->log_file; }
}
function siloq_debug_log( $message ) {
    Siloq_Debug_Logger::get_instance()->log( $message );
}
