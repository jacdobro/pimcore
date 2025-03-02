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
 * @package    User
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\User\Permission\Definition;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\User\Permission\Definition\Listing\Dao getDao()
 * @method Model\User\Permission\Definition[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * @var array|null
     */
    protected $definitions = null;

    /**
     * @param $definitions
     *
     * @return $this
     */
    public function setDefinitions($definitions)
    {
        $this->definitions = $definitions;

        return $this;
    }

    /**
     * @return Model\User\Permission\Definition[]
     */
    public function getDefinitions()
    {
        if($this->definitions === null) {
            $this->getDao()->load();
        }

        return $this->definitions;
    }
}
