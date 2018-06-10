<?php


class WHTP_Installer{
	private $hits_table;
	private $hitinfo_table;
	private $user_agents_table;
	private $ip_hits_table;
	private $visiting_countries_table;
	private $ip_to_loacation_table;
	private $ip_hits_table;

	public function __construct(){
		global $wpdb;

		self::$hits_table 				= $wpdb->prefix . 'whtp_hits';
		self::$hitinfo_table 			= $wpdb->prefix . 'whtp_hitinfo';
		self::$user_agents_table 		= $wpdb->prefix . 'whtp_user_agents';
		self::$ip_hits_table 			= $wpdb->prefix . 'whtp_ip_hits';
		self::$visiting_countries_table = $wpdb->prefix . 'whtp_visiting_countries';
		self::$ip2loacation_table 		= $wpdb->prefix . 'whtp_ip2location';
		
		self::upgrade_db();

		if ( ! self::is_installed() ) {	
			update_option( 'whtp_version', WHTP_VERSION );
			update_option( 'whtp_installed', "yes");
			update_option( 'whtp_vc_updated', "yes");
		}

		self::create();
	}

	public static function is_installed(){
		$installed = get_option('whtp_installed');
		if ( $installed == "yes" ) return true;
		else return false;
	}

	public static function create(){
		self::create_whtp_hits_table();
		self::create_whtp_hitinfo_table();
		self::create_whtp_visiting_countries();
		self::create_whtp_user_agents();
		self::create_ip_2_location_country();
		self::create_whtp_ip_hits_table();
	}

	public static function upgrade_db(){
		self::check_rename_tables();
		self::update_old_user_agents();
		WHTP_Functions::update_visiting_countries();
	}

	public static function check_rename_tables(){
		if ( self::table_exists("hits") && ! self::table_exists( self::$hits_table ) ){
			self::rename_table("hits", self::$hits_table);
		}

		if ( self::table_exists( "hitinfo" ) && ! self::table_exists( self::$hitinfo_table ) ) { 
			self::rename_table("hitinfo", self::$hitinfo_table );
		}

		if ( self::table_exists( 'whtp_hits' ) && ! self::table_exists( self::$hits_table  ) ) { 
			self::rename_table( 'whtp_hits' , self::$hits_table );
		}

		if ( self::table_exists( 'whtp_hitinfo' ) && ! self::table_exists( self::$hitinfo_table  ) ) { 
			self::rename_table( 'whtp_hitinfo', self::$hitinfo_table );
		}

		if ( self::table_exists( 'whtp_user_agents' ) && ! self::table_exists(self::$user_agents_table  ) ) { 
			self::rename_table( 'whtp_user_agents', self::$user_agents_table );
		}

		if ( self::table_exists( 'whtp_ip_hits' ) && ! self::table_exists( self::$ip_hits_table ) ) { 
			self::rename_table( 'whtp_ip_hits' , self::$ip_hits_table );
		}

		if ( self::table_exists( 'whtp_visiting_countries' ) && ! self::table_exists( self::$visiting_countries_table ) ) { 
			self::rename_table( 'whtp_visiting_countries', self::$visiting_countries_table );
		}

		if ( self::table_exists( 'whtp_ip2location' ) && ! self::table_exists( self::$ip2loacation_table  ) ) { 
			self::rename_table( 'whtp_ip2location', self::$ip2loacation_table );
		}
	}

	public static function rename_table( $old_table_name, $new_table_name ){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		if ( dbDelta( "RENAME TABLE `" .  $old_table_name . "` TO `" . $new_table_name ."`;" ) ){
			return true;
		}
		else return false;
	}

	/*
	* Update old user agents into browser names
	*/
	public static function update_old_user_agents(){
		set_time_limit( 0 );
		global $wpdb;
			
		$user_agents = $wpdb->get_results( "SELECT ip_address, user_agent FROM ". self::$hitinfo_table );
		if ( count( $user_agents ) > 0 ){
			foreach ( $user_agents as $uagent ) {
				$ua = whtp_browser_info();
				$browser = $ua['name'];
				$ip = $uagent->ip_address;
				if ( $uagent->user_agent != $browser ){
					$update_browser = $wpdb->update(
						self::$hitinfo_table, array( "user_agent" => $browser ),
						array("ip_address"=>$ip),
						array("%s","%s")
					);
				}
			}
		}
	}

