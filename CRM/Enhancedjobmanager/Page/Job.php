<?php
use CRM_Enhancedjobmanager_ExtensionUtil as E;
require_once  E::path('vendor/autoload.php');


use Lorisleiva\CronTranslator\CronTranslator;

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC https://civicrm.org/licensing
 */

/**
 * Page for displaying list of jobs with enhanced UI features.
 */
class CRM_Enhancedjobmanager_Page_Job extends CRM_Admin_Page_Job {

  /**
   * The action links that we need to display for the browse screen.
   *
   * @var array
   */
  public static $_links;

  /**
   * Get BAO Name.
   *
   * @return string
   *   Classname of BAO.
   */
  public function getBAOName() {
    return 'CRM_Core_BAO_Job';
  }

  /**
   * Get action Links.
   *
   * @return array
   *   (reference) of action links
   */
  public function &links() {
    if (!(self::$_links)) {
      self::$_links = [
        CRM_Core_Action::FOLLOWUP => [
          'name' => ts('View Job Log'),
          'url' => 'civicrm/admin/joblog',
          'qs' => 'jid=%%id%%&reset=1',
          'title' => ts('See log entries for this Scheduled Job'),
          'weight' => CRM_Core_Action::getWeight(CRM_Core_Action::VIEW),
        ],
        CRM_Core_Action::VIEW => [
          'name' => ts('Execute'),
          'url' => 'civicrm/admin/job/edit',
          'qs' => 'action=view&id=%%id%%&reset=1',
          'title' => ts('Execute Scheduled Job Now'),
          'weight' => -15,
        ],
        CRM_Core_Action::UPDATE => [
          'name' => ts('Edit'),
          'url' => 'civicrm/admin/job/edit',
          'qs' => 'action=update&id=%%id%%&reset=1',
          'title' => ts('Edit Scheduled Job'),
          'weight' => CRM_Core_Action::getWeight(CRM_Core_Action::UPDATE),
        ],
        CRM_Core_Action::DISABLE => [
          'name' => ts('Disable'),
          'ref' => 'crm-enable-disable',
          'title' => ts('Disable Scheduled Job'),
          'weight' => CRM_Core_Action::getWeight(CRM_Core_Action::DISABLE),
        ],
        CRM_Core_Action::ENABLE => [
          'name' => ts('Enable'),
          'ref' => 'crm-enable-disable',
          'title' => ts('Enable Scheduled Job'),
          'weight' => CRM_Core_Action::getWeight(CRM_Core_Action::ENABLE),
        ],
        CRM_Core_Action::DELETE => [
          'name' => ts('Delete'),
          'url' => 'civicrm/admin/job/edit',
          'qs' => 'action=delete&id=%%id%%',
          'title' => ts('Delete Scheduled Job'),
          'weight' => CRM_Core_Action::getWeight(CRM_Core_Action::DELETE),
        ],
        CRM_Core_Action::COPY => [
          'name' => ts('Copy'),
          'url' => 'civicrm/admin/job/edit',
          'qs' => 'action=copy&id=%%id%%&qfKey=%%key%%',
          'title' => ts('Copy Scheduled Job'),
          'weight' => CRM_Core_Action::getWeight(CRM_Core_Action::COPY),
        ],
      ];
    }
    return self::$_links;
  }

  /**
   * Run the page.
   *
   * This method is called after the page is created. It checks for the
   * type of action and executes that action.
   * Finally it calls the parent's run method.
   */
  public function run() {
    CRM_Utils_System::setTitle(ts('Settings - Scheduled Jobs'));
    //exit;
    CRM_Utils_System::appendBreadCrumb([
      [
        'title' => ts('Administer'),
        'url' => CRM_Utils_System::url('civicrm/admin', 'reset=1'),
      ],
    ]);

    $this->_id = CRM_Utils_Request::retrieve('id', 'String',
      $this, FALSE, 0
    );
    $this->_action = CRM_Utils_Request::retrieve('action', 'String',
      $this, FALSE, 0
    );

    // Handle bulk actions
    $bulkAction = CRM_Utils_Request::retrieve('bulk_action', 'String', $this);
    $selectedJobs = CRM_Utils_Request::retrieve('selected_jobs', 'String', $this);

    if ($bulkAction && $selectedJobs) {
      $this->handleBulkAction($bulkAction, explode(',', $selectedJobs));
    }

    // Handle AJAX requests for job status updates
    if (CRM_Utils_Request::retrieve('ajax', 'Boolean', $this)) {
      $this->handleAjaxRequest();
      return;
    }

    if (($this->_action & CRM_Core_Action::COPY) && (!empty($this->_id))) {
      $key = $_POST['qfKey'] ?? $_GET['qfKey'] ?? $_REQUEST['qfKey'] ?? NULL;
      $k = CRM_Core_Key::validate($key, CRM_Utils_System::getClassName($this));
      if (!$k) {
        $this->invalidKey();
      }
      try {
        $jobResult = civicrm_api3('Job', 'clone', ['id' => $this->_id]);
        if ($jobResult['count'] > 0) {
          CRM_Core_Session::setStatus($jobResult['values'][$jobResult['id']]['name'], ts('Job copied successfully'), 'success');
        }
        CRM_Utils_System::redirect(CRM_Utils_System::url('civicrm/admin/job', 'reset=1'));
      }
      catch (Exception $e) {
        CRM_Core_Session::setStatus(ts('Failed to copy job'), 'Error');
      }
    }

    return parent::run();
  }

