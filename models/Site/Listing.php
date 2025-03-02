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
 * @package    Site
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Model\Site;

use Pimcore\Model;

/**
 * @method \Pimcore\Model\Site\Listing\Dao getDao()
 * @method Model\Site[] load()
 */
class Listing extends Model\Listing\AbstractListing
{
    /**
     * @var array|null
     */
    protected $sites = null;

    /**
     * @return Model\Site[]
     */
    public function getSites()
    {
        if($this->sites === null) {
            $this->getDao()->load();
        }

        return $this->sites;
    }

    /**
     * @param array $sites
     *
     * @return $this
     */
    public function setSites($sites)
    {
        $this->sites = $sites;

        return $this;
    }
}
