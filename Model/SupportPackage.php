<?php
declare(strict_types=1);

namespace Bitpay\BPCheckout\Model;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Module\Dir as ModuleDir;
use Magento\Framework\Module\FullModuleList;
use Magento\Framework\Module\ResourceInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\Xml\Parser as XmlParser;
use ZipArchive;

/**
 * Support Package model
 */
class SupportPackage
{
    /**
     * @var FullModuleList
     */
    private $fullModuleList;

    /**
     * @var ResourceInterface
     */
    private $moduleResource;

    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var XmlParser
     */
    private $xmlParser;

    /**
     * @var ModuleDir
     */
    private $moduleDir;

    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var ProductMetadataInterface
     */
    private $productMetadata;

    /**
     * @var DirectoryList
     */
    private $directoryList;

    /**
     * @var Json
     */
    private $jsonSerializer;

    /**
     * @var File
     */
    private $fileDriver;

    /**
     * @var ZipArchive
     */
    private $zipArchive;

    /**
     * Constructor
     *
     * @param FullModuleList $fullModuleList
     * @param ResourceInterface $moduleResource
     * @param DeploymentConfig $deploymentConfig
     * @param ResourceConnection $resourceConnection
     * @param XmlParser $xmlParser
     * @param ModuleDir $moduleDir
     * @param UrlInterface $urlBuilder
     * @param ProductMetadataInterface $productMetadata
     * @param DirectoryList $directoryList
     * @param Json $jsonSerializer
     * @param File $fileDriver
     * @param ZipArchive $zipArchive
     */
    public function __construct(
        FullModuleList $fullModuleList,
        ResourceInterface $moduleResource,
        DeploymentConfig $deploymentConfig,
        ResourceConnection $resourceConnection,
        XmlParser $xmlParser,
        ModuleDir $moduleDir,
        UrlInterface $urlBuilder,
        ProductMetadataInterface $productMetadata,
        DirectoryList $directoryList,
        Json $jsonSerializer,
        File $fileDriver,
        ZipArchive $zipArchive,
    ) {
        $this->moduleResource = $moduleResource;
        $this->fullModuleList = $fullModuleList;
        $this->deploymentConfig = $deploymentConfig;
        $this->resourceConnection = $resourceConnection;
        $this->xmlParser = $xmlParser;
        $this->moduleDir = $moduleDir;
        $this->urlBuilder = $urlBuilder;
        $this->productMetadata = $productMetadata;
        $this->directoryList = $directoryList;
        $this->jsonSerializer = $jsonSerializer;
        $this->fileDriver = $fileDriver;
        $this->zipArchive = $zipArchive;
    }

    /**
     * Prepares the support download archive
     */
    public function prepareDownloadArchive()
    {
        $path = $this->directoryList->getPath(DirectoryList::TMP) . '/bitpay-support.zip';

        $this->zipArchive->open($path, \ZipArchive::CREATE);
        $this->zipArchive->addFromString(
            'bitpay-support.json',
            $this->jsonSerializer->serialize($this->prepareSupportDetails())
        );
        $logPath = $this->directoryList->getPath(DirectoryList::LOG) . '/bitpay.log';
        if ($this->fileDriver->isExists($logPath)) {
            $this->zipArchive->addFile($logPath, 'bitpay.log');
        }
        $this->zipArchive->close();

        return $path;
    }

    /**
     * Prepares the support details
     *
     * @return array
     */
    public function prepareSupportDetails()
    {
        return [
            'bitpay_module_verson' => $this->getBitpayModuleVersion(),
            'modules' => $this->getModuleList(),
            'database' => $this->getDbDetails(),
            'magento' => $this->getMagentoDetails(),
            'server' => $this->getServerDetails(),
            'php' => $this->getPhpDetails(),
        ];
    }

    /**
     * Get the Bitpay module version
     */
    public function getBitpayModuleVersion()
    {
        return $this->moduleResource->getDbVersion('Bitpay_BPCheckout');
    }

    /**
     * Get the installed modules list
     */
    public function getModuleList()
    {
        $modules = [];
        $allModules = $this->fullModuleList->getAll();
        foreach ($allModules as $module) {
            $modules[] = [
                'name' => $module['name'],
                'schema_version' => $this->moduleResource->getDbVersion($module['name']) ?: 'N/A',
                'data_version' => $this->moduleResource->getDataVersion($module['name']) ?: 'N/A',
            ];
        }

        return $modules;
    }

