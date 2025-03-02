<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @category   Pimcore
 * @package    DataObject\Objectbrick
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\DataObject\Objectbrick;

use Pimcore\Model;
use Pimcore\Model\DataObject;
use Pimcore\Model\DataObject\ClassDefinition\Data\CustomResourcePersistingInterface;
use Pimcore\Model\DataObject\ClassDefinition\Data\ResourcePersistenceAwareInterface;

/**
 * @property \Pimcore\Model\DataObject\Objectbrick $model
 */
class Dao extends Model\DataObject\Fieldcollection\Dao
{
    /**
     * @param DataObject\Concrete $object
     * @param array $params
     *
     * @return array
     */
    public function load(DataObject\Concrete $object, $params = [])
    {
        $fieldDef = $object->getClass()->getFieldDefinition($this->model->getFieldname());

        $values = [];

        foreach ($fieldDef->getAllowedTypes() as $type) {
            if(!$definition = DataObject\Objectbrick\Definition::getByKey($type)) {
                continue;
            }

            $tableName = $definition->getTableName($object->getClass(), false);

            try {
                $results = $this->db->fetchAll('SELECT * FROM '.$tableName.' WHERE o_id = ? AND fieldname = ?', [$object->getId(), $this->model->getFieldname()]);
            } catch (\Exception $e) {
                $results = [];
            }

            $fieldDefinitions = $definition->getFieldDefinitions(['object' => $object, 'suppressEnrichment' => true]);
            $brickClass = '\\Pimcore\\Model\\DataObject\\Objectbrick\\Data\\' . ucfirst($type);

            foreach ($results as $result) {
                $brick = new $brickClass($object);
                $brick->setFieldname($result['fieldname']);
                $brick->setObject($object);

                foreach ($fieldDefinitions as $key => $fd) {
                    if ($fd instanceof CustomResourcePersistingInterface) {
                        $doLoad = true;

                        if ($fd instanceof  DataObject\ClassDefinition\Data\Relations\AbstractRelations) {
                            if (!DataObject\Concrete::isLazyLoadingDisabled() && $fd->getLazyLoading()) {
                                $doLoad = false;
                            }
                        }

                        if ($doLoad) {
                            // datafield has it's own loader
                            $context = [];
                            $context['object'] = $object;
                            $context['containerType'] = 'objectbrick';
                            $context['containerKey'] = $brick->getType();
                            $context['brickField'] = $key;
                            $context['fieldname'] = $brick->getFieldname();
                            $params['context'] = $context;

                            $value = $fd->load($brick, $params);
                            if ($value === 0 || !empty($value)) {
                                $brick->setValue($key, $value);
                            }
                        }
                    }
                    if ($fd instanceof ResourcePersistenceAwareInterface) {
                        if (is_array($fd->getColumnType())) {
                            $multidata = [];
                            foreach ($fd->getColumnType() as $fkey => $fvalue) {
                                $multidata[$key . '__' . $fkey] = $result[$key . '__' . $fkey];
                            }
                            $brick->setValue(
                                $key,
                                $fd->getDataFromResource($multidata)
                            );
                        } else {
                            $brick->setValue(
                                $key,
                                $fd->getDataFromResource($result[$key])
                            );
                        }
                    }
                }

                $setter = 'set' . ucfirst($type);

                if ($brick instanceof DataObject\DirtyIndicatorInterface) {
                    $brick->markFieldDirty($key, false);
                }

                $this->model->$setter($brick);

                $values[] = $brick;
            }
        }

        return $values;
    }

    /**
     * @param DataObject\Concrete $object
     * @param $saveMode true if called from save method
     *
     * @return whether an insert should be done or not
     */
    public function delete(DataObject\Concrete $object, $saveMode = false)
    {
        // this is to clean up also the inherited values
        $fieldDef = $object->getClass()->getFieldDefinition($this->model->getFieldname());
        foreach ($fieldDef->getAllowedTypes() as $type) {
            if($definition = DataObject\Objectbrick\Definition::getByKey($type)) {
                $tableName = $definition->getTableName($object->getClass(), true);
                $this->db->delete($tableName, ['o_id' => $object->getId()]);
            }
        }
    }
}
