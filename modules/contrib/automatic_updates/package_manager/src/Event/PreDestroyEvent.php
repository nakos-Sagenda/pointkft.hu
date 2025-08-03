<?php

declare(strict_types = 1);

namespace Drupal\package_manager\Event;

/**
 * Event fired before the stage directory is destroyed.
 *
 * If the stage is being force destroyed, ::getStage() may return an object of a
 * different class than the one that originally created the stage directory.
 *
 * @see \Drupal\package_manager\Stage::destroy()
 */
class PreDestroyEvent extends PreOperationStageEvent {
}
