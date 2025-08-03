<?php

/**
 * @file
 * Hooks provided by the GDPR compliance module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Policy page alter hook.
 *
 * Gives ability to alter the content and the context of the rendered page.
 *
 * @param string $policy
 *   Raw content of the page.
 * @param string[] $context
 *   Values that replace related tokens in the raw content. Array contains
 *   'changed', 'mail', 'url' keys to alter.
 */
function hook_gdpr_compliance_policy_alter(&$policy, array &$context) {
  $context['mail'] = 'mail@example.org';
}

/**
 * @} End of "addtogroup hooks".
 */
