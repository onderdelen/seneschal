<?php
/**
 * Group.php
 * Created by @anonymoussc on 12/02/16 4:42.
 */

namespace Componeint\Seneschal\Models;

use Hashids;

class Group extends \Cartalyst\Sentry\Groups\Eloquent\Group
{
    /**
     * Use a mutator to derive the appropriate hash for this group
     *
     * @return mixed
     */
    public function getHashAttribute()
    {
        return Hashids::encode($this->attributes['id']);
    }
}