	# create hits table
	public static function create_hits_table(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta(
			"CREATE TABLE IF NOT EXISTS `" . self::$hits_table ."` (
			`page_id` int(10) NOT NULL AUTO_INCREMENT,
			`page` varchar(100) NOT NULL,
			`count` int(15) DEFAULT '0',
			PRIMARY KEY (`page_id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=5;"
		);
	}
	# create hitinfo table
	public static function create_hitinfo_table(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta(
			"CREATE TABLE IF NOT EXISTS `" . self::$hitinfo_table ."` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`ip_address` varchar(30) DEFAULT NULL,
			`ip_status` varchar(10) NOT NULL DEFAULT 'active',
			`ip_total_visits` int(15) DEFAULT '0',
			`user_agent` varchar(50) DEFAULT NULL,
			`datetime_first_visit` varchar(25) DEFAULT NULL,
			`datetime_last_visit` varchar(25) DEFAULT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=7;"
		);
	}

	public static function create_visiting_countries(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta("CREATE TABLE IF NOT EXISTS `" . self::$visiting_countries_table . "` (
			`country_code` char(2) NOT NULL,
			`count` int(11) NOT NULL,
			UNIQUE KEY `country_code` (`country_code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;"
		);
	}


	public static function create_ip_hits_table(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta(
			"CREATE TABLE IF NOT EXISTS `" . self::$ip_hits_table ."` (
			`id` int(10) NOT NULL AUTO_INCREMENT,
			`ip_id` int(11) NOT NULL,
			`page_id` int(10) NOT NULL,
			`datetime_first_visit` datetime NOT NULL,
			`datetime_last_visit` datetime NOT NULL,
			`browser_id` int(11) NOT NULL,
			PRIMARY KEY (`id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;"
		);
	}
	/*
	* Create user agents table
	*/
	public static function create_user_agents(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta("
			CREATE TABLE IF NOT EXISTS `" . self::$user_agents_table. "` (
			`agent_id` int(11) NOT NULL AUTO_INCREMENT,
			`agent_name` varchar(20) NOT NULL,
			`agent_details` text NOT NULL,
			PRIMARY KEY (`agent_id`),
			UNIQUE KEY `agent_name` (`agent_name`)
			) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=60;"
		);
	}

	public static function create_ip_2_location_country(){
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta(
			"CREATE TABLE IF NOT EXISTS `" . self::$ip_to_loacation_table ."` (
			`ip_from` varchar(15) COLLATE utf8_bin DEFAULT NULL,
			`ip_to` varchar(15) COLLATE utf8_bin DEFAULT NULL,
			`decimal_ip_from` int(11) NOT NULL,
			`decimal_ip_to` int(11) NOT NULL,
			`country_code` char(2) COLLATE utf8_bin DEFAULT NULL,
			`country_name` varchar(64) COLLATE utf8_bin DEFAULT NULL,
			KEY `idx_ip_from` (`ip_from`),
			KEY `idx_ip_to` (`ip_to`),
			KEY `idx_ip_from_to` (`ip_from`,`ip_to`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_bin;"
		);
	}

	/*
	* Functions to export the old `hits` and `hitinfo` tables to the new `whtp_hits` and `whtp_hitinfo` tables
	* First run the function `whtp_table_exists()` to check if the table exists, then
	* Start the export if both the source and destination tables exists
	* If the destinatio table doesn't exist, create it and run the export again
	*/

	# check if a table exists in the database
	public static function table_exists ( $tablename ){
		global $wpdb;
		
		if ( $wpdb->get_var("SHOW TABLES LIKE '$tablename'") )
			$table_exists = true;
		else
			$table_exists = false;
			
			
		/**/
		$dbname  = DB_NAME; # wordpress database name
		$tables = $wpdb->get_results("SHOW TABLES FROM $dbname, ARRAY_A");
		foreach ( $tables as $table ){
			$arr[] = $table[0];
		}

		if ( in_array( $tablename, $arr ) ){
			$table_exists = true;
		}
		else{
			$table_exists = false;
		}
		return $table_exists;
	}

	# export hits data to whtp_hits
	public static function export_hits(){
		global $wpdb;
		$wpdb->hide_errors();
		
		$hits = $wpdb->get_results("SELECT * FROM `hits`, ARRAY_A");
		if ( count($hits ) > 0){
			$message = "";
			$exported = false;
			foreach( $hits as $hit ){
				$insert = $wpdb->insert("whtp_hits", array("page"=>$hit['page'],"count"=>$hit['count']), array("%s", "%d"));
				if( !$insert ){
					$exported = false;
				}else{
					$exported = true;
				}
			}		
		}
		if ($exported == true) {
			$wpdb->query( "DROP TABLE IF EXISTS `hits`" );
		}
	}

	#export hitinfo data to whtp_hitinfo table
	public static function export_hitinfo(){
		global $wpdb;
		$wpdb->hide_errors();
		
		$hitsinfo = $wpdb->get_results("SELECT * FROM hitinfo");
		if( count($hitsinfo) > 0){
			$message 	= "";
			$exported	= false;
			foreach( $hitsinfo as $info ){	
				$insert = $wpdb->insert(
					"whtp_hitinfo", 
					array(
						"ip_address"=>$info->ip_address,
						"ip_status"=>'active',
						"user_agent"=>$info->user_agent,
						"datetime_first_visit"=>$info->datetime,
						"datetime_last_visit"=>$info->datetime
					), 
					array("%s","%s","%s","%s","%s") 
				);
				if ( !$insert ){
					$exported = false;
				}
				else{
					$exported = true;
				}
			}
		}
		
		if ($exported == true) {
			$wpdb->query("DROP TABLE IF EXISTS `hitinfo`");
		}
	}
}