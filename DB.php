<?php
namespace Mpm\Database;
use Mpm\Utils\Utils;

class DB {
  
  /**
   * @var string $username
   */
  private  $username ;
  
  /**
   * @var string $password 
   */
  private $password;
  
  /**
   * @var string  $host 
   */
  private $host ;
  
  /**
   * @var string $port 
   */
  private $port;
  
  /**
   * @var string $dbname 
   */
  private $dbname;
  
  /**
   * @var MysqliConnection $conn 
  
   */
  private $conn; 
  
  /**
   * Database Obj 
   */
   private static $dbObj;
  
  private function __construct($username,$password,$host,$port,$db){
    $this->username = $username;
    $this->password = $password;
    $this->host = $host;
    $this->port = $port;
    $this->dbname = $db;
  }
  
  /** 
   * Instantiates and Returns Singleton Class Object . 
   * 
   * @param string $username 
   * @param string $password 
   * @param string $host 
   * @param string $port 
   * @param string $db 
   */
  public static function  init($username,$password,$host,$port,$db){
    if(isset(self::$dbObj)) return self::$dbObj;
    else self::$dbObj = new static($username,$password,$host,$port,$db);
    return self::$dbObj;
  }
  
  
  public static function connect($database=true){
    try {
      if(!isset(self::$dbObj)) throw new DatabaseObjectException();
      $username = self::$dbObj->username;
      $password = self::$dbObj->password;
      $host     = self::$dbObj->host;
      $port     = self::$dbObj->port;
      $dbname   = self::$dbObj->dbname;
    } catch(DatabaseObjectException $e){
      return null;
    }

    if($database==true) 
      $conn  = mysqli_connect("$host:$port",$username,$password,$dbname);
    else $conn  = mysqli_connect("$host:$port",$username,$password);
    if(!$conn) {
      echo "<h1>Database Could not sync </h1>";
      echo mysqli_error($conn);
    }
    return $conn;
  }
  
  public static function insert($table,array $data) {
    $conn = self::connect();
    $keys = join(',',array_keys($data));
    $values=array();
    foreach(array_values($data) as $value){
      array_push($values,Utils::quote($value));
    }
  
    $values = join(',',array_values($values));
    $sql = "INSERT INTO $table ($keys) values($values)";
    if(!mysqli_query($conn,$sql)){
      echo mysqli_error($conn);
    };
    $insertId =  mysqli_insert_id($conn);
    mysqli_close($conn);
    return $insertId;
  }
  
  public static function read($table,$data=array(),array $filter=array(),$filterOperator='AND',$order_array=array(),$returnType=MYSQLI_ASSOC) {
    $conn = self::connect();
    
    /** Set Restrictions or Filers **/
    $restrictions = ' ';//Where Rules
    $operation = ' ';//Filter operation
    foreach ($filter as $key=>$value){
      $restrictions .= $operation." $key = '$value' ";
      $operation = $filterOperator;
    }
    
    /*** Data to get **/
    $fields = ' ';
    $seperation = ' ';
    foreach ($data as $key=>$value){
      $fields .= $seperation." $value ";
      $seperation = ' , ';
    }
   
    /** Order Output **/
    $order_by_string = ' ';
    $order_by_seperator = ' ';
    foreach ($order_array as $key=>$value){
      $order_by_string .= $order_by_seperator." $key $value ";
      $order_by_seperator = ',';
    }
    
    $where = ($filter!=null && count($filter)>0)?"WHERE":" ";
    $order_by = ($order_array!=null && count($order_array)>0)?"ORDER BY":" ";
    $dataFields = ($data!=null && count($data)>0)?$fields:" * ";
    $sql = "SELECT $dataFields FROM $table $where $restrictions $order_by $order_by_string";
    $result = mysqli_query($conn,$sql);
    if(!$result){
      return false;
    }
    $data =  mysqli_fetch_all($result,$returnType);
    $data = Utils::normalize($data);
    mysqli_close($conn);
    return $data;
  }
  
  
  public static function update($table,array $data,array $filter = array(),$filterOperator = 'AND'){
    $data = self::clean($table,$data);
    $data = array_map("Mpm\Utils\Utils::quote",$data);
    $conn = self::connect();
    $modifications = '';//update Data String
    $seperation = ' ';//Comma 
    $restrictions = ' ';//Where Rules
    $operation = ' ';//Filter operation
    foreach ($data as $key=>$value){
      $modifications .= $seperation."$key = $value ";
      $seperation = ',';
    }
    foreach ($filter as $key=>$value){
      $restrictions .= $operation." $key = '$value' ";
      $operation = $filterOperator;
    }
    $where = ($filter!=null && count($filter)>0)?"WHERE":" ";
    $sql = "UPDATE $table SET $modifications $where $restrictions";
    $response = mysqli_query($conn,$sql);
    mysqli_close($conn);
    echo $sql;
    return $response;
  }
  
  
  public static function delete($table,array $filter=[],$operator='AND') {
    $conn = self::connect();
    $law = '';
    $operation = '';
    foreach ($filter as $key=>$value){
      if(is_array($value)) {
        $value = trim(json_encode($value));
        $value = str_replace("[","(",$value);
        $value = str_replace("]",")",$value);
        $law .= $operation." $key in $value";
      } else {
        $law .= $operation." $key = '$value' ";
      }
      $operation = $operator;
    }
    $where = ($filter!=null && count($filter)>0)?"WHERE":" ";
    $sql = "DELETE FROM $table $where $law";
    $response =  mysqli_query($conn,$sql);
    mysqli_close($conn);
    return $response;
  }
  