  /**
   * Handle bulk actions on selected jobs.
   *
   * @param string $action
   * @param array $jobIds
   */
  private function handleBulkAction($action, $jobIds) {
    $successCount = 0;
    $errorCount = 0;

    foreach ($jobIds as $jobId) {
      try {
        switch ($action) {
          case 'enable':
            civicrm_api3('Job', 'create', ['id' => $jobId, 'is_active' => 1]);
            $successCount++;
            break;
          case 'disable':
            civicrm_api3('Job', 'create', ['id' => $jobId, 'is_active' => 0]);
            $successCount++;
            break;
          case 'run':
            civicrm_api3('Job', 'execute', ['id' => $jobId]);
            $successCount++;
            break;
        }
      } catch (Exception $e) {
        $errorCount++;
      }
    }

    if ($successCount > 0) {
      CRM_Core_Session::setStatus(
        ts('%1 jobs processed successfully', [1 => $successCount]),
        ts('Bulk Action Complete'),
        'success'
      );
    }

    if ($errorCount > 0) {
      CRM_Core_Session::setStatus(
        ts('%1 jobs failed to process', [1 => $errorCount]),
        ts('Bulk Action Errors'),
        'error'
      );
    }
  }

  /**
   * Handle AJAX requests.
   */
  private function handleAjaxRequest() {
    $action = CRM_Utils_Request::retrieve('ajax_action', 'String', $this);

    switch ($action) {
      case 'get_job_status':
        $this->getJobStatusData();
        break;
      case 'search_jobs':
        $this->searchJobs();
        break;
    }
  }

  /**
   * Get job status data for dashboard.
   */
  private function getJobStatusData() {
    $jobs = $this->getJobs();
    $status = [
      'total' => count($jobs),
      'active' => 0,
      'inactive' => 0,
      'errors' => 0,
      'running' => 0,
    ];

    foreach ($jobs as $job) {
      if ($job->is_active) {
        $status['active']++;
        // Check if job is currently running (simplified check)
        if ($this->isJobRunning($job)) {
          $status['running']++;
        }
        // Check for recent errors
        if ($this->hasRecentErrors($job)) {
          $status['errors']++;
        }
      } else {
        $status['inactive']++;
      }
    }

    CRM_Core_Page_AJAX::returnJsonResponse($status);
  }

  /**
   * Search jobs based on criteria.
   */
  private function searchJobs() {
    $search = CRM_Utils_Request::retrieve('search', 'String', $this);
    $status = CRM_Utils_Request::retrieve('status_filter', 'String', $this);
    $frequency = CRM_Utils_Request::retrieve('frequency_filter', 'String', $this);

    $jobs = $this->getJobs();
    $filteredJobs = [];

    foreach ($jobs as $job) {
      // Apply search filter
      if ($search && (
          stripos($job->name, $search) === FALSE &&
          stripos($job->description, $search) === FALSE &&
          stripos($job->api_entity, $search) === FALSE &&
          stripos($job->api_action, $search) === FALSE
        )) {
        continue;
      }

      // Apply status filter
      if ($status && $status !== 'all') {
        if (($status === 'active' && !$job->is_active) ||
          ($status === 'inactive' && $job->is_active)) {
          continue;
        }
      }

      // Apply frequency filter
      if ($frequency && $frequency !== 'all') {
        if (stripos($job->run_frequency, $frequency) === FALSE) {
          continue;
        }
      }

      $filteredJobs[] = $job;
    }

    CRM_Core_Page_AJAX::returnJsonResponse(['jobs' => $filteredJobs]);
  }

