<?php

namespace Api\Extension\Events;

use App\Events\Event;
use Api\Extension\Models\Extension;

/*
class ExtensionWasUpdated extends Event
{
    public $extension;

    public function __construct(Extension $extension)
    {
        $this->extension = $extension;
    }
}
*/

class ExtensionWasUpdated extends Event
{
    public $object;

    public $clearCacheUri;

    public function __construct(Extension $object, $clearCacheUri = null)
    {
      $this->object = $object;
      $this->clearCacheUri = $clearCacheUri;
    }
}
