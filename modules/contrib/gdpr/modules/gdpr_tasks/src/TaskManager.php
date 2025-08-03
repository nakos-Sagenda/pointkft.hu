<?php

namespace Drupal\gdpr_tasks;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Session\AccountProxy;

/**
 * Defines a helper class for stuff related to views data.
 */
class TaskManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $taskStorage;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * Filesystem.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Constructs a TaskManager object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountProxy $currentUser
   *   The current user service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Filesystem.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManager $entityTypeManager,
    AccountProxy $currentUser,
    FileSystemInterface $fileSystem
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->taskStorage = $entityTypeManager->getStorage('gdpr_task');
    $this->currentUser = $currentUser;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Fetch tasks for a certain user.
   *
   * @param null|\Drupal\Core\Session\AccountInterface $account
   *   The user account to get tasks for. Defaults to current user.
   * @param null|string $type
   *   Optionally filter by task type.
   *
   * @return array|\Drupal\gdpr_tasks\Entity\TaskInterface[]
   *   Array of fully loaded task entities.
   */
  public function getUserTasks($account = NULL, $type = NULL) {
    $tasks = [];

    if (!$account) {
      $account = $this->currentUser->getAccount();
    }

    $query = $this->taskStorage->getQuery()->accessCheck(TRUE);
    $query->condition('user_id', $account->id(), '=');

    if ($type) {
      $query->condition('type', $type, '=');
    }

    if (!empty($ids = $query->execute())) {
      $tasks = $this->taskStorage->loadMultiple($ids);
    }

    return $tasks;
  }

  /**
   * Writes array data to a csv file.
   *
   * @param array $data
   *   The data to be stored in csv.
   * @param string $dirname
   *   The local path or stream wrapper for destination directory.
   *
   * @return string
   *   The uri path of the created file.
   */
  public function toCsv(array $data, $dirname = 'private://') {
    // Prepare destination.
    $this->fileSystem->prepareDirectory($dirname, FileSystemInterface::CREATE_DIRECTORY);

    // Generate a file entity.
    $random = new Random();
    $destination = $dirname . '/' . $random->name(10, TRUE) . '.csv';

    // Update csv with actual data.
    $fp = fopen($destination, 'wb');
    foreach ($data as $line) {
      fputcsv($fp, $line);
    }
    fclose($fp);

    return $destination;
  }

}
