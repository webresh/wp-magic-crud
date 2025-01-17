<?php
namespace WPMC\UI;

use Exception;
use WPMC\Entity;

class TableActionsProcessor
{
    public function __construct(private Entity $entity)
    {
    }

    private function updateFormUrl($id) {
        $entity = $this->entity;
        $page = CommonAdmin::formPageIdentifier($entity);
        return get_admin_url(get_current_blog_id(), 'admin.php?page='.$page.'&id='.$id);
    }

    private function getActionLink($action, $id, $label) {
        return sprintf('<a href="%s">%s</a>', $this->getActionUrl($action, $id), $label);
    }

    private function getActionUrl($action, $id) {
        $identifier = $this->entity->getIdentifier();
        return get_admin_url(get_current_blog_id(), 'admin.php?page='.$identifier.'&action='.$action.'&id='.$id.'&crud='.$identifier);
    }

    public function getActions($item) {
        if ( empty($item['id'])) {
            return [];
        }

        $updateUrl = $this->updateFormUrl($item['id']);
        $page = sanitize_text_field($_REQUEST['page']);
        $entity = $this->entity;

        $actions = array(
            'edit' => sprintf('<a href="%s">%s</a>', $updateUrl, __('Edit', 'wp-magic-crud')),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s" onclick="return confirm(\'%s\')">%s</a>', $page, $item['id'], __('Confirm delete?', 'wp-magic-crud'), __('Delete', 'wp-magic-crud')),
        );

        $uiActions = $entity->actionsCollection()->getFieldableActionUIs();

        foreach ( $uiActions as $action ) {
            $actions[ $action->getAlias() ] = $this->getActionLink($action->getAlias(), $item['id'], $action->getLabel());
        }

        return apply_filters('wpmc_list_actions', $actions, $item);
    }

    public function getBulkActions()
    {
        $entity = $this->entity;

        if ( !$entity->getDatabase()->hasPrimaryKey() ) {
            return [];
        }

        $actions = array(
            'delete' => __('Delete', 'wp-magic-crud'),
        );

        foreach ( $entity->actionsCollection()->getFieldableActionUIs() as $action ) {
            if ( $action->isBulkable() ) {
                $actions[ $action->getAlias() ] = $action->getLabel();
            }
        }

        return apply_filters('wpmc_bulk_actions', $actions);
    }

    private function getRequestIds() {
        $ids = [];

        if ( !empty($_REQUEST['id']) ) {
            $ids = is_array($_REQUEST['id']) ?
                array_map('sanitize_text_field', $_REQUEST['id']) :
                explode(',', sanitize_text_field($_REQUEST['id']));
        }

        return $ids;
    }

    public function processActionsAndBulk($action)
    {
        $ids = $this->getRequestIds();
        $entity = $this->entity;

        try {
            if ( empty($ids) ) {
                throw new Exception(__('Please select one or more items', 'wp-magic-crud'));
            }
    
            switch($action) {
                case 'delete':
                    $this->entity->delete($ids);
    
                    wpmc_flash_message( sprintf(__('Items removed: %d', 'wp-magic-crud'), count($ids)) );
                    wpmc_redirect( wpmc_entity_admin_url($entity) );
                break;
                default:
                    $obj = $entity->actionsCollection()->getActionByAlias($action);

                    $uiAction = $obj->getActionUI();
                    $uiAction->renderOrExecute($ids);
                break;
            }
        }
        catch (Exception $e) {
            wpmc_flash_message($e->getMessage(), 'error');
            wpmc_redirect( wpmc_entity_admin_url($entity) );
        }
    }
}