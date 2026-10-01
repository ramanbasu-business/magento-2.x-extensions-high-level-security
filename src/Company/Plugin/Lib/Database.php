<?php

/*
 * Company Plugin
 *
 * This file is part of the Company Plugin for Magento.
 *
 * @package Company\\Plugin
 */


namespace Company\Plugin\Lib;

class Database
{
    protected $db;
    protected $jobFactory;
    protected $helper;
    protected $jobTableName;
    protected $resource;
    protected $debugMessage;

    public function __construct(
        \Company\Plugin\Helper\Data $helper,
        \Magento\Framework\App\ResourceConnection $resource,
        \Company\Plugin\Model\JobFactory $jobFactory
    ) {
        $this->resource = $resource;
        $this->db = $this->resource->getConnection();
        $this->jobTableName = $this->resource->getTableName('job');
        $this->jobFactory = $jobFactory;
        $this->helper = $helper;

        $this->debugMessage = "";
    }

    public function getDebugMessage()
    {
        return $this->debugMessage;
    }

    public function cleanUpJobData()
    {
        //$query = "delete from " . $this->jobTableName . " where submittedon < CurDate() - 7";
        //$this->db->query($query, null);

        $binds = ['days' => 7];
        $this->db->query("DELETE FROM " . $this->jobTableName . ' WHERE submittedon < CurDate() - :days', $binds);
    }

    public function checkJobColumns($columnName)
    {
        $config = $this->db->getConfig();
        $dbname = $config['dbname'];
        
        $binds = ["dbname" => $dbname, "columnName" => $columnName];
        $sql = "SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = :dbname  
                AND TABLE_NAME = 'job' AND COLUMN_NAME = :columnName";
        //var_dump($sql);
        
        $row = $this->db->fetchAll($sql, $binds);
        //var_dump($row);
        unset($binds);
        
        if ($row == null || count($row) == 0):
            $this->db->query("ALTER TABLE " . $this->jobTableName
                . " ADD COLUMN " . $columnName . " LONGTEXT NULL", null);
            return true;
        else:
            return false;
        endif;
    }

    public function fetchAll($sql)
    {
        //$sql = $this->db->select();
        //$sql->from(array('f' => 'foobar'));
        //$data = $this->db->fetchAll($sql);
        $rows = $this->db->fetchAll($sql);
        return $rows;
    }

    public function getJobs($count)
    {
        $binds = ['count' => $count];
        $jobRows = $this->db
                ->fetchAll(
                    "SELECT * FROM  " . $this->jobTableName . " where status!=2 order by id desc LIMIT 0,:count",
                    $binds
                );
        return $jobRows;
    }

    /*
      CREATE TABLE  job (
      id BIGINT NOT NULL  AUTO_INCREMENT,
      submittedon DATETIME NOT NULL ,
      processedon DATETIME NULL ,
      localfilename VARCHAR( 100 ) NOT NULL ,
      status TINYINT NOT NULL ,
      logfilename VARCHAR( 100 ) NOT NULL ,
      PRIMARY KEY (  id )
      )
     */

    public function createJobTableIfNotExist()
    {
        try {
            $q = 'CREATE TABLE IF NOT EXISTS job (
                            id                  BIGINT NOT NULL  AUTO_INCREMENT,
                            submittedon         DATETIME NOT NULL ,
                            processedon         DATETIME NULL ,
                            localfilename       VARCHAR( 100 ) NOT NULL ,
                            module              VARCHAR( 50 ) NULL ,
                            status              TINYINT NOT NULL ,
                            logfilename         VARCHAR( 100 ) NOT NULL ,
                            local_file_source   LONGTEXT NULL,
                            log_file_source     LONGTEXT NULL,
                            stack_trace         LONGTEXT NULL,
                            
                    PRIMARY KEY (  id )
            )';
            $this->db->query($q, null);
            //$this->db->query("alter table `job` add column `module` varchar(50) NULL", null);
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
        }