    /**
     * Get the database details
     */
    public function getDbDetails()
    {
        $configData = $this->deploymentConfig->getConfigData();

        $dbServerType = $configData['db']['connection']['default']['model'] ?: 'N/A';
        switch (true) {
            case stristr($dbServerType, 'mysql') !== false:
                $dbServerType = 'mysql';
                break;
            case stristr($dbServerType, 'postgre') !== false || stristr($dbServerType, 'pgsql') !== false:
                $dbServerType = 'postgresql';
                break;
            case stristr($dbServerType, 'oracle') !== false:
                $dbServerType = 'oracle';
                break;
            case stristr($dbServerType, 'sqlite') !== false:
                $dbServerType = 'sqlite';
                break;
            case stristr($dbServerType, 'sqlsrv') !== false || stristr($dbServerType, 'mssql') !== false:
                $dbServerType = 'mssql';
                break;
        }

        $dbVersion = 'N/A';
        $dbCollation = 'N/A';
        $dbCharSet = 'N/A';
        $connection = $this->resourceConnection->getConnection();
        switch ($dbServerType) {
            case 'mysql':
                $dbVersion = $connection->fetchOne('SELECT VERSION()');
                $row = $connection->fetchRow('SHOW VARIABLES LIKE "collation_connection"');
                $dbCollation = $row['Value'];
                $row = $connection->fetchRow('SHOW VARIABLES LIKE "character_set_database"');
                $dbCharSet = $row['Value'];
                break;
            case 'postgresql':
                $dbVersion = $connection->fetchOne('SELECT version()');
                $row = $connection->fetchRow('SHOW SERVER_ENCODING');
                $dbCollation = $row['server_encoding'];
                $row = $connection->fetchRow('SHOW SERVER_ENCODING');
                $dbCharSet = $row['server_encoding'];
                break;
            case 'mssql':
                $dbVersion = $connection->fetchOne('SELECT @@VERSION');
                $row = $connection->fetchRow('SELECT SERVERPROPERTY(\'COLLATION\') AS collation');
                $dbCollation = $row['collation'];
                $row = $connection->fetchRow('SELECT SERVERPROPERTY(\'COLLATION\') AS collation');
                $dbCharSet = $row['collation'];
                break;
            case 'oracle':
                $dbVersion = $connection->fetchOne('SELECT * FROM V$VERSION');
                $row = $connection->fetchRow(
                    'SELECT VALUE FROM NLS_DATABASE_PARAMETERS WHERE PARAMETER = \'NLS_CHARACTERSET\''
                );
                $dbCollation = $row['VALUE'];
                break;
            case 'sqlite':
                $dbVersion = $connection->fetchOne('SELECT sqlite_version()');
                break;
        }

        $moduleEtcPath = $this->moduleDir->getDir('Bitpay_BPCheckout', \Magento\Framework\Module\Dir::MODULE_ETC_DIR);
        $tables = [];
        $dbSchema = $this->xmlParser->load($moduleEtcPath . '/db_schema.xml')->xmlToArray();
        foreach ($dbSchema['schema']['_value']['table'] as $table) {
            $tables[] = [
                'name' => $table['_attribute']['name'],
                'exists' => $connection->isTableExists($table['_attribute']['name']),
            ];
        }

        return [
            'dbms' => $dbServerType,
            'dbms_version' => $dbVersion,
            'char_set' => $dbCharSet,
            'collation' => $dbCollation,
            'tables' => $tables,
        ];
    }

    /**
     * Get Magento details
     */
    public function getMagentoDetails()
    {
        return [
            'url' => $this->urlBuilder->getBaseUrl(),
            'version' => $this->productMetadata->getVersion(),
        ];
    }

    /**
     * Get server details
     */
    public function getServerDetails()
    {
        list($serverSoftware, $serverVersion) = explode(
            '/',
            isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : getenv('SERVER_SOFTWARE')
        );
        
        return [
            'software' => $serverSoftware,
            'version' => $serverVersion,
            'document_root' => $this->directoryList->getRoot(),
        ];
    }

    /**
     * Get PHP details
     */
    public function getPhpDetails()
    {
        return [
            'version' => phpversion(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'max_file_upload_size' => ini_get('upload_max_filesize'),
            'max_post_size' => ini_get('post_max_size'),
            'max_input_variables' => ini_get('max_input_vars'),
            'curl_enabled' => extension_loaded('curl'),
            'curl_version' => extension_loaded('curl') ? curl_version()['version'] : 'N/A',
            'openssl_version' => extension_loaded('openssl') ? OPENSSL_VERSION_TEXT: 'N/A',
            'mcrypt_enabled' => extension_loaded('mcrypt'),
            'mbstring_enabled' => extension_loaded('mbstring'),
            'extensions' => get_loaded_extensions(),
        ];
    }
}