  /**
   * Check if a job is currently running.
   *
   * @param CRM_Core_ScheduledJob $job
   * @return bool
   */
  private function isJobRunning($job) {
    // Check if the job has a last run time and no end time
    if ($job->last_run && empty($job->last_run_end)) {
      if (strtotime($job->last_run) > strtotime('-5 minutes')) {
        // If the last run was within the last 5 minutes, consider it running
        return TRUE;
      }
    }
    return FALSE;
  }

  private function isUnsuccessful($job) {
    // Check if the job has a last run time and  last_run > last_run_end
    if ($job->is_active && !empty($job->last_run) &&
      ((!empty($job->last_run_end) && strtotime($job->last_run) > strtotime($job->last_run_end))
        || (empty($job->last_run_end) && strtotime($job->last_run) > strtotime('-5 minutes'))
      )) {
      // If the last run was within the last 5 minutes, consider it unsuccessful
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check last run time duration time
   * @param $job
   * @return string
   */
  private function runDuration($job) {
    // Check if the job has a last run time and  last_run > last_run_end
    if ($job->is_active && !empty($job->last_run) && !empty($job->last_run_end) && strtotime($job->last_run_end) > strtotime($job->last_run)) {
      // get duration now.
      $diff = strtotime($job->last_run_end) - strtotime($job->last_run);
      if ($diff < 0) {
        return 'unknown';
      }
      if ($diff < 60) {
        return $diff . ' seconds';
      }
      if ($diff < 3600) {
        return floor($diff / 60) . ' minutes';
      }
      if ($diff < 86400) {
        return floor($diff / 3600) . ' hours';
      }
      if ($diff < 604800) {
        return floor($diff / 86400) . ' days';
      }
      return floor($diff / 604800) . ' weeks';
    }
    return '';
  }

  /**
   * Check if a job has recent errors.
   *
   * @param CRM_Core_ScheduledJob $job
   * @return bool
   */
  private function hasRecentErrors($job) {
    try {
      $logs = civicrm_api3('JobLog', 'get', [
        'job_id' => $job->id,
        'run_time' => ['>' => date('Y-m-d H:i:s', strtotime('-24 hours'))],
        'options' => ['limit' => 5, 'sort' => 'run_time DESC'],
      ]);
      if ($logs['count'] == 0) {
        return FALSE;
      }
      $errorStrings = ['failed', 'DB Error', 'unknown error', 'Failure'];
      foreach ($logs['values'] as $log) {
        foreach ($errorStrings as $errorString) {
          if (!empty($log['data']) && str_contains($log['data'], $errorString)) {
            return TRUE;
          }
        }
      }
    } catch (Exception $e) {
      // Ignore API errors
    }

    return FALSE;
  }

  /**
   * Browse all jobs with enhanced data.
   */
  public function browse() {
    // check if non-prod mode is enabled.
    if (CRM_Core_Config::environment() != 'Production') {
      CRM_Core_Session::setStatus(ts('Execution of scheduled jobs has been turned off by default since this is a non-production environment. You can override this for particular jobs by adding runInNonProductionEnvironment=TRUE as a parameter. Note: this will send emails if your <a %1>outbound email</a> is enabled.', [1 => 'href="' . CRM_Utils_System::url('civicrm/admin/setting/smtp', 'reset=1') . '"']), ts('Non-production Environment'), 'warning', ['expires' => 0]);
    }
    else {
      $cronError = Civi\Api4\System::check(FALSE)
        ->addWhere('name', '=', 'checkLastCron')
        ->addWhere('severity_id', '>', 1)
        ->setIncludeDisabled(TRUE)
        ->execute()
        ->first();
      if ($cronError) {
        CRM_Core_Session::setStatus($cronError['message'], $cronError['title'], 'alert', ['expires' => 0]);
      }
    }

    $rows = [];
    $statusCounts = [
      'total' => 0,
      'active' => 0,
      'inactive' => 0,
      'errors' => 0,
      'running' => 0,
      'unsuccessful' => 0,
    ];

    foreach ($this->getJobs() as $job) {
      $action = array_sum(array_keys($this->links()));

      // update enable/disable links.
      // CRM-9868- remove enable action for jobs that should never be run automatically via execute action or runjobs url
      if ($job->api_action === 'process_membership_reminder_date' || $job->api_action === 'update_greeting') {
        $action -= CRM_Core_Action::ENABLE;
        $action -= CRM_Core_Action::DISABLE;
        if ($job->is_active) {
          $statusCounts['active']++;
        }
        else {
          $statusCounts['inactive']++;
        }
      }
      elseif ($job->is_active) {
        $action -= CRM_Core_Action::ENABLE;
        $statusCounts['active']++;
      }
      else {
        $action -= CRM_Core_Action::DISABLE;
        $statusCounts['inactive']++;
      }

      $job->action = CRM_Core_Action::formLink($this->links(), $action,
        ['id' => $job->id, 'key' => CRM_Core_Key::get(CRM_Utils_System::getClassName($this))],
        ts('more'),
        FALSE,
        'job.manage.action',
        'Job',
        $job->id
      );

      // Enhanced data for UI
      $jobData = get_object_vars($job);
      // Add success rate calculation
      $jobData['success_rate'] = $this->calculateSuccessRate($job);

      // Add next run prediction
      $jobData['next_run'] = $this->predictNextRun($job);

      // Add status class for styling
      $jobData['status_class'] = $job->is_active ? 'active' : 'inactive';

      // Add running status
      $jobData['status_execution'] = [];
      $jobData['is_running'] = $this->isJobRunning($job);
      if ($jobData['is_running']) {
        $jobData['status_execution']['running'] = 'running';
        $statusCounts['running']++;
      }

      // Add error status
      $jobData['has_errors'] = $this->hasRecentErrors($job);
      if ($jobData['has_errors']) {
        $jobData['status_execution']['error'] = 'error';
        $statusCounts['errors']++;
      }

      // Add error status
      if ($this->isUnsuccessful($job)) {
        $jobData['status_execution']['unsuccessful'] = 'unsuccessful';
        $statusCounts['unsuccessful']++;
      }
      $jobData['status_execution'] = implode(" ", $jobData['status_execution']);

      // Format last run time
      if ($job->last_run) {
        $jobData['last_run_formatted'] = CRM_Utils_Date::customFormat($job->last_run);
        $jobData['last_run_relative'] = $this->getRelativeTime($job->last_run);
        $jobData['last_run_timestamp'] = strtotime($job->last_run); // Convert to Unix timestamp
      }
      else {
        $jobData['last_run_timestamp'] = NULL;
      }

      $jobData['last_run_duration'] = $this->runDuration($job);

      if (property_exists($job, 'crontab_apply') && $job->crontab_apply && $job->crontab_frequency) {
        $jobData['run_frequency'] = $this->cronToHuman($job->crontab_frequency);
        if ($job->crontab_date_time_start) {
          $jobData['crontab_date_time_start'] = CRM_Utils_Date::customFormat($job->crontab_date_time_start);
        }
        if ($job->crontab_date_time_end) {
          $jobData['crontab_date_time_end'] = CRM_Utils_Date::customFormat($job->crontab_date_time_end);
        }
        if ($job->crontab_time_from) {
          $jobData['crontab_time_from'] = date('g:i A', strtotime($job->crontab_time_from));
        }
        if ($job->crontab_time_to) {
          $jobData['crontab_time_to'] = date('g:i A', strtotime($job->crontab_time_to));
        }
        // combine all express together

        // Start with the base run frequency
        $cronExpression = '<div class="job-frequency-main">' .$jobData['run_frequency'] . '</div>';
        $additionalInfo = [];

        // Add date range if specified
        if (!empty($jobData['crontab_date_time_start']) || !empty($jobData['crontab_date_time_end'])) {
          $dateRange = '<span class="constraint-value">';
          if (!empty($jobData['crontab_date_time_start']) && !empty($jobData['crontab_date_time_end'])) {
            $dateRange .= 'Between ' . $jobData['crontab_date_time_start'] . ' and ' . $jobData['crontab_date_time_end'];
          }
          elseif (!empty($jobData['crontab_date_time_start'])) {
            $dateRange .= 'From ' . $jobData['crontab_date_time_start'];
          }
          elseif (!empty($jobData['crontab_date_time_end'])) {
            $dateRange .= 'Until ' . $jobData['crontab_date_time_end'];
          }
          $dateRange .= '</span>';
          $additionalInfo[] = '<div class="job-date-range"><i class="crm-i fa-calendar" aria-hidden="true"></i><span class="constraint-label">Date:</span>' . $dateRange . '</div>';
        }
        // Add time window if specified
        if (!empty($jobData['crontab_time_from']) || !empty($jobData['crontab_time_to'])) {
          $timeWindow = '<span class="constraint-value">';

          if (!empty($jobData['crontab_time_from']) && !empty($jobData['crontab_time_to'])) {
            $timeWindow .= 'Between ' . $jobData['crontab_time_from'] . ' and ' . $jobData['crontab_time_to'];
          }
          elseif (!empty($jobData['crontab_time_from'])) {
            $timeWindow .= 'From ' . $jobData['crontab_time_from'];
          }
          elseif (!empty($jobData['crontab_time_to'])) {
            $timeWindow .= 'Until ' . $jobData['crontab_time_to'];
          }
          $timeWindow .= '</span>';

          $additionalInfo[] = '<div class="job-time-window"><i class="crm-i fa-clock-o" aria-hidden="true"></i><span class="constraint-label">Time Window:</span>' . $timeWindow . '</div>';
        }

        // Combine everything
        if (!empty($additionalInfo)) {
          $jobData['crontab_expression'] = '<div class="job-constraints">' .
            '<span class="crm-cron-expression">' . $cronExpression . '</span>' .
            '<div class="crm-cron-additional-info">' .
            implode('', $additionalInfo) .
            '</div></div>';
          $cronExpression . '<br/>' . implode('<br/>', $additionalInfo);
        } else {
          $jobData['crontab_expression'] = $cronExpression;
        }
        $jobData['run_frequency'] = '<div class="job-frequency-container">' .
        $jobData['crontab_expression'] . '</div>';
        $jobData['run_frequency_label'] = 'custom';
      }
      else {
        // Fallback to simple run frequency
        $jobData['run_frequency_label'] =  strtolower($jobData['run_frequency']);
      }


      $statusCounts['total']++;
      $rows[] = $jobData;
    }

    // Assign data to template
    $this->assign('rows', $rows);
    $this->assign('statusCounts', $statusCounts);

    // Add filter options
    $this->assign('statusOptions', [
      'all' => ts('All Statuses'),
      'active' => ts('Active'),
      'inactive' => ts('Inactive'),
      'error' => ts('Error'),
      'running' => ts('Running'),
      'unsuccessful' => ts('Unsuccessful'),
    ]);

    $this->assign('frequencyOptions', [
      'all' => ts('All Frequencies'),
      'always' => ts('Always'),
      'hourly' => ts('Hourly'),
      'daily' => ts('Daily'),
      'weekly' => ts('Weekly'),
      'monthly' => ts('Monthly'),
      'quarterly' => ts('Quarterly'),
      'yearly' => ts('Yearly'),
      'custom' => ts('Custom'),
    ]);
  }

  /**
   * Calculate success rate for a job.
   *
   * @param CRM_Core_ScheduledJob $job
   * @return float
   */
  private function calculateSuccessRate($job) {
    try {
      $logs = civicrm_api3('JobLog', 'get', [
        'job_id' => $job->id,
        'run_time' => ['>' => date('Y-m-d H:i:s', strtotime('-30 days'))],
        'options' => ['limit' => 100],
      ]);

      if ($logs['count'] == 0) {
        return NULL;
      }

      $successful = 0;
      foreach ($logs['values'] as $log) {
        if (empty($log['data']) || stripos($log['data'], 'error') === FALSE) {
          $successful++;
        }
      }

      return round(($successful / $logs['count']) * 100, 1);
    }
    catch (Exception $e) {
      return NULL;
    }
  }

  /**
   * Predict next run time for a job.
   *
   * @param CRM_Core_ScheduledJob $job
   * @return string|null
   */
  public function predictNextRun($job) {
    if (!$job->is_active) {
      return NULL;
    }
    // Check if run_frequency is a cron expression
    if (property_exists($job, 'crontab_apply') && $job->crontab_apply && $job->crontab_frequency) {
      try {
        $cron = Cron\CronExpression::factory($job->crontab_frequency);
        $fromTime = $job->last_run ? new DateTime($job->last_run) : new DateTime();
        // Get next run time
        $nextRun = $cron->getNextRunDate($fromTime);
        return $this->getFutureRelativeTime($nextRun->getTimestamp());
      }
      catch (Exception $e) {
        // Fallback to simple prediction
      }

    }

    // This is a simplified prediction - in reality you'd parse the run_frequency
    // and calculate based on cron schedule
    $frequency = strtolower($job->run_frequency);

    if (strpos($frequency, 'hourly') !== FALSE) {
      return ts('Next hour');
    }
    elseif (strpos($frequency, 'daily') !== FALSE) {
      return ts('Tomorrow');
    }
    elseif (strpos($frequency, 'weekly') !== FALSE) {
      return ts('Next week');
    }
    elseif (strpos($frequency, 'monthly') !== FALSE) {
      return ts('Next month');
    }
    elseif (strpos($frequency, 'quarterly') !== FALSE) {
      return ts('Next quarter');
    }
    elseif (strpos($frequency, 'yearly') !== FALSE) {
      return ts('Next year');
    }

    return ts('Scheduled');
  }

  /**
   * Get relative time string.
   *
   * @param string $datetime
   * @return string
   */
  private function getRelativeTime($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
      return ts('%1 seconds ago', [1 => $diff]);
    }
    elseif ($diff < 3600) {
      return ts('%1 minutes ago', [1 => floor($diff / 60)]);
    }
    elseif ($diff < 86400) {
      return ts('%1 hours ago', [1 => floor($diff / 3600)]);
    }
    else {
      return ts('%1 days ago', [1 => floor($diff / 86400)]);
    }
  }

  function getFutureRelativeTime($timestamp) {
    $diff = $timestamp - time();

    if ($diff < 0) return 'Overdue';
    if ($diff < 60) return 'In ' . $diff . ' seconds';
    if ($diff < 3600) return 'In ' . floor($diff / 60) . ' minutes';
    if ($diff < 86400) return 'In ' . floor($diff / 3600) . ' hours';
    if ($diff < 604800) return 'In ' . floor($diff / 86400) . ' days';
    return 'In ' . floor($diff / 604800) . ' weeks';
  }

  protected function cronToHuman($cronExpression) {
    try {
      $expression = \Sivaschenko\Utility\Cron\ExpressionFactory::getExpression($cronExpression);
      return $expression->getVerbalString();
    }
    catch (\InvalidArgumentException $e) {
      // Fallback to CronTranslator if ExpressionFactory fails
      // This is a more user-friendly translation
      return $cronExpression;
    }
    catch (Exception $e) {
      // If there's an error, return the original expression
      // This is a fallback to avoid breaking the UI
      return $cronExpression;
    }
    //return CronTranslator::translate($cronExpression);
  }

  /**
   * Retrieves the list of jobs from the database,
   * populates class param.
   *
   * @fixme: Copied from JobManager. We should replace with API
   *
   * @return array
   *   ($id => CRM_Core_ScheduledJob)
   */
  private function getJobs(): array {
    $jobs = [];
    $class = class_exists('CRM_Crontab_ScheduledJob') ? 'CRM_Crontab_ScheduledJob' : 'CRM_Core_ScheduledJob';
    if ($class == 'CRM_Core_ScheduledJob') {
      $obj = new $class([]);
      // To avoid php deprecation warning check property exist.
      if (!property_exists($obj, 'last_run_end')) {
        $class = 'CRM_Enhancedjobmanager_ScheduledJob';
      }
    }

    $jobList = Civi\Api4\Job::get(FALSE)
      ->addWhere('domain_id', '=', CRM_Core_Config::domainID())
      ->addOrderBy('name', 'ASC')
      ->execute()
      ->indexBy('id')
      ->getArrayCopy();

    foreach ($jobList as $id => $job) {
      $job['parameters'] = $job['parameters'] ?? NULL;
      $job['last_run'] = $job['last_run'] ?? NULL;
      $jobs[$id] = new $class($job);
      if (!property_exists($jobs[$id], 'last_run_end')) {
        $jobs[$id]->last_run_end = $job['last_run_end'] ?? NULL;
      }
    }
    return $jobs;
  }

  /**
   * Get name of edit form.
   *
   * @return string
   *   Classname of edit form.
   */
  public function editForm() {
    return 'CRM_Admin_Form_Job';
  }

  /**
   * Get edit form name.
   *
   * @return string
   *   name of this page.
   */
  public function editName() {
    return 'Scheduled Jobs';
  }

  /**
   * Get user context.
   *
   * @param null $mode
   *
   * @return string
   *   user context.
   */
  public function userContext($mode = NULL) {
    return 'civicrm/admin/job';
  }

}
