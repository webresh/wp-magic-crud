<?php

namespace WPMC\DB;

use WPMC\Entity;

class EntitySchema
{
    public function __construct(private Entity $entity)
    {
    }

    public function doCreateTable() {
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        $entity = $this->entity;
        $table = $entity->getDatabase()->getTableName();
        $pkey = $entity->getDatabase()->getPrimaryKey();
        $fields = $entity->getFieldsObjects();
        $stmt = $this->buildColumnStatements($entity);
        $fkStmt = $this->buildForeignKeyStatements($entity);
        
        $sql = "CREATE TABLE {$table} (
            `{$pkey}` int(11) NOT NULL AUTO_INCREMENT,
            `user_id` INTEGER,
            " .  $stmt .
            $fkStmt . "
            PRIMARY KEY ({$pkey})
        ) {$charset}";

        dbDelta($sql);

        // if ( $wpdb->last_error !== '' ) {
        //     throw new Exception('Error when creating HasManyField relationship table: ' . $sql);
        // }

        if ( $entity->getDatabase()->hasPrimaryKey() && !psTableColumnExists($table, $pkey) ) {
            $wpdb->query("ALTER TABLE {$table} ADD {$pkey} INT PRIMARY KEY AUTO_INCREMENT");
        }

        foreach ( $fields as $field ) {
            $field->afterDbTableCreated();
        }
    }

    private function buildColumnStatements(Entity $entity)
    {
        $fields = $entity->getFieldsObjects();
        $stmt = [];

        foreach ( $fields as $field ) {
            if ( $field->isPrimitiveType() ) {
                $col = $field->getName();
                $dbType = $field->getDbType();
                $ref = $field->getDbReferences();
                $null = $field->getRequired() ? ' NOT NULL' : '';
    
                $stmt[$col] = "  `{$col}` {$dbType}{$null}{$ref},";
            }

            $fk = $field->getForeignKeyStatement();

            if ( !empty($fk) ) {
                $foreignKeys[] = $fk;
            }
        }

        return implode("\n", $stmt);
    }

    private function buildForeignKeyStatements(Entity $entity)
    {
        $fields = $entity->getFieldsObjects();
        $foreignKeys = [];

        foreach ( $fields as $field ) {
            $fk = $field->getForeignKeyStatement();

            if ( !empty($fk) ) {
                $foreignKeys[] = $fk;
            }
        }
        
        if ( !empty($foreignKeys) ) {
            return "\n" . implode("\n", $foreignKeys);
        }

        return '';
    }
}