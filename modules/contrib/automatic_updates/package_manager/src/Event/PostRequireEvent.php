<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Event;

/**
 * Event fired after packages are updated to the stage directory.
 */
class PostRequireEvent extends StageEvent {

  use RequireEventTrait;

}
