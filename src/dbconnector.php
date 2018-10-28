<?php

namespace dsda\dbconnector;

class dbconnector {

	private $link = NULL;

	private $callsCount=0;
	private $callsDebug=Array();
	private $querysLog;
	private $cache = array(); // local cache

	function __construct($config) {
		
		$this->config = $config;

		$dbtype = $this->config->get('dbtype');
		if (!in_array($dbtype, array('mysql','sqlite'))) {
			throw new \Exception('DB Type in config has error!', 0);  // root namespace need  
		};

		switch($dbtype) {
			case 'mysql':
				include(dirname(__FILE__).'/db.extensions/db.mysql.php');		// MySQL extends
				$this->connectInstance = new dbMysqlClass();
				break;
			case 'sqlite':
				include(dirname(__FILE__).'/db.extensions/db.sqlite.php');	// SQLite extends
				
				$this->checkSqliteFolder();
				
				$this->connectInstance = new dbSqliteClass();
				break;
			default:
				throw new Exception('DB Type in config has error!', 0);
		}
		$this->link = $this->connectInstance->connect($this->config->get('dbconfig'));

	}


	function __destruct() {
		$this->link = NULL;
	}

	function checkSqliteFolder(){
		$dbconfig = $this->config->get('dbconfig');
		$dbconfig['dbpath'] = dirname(__FILE__).'/'.$dbconfig['dbpath'];
		if (!file_exists(dirname(__FILE__).'/data')) mkdir(dirname(__FILE__).'/data');
		$this->config->set('dbconfig', $dbconfig);
	}


	function uncommentSQL($sql) {
		$sqlComments = '@(([\'"]).*?[^\\\]\2)|((?:\#|--).*?$|/\*(?:[^/*]|/(?!\*)|\*(?!/)|(?R))*\*\/)\s*|(?<=;)\s+@ms';
		/* Commented version
		$sqlComments = '@
		    (([\'"]).*?[^\\\]\2) # $1 : Skip single & double quoted expressions
		    |(                   # $3 : Match comments
		        (?:\#|--).*?$    # - Single line comments
		        |                # - Multi line (nested) comments
		         /\*             #   . comment open marker
		            (?: [^/*]    #   . non comment-marker characters
		                |/(?!\*) #   . ! not a comment open
		                |\*(?!/) #   . ! not a comment close
		                |(?R)    #   . recursive case
		            )*           #   . repeat eventually
		        \*\/             #   . comment close marker
		    )\s*                 # Trim after comments
		    |(?<=;)\s+           # Trim after semi-colon
		    @msx';
		*/
		$uncommentedSQL = trim( preg_replace( $sqlComments, '$1', $sql ) );
		preg_match_all( $sqlComments, $sql, $comments );
		$extractedComments = array_filter( $comments[ 3 ] );
		//var_dump( $uncommentedSQL, $extractedComments );
		return $uncommentedSQL;
	}

	function parseQuery($q) {
		$q = $this->uncommentSQL($q);
		$q = str_replace("\n", " ", $q);
		$q = str_replace("\r", " ", $q);
		$q = str_replace("\t", " ", $q);
		$q = preg_replace("/\/\*.*\*\//Uis",'',$q);
		$q = preg_replace("/\s+/is",' ',$q);
		$q = trim($q);
		$type = explode(" ",$q);
		$type = trim(mb_strtoupper($type[0],"UTF-8"));
		return $type;

	}