        //$this->CheckJobColumns( "local_file_source" );
        $this->db->query("REPAIR TABLE `job`", null);
    }

    public function getJobLocalFileSource($id)
    {
        if ($id == 0) {
            return null;
        }
        $binds = ['id' => $id];
        $local_file_source = $this->db
                ->fetchOne("SELECT local_file_source FROM " . $this->jobTableName . " WHERE id= :id", $binds);
        return $local_file_source;
    }

    public function getJobLogFileSource($id)
    {
        if ($id == 0) {
            return null;
        }
        $binds = ['id' => $id];
        $log_file_source = $this->db
                ->fetchOne("SELECT log_file_source FROM " . $this->jobTableName . " WHERE id= :id", $binds);
        return $log_file_source;
    }

    public function getJobStackTrace($id)
    {
        if ($id == 0) {
            return null;
        }
        
        $binds = ['id' => $id];
        $stack_trace = $this->db->fetchOne("SELECT stack_trace FROM " . $this->jobTableName . " WHERE id= :id", $binds);
        return $stack_trace;
    }
    
    public function updateJobSubmitted($id)
    {
        $query = "UPDATE " . $this->jobTableName . " SET status=1 WHERE id = :id";
        $binds = ['id' => $id];
        try {
            $this->db->query($query, $binds);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
            return false;
        }
    }

    public function updateJobCompleted($id)
    {
        $query = "UPDATE " . $this->jobTableName . " SET processedon=NOW(), status=2 WHERE id = :id";
        $binds = ['id' => $id];
        try {
            $this->db->query($query, $binds);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
            return false;
        }
    }

    public function updateOldJobErrored()
    {
        $query = "UPDATE " . $this->jobTableName . " SET processedon=NOW(), status=-1 WHERE status in(0, 1)";
        $binds = [];
        try {
            $this->db->query($query);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
            return false;
        }
    }

    public function updateJobErrored($id)
    {
        $query = "UPDATE " . $this->jobTableName . " SET processedon=NOW(), status=-1 WHERE id = :id";
        $binds = ['id' => $id];
        try {
            $this->db->query($query, $binds);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
            return false;
        }
    }

    public function updateInvalid()
    {
        $query = "UPDATE " . $this->jobTableName . " SET status=-1 WHERE status = NULL";
        try {
            $this->db->query($query, null);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
            return false;
        }
    }

    public function updateJobStatus($id, $status)
    {
        $query = "UPDATE " . $this->jobTableName . " SET status=" . $status . " WHERE id = :id";
        $binds = ['id' => $id];
        try {
            $this->db->query($query, $binds);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage() . ' ' . $query;
            return false;
        }
    }

    public function updateJobLogFileSource($id, $log_file_source)
    {
        $query = "UPDATE " . $this->jobTableName . " SET log_file_source=:log_file_source WHERE id = :id";
        $binds = [
            'log_file_source' => $log_file_source,
            'id' => $id
        ];

        try {
            $this->db->query($query, $binds);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage() . ' ' . $query;
            return false;
        }
    }

    public function updateJobStackTraceConcatenated($id, $stack_trace)
    {
        $stack_trace .= "<br />\r\n";

        $query = "UPDATE " . $this->jobTableName
                . " SET stack_trace = IFNULL (CONCAT( stack_trace , :stack_trace ), :stack_trace)  WHERE id = :id";
        //var_dump($query);
        $binds = [
            'stack_trace' => $stack_trace,
            'id' => $id
        ];
        
        try {
            $this->db->query($query, $binds);
            return true;
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage() . ' ' . $query;
            return false;
        }
    }
    
    public function insertJob(
        $submittedon,
        $processedon,
        $status,
        $module,
        $local_file_source,
        $log_file_source
    ) {
        //$datetime = mysql_real_escape_string($datetime);
        //$datetime = strtotime($datetime);
        //$datetime = date('Y-m-d H:i:s',$datetime);
        //$submittedon = date("Y-m-d H:i:s");
        try {
            $job = $this->jobFactory->create();

            $job->setData("submittedon", $submittedon);
            $job->setData("processedon", $processedon);
            $job->setData("status", $status);
            $job->setData("local_file_source", $local_file_source);
            $job->setData("log_file_source", $log_file_source);
            $job->setData("module", $module);

            $job->save();
            return $job->getId();
        } catch (Exception $e) {
            $this->debugMessage .= $e->getMessage();
        }
        
        return -1;
    }

    public function getOneSubmittedJob($module)
    {
        if ($module == ''):
            $jobRow = $this->db->fetchAll(
                "SELECT * FROM "
                . $this->jobTableName
                . " WHERE status in (0,1) and module in"
                . "('', 'invprice', 'images', 'shipping', 'refund', 'order_field_update') "
                . " ORDER BY submittedon ASC LIMIT 0,1"
            );
        else:
            $binds = ["module" => $module];
            $jobRow = $this->db->fetchAll("SELECT * FROM " . $this->jobTableName
                    . " WHERE status in (0,1) and module= :module ORDER BY submittedon ASC LIMIT 0,1", $binds);
        endif;
        return $jobRow;
    }

    public function getJob($id)
    {
        $binds = ["id" => $id];
        $jobRow = $this->db->fetchAll("SELECT * FROM " . $this->jobTableName . " WHERE id= :id", $binds);
        return $jobRow;
    }

    public function printJobXmlError($err)
    {
        $xml_output = "<?xml version=\"1.0\"?>";
        $xml_output .= "<jobresult>";
        $xml_output .= $this->helper->printModuleVersion();
        $xml_output .= "<id>0</id><status>error</status><submittedon/><processedon/>"
                . "<localfilename/><logfilename/><products/>"
                . "<errors><error>" . $err . "</error></errors></jobresult>";
        return $xml_output;
    }

    public function printXml($job_row, $extra_error = '')
    {
        $scmp_log_vpath = $this->helper->getScmpLogVirtualPath();
        $xml_output = "";
        $error_output = "<errors>";

        if ($extra_error != ''):
            $error_output .= "<error><![CDATA[" . $extra_error . "]]></error>";
        endif;

        $log_file_source = $job_row[0]["log_file_source"];
        $xml_output .= "<?xml version=\"1.0\"?>\n";

        if ($job_row != null) {
            $xml_output .= "<jobresult>\n";
            $xml_output .= $this->helper->printModuleVersion() ;

            $status = "";
            if ($job_row[0]['status'] == -1) {
                $status = "error";
            } elseif ($job_row[0]['status'] == 0) {
                $status = "submitted";
            } elseif ($job_row[0]['status'] == 1) {
                $status = "processing";
            } elseif ($job_row[0]['status'] == 2) {
                $status = "completed";
            }

            $xml_output .= "<id>" . $job_row[0]['id'] . "</id>\n";
            $xml_output .= "<status>" . $status . "</status>\n";
            $xml_output .= "<submittedon>" . $job_row[0]['submittedon'] . "</submittedon>\n";
            $xml_output .= "<processedon>" . $job_row[0]['processedon'] . "</processedon>\n";
            $xml_output .= "<localfilename>" . $scmp_log_vpath
                    . "jobsource?id=" . $job_row[0]['id'] . "</localfilename>\n";
            $xml_output .= "<logfilename>" . $scmp_log_vpath
                    . "joblog?id=" . $job_row[0]['id'] . "</logfilename>\n";
            $xml_output .= "<module>" . $job_row[0]['module'] . "</module>\n";

            $dom = new \DOMDocument();

            try {
                if ($job_row[0]['status'] != 0) {
                    //var_dump($log_file_source);
                    if (empty($log_file_source)) {
                        throw new \Magento\Framework\Exception\LocalizedException("LOG XML does not exist");
                    }
                }

                $dom->loadXML($log_file_source);
                if ($dom) {
                    $node = $dom->getElementsByTagName('products')->item(0);
                    if ($node) {
                        $nodeXml = $dom->saveXML($node);
                        $xml_output .= "\n\t" . $nodeXml . "\n";
                    } else {
                        $xml_output .= "<products></products>";
                    }
                } else {
                    $error_output .= "<error>Log Xml could not be loaded into DOM</error>";
                }
            } catch (Exception $ex) {
                $error_output .= "<error>" . htmlentities($ex->getMessage()) . "</error>";
            }

            $error_output .= "</errors>\n";
            $xml_output .= $error_output;
            $xml_output .= "</jobresult>";
        } else {
            $xml_output .= "<jobresult>";
            $xml_output .= $this->helper->printModuleVersion() ;
            $xml_output .= "<id>0</id><status></status><submittedon/><processedon/>"
                    . "<localfilename/><logfilename/><products/>"
                    . "<errors></errors></jobresult>";
        }

        return $xml_output;
    }
    
    public function removeMediaGalleryManually($productId, $verbose = 0)
    {
        $binds = ['entity_id' => $productId];
        
        $sql = "SELECT value_id FROM  "
            . $this->resource->getTableName("catalog_product_entity_media_gallery_value_to_entity")
            . " where entity_id=:entity_id" ;

        if ($verbose == 1):
            $this->debugMessage .= $sql . " <br />\r\n";
        endif;
        
        $valueIdRows = $this->db->fetchAll($sql, $binds);
        if (!$valueIdRows || ($valueIdRows && count($valueIdRows) == 0)):
            if ($verbose == 1):
                $this->debugMessage .= "No rows found for deletion [removeMediaGalleryManually] <br />\r\n";
            endif;
            return;
        endif;
        
        $this->db->query("DELETE FROM " . $this->resource->getTableName("catalog_product_entity_media_gallery_value")
                    . " WHERE entity_id=:entity_id", $binds);
        $this->db->query("DELETE FROM " . $this->resource
            ->getTableName("catalog_product_entity_media_gallery_value_to_entity")
            . " WHERE entity_id=:entity_id", $binds);
        
        foreach ($valueIdRows as $key => $value):
            $binds = ['value_id' => $value["value_id"]];
            $this->db->query(
                "DELETE FROM " . $this->resource->getTableName("catalog_product_entity_media_gallery")
                . " WHERE value_id=:value_id",
                $binds
            );
            unset($binds);
        endforeach;
        
        if ($verbose == 1):
            $this->debugMessage .= "Deleted rows from [removeMediaGalleryManually] <br />\r\n";
        endif;
        
        return $valueIdRows;
    }
    
    public function removeTierPrices($productId, $verbose = 0)
    {
        $binds = ['entity_id' => $productId];
        $this->db->query("DELETE FROM " . $this->resource->getTableName("catalog_product") . '_tier_price'
                    . " WHERE entity_id=:entity_id", $binds);
    }
}
