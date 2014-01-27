<?php
/**
 * @file
 * Crontab cron job scheduler for Ultimate Cron.
 */

/**
 * Crontab scheduler.
 */
class UltimateCronCrontabScheduler extends UltimateCronScheduler {
  /**
   * Default settings.
   */
  public function defaultSettings() {
    return array(
      'rules' => array('*/10+@ * * * *'),
      'catch_up' => '300',
    );
  }

  /**
   * Label for schedule.
   */
  public function formatLabel($job) {
    $settings = $job->getSettings();
    return implode(', ', $settings[$this->type][$this->name]['rules']);
  }

  /**
   * Label for schedule.
   */
  public function formatLabelVerbose($job) {
    $settings = $job->getSettings($this->type);
    $parsed = array();

    include_once drupal_get_path('module', 'ultimate_cron') . '/CronRule.class.php';
    foreach ($settings['rules'] as $rule); {
      $cron = new CronRule($rule);
      $cron->offset = $this->getOffset($job);
      $parsed[] = $cron->parseRule();
    }
    return implode(', ', $parsed);
  }

  /**
   * Settings form for the crontab scheduler.
   */
  public function settingsForm(&$form, &$form_state) {
    $elements = &$form['settings'][$this->type][$this->name];
    $values = &$form_state['values']['settings'][$this->type][$this->name];

    $rules = is_array($values['rules']) ? implode(',', $values['rules']) : '';

    $elements['rules'] = array(
      '#title' => t("Rules"),
      '#type' => 'textfield',
      '#default_value' => $rules,
      '#description' => t('Comma separated list of crontab rules.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
      '#element_validate' => array('ultimate_cron_plugin_crontab_element_validate_rule'),
    );
    $elements['catch_up'] = array(
      '#title' => t("Catch up"),
      '#type' => 'textfield',
      '#default_value' => $values['catch_up'],
      '#description' => t('Dont run job after X seconds of rule.'),
      '#fallback' => TRUE,
      '#required' => TRUE,
    );
  }

  /**
   * Submit handler.
   */
  public function settingsFormSubmit(&$form, &$form_state) {
    $values = &$form_state['values']['settings'][$this->type][$this->name];

    if (!empty($values['rules'])) {
      $rules = explode(',', $values['rules']);
      $values['rules'] = array_map('trim', $rules);
    }
  }

  /**
   * Schedule handler.
   */
  public function schedule($job) {
    include_once drupal_get_path('module', 'ultimate_cron') . '/CronRule.class.php';
    $settings = $job->getSettings($this->type);

    foreach ($settings['rules'] as $rule) {
      $now = time();
      $cron = new CronRule($rule);
      $cron->offset = $this->getOffset($job);
      $cron_last_ran = $cron->getLastRan($now);
      $log_entry = isset($job->log_entry) ? $job->log_entry : $job->loadLatestLogEntry();
      $job_last_ran = $log_entry->start_time;

      if ($cron_last_ran >= $job_last_ran && $now >= $job_last_ran) {
        if ($now <= $cron_last_ran + $settings['catch_up']) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Determine if job is behind schedule.
   */
  public function isBehind($job) {
    include_once drupal_get_path('module', 'ultimate_cron') . '/CronRule.class.php';
    $settings = $job->getSettings($this->type);

    foreach ($settings['rules'] as $rule) {
      $now = time();
      $cron = new CronRule($rule);
      $cron->offset = $this->getOffset($job);
      $cron_last_ran = $cron->getLastRan($now);
      $log_entry = isset($job->log_entry) ? $job->log_entry : $job->loadLatestLogEntry();
      $job_last_ran = $log_entry->start_time;

      if ($cron_last_ran >= $job_last_ran && $now >= $job_last_ran + $settings['catch_up']) {
        return $now - $job_last_ran;
      }
    }
    return FALSE;
  }

  /**
   * Get a "unique" offset for a job.
   */
  protected function getOffset($job) {
    return hexdec(substr(sha1($job->name), -2));
  }
}
