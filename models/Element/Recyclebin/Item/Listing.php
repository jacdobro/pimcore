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
 * @package    Element
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Element\Recyclebin\Item;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Element\Recyclebin\Item\Listing\Dao getDao()
 * @method Model\Element\Recyclebin\Item[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * @var array|null
     */
    protected $items = null;

    /**
     * @return Model\Element\Recyclebin\Item[]
     */
    public function getItems()
    {
        if($this->items === null) {
            $this->getDao()->load();
        }

        return $this->items;
    }

    /**
     * @param array $items
     *
     * @return $this
     */
    public function setItems($items)
    {
        $this->items = $items;

        return $this;
    }
}
