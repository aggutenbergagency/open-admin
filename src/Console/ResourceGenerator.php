<?php

namespace OpenAdmin\Admin\Console;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use Illuminate\Database\MySqlConnection;
use PDO;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Connection as DoctrineConnection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use \OpenAdmin\Admin\Console\ResourceGenerator as BaseResourceGenerator;

class ResourceGenerator
{
    /**
     * @var Model
     */
    protected $model;
    private $useDoctine = true;

    /**
     * @var array
     */
    protected $formats = [
        'form_field'  => "\$form->%s('%s', __('%s'))",
        'show_field'  => "\$show->field('%s', __('%s'))",
        'grid_column' => "\$grid->column('%s', __('%s'))",
    ];

    /**
     * @var array
     */
    private $doctrineTypeMapping = [
        'string' => [
            'enum', 'geometry', 'geometrycollection', 'linestring',
            'polygon', 'multilinestring', 'multipoint', 'multipolygon',
            'point',
        ],
    ];

    /**
     * @var array
     */
    protected $fieldTypeMapping = [
        'ip'          => 'ip',
        'email'       => 'email|mail',
        'password'    => 'password|pwd',
        'url'         => 'url|link|src|href',
        'phonenumber' => 'mobile|phone',
        'color'       => 'color|rgb',
        'image'       => 'image|img|avatar|pic|picture|cover',
        'file'        => 'file|attachment',
    ];

    /**
     * ResourceGenerator constructor.
     *
     * @param mixed $model
     */
    public function __construct($model)
    {
        $this->model = $this->getModel($model);

        if (explode('.', $this->model->getTable())[0] >= 11) {
            $this->useDoctine = false;
        }
    }

    /**
     * @param mixed $model
     *
     * @return mixed
     */
    protected function getModel($model)
    {
        if ($model instanceof Model) {
            return $model;
        }

        if (!class_exists($model) || !is_string($model) || !is_subclass_of($model, Model::class)) {
            throw new \InvalidArgumentException("Invalid model [$model] !");
        }

        return new $model();
    }

    /**
     * @return string
     */
    public function generateForm()
    {
        $reservedColumns = $this->getReservedColumns();

        $output = '';

        $table = $this->model->getTable();
        foreach ($this->getTableColumns() as $column) {
            $name = $column->getName();
            if (in_array($name, $reservedColumns)) {
                continue;
            }
            if ($this->useDoctine) {
                $type = $column->getType()->getName();
            } else {
                $type = Schema::getColumnType($table, $name);
            }
            $default = $column->getDefault();

            $defaultValue = '';

            // set column fieldType and defaultValue
            switch ($type) {
                case 'boolean':
                case 'bool':
                    $fieldType = 'switch';
                    break;
                case 'json':
                case 'array':
                case 'object':
                    $fieldType = 'textarea';
                    break;
                case 'string':
                    $fieldType = 'text';
                    foreach ($this->fieldTypeMapping as $type => $regex) {
                        if (preg_match("/^($regex)$/i", $name) !== 0) {
                            $fieldType = $type;
                            break;
                        }
                    }
                    $defaultValue = "'{$default}'";
                    break;
                case 'integer':
                case 'bigint':
                case 'smallint':
                    $fieldType = 'number';
                    break;
                case 'decimal':
                case 'float':
                case 'real':
                    $fieldType = 'decimal';
                    break;
                case 'timestamp':
                case 'datetime':
                    $fieldType    = 'datetime';
                    $defaultValue = "date('Y-m-d H:i:s')";
                    break;
                case 'date':
                    $fieldType    = 'date';
                    $defaultValue = "date('Y-m-d')";
                    break;
                case 'time':
                    $fieldType    = 'time';
                    $defaultValue = "date('H:i:s')";
                    break;
                case 'text':
                case 'blob':
                    $fieldType = 'textarea';
                    break;
                default:
                    $fieldType    = 'text';
                    $defaultValue = "'{$default}'";
            }

            $defaultValue = $defaultValue ?: $default;

            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['form_field'], $fieldType, $name, $label);

            if (trim($defaultValue, "'\"")) {
                $output .= "->default({$defaultValue})";
            }

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateShow()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name = $column->getName();

            // set column label
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['show_field'], $name, $label);

            $output .= ";\r\n";
        }

        return $output;
    }

    public function generateGrid()
    {
        $output = '';

        foreach ($this->getTableColumns() as $column) {
            $name  = $column->getName();
            $label = $this->formatLabel($name);

            $output .= sprintf($this->formats['grid_column'], $name, $label);
            $output .= ";\r\n";
        }

        return $output;
    }

    protected function getReservedColumns()
    {
        return [
            $this->model->getKeyName(),
            $this->model->getCreatedAtColumn(),
            $this->model->getUpdatedAtColumn(),
            'deleted_at',
        ];
    }

    /**
     * Get table columns for the model.
     *
     * @return array
     * @throws Exception
     */
    protected function getTableColumns(): array
    {   
        $doctrineConnection = $this->createDoctrineConnection();
        $schemaManager = $doctrineConnection->getSchemaManager();

        $table = $this->getTableNameWithPrefix();
        $this->mapCustomDoctrineTypes($schemaManager);

        [$database, $table] = $this->splitDatabaseAndTable($table);

        return $schemaManager->listTableColumns($table, $database);
    }

    /**
     * Create a Doctrine DBAL connection.
     *
     * @return DoctrineConnection
     * @throws Exception
     */
    private function createDoctrineConnection(): DoctrineConnection
    {
        /** @var MySqlConnection $connection */
        $connection = $this->model->getConnection();

        return DriverManager::getConnection([
            'pdo'      => $connection->getPdo(),
            'driver'   => 'pdo_mysql',
            'user'     => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            'dbname'   => env('DB_DATABASE'),
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', 3306),
        ]);
    }

    /**
     * Get the table name with prefix.
     *
     * @return string
     */
    private function getTableNameWithPrefix(): string
    {
        $connection = $this->model->getConnection();
        return $connection->getTablePrefix() . $this->model->getTable();
    }

    /**
     * Map custom Doctrine types.
     *
     * @param AbstractSchemaManager $schemaManager
     * @return void
     */
    private function mapCustomDoctrineTypes(AbstractSchemaManager $schemaManager): void
    {
        $databasePlatform = $schemaManager->getDatabasePlatform();

        foreach ($this->doctrineTypeMapping as $doctrineType => $dbTypes) {
            foreach ($dbTypes as $dbType) {
                $databasePlatform->registerDoctrineTypeMapping($dbType, $doctrineType);
            }
        }
    }

    /**
     * Split database and table name if necessary.
     *
     * @param string $table
     * @return array [database, table]
     */
    private function splitDatabaseAndTable(string $table): array
    {
        if (strpos($table, '.') !== false) {
            return explode('.', $table, 2);
        }

        return [null, $table];
    }

    /**
     * Format label.
     *
     * @param string $value
     *
     * @return string
     */
    protected function formatLabel($value)
    {
        return ucfirst(str_replace(['-', '_'], ' ', $value));
    }
}
