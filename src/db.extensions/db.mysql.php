<?php


namespace dsda\dbconnector;

/* !=== DB Class === */
class dbMysqlClass {

	private $link = NULL;

	function connect($dbConfig) {
		$driver		= $dbConfig["dbdriver"];
		$dsn		= $driver.":";

		// перечитываем аттрибуты
		foreach ( $dbConfig["dsn"] as $k => $v ) { $dsn .= "${k}=${v};"; }

		// dsn is "mysql:dbhost=localhost;dbport=3306;dbname=ee;charset=utf8;"

		try {
			$this->link = new \PDO ( $dsn, $dbConfig['dbuser'], $dbConfig['dbpassword'], $dbConfig["dboptions"] ) ; // root namespace need
			foreach ( $dbConfig["dbattributes"] as $k => $v ) {
				$this->link -> setAttribute ( constant ( "PDO::{$k}" ), constant ( "PDO::{$v}" ) ) ;
			}

		} catch(PDOException $e) {
			throw new \Exception($e -> getMessage(), 0); // root namespace need 
		}

		return $this->link;

	}

}

?>
