<?php
if (!defined('F__IS_INDEX')) throw new Exception('Sorry, wrong include, try hack another way!');

class fsf_db extends fsf_base{

	private $link = NULL;

	private $callsCount=0;
	private $callsDebug=Array();
	private $querysLog;
	private $cache = array(); // local cache

	// конструктор
	function __construct() {
		
		$extract = extract($GLOBALS['fsf_classes'], EXTR_REFS);
		
		$dbtype = $fsf_config->get('dbtype');
		if (!in_array($dbtype, array('mysql','sqlite'))) {
			throw new Exception('DB Type in config has error!', 0);  // root namespace need because we in SPCC namespace
		};
		$fsf_config->get('dbconfig');


		switch($dbtype) {
			case 'mysql':
				include($fsf_config->get('path').'/gears/db.extensions/db.mysql.php');		// SQLite extends
				$this->connectInstance = new dbMysqlClass();
				break;
			case 'sqlite':
				include($fsf_config->get('path').'/gears/db.extensions/db.sqlite.php');	// MySQL extends
				$this->connectInstance = new dbSqliteClass();
				break;
			default:
				throw new Exception('DB Type in config has error!', 0);
		}
		$this->link = $this->connectInstance->connect($fsf_config->get('dbconfig'));

	}


	function __destruct() {
		$this->link = NULL;
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

	// простой запрос к базе
	function query($query,$cache=false,$asArray=false) {

		extract($GLOBALS['fsf_classes'], EXTR_REFS);
		
		if ($fsf_config->get('debug')) {
			$this->querysLog[] = $query;
		}

		// разбираем запрос
		$type = $this->parseQuery($query);
		$pureQuery = $this->uncommentSQL($query);


		if ($fsf_config->get('debug')==true) { $this->callsDebug[]=array("hash"=>md5($pureQuery),'query'=>str_replace("\t","",$query)); }

		// кеширование
		if (isset($this->cache[md5($pureQuery)]) && in_array($type,array('SELECT', 'SHOW'))) {
			return $this->cache[md5($pureQuery)];
		}

		if ($this->link==NULL) {
			throw new Exception('No DB link, connect first! ('.$query.')', 0);  // root namespace need because we in SPCC namespace
		}

		// выполняем запрос
		try {

			$result=$this->link->query($query);

			// получаем результаты
			if (in_array($type,array('SELECT', 'SHOW'))) {
				if ($asArray==true) {
					$result->setFetchMode(\PDO::FETCH_ASSOC);	  // root namespace need because we in SPCC namespace
				} else {
					$result->setFetchMode(\PDO::FETCH_OBJ);	  // root namespace need because we in SPCC namespace
				}
				//TODO: если в запросе есть INTO OUTFILE то $result->fetch() бросает ошибку т.к. нечего возвращать надо научиться ловить пустой результат
				while($row = $result->fetch()) {
					$res[]=$row;
				}
				if (isset($res) && ($res==NULL || $res==false || !isset($res[0]) || $res[0]==false)) $res = false;
			} elseif(in_array($type,array('INSERT'))) {
				$res=$this->link->lastInsertId();
			}

			// увеличиваем счетчик запросов
			$this->callsCount++;
			// если дебаг включен то добавляем запрос в лог
			if ($fsf_config->get('debug')==true) { $this->callsDebug[]=$query; }
		} catch(PDOException $e) {
			throw new Exception($e -> getMessage()."\n".$query, 0);  // root namespace need because we in SPCC namespace
		}
		// кеширование
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