	function query($query,$cache=false,$asArray=false) {

		if ($this->config->get('debug')) {
			$this->querysLog[] = $query;
		}

		$type = $this->parseQuery($query);
		$pureQuery = $this->uncommentSQL($query);


		if ($this->config->get('debug')==true) { $this->callsDebug[]=array("hash"=>md5($pureQuery),'query'=>str_replace("\t","",$query)); }

		if (isset($this->cache[md5($pureQuery)]) && in_array($type,array('SELECT', 'SHOW'))) {
			return $this->cache[md5($pureQuery)];
		}

		if ($this->link==NULL) {
			throw new \Exception('No DB link, connect first! ('.$query.')', 0);  // root namespace need
		}

		try {
			$result=$this->link->query($query);
		} catch(PDOException $e) {
			throw new \Exception($e -> getMessage()."\n".$query, 0);  // root namespace need 
		}
		if (in_array($type,array('SELECT', 'SHOW'))) {
			if ($asArray==true) {
				$result->setFetchMode(\PDO::FETCH_ASSOC);	  // root namespace need
			} else {
				$result->setFetchMode(\PDO::FETCH_OBJ);	  // root namespace 
			}
			//TODO: if request have INTO OUTFILE then $result->fetch() catch ecxeption becouse result is empty
			while($row = $result->fetch()) {
				$res[]=$row;
			}
			if (isset($res) && ($res==NULL || $res==false || !isset($res[0]) || $res[0]==false)) $res = false;
		} elseif(in_array($type,array('INSERT'))) {
			$res=$this->link->lastInsertId();
		}

		$this->callsCount++;
		if ($this->config->get('debug')==true) { $this->callsDebug[]=$query; }
		
		if ($cache==true) $this->cache[md5($pureQuery)] = (isset($res[0])) ? $res : false;
		return (isset($res)) ? $res : false;
	}

	// insert binary data
	function queryInsertBinary($query, $binarray) {
		$pdoLink = $this->link;
		$stmt = $pdoLink->prepare($query);
		foreach($binarray as $key=>$value) {
			/*
			$db->queryInsertBinary(
					"INSERT INTO tbl VALUES(NULL, :SOME_ID, :BINARY_DATA);",
					array(
						'SOME_ID'		=> array('data'=>123,'param'=>array(PDO::PARAM_STR,sizeof('123'))),
						'BINARY_DATA'	=> array('data'=>$binary_data,'param'=>array(PDO::PARAM_LOB,sizeof($binary_data))),
					)
			);
			*/
			$stmt->bindParam(":".$key, $value['data'], $value['param'][0], $value['param'][1]); //PDO::PARAM_STR || PDO::PARAM_LOB, sizeof($binary)
		}
		$stmt->execute();
		return $pdoLink->lastInsertId();
	}

	function tableExist($tblName) {
		if ($this->config->get('dbtype')=='sqlite') {
			$query = "SELECT name FROM sqlite_master WHERE type='table' AND name='".$tblName."';";
		} elseif($this->config->get('dbconfig')=='mysql') {
			$dbconfig = $this->config->get('dbconfig');
			$query = "SELECT * FROM information_schema.tables WHERE table_schema = '".$dbconfig['dbname']."' AND table_name = '".$tblName."' LIMIT 1;";
		}
		$result = $this->query($query);
		if ($result!==false && $result!==NULL) {
			$res = true;
		} else {
			$res = false;
		}
		return $res;
	}

	function get_value($var) {
		return $this->$var;
	}

	function analizeUnCache(){
		$uncached = array();
		foreach($this->callsDebug as $cdk=>$cdv) {
			if (!isset($this->cache[$cdk]['hash'])) {
				if (isset($uncached[$cdk]['hash'])) $uncached[$cdk]['hash']++; else $uncached[$cdk]['hash']=1;
			}
		}
		return $uncached;
	}

	function analizeAll(){
		$queryAnalizer=array();
		foreach($this->callsDebug as $k=>$v) {
			if (isset($queryAnalizer[$k]['hash'])) {
				$queryAnalizer[$k]['hash']++;
			} else {
				$queryAnalizer[$k]['hash'] = 1;
			}
		}
		return $queryAnalizer;
	}

	function showLog(){
		$q = array();
		foreach($this->querysLog as $query) {
			$q[] = str_replace("\t\t","\t",$query);
		}
		debug($q);
	}


}


?>
