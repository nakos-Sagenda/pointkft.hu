<?php

namespace Drupal\gdpr_consent\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\gdpr_consent\Entity\ConsentAgreementInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for reverting a Consent Agreement revision.
 *
 * @ingroup gdpr_consent
 */
class ConsentAgreementRevisionRevertForm extends ConfirmFormBase {


  /**
   * The Consent Agreement revision.
   *
   * @var \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   */
  protected $revision;

  /**
   * The Consent Agreement storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $consentAgreementStorage;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The date time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $dateTime;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('gdpr_consent_agreement'),
      $container->get('date.formatter'),
      $container->get('datetime.time'),
      $container->get('messenger')
    );
  }

  /**
   * Constructs a new ConsentAgreementRevisionRevertForm.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $entityStorage
   *   The Consent Agreement storage.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $dateFormatter
   *   The date formatter service.
   * @param \Drupal\Component\Datetime\TimeInterface $date_time
   *   The date time service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(
    EntityStorageInterface $entityStorage,
    DateFormatterInterface $dateFormatter,
    TimeInterface $date_time,
    MessengerInterface $messenger,
  ) {
    $this->consentAgreementStorage = $entityStorage;
    $this->dateFormatter = $dateFormatter;
    $this->dateTime = $date_time;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gdpr_consent_agreement_revision_revert_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to revert to the revision from %revision-date?', ['%revision-date' => $this->dateFormatter->format($this->revision->getRevisionCreationTime())]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.gdpr_consent_agreement.version_history', ['gdpr_consent_agreement' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Revert');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $gdpr_consent_agreement_revision = NULL) {
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $consentAgreementStorage */
    $consentAgreementStorage = $this->consentAgreementStorage;
    $this->revision = $consentAgreementStorage->loadRevision($gdpr_consent_agreement_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // The revision timestamp will be updated when the revision is saved. Keep
    // the original one for the confirmation message.
    $originalRevisionTimestamp = $this->revision->getRevisionCreationTime();

    $this->revision = $this->prepareRevertedRevision($this->revision, $form_state);
    $this->revision->revision_log = $this->t('Copy of the revision from %date.', [
      '%date' => $this->dateFormatter->format($originalRevisionTimestamp),
    ]);
    $this->revision->save();

    $this->logger('content')->notice('Consent Agreement: reverted %title revision %revision.', [
      '%title' => $this->revision->label(),
      '%revision' => $this->revision->getRevisionId(),
    ]);
    $this->messenger->addMessage($this->t('Consent Agreement %title has been reverted to the revision from %revision-date.', [
      '%title' => $this->revision->label(),
      '%revision-date' => $this->dateFormatter->format($originalRevisionTimestamp),
    ]));
    $form_state->setRedirect(
      'entity.gdpr_consent_agreement.version_history',
      ['gdpr_consent_agreement' => $this->revision->id()]
    );
  }

  /**
   * Prepares a revision to be reverted.
   *
   * @param \Drupal\gdpr_consent\Entity\ConsentAgreementInterface $revision
   *   The revision to be reverted.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\gdpr_consent\Entity\ConsentAgreementInterface
   *   The prepared revision ready to be stored.
   */
  protected function prepareRevertedRevision(ConsentAgreementInterface $revision, FormStateInterface $form_state) {
    $revision->setNewRevision();
    $revision->isDefaultRevision(TRUE);
    $revision->setRevisionCreationTime($this->dateTime->getRequestTime());

    return $revision;
  }

}