  public static function fetch_sql($sql){
    $conn = self::connect();
    $result = mysqli_query($conn,$sql);
    $data =  mysqli_fetch_all($result,MYSQLI_ASSOC);
    mysqli_close($conn);
    return $data;
  }
  
  public static function sql($sql){
    $conn = self::connect();
    $result = mysqli_query($conn,$sql);
    mysqli_close($conn);
    return $result;
  }
  
  public static function column_exists($table,$data){
   $row =  self::read($table,filter:$data);
    return (count($row)>0)?true:false;
  }
  
  public static function table_exists($db,$table) {
    $conn = self::connect(database:false);
    $result = mysqli_query($conn,"SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA='$db' AND TABLE_NAME='$table'");
    $res =  mysqli_num_rows($result)>0?true:false;
    mysqli_close($conn);
    return $res;
  }
  
  /**
   * Reads Multiple Queryies seperated by semicolon (;)  one by one 
   */
  public static function query($sql,$conn) { 
    $conn = isset($conn)?$conn:self::connect();
    $query_array = explode(';',$sql);
    foreach($query_array as $query) {
      $query = trim($query);
      if(empty($query)) continue;
      try {
        echo "\nReading `". substr($query,0,25)." ....)`\n";
        mysqli_query($conn,$query);
        echo "[Done]";
      } catch(\Exception $e) {
        echo "[ERROR] : ".mysqli_error($conn);
      }
      echo "\n\n";
    }
  }
  
  /**
   * Reads sql file and executes its commands line by line , Where every line is considered to be end with a semicolon (;) 
   * 
   * @static
   * @param string $file 
   * @param MysqliObject $conn
   */
  public static function read_from_file($file,$conn){
    $conn = isset($conn)?$conn:self::connect();
    echo "Loading : {$file}\n";
    $query = '';
    $sqlScript = file($file);
    foreach ($sqlScript as $line)	{
      $startWith = substr(trim($line), 0 ,2);
      $endWith = substr(trim($line), -1 ,1);
      if (empty($line) || $startWith == '--' || $startWith == '/*' || $startWith == '//') continue;
  	        
      $query = $query . $line;
      if ($endWith == ';') {
        try{
  	       mysqli_query($conn,$query);
        }catch(\Exception $e){
  	       echo "\n[ERROR]\n Problem in executing the SQL query :\n\t\"" . trim($query) ."\" \n\n";
  	       echo "REASON : ".mysqli_error($conn)."\n\n";
  	       $query= '';		
        }
      }//endswith
    }//foreach reading file line be line
    echo "Loaded : {$file} \n\n";
  }
  
  /**
   * Makes Form values compatible with database table schema 
   * 
   * @param string $table 
   * @param string $data.
   */
  public static function clean($table,$data){
    $conn    = self::connect();
    foreach($data as $name=>&$value){
      $result = DB::sql("SHOW COLUMNS FROM $table where FIELD='$name'");
      $row    = mysqli_fetch_assoc($result);
      if(empty($value) && $row["Null"] === "YES") {
        // change the form value to null
        $data[$name] = null;
      } elseif(empty($value) && isset($row["Default"])){
        $data[$name] = $row["Default"];
      }
      else {
        // sanitize and validate the form value
        switch ($row["Type"]) {
            case "int":
            case "tinyint":
            case "smallint":
            case "mediumint":
            case "bigint":
            case "float":
            case "double":
            case "decimal":
                $data[$name] = filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                break;
            case "date":
                $data[$name] = date("Y-m-d", strtotime($value));
                break;
            case "datetime":
                $data[$name] = date("Y-m-d H:i:s", strtotime($value));
                break;
            default:
                $data[$name] = filter_var($value, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                break;
        }
      }
    }
    unset($value);
    return $data;
  }
}
